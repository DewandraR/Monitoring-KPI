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

    private $aggregateColumns = [
        'total_jam',
        'mint2',
        'mintu',
        'mintu2',
        'mintu3',
        'gji',
        'gji2',
        'varnt',
        'varnt1',
    ];

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

        // Jika hari ini tanggal 1, maka tidak ada rentang (biarkan kosong)
        if ($end->lt($start)) {
            $this->detailData = [];
            $this->showDetailModal = false;
            return;
        }

        // Ambil data asli per hari di rentang itu
        $rows = ReportData::query()
            ->whereRaw('UPPER(TRIM(werks)) = ?', [$this->werks])
            ->where('pernr', $clickedPernr)
            ->whereBetween('begda', [$start->format('Ymd'), $end->format('Ymd')])
            ->orderBy('begda', 'asc')
            ->get();

        // Simpan per tanggal, dan ambil nama jika ada
        $byDate = $rows->keyBy('begda');
        $name   = optional($rows->first())->cname;

        // Susun list lengkap 1..H-1; isi '-' jika tidak ada data
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
                    'varnt1'    => null,
                    'arbpl'     => null,
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

    public function render()
    {
        $headers = [
            'No',
            'Personal No.',
            'Rentang Tanggal',
            'Menit Hadir',
            'Menit Kerja',
            'Total Menit Inspect',
            'Total Detik Inspect',
            'Total Detik Confirmation',
            'Nama',
            'Upah Hadir',
            'Upah Insp',
            'Variant Upah',
            'Prosentase Upah',
            'WC Personal',
            'WC Confirmasi',
            'Plant',
            'Shift', // <— tambahkan ini
        ];

        $baseQuery = ReportData::query()
            ->whereRaw('UPPER(TRIM(werks)) = ?', [$this->werks]);

        // === Parsing input: frasa nama (kutip) & token (NIK/WC) ===
        $raw = trim((string) $this->q);

        preg_match_all('/"([^"]+)"/u', $raw, $m);
        $namePhrases = collect($m[1] ?? [])
            ->map(fn($p) => mb_strtolower(trim($p)))
            ->filter()
            ->values();

        $rest = preg_replace('/"([^"]+)"/u', ' ', $raw);
        $tokens = collect(preg_split('/[\s,]+/u', $rest))
            ->filter()
            ->map(fn($t) => trim($t))
            ->values();

        $pernrTokens = $tokens->filter(fn($t) => preg_match('/^\d{6,}$/', $t));
        $arbplTokens = $tokens->diff($pernrTokens)->values();

        if ($namePhrases->isEmpty() && $raw !== '' && preg_match('/^[\p{L}\s]+$/u', $raw)) {
            preg_match_all('/\p{L}+/u', $raw, $words);
            if (count($words[0]) >= 2) $namePhrases = collect([mb_strtolower($raw)]);
        }

        $baseQuery->where(function ($q) use ($pernrTokens, $arbplTokens, $namePhrases) {
            if ($pernrTokens->isNotEmpty()) {
                $q->where(function ($qq) use ($pernrTokens) {
                    foreach ($pernrTokens as $t) $qq->orWhere('pernr', 'LIKE', "%{$t}%");
                });
            }
            if ($arbplTokens->isNotEmpty()) {
                $q->where(function ($qq) use ($arbplTokens) {
                    foreach ($arbplTokens as $t) $qq->orWhere('arbpl', 'LIKE', "%{$t}%");
                });
            }
            if ($namePhrases->isNotEmpty()) {
                $q->where(function ($qq) use ($namePhrases) {
                    foreach ($namePhrases as $p) $qq->orWhereRaw('LOWER(cname) LIKE ?', ["%{$p}%"]);
                });
            }
        });

        $sumSelects     = array_map(fn($col) => "SUM($col) as $col", $this->aggregateColumns);
        $nonAggSelects  = array_map(fn($col) => "MAX($col) as $col", ['cname', 'arbpl', 'arbpl2', 'werks']);
        $nonAggSelects[] = 'MIN(shift) as shift';
        $dateRangeSel   = ['MIN(begda) as min_begda', 'MAX(begda) as max_begda'];
        $selects        = array_merge(['pernr'], $dateRangeSel, $nonAggSelects, $sumSelects);

        $reportData = $baseQuery
            ->selectRaw(implode(', ', $selects))
            ->groupBy('pernr')
            ->get();

        return view('livewire.report-generator', [
            'reportData' => $reportData,
            'headers'    => $headers,
        ]);
    }
}
