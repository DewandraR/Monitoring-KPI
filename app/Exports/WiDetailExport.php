<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WiDetailExport implements
    FromCollection,
    WithHeadings,
    ShouldAutoSize,
    WithStyles,
    WithColumnFormatting,
    WithEvents
{
    protected Collection $rows;
    protected string $plant;

    public function __construct(Collection $rows, string $plant)
    {
        $this->rows  = $rows;
        $this->plant = $plant;
    }

    public function headings(): array
    {
        return [
            'No',       // A
            'NIK',      // B
            'Tanggal',  // C
            'Nama',     // D
            'WC',  // E
            'Time WI',  // F
            'Time QM',  // G
            '% KPI',    // H
        ];
    }

    public function collection(): Collection
    {
        $i = 0;

        return $this->rows->map(function ($row) use (&$i) {
            $i++;

            $tgl = !empty($row->tanggal) ? Carbon::parse($row->tanggal)->isoFormat('YY-MM-DD') : '';

            $timeWi = isset($row->time_wi) ? (is_null($row->time_wi) ? null : (float)$row->time_wi) : null;
            $timeQm = isset($row->time_qm) ? (is_null($row->time_qm) ? null : (float)$row->time_qm) : null;

            $kpi = null;
            if (!is_null($timeWi)) {
                $kpi = ($timeWi == 0.0) ? 0.0 : (((float)($timeQm ?? 0) / $timeWi) * 100);
            }

            return [
                $i,                         // A
                (string)($row->nik ?? ''),  // B
                $tgl,                       // C
                (string)($row->nama ?? ''), // D
                (string)($row->wc ?? ''),   // E
                $timeWi,                    // F (boleh null)
                $timeQm,                    // G (boleh ada walau WI null)
                $kpi,                       // H (null kalau WI null)
            ];
        });
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF065F46'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'F' => '#,##0.00', // Time WI
            'G' => '#,##0.00', // Time QM
            'H' => '0.00',     // KPI
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $rowCount = $this->rows->count() + 1;
                $lastCol = 'H';
                $tableRange = "A1:{$lastCol}{$rowCount}";

                $sheet->setAutoFilter("A1:{$lastCol}1");
                $sheet->freezePane('A2');

                // Align
                $sheet->getStyle("A2:C{$rowCount}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // No, NIK, Tanggal
                $sheet->getStyle("E2:E{$rowCount}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // WC
                $sheet->getStyle("D2:D{$rowCount}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);   // Nama
                $sheet->getStyle("F2:H{$rowCount}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);  // angka

                // Border
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFD1D5DB'],
                        ],
                    ],
                ]);

                // Zebra
                for ($i = 2; $i <= $rowCount; $i++) {
                    if ($i % 2 === 0) {
                        $sheet->getStyle("A{$i}:{$lastCol}{$i}")->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FFECFDF5'],
                            ],
                        ]);
                    }
                }
            },
        ];
    }
}
