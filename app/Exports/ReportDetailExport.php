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
            'Personal No.',      // A
            'Tanggal',           // B
            'Nama',              // C
            'WC Personal',       // D
            'DESC WC',           // E
            'Menit Hadir',       // F
            'Menit Conf',        // G
            'Menit Inspect',     // H
            'Detik Inspect',     // I
            'Detik Konfirmasi',  // J
            'Upah Hadir',        // K (gji)
            'Upah Inspect',      // L (gji2)
            'Var Upah',          // M
            'Persentase Upah',   // N
            'Plant',             // O
        ];
    }

    public function collection(): Collection
    {
        return $this->rows->map(function ($row) {

            // Pastikan null → 0 supaya TIDAK kosong di Excel
            $totalJam  = (float) ($row->total_jam ?? 0);
            $mint2     = (int)   ($row->mint2 ?? 0);
            $mintu     = (int)   ($row->mintu ?? 0);
            $mintu2    = (int)   ($row->mintu2 ?? 0);
            $mintu3    = (int)   ($row->mintu3 ?? 0);

            $gji       = (float) ($row->gji ?? 0);   // Upah Hadir
            $gji2      = (float) ($row->gji2 ?? 0);  // Upah Inspect
            $varnt     = (float) ($row->varnt ?? 0);

            // Persentase = Var Upah / Upah Hadir * 100
            $persentase = 0.0;
            if ($gji != 0.0) {
                $persentase = ($varnt / $gji) * 100;
            }

            return [
                (string) ($row->pernr ?? ''),                    // A: Personal No.
                Carbon::createFromFormat('Ymd', $row->begda)
                    ->isoFormat('YY-MM-DD'),                     // B: Tanggal
                (string) ($row->cname ?? ''),                    // C: Nama
                (string) ($row->arbpl ?? ''),                    // D: WC Personal
                (string) ($row->desc ?? ''),                     // E: DESC WC

                $totalJam,                                       // F: Menit Hadir
                $mint2,                                          // G: Menit Conf
                $mintu,                                          // H: Menit Inspect
                $mintu2,                                         // I: Detik Inspect
                $mintu3,                                         // J: Detik Konfirmasi

                $gji,                                            // K: Upah Hadir
                $gji2,                                           // L: Upah Inspect
                $varnt,                                          // M: Var Upah
                $persentase,                                     // N: Persentase Upah (angka, sudah x100)
                (string) ($row->werks ?? ''),                    // O: Plant
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
            'F' => '#,##0.0',    // Menit Hadir
            'G' => '#,##0',      // Menit Conf
            'H' => '#,##0',      // Menit Inspect
            'I' => '#,##0',      // Detik Inspect
            'J' => '#,##0',      // Detik Konfirmasi
            'K' => '#,##0.00',   // Upah Hadir
            'L' => '#,##0.00',   // Upah Inspect
            'M' => '#,##0.00',   // Var Upah
            // Persentase: 8.18 -> tampil 8.18%
            'N' => '0.00"%"',    // Persentase Upah
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet     = $event->sheet->getDelegate();
                $rowCount  = $this->rows->count() + 1;
                $lastCol   = 'O'; // kolom terakhir sekarang O

                $tableRange = "A1:{$lastCol}{$rowCount}";

                // AutoFilter & freeze header
                $sheet->setAutoFilter("A1:{$lastCol}1");
                $sheet->freezePane('A2');

                // Border tipis semua sel
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['argb' => 'FFD1D5DB'],
                        ],
                    ],
                ]);

                // Alignment kolom
                $sheet->getStyle("A2:A{$rowCount}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Personal No.

                $sheet->getStyle("B2:B{$rowCount}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Tanggal

                $sheet->getStyle("D2:D{$rowCount}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // WC Personal

                $sheet->getStyle("O2:O{$rowCount}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Plant

                $sheet->getStyle("C2:C{$rowCount}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);   // Nama

                $sheet->getStyle("E2:E{$rowCount}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);   // DESC WC

                // Semua angka menit/detik/gaji/persen
                $sheet->getStyle("F2:N{$rowCount}")
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
