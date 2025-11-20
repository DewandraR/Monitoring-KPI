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
            'Devisi',          // <-- TAMBAHAN
            'Plant',
        ];

        $rows = WcPersonData::query()
            ->search($this->q)
            // URUTAN: 1) PLANT, 2) WC, 3) NIK
            ->orderByRaw('CAST(werks AS UNSIGNED), werks')
            ->orderBy('arbpl')
            ->orderBy('pernr')
            ->get();

        return view('livewire.wc-person-list', [
            'headers'        => $headers,
            'rows'           => $rows,
            'selectedPernrs' => $this->selectedPernrs,
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
        $query = WcPersonData::query()
            ->search($this->q)
            // HARUS SAMA DENGAN render():
            ->orderByRaw('CAST(werks AS UNSIGNED), werks')
            ->orderBy('arbpl')
            ->orderBy('pernr');

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
