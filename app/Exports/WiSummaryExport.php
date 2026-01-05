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
            'WC',         // E
            'Time WI (SUM)',   // F
            'Time QM (SUM)',   // G
            '% KPI',           // H
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

            return [
                $i,                       // A
                (string)($row->nik ?? ''),// B
                $rentang,                 // C
                (string)($row->nama ?? ''), // D
                (string)($row->wc ?? ''), // E
                $wi,                      // F
                $qm,                      // G
                $kpi,                     // H (angka, bukan "xx%")
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
            'H' => '0.00',     // KPI (angka)
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $rowCount = $this->rows->count() + 1; // + header
                $lastCol = 'H';
                $tableRange = "A1:{$lastCol}{$rowCount}";

                $sheet->setAutoFilter("A1:{$lastCol}1");
                $sheet->freezePane('A2');

                // Align
                $sheet->getStyle("A2:A{$rowCount}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B2:B{$rowCount}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E2:E{$rowCount}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle("C2:D{$rowCount}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $sheet->getStyle("F2:H{$rowCount}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

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
