<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\WiSummaryExport;
use App\Exports\WiDetailExport;

class WiReportPdfController extends Controller
{
    // ====== TABLES ======
    protected string $qmTable = 'yppr058_data';   // DRIVER UTAMA (QM)
    protected string $wiTable = 'daily_time_wi';  // JOIN OPTIONAL (WI)

    // ====== WI COLUMNS ======
    protected string $wiNikColumn  = 'nik';
    protected string $wiNamaColumn = 'nama';
    protected string $wiDateColumn = 'tanggal';
    protected string $wiTimeColumn = 'total_time_wi';

    // QM (yppr058_data) => SUM(mintu)
    // kalau mau convert menit -> jam: set 60
    protected float $qmDivisor = 1.0;

    // role filter (driver)
    protected string $qmRoleValue = 'INDUK';

    // di WiReportPdfController
    protected string $dataVisibleFrom = '2026-01-01';

    protected function dataVisibleFromDate(): ?Carbon
    {
        $raw = trim((string) $this->dataVisibleFrom);
        if ($raw === '') return null;

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function clampStartByVisibleFrom(Carbon &$start, Carbon $end): void
    {
        $vf = $this->dataVisibleFromDate();
        if (!$vf) return;

        // sama seperti Livewire: clamp kalau vf masih masuk range
        if ($vf->lte($end) && $start->lt($vf)) {
            $start = $vf->copy();
        }
    }


    // =========================================================
    // DATE RANGE
    // =========================================================
    protected function getDateRangeForMonth(?string $monthFilter): array
    {
        $today = Carbon::today();
        $monthFilter = $monthFilter === 'prev' ? 'prev' : 'this';

        if ($monthFilter === 'prev') {
            $start = $today->copy()->subMonth()->startOfMonth();
            $end   = $today->copy()->subMonth()->endOfMonth();
        } else {
            if ($today->day === 1) {
                $start = $today->copy()->subMonth()->startOfMonth();
                $end   = $today->copy()->subMonth()->endOfMonth();
            } else {
                $start = $today->copy()->startOfMonth();
                $end   = $today->copy()->subDay();
            }
        }

        // ✅ penting: cutoff
        $this->clampStartByVisibleFrom($start, $end);

        return [$start, $end];
    }

    protected function getKorlapMembersData(string $plant, array $dateIso, array $dateYmd, array $selectedKorlapNiks, string $wiMode)
    {
        // 1. Ambil Data Master Korlap yang dipilih
        $korlaps = DB::table('nik_korlap')
            ->whereIn('nik', $selectedKorlapNiks)
            ->whereRaw('TRIM(plant) = ?', [$plant])
            ->select('nik', 'nama', 'wc_korlap')
            ->orderBy('nama')
            ->get();

        // 2. Kumpulkan semua WC
        $allWcs = [];
        foreach ($korlaps as $k) {
            $wcs = json_decode($k->wc_korlap ?? '[]', true);
            if (is_array($wcs)) {
                $allWcs = array_merge($allWcs, $wcs);
            }
        }
        $allWcs = array_unique($allWcs);

        if (empty($allWcs)) return collect([]);

        // 3. Query Member Data
        $joined = $this->joinedDayQuery($plant, $dateIso, $dateYmd, [], ''); 
        $joined->whereIn('qm.wc', $allWcs);

        $memberRows = DB::query()
            ->fromSub($joined, 'd')
            ->selectRaw("
                nik,
                MAX(nama) as nama,
                MAX(wc) as wc,
                MAX(devisi) as devisi,

                COALESCE(SUM(COALESCE(time_wi,0)),0) as time_wi_sum,
                COALESCE(SUM(COALESCE(time_conf,0)),0) as time_conf_sum,
                COALESCE(SUM(COALESCE(time_qm,0)),0) as time_qm_sum,

                -- HASIL QM (QM / WI)
                CASE
                    WHEN SUM(COALESCE(time_wi,0)) = 0 THEN 0
                    ELSE (SUM(COALESCE(time_qm,0)) / SUM(COALESCE(time_wi,0))) * 100
                END as kpi_quality_pct,

                -- HASIL WI (WI / CONF)
                CASE
                    WHEN SUM(COALESCE(time_wi,0)) = 0 THEN 0
                    ELSE (SUM(COALESCE(time_conf,0)) / SUM(COALESCE(time_wi,0))) * 100
                END as kpi_qty_pct
            ")
            ->groupBy('nik')
            ->get();

        // 4. Mapping & Calculating Group Totals
        $result = collect();

        foreach ($korlaps as $k) {
            $myWcs = json_decode($k->wc_korlap ?? '[]', true) ?: [];
            
            // Filter members milik korlap ini
            $myMembers = $memberRows->filter(function($m) use ($myWcs) {
                return in_array($m->wc, $myWcs);
            });

            // ✅ FILTERING: (Ada WI / Belum Ada WI)
            if ($wiMode === 'with') {
                $myMembers = $myMembers->filter(fn($m) => (float)$m->time_wi_sum > 0);
            } elseif ($wiMode === 'without') {
                $myMembers = $myMembers->filter(fn($m) => (float)$m->time_wi_sum == 0);
            }

            // ✅ JIKA ADA MEMBER SETELAH FILTER, HITUNG TOTAL ULANG UTK KORLAP
            if ($myMembers->isNotEmpty()) {
                
                // Hitung Total Agregat Berdasarkan Member yg Lolos Filter
                $totalWi   = $myMembers->sum('time_wi_sum');
                $totalConf = $myMembers->sum('time_conf_sum');
                $totalQm   = $myMembers->sum('time_qm_sum');
                
                // HASIL QM (QM/WI)
                $kpiQualityKorlap = ($totalWi == 0) ? 0 : ($totalQm / $totalWi) * 100;

                // HASIL WI (WI/CONF)
                $kpiQtyKorlap = ($totalWi == 0) ? 0 : ($totalConf / $totalWi) * 100;
                
                // Format WC jadi string
                sort($myWcs);
                $wcString = implode(', ', $myWcs);

                $result->push([
                    'korlap_nik'  => $k->nik,
                    'korlap_nama' => $k->nama,
                    
                    // Data Summary Korlap (Baris Induk)
                    'summary' => [
                        'wc_string'   => $wcString,
                        'count_nik'   => $myMembers->count(),
                        'total_wi'    => $totalWi,
                        'total_conf'  => $totalConf,
                        'total_qm'    => $totalQm,

                        'kpi_quality_pct' => $kpiQualityKorlap,
                        'kpi_qty_pct'     => $kpiQtyKorlap,
                    ],

                    'members'     => $myMembers->sortBy('nama')->values()
                ]);
            }
        }

        return $result;
    }

    // =========================================================
    // ✅ BARU: ACTION ROUTE EXPORT KORLAP
    // =========================================================
    public function exportKorlapPdf(Request $request, string $plant)
    {
        $plant = strtoupper(trim($plant));

        // Ambil session
        $monthFilter = (string)$request->session()->get('wi_export_korlap.month_filter', 'this');
        $korlaps     = (array)$request->session()->get('wi_export_korlap.korlaps', []);
        $wiMode      = (string)$request->session()->get('wi_export_korlap.wi_mode', 'all');

        $korlaps = array_values(array_filter(array_map('strval', $korlaps)));

        if (empty($korlaps)) abort(404, 'Tidak ada Korlap yang dipilih.');

        // Hitung Range Tanggal
        [$start, $end] = $this->getDateRangeForMonth($monthFilter);
        $range = $this->getRangeStrings($start, $end);

        // Ambil Data
        $data = $this->getKorlapMembersData($plant, $range['iso'], $range['ymd'], $korlaps, $wiMode);

        if ($data->isEmpty()) {
            return abort(404, 'Tidak ada data anggota tim yang sesuai kriteria filter untuk Korlap yang dipilih.');
        }

        // Hapus session
        $request->session()->forget(['wi_export_korlap.month_filter', 'wi_export_korlap.korlaps', 'wi_export_korlap.wi_mode', 'wi_export_korlap.q']);

        // Load View
        $pdf = Pdf::loadView('pdf.wi-korlap', [
            'data'       => $data,
            'plant'      => $plant,
            'rangeStart' => $range['iso'][0],
            'rangeEnd'   => $range['iso'][1],
            'wiMode'     => $wiMode
        ])->setPaper('a4', 'portrait'); // Bisa landscape kalau kolom banyak

        return $pdf->download("wi-korlap-report-{$plant}.pdf");
    }

    public function exportKorlapExcel(Request $request, string $plant)
    {
        $plant = strtoupper(trim($plant));

        // 1. Ambil session (sama seperti PDF)
        $monthFilter = (string)$request->session()->get('wi_export_korlap.month_filter', 'this');
        $korlaps     = (array)$request->session()->get('wi_export_korlap.korlaps', []);
        $wiMode      = (string)$request->session()->get('wi_export_korlap.wi_mode', 'all');

        $korlaps = array_values(array_filter(array_map('strval', $korlaps)));

        if (empty($korlaps)) abort(404, 'Tidak ada Korlap yang dipilih.');

        // 2. Hitung Range Tanggal
        [$start, $end] = $this->getDateRangeForMonth($monthFilter);
        $range = $this->getRangeStrings($start, $end);
        $rangeString = "{$range['iso'][0]} s.d {$range['iso'][1]}"; // String utk judul Excel

        // 3. Ambil Data (Pakai helper yang sama dengan PDF)
        $data = $this->getKorlapMembersData($plant, $range['iso'], $range['ymd'], $korlaps, $wiMode);

        if ($data->isEmpty()) {
            return abort(404, 'Tidak ada data anggota tim yang sesuai kriteria filter untuk Korlap yang dipilih.');
        }

        // 4. Hapus session
        $request->session()->forget(['wi_export_korlap.month_filter', 'wi_export_korlap.korlaps', 'wi_export_korlap.wi_mode', 'wi_export_korlap.q']);

        // 5. Download Excel
        // Pastikan namespace App\Exports\WiKorlapExport sudah di-import di atas controller
        return Excel::download(new \App\Exports\WiKorlapExport($data, $plant, $rangeString), "wi-korlap-report-{$plant}.xlsx");
    }


    protected function getRangeStrings(Carbon $start, Carbon $end): array
    {
        return [
            'iso' => [$start->toDateString(), $end->toDateString()], // YYYY-MM-DD
            'ymd' => [$start->format('Ymd'),   $end->format('Ymd')],  // YYYYMMDD
        ];
    }

    // =========================================================
    // SCOPE (SAMA SEPERTI LIVEWIRE) - OPTIONAL TAPI DISARANKAN
    // (mengikuti request attributes dari middleware)
    // =========================================================
    protected function scopeData(): array
    {
        $all   = (bool) request()->attributes->get('data_scope_all', false);
        $dev   = (array) request()->attributes->get('data_scope_devisi', []);
        $arbpl = (array) request()->attributes->get('data_scope_arbpl', []);

        $niks  = (array) request()->attributes->get('data_scope_nik', []);

        $dev = array_values(array_unique(array_filter(array_map(
            fn ($v) => strtoupper(trim((string) $v)),
            $dev
        ))));

        $arbpl = array_values(array_unique(array_filter(array_map(
            fn ($v) => strtoupper(trim((string) $v)),
            $arbpl
        ))));

        $niks = array_values(array_unique(array_filter(array_map(
            fn ($v) => trim((string) $v),
            $niks
        ))));

        return [$all, $dev, $arbpl, $niks];
    }

    /**
     * Terapkan scope ke query yppr058_data
     */
    protected function applyScopeToQmQuery($q): void
    {
        [$all, $dev, $arbpl, $niks] = $this->scopeData();

        if ($all) return;

        if (empty($dev) && empty($arbpl) && empty($niks)) {
            $q->whereRaw('1=0');
            return;
        }

        $q->where(function ($w) use ($dev, $arbpl, $niks) {
            if (!empty($niks)) {
                $w->orWhereIn('pernr', $niks);
            }

            if (!empty($dev)) {
                $w->orWhereIn(DB::raw('UPPER(TRIM(devisi))'), $dev);
            }

            if (!empty($arbpl)) {
                $w->orWhereIn(DB::raw('UPPER(TRIM(arbpl))'), $arbpl)
                  ->orWhereIn(DB::raw('UPPER(TRIM(arbpl2))'), $arbpl);
            }
        });
    }

    // =========================================================
    // SEARCH TOKENIZER (SAMA SEPERTI SEBELUMNYA)
    // =========================================================
    protected function tokenizeSearch(string $q): array
    {
        $q = trim($q);
        if ($q === '') return [];

        preg_match_all('/"([^"]+)"|(\S+)/', $q, $m, PREG_SET_ORDER);

        $tokens = [];
        foreach ($m as $row) {
            $tokens[] = $row[1] !== '' ? $row[1] : $row[2];
        }

        return array_values(array_filter(array_map('trim', $tokens)));
    }

    // =========================================================
    // PLANT GROUP SCOPE
    // =========================================================

    /**
     * PLANT untuk yppr058_data berdasarkan WERKS (sama seperti Livewire)
     */
    protected function applyPlantGroupScopeToQm($query, string $plant)
    {
        $plant = trim($plant);

        $query->whereRaw("
            CASE
                WHEN TRIM(werks) IN ('1001','1002','1003','1015') THEN '1001'
                WHEN LEFT(TRIM(werks),1)='1' THEN '1000'
                ELSE CONCAT(LEFT(TRIM(werks),1),'000')
            END = ?
        ", [$plant]);

        return $query;
    }

    /**
     * PLANT untuk daily_time_wi berdasarkan kode_laravel (sama seperti Livewire)
     */
    protected function getWiColumns(): array
    {
        return Schema::getColumnListing($this->wiTable);
    }

    protected function applyPlantGroupScopeToWi($query, string $plant, array $cols)
    {
        $plant = trim($plant);

        if (in_array('kode_laravel', $cols, true)) {
            $query->whereNotNull('kode_laravel')
                ->whereRaw("TRIM(kode_laravel) <> ''")
                ->whereRaw("TRIM(kode_laravel) REGEXP '^[0-9]{4}'")
                ->whereRaw("
                    CASE
                        WHEN LEFT(TRIM(kode_laravel),4) IN ('1001','1002','1003','1015') THEN '1001'
                        WHEN LEFT(TRIM(kode_laravel),1) = '1' THEN '1000'
                        ELSE CONCAT(LEFT(TRIM(kode_laravel),1),'000')
                    END = ?
                ", [$plant]);
        }

        return $query;
    }

    // =========================================================
    // BUILD SUBQUERY: QM DAY (driver) & WI DAY (optional)
    // =========================================================

    /**
     * QM per nik+begda (role INDUK) => driver rows
     */
    protected function qmDaySub(string $plant, array $dateYmd, array $niks = [])
    {
        [$startYmd, $endYmd] = $dateYmd;

        $q = DB::table($this->qmTable)
            ->whereBetween('begda', [$startYmd, $endYmd])
            ->whereRaw("UPPER(TRIM(role)) = ?", [strtoupper($this->qmRoleValue)]);

        $this->applyPlantGroupScopeToQm($q, $plant);
        $this->applyScopeToQmQuery($q);

        if (!empty($niks)) {
            $q->whereIn('pernr', $niks);
        }

        return $q->selectRaw("
            pernr as nik,
            begda,
            MAX(cname) as nama,
            MAX(arbpl) as wc,
            MAX(NULLIF(TRIM(devisi),'')) as devisi,
            COALESCE(SUM(mintu),0) as mintu_sum,
            COALESCE(SUM(mint2),0) as mint2_sum
        ")
        ->groupBy('pernr', 'begda');
        }

    /**
     * WI per nik+tanggal (optional)
     */
    protected function wiDaySub(string $plant, array $dateIso, array $niks = [])
    {
        [$startIso, $endIso] = $dateIso;

        $cols = $this->getWiColumns();

        $q = DB::table($this->wiTable);
        $q = $this->applyPlantGroupScopeToWi($q, $plant, $cols);

        $q->whereBetween($this->wiDateColumn, [$startIso, $endIso]);

        if (!empty($niks)) {
            $q->whereIn($this->wiNikColumn, $niks);
        }

        return $q->selectRaw("
                MIN(id) as id,
                {$this->wiNikColumn} as nik,
                {$this->wiDateColumn} as tanggal,
                MAX(kode_laravel) as kode_laravel,
                COALESCE(SUM({$this->wiTimeColumn}),0) as time_wi
            ")
            ->groupBy($this->wiNikColumn, $this->wiDateColumn);
    }

    /**
     * Joined per day: QM (driver) LEFT JOIN WI
     * Hasilnya dipakai untuk:
     * - summary aggregation
     * - detail day map
     */
    protected function joinedDayQuery(string $plant, array $dateIso, array $dateYmd, array $niks = [], string $q = '')
    {
        $qmDay = $this->qmDaySub($plant, $dateYmd, $niks);
        $wiDay = $this->wiDaySub($plant, $dateIso, $niks);

        $joined = DB::query()
            ->fromSub($qmDay, 'qm')
            ->leftJoinSub($wiDay, 'wi', function ($join) {
                $join->on('wi.nik', '=', 'qm.nik')
                    ->on(DB::raw("DATE_FORMAT(wi.tanggal, '%Y%m%d')"), '=', 'qm.begda');
            })
            ->selectRaw("
                qm.nik,
                DATE_FORMAT(STR_TO_DATE(qm.begda,'%Y%m%d'), '%Y-%m-%d') as tanggal,
                qm.begda,
                qm.nama,
                qm.wc,
                qm.devisi,
                (qm.mintu_sum / {$this->qmDivisor}) as time_qm,
                (qm.mint2_sum / {$this->qmDivisor}) as time_conf,
                wi.id as wi_id,
                wi.kode_laravel as kode_laravel,
                wi.time_wi as time_wi,
                CASE
                    WHEN wi.time_wi IS NULL THEN NULL
                    WHEN wi.time_wi = 0 THEN 0
                    ELSE ((qm.mintu_sum / {$this->qmDivisor}) / wi.time_wi) * 100
                END as kpi_pct
            ");


        // search (AND per token)
        $raw = trim((string) $q);
        if ($raw !== '') {
            $tokens = $this->tokenizeSearch($raw);

            foreach ($tokens as $t) {
                $t = trim((string)$t);
                if ($t === '') continue;

                $lower = mb_strtolower($t);
                $like  = "%{$lower}%";

                $ymd = null;
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $t)) {
                    $ymd = str_replace('-', '', $t);
                }

                $joined->where(function ($w) use ($t, $lower, $like, $ymd) {
                    // NIK
                    $w->orWhere('qm.nik', 'like', "%{$t}%");

                    // tanggal
                    if ($ymd) {
                        $w->orWhere('qm.begda', $ymd);
                        $w->orWhereDate('wi.tanggal', $t);
                    }

                    // nama, wc, devisi
                    $w->orWhereRaw('LOWER(qm.nama) LIKE ?', [$like]);
                    $w->orWhereRaw('LOWER(qm.wc) LIKE ?', [$like]);
                    $w->orWhereRaw('LOWER(qm.devisi) LIKE ?', [$like]);

                    // kode_laravel, id (optional)
                    $w->orWhereRaw('LOWER(COALESCE(wi.kode_laravel,"")) LIKE ?', [$like]);
                    $w->orWhereRaw('CAST(COALESCE(wi.id,0) AS CHAR) LIKE ?', ["%{$t}%"]);
                });
            }
        }

        return $joined;
    }

    // =========================================================
    // SUMMARY ROWS (PDF/EXCEL)
    // =========================================================
    protected function getSummaryRows(string $plant, array $dateIso, array $dateYmd, string $q = '', array $niks = []): Collection
    {
        $joined = $this->joinedDayQuery($plant, $dateIso, $dateYmd, $niks, $q);

        return DB::query()
            ->fromSub($joined, 'd')
            ->selectRaw("
                nik,
                MAX(nama) as nama,
                MIN(tanggal) as min_tanggal,
                MAX(tanggal) as max_tanggal,
                MAX(wc) as wc,
                MAX(devisi) as devisi,

                COALESCE(SUM(COALESCE(time_wi,0)),0) as time_wi_sum,
                COALESCE(SUM(COALESCE(time_conf,0)),0) as time_conf_sum,
                COALESCE(SUM(COALESCE(time_qm,0)),0) as time_qm_sum,

                -- HASIL QM (QM / WI)
                CASE
                    WHEN SUM(COALESCE(time_wi,0)) = 0 THEN 0
                    ELSE (SUM(COALESCE(time_qm,0)) / SUM(COALESCE(time_wi,0))) * 100
                END as kpi_quality_pct,

                -- HASIL WI (CONF / WI)
                CASE
                    WHEN SUM(COALESCE(time_wi,0)) = 0 THEN 0
                    ELSE (SUM(COALESCE(time_conf,0)) / SUM(COALESCE(time_wi,0))) * 100
                END as kpi_qty_pct,

                -- (optional backward compat)
                CASE
                    WHEN SUM(COALESCE(time_wi,0)) = 0 THEN 0
                    ELSE (SUM(COALESCE(time_qm,0)) / SUM(COALESCE(time_wi,0))) * 100
                END as kpi_pct
            ")
            ->groupBy('nik')
            ->orderByRaw("COALESCE(NULLIF(TRIM(MAX(wc)), ''), 'ZZZZ') ASC")
            ->orderByRaw("CAST(nik AS UNSIGNED) ASC")
            ->get();
    }

    // =========================================================
    // DETAIL ROWS (PDF/EXCEL)
    // =========================================================
    protected function parseDetailKeys(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $k = trim((string)$k);
            if ($k === '') continue;

            [$nik, $tgl] = array_pad(explode('|', $k, 2), 2, '');
            $nik = trim((string)$nik);
            $tgl = trim((string)$tgl);

            if ($nik === '' || $tgl === '') continue;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) continue;

            $out[$nik] ??= [];
            $out[$nik][] = $tgl;
        }

        foreach ($out as $nik => $arr) {
            $arr = array_values(array_unique(array_filter($arr)));
            sort($arr);
            $out[$nik] = $arr;
        }

        return $out;
    }

    protected function getDetailRows(
        string $plant,
        array $dateIso,
        array $dateYmd,
        array $fullRangeNiks,
        array $detailKeys,
        string $q = ''
    ): Collection {
        [$startIso, $endIso] = $dateIso;

        // normalize
        $fullRangeNiks = array_values(array_unique(array_filter(array_map(fn($x) => trim((string)$x), $fullRangeNiks))));
        $partialByNik  = $this->parseDetailKeys($detailKeys);

        // anti dobel
        if (!empty($fullRangeNiks) && !empty($partialByNik)) {
            foreach ($fullRangeNiks as $n) unset($partialByNik[$n]);
        }

        if (empty($fullRangeNiks) && empty($partialByNik)) {
            return collect();
        }

        $allNiks = array_values(array_unique(array_merge($fullRangeNiks, array_keys($partialByNik))));

        // ambil joined day data (driver QM INDUK + optional WI)
        $joinedRows = $this->joinedDayQuery($plant, $dateIso, $dateYmd, $allNiks, $q)
            ->get();

        $byKey = $joinedRows->keyBy(fn($r) => (string)$r->nik . '|' . (string)$r->tanggal);

        // fallback per nik
        $fallback = $joinedRows->groupBy('nik')->map(function ($g) {
            $first = $g->first();
            return [
                'nama'   => (string)($first->nama ?? '-'),
                'wc'     => $first->wc ?? null,
                'devisi' => $first->devisi ?? null,
                'min'    => (string)($g->min('tanggal') ?? ''),
                'max'    => (string)($g->max('tanggal') ?? ''),
            ];
        });

        $out = collect();

        $pushRow = function (string $nik, string $tgl) use (&$out, $byKey, $fallback) {
            $key = $nik . '|' . $tgl;
            $r = $byKey->get($key);

            $fb = $fallback->get($nik, [
                'nama' => '-',
                'wc' => null,
                'devisi' => null,
            ]);

            $nama   = $r?->nama ?? $fb['nama'] ?? '-';
            $wc     = $r?->wc ?? ($fb['wc'] ?? null);
            $devisi = $r?->devisi ?? ($fb['devisi'] ?? null);

            $timeWi = isset($r) ? ($r->time_wi !== null ? (float)$r->time_wi : null) : null;
            $timeQm = isset($r) ? ($r->time_qm !== null ? (float)$r->time_qm : null) : null;
            
            // ✅ TAMBAHKAN INI: Ambil Time Conf
            $timeConf = isset($r) ? ($r->time_conf !== null ? (float)$r->time_conf : null) : null;

            $kpiQuality = null;
            $kpiQty     = null;

            if (!is_null($timeWi)) {
                // Quality = QM / WI
                $kpiQuality = ($timeWi == 0.0) ? 0.0 : (((float)($timeQm ?? 0) / $timeWi) * 100);

                // Qty = CONF / WI
                $wiBase = (float)($timeWi ?? 0);
                $kpiQty = ($wiBase == 0.0) ? 0.0 : (((float)($timeConf ?? 0) / $wiBase) * 100);
            }

            $out->push((object)[
                'nik'     => $nik,
                'nama'    => (string)$nama,
                'tanggal' => $tgl,
                'wc'      => $wc,
                'devisi'  => $devisi,

                'time_wi'   => $timeWi,
                'time_conf' => $timeConf,
                'time_qm'   => $timeQm,

                // baru
                'kpi_qty_pct'     => $kpiQty,
                'kpi_quality_pct' => $kpiQuality,

                // optional backward compat (kalau masih dipakai di tempat lain)
                'kpi_pct' => $kpiQuality,
            ]);
        };

        // 1) full range: pakai min..max dari driver QM (hasil joined) supaya sesuai badge logic UI
        foreach ($fullRangeNiks as $nik) {
            $nik = (string)$nik;

            $min = (string)($fallback->get($nik)['min'] ?? '');
            $max = (string)($fallback->get($nik)['max'] ?? '');

            // kalau tidak ketemu (misal karena q terlalu ketat), fallback ke range bulan
            if ($min === '' || $max === '') {
                $min = $startIso;
                $max = $endIso;
            }

            $cur = Carbon::parse($min)->startOfDay();
            $end = Carbon::parse($max)->startOfDay();

            while ($cur->lte($end)) {
                $pushRow($nik, $cur->toDateString());
                $cur->addDay();
            }
        }

        // 2) partial dates
        foreach ($partialByNik as $nik => $tgls) {
            $nik = (string)$nik;
            foreach ($tgls as $tgl) {
                $pushRow($nik, (string)$tgl);
            }
        }

        return $out->sortBy([
            ['wc', 'asc'],      // 1. WC
            ['nik', 'asc'],     // 2. NIK
            ['tanggal', 'asc']  // 3. Tanggal
        ])->values();
    }

    // =========================================================
    // ROUTES
    // =========================================================

    public function exportSummaryPdf(Request $request, string $plant)
    {
        $plant = strtoupper(trim($plant));

        $monthFilter = (string)$request->session()->get('wi_export.month_filter', 'this');
        $q           = (string)$request->session()->get('wi_export.q', '');
        $niks        = (array)$request->session()->get('wi_export.niks', []);
        $niks        = array_values(array_filter(array_map('strval', $niks)));

        [$start, $end] = $this->getDateRangeForMonth($monthFilter);
        $range = $this->getRangeStrings($start, $end);

        $rows = $this->getSummaryRows($plant, $range['iso'], $range['ymd'], $q, $niks);
        if ($rows->isEmpty()) abort(404, 'Tidak ada data QM (role INDUK) untuk filter tersebut.');

        $overallWi  = (float)$rows->sum(fn($r) => (float)($r->time_wi_sum ?? 0));
        $overallQm  = (float)$rows->sum(fn($r) => (float)($r->time_qm_sum ?? 0));
        $overallKpi = $overallWi == 0.0 ? 0.0 : (($overallQm / $overallWi) * 100);

        $request->session()->forget(['wi_export.month_filter', 'wi_export.q', 'wi_export.niks']);

        $pdf = Pdf::loadView('pdf.wi-summary', [
            'rows'       => $rows,
            'plant'      => $plant,
            'rangeStart' => $range['iso'][0],
            'rangeEnd'   => $range['iso'][1],
            'overallWi'  => $overallWi,
            'overallQm'  => $overallQm,
            'overallKpi' => $overallKpi,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("wi-summary-{$plant}.pdf");
    }

    public function exportDetailPdf(Request $request, string $plant)
    {
        $plant = strtoupper(trim($plant));

        $monthFilter = (string)$request->session()->get('wi_export_detail.month_filter', 'this');
        $q           = (string)$request->session()->get('wi_export_detail.q', '');

        $summaryNiks = (array)$request->session()->get('wi_export_detail.niks', []);
        $summaryNiks = array_values(array_filter(array_map('strval', $summaryNiks)));

        $detailKeys  = (array)$request->session()->get('wi_export_detail.keys', []);
        $detailKeys  = array_values(array_filter(array_map('strval', $detailKeys)));

        [$start, $end] = $this->getDateRangeForMonth($monthFilter);
        $range = $this->getRangeStrings($start, $end);

        if (empty($summaryNiks) && empty($detailKeys)) {
            abort(404, 'Tidak ada pilihan NIK / tanggal untuk di-export detail.');
        }

        $rows = $this->getDetailRows($plant, $range['iso'], $range['ymd'], $summaryNiks, $detailKeys, $q);
        if ($rows->isEmpty()) abort(404, 'Data detail tidak ditemukan untuk filter tersebut.');

        $request->session()->forget([
            'wi_export_detail.month_filter',
            'wi_export_detail.q',
            'wi_export_detail.niks',
            'wi_export_detail.keys'
        ]);

        $pdf = Pdf::loadView('pdf.wi-detail', [
            'rows'       => $rows,
            'plant'      => $plant,
            'rangeStart' => $range['iso'][0],
            'rangeEnd'   => $range['iso'][1],
        ])->setPaper('a4', 'portrait');

        return $pdf->download("wi-detail-{$plant}.pdf");
    }

    public function exportSummaryExcel(Request $request, string $plant)
    {
        $plant = strtoupper(trim($plant));

        $monthFilter = (string)$request->session()->get('wi_export.month_filter', 'this');
        $q           = (string)$request->session()->get('wi_export.q', '');

        $niks = (array)$request->session()->get('wi_export.niks', []);
        $niks = array_values(array_filter(array_map('strval', $niks)));

        [$start, $end] = $this->getDateRangeForMonth($monthFilter);
        $range = $this->getRangeStrings($start, $end);

        $rows = $this->getSummaryRows($plant, $range['iso'], $range['ymd'], $q, $niks);
        if ($rows->isEmpty()) abort(404, 'Tidak ada data QM (role INDUK) untuk filter tersebut.');

        $request->session()->forget(['wi_export.month_filter', 'wi_export.q', 'wi_export.niks']);

        return Excel::download(new WiSummaryExport($rows, $plant), "wi-summary-{$plant}.xlsx");
    }

    public function exportDetailExcel(Request $request, string $plant)
    {
        $plant = strtoupper(trim($plant));

        $monthFilter = (string)$request->session()->get('wi_export_detail.month_filter', 'this');
        $q           = (string)$request->session()->get('wi_export_detail.q', '');

        $summaryNiks = (array)$request->session()->get('wi_export_detail.niks', []);
        $summaryNiks = array_values(array_filter(array_map('strval', $summaryNiks)));

        $detailKeys  = (array)$request->session()->get('wi_export_detail.keys', []);
        $detailKeys  = array_values(array_filter(array_map('strval', $detailKeys)));

        [$start, $end] = $this->getDateRangeForMonth($monthFilter);
        $range = $this->getRangeStrings($start, $end);

        if (empty($summaryNiks) && empty($detailKeys)) {
            abort(404, 'Tidak ada pilihan NIK / tanggal untuk di-export detail.');
        }

        $rows = $this->getDetailRows($plant, $range['iso'], $range['ymd'], $summaryNiks, $detailKeys, $q);
        if ($rows->isEmpty()) abort(404, 'Data detail tidak ditemukan untuk filter tersebut.');

        $request->session()->forget([
            'wi_export_detail.month_filter',
            'wi_export_detail.q',
            'wi_export_detail.niks',
            'wi_export_detail.keys'
        ]);

        return Excel::download(new WiDetailExport($rows, $plant), "wi-detail-{$plant}.xlsx");
    }
}
