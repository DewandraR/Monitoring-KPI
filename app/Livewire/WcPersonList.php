<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\WcPersonData;

#[Layout('layouts.app')]
class WcPersonList extends Component
{
    public string $q = '';
    public array $selectedPernrs = [];

    // 🔹 FILTER PLANT (ALL / 1000 / 1001 / 2000 / 3000)
    public string $plant = 'ALL';
    public bool $onlyDuplicate = false;

    public function render()
    {
        $headers = [
            'No',
            'NIK',
            'Tgl Mulai',
            'Nama',
            'Role',
            'Work Center',
            'Deskripsi Work Center',
            'Devisi',
            'Plant',
        ];

        $rawQ = trim((string) $this->q);

        // Ambil semua token WCxxx dari q (misal: "WC007, WC019" -> ["WC007","WC019"])
        preg_match_all('/\bWC\d+\b/i', $rawQ, $m);
        $wcTokens = array_values(array_unique(array_map('strtoupper', $m[0] ?? [])));

        // Sisa kata selain WC (untuk search umum: nama, nik, devisi, desc, dll)
        $otherQ = trim(preg_replace('/\bWC\d+\b/i', '', $rawQ));
        $otherQ = trim(preg_replace('/[,\s;]+/', ' ', $otherQ));

        $query = WcPersonData::query();

        // ✅ filter arbpl berdasarkan WC tokens
        if (count($wcTokens) === 1) {
            $query->where('arbpl', $wcTokens[0]);
        } elseif (count($wcTokens) > 1) {
            $query->whereIn('arbpl', $wcTokens);
        }

        // ✅ jalankan search() hanya kalau ada kata lain selain WC
        if ($otherQ !== '') {
            $query->search($otherQ);
        }

        // 🔹 Terapkan filter plant (kecuali ALL)
        if ($this->plant !== 'ALL') {
            $query->where('werks', $this->plant);
        }

        // 🔹 JIKA mode "NIK duplikat saja" aktif
        if ($this->onlyDuplicate) {
            // subquery: cari pernr yang muncul > 1 kali (ikut filter plant juga)
            $sub = WcPersonData::query()
                ->select('pernr')
                ->when($this->plant !== 'ALL', fn($qq) => $qq->where('werks', $this->plant))
                ->groupBy('pernr')
                ->havingRaw('COUNT(*) > 1');

            // hanya tampilkan baris yang pernr-nya ada di subquery
            $query->whereIn('pernr', $sub);
        }

        if ($this->onlyDuplicate) {
            // Mode "NIK duplikat saja" → urutkan hanya berdasarkan NIK
            $query->orderBy('pernr');
        } else {
            // Mode normal → urutkan Plant → WC → NIK
            $query->orderByRaw('CAST(werks AS UNSIGNED), werks')
                ->orderBy('arbpl')
                ->orderBy('pernr');
        }

        $rows = $query->get();
        return view('livewire.wc-person-list', [
            'headers'        => $headers,
            'rows'           => $rows,
            'selectedPernrs' => $this->selectedPernrs,
            // ⬇⬇⬇ kirim ke Blade supaya bisa baca status ON/OFF
            'plant'          => $this->plant,
            'onlyDuplicate'  => $this->onlyDuplicate,
        ]);
    }

    public function togglePernr(string $pernr): void
    {
        $pernr = (string) $pernr;

        if (in_array($pernr, $this->selectedPernrs, true)) {
            $this->selectedPernrs = array_values(
                array_diff($this->selectedPernrs, [$pernr])
            );
        } else {
            $this->selectedPernrs[] = $pernr;
            $this->selectedPernrs = array_values(
                array_unique($this->selectedPernrs)
            );
        }
    }

    public function toggleSelectAll(): void
    {
        $rawQ = trim((string) $this->q);
        preg_match_all('/\bWC\d+\b/i', $rawQ, $m);
        $wcTokens = array_values(array_unique(array_map('strtoupper', $m[0] ?? [])));

        $otherQ = trim(preg_replace('/\bWC\d+\b/i', '', $rawQ));
        $otherQ = trim(preg_replace('/[,\s;]+/', ' ', $otherQ));

        $query = WcPersonData::query();

        // filter WC sama seperti render()
        if (count($wcTokens) === 1) {
            $query->where('arbpl', $wcTokens[0]);
        } elseif (count($wcTokens) > 1) {
            $query->whereIn('arbpl', $wcTokens);
        }

        if ($otherQ !== '') {
            $query->search($otherQ);
        }

        $query->orderByRaw('CAST(werks AS UNSIGNED), werks')
            ->orderBy('arbpl')
            ->orderBy('pernr');

        // 🔹 Filter plant juga berpengaruh ke Select All
        if ($this->plant !== 'ALL') {
            $query->where('werks', $this->plant);
        }

        // 🔹 Kalau mode "NIK duplikat saja" aktif, Select All hanya untuk NIK duplikat
        if ($this->onlyDuplicate) {
            $sub = WcPersonData::query()
                ->select('pernr')
                ->when($this->plant !== 'ALL', fn($qq) => $qq->where('werks', $this->plant))
                ->groupBy('pernr')
                ->havingRaw('COUNT(*) > 1');

            $query->whereIn('pernr', $sub);
        }

        $currentPernrs = $query->pluck('pernr')
            ->map(fn($p) => (string) $p)
            ->all();

        if (empty($currentPernrs)) {
            return;
        }

        $selected = $this->selectedPernrs;
        $diff = array_diff($currentPernrs, $selected);

        if (empty($diff)) {
            // semua di hasil filter sudah kepilih -> unselect semua di hasil filter
            $this->selectedPernrs = array_values(
                array_diff($selected, $currentPernrs)
            );
        } else {
            // masih ada yang belum -> tambahkan semuanya
            $this->selectedPernrs = array_values(
                array_unique(array_merge($selected, $currentPernrs))
            );
        }
    }

    public function export(string $type): void
    {
        $pernrs = array_values(
            array_unique(array_map('strval', $this->selectedPernrs))
        );

        if (empty($pernrs)) {
            $this->dispatch('wc-person-alert', message: 'Pilih minimal satu NIK terlebih dahulu.');
            return;
        }

        session()->put('wc_person_export.pernrs', $pernrs);
        session()->put('wc_person_export.q', $this->q);

        if ($type === 'pdf') {
            $url = route('wc-person.export-pdf');
        } elseif ($type === 'excel') {
            $url = route('wc-person.export-excel');
        } else {
            return;
        }

        $this->dispatch('wc-person-export', url: $url);
    }
}
