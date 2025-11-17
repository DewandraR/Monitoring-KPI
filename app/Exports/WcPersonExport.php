<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Carbon\Carbon;

class WcPersonExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
{
    /** @var \Illuminate\Support\Collection */
    protected Collection $rows;

    public function __construct($rows)
    {
        $this->rows = $rows instanceof Collection ? $rows : collect($rows);
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'NIK',
            'TGL Mulai',
            'Nama Karyawan',
            'Work Center',
            'Deskripsi Work Center',
            'Plant',
            'Role',
        ];
    }

    public function map($row): array
    {
        return [
            $row->pernr,
            $this->formatBegda($row->begda),
            $row->stext,
            $row->arbpl,
            $row->desc,
            $row->werks,
            $row->role,
        ];
    }

    protected function formatBegda($begda): string
    {
        if ($begda && preg_match('/^\d{8}$/', $begda)) {
            return Carbon::createFromFormat('Ymd', $begda)->format('d-m-Y');
        }
        return (string) $begda;
    }

    /**
     * 1. Styling Dasar Header
     */
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
                    'startColor' => ['argb' => 'FF065F46'], // Emerald 800
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    /**
     * 2. Event Listener untuk Styling Lanjutan
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $rowCount = $this->rows->count() + 1;
                $lastCol = 'G'; // Kolom terakhir (Role)
                $range = 'A1:' . $lastCol . $rowCount;

                // A. Auto Filter & Freeze Pane
                $sheet->setAutoFilter($range);
                $sheet->freezePane('A2');

                // B. Borders Tipis Semua Sel
                $sheet->getStyle($range)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFD1D5DB'],
                        ],
                    ],
                ]);

                // C. Alignment Per Kolom
                // Center: NIK (A), Tgl (B), Plant (F), Role (G)
                $sheet->getStyle('A2:B' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('F2:G' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Left: Nama (C), WC (D), Desc (E)
                $sheet->getStyle('C2:E' . $rowCount)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // D. Loop Styling Baris (Zebra & Induk Highlight)
                for ($i = 2; $i <= $rowCount; $i++) {
                    // Zebra Striping (Genap)
                    if ($i % 2 == 0) {
                        $sheet->getStyle('A' . $i . ':' . $lastCol . $i)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FFECFDF5'], // Emerald 50
                            ],
                        ]);
                    }

                    // Cek Kolom Role (G) untuk "INDUK"
                    // Kita ambil nilai cell G di baris ini
                    $roleVal = $sheet->getCell('G' . $i)->getValue();
                    if (strtoupper(trim($roleVal)) === 'INDUK') {
                        // Bold & Warna Kuning Emas pada tulisan "INDUK"
                        $sheet->getStyle('G' . $i)->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'color' => ['argb' => 'FFB45309'], // Amber 700
                            ],
                        ]);
                    }
                }
            },
        ];
    }
}
