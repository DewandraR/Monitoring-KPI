<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\WcPersonData;

#[Layout('layouts.app')]
class WcPersonList extends Component
{
    /** kata kunci pencarian */
    public string $q = '';

    /** daftar NIK yang dipilih (dipakai untuk export) */
    public array $selectedPernrs = [];

    public function render()
    {
        $headers = [
            'No',
            'NIK',
            'Tgl Mulai',
            'Nama',
            'Role',                // <--- baru
            'Work Center',
            'Deskripsi Work Center',
            'Plant',
        ];

        $rows = WcPersonData::query()
            ->search($this->q)
            ->orderByRaw('CAST(werks AS UNSIGNED), werks')
            ->orderBy('pernr')
            ->get();

        return view('livewire.wc-person-list', [
            'headers'        => $headers,
            'rows'           => $rows,
            // supaya Blade punya akses ke array ini
            'selectedPernrs' => $this->selectedPernrs,
        ]);
    }

    /**
     * Klik checkbox per baris.
     * Kalau sudah ada di selectedPernrs -> lepas.
     * Kalau belum -> tambahkan.
     */
    public function togglePernr(string $pernr): void
    {
        $pernr = (string) $pernr;

        if (in_array($pernr, $this->selectedPernrs, true)) {
            // lepas dari selection
            $this->selectedPernrs = array_values(
                array_diff($this->selectedPernrs, [$pernr])
            );
        } else {
            // tambahkan ke selection
            $this->selectedPernrs[] = $pernr;
            $this->selectedPernrs = array_values(
                array_unique($this->selectedPernrs)
            );
        }
    }

    /**
     * Klik checkbox header "Pilih".
     * Bekerja berdasarkan hasil filter saat ini ($this->q).
     */
    public function toggleSelectAll(): void
    {
        $query = WcPersonData::query()
            ->search($this->q)
            ->orderByRaw('CAST(werks AS UNSIGNED), werks')
            ->orderBy('pernr');

        // semua NIK di hasil filter saat ini
        $currentPernrs = $query->pluck('pernr')
            ->map(fn($p) => (string) $p)
            ->all();

        if (empty($currentPernrs)) {
            return;
        }

        $selected = $this->selectedPernrs;

        // cek apakah SEMUA NIK di hasil filter sudah ada di selection
        $diff = array_diff($currentPernrs, $selected);

        if (empty($diff)) {
            // semua sudah terpilih -> unselect semua NIK di hasil filter ini
            $this->selectedPernrs = array_values(
                array_diff($selected, $currentPernrs)
            );
        } else {
            // masih ada yang belum -> tambahkan semua NIK hasil filter ke selection
            $this->selectedPernrs = array_values(
                array_unique(array_merge($selected, $currentPernrs))
            );
        }
    }

    /**
     * Dipanggil dari tombol Export (PDF / Excel).
     * Mengirim URL ke JS lewat event browser.
     */
    public function export(string $type): void
    {
        // rapikan & unik
        $pernrs = array_values(
            array_unique(array_map('strval', $this->selectedPernrs))
        );

        if (empty($pernrs)) {
            $this->dispatch('wc-person-alert', message: 'Pilih minimal satu NIK terlebih dahulu.');
            return;
        }

        if ($type === 'pdf') {
            $url = route('wc-person.export-pdf', [
                'pernrs' => $pernrs,
                'q'      => $this->q,
            ]);
        } elseif ($type === 'excel') {
            $url = route('wc-person.export-excel', [
                'pernrs' => $pernrs,
                'q'      => $this->q,
            ]);
        } else {
            return; // tipe tidak dikenal
        }

        // Livewire v3: kirim event ke browser
        $this->dispatch('wc-person-export', url: $url);
    }
}
