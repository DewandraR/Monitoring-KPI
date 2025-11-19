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
            'Menit Hadir',      // G
            'Menit Conf',       // H
            'Menit Inspect',    // I
            'Detik Inspect',    // J
            'Detik Konfirmasi', // K
            'Upah Hadir',       // L (gji)
            'Upah Inspect',     // M (gji2)
            'Var Upah',         // N
            'Persentase Var',   // O
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

            return [
                $i,                             // A: No
                $row->pernr,                    // B: Personal No.
                $rentangTanggal,                // C: Rentang Tanggal
                $row->cname,                    // D: Nama
                $row->arbpl,                    // E: WC Personal
                $row->desc,                     // F: DESC WC

                (float) $row->total_jam,        // G: Menit Hadir
                (int) $row->mint2,              // H: Menit Conf
                (int) $row->mintu,              // I: Menit Inspect
                (int) $row->mintu2,             // J: Detik Inspect
                (int) $row->mintu3,             // K: Detik Konfirmasi

                (float) $row->gji,              // L: Upah Hadir
                (float) $row->gji2,             // M: Upah Inspect
                (float) $row->varnt,            // N: Var Upah
                (float) $row->varnt1,           // O: Persentase Var (rata-rata harian, 0-∞)
            ];
        });
    }

    /**
     * 1. Styling Header (Baris 1)
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style Baris 1 (Header)
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

    /**
     * 2. Format Angka
     */
    public function columnFormats(): array
    {
        return [
            'G' => '#,##0.0',   // Menit Hadir
            'H' => '#,##0',     // Menit Conf
            'I' => '#,##0',     // Menit Inspect
            'J' => '#,##0',     // Detik Inspect
            'K' => '#,##0',     // Detik Konfirmasi
            'L' => '#,##0.00',  // Upah Hadir
            'M' => '#,##0.00',  // Upah Inspect
            'N' => '#,##0.00',  // Var Upah
            'O' => '0.00',      // Persentase Var (angka 0-∞, tanpa % kedua kalinya)
        ];
    }

    /**
     * 3. Event Listener untuk Styling Lanjutan (Border, Alignment Kolom, Freeze Pane)
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $rowCount = $this->rows->count() + 1; // +1 header
                $lastColumn = 'O'; // sekarang kolom terakhir O
                $tableRange = 'A1:' . $lastColumn . $rowCount;

                $sheet->setAutoFilter('A1:' . $lastColumn . '1');

                // rata tengah No, Personal No, WC
                $sheet->getStyle('A2:A' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('B2:B' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('E2:E' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // kiri: rentang tanggal, nama, desc
                $sheet->getStyle('C2:C' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('D2:D' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('F2:F' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // kanan: semua angka (G..O)
                $sheet->getStyle('G2:O' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Border semua sel
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['argb' => 'FFD1D5DB'],
                        ],
                    ],
                ]);

                // zebra striping pakai $lastColumn
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
