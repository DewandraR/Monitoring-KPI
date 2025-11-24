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

class ReportSummaryExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithColumnFormatting, WithEvents
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
            'No',               // A
            'Personal No.',     // B
            'Rentang Tanggal',  // C
            'Nama',             // D
            'WC Personal',      // E
            'DESC WC',          // F
            'Role',             // G
            'Devisi',           // H
            'Menit Hadir',      // I
            'Menit Conf',       // J
            'Menit Inspect',    // K
            'Detik Inspect',    // L
            'Detik Konfirmasi', // M
            'Upah Hadir',       // N (gji)
            'Upah Inspect',     // O (gji2)
            'Var Upah',         // P
            'Persentase Var',   // Q
        ];
    }

    public function collection(): Collection
    {
        $i = 0;

        return $this->rows->map(function ($row) use (&$i) {
            $i++;

            $rentangTanggal =
                Carbon::createFromFormat('Ymd', $row->min_begda)->isoFormat('YY-MM-DD') .
                ' - ' .
                Carbon::createFromFormat('Ymd', $row->max_begda)->isoFormat('YY-MM-DD');

            // Persentase Var = TOTAL Var Upah / TOTAL Upah Inspect * 100
            $gji2      = (float) $row->gji2;   // Upah Inspect total
            $varnt     = (float) $row->varnt;  // Var Upah total
            $persenVar = $gji2 != 0.0 ? ($varnt / $gji2) * 100 : 0.0;

            return [
                $i,                             // A: No
                (string) $row->pernr,           // B: Personal No.
                $rentangTanggal,                // C: Rentang Tanggal
                (string) $row->cname,           // D: Nama
                (string) $row->arbpl,           // E: WC Personal
                (string) $row->desc,            // F: DESC WC
                (string) ($row->role ?? ''),    // G: Role
                (string) ($row->devisi ?? ''),  // H: Devisi

                (float) $row->total_jam,        // I: Menit Hadir
                (int) $row->mint2,              // J: Menit Conf
                (int) $row->mintu,              // K: Menit Inspect
                (int) $row->mintu2,             // L: Detik Inspect
                (int) $row->mintu3,             // M: Detik Konfirmasi

                (float) $row->gji,              // N: Upah Hadir
                $gji2,                          // O: Upah Inspect
                $varnt,                         // P: Var Upah
                $persenVar,                     // Q: Persentase Var (VarUpah/UpahInspect*100)
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
                    'color' => ['argb' => 'FFFFFFFF'], // Putih
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF065F46'], // Emerald Green 800
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
            'I' => '#,##0.0',   // Menit Hadir
            'J' => '#,##0',     // Menit Conf
            'K' => '#,##0',     // Menit Inspect
            'L' => '#,##0',     // Detik Inspect
            'M' => '#,##0',     // Detik Konfirmasi
            'N' => '#,##0.00',  // Upah Hadir
            'O' => '#,##0.00',  // Upah Inspect
            'P' => '#,##0.00',  // Var Upah
            'Q' => '0.00',      // Persentase Var (angka saja, tanpa simbol %)
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet     = $event->sheet->getDelegate();
                $rowCount  = $this->rows->count() + 1; // +1 header
                $lastColumn = 'Q'; // kolom terakhir sekarang Q
                $tableRange = 'A1:' . $lastColumn . $rowCount;

                // AutoFilter
                $sheet->setAutoFilter('A1:' . $lastColumn . '1');

                // rata tengah: No, Personal No, WC, Role
                $sheet->getStyle('A2:A' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('B2:B' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('E2:E' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('G2:G' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // kiri: rentang tanggal, nama, desc, devisi
                $sheet->getStyle('C2:C' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('D2:D' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('F2:F' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('H2:H' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // kanan: semua angka (I..Q)
                $sheet->getStyle('I2:Q' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Border semua sel
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['argb' => 'FFD1D5DB'],
                        ],
                    ],
                ]);

                // zebra striping
                for ($i = 2; $i <= $rowCount; $i++) {
                    if ($i % 2 == 0) {
                        $sheet->getStyle('A' . $i . ':' . $lastColumn . $i)->applyFromArray([
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
