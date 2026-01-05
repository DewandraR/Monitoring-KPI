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
    // ====== SESUAIKAN DENGAN TABEL ASLI ======
    protected string $wiTable      = 'daily_time_wi';
    protected string $wiNikColumn  = 'nik';
    protected string $wiNamaColumn = 'nama';
    protected string $wiDateColumn = 'tanggal';
    protected string $wiTimeColumn = 'total_time_wi';
    protected ?string $wiWcColumn  = null; // tabel WI biasanya tidak punya wc

    // QM dari yppr058_data = SUM(mintu)
    // kalau mau convert menit -> jam: set 60
    protected float $qmDivisor = 1.0;

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

        return [$start, $end];
    }

    protected function getRangeStrings(Carbon $start, Carbon $end): array
    {
        return [
            'iso' => [$start->toDateString(), $end->toDateString()], // YYYY-MM-DD
            'ymd' => [$start->format('Ymd'),   $end->format('Ymd')],  // YYYYMMDD
        ];
    }

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

    protected function getWiColumns(): array
    {
        return Schema::getColumnListing($this->wiTable);
    }

    protected function pickExistingColumns(array $wanted, array $available): array
    {
        $set = array_flip($available);
        return array_values(array_filter($wanted, fn($c) => isset($set[$c])));
    }

    /**
     * Scope plant sama seperti Livewire:
     * plant 3000 => prefix "3" (LEFT(kode_laravel,1) = "3")
     */
    protected function applyPlantPrefixScope($query, string $plant, array $cols)
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

    /**
     * SUMMARY rows (per NIK) untuk PDF/Excel
     * ✅ Tambah devisi dari yppr058_data (MAX(devisi))
     */
    protected function getSummaryRows(string $plant, array $dateIso, array $dateYmd, string $q = '', array $niks = []): Collection
    {
        [$startIso, $endIso] = $dateIso;
        [$startYmd, $endYmd] = $dateYmd;

        $cols = $this->getWiColumns();

        $searchCols = $this->pickExistingColumns(
            [$this->wiNikColumn, $this->wiNamaColumn, $this->wiDateColumn, 'kode_laravel', 'id'],
            $cols
        );

        $wiAgg = DB::table($this->wiTable);
        $wiAgg = $this->applyPlantPrefixScope($wiAgg, $plant, $cols);

        $wiAgg = $wiAgg
            ->whereBetween($this->wiDateColumn, [$startIso, $endIso])
            ->when(!empty($niks), fn($qq) => $qq->whereIn($this->wiNikColumn, $niks))
            ->when(trim($q) !== '' && !empty($searchCols), function ($qq) use ($q, $searchCols) {
                $tokens = $this->tokenizeSearch($q);
                foreach ($tokens as $t) {
                    $qq->where(function ($w) use ($t, $searchCols) {
                        foreach ($searchCols as $col) {
                            $w->orWhere($col, 'like', '%' . $t . '%');
                        }
                    });
                }
            })
            ->selectRaw("
                {$this->wiNikColumn} as nik,
                MAX({$this->wiNamaColumn}) as nama,
                MIN({$this->wiDateColumn}) as min_tanggal,
                MAX({$this->wiDateColumn}) as max_tanggal,
                SUM({$this->wiTimeColumn}) as time_wi_sum
            ");

        // aman walau WI tidak punya wc
        if ($this->wiWcColumn && in_array($this->wiWcColumn, $cols, true)) {
            $wiAgg->addSelect(DB::raw("MAX({$this->wiWcColumn}) as wc_wi"));
        } else {
            $wiAgg->addSelect(DB::raw("NULL as wc_wi"));
        }

        $wiAgg = $wiAgg->groupBy($this->wiNikColumn);

        // ✅ QM + WC + DEVISI dari yppr058_data
        $qmAgg = DB::table('yppr058_data')
            ->selectRaw("
                pernr as nik,
                MAX(arbpl) as wc_qm,
                MAX(NULLIF(TRIM(devisi),'')) as devisi_qm,
                SUM(mintu) as mintu_sum
            ")
            ->whereRaw("
                CASE
                    WHEN TRIM(werks) IN ('1001','1002','1003','1015') THEN '1001'
                    WHEN LEFT(TRIM(werks),1)='1' THEN '1000'
                    ELSE CONCAT(LEFT(TRIM(werks),1),'000')
                END = ?
            ", [trim($plant)])
            ->whereBetween('begda', [$startYmd, $endYmd])
            ->groupBy('pernr');

        return DB::query()
            ->fromSub($wiAgg, 'w')
            ->leftJoinSub($qmAgg, 'q', 'q.nik', '=', 'w.nik')
            ->selectRaw("
                w.nik,
                w.nama,
                w.min_tanggal,
                w.max_tanggal,
                COALESCE(w.wc_wi, q.wc_qm) as wc,
                q.devisi_qm as devisi,
                w.time_wi_sum,
                (COALESCE(q.mintu_sum,0) / {$this->qmDivisor}) as time_qm_sum,
                CASE
                    WHEN w.time_wi_sum = 0 THEN 0
                    ELSE ((COALESCE(q.mintu_sum,0) / {$this->qmDivisor}) / w.time_wi_sum) * 100
                END as kpi_pct
            ")
            ->orderByRaw("COALESCE(NULLIF(TRIM(COALESCE(w.wc_wi, q.wc_qm)), ''), 'ZZZZ') ASC")
            ->orderByRaw("CAST(w.nik AS UNSIGNED) ASC")
            ->get();
    }

    /**
     * Parse detail keys "nik|YYYY-MM-DD"
     */
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

        // unique + sort tanggal per nik
        foreach ($out as $nik => $arr) {
            $arr = array_values(array_unique(array_filter($arr)));
            sort($arr);
            $out[$nik] = $arr;
        }

        return $out; // [nik => [tgl,tgl]]
    }

    /**
     * DETAIL rows untuk PDF/Excel
     * - $fullRangeNiks: export full tanggal range untuk NIK ini (dari summary)
     * - $detailKeys: export tanggal tertentu untuk NIK tertentu (dari modal detail)
     * ✅ Tambah devisi dari yppr058_data
     */
    protected function getDetailRows(string $plant, array $dateIso, array $dateYmd, array $fullRangeNiks, array $detailKeys, string $q = ''): Collection
    {
        [$startIso, $endIso] = $dateIso;
        [$startYmd, $endYmd] = $dateYmd;

        $start = Carbon::parse($startIso)->startOfDay();
        $end   = Carbon::parse($endIso)->startOfDay();

        // normalize full nik
        $fullRangeNiks = array_values(array_unique(array_filter(array_map(fn($x) => trim((string)$x), $fullRangeNiks))));

        // parse keys -> [nik => [tgl]]
        $partialByNik = $this->parseDetailKeys($detailKeys);

        // buang partial nik yang sudah masuk full range (anti dobel)
        if (!empty($fullRangeNiks) && !empty($partialByNik)) {
            foreach ($fullRangeNiks as $n) {
                unset($partialByNik[$n]);
            }
        }

        if (empty($fullRangeNiks) && empty($partialByNik)) {
            return collect();
        }

        // list semua nik yg akan diambil datanya
        $allNiks = array_values(array_unique(array_merge(
            $fullRangeNiks,
            array_keys($partialByNik)
        )));

        // list tanggal yg dibutuhkan
        $allDatesRange = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $allDatesRange[] = $d->toDateString();
        }

        $datesNeeded = $allDatesRange;
        if (empty($fullRangeNiks)) {
            // kalau tidak ada full range, cukup tanggal yang dipilih saja
            $datesNeeded = [];
            foreach ($partialByNik as $nik => $arr) {
                foreach ($arr as $tgl) $datesNeeded[] = $tgl;
            }
            $datesNeeded = array_values(array_unique($datesNeeded));
            sort($datesNeeded);
        }

        // untuk QM, butuh format Ymd
        $ymdNeeded = array_map(fn($iso) => Carbon::parse($iso)->format('Ymd'), $datesNeeded);
        $ymdNeeded = array_values(array_unique($ymdNeeded));

        $cols = $this->getWiColumns();

        $searchCols = $this->pickExistingColumns(
            [$this->wiNikColumn, $this->wiNamaColumn, $this->wiDateColumn, 'kode_laravel', 'id'],
            $cols
        );

        // ===== WI per nik+tanggal =====
        $wiQ = DB::table($this->wiTable);
        $wiQ = $this->applyPlantPrefixScope($wiQ, $plant, $cols);

        $wiQ = $wiQ
            ->whereIn($this->wiNikColumn, $allNiks)
            ->whereBetween($this->wiDateColumn, [$startIso, $endIso]);

        // kalau export hanya tanggal tertentu (tanpa full range), kita batasi tanggalnya
        if (empty($fullRangeNiks) && !empty($datesNeeded)) {
            $wiQ->whereIn($this->wiDateColumn, $datesNeeded);
        }

        $wiPerDay = $wiQ
            ->when(trim($q) !== '' && !empty($searchCols), function ($qq) use ($q, $searchCols) {
                $tokens = $this->tokenizeSearch($q);
                foreach ($tokens as $t) {
                    $qq->where(function ($w) use ($t, $searchCols) {
                        foreach ($searchCols as $col) {
                            $w->orWhere($col, 'like', '%' . $t . '%');
                        }
                    });
                }
            })
            ->selectRaw("
                {$this->wiNikColumn} as nik,
                {$this->wiDateColumn} as tanggal,
                MAX({$this->wiNamaColumn}) as nama,
                SUM({$this->wiTimeColumn}) as time_wi
            ")
            ->groupBy($this->wiNikColumn, $this->wiDateColumn)
            ->get()
            ->keyBy(fn($r) => (string)$r->nik . '|' . (string)$r->tanggal);

        // ===== QM per nik+tanggal (+ devisi) =====
        $qmQ = DB::table('yppr058_data')
            ->selectRaw("
                pernr as nik,
                begda,
                MAX(arbpl) as wc_qm,
                MAX(NULLIF(TRIM(devisi),'')) as devisi_qm,
                SUM(mintu) as mintu_sum
            ")
            ->whereRaw("
                CASE
                    WHEN TRIM(werks) IN ('1001','1002','1003','1015') THEN '1001'
                    WHEN LEFT(TRIM(werks),1)='1' THEN '1000'
                    ELSE CONCAT(LEFT(TRIM(werks),1),'000')
                END = ?
            ", [trim($plant)])
            ->whereIn('pernr', $allNiks)
            ->whereBetween('begda', [$startYmd, $endYmd]);

        if (empty($fullRangeNiks) && !empty($ymdNeeded)) {
            $qmQ->whereIn('begda', $ymdNeeded);
        }

        $qmPerDay = $qmQ
            ->groupBy('pernr', 'begda')
            ->get()
            ->keyBy(function ($r) {
                $tgl = Carbon::createFromFormat('Ymd', (string)$r->begda)->toDateString();
                return (string)$r->nik . '|' . $tgl;
            });

        // nama default per nik (dari WI)
        $nikNamaMap = $wiPerDay->values()
            ->groupBy('nik')
            ->map(fn($g) => (string)($g->first()->nama ?? '-'));

        // devisi default per nik (dari QM) -> buat fallback kalau hari itu tidak ada qm row
        $nikDevisiMap = $qmPerDay->values()
            ->groupBy('nik')
            ->map(fn($g) => (string)($g->first()->devisi_qm ?? ''));

        $out = collect();

        // helper push row
        $pushRow = function (string $nik, string $tgl) use ($wiPerDay, $qmPerDay, $nikNamaMap, $nikDevisiMap, &$out) {
            $key = $nik . '|' . $tgl;

            $wi = $wiPerDay->get($key);
            $qm = $qmPerDay->get($key);

            $timeWi = $wi ? (float)$wi->time_wi : null;
            $timeQm = $qm ? ((float)$qm->mintu_sum / $this->qmDivisor) : null;

            $wc     = $qm?->wc_qm ?? null;
            $devisi = $qm?->devisi_qm ?? null;

            if (is_null($devisi)) {
                $fallback = (string)($nikDevisiMap[$nik] ?? '');
                $devisi = $fallback !== '' ? $fallback : null;
            }

            $namaDefault = (string)($nikNamaMap[$nik] ?? '-');
            $nama = $wi?->nama ?? $namaDefault;

            $kpi = null;
            if (!is_null($timeWi)) {
                $kpi = ($timeWi == 0.0) ? 0.0 : (((float)($timeQm ?? 0) / $timeWi) * 100);
            }

            $out->push((object)[
                'nik'     => $nik,
                'nama'    => (string)$nama,
                'tanggal' => $tgl,
                'wc'      => $wc,
                'devisi'  => $devisi,
                'time_wi' => $timeWi,
                'time_qm' => $timeQm,
                'kpi_pct' => $kpi,
            ]);
        };

        // 1) Full range untuk NIK summary
        foreach ($fullRangeNiks as $nik) {
            $nik = (string)$nik;
            foreach ($allDatesRange as $tgl) {
                $pushRow($nik, $tgl);
            }
        }

        // 2) Partial dates untuk NIK yang hanya dipilih via modal detail
        foreach ($partialByNik as $nik => $tgls) {
            $nik = (string)$nik;
            foreach ($tgls as $tgl) {
                $pushRow($nik, $tgl);
            }
        }

        return $out->sortBy([['nik', 'asc'], ['tanggal', 'asc']])->values();
    }

    // ==========================
    // ROUTE: /wi-daily/{plant}/export-summary-pdf
    // ==========================
    public function exportSummaryPdf(Request $request, string $plant)
    {
        $plant = strtoupper(trim($plant));

        $monthFilter = (string)$request->session()->get('wi_export.month_filter', 'this');
        $q           = (string)$request->session()->get('wi_export.q', '');

        $niks = (array)$request->session()->get('wi_export.niks', []);
        $niks = array_values(array_filter(array_map('strval', $niks)));

        [$start, $end] = $this->getDateRangeForMonth($monthFilter);
        $range = $this->getRangeStrings($start, $end);

        $rows = $this->getSummaryRows($plant, $range['iso'], $range['ymd'], $q, $niks);
        if ($rows->isEmpty()) abort(404, 'Tidak ada data WI untuk filter tersebut.');

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

    // ==========================
    // ROUTE: /wi-daily/{plant}/export-detail-pdf
    // ==========================
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

        // ✅ jangan fallback export semua NIK (biar sesuai UI). Harus ada pilihan.
        if (empty($summaryNiks) && empty($detailKeys)) {
            abort(404, 'Tidak ada pilihan NIK / tanggal untuk di-export detail.');
        }

        $rows = $this->getDetailRows($plant, $range['iso'], $range['ymd'], $summaryNiks, $detailKeys, $q);
        if ($rows->isEmpty()) abort(404, 'Data detail WI tidak ditemukan untuk filter tersebut.');

        $request->session()->forget(['wi_export_detail.month_filter', 'wi_export_detail.q', 'wi_export_detail.niks', 'wi_export_detail.keys']);

        $pdf = Pdf::loadView('pdf.wi-detail', [
            'rows'       => $rows,
            'plant'      => $plant,
            'rangeStart' => $range['iso'][0],
            'rangeEnd'   => $range['iso'][1],
        ])->setPaper('a4', 'portrait');

        return $pdf->download("wi-detail-{$plant}.pdf");
    }

    // ==========================
    // ROUTE: /wi-daily/{plant}/export-summary-excel
    // ==========================
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
        if ($rows->isEmpty()) abort(404, 'Tidak ada data WI untuk filter tersebut.');

        $request->session()->forget(['wi_export.month_filter', 'wi_export.q', 'wi_export.niks']);

        return Excel::download(new WiSummaryExport($rows, $plant), "wi-summary-{$plant}.xlsx");
    }

    // ==========================
    // ROUTE: /wi-daily/{plant}/export-detail-excel
    // ==========================
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
        if ($rows->isEmpty()) abort(404, 'Data detail WI tidak ditemukan untuk filter tersebut.');

        $request->session()->forget(['wi_export_detail.month_filter', 'wi_export_detail.q', 'wi_export_detail.niks', 'wi_export_detail.keys']);

        return Excel::download(new WiDetailExport($rows, $plant), "wi-detail-{$plant}.xlsx");
    }
}
