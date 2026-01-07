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

class WiDetailExport implements
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
            'No',            // A
            'NIK',           // B
            'Tanggal',       // C
            'Nama',          // D
            'Devisi',        // E
            'WC',            // F
            'Time WI',       // G
            'Time CONF',     // H
            'Time QM',       // I
            '% Hasil WI',     // J
            '% HASIL QM', // K
        ];
    }

    public function collection(): Collection
    {
        $i = 0;

        return $this->rows->map(function ($row) use (&$i) {
            $i++;

            $tgl = !empty($row->tanggal) ? Carbon::parse($row->tanggal)->isoFormat('YY-MM-DD') : '';

            $timeWi   = isset($row->time_wi) ? (is_null($row->time_wi) ? null : (float)$row->time_wi) : null;
            $timeConf = isset($row->time_conf) ? (is_null($row->time_conf) ? null : (float)$row->time_conf) : null;
            $timeQm   = isset($row->time_qm) ? (is_null($row->time_qm) ? null : (float)$row->time_qm) : null;

            // KPI hanya valid kalau WI tidak null (sesuai UI kamu)
            $kpiQty = null;
            $kpiQuality = null;

            if (!is_null($timeWi)) {
                // Qty = WI / CONF
                $wiBase = (float)($timeWi ?? 0);
                $kpiQty = ($wiBase == 0.0) ? 0.0 : (((float)($timeConf ?? 0) / $wiBase) * 100);

                // Quality = QM / WI
                $kpiQuality = ($timeWi == 0.0) ? 0.0 : (((float)($timeQm ?? 0) / (float)$timeWi) * 100);
            }

            // fallback kalau controller sudah kirim
            if (!is_null($timeWi)) {
                if (isset($row->kpi_qty_pct)) {
                    $kpiQty = (float)$row->kpi_qty_pct;
                }
                if (isset($row->kpi_quality_pct)) {
                    $kpiQuality = (float)$row->kpi_quality_pct;
                }
            }

            $devisi = (string)($row->devisi ?? '');
            if (trim($devisi) === '') $devisi = '-';

            return [
                $i,                          // A
                (string)($row->nik ?? ''),   // B
                $tgl,                        // C
                (string)($row->nama ?? ''),  // D
                $devisi,                     // E
                (string)($row->wc ?? ''),    // F
                $timeWi,                     // G
                $timeConf,                   // H
                $timeQm,                     // I
                $kpiQty,                     // J
                $kpiQuality,                 // K
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
            'G' => '#,##0.00', // Time WI
            'H' => '#,##0.00', // Time CONF
            'I' => '#,##0.00', // Time QM
            'J' => '0.00',     // KPI HASIL WI (2 decimal)
            'K' => '0.00',     // HASIL QM (2 decimal)
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $rowCount = $this->rows->count() + 1; // + header
                $lastCol = 'K';
                $tableRange = "A1:{$lastCol}{$rowCount}";

                $sheet->setAutoFilter("A1:{$lastCol}1");
                $sheet->freezePane('A2');

                // Align
                $sheet->getStyle("A2:C{$rowCount}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // No, NIK, Tanggal
                $sheet->getStyle("F2:F{$rowCount}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // WC
                $sheet->getStyle("D2:D{$rowCount}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);   // Nama
                $sheet->getStyle("E2:E{$rowCount}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);   // Devisi

                $sheet->getStyle("G2:K{$rowCount}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);  // Angka

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
