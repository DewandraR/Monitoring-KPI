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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportDetailExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithColumnFormatting, WithEvents
{
    protected Collection $rows;
    protected string $werks;

    public function __construct(Collection $rows, string $werks)
    {
        $this->rows  = $rows;
        $this->werks = $werks;
    }

    public function headings(): array
    {
        return [
            'Personal No.',
            'Tanggal',
            'Nama',
            'WC Personal',
            'DESC WC',
            'Menit Hadir',
            'Menit Conf',
            'Menit Inspect',
            'Var Upah',
            'Persentase Upah',
            'WC Confirmasi',
            'Plant',
            'Shift',
        ];
    }

    public function collection(): Collection
    {
        return $this->rows->map(function ($row) {
            return [
                $row->pernr,                                  // A
                Carbon::createFromFormat('Ymd', $row->begda)
                    ->isoFormat('YY-MM-DD'),                  // B
                $row->cname,                                  // C
                $row->arbpl,                                  // D
                $row->desc,                                   // E
                (float) $row->total_jam,                      // F
                (int) $row->mintu3,                           // G
                (int) $row->mintu,                            // H
                (float) $row->varnt,                          // I
                (float) $row->varnt1,                         // J
                $row->arbpl2,                                 // K
                $row->werks,                                  // L
                is_null($row->shift) ? null : (int) $row->shift, // M
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
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'F' => '#,##0.0',  // Menit Hadir
            'G' => '#,##0',    // Menit Conf
            'H' => '#,##0',    // Menit Inspect
            'I' => '#,##0.00', // Var Upah
            'J' => '0.00%',    // Persentase
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet     = $event->sheet->getDelegate();
                $rowCount  = $this->rows->count() + 1;
                $lastCol   = 'M';
                $tableRange = "A1:{$lastCol}{$rowCount}";

                // AutoFilter
                $sheet->setAutoFilter("A1:{$lastCol}1");
                // Freeze header
                $sheet->freezePane('A2');

                // Border
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['argb' => 'FFD1D5DB'],
                        ],
                    ],
                ]);

                // Alignments
                $sheet->getStyle("A2:A{$rowCount}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle("D2:D{$rowCount}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle("K2:M{$rowCount}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle("C2:C{$rowCount}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $sheet->getStyle("E2:E{$rowCount}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $sheet->getStyle("F2:J{$rowCount}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Zebra striping
                for ($i = 2; $i <= $rowCount; $i++) {
                    if ($i % 2 === 0) {
                        $sheet->getStyle("A{$i}:{$lastCol}{$i}")
                            ->applyFromArray([
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
