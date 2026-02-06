<?php

namespace App\Exports;

use App\Livewire\WiDailyReport;
use Carbon\Carbon;
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

    // samakan dengan Livewire (tanpa ubah constructor)
    protected string $dataVisibleFrom = '2025-12-01';

    // total kolom (A..K)
    protected int $cols = 11;

    public function __construct(Collection $data, string $plant, string $range)
    {
        $this->data  = $data;
        $this->plant = $plant;
        $this->range = $range;
    }

    protected function padRow(array $row): array
    {
        return array_pad($row, $this->cols, '');
    }

    protected function parseRangeDates(string $range): array
    {
        // menerima format: "YYYY-MM-DD s.d. YYYY-MM-DD" (sesuai PDF)
        $range = trim($range);

        if (preg_match('/(\d{4}-\d{2}-\d{2})\s*(?:s\.d\.|sd|to|-\s*)\s*(\d{4}-\d{2}-\d{2})/i', $range, $m)) {
            $s = Carbon::parse($m[1])->startOfDay();
            $e = Carbon::parse($m[2])->startOfDay();
            if ($e->lt($s)) [$s, $e] = [$e, $s];
            return [$s, $e];
        }

        // fallback aman (kalau range string beda)
        $today = Carbon::today()->startOfDay();
        return [$today->copy()->startOfMonth(), $today->copy()->subDay()];
    }

    public function collection(): Collection
    {
        $rows = collect();

        [$start, $end] = $this->parseRangeDates($this->range);

        // ==========
        // PRE-CALC REMARK (1x)
        // ==========
        $membersByKorlap = [];
        $allMemberSet = [];

        foreach ($this->data as $group) {
            $korlapNik = (string)($group['korlap_nik'] ?? '');
            $members = $group['members'] ?? [];

            $set = [];
            foreach ($members as $m) {
                $nik = trim((string)($m->nik ?? ''));
                if ($nik === '') continue;

                $allMemberSet[$nik] = true;
                $set[$nik] = true;
            }

            $membersByKorlap[$korlapNik] = array_values(array_keys($set));
        }

        $allMemberNiks = array_values(array_keys($allMemberSet));

        $remarkByNik = !empty($allMemberNiks)
            ? WiDailyReport::export_remarkMapForNiks($this->plant, $this->dataVisibleFrom, $allMemberNiks, $start, $end)
            : [];

        [$remarkTotalByKorlap, $remarkPreviewByKorlap] = !empty($membersByKorlap)
            ? WiDailyReport::export_korlapRemarkTotalPreview($this->plant, $this->dataVisibleFrom, $membersByKorlap, $start, $end, 3)
            : [[], []];

        // ==========
        // JUDUL
        // ==========
        $rows->push($this->padRow(['LAPORAN TIM KORLAP - PLANT ' . $this->plant]));
        $rows->push($this->padRow(['Periode: ' . $this->range]));
        $rows->push($this->padRow(['']));

        foreach ($this->data as $idx => $group) {

            $korlapNik = (string)($group['korlap_nik'] ?? '');

            $kpiQtyKorlap     = (float)($group['summary']['kpi_qty_pct'] ?? 0);
            $kpiQualityKorlap = (float)($group['summary']['kpi_quality_pct'] ?? 0);

            // remark korlap
            $totalKorlap = (int)($group['remark_total'] ?? ($remarkTotalByKorlap[$korlapNik] ?? 0));
            $previewKorlap = $group['remark_preview_lines'] ?? ($remarkPreviewByKorlap[$korlapNik] ?? []);
            if (!is_array($previewKorlap)) $previewKorlap = [];

            $remarkKorlapText = 'Total: ' . $totalKorlap . ' Task';
            if (!empty($previewKorlap)) {
                $remarkKorlapText .= ' | ' . implode(' | ', $previewKorlap);
            }

            // =========================
            // HEADER KORLAP (A..K)
            // =========================
            $rows->push($this->padRow([
                'NO',
                'NIK KORLAP',
                'NAMA KORLAP',
                'WC Anggota',
                'JML NIK INDUK WI',
                'Menit WI',
                'Menit CONF',
                'TIME QM',
                'HASIL MENIT CONF %',
                'HASIL MENIT QM %',
                'REMARK (TOTAL & TOP)',
            ]));

            // DATA KORLAP
            $rows->push($this->padRow([
                $idx + 1,
                $group['korlap_nik'] ?? '',
                strtoupper((string)($group['korlap_nama'] ?? '')),
                $group['summary']['wc_string'] ?? '',
                ($group['summary']['count_nik'] ?? 0) . ' Org',
                (float)($group['summary']['total_wi'] ?? 0),
                (float)($group['summary']['total_conf'] ?? 0),
                (float)($group['summary']['total_qm'] ?? 0),
                $kpiQtyKorlap,
                $kpiQualityKorlap,
                $remarkKorlapText,
            ]));

            // Label Detail (merge via style)
            $rows->push($this->padRow(['DETAIL ANGGOTA:']));

            // =========================
            // HEADER MEMBER (A..K)
            // =========================
            $rows->push($this->padRow([
                'NO',
                'NIK',
                'NAMA ANGGOTA',
                'DEVISI',
                'WC',
                'Menit WI',
                'Menit CONF',
                'TIME QM',
                'HASIL MENIT CONF %',
                'HASIL MENIT QM %',
                'REMARK',
            ]));

            // DATA MEMBER
            foreach (($group['members'] ?? []) as $mIdx => $m) {
                $nik = trim((string)($m->nik ?? ''));

                $lines = $m->remark_lines ?? ($remarkByNik[$nik] ?? []);
                if (!is_array($lines)) $lines = [];

                $remarkText = !empty($lines) ? implode("\n", $lines) : '';

                $rows->push($this->padRow([
                    $mIdx + 1,
                    $m->nik ?? '',
                    $m->nama ?? '',
                    $m->devisi ?? '',
                    $m->wc ?? '',
                    (float)($m->time_wi_sum ?? 0),
                    (float)($m->time_conf_sum ?? 0),
                    (float)($m->time_qm_sum ?? 0),
                    (float)($m->kpi_qty_pct ?? 0),
                    (float)($m->kpi_quality_pct ?? 0),
                    $remarkText,
                ]));
            }

            // Spasi antar grup
            $rows->push($this->padRow(['']));
            $rows->push($this->padRow(['']));
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
        return [];
    }

    public function columnFormats(): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                // Merge judul
                $sheet->mergeCells('A1:K1');
                $sheet->mergeCells('A2:K2');

                // Lebar kolom A..K
                $sheet->getColumnDimension('A')->setWidth(5);   // No
                $sheet->getColumnDimension('B')->setWidth(14);  // NIK
                $sheet->getColumnDimension('C')->setWidth(30);  // Nama
                $sheet->getColumnDimension('D')->setWidth(24);  // Devisi / WC Anggota
                $sheet->getColumnDimension('E')->setWidth(14);  // WC / Jml
                $sheet->getColumnDimension('F')->setWidth(12);  // WI
                $sheet->getColumnDimension('G')->setWidth(12);  // Conf
                $sheet->getColumnDimension('H')->setWidth(12);  // QM
                $sheet->getColumnDimension('I')->setWidth(14);  // KPI CONF
                $sheet->getColumnDimension('J')->setWidth(14);  // KPI QM
                $sheet->getColumnDimension('K')->setWidth(45);  // REMARK

                // Wrap text untuk kolom D & K
                $sheet->getStyle("D:D")->getAlignment()->setWrapText(true);
                $sheet->getStyle("K:K")->getAlignment()->setWrapText(true);

                for ($row = 1; $row <= $lastRow; $row++) {

                    $cellA = $sheet->getCell("A{$row}")->getValue();
                    $cellB = $sheet->getCell("B{$row}")->getValue();

                    // HEADER KORLAP
                    if ($cellB === 'NIK KORLAP') {
                        $range = "A{$row}:K{$row}";
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

                    // DATA KORLAP (baris setelah header korlap)
                    $prevRow = $row - 1;
                    $prevCellB = ($prevRow > 0) ? $sheet->getCell("B{$prevRow}")->getValue() : null;

                    if ($prevCellB === 'NIK KORLAP') {
                        $range = "A{$row}:K{$row}";
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

                        // Warna KPI CONF (I)
                        $kpiQtyVal = $sheet->getCell("I{$row}")->getValue();
                        if (is_numeric($kpiQtyVal) && $kpiQtyVal < 100) {
                            $sheet->getStyle("I{$row}")->getFont()->getColor()->setARGB('FFC53030');
                        } else {
                            $sheet->getStyle("I{$row}")->getFont()->getColor()->setARGB('FF047857');
                        }

                        // Warna KPI QM (J)
                        $kpiQualityVal = $sheet->getCell("J{$row}")->getValue();
                        if (is_numeric($kpiQualityVal) && $kpiQualityVal < 100) {
                            $sheet->getStyle("J{$row}")->getFont()->getColor()->setARGB('FFC53030');
                        } else {
                            $sheet->getStyle("J{$row}")->getFont()->getColor()->setARGB('FF047857');
                        }

                        // Wrap remark korlap
                        $sheet->getStyle("K{$row}")->getAlignment()->setWrapText(true);

                        continue;
                    }

                    // LABEL DETAIL
                    if ($cellA === 'DETAIL ANGGOTA:') {
                        $sheet->mergeCells("A{$row}:K{$row}");
                        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setItalic(true);
                        continue;
                    }

                    // HEADER MEMBER
                    if ($cellB === 'NIK' && $cellA === 'NO') {
                        $range = "A{$row}:K{$row}";
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

                    // DATA MEMBER
                    if (is_numeric($cellA) && $cellB !== null && $prevCellB !== 'NIK KORLAP') {

                        $range = "A{$row}:K{$row}";
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

                        // KPI CONF color (I)
                        $kpiQtyVal = $sheet->getCell("I{$row}")->getValue();
                        if (is_numeric($kpiQtyVal) && $kpiQtyVal < 100) {
                            $sheet->getStyle("I{$row}")->getFont()->getColor()->setARGB('FFC53030');
                        } else {
                            $sheet->getStyle("I{$row}")->getFont()->getColor()->setARGB('FF047857');
                        }

                        // KPI QM color (J)
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

                        // Wrap remark anggota
                        $sheet->getStyle("K{$row}")->getAlignment()->setWrapText(true);
                    }
                }
            }
        ];
    }
}
