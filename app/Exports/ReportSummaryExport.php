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
            'No',
            'Personal No.',
            'Rentang Tanggal',
            'Nama',
            'WC Personal',
            'DESC WC',
            'Menit Hadir',
            'Menit Conf',
            'Menit Inspect',
            'Var Upah',
            'Persentase Var',
        ];
    }

    public function collection(): Collection
    {
        // Kita generate kolom "No" (1,2,3,...) manual
        $i = 0;

        return $this->rows->map(function ($row) use (&$i) {
            $i++;

            $rentangTanggal =
                Carbon::createFromFormat('Ymd', $row->min_begda)->isoFormat('YY-MM-DD') .
                ' - ' .
                Carbon::createFromFormat('Ymd', $row->max_begda)->isoFormat('YY-MM-DD');

            return [
                $i,                     // A: No
                $row->pernr,            // B: Personal No.
                $rentangTanggal,        // C: Rentang Tanggal
                $row->cname,            // D: Nama
                $row->arbpl,            // E: WC Personal
                $row->desc,             // F: DESC WC
                (float) $row->total_jam, // G: Menit Hadir
                (int) $row->mintu3,     // H: Menit Conf
                (int) $row->mintu,      // I: Menit Inspect
                (float) $row->varnt,    // J: Var Upah
                (float) $row->varnt1,   // K: Persentase Var (angka 100 = 100.00)
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
            'G' => '#,##0.0',  // Menit Hadir (1 desimal)
            'H' => '#,##0',    // Menit Conf
            'I' => '#,##0',    // Menit Inspect
            'J' => '#,##0.00', // Var Upah
            // K: Persentase Var -> kita biarkan sebagai angka biasa 2 desimal
            'K' => '0.00',
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
                $lastColumn = 'K'; // Kolom terakhir

                // Range seluruh tabel data
                $tableRange = 'A1:' . $lastColumn . $rowCount;

                // a. AutoFilter di header
                $sheet->setAutoFilter('A1:' . $lastColumn . '1');

                // b. Freeze header
                $sheet->freezePane('A2');

                // c. Border tipis semua sel
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFD1D5DB'],
                        ],
                    ],
                ]);

                // d. Alignment kolom

                // Rata tengah: No (A), Personal No (B), WC Personal (E)
                $sheet->getStyle('A2:A' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('B2:B' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('E2:E' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Rata kiri: Rentang Tanggal (C), Nama (D), DESC WC (F)
                $sheet->getStyle('C2:C' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('D2:D' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('F2:F' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Rata kanan: angka Menit & Var (G-K)
                $sheet->getStyle('G2:K' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // e. Zebra striping
                for ($i = 2; $i <= $rowCount; $i++) {
                    if ($i % 2 == 0) { // Baris genap
                        $sheet->getStyle('A' . $i . ':' . $lastColumn . $i)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FFECFDF5'], // Emerald 50
                            ],
                        ]);
                    }
                }
            },
        ];
    }
}
