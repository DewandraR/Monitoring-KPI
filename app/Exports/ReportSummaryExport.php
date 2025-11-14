<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReportSummaryExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    /**
     * @var \Illuminate\Support\Collection
     */
    protected Collection $rows;

    /**
     * @var string
     */
    protected string $werks;

    /**
     * @param  \Illuminate\Support\Collection  $rows
     * @param  string  $werks
     */
    public function __construct(Collection $rows, string $werks)
    {
        $this->rows  = $rows;
        $this->werks = $werks;
    }

    /**
     * Header kolom Excel.
     */
    public function headings(): array
    {
        return [
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
            'Shift',
        ];
    }

    /**
     * Data yang akan diexport ke Excel.
     */
    public function collection(): Collection
    {
        return $this->rows->map(function ($row) {
            $rentangTanggal =
                Carbon::createFromFormat('Ymd', $row->min_begda)->isoFormat('YY-MM-DD') .
                ' - ' .
                Carbon::createFromFormat('Ymd', $row->max_begda)->isoFormat('YY-MM-DD');

            return [
                // Personal No.
                $row->pernr,
                // Rentang Tanggal
                $rentangTanggal,
                // Menit Hadir
                (float) number_format($row->total_jam, 1, '.', ''),
                // Menit Kerja
                (int) $row->mint2,
                // Total Menit Inspect
                (int) $row->mintu,
                // Total Detik Inspect
                (int) $row->mintu2,
                // Total Detik Confirmation
                (int) $row->mintu3,
                // Nama
                $row->cname,
                // Upah Hadir
                (float) number_format($row->gji, 2, '.', ''),
                // Upah Insp
                (float) number_format($row->gji2, 2, '.', ''),
                // Variant Upah
                (float) number_format($row->varnt, 2, '.', ''),
                // Prosentase Upah
                (float) number_format($row->varnt1, 2, '.', ''),
                // WC Personal
                $row->arbpl,
                // WC Confirmasi
                $row->arbpl2,
                // Plant
                $row->werks,
                // Shift
                is_null($row->shift) ? null : (int) $row->shift,
            ];
        });
    }
}
