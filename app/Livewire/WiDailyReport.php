<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\DailyTimeWi;
use App\Models\ReportData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

#[Layout('layouts.app')]
class WiDailyReport extends Component
{
    public string $q = '';
    public string $plant;               // contoh: "1000", "1001", "2000", "3000"
    public string $monthFilter = 'this';
    public string $wiMode = 'all'; // all | with | without

    public bool $showDetailModal = false;
    public ?string $selectedNik = null;
    public array $detailData = [];

    // total modal
    public ?float $detailTotalWi = null;
    public ?float $detailTotalQm = null;
    public ?float $detailKpi = null;

    // devisi ringkas modal (unik/MULTI/-)
    public ?string $detailDevisi = null;

    // selection export
    public array $selectedNiks = [];
    public array $selectedDetailKeys = [];
    public string $reportMode = 'wi'; // wi | korlap

    // untuk expand/collapse row korlap
    public array $expandedKorlaps = []; // contoh: ['10000471','10000424']

    // cache list NIK summary per korlap (agar expand cepat)
    public array $korlapNikSummaries = []; // ['10000471' => [..rows..], ...]

    /**
     * Ambil scope dari middleware (data.scope)
     */
    protected function scopeData(): array
    {
        $all   = (bool) request()->attributes->get('data_scope_all', false);
        $dev   = (array) request()->attributes->get('data_scope_devisi', []);
        $arbpl = (array) request()->attributes->get('data_scope_arbpl', []);

        // OPTIONAL scope NIK kalau ada
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


    public function setReportMode(string $mode): void
    {
        $mode = $mode === 'korlap' ? 'korlap' : 'wi';
        $this->reportMode = $mode;

        session(['wi_daily.report_mode' => $this->reportMode]);

        // reset state biar tidak nyangkut dari mode lain
        $this->selectedNiks = [];
        $this->selectedDetailKeys = [];

        $this->expandedKorlaps = [];
        $this->korlapNikSummaries = [];

        $this->closeDetailModal();
    }

    protected function buildDetailJoinedForCurrentFilters(bool $withSearch = true): array
    {
        [$start, $end] = $this->getDateRangeForFilter();

        $begdaStart = $start->format('Ymd');
        $begdaEnd   = $end->format('Ymd');

        $qmAgg = $this->qmAggSub($begdaStart, $begdaEnd);
        $wiAgg = $this->wiAggSub($start, $end);

        $detailJoined = $this->detailJoinedQuery($qmAgg, $wiAgg);

        if ($withSearch) {
            $this->applyLiveSearchToJoined($detailJoined);
        }

        return [$detailJoined, $start, $end];
    }

    public function toggleKorlap(string $korlapNik): void
    {
        $korlapNik = trim((string)$korlapNik);
        if ($korlapNik === '') return;

        // kalau sudah expand -> collapse
        if (in_array($korlapNik, $this->expandedKorlaps, true)) {
            $this->expandedKorlaps = array_values(array_diff($this->expandedKorlaps, [$korlapNik]));
            return;
        }

        // expand
        $this->expandedKorlaps[] = $korlapNik;

        // lazy load daftar NIK di bawah korlap (sekali saja)
        if (!array_key_exists($korlapNik, $this->korlapNikSummaries)) {
            $this->korlapNikSummaries[$korlapNik] = $this->queryKorlapNikSummaries($korlapNik);
        }
    }

    protected function queryKorlapNikSummaries(string $korlapNik): array
    {
        [$detailJoined] = $this->buildDetailJoinedForCurrentFilters(true);

        // Ambil summary per NIK, tapi hanya baris yang WC-nya termasuk wc_korlap milik korlap ini
        $rows = DB::query()
            ->fromSub($detailJoined, 'd')
            ->join('nik_korlap as nk', function ($join) {
                $join->whereRaw("JSON_CONTAINS(nk.wc_korlap, JSON_QUOTE(d.wc))");
            })
            ->where('nk.nik', $korlapNik)
            ->whereRaw('TRIM(nk.plant) = ?', [trim($this->plant)]) // ✅ penting
            ->selectRaw("
                d.nik as nik,
                MAX(d.nama) as nama,
                CASE WHEN COUNT(DISTINCT d.wc)=1 THEN MAX(d.wc) ELSE 'MULTI' END as wc,
                CASE WHEN COUNT(DISTINCT d.devisi)=1 THEN MAX(d.devisi) ELSE 'MULTI' END as devisi,
                MIN(d.tanggal) as min_tanggal,
                MAX(d.tanggal) as max_tanggal,
                COALESCE(SUM(d.time_wi),0) as time_wi_sum,
                COALESCE(SUM(d.time_qm),0) as time_qm_sum,
                CASE
                    WHEN COALESCE(SUM(d.time_wi),0)=0 THEN 0
                    ELSE (COALESCE(SUM(d.time_qm),0)/COALESCE(SUM(d.time_wi),0))*100
                END as kpi_pct
            ")
            ->groupBy('d.nik')
            ->orderBy('wc', 'asc') // 1. Urutkan WC (A-Z)
            ->orderByRaw("CAST(d.nik AS UNSIGNED) ASC") // 2. Baru urutkan NIK (Angka)
            ->get();
        return $rows->map(fn($r) => [
            'nik'         => (string) $r->nik,
            'nama'        => (string) $r->nama,
            'wc'          => (string) $r->wc,
            'devisi'      => (string) $r->devisi,
            'min_tanggal' => (string) $r->min_tanggal,
            'max_tanggal' => (string) $r->max_tanggal,
            'time_wi_sum' => (float) $r->time_wi_sum,
            'time_qm_sum' => (float) $r->time_qm_sum,
            'kpi_pct'     => (float) $r->kpi_pct,
        ])->all();
    }

    /**
     * Terapkan scope ke query ReportData (yppr058_data)
     * berdasarkan devisi / arbpl / arbpl2 / (optional) nik list
     */
    protected function applyScopeToReportData(Builder $q): void
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

    public function mount(string $plant): void
    {
        $this->plant = trim($plant);

        $saved = session('wi_daily.month_filter', 'this');
        $this->monthFilter = $saved === 'prev' ? 'prev' : 'this';

        // ✅ NEW: load WI mode
        $m = (string) session('wi_daily.wi_mode', 'all');
        if ($m === 'has')  $m = 'with';
        if ($m === 'none') $m = 'without';

        $this->wiMode = in_array($m, ['all','with','without'], true) ? $m : 'all';

        $rm = (string) session('wi_daily.report_mode', 'wi');
        $this->reportMode = in_array($rm, ['wi','korlap'], true) ? $rm : 'wi';

    }

    public function setMonthFilter(string $mode): void
    {
        $mode = $mode === 'prev' ? 'prev' : 'this';
        $this->monthFilter = $mode;

        session(['wi_daily.month_filter' => $this->monthFilter]);
        $this->closeDetailModal();
    }

    public function updatedWiMode($value): void
    {
        $value = (string) $value;

        if ($value === 'has')  $value = 'with';
        if ($value === 'none') $value = 'without';

        $value = in_array($value, ['all','with','without'], true) ? $value : 'all';

        $this->wiMode = $value;
        session(['wi_daily.wi_mode' => $this->wiMode]);

        $this->selectedNiks = [];
        $this->selectedDetailKeys = [];
        $this->closeDetailModal();
    }



    protected function getDateRangeForFilter(): array
    {
        $today = Carbon::today();

        if ($this->monthFilter === 'prev') {
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

    /**
     * Hardening: cek akses NIK (berdasarkan yppr058_data role INDUK + plant filter + scope)
     */
    protected function canAccessNik(string $nik, string $begdaStart, string $begdaEnd): bool
    {
        [$all] = $this->scopeData();
        if ($all) return true;

        $q = ReportData::query()
            ->where('pernr', $nik)
            ->whereBetween('begda', [$begdaStart, $begdaEnd])
            ->whereRaw("UPPER(TRIM(role)) = 'INDUK'")
            ->whereRaw("
                CASE
                    WHEN TRIM(werks) IN ('1001','1002','1003','1015') THEN '1001'
                    WHEN LEFT(TRIM(werks),1)='1' THEN '1000'
                    ELSE CONCAT(LEFT(TRIM(werks),1),'000')
                END = ?
            ", [trim($this->plant)]);

        $this->applyScopeToReportData($q);

        return $q->limit(1)->exists();
    }

    /**
     * Subquery agregasi QM dari yppr058_data (driver utama):
     * - FILTER: role INDUK
     * - WC  : MAX(arbpl)
     * - DEV : MAX(devisi)
     * - NAMA: MAX(cname)
     * - QM  : SUM(mintu)
     * group per pernr+begda
     */
    protected function qmAggSub(string $begdaStart, string $begdaEnd)
    {
        $q = ReportData::query()
            ->selectRaw("
                pernr,
                begda,
                MAX(cname) as nama,
                MAX(arbpl) as wc,
                MAX(devisi) as devisi,
                COALESCE(SUM(mintu),0) as time_qm
            ")
            ->whereBetween('begda', [$begdaStart, $begdaEnd])
            ->whereRaw("UPPER(TRIM(role)) = 'INDUK'")
            ->whereRaw("
                CASE
                    WHEN TRIM(werks) IN ('1001','1002','1003','1015') THEN '1001'
                    WHEN LEFT(TRIM(werks),1)='1' THEN '1000'
                    ELSE CONCAT(LEFT(TRIM(werks),1),'000')
                END = ?
            ", [trim($this->plant)]);

        $this->applyScopeToReportData($q);

        return $q->groupBy('pernr', 'begda')->toBase();
    }

    /**
     * Subquery agregasi WI dari daily_time_wi (hanya pelengkap):
     * ambil tanggal,kode_laravel,nik,total_time_wi (sum)
     *
     * NOTE: ini tetap pakai filter plant_group dari kode_laravel (biar tidak nyasar plant lain)
     */
    protected function wiAggSub(Carbon $start, Carbon $end)
    {
        $plant = trim($this->plant);

        return DailyTimeWi::query()
            ->selectRaw("
                MIN(id) as id,
                tanggal,
                nik,
                MAX(kode_laravel) as kode_laravel,
                COALESCE(SUM(total_time_wi),0) as time_wi
            ")
            ->whereNotNull('kode_laravel')
            ->whereRaw("TRIM(kode_laravel) <> ''")
            ->whereRaw("TRIM(kode_laravel) REGEXP '^[0-9]{4}'")
            ->whereRaw("
                CASE
                    WHEN LEFT(TRIM(kode_laravel),4) IN ('1001','1002','1003','1015') THEN '1001'
                    WHEN LEFT(TRIM(kode_laravel),1) = '1' THEN '1000'
                    ELSE CONCAT(LEFT(TRIM(kode_laravel),1),'000')
                END = ?
            ", [$plant])
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->groupBy('tanggal', 'nik')
            ->toBase();
    }

    /**
     * JOIN utama: QM (yppr058_data role INDUK) LEFT JOIN WI (daily_time_wi)
     * - Driver = QM -> semua baris INDUK tampil
     * - WI optional -> kalau tidak ada -> NULL -> Blade tampil "-"
     */
    protected function detailJoinedQuery($qmAggSub, $wiAggSub)
    {
        return DB::query()
            ->fromSub($qmAggSub, 'qm')
            ->leftJoinSub($wiAggSub, 'wi', function ($join) {
                $join->on('wi.nik', '=', 'qm.pernr')
                    ->on(DB::raw("DATE_FORMAT(wi.tanggal, '%Y%m%d')"), '=', 'qm.begda');
            })
            ->selectRaw("
                wi.id as id,
                DATE_FORMAT(STR_TO_DATE(qm.begda,'%Y%m%d'), '%Y-%m-%d') as tanggal,
                qm.pernr as nik,
                qm.nama as nama,
                qm.wc as wc,
                qm.devisi as devisi,
                qm.time_qm as time_qm,
                wi.kode_laravel as kode_laravel,
                wi.time_wi as time_wi,
                CASE
                    WHEN wi.time_wi IS NULL THEN NULL
                    WHEN wi.time_wi = 0 THEN 0
                    ELSE (qm.time_qm / wi.time_wi) * 100
                END as kpi_pct
            ");
    }

    /**
     * Live search: sekarang nempel ke joined query (QM driver)
     * - cari NIK / Nama / Tanggal / WC / kode_laravel
     */
    protected function applyLiveSearchToJoined($query): void
    {
        $raw = trim((string) $this->q);
        if ($raw === '') return;

        preg_match_all('/"([^"]+)"/u', $raw, $m);
        $phrases = collect($m[1] ?? [])
            ->map(fn ($p) => mb_strtolower(trim($p)))
            ->filter()
            ->values();

        $rest = preg_replace('/"([^"]+)"/u', ' ', $raw);

        $tokens = collect(preg_split('/[\s,]+/u', $rest))
            ->filter()
            ->map(fn($t) => trim((string)$t))
            ->values();

        $nikTokens  = $tokens->filter(fn($t) => preg_match('/^\d{6,}$/', $t));
        $dateTokens = $tokens->filter(fn($t) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $t));
        $kodeTokens = $tokens->filter(fn($t) => preg_match('/^\d{4}$/', $t)); // buat kode_laravel
        $wcTokens   = $tokens->filter(fn($t) => preg_match('/^wc[\w\-]*$/i', $t))->values();

        $textTokens = $tokens
            ->diff($nikTokens)
            ->diff($dateTokens)
            ->diff($kodeTokens)
            ->diff($wcTokens)
            ->values();

        $query->where(function ($q) use ($nikTokens, $dateTokens, $kodeTokens, $wcTokens, $textTokens, $phrases) {

            foreach ($nikTokens as $t) {
                $q->orWhere('qm.pernr', 'LIKE', "%{$t}%");
            }

            foreach ($kodeTokens as $t) {
                $q->orWhere('wi.kode_laravel', 'LIKE', "%{$t}%");
            }

            foreach ($dateTokens as $t) {
                $ymd = str_replace('-', '', $t); // 2026-01-02 -> 20260102
                $q->orWhere('qm.begda', $ymd);
            }

            foreach ($wcTokens as $wc) {
                $like = '%'.mb_strtolower($wc).'%';
                $q->orWhereRaw('LOWER(qm.wc) LIKE ?', [$like]);
            }

            foreach ($textTokens as $t) {
                $lower = mb_strtolower($t);
                $q->orWhereRaw('LOWER(qm.nama) LIKE ?', ["%{$lower}%"]);
            }

            foreach ($phrases as $p) {
                $q->orWhereRaw('LOWER(qm.nama) LIKE ?', ["%{$p}%"]);
                $q->orWhereRaw('LOWER(qm.wc) LIKE ?', ["%{$p}%"]);

                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $p)) {
                    $q->orWhere('qm.begda', str_replace('-', '', $p));
                }
            }
        });
    }

    protected function extractNikTokens(string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') return [];

        // ambil token angka panjang (>= 8 digit)
        $tokens = preg_split('/[\s,]+/u', $raw);
        $tokens = array_map(fn($v) => trim((string)$v), $tokens);
        $tokens = array_filter($tokens);

        $niks = [];
        foreach ($tokens as $t) {
            if (preg_match('/^\d{8,}$/', $t)) {
                $niks[] = $t;
            }
        }

        return array_values(array_unique($niks));
    }

    /**
     * Export Summary (tetap)
     */
    public function export(string $format = 'pdf'): void
    {
        $format = strtolower(trim($format));
        if (!in_array($format, ['pdf', 'xlsx', 'excel'], true)) $format = 'pdf';

        $niks = collect($this->selectedNiks)
            ->map(fn($v) => trim((string)$v))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($niks)) {
            $this->dispatch('wi-toast', type: 'warning', message: 'Pilih minimal 1 NIK untuk Export Report.');
            return;
        }

        session([
            'wi_export.month_filter' => $this->monthFilter,
            'wi_export.q'           => $this->q,
            'wi_export.niks'        => $niks,
        ]);

        $url = $format === 'pdf'
            ? route('wi.export.summary.pdf', ['plant' => $this->plant])
            : route('wi.export.summary.excel', ['plant' => $this->plant]);

        $this->dispatch('wi-open-url', url: $url);
    }

    /**
     * Export Detail (tetap)
     */
    public function exportDetail(string $format = 'pdf'): void
    {
        $format = strtolower(trim($format));
        if (!in_array($format, ['pdf', 'xlsx', 'excel'], true)) $format = 'pdf';

        $summaryNiks = collect($this->selectedNiks)
            ->map(fn($v) => trim((string)$v))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $detailKeys = collect($this->selectedDetailKeys)
            ->map(fn($v) => trim((string)$v))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($summaryNiks) && empty($detailKeys)) {
            $this->dispatch('wi-toast', type: 'warning', message: 'Pilih minimal 1 NIK / tanggal untuk Export Detail.');
            return;
        }

        session([
            'wi_export_detail.month_filter' => $this->monthFilter,
            'wi_export_detail.q'           => $this->q,
            'wi_export_detail.niks'        => $summaryNiks,
            'wi_export_detail.keys'        => $detailKeys,
        ]);

        $url = $format === 'pdf'
            ? route('wi.export.detail.pdf', ['plant' => $this->plant])
            : route('wi.export.detail.excel', ['plant' => $this->plant]);

        $this->dispatch('wi-open-url', url: $url);
    }

    /**
     * SHOW DETAIL MODAL per NIK (driver QM INDUK, WI optional)
     */
    public function showNikDetail(string $clickedNik): void
    {
        $this->selectedNik = trim((string) $clickedNik);

        [$start, $end] = $this->getDateRangeForFilter();
        $begdaStart = $start->format('Ymd');
        $begdaEnd   = $end->format('Ymd');

        // hardening
        if (!$this->canAccessNik($this->selectedNik, $begdaStart, $begdaEnd)) {
            $this->dispatch('wi-toast', type: 'error', message: 'Anda tidak memiliki akses untuk NIK ini.');
            return;
        }

        // QM per tanggal untuk NIK ini (role INDUK)
        $qmQ = ReportData::query()
            ->selectRaw("
                begda,
                MAX(cname) as nama,
                MAX(arbpl) as wc,
                MAX(devisi) as devisi,
                COALESCE(SUM(mintu),0) as time_qm
            ")
            ->where('pernr', $this->selectedNik)
            ->whereBetween('begda', [$begdaStart, $begdaEnd])
            ->whereRaw("UPPER(TRIM(role)) = 'INDUK'")
            ->whereRaw("
                CASE
                    WHEN TRIM(werks) IN ('1001','1002','1003','1015') THEN '1001'
                    WHEN LEFT(TRIM(werks),1)='1' THEN '1000'
                    ELSE CONCAT(LEFT(TRIM(werks),1),'000')
                END = ?
            ", [trim($this->plant)]);

        $this->applyScopeToReportData($qmQ);

        $qmRows = $qmQ->groupBy('begda')->get();

        $qmByDate = $qmRows->keyBy(function ($r) {
            return Carbon::createFromFormat('Ymd', (string) $r->begda)->toDateString();
        });

        // WI per tanggal untuk NIK ini (optional)
        $plant = trim($this->plant);

        $wiRows = DailyTimeWi::query()
            ->where('nik', $this->selectedNik)
            ->whereNotNull('kode_laravel')
            ->whereRaw("TRIM(kode_laravel) <> ''")
            ->whereRaw("TRIM(kode_laravel) REGEXP '^[0-9]{4}'")
            ->whereRaw("
                CASE
                    WHEN LEFT(TRIM(kode_laravel),4) IN ('1001','1002','1003','1015') THEN '1001'
                    WHEN LEFT(TRIM(kode_laravel),1) = '1' THEN '1000'
                    ELSE CONCAT(LEFT(TRIM(kode_laravel),1),'000')
                END = ?
            ", [$plant])
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("
                tanggal,
                MAX(kode_laravel) as kode_laravel,
                COALESCE(SUM(total_time_wi),0) as time_wi
            ")
            ->groupBy('tanggal')
            ->get();

        $wiByDate = $wiRows->keyBy(fn($r) => Carbon::parse($r->tanggal)->toDateString());

        $name = optional($qmRows->first())->nama ?? '-';

        // total modal
        $sumWi = (float) $wiRows->sum('time_wi');
        $sumQm = (float) $qmRows->sum('time_qm');
        $kpi   = $sumWi == 0 ? 0 : ($sumQm / $sumWi) * 100;

        $this->detailTotalWi = $sumWi;
        $this->detailTotalQm = $sumQm;
        $this->detailKpi     = $kpi;

        // devisi ringkas modal
        $devs = collect($qmRows)
            ->pluck('devisi')
            ->map(fn($v) => trim((string)$v))
            ->filter()
            ->unique()
            ->values();

        $this->detailDevisi = $devs->count() === 1
            ? $devs->first()
            : ($devs->isEmpty() ? '-' : 'MULTI');

        // build detail full date range (layout kamu tetap)
        $detail = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();

            $qm = $qmByDate->get($key);
            $wi = $wiByDate->get($key);

            $timeWi = $wi ? (float) $wi->time_wi : null; // kalau tidak ada -> NULL (Blade tampil "-")
            $timeQm = $qm ? (float) $qm->time_qm : null;

            $wc     = $qm->wc ?? null;
            $devisi = $qm->devisi ?? null;

            $namaRow = $qm->nama ?? $name;

            $detail[] = [
                'id'      => null,
                'tanggal' => $key,
                'nik'     => $this->selectedNik,
                'nama'    => (string) $namaRow,
                'wc'      => $wc,
                'devisi'  => $devisi,
                'time_wi' => $timeWi,
                'time_qm' => $timeQm,
                'kpi_pct' => is_null($timeWi) ? null : ($timeWi == 0 ? 0 : (((float)($timeQm ?? 0)) / $timeWi) * 100),
            ];

            $cursor->addDay();
        }

        $this->detailData = $detail;
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedNik = null;
        $this->detailData = [];

        $this->detailTotalWi = null;
        $this->detailTotalQm = null;
        $this->detailKpi = null;
        $this->detailDevisi = null;
    }

    public function render()
    {
        // ✅ base TANPA search dulu
        [$detailJoinedBase, $start, $end] = $this->buildDetailJoinedForCurrentFilters(false);

        // ✅ untuk missing NIK (NO SEARCH)
        $detailJoinedNoSearch = clone $detailJoinedBase;

        // ✅ yang dipakai tampil: baru apply search SEKALI
        $detailJoined = $detailJoinedBase;
        $this->applyLiveSearchToJoined($detailJoined);


        // =========================================
        // ✅ 4) JIKA MODE = KORLAP, RETURN DI SINI
        // =========================================
        if (($this->reportMode ?? 'wi') === 'korlap') {

            // agregasi per KORLAP (SUM dari semua NIK pada WC yang ada di wc_korlap)
            $korlapQuery = DB::query()
                ->from('nik_korlap as nk')
                ->whereRaw('TRIM(nk.plant) = ?', [trim($this->plant)]) // ✅ FILTER PLANT DI SINI
                ->leftJoinSub($detailJoined, 'd', function ($join) {
                    $join->on(DB::raw('1'), '=', DB::raw('1'))
                        ->whereRaw("JSON_CONTAINS(nk.wc_korlap, JSON_QUOTE(d.wc))");
                })
                ->selectRaw("
                    nk.nik as korlap_nik,
                    nk.nama as korlap_nama,
                    MAX(nk.wc_korlap) as wc_korlap,
                    COUNT(DISTINCT d.nik) as nik_count,
                    COALESCE(SUM(d.time_wi),0) as time_wi_sum,
                    COALESCE(SUM(d.time_qm),0) as time_qm_sum,
                    CASE
                        WHEN COALESCE(SUM(d.time_wi),0)=0 THEN 0
                        ELSE (COALESCE(SUM(d.time_qm),0)/COALESCE(SUM(d.time_wi),0))*100
                    END as kpi_pct
                ")
                ->groupBy('nk.nik', 'nk.nama');
            // ✅ filter wiMode juga berlaku untuk korlap
            if ($this->wiMode === 'with') {
                $korlapQuery->havingRaw("COALESCE(SUM(d.time_wi),0) > 0");
            } elseif ($this->wiMode === 'without') {
                $korlapQuery->havingRaw("COALESCE(SUM(d.time_wi),0) = 0");
            }

            $korlapData = $korlapQuery
                ->orderBy('nk.nama')
                ->get()
                ->map(fn($r) => [
                    'korlap_nik'  => (string)$r->korlap_nik,
                    'korlap_nama' => (string)$r->korlap_nama,
                    'wc_korlap'   => (json_decode($r->wc_korlap ?? '[]', true) ?: []),
                    'nik_count'   => (int)$r->nik_count,
                    'time_wi_sum' => (float)$r->time_wi_sum,
                    'time_qm_sum' => (float)$r->time_qm_sum,
                    'kpi_pct'     => (float)$r->kpi_pct,
                ])
                ->all();

            return view('livewire.wi-daily-report', [
                'korlapData' => $korlapData,
                'rangeStart' => $start->toDateString(),
                'rangeEnd'   => $end->toDateString(),

                // supaya blade aman walau tidak dipakai
                'reportData' => collect([]),
                'missingNiks' => [],
                'overallWi' => 0,
                'overallQm' => 0,
                'overallKpi' => 0,
                'overallDevisi' => '-',
            ]);
        }

        // =========================================
        // ✅ 5) MODE WI (kode kamu yang lama) LANJUT
        // =========================================

        // =======================
        // DETEKSI NIK TIDAK ADA
        // =======================
        $searchedNiks = $this->extractNikTokens($this->q);
        $missingNiks = [];

        if (!empty($searchedNiks)) {
            $foundNiks = DB::query()
                ->fromSub($detailJoinedNoSearch, 'd')
                ->whereIn('nik', $searchedNiks)
                ->select('nik')
                ->distinct()
                ->pluck('nik')
                ->map(fn($v) => (string)$v)
                ->all();

            $foundSet = array_flip($foundNiks);
            $missingNiks = array_values(array_filter($searchedNiks, fn($n) => !isset($foundSet[$n])));
        }

        // SUMMARY per NIK (dari joined per tanggal)
        $reportDataQuery = DB::query()
            ->fromSub($detailJoined, 'd')
            ->selectRaw("
                nik,
                MIN(tanggal) as min_tanggal,
                MAX(tanggal) as max_tanggal,
                MAX(nama) as nama,
                MAX(wc) as wc,
                MAX(devisi) as devisi,

                COALESCE(SUM(time_wi),0) as time_wi_sum,
                COALESCE(SUM(time_qm),0) as time_qm_sum,

                MAX(CASE WHEN time_wi IS NULL THEN 0 ELSE 1 END) as has_wi,

                CASE
                    WHEN SUM(time_wi) = 0 THEN 0
                    ELSE (SUM(time_qm) / SUM(time_wi)) * 100
                END as kpi_pct
            ")
            ->groupBy('nik');

        // ✅ FILTER MODE: with / without
        if ($this->wiMode === 'with') {
            $reportDataQuery->havingRaw("COALESCE(SUM(time_wi),0) > 0");
        } elseif ($this->wiMode === 'without') {
            $reportDataQuery->havingRaw("COALESCE(SUM(time_wi),0) = 0");
        }

        $reportData = $reportDataQuery
            ->orderByRaw("CAST(nik AS UNSIGNED) ASC")
            ->get();

        // TOTAL keseluruhan
        $overallWi = 0.0;
        $overallQm = 0.0;

        foreach ($reportData as $r) {
            $overallWi += (float) ($r->time_wi_sum ?? 0);
            $overallQm += (float) ($r->time_qm_sum ?? 0);
        }

        $overallKpi = $overallWi == 0 ? 0 : ($overallQm / $overallWi) * 100;

        // overall devisi ringkas
        $overallDevisiList = collect($reportData)
            ->pluck('devisi')
            ->map(fn($v) => trim((string)$v))
            ->filter()
            ->unique()
            ->values();

        $overallDevisi = $overallDevisiList->count() === 1
            ? $overallDevisiList->first()
            : ($overallDevisiList->isEmpty() ? '-' : 'MULTI');

        return view('livewire.wi-daily-report', [
            'reportData' => $reportData,
            'rangeStart' => $start->toDateString(),
            'rangeEnd'   => $end->toDateString(),

            'overallWi'  => $overallWi,
            'overallQm'  => $overallQm,
            'overallKpi' => $overallKpi,

            'overallDevisi' => $overallDevisi,
            'missingNiks' => $missingNiks,

            // korlapData tidak dipakai di mode wi (biar blade aman kalau dicek)
            'korlapData' => [],
        ]);
    }
}
