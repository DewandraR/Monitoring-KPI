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
            'DESC WC',
            'WC Confirmasi',
            'Plant',
            'Shift',
        ];
    }

    public function collection(): Collection
    {
        return $this->rows->map(function ($row) {
            $rentangTanggal =
                Carbon::createFromFormat('Ymd', $row->min_begda)->isoFormat('YY-MM-DD') .
                ' - ' .
                Carbon::createFromFormat('Ymd', $row->max_begda)->isoFormat('YY-MM-DD');

            return [
                $row->pernr,            // A
                $rentangTanggal,        // B
                (float) $row->total_jam, // C
                (int) $row->mint2,      // D
                (int) $row->mintu,      // E
                (int) $row->mintu2,     // F
                (int) $row->mintu3,     // G
                $row->cname,            // H
                (float) $row->gji,      // I
                (float) $row->gji2,     // J
                (float) $row->varnt,    // K
                (float) $row->varnt1,   // L
                $row->arbpl,            // M
                $row->desc,             // N
                $row->arbpl2,           // O
                $row->werks,            // P
                is_null($row->shift) ? null : (int) $row->shift, // Q
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
     * 2. Format Angka (Agar Excel membacanya sebagai angka/uang, bukan teks)
     */
    public function columnFormats(): array
    {
        return [
            'C' => '#,##0.0',  // Menit Hadir (1 desimal)
            'D' => '#,##0',    // Menit Kerja (Bulat)
            'E' => '#,##0',    // Menit Inspect
            'F' => '#,##0',    // Detik Inspect
            'G' => '#,##0',    // Detik Conf
            'I' => '#,##0.00', // Upah Hadir (2 desimal)
            'J' => '#,##0.00', // Upah Insp
            'K' => '#,##0.00', // Variant
            'L' => '0.00%',    // Prosentase (langsung format %)
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
                $rowCount = $this->rows->count() + 1; // +1 karena ada header
                $lastColumn = 'Q'; // Kolom terakhir (Shift)

                // Range seluruh tabel data
                $tableRange = 'A1:' . $lastColumn . $rowCount;

                // a. Tambahkan AutoFilter di Header
                $sheet->setAutoFilter('A1:' . $lastColumn . '1');

                // b. Freeze Header (Biar header tetap terlihat saat scroll ke bawah)
                $sheet->freezePane('A2');

                // c. Berikan Border Tipis ke seluruh data
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFD1D5DB'], // Abu-abu muda
                        ],
                    ],
                ]);

                // d. Atur Alignment Kolom secara spesifik

                // Rata Tengah: NIK (A), Codes (M, O, P, Q)
                $sheet->getStyle('A2:A' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('M2:Q' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Rata Kiri: Nama (H), Desc (N)
                $sheet->getStyle('H2:H' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('N2:N' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Rata Kanan: Angka (C, D, E, F, G, I, J, K, L)
                // (Sebenarnya format angka otomatis rata kanan, tapi kita paksa biar rapi)
                $sheet->getStyle('C2:G' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('I2:L' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // e. Opsional: Zebra Striping (Warna selang-seling)
                // Loop dari baris 2 sampai akhir
                for ($i = 2; $i <= $rowCount; $i++) {
                    if ($i % 2 == 0) { // Baris Genap
                        $sheet->getStyle('A' . $i . ':' . $lastColumn . $i)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FFECFDF5'], // Emerald 50 (Sangat muda)
                            ],
                        ]);
                    }
                }
            },
        ];
    }
}
