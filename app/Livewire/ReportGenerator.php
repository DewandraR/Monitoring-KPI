<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\ReportData;
use Carbon\Carbon;

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

        // Rentang tetap: 1 s.d kemarin pada bulan & tahun SAAT INI
        $start = Carbon::now()->startOfMonth();
        $end   = Carbon::now()->subDay();

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
                    'pernr'  => $clickedPernr,
                    'begda'  => $key,
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
                    'desc'      => null,          // <--- TAMBAHAN
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
            'Menit Hadir',
            'Menit Conf',
            'Menit Inspect',
            'Var Upah',
            'Persentase Var',
        ];

        $baseQuery = ReportData::query()
            ->whereRaw('UPPER(TRIM(werks)) = ?', [$this->werks]);

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
                            ->orWhereRaw('LOWER(cname) LIKE ?', ["%{$lower}%"]);
                    }
                });
            }

            // --- Frasa di dalam tanda kutip (lebih spesifik) ---
            if ($namePhrases->isNotEmpty()) {
                $q->where(function ($qq) use ($namePhrases) {
                    foreach ($namePhrases as $p) {
                        $qq->orWhereRaw('LOWER(cname) LIKE ?', ["%{$p}%"])
                            ->orWhereRaw('LOWER(`desc`) LIKE ?',  ["%{$p}%"]);
                    }
                });
            }
        });

        // === Aggregate per pernr (summary) ===
        $sumSelects = array_map(fn($col) => "SUM($col) as $col", $this->aggregateColumns);

        // non-aggregate (MAX) termasuk DESC WC
        $nonAggSelects = array_map(
            fn($col) => "MAX(`$col`) as `$col`",
            ['cname', 'arbpl', 'desc', 'arbpl2', 'werks']
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
