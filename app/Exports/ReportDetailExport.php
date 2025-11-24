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
            'Role',              // F
            'Devisi',            // G
            'Menit Hadir',       // H
            'Menit Conf',        // I
            'Menit Inspect',     // J
            'Detik Inspect',     // K
            'Detik Konfirmasi',  // L
            'Upah Hadir',        // M (gji)
            'Upah Inspect',      // N (gji2)
            'Var Upah',          // O
            'Plant',             // P
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
            $varnt     = (float) ($row->varnt ?? 0); // Var Upah

            return [
                (string) ($row->pernr ?? ''),                    // A: Personal No.
                Carbon::createFromFormat('Ymd', $row->begda)
                    ->isoFormat('YY-MM-DD'),                     // B: Tanggal
                (string) ($row->cname ?? ''),                    // C: Nama
                (string) ($row->arbpl ?? ''),                    // D: WC Personal
                (string) ($row->desc ?? ''),                     // E: DESC WC
                (string) ($row->role ?? ''),                     // F: Role
                (string) ($row->devisi ?? ''),                   // G: Devisi

                $totalJam,                                       // H: Menit Hadir
                $mint2,                                          // I: Menit Conf
                $mintu,                                          // J: Menit Inspect
                $mintu2,                                         // K: Detik Inspect
                $mintu3,                                         // L: Detik Konfirmasi

                $gji,                                            // M: Upah Hadir
                $gji2,                                           // N: Upah Inspect
                $varnt,                                          // O: Var Upah
                (string) ($row->werks ?? ''),                    // P: Plant
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
            'H' => '#,##0.0',    // Menit Hadir
            'I' => '#,##0',      // Menit Conf
            'J' => '#,##0',      // Menit Inspect
            'K' => '#,##0',      // Detik Inspect
            'L' => '#,##0',      // Detik Konfirmasi
            'M' => '#,##0.00',   // Upah Hadir
            'N' => '#,##0.00',   // Upah Inspect
            'O' => '#,##0.00',   // Var Upah
            // Tidak ada lagi kolom persentase
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet     = $event->sheet->getDelegate();
                $rowCount  = $this->rows->count() + 1;
                $lastCol   = 'P'; // kolom terakhir sekarang P

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

                // Alignment kolom teks/angka

                // Personal No & Tanggal & WC & Role & Plant -> tengah
                $sheet->getStyle("A2:A{$rowCount}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Personal No.
                $sheet->getStyle("B2:B{$rowCount}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Tanggal
                $sheet->getStyle("D2:D{$rowCount}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // WC Personal
                $sheet->getStyle("F2:F{$rowCount}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Role
                $sheet->getStyle("P2:P{$rowCount}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Plant

                // Nama, DESC WC, Devisi -> kiri
                $sheet->getStyle("C2:C{$rowCount}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);   // Nama
                $sheet->getStyle("E2:E{$rowCount}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);   // DESC WC
                $sheet->getStyle("G2:G{$rowCount}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);   // Devisi

                // Semua angka menit/detik/gaji
                $sheet->getStyle("H2:O{$rowCount}")
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
