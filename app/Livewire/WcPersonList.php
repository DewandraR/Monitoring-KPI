<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\WcPersonData;

#[Layout('layouts.app')]
class WcPersonList extends Component
{
    public string $q = '';

    public function render()
    {
        $headers = [
            'No',
            'otype',
            'objid',
            'pernr',
            'begda',
            'endda',
            'short',
            'stext',
            'arbpl',
            'werks', // arbid dihapus
        ];

        $rows = WcPersonData::query()
            ->search($this->q)
            ->orderByRaw('CAST(werks AS UNSIGNED), werks')
            ->orderBy('pernr')
            ->get();

        return view('livewire.wc-person-list', [
            'headers' => $headers,
            'rows'    => $rows,
        ]);
    }
}
