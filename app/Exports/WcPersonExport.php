<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WcPersonExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    ShouldAutoSize,
    WithStyles,
    WithEvents
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
            'Tgl Mulai',
            'Nama',
            'Role',
            'Work Center',
            'Deskripsi Work Center',
            'Devisi',
            'Plant',
        ];
    }

    public function map($row): array
    {
        return [
            $row->pernr,
            $this->formatBegda($row->begda),
            $row->stext,
            $row->role,
            $row->arbpl,
            $row->desc,
            $row->devisi ?? '',   // Ganti jika field namanya beda
            $row->werks,
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
     * Styling dasar header (baris pertama)
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold'  => true,
                    'size'  => 12,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
                'fill' => [
                    'fillType'  => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF065F46'], // Emerald 800
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    /**
     * Styling lanjutan AfterSheet
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $rowCount = $this->rows->count() + 1;
                $lastCol = 'H'; // Kolom terakhir sekarang H (Plant)
                $range   = 'A1:' . $lastCol . $rowCount;

                // A. Auto Filter & Freeze Pane
                $sheet->setAutoFilter($range);
                $sheet->freezePane('A2');

                // B. Border tipis
                $sheet->getStyle($range)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['argb' => 'FFD1D5DB'],
                        ],
                    ],
                ]);

                // C. Alignment per kolom
                // Center: NIK (A), Tgl (B), Role (D), Plant (H)
                $sheet->getStyle('A2:B' . $rowCount)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('D2:D' . $rowCount)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('H2:H' . $rowCount)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Left: Nama (C), Work Center (E), Deskripsi (F), Devisi (G)
                $sheet->getStyle('C2:C' . $rowCount)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('E2:G' . $rowCount)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // D. Zebra + highlight "INDUK" di kolom Role (D)
                for ($i = 2; $i <= $rowCount; $i++) {
                    // Zebra striping (genap)
                    if ($i % 2 == 0) {
                        $sheet->getStyle('A' . $i . ':' . $lastCol . $i)->applyFromArray([
                            'fill' => [
                                'fillType'  => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FFECFDF5'], // Emerald 50
                            ],
                        ]);
                    }

                    // Cek Role di kolom D untuk "INDUK"
                    $roleVal = $sheet->getCell('D' . $i)->getValue();
                    if (strtoupper(trim($roleVal)) === 'INDUK') {
                        $sheet->getStyle('D' . $i)->applyFromArray([
                            'font' => [
                                'bold'  => true,
                                'color' => ['argb' => 'FFB45309'], // Amber 700
                            ],
                        ]);
                    }
                }
            },
        ];
    }
}
