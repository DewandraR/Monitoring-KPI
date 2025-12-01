<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\ReportData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Yppr058SapLog;
use Illuminate\Support\Str;


#[Layout('layouts.app')]
class ReportGenerator extends Component
{
    public $q = '';
    public $werks;

    public $showDetailModal = false;
    public $selectedPernr = null;
    public $detailData = [];

    // <<< BARU: simpan pilihan user & list pernr di halaman saat ini >>>
    public array $selectedPernrs = [];
    public array $currentPagePernrs = [];

    private $aggregateColumns = [
        'total_jam',
        'mint2',
        'mintu',
        'mintu2',
        'mintu3',
        'gji',
        'gji2',
        'varnt',   // varnt1 dihilangkan, nanti dihitung manual
    ];

    public array $selectedDetailKeys = [];   // pernr|begda yang dicentang di modal

    public bool $onlyInduk = false;

    public bool $showSaveSapModal = false;
    public string $sapUser = '';
    public string $sapPass = '';
    public array $saveResults = [];
    public ?string $sapAuthError = null;

    public function exportDetail(string $format)
    {
        // Kumpulkan semua NIK dari:
        // 1) checkbox detail (selectedDetailKeys: "pernr|begda")
        // 2) checkbox summary (selectedPernrs)
        // Per NIK -> selalu export FULL range tanggal (1..H-1), bukan hanya tanggal yang dicentang.

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

        if ($format === 'pdf') {
            return redirect()->route('report-data.export-detail-pdf', ['werks' => $this->werks]);
        }

        if ($format === 'excel') {
            return redirect()->route('report-data.export-detail-excel', ['werks' => $this->werks]);
        }
    }


    public function mount($werks)
    {
        $this->werks = strtoupper(trim($werks));
    }

    /** ==== DETAIL: isi tanggal kosong dengan placeholder ('-') sampai H-1 ==== */
    public function showPernrDetail($clickedPernr)
    {
        $clickedPernr = (string) $clickedPernr;
        $this->selectedPernr = $clickedPernr;

        $today = Carbon::today();

        // Kalau hari ini tgl 1 → pakai bulan sebelumnya full
        if ($today->day === 1) {
            $start = $today->copy()->subMonth()->startOfMonth();
            $end   = $today->copy()->subMonth()->endOfMonth();
        } else {
            // Selain itu: 1 s.d kemarin di bulan ini
            $start = $today->copy()->startOfMonth();
            $end   = $today->copy()->subDay();
        }

        // (opsional) guard, tapi sekarang harusnya selalu start <= end
        if ($end->lt($start)) {
            $this->detailData = [];
            $this->showDetailModal = false;
            return;
        }

        $rows = ReportData::query()
            ->whereRaw('UPPER(TRIM(werks)) = ?', [$this->werks])
            ->where('pernr', $clickedPernr)
            ->whereBetween('begda', [$start->format('Ymd'), $end->format('Ymd')])
            ->orderBy('begda', 'asc')
            ->get();

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
        $this->selectedPernr = null;
        $this->detailData = [];
    }

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

        $this->sapUser = '';
        $this->sapPass = '';
        $this->saveResults = [];

        // ⬇️ reset pesan error otorisasi setiap kali modal dibuka
        $this->sapAuthError = null;
        // ⬆️

        $this->showSaveSapModal = true;
    }
    public function closeSaveSapModal()
    {
        $this->showSaveSapModal = false;
        $this->sapAuthError = null; // ⬅️ sekalian clear
    }

    public function saveToSap()
    {
        // Reset error & state SAP
        $this->resetErrorBag(['sapUser', 'sapPass']);
        $this->sapAuthError = null;
        $this->saveResults = [];

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

        // 3. Ambil data summary ($reportData) dan mapping jadi items[] untuk API Python
        $reportCollection = collect($this->reportData ?? []);

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

            if (! $row) {
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
                'arbpl'      => (string) $get($row, 'arbpl'),     // WC personal (kalau mau pakai arbpl2, silakan ganti)
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

        // Bisa kamu pindah ke config('services.yppr058_save.url') kalau mau
        $apiUrl = 'http://127.0.0.1:5011/api/yppr058/save';

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
        $this->saveResults = $results;

        // Kalau mau: tutup modal & kosongkan password
        $this->sapPass = '';
        $this->showSaveSapModal = false;

        // Opsional: kalau semua gagal, kamu bisa tambahkan warning tambahan
        // berdasarkan $body['summary'] atau $body['ok']
        // misal:
        // if (isset($body['ok']) && $body['ok'] === false) {
        //     $this->addError('sapUser', 'Semua item gagal disimpan ke SAP. Lihat detail log di bawah.');
        // }
    }

    /** ==== EXPORT PDF / EXCEL (gunakan session) ==== */
    public function export(string $format)
    {
        // pastikan unik & string
        $pernrs = array_values(array_unique(
            array_map('strval', $this->selectedPernrs ?? [])
        ));

        if (empty($pernrs)) {
            return;
        }

        session()->put('report_export.pernrs', $pernrs);

        if ($format === 'pdf') {
            return redirect()->route('report-data.export-pdf', ['werks' => $this->werks]);
        }

        if ($format === 'excel') {
            return redirect()->route('report-data.export-excel', ['werks' => $this->werks]);
        }
    }

    /** ==== Toggle "Pilih semua" untuk baris yang sedang ditampilkan ==== */
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

        $baseQuery = ReportData::query()
            ->whereRaw('UPPER(TRIM(werks)) = ?', [$this->werks]);

        // >>> FILTER: kalau toggle hanya Role INDUK aktif <<<
        if ($this->onlyInduk) {
            $baseQuery->whereRaw('UPPER(TRIM(role)) = ?', ['INDUK']);
        }

        // === Parsing input search ===
        $raw = trim((string) $this->q);

        // 1) ambil semua yang di dalam tanda kutip -> $namePhrases
        preg_match_all('/"([^"]+)"/u', $raw, $m);
        $namePhrases = collect($m[1] ?? [])
            ->map(fn($p) => mb_strtolower(trim($p)))
            ->filter()
            ->values();

        // buang yang di-kutip dari string mentah
        $rest = preg_replace('/"([^"]+)"/u', ' ', $raw);

        // 2) token sisa (dipisah spasi/koma)
        $tokens = collect(preg_split('/[\s,]+/u', $rest))
            ->filter()
            ->map(fn($t) => trim($t))
            ->values();

        // token numerik (NIK)
        $pernrTokens = $tokens->filter(fn($t) => preg_match('/^\d{6,}$/', $t));
        // token lain (kode WC, nama, desc, dll)
        $arbplTokens = $tokens->diff($pernrTokens)->values();

        // 3) fallback: kalau belum ada frasa di-kutip,
        //    dan input hanya terdiri dari huruf & spasi (tidak ada angka),
        //    maka anggap seluruh input sebagai frasa nama/desc
        if ($namePhrases->isEmpty() && $raw !== '' && preg_match('/^[\p{L}\s]+$/u', $raw)) {
            $namePhrases = collect([mb_strtolower($raw)]);
        }

        $baseQuery->where(function ($q) use ($pernrTokens, $arbplTokens, $namePhrases) {
            // --- NIK (pernr) ---
            if ($pernrTokens->isNotEmpty()) {
                $q->where(function ($qq) use ($pernrTokens) {
                    foreach ($pernrTokens as $t) {
                        $qq->orWhere('pernr', 'LIKE', "%{$t}%");
                    }
                });
            }

            // --- Token non-angka: cari WC code, DESC WC, dan NAMA (cname) ---
            if ($arbplTokens->isNotEmpty()) {
                $q->where(function ($qq) use ($arbplTokens) {
                    foreach ($arbplTokens as $t) {
                        $lower = mb_strtolower($t);
                        $qq->orWhere('arbpl', 'LIKE', "%{$t}%")
                            ->orWhere('desc',  'LIKE', "%{$t}%")
                            ->orWhereRaw('LOWER(cname) LIKE ?',   ["%{$lower}%"])
                            ->orWhereRaw('LOWER(devisi) LIKE ?',  ["%{$lower}%"]); // <<< Devisi ikut dicari
                    }
                });
            }

            // --- Frasa di dalam tanda kutip (lebih spesifik) ---
            if ($namePhrases->isNotEmpty()) {
                $q->where(function ($qq) use ($namePhrases) {
                    foreach ($namePhrases as $p) {
                        $qq->orWhereRaw('LOWER(cname)   LIKE ?', ["%{$p}%"])
                            ->orWhereRaw('LOWER(`desc`) LIKE ?', ["%{$p}%"])
                            ->orWhereRaw('LOWER(devisi) LIKE ?', ["%{$p}%"]); // <<< frasa spesifik ke devisi juga
                    }
                });
            }
        });

        // === Aggregate per pernr (summary) ===
        $sumSelects = array_map(fn($col) => "SUM($col) as $col", $this->aggregateColumns);

        // non-aggregate (MAX) termasuk DESC WC, role, dan devisi
        $nonAggSelects = array_map(
            fn($col) => "MAX(`$col`) as `$col`",
            ['cname', 'arbpl', 'desc', 'arbpl2', 'werks', 'role', 'devisi']
        );

        $nonAggSelects[] = 'MIN(shift) as shift';

        $dateRangeSel = ['MIN(begda) as min_begda', 'MAX(begda) as max_begda'];

        // RATA-RATA PERSENTASE VAR PER HARI:
        //   AVG( (varnt / gji) * 100 )  -> kalau gji = 0, anggap 0%
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

        $reportData = $baseQuery
            ->selectRaw(implode(', ', $selects))
            ->groupBy('pernr')
            ->get();
        // simpan daftar pernr yang muncul di halaman ini
        $this->currentPagePernrs = $reportData
            ->pluck('pernr')
            ->map(fn($p) => (string) $p)
            ->values()
            ->all();

        return view('livewire.report-generator', [
            'reportData' => $reportData,
            'headers'    => $headers,
        ]);
    }
}
