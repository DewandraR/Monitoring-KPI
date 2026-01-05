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
    public string $plant;               // contoh: "3000"
    public string $monthFilter = 'this';

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

    /**
     * Terapkan scope ke query DailyTimeWi dengan "melihat" scope dari yppr058_data
     * (per-row: nik + tanggal harus punya baris di yppr058_data yang match scope)
     */
    protected function applyScopeToWiQuery(Builder $wi, Carbon $start, Carbon $end): void
    {
        [$all, $dev, $arbpl, $niks] = $this->scopeData();

        if ($all) return;

        if (empty($dev) && empty($arbpl) && empty($niks)) {
            $wi->whereRaw('1=0');
            return;
        }

        $begdaStart = $start->format('Ymd');
        $begdaEnd   = $end->format('Ymd');

        $rdTable = (new ReportData())->getTable();     // yppr058_data
        $wiTable = (new DailyTimeWi())->getTable();    // daily_time_wi

        $wi->whereExists(function ($sq) use ($rdTable, $wiTable, $begdaStart, $begdaEnd, $dev, $arbpl, $niks) {
            $sq->selectRaw('1')
                ->from($rdTable)
                ->whereColumn("$rdTable.pernr", "$wiTable.nik")
                ->whereRaw("$rdTable.begda = DATE_FORMAT($wiTable.tanggal, '%Y%m%d')")
                ->whereBetween("$rdTable.begda", [$begdaStart, $begdaEnd])
                ->where(function ($m) use ($rdTable, $wiTable) {
                    $m->whereRaw("TRIM($rdTable.werks) = LEFT(TRIM($wiTable.kode_laravel),4)")
                    ->orWhereRaw("TRIM($rdTable.werks) = CONCAT(LEFT(TRIM($wiTable.kode_laravel),1),'000')");
                })
                ->where(function ($w) use ($rdTable, $dev, $arbpl, $niks) {

                    if (!empty($niks)) {
                        $w->orWhereIn("$rdTable.pernr", $niks);
                    }

                    if (!empty($dev)) {
                        $w->orWhereIn(DB::raw("UPPER(TRIM($rdTable.devisi))"), $dev);
                    }

                    if (!empty($arbpl)) {
                        $w->orWhereIn(DB::raw("UPPER(TRIM($rdTable.arbpl))"), $arbpl)
                          ->orWhereIn(DB::raw("UPPER(TRIM($rdTable.arbpl2))"), $arbpl);
                    }
                });
        });
    }

    /**
     * Validasi cepat akses NIK (buat hardening showNikDetail/export)
     */
    protected function canAccessNik(string $nik, string $begdaStart, string $begdaEnd): bool
    {
        [$all] = $this->scopeData();
        if ($all) return true;

        $q = ReportData::query()
            ->where('pernr', $nik)
            ->whereBetween('begda', [$begdaStart, $begdaEnd]);

        $this->applyScopeToReportData($q);

        return $q->limit(1)->exists();
    }

    /**
     * Export Summary
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
     * Export Detail
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

    public function mount(string $plant): void
    {
        $this->plant = trim($plant);

        $saved = session('wi_daily.month_filter', 'this');
        $this->monthFilter = $saved === 'prev' ? 'prev' : 'this';
    }

    public function setMonthFilter(string $mode): void
    {
        $mode = $mode === 'prev' ? 'prev' : 'this';
        $this->monthFilter = $mode;

        session(['wi_daily.month_filter' => $this->monthFilter]);
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
     * Base query WI (daily_time_wi) + scope dari yppr058_data
     */
    protected function baseWiQuery(): array
    {
        $plant = trim($this->plant); // ini sekarang "plant_group" (1000/1001/2000/3000...)
        [$start, $end] = $this->getDateRangeForFilter();

        $q = DailyTimeWi::query()
            ->whereNotNull('kode_laravel')
            ->whereRaw("TRIM(kode_laravel) <> ''")
            // pastikan minimal 4 digit angka di depan (biar aman)
            ->whereRaw("TRIM(kode_laravel) REGEXP '^[0-9]{4}'")
            // INI KUNCI: filter berdasarkan plant_group, bukan prefix saja
            ->whereRaw("
                CASE
                    WHEN LEFT(TRIM(kode_laravel),4) IN ('1001','1002','1003','1015') THEN '1001'
                    WHEN LEFT(TRIM(kode_laravel),1) = '1' THEN '1000'
                    ELSE CONCAT(LEFT(TRIM(kode_laravel),1),'000')
                END = ?
            ", [$plant])
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()]);

        $this->applyScopeToWiQuery($q, $start, $end);

        return [$q, $start, $end];
    }

    /**
     * Subquery agregasi QM dari yppr058_data:
     * - WC  : MAX(arbpl)
     * - DEV : MAX(devisi)
     * - QM  : SUM(mintu)
     * group per pernr+begda
     */
    protected function qmAggSub(string $begdaStart, string $begdaEnd)
    {
        $q = ReportData::query()
            ->selectRaw("
                pernr,
                begda,
                MAX(arbpl) as wc,
                MAX(devisi) as devisi,
                COALESCE(SUM(mintu),0) as time_qm
            ")
            ->whereBetween('begda', [$begdaStart, $begdaEnd])
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
     * Live search (filter)
     */
    protected function applyLiveSearch($query, Carbon $start, Carbon $end): void
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
        $kodeTokens = $tokens->filter(fn($t) => preg_match('/^\d{4}$/', $t));
        $idTokens   = $tokens->filter(fn($t) => preg_match('/^\d{1,5}$/', $t));

        // token WC
        $wcTokens = $tokens->filter(fn($t) => preg_match('/^wc[\w\-]*$/i', $t))->values();

        $textTokens = $tokens
            ->diff($nikTokens)
            ->diff($dateTokens)
            ->diff($kodeTokens)
            ->diff($idTokens)
            ->diff($wcTokens)
            ->values();

        $begdaStart = $start->format('Ymd');
        $begdaEnd   = $end->format('Ymd');

        $query->where(function ($q) use (
            $nikTokens, $dateTokens, $kodeTokens, $idTokens, $textTokens, $phrases,
            $wcTokens, $begdaStart, $begdaEnd
        ) {

            foreach ($idTokens as $t) {
                $q->orWhere('id', (int)$t);
            }

            foreach ($nikTokens as $t) {
                $q->orWhere('nik', 'LIKE', "%{$t}%");
            }

            foreach ($kodeTokens as $t) {
                $q->orWhere('kode_laravel', 'LIKE', "%{$t}%");
            }

            foreach ($dateTokens as $t) {
                $q->orWhereDate('tanggal', $t);
            }

            foreach ($textTokens as $t) {
                $lower = mb_strtolower($t);
                $q->orWhereRaw('LOWER(nama) LIKE ?', ["%{$lower}%"]);
            }

            foreach ($phrases as $p) {
                $q->orWhereRaw('LOWER(nama) LIKE ?', ["%{$p}%"]);

                if (preg_match('/^wc[\w\-]*$/i', $p)) {
                    $like = "%{$p}%";
                    $q->orWhereExists(function ($sq) use ($like, $begdaStart, $begdaEnd) {
                        $table = (new ReportData)->getTable();

                        $sq->selectRaw('1')
                            ->from($table)
                            ->whereColumn($table.'.pernr', 'daily_time_wi.nik')
                            ->whereBetween($table.'.begda', [$begdaStart, $begdaEnd])
                            ->whereRaw('LOWER('.$table.'.arbpl) LIKE ?', [$like]);
                    });
                }
            }

            foreach ($wcTokens as $wc) {
                $wcLower = mb_strtolower($wc);
                $like = "%{$wcLower}%";

                $q->orWhereExists(function ($sq) use ($like, $begdaStart, $begdaEnd) {
                    $table = (new ReportData)->getTable();

                    $sq->selectRaw('1')
                        ->from($table)
                        ->whereColumn($table.'.pernr', 'daily_time_wi.nik')
                        ->whereBetween($table.'.begda', [$begdaStart, $begdaEnd])
                        ->whereRaw('LOWER('.$table.'.arbpl) LIKE ?', [$like]);
                });
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

        // unique + urut sesuai input
        $niks = array_values(array_unique($niks));
        return $niks;
    }

    /**
     * Query detail per tanggal (WI SUM),
     * lalu JOIN ke QM sub (SUM mintu) + WC + DEV via begda.
     */
    protected function detailJoinedQuery($wiBase)
    {
        [$start, $end] = $this->getDateRangeForFilter();
        $begdaStart = $start->format('Ymd');
        $begdaEnd   = $end->format('Ymd');

        $wiAggSub = (clone $wiBase)
            ->selectRaw("
                MIN(id) as id,
                tanggal,
                nik,
                MAX(nama) as nama,
                MAX(kode_laravel) as kode_laravel,
                COALESCE(SUM(total_time_wi),0) as time_wi
            ")
            ->groupBy('tanggal', 'nik')
            ->toBase();

        $qmSub = $this->qmAggSub($begdaStart, $begdaEnd);

        return DB::query()
            ->fromSub($wiAggSub, 'wi')
            ->leftJoinSub($qmSub, 'qm', function ($join) {
                $join->on('qm.pernr', '=', 'wi.nik')
                    ->on('qm.begda', '=', DB::raw("DATE_FORMAT(wi.tanggal, '%Y%m%d')"));
            })
            ->selectRaw("
                wi.id,
                wi.tanggal,
                wi.nik,
                wi.nama,
                wi.time_wi,
                COALESCE(qm.time_qm,0) as time_qm,
                qm.wc as wc,
                qm.devisi as devisi,
                CASE
                    WHEN wi.time_wi = 0 THEN 0
                    ELSE (COALESCE(qm.time_qm,0) / wi.time_wi) * 100
                END as kpi_pct
            ");
    }

    /**
     * SHOW DETAIL MODAL per NIK
     */
    public function showNikDetail(string $clickedNik): void
    {
        $this->selectedNik = trim((string) $clickedNik);

        [$wiBase, $start, $end] = $this->baseWiQuery();

        $begdaStart = $start->format('Ymd');
        $begdaEnd   = $end->format('Ymd');

        // hardening
        if (!$this->canAccessNik($this->selectedNik, $begdaStart, $begdaEnd)) {
            $this->dispatch('wi-toast', type: 'error', message: 'Anda tidak memiliki akses untuk NIK ini.');
            return;
        }

        // QM per tanggal untuk NIK ini (ambil WC + DEV + QM)
        $qmQ = ReportData::query()
            ->selectRaw("
                begda,
                MAX(arbpl) as wc,
                MAX(devisi) as devisi,
                COALESCE(SUM(mintu),0) as time_qm
            ")
            ->where('pernr', $this->selectedNik)
            ->whereBetween('begda', [$begdaStart, $begdaEnd]);
            $qmQ->whereRaw("
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

        // WI detail join (tanggal yang ada WI)
        $rows = $this->detailJoinedQuery((clone $wiBase)->where('nik', $this->selectedNik))
            ->orderBy('wi.tanggal', 'asc')
            ->get();

        $byDate = $rows->keyBy(fn ($r) => Carbon::parse($r->tanggal)->toDateString());

        $name = optional($rows->first())->nama ?? '-';

        // total modal
        $sumWi = (float) $rows->sum('time_wi');
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

        // build detail full date range
        $detail = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $qm  = $qmByDate->get($key);

            if ($byDate->has($key)) {
                $r = $byDate->get($key);

                $timeWi = (float) $r->time_wi;
                $timeQm = $qm ? (float) $qm->time_qm : (float) $r->time_qm;

                $wc     = $qm ? $qm->wc : $r->wc;
                $devisi = $qm ? ($qm->devisi ?? null) : ($r->devisi ?? null);

                $detail[] = [
                    'id'      => $r->id,
                    'tanggal' => $key,
                    'nik'     => (string) $r->nik,
                    'nama'    => (string) $r->nama,
                    'wc'      => $wc,
                    'devisi'  => $devisi,
                    'time_wi' => $timeWi,
                    'time_qm' => $timeQm,
                    'kpi_pct' => $timeWi == 0 ? 0 : ($timeQm / $timeWi) * 100,
                ];
            } else {
                $detail[] = [
                    'id'      => null,
                    'tanggal' => $key,
                    'nik'     => $this->selectedNik,
                    'nama'    => $name,
                    'wc'      => $qm->wc ?? null,
                    'devisi'  => $qm->devisi ?? null,
                    'time_wi' => null,
                    'time_qm' => $qm ? (float) $qm->time_qm : null,
                    'kpi_pct' => null,
                ];
            }

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
        [$wiBase, $start, $end] = $this->baseWiQuery();

        // clone base query (tanpa live search) untuk cek NIK tidak ditemukan
        $wiBaseNoSearch = clone $wiBase;

        // live search (filter tampilan)
        $this->applyLiveSearch($wiBase, $start, $end);

        // =======================
        // DETEKSI NIK TIDAK ADA
        // =======================
        $searchedNiks = $this->extractNikTokens($this->q);
        $missingNiks = [];

        if (!empty($searchedNiks)) {
            $foundNiks = (clone $wiBaseNoSearch)
                ->whereIn('nik', $searchedNiks)
                ->select('nik')
                ->distinct()
                ->pluck('nik')
                ->map(fn($v) => (string)$v)
                ->all();

            $foundSet = array_flip($foundNiks);

            $missingNiks = array_values(array_filter($searchedNiks, fn($n) => !isset($foundSet[$n])));
        }

        $detailJoined = $this->detailJoinedQuery($wiBase);

        // SUMMARY per NIK
        $reportData = DB::query()
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
                CASE
                    WHEN SUM(time_wi) = 0 THEN 0
                    ELSE (SUM(time_qm) / SUM(time_wi)) * 100
                END as kpi_pct
            ")
            ->groupBy('nik')
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

        // overall devisi ringkas (unik/MULTI/-) -> opsional dipakai di blade
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

            // opsional
            'overallDevisi' => $overallDevisi,
            'missingNiks' => $missingNiks,
        ]);
    }
}
