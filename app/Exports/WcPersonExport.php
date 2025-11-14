<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;

class WcPersonExport implements FromCollection, WithHeadings, WithMapping
{
    /** @var \Illuminate\Support\Collection */
    protected Collection $rows;

    public function __construct($rows)
    {
        $this->rows = $rows instanceof Collection ? $rows : collect($rows);
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'NIK',
            'TGL Mulai',
            'Nama',
            'Work Center',
            'Deskripsi Work Center',
            'Plant',
            'Role',
        ];
    }

    public function map($row): array
    {
        return [
            $row->pernr,
            $this->formatBegda($row->begda),
            $row->stext,
            $row->arbpl,
            $row->desc,
            $row->werks,
            $row->role,
        ];
    }

    protected function formatBegda($begda): string
    {
        if ($begda && preg_match('/^\d{8}$/', $begda)) {
            return Carbon::createFromFormat('Ymd', $begda)->format('Y-m-d');
        }

        return (string) $begda;
    }
}
