<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\ReportData;
use App\Models\Yppr058SapLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

#[Layout('layouts.app')]
class ReportGenerator extends Component
{
    public $q = '';
    public $werks;

    public $showDetailModal = false;
    public $selectedPernr = null;
    public $detailData = [];

    public string $monthFilter = 'this';

    // Simpan pilihan user & list pernr di halaman saat ini
    // <<< BARU: simpan pilihan user & list pernr di halaman saat ini >>>
    public array $selectedPernrs = [];
    public array $currentPagePernrs = [];

    // Versi array dari summary, khusus untuk saveToSap (JANGAN bernama reportData)
    public array $reportDataForSave = [];

    private array $aggregateColumns = [
        'total_jam',
        'mint2',
        'mintu',
        'mintu2',
        'mintu3',
        'gji',
        'gji2',
        'varnt',   // varnt1 dihilangkan, nanti dihitung manual di SQL
    ];

    // pernr|begda yang dicentang di modal detail
    public array $selectedDetailKeys = [];

    public bool $onlyInduk = false;

    // SAVE ke SAP
    public bool $showSaveSapModal = false;
    public string $sapUser = '';
    public string $sapPass = '';
    public array $saveResults = [];
    public ?string $sapAuthError = null;

    public bool $showTotalUpahModal = false;
    public string $totalUpahPin = '';
    public ?string $totalUpahError = null;
    public ?float $totalUpahGji = null;
    public ?float $totalUpahGji2 = null;
    public ?string $totalUpahPeriodLabel = null;

    // -------------------------------------------------------------------------
    // EXPORT DETAIL (PDF / EXCEL) - pakai session
    // -------------------------------------------------------------------------
    public function exportDetail(string $format)
    {
        // Kumpulkan semua NIK dari:
        // 1) checkbox detail (selectedDetailKeys: "pernr|begda")
        // 2) checkbox summary (selectedPernrs)
        // Per NIK -> selalu export FULL range tanggal (1..H-1)

        $pernrSet = [];

        // Dari DETAIL
        foreach ($this->selectedDetailKeys as $key) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            [$pernr] = array_pad(explode('|', $key, 2), 2, '');
            $pernr = trim((string) $pernr);

            if ($pernr === '') {
                continue;
            }

            $pernrSet[$pernr] = true;
        }

        // Dari SUMMARY
        foreach ($this->selectedPernrs as $p) {
            $p = trim((string) $p);
            if ($p === '') {
                continue;
            }
            $pernrSet[$p] = true;
        }

        if (empty($pernrSet)) {
            // tidak ada apapun yang dipilih
            return;
        }

        // Normalisasi: jadikan array items,
        // dates = []  => artinya "semua tanggal" (full range) akan di-handle di controller
        $items = [];
        foreach (array_keys($pernrSet) as $pernr) {
            $items[] = [
                'pernr' => $pernr,
                'dates' => [],   // selalu full range
            ];
        }

        // Simpan ke session untuk dibaca controller export detail
        session()->put('report_export_detail.items', $items);
        session()->put('report_export_detail.month_filter', $this->monthFilter); // <<< TAMBAH INI

        if ($format === 'pdf') {
            return redirect()->route('report-data.export-detail-pdf', ['werks' => $this->werks]);
        }

        if ($format === 'excel') {
            return redirect()->route('report-data.export-detail-excel', ['werks' => $this->werks]);
        }
    }

    // -------------------------------------------------------------------------
    // LIFECYCLE
    // -------------------------------------------------------------------------
    public function mount($werks)
    {
        $this->werks = strtoupper(trim($werks));

        // Ambil state bulan terakhir dari session (default 'this')
        $saved = session('yppr058.month_filter', 'this');
        $this->monthFilter = $saved === 'prev' ? 'prev' : 'this';
    }

    protected function getDateRangeForFilter(): array
    {
        $today = Carbon::today();

        if ($this->monthFilter === 'prev') {
            // BULAN KEMARIN: full 1..akhir
            $start = $today->copy()->subMonth()->startOfMonth();
            $end   = $today->copy()->subMonth()->endOfMonth();
        } else {
            // BULAN INI: 1..H-1 (kemarin)
            if ($today->day === 1) {
                // Kalau tgl 1, tidak ada H-1 → fallback bulan kemarin full
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
     * Dipanggil dari Blade saat user klik toggle Bulan ini/Bulan kemarin.
     */
    public function setMonthFilter(string $mode): void
    {
        // Normalisasi nilai
        $mode = $mode === 'prev' ? 'prev' : 'this';

        // Update state Livewire
        $this->monthFilter = $mode;

        // SIMPAN ke session supaya bertahan setelah reload
        session(['yppr058.month_filter' => $this->monthFilter]);

        // Opsional: tutup modal detail supaya tidak pakai bulan lama
        $this->detailData      = [];
        $this->selectedPernr   = null;
        $this->showDetailModal = false;
    }


    // -------------------------------------------------------------------------
    // DETAIL PER NIK (isi tanggal kosong dengan placeholder sampai H-1)
    // -------------------------------------------------------------------------
    public function showPernrDetail($clickedPernr)
    {
        $clickedPernr = (string) $clickedPernr;
        $this->selectedPernr = $clickedPernr;

        // ambil range tanggal sesuai filter bulan global
        [$start, $end] = $this->getDateRangeForFilter();

        if ($end->lt($start)) {
            $this->detailData = [];
            $this->showDetailModal = false;
            return;
        }

        $scopeAll   = (bool) request()->attributes->get('data_scope_all', false);
        $scopeDev   = (array) request()->attributes->get('data_scope_devisi', []);
        $scopeArbpl = (array) request()->attributes->get('data_scope_arbpl', []);

        $q = ReportData::query()
            ->whereRaw('UPPER(TRIM(werks)) = ?', [$this->werks])
            ->where('pernr', $clickedPernr)
            ->whereBetween('begda', [$start->format('Ymd'), $end->format('Ymd')]);

        if (!$scopeAll) {
            if (empty($scopeDev) && empty($scopeArbpl)) {
                $q->whereRaw('1=0');
            } else {
                $q->where(function ($qq) use ($scopeDev, $scopeArbpl) {
                    if (!empty($scopeDev)) {
                        $qq->orWhereIn('devisi', $scopeDev);
                    }
                    if (!empty($scopeArbpl)) {
                        $qq->orWhereIn('arbpl', $scopeArbpl)
                        ->orWhereIn('arbpl2', $scopeArbpl);
                    }
                });
            }
        }

        $rows = $q->orderBy('begda', 'asc')->get();

        $byDate = $rows->keyBy('begda');
        $name   = optional($rows->first())->cname;

        $detail = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $key = $cursor->format('Ymd');

            if ($byDate->has($key)) {
                $detail[] = $byDate->get($key)->toArray();
            } else {
                $detail[] = [
                    'pernr'     => $clickedPernr,
                    'begda'     => $key,
                    'total_jam' => null,
                    'mint2'     => null,
                    'mintu'     => null,
                    'mintu2'    => null,
                    'mintu3'    => null,
                    'cname'     => $name ?? '-',
                    'gji'       => null,
                    'gji2'      => null,
                    'varnt'     => null,
                    'arbpl'     => null,
                    'desc'      => null,
                    'role'      => null,
                    'devisi'    => null,
                    'arbpl2'    => null,
                    'werks'     => $this->werks,
                    'shift'     => null,
                ];
            }

            $cursor->addDay();
        }

        $this->detailData = $detail;
        $this->showDetailModal = !empty($detail);
    }

    public function closeDetailModal()
    {
        $this->showDetailModal = false;
        $this->selectedPernr   = null;
        $this->detailData      = [];
    }

    // -------------------------------------------------------------------------
    // MODAL SAVE SAP
    // -------------------------------------------------------------------------
    public function openSaveSapModal()
    {
        // pastikan ada NIK dicentang di summary
        $pernrs = array_values(array_unique(
            array_map('strval', $this->selectedPernrs ?? [])
        ));

        if (empty($pernrs)) {
            session()->flash('error', 'Pilih minimal satu Personal No. di tabel ringkasan.');
            return;
        }

        $this->sapUser      = '';
        $this->sapPass      = '';
        $this->saveResults  = [];
        $this->sapAuthError = null;

        $this->showSaveSapModal = true;
    }

    public function closeSaveSapModal()
    {
        $this->showSaveSapModal = false;
        $this->sapAuthError     = null;
    }

    public function openTotalUpahModal(): void
    {
        $this->totalUpahPin = '';
        $this->totalUpahError = null;
        $this->totalUpahGji = null;
        $this->totalUpahGji2 = null;
        $this->totalUpahPeriodLabel = null;

        $this->showTotalUpahModal = true;
    }

    public function closeTotalUpahModal(): void
    {
        $this->showTotalUpahModal = false;
        $this->totalUpahError = null;
    }

    public function calculateTotalUpah(): void
    {
        // PIN hardcode 332211
        if ($this->totalUpahPin !== '332211') {
            $this->totalUpahError = 'PIN salah. Silakan coba lagi.';
            $this->totalUpahGji = null;
            $this->totalUpahGji2 = null;
            $this->totalUpahPeriodLabel = null;
            return;
        }

        $this->totalUpahError = null;

        // Ambil range tanggal yang sama dengan filter bulan global
        [$start, $end] = $this->getDateRangeForFilter();

        $query = ReportData::query()
            ->whereRaw('UPPER(TRIM(werks)) = ?', [$this->werks])
            ->whereBetween('begda', [$start->format('Ymd'), $end->format('Ymd')]);

        // Kalau mau total mengikuti filter "onlyInduk", buka komentar ini:
        // if ($this->onlyInduk) {
        //     $query->whereRaw('UPPER(TRIM(role)) = ?', ['INDUK']);
        // }

        $totals = $query
            ->selectRaw('COALESCE(SUM(gji),0) AS total_gji, COALESCE(SUM(gji2),0) AS total_gji2')
            ->first();

        $this->totalUpahGji  = (float) ($totals->total_gji  ?? 0);
        $this->totalUpahGji2 = (float) ($totals->total_gji2 ?? 0);

        // Label periode untuk ditampilkan di modal
        $this->totalUpahPeriodLabel = $start->format('d-m-Y') . ' s.d. ' . $end->format('d-m-Y');
    }

    // -------------------------------------------------------------------------
    // SAVE KE SAP (CALL API PYTHON + LOG KE DB)
    // -------------------------------------------------------------------------
    public function saveToSap()
    {
        // Reset error & state SAP
        $this->resetErrorBag(['sapUser', 'sapPass']);
        $this->sapAuthError  = null;
        $this->saveResults   = [];

        // 1. Validasi input SAP user & password
        $this->validate(
            [
                'sapUser' => ['required', 'string'],
                'sapPass' => ['required', 'string'],
            ],
            [
                'sapUser.required' => 'SAP User wajib diisi.',
                'sapPass.required' => 'SAP Password wajib diisi.',
            ]
        );

        // 2. Ambil daftar NIK yang dipilih di summary
        $selectedPernrs = collect($this->selectedPernrs ?? [])
            ->filter()
            ->map(fn($p) => trim((string) $p))
            ->unique()
            ->values();

        if ($selectedPernrs->isEmpty()) {
            $this->addError('sapUser', 'Tidak ada Personal No. yang dipilih untuk di-SAVE.');
            return;
        }

        // 3. Ambil data summary (disimpan di reportDataForSave) dan mapping jadi items[] untuk API Python
        $reportCollection = collect($this->reportDataForSave ?? []);
        $items = [];

        foreach ($selectedPernrs as $pernr) {
            // Cari baris summary untuk NIK ini
            $row = $reportCollection->first(function ($row) use ($pernr) {
                // Support array atau object (stdClass/Eloquent)
                if (is_array($row)) {
                    return (string) ($row['pernr'] ?? '') === (string) $pernr;
                }

                return (string) ($row->pernr ?? '') === (string) $pernr;
            });

            if (!$row) {
                continue;
            }

            // Helper ambil field, aman untuk array/obj
            $get = function ($row, string $key) {
                if (is_array($row)) {
                    return $row[$key] ?? null;
                }

                return $row->$key ?? null;
            };

            // Sesuai format yang diminta API Flask app_yppr058_save.py
            $items[] = [
                'pernr'      => (string) $get($row, 'pernr'),
                'cname'      => (string) $get($row, 'cname'),
                'arbpl'      => (string) $get($row, 'arbpl'),     // WC personal
                'start_date' => (string) $get($row, 'min_begda'), // yyyymmdd
                'end_date'   => (string) $get($row, 'max_begda'), // yyyymmdd
                'mint2'      => (int) ($get($row, 'mint2') ?? 0),    // Menit Conf
                'mintu'      => (int) ($get($row, 'mintu') ?? 0),    // Menit Inspect
                'mintu2'     => (int) ($get($row, 'mintu2') ?? 0),   // Detik Inspect
                'mintu3'     => (int) ($get($row, 'mintu3') ?? 0),   // Detik Konfirmasi
            ];
        }

        if (empty($items)) {
            $this->addError('sapUser', 'Tidak berhasil membangun data SAVE dari pilihan saat ini.');
            return;
        }

        // 4. Siapkan info batch & URL API Python
        $batchId = (string) Str::uuid();
        $apiUrl  = config('services.yppr058_save.url');

        // 5. Call API Flask
        try {
            $response = Http::timeout(90)->post($apiUrl, [
                'sap_user' => $this->sapUser,
                'sap_pass' => $this->sapPass,
                'items'    => $items,
            ]);
        } catch (\Throwable $e) {
            // Gagal konek ke servis Python
            $this->addError('sapUser', 'Gagal terhubung ke service SAVE SAP: ' . $e->getMessage());
            return;
        }

        $status = $response->status();
        $body   = $response->json() ?? [];

        // 6. Handle khusus unauthorized / logon error
        if ($status === 403) {
            // Sesuai kode Python: user SAP tidak memiliki otorisasi
            $this->sapAuthError = $body['error'] ?? 'SAP user tidak memiliki otorisasi untuk SAVE YPPR058.';
            return;
        }

        if ($status === 401) {
            // Logon SAP gagal (password salah / user tidak valid)
            $this->sapAuthError = $body['error'] ?? 'SAP logon failed. Periksa user & password.';
            return;
        }

        // 7. Ambil results dari API (baik status 200 maupun 500)
        $results = $body['results'] ?? [];

        if (empty($results)) {
            // Kalau nggak ada results sama sekali → anggap error
            $this->addError('sapUser', $body['error'] ?? 'Service SAVE SAP tidak mengembalikan hasil.');
            return;
        }

        // 8. Simpan setiap hasil ke DB (tabel yppr058_sap_logs)
        foreach ($results as $r) {
            Yppr058SapLog::create([
                'batch_id'        => $batchId,
                'sap_user'        => $this->sapUser,

                'pernr'           => $r['pernr'] ?? null,
                'cname'           => $r['cname'] ?? null,
                'arbpl'           => $r['arbpl'] ?? null,
                'start_date'      => $r['start_date'] ?? null,
                'end_date'        => $r['end_date'] ?? null,

                'mint2'           => $r['mint2'] ?? null,
                'mintu'           => $r['mintu'] ?? null,
                'mintu2'          => $r['mintu2'] ?? null,
                'mintu3'          => $r['mintu3'] ?? null,

                'ok'              => (bool) ($r['ok'] ?? false),
                'return_type'     => $r['return_type'] ?? null,
                'return_id'       => $r['return_id'] ?? null,
                'return_number'   => $r['return_number'] ?? null,
                'return_message'  => $r['return_message'] ?? ($r['error'] ?? null),
                'message_v1'      => $r['message_v1'] ?? null,
                'message_v2'      => $r['message_v2'] ?? null,
                'message_v3'      => $r['message_v3'] ?? null,
                'message_v4'      => $r['message_v4'] ?? null,

                'error_raw'       => $r['error'] ?? null,
            ]);
        }

        // 9. Oper ke view untuk toast "Proses Simpan Selesai"
        $this->saveResults      = $results;
        $this->sapPass          = '';
        $this->showSaveSapModal = false;
    }

    // -------------------------------------------------------------------------
    // EXPORT SUMMARY (PDF / EXCEL) - pakai session
    // -------------------------------------------------------------------------
    public function export(string $format)
    {
        $pernrs = array_values(array_unique(
            array_map('strval', $this->selectedPernrs ?? [])
        ));

        if (empty($pernrs)) {
            return;
        }

        session()->put('report_export.pernrs', $pernrs);
        session()->put('report_export.month_filter', $this->monthFilter); // <<< TAMBAH INI

        if ($format === 'pdf') {
            return redirect()->route('report-data.export-pdf', ['werks' => $this->werks]);
        }

        if ($format === 'excel') {
            return redirect()->route('report-data.export-excel', ['werks' => $this->werks]);
        }
    }

    // -------------------------------------------------------------------------
    // Toggle "Pilih semua" untuk baris yang sedang ditampilkan
    // -------------------------------------------------------------------------
    public function toggleSelectAll()
    {
        $current = array_map('strval', $this->currentPagePernrs ?? []);
        if (empty($current)) {
            return;
        }

        $selected = array_map('strval', $this->selectedPernrs ?? []);

        $allSelected = count(array_intersect($current, $selected)) === count($current);

        if ($allSelected) {
            // kalau semua baris di halaman ini sudah terpilih → unselect baris-baris ini saja
            $this->selectedPernrs = array_values(array_diff($selected, $current));
        } else {
            // kalau belum semua → tambahkan semua baris di halaman ini
            $this->selectedPernrs = array_values(array_unique(array_merge($selected, $current)));
        }
    }

    // -------------------------------------------------------------------------
    // RENDER
    // -------------------------------------------------------------------------
    public function render()
    {
        $headers = [
            'No',
            'Personal No.',
            'Rentang Tanggal',
            'Nama',
            'WC Personal',
            'DESC WC',
            'Role',
            'Devisi',
            'Menit Hadir',
            'Menit Conf',
            'Menit Inspect',
            'Var Upah',
            'Persentase Var',
        ];

        [$start, $end] = $this->getDateRangeForFilter();

        $baseQuery = ReportData::query()
            ->whereRaw('UPPER(TRIM(werks)) = ?', [$this->werks])
            ->whereBetween('begda', [$start->format('Ymd'), $end->format('Ymd')]);
        // =====================
        // FILTER SCOPE USER (DEVISI / ARBPL) - TANPA DB
        // =====================
        $scopeAll   = (bool) request()->attributes->get('data_scope_all', false);
        $scopeDev   = (array) request()->attributes->get('data_scope_devisi', []);
        $scopeArbpl = (array) request()->attributes->get('data_scope_arbpl', []);

        if (!$scopeAll) {
            if (empty($scopeDev) && empty($scopeArbpl)) {
                // user tidak punya akses apa-apa
                $baseQuery->whereRaw('1=0');
            } else {
                $baseQuery->where(function ($q) use ($scopeDev, $scopeArbpl) {
                    if (!empty($scopeDev)) {
                        $q->orWhereIn('devisi', $scopeDev);
                    }
                    if (!empty($scopeArbpl)) {
                        // cocokkan WC personal dan WC konfirmasi
                        $q->orWhereIn('arbpl', $scopeArbpl)
                        ->orWhereIn('arbpl2', $scopeArbpl);
                    }
                });
            }
        }


        // Filter role INDUK
        if ($this->onlyInduk) {
            $baseQuery->whereRaw('UPPER(TRIM(role)) = ?', ['INDUK']);
        }

        // === Parsing input search ===
        $raw = trim((string) $this->q);

        // frasa di dalam tanda kutip -> $namePhrases
        preg_match_all('/"([^"]+)"/u', $raw, $m);

        $namePhrases = collect($m[1] ?? [])
            ->map(fn($p) => mb_strtolower(trim($p)))
            ->filter()
            ->values();

        // buang yang di-kutip dari string mentah
        $rest = preg_replace('/"([^"]+)"/u', ' ', $raw);

        // token sisa (dipisah spasi/koma)
        $tokens = collect(preg_split('/[\s,]+/u', $rest))
            ->filter()
            ->map(fn($t) => trim($t))
            ->values();

        // token numerik (NIK)
        $pernrTokens = $tokens->filter(fn($t) => preg_match('/^\d{6,}$/', $t));
        // token lain (kode WC, nama, desc, dll)
        $arbplTokens = $tokens->diff($pernrTokens)->values();

        // fallback: kalau belum ada frasa di-kutip,
        // dan input hanya huruf & spasi → anggap sebagai frasa nama/desc
        if ($namePhrases->isEmpty() && $raw !== '' && preg_match('/^[\p{L}\s]+$/u', $raw)) {
            $namePhrases = collect([mb_strtolower($raw)]);
        }

        if ($pernrTokens->isNotEmpty() || $arbplTokens->isNotEmpty() || $namePhrases->isNotEmpty()) {
            $baseQuery->where(function ($q) use ($pernrTokens, $arbplTokens, $namePhrases) {

                // 1) Token numerik (NIK) -> OR
                foreach ($pernrTokens as $t) {
                    $t = trim((string) $t);
                    if ($t === '') continue;

                    $q->orWhere('pernr', 'LIKE', "%{$t}%");
                }

                // 2) Token non-numerik (WC/desc/nama/dev) -> OR per token (dan OR antar kolom)
                foreach ($arbplTokens as $t) {
                    $t = trim((string) $t);
                    if ($t === '') continue;

                    $lower = mb_strtolower($t);

                    $q->orWhere(function ($qq) use ($t, $lower) {
                        $qq->where('arbpl', 'LIKE', "%{$t}%")
                        ->orWhere('desc',  'LIKE', "%{$t}%")
                        ->orWhereRaw('LOWER(cname)  LIKE ?', ["%{$lower}%"])
                        ->orWhereRaw('LOWER(devisi) LIKE ?', ["%{$lower}%"]);
                    });
                }

                // 3) Frasa dalam tanda kutip -> OR (cname/desc/devisi)
                foreach ($namePhrases as $p) {
                    $p = trim((string) $p);
                    if ($p === '') continue;

                    $q->orWhere(function ($qq) use ($p) {
                        $qq->whereRaw('LOWER(cname) LIKE ?', ["%{$p}%"])
                        ->orWhereRaw('LOWER(`desc`) LIKE ?', ["%{$p}%"])
                        ->orWhereRaw('LOWER(devisi) LIKE ?', ["%{$p}%"]);
                    });
                }
            });
        }

        // Aggregate per pernr (summary)
        $sumSelects = array_map(fn($col) => "SUM($col) as $col", $this->aggregateColumns);

        // non-aggregate (MAX) termasuk DESC WC, role, dan devisi
        $nonAggSelects = array_map(
            fn($col) => "MAX(`$col`) as `$col`",
            ['cname', 'arbpl', 'desc', 'arbpl2', 'werks', 'role', 'devisi']
        );
        $nonAggSelects[] = 'MIN(shift) as shift';

        $dateRangeSel = ['MIN(begda) as min_begda', 'MAX(begda) as max_begda'];

        // Persentase Var = (TOTAL Var Upah / TOTAL Upah Inspect) * 100
        $persenVarExpr = "
            CASE
                WHEN SUM(gji2) = 0 THEN 0
                ELSE (SUM(varnt) / SUM(gji2)) * 100
            END as varnt1
        ";

        $selects = array_merge(
            ['pernr'],
            $dateRangeSel,
            $nonAggSelects,
            $sumSelects,
            [$persenVarExpr]
        );

        // === De-duplicate per date (pernr + begda) ===
        // We pick one record per date before aggregating.
        // We use MAX() for other columns to be compatible with only_full_group_by.
        $subSelects = array_merge(
            ['pernr', 'begda'],
            array_map(fn($col) => "MAX(`$col`) as `$col`" , array_merge($this->aggregateColumns, ['cname', 'arbpl', 'desc', 'arbpl2', 'werks', 'role', 'devisi', 'shift']))
        );

        $subquery = DB::table(DB::raw("({$baseQuery->toSql()}) as filtered"))
            ->mergeBindings($baseQuery->getQuery())
            ->selectRaw(implode(', ', $subSelects))
            ->groupBy('pernr', 'begda');

        // $reportData UNTUK VIEW (Collection of objects)
        // We query from the deduplicated subquery
        $reportData = DB::table(DB::raw("({$subquery->toSql()}) as deduplicated"))
            ->mergeBindings($subquery)
            ->selectRaw(implode(', ', $selects))
            ->groupBy('pernr')
            ->orderByRaw("COALESCE(MAX(`arbpl`), 'ZZZZ') ASC")  // urut WC Personal dulu
            ->orderByRaw("CAST(`pernr` AS UNSIGNED) ASC")       // lalu NIK
            ->get();


        // simpan daftar pernr yang muncul di halaman ini (untuk toggleSelectAll & JS)
        $this->currentPagePernrs = $reportData
            ->pluck('pernr')
            ->map(fn($p) => (string) $p)
            ->values()
            ->all();

        // simpan VERSI ARRAY ke properti Livewire untuk saveToSap()
        $this->reportDataForSave = $reportData
            ->map(fn($row) => (array) $row)
            ->all();

        return view('livewire.report-generator', [
            'reportData' => $reportData,
            'headers'    => $headers,
            'rangeStart' => $start->format('Ymd'), // contoh: 20251201
            'rangeEnd'   => $end->format('Ymd'),   // contoh: 20251217
        ]);
    }
}
