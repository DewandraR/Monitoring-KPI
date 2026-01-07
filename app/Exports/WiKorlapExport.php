<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WiKorlapExport implements
    FromCollection,
    WithStyles,
    WithColumnFormatting,
    WithEvents
{
    protected Collection $data;
    protected string $plant;
    protected string $range;

    public function __construct(Collection $data, string $plant, string $range)
    {
        $this->data  = $data;
        $this->plant = $plant;
        $this->range = $range;
    }

    public function collection(): Collection
    {
        $rows = collect();

        // Judul (baris 1)
        $rows->push(['LAPORAN TIM KORLAP - PLANT ' . $this->plant]);
        // Periode (baris 2)
        $rows->push(['Periode: ' . $this->range]);
        // Spasi (baris 3)
        $rows->push(['']);

        foreach ($this->data as $idx => $group) {

            $kpiQtyKorlap     = (float)($group['summary']['kpi_qty_pct'] ?? 0);
            $kpiQualityKorlap = (float)($group['summary']['kpi_quality_pct'] ?? 0);

            // =========================
            // HEADER KORLAP (10 kolom)
            // =========================
            $rows->push([
                'NO',
                'NIK KORLAP',
                'NAMA KORLAP',
                'WC Anggota',
                'JML NIK INDUK WI',
                'TIME WI',
                'TIME CONF',
                'TIME QM',
                'HASIL MENIT WI %',
                'HASIL MENIT QM %',
            ]);

            // DATA KORLAP
            $rows->push([
                $idx + 1,
                $group['korlap_nik'],
                strtoupper($group['korlap_nama']),
                $group['summary']['wc_string'],
                ($group['summary']['count_nik'] ?? 0) . ' Org',
                (float)($group['summary']['total_wi'] ?? 0),
                (float)($group['summary']['total_conf'] ?? 0),
                (float)($group['summary']['total_qm'] ?? 0),
                $kpiQtyKorlap,
                $kpiQualityKorlap,
            ]);

            // Label Detail
            $rows->push(['DETAIL ANGGOTA:']);

            // =========================
            // HEADER MEMBER (10 kolom)
            // =========================
            $rows->push([
                'NO',
                'NIK',
                'NAMA ANGGOTA',
                'DEVISI',
                'WC',
                'TIME WI',
                'TIME CONF',
                'TIME QM',
                'HASIL MENIT WI %',
                'HASIL MENIT QM %',
            ]);

            // DATA MEMBER
            foreach ($group['members'] as $mIdx => $m) {
                $rows->push([
                    $mIdx + 1,
                    $m->nik,
                    $m->nama,
                    $m->devisi,
                    $m->wc,
                    (float)($m->time_wi_sum ?? 0),
                    (float)($m->time_conf_sum ?? 0),
                    (float)($m->time_qm_sum ?? 0),
                    (float)($m->kpi_qty_pct ?? 0),
                    (float)($m->kpi_quality_pct ?? 0),
                ]);
            }

            // Spasi antar grup
            $rows->push(['']);
            $rows->push(['']);
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // Style judul
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

        return [];
    }

    public function columnFormats(): array
    {
        // Kita format dinamis via AfterSheet
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                // Lebar kolom A..J
                $sheet->getColumnDimension('A')->setWidth(5);   // No
                $sheet->getColumnDimension('B')->setWidth(14);  // NIK
                $sheet->getColumnDimension('C')->setWidth(30);  // Nama
                $sheet->getColumnDimension('D')->setWidth(24);  // Devisi / WC Anggota
                $sheet->getColumnDimension('E')->setWidth(12);  // WC / Jml
                $sheet->getColumnDimension('F')->setWidth(12);  // WI
                $sheet->getColumnDimension('G')->setWidth(12);  // Conf
                $sheet->getColumnDimension('H')->setWidth(12);  // QM
                $sheet->getColumnDimension('I')->setWidth(12);  // HASIL MENIT WI
                $sheet->getColumnDimension('J')->setWidth(14);  // HASIL MENIT QM

                for ($row = 1; $row <= $lastRow; $row++) {

                    $cellA = $sheet->getCell("A{$row}")->getValue();
                    $cellB = $sheet->getCell("B{$row}")->getValue();

                    // =====================
                    // HEADER KORLAP
                    // =====================
                    if ($cellB === 'NIK KORLAP') {
                        $range = "A{$row}:J{$row}";
                        $sheet->getStyle($range)->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => '2D3748']
                            ],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                        ]);
                        continue;
                    }

                    // =====================
                    // DATA KORLAP (baris setelah header korlap)
                    // =====================
                    $prevRow = $row - 1;
                    $prevCellB = ($prevRow > 0) ? $sheet->getCell("B{$prevRow}")->getValue() : null;

                    if ($prevCellB === 'NIK KORLAP') {
                        $range = "A{$row}:J{$row}";
                        $sheet->getStyle($range)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'EDF2F7']
                            ],
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                        ]);

                        // Format angka time
                        $sheet->getStyle("F{$row}:H{$row}")
                            ->getNumberFormat()->setFormatCode('#,##0.00');

                        // Format KPI (I & J)
                        $sheet->getStyle("I{$row}:J{$row}")
                            ->getNumberFormat()->setFormatCode('0.00');

                        // Warna HASIL MENIT WI (I)
                        $kpiQtyVal = $sheet->getCell("I{$row}")->getValue();
                        if (is_numeric($kpiQtyVal) && $kpiQtyVal < 100) {
                            $sheet->getStyle("I{$row}")->getFont()->getColor()->setARGB('FFC53030');
                        } else {
                            $sheet->getStyle("I{$row}")->getFont()->getColor()->setARGB('FF047857');
                        }

                        // Warna HASIL MENIT QM (J)
                        $kpiQualityVal = $sheet->getCell("J{$row}")->getValue();
                        if (is_numeric($kpiQualityVal) && $kpiQualityVal < 100) {
                            $sheet->getStyle("J{$row}")->getFont()->getColor()->setARGB('FFC53030');
                        } else {
                            $sheet->getStyle("J{$row}")->getFont()->getColor()->setARGB('FF047857');
                        }

                        continue;
                    }

                    // =====================
                    // LABEL DETAIL
                    // =====================
                    if ($cellA === 'DETAIL ANGGOTA:') {
                        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setItalic(true);
                        continue;
                    }

                    // =====================
                    // HEADER MEMBER
                    // =====================
                    if ($cellB === 'NIK' && $cellA === 'NO') {
                        $range = "A{$row}:J{$row}";
                        $sheet->getStyle($range)->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => '2D3748']],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'E2E8F0']
                            ],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                        ]);
                        continue;
                    }

                    // =====================
                    // DATA MEMBER
                    // =====================
                    if (is_numeric($cellA) && $cellB !== null && $prevCellB !== 'NIK KORLAP') {
                        $range = "A{$row}:J{$row}";
                        $sheet->getStyle($range)->applyFromArray([
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                        ]);

                        // Zebra striping
                        if (((int)$cellA) % 2 === 0) {
                            $sheet->getStyle($range)->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setRGB('F7FAFC');
                        }

                        // Format angka time
                        $sheet->getStyle("F{$row}:H{$row}")
                            ->getNumberFormat()->setFormatCode('#,##0.00');

                        // KPI format
                        $sheet->getStyle("I{$row}:J{$row}")
                            ->getNumberFormat()->setFormatCode('0.00');

                        // HASIL MENIT WI color (I)
                        $kpiQtyVal = $sheet->getCell("I{$row}")->getValue();
                        if (is_numeric($kpiQtyVal) && $kpiQtyVal < 100) {
                            $sheet->getStyle("I{$row}")->getFont()->getColor()->setARGB('FFC53030');
                        } else {
                            $sheet->getStyle("I{$row}")->getFont()->getColor()->setARGB('FF047857');
                        }

                        // HASIL MENIT QM color (J)
                        $kpiQualityVal = $sheet->getCell("J{$row}")->getValue();
                        if (is_numeric($kpiQualityVal) && $kpiQualityVal < 100) {
                            $sheet->getStyle("J{$row}")->getFont()->getColor()->setARGB('FFC53030');
                        } else {
                            $sheet->getStyle("J{$row}")->getFont()->getColor()->setARGB('FF047857');
                        }

                        // Alignment
                        $sheet->getStyle("A{$row}:B{$row}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle("E{$row}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }
            }
        ];
    }
}
