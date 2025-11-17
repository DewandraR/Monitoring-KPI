<?php

namespace App\Http\Controllers;

use App\Exports\ReportSummaryExport;
use App\Models\ReportData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportPdfController extends Controller
{
    /**
     * Ambil summary rows (sama persis dengan query di ReportGenerator)
     *
     * @param  array  $pernrs
     * @param  string $werks
     * @return \Illuminate\Support\Collection
     */
    protected function getSummaryRows(array $pernrs, string $werks)
    {
        $aggregateColumns = [
            'total_jam',
            'mint2',
            'mintu',
            'mintu2',
            'mintu3',
            'gji',
            'gji2',
            'varnt',
            'varnt1',
        ];

        $sumSelects = array_map(fn($col) => "SUM($col) as $col", $aggregateColumns);

        // ⬇️ DIUBAH: ikutkan 'desc' sebagai non-aggregate (MAX)
        $nonAggSelects = array_map(
            fn($col) => "MAX(`$col`) as `$col`",
            ['cname', 'arbpl', 'desc', 'arbpl2', 'werks']
        );

        $nonAggSelects[] = 'MIN(shift) as shift';
        $dateRangeSel    = ['MIN(begda) as min_begda', 'MAX(begda) as max_begda'];

        $selects = array_merge(['pernr'], $dateRangeSel, $nonAggSelects, $sumSelects);

        return ReportData::query()
            ->whereRaw('UPPER(TRIM(werks)) = ?', [$werks])
            ->whereIn('pernr', $pernrs)
            ->selectRaw(implode(', ', $selects))
            ->groupBy('pernr')
            ->orderBy('pernr')
            ->get();
    }

    /**
     * Export PDF untuk NIK terpilih.
     * Route: GET /report-data/{werks}/export-pdf
     */
    public function exportSelected(Request $request, string $werks)
    {
        // ambil dari session yang di-set Livewire
        $pernrs = (array) $request->session()->get('report_export.pernrs', []);

        // rapikan
        $pernrs = array_values(array_filter(array_map('strval', $pernrs)));

        if (empty($pernrs)) {
            abort(404, 'Tidak ada Personal No. yang dipilih untuk di-export.');
        }

        $werks = strtoupper(trim($werks));

        $rows = $this->getSummaryRows($pernrs, $werks);

        if ($rows->isEmpty()) {
            abort(404, 'Data tidak ditemukan untuk pilihan tersebut.');
        }

        // opsional: bersihkan session supaya kalau halaman di-refresh tidak pakai data lama
        $request->session()->forget('report_export.pernrs');

        $pdf = Pdf::loadView('pdf.report-summary', [
            'rows'  => $rows,
            'werks' => $werks,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("report-data-{$werks}.pdf");
    }

    /**
     * Export Excel (.xlsx) untuk NIK terpilih.
     * Route: GET /report-data/{werks}/export-excel
     */
    public function exportSelectedExcel(Request $request, string $werks)
    {
        // ambil dari session yang di-set Livewire
        $pernrs = (array) $request->session()->get('report_export.pernrs', []);

        // rapikan
        $pernrs = array_values(array_filter(array_map('strval', $pernrs)));

        if (empty($pernrs)) {
            abort(404, 'Tidak ada Personal No. yang dipilih untuk di-export.');
        }

        $werks = strtoupper(trim($werks));

        $rows = $this->getSummaryRows($pernrs, $werks);

        if ($rows->isEmpty()) {
            abort(404, 'Data tidak ditemukan untuk pilihan tersebut.');
        }

        // bersihkan session
        $request->session()->forget('report_export.pernrs');

        $filename = "report-data-{$werks}.xlsx";

        return Excel::download(new ReportSummaryExport($rows, $werks), $filename);
    }
}
