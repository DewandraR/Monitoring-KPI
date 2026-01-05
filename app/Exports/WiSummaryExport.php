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

class WiSummaryExport implements
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
            'No',              // A
            'NIK',             // B
            'Rentang Tanggal', // C
            'Nama',            // D
            'WC',              // E
            'Devisi',          // F ✅
            'Time WI',         // G
            'Time QM',         // H
            '% KPI',           // I
        ];
    }

    public function collection(): Collection
    {
        $i = 0;

        return $this->rows->map(function ($row) use (&$i) {
            $i++;

            $min = !empty($row->min_tanggal) ? Carbon::parse($row->min_tanggal)->isoFormat('YY-MM-DD') : '-';
            $max = !empty($row->max_tanggal) ? Carbon::parse($row->max_tanggal)->isoFormat('YY-MM-DD') : '-';
            $rentang = "{$min} - {$max}";

            $wi  = (float)($row->time_wi_sum ?? 0);
            $qm  = (float)($row->time_qm_sum ?? 0);
            $kpi = isset($row->kpi_pct) ? (float)$row->kpi_pct : ($wi == 0.0 ? 0.0 : (($qm / $wi) * 100));

            $devisi = (string)($row->devisi ?? '');
            if (trim($devisi) === '') $devisi = '-';

            return [
                $i,                          // A
                (string)($row->nik ?? ''),   // B
                $rentang,                    // C
                (string)($row->nama ?? ''),  // D
                (string)($row->wc ?? ''),    // E
                $devisi,                     // F ✅
                $wi,                         // G
                $qm,                         // H
                $kpi,                        // I
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
            'G' => '#,##0.00', // Time WI
            'H' => '#,##0.00', // Time QM
            'I' => '0.00',     // KPI (angka)
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $rowCount = $this->rows->count() + 1; // + header
                $lastCol = 'I';
                $tableRange = "A1:{$lastCol}{$rowCount}";

                $sheet->setAutoFilter("A1:{$lastCol}1");
                $sheet->freezePane('A2');

                // Align
                $sheet->getStyle("A2:A{$rowCount}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // No
                $sheet->getStyle("B2:B{$rowCount}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // NIK
                $sheet->getStyle("E2:E{$rowCount}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // WC

                $sheet->getStyle("C2:C{$rowCount}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Rentang
                $sheet->getStyle("D2:D{$rowCount}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);   // Nama
                $sheet->getStyle("F2:F{$rowCount}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);   // Devisi ✅

                $sheet->getStyle("G2:I{$rowCount}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);  // angka

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
