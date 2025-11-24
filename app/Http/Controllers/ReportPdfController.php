<?php

namespace App\Http\Controllers;

use App\Exports\ReportSummaryExport;
use App\Exports\ReportDetailExport;
use App\Models\ReportData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class ReportPdfController extends Controller
{
    /**
     * Ambil summary rows (sama persis dengan query di ReportGenerator)
     *
     * @param  array  $pernrs
     * @param  string $werks
     * @return \Illuminate\Support\Collection
     */
    protected function getSummaryRows(array $pernrs, string $werks): Collection
    {
        // kolom yang di-SUM
        $aggregateColumns = [
            'total_jam',
            'mint2',
            'mintu',
            'mintu2',
            'mintu3',
            'gji',   // Upah Hadir
            'gji2',  // Upah Inspect
            'varnt',
            // varnt1 sengaja TIDAK dimasukkan, kita hitung manual di view/export
        ];

        $sumSelects = array_map(fn($col) => "SUM($col) AS $col", $aggregateColumns);

        // non-aggregate (ambil MAX atau MIN)
        $nonAggSelects = array_map(
            fn($col) => "MAX(`$col`) AS `$col`",
            // >> DI SINI DITAMBAH role & devisi <<
            ['cname', 'arbpl', 'desc', 'arbpl2', 'werks', 'role', 'devisi']
        );
        $nonAggSelects[] = 'MIN(shift) AS shift';

        $dateRangeSel = ['MIN(begda) AS min_begda', 'MAX(begda) AS max_begda'];

        // RATA-RATA PERSENTASE VAR:
        // (masih disimpan sebagai varnt1, tapi di PDF/Excel kita hitung ulang pakai varnt & gji2)
        $persenVarExpr = 'AVG(CASE WHEN gji <> 0 THEN (varnt / gji) * 100 ELSE 0 END) AS varnt1';

        $selects = array_merge(
            ['pernr'],
            $dateRangeSel,
            $nonAggSelects,
            $sumSelects,
            [$persenVarExpr]
        );

        return ReportData::query()
            ->whereRaw('UPPER(TRIM(werks)) = ?', [$werks])
            ->whereIn('pernr', $pernrs)
            ->selectRaw(implode(', ', $selects))
            ->groupBy('pernr')
            ->orderBy('pernr')
            ->get();
    }

    /**
     * PARSER untuk key detail "pernr|begda" dari session.
     *
     * @param  array  $keys
     * @return \Illuminate\Support\Collection [ ['pernr' => ..., 'begda' => ...], ... ]
     */
    protected function parseDetailKeys(array $keys): Collection
    {
        return collect($keys)
            ->map(function ($key) {
                [$pernr, $begda] = array_pad(explode('|', (string) $key, 2), 2, null);

                $pernr = trim((string) $pernr);
                $begda = trim((string) $begda);

                if ($pernr === '' || $begda === '') {
                    return null;
                }

                return ['pernr' => $pernr, 'begda' => $begda];
            })
            ->filter()
            ->unique(fn($row) => $row['pernr'] . '|' . $row['begda'])
            ->values();
    }

    /**
     * Ambil DETAIL rows berdasarkan pasangan pernr+begda (multi user, multi tanggal).
     *
     * @param  array  $pairs  format: [ ['pernr' => '10000001', 'begda' => '20251101'], ... ]
     * @param  string $werks
     * @return \Illuminate\Support\Collection
     */
    protected function getDetailRows(array $pairs, string $werks): Collection
    {
        if (empty($pairs)) {
            return collect();
        }

        return ReportData::query()
            ->whereRaw('UPPER(TRIM(werks)) = ?', [$werks])
            ->where(function ($q) use ($pairs) {
                foreach ($pairs as $row) {
                    $q->orWhere(function ($qq) use ($row) {
                        $qq->where('pernr', $row['pernr'])
                            ->where('begda', $row['begda']);
                    });
                }
            })
            ->orderBy('pernr')
            ->orderBy('begda')
            ->get();
    }

    /**
     * Export PDF untuk NIK terpilih (SUMMARY).
     * Route: GET /report-data/{werks}/export-pdf
     */
    public function exportSelected(Request $request, string $werks)
    {
        $pernrs = (array) $request->session()->get('report_export.pernrs', []);
        $pernrs = array_values(array_filter(array_map('strval', $pernrs)));

        if (empty($pernrs)) {
            abort(404, 'Tidak ada Personal No. yang dipilih untuk di-export.');
        }

        $werks = strtoupper(trim($werks));

        $rows = $this->getSummaryRows($pernrs, $werks);

        if ($rows->isEmpty()) {
            abort(404, 'Data tidak ditemukan untuk pilihan tersebut.');
        }

        $request->session()->forget('report_export.pernrs');

        $pdf = Pdf::loadView('pdf.report-summary', [
            'rows'  => $rows,
            'werks' => $werks,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("report-data-{$werks}.pdf");
    }

    /**
     * Export Excel (.xlsx) untuk NIK terpilih (SUMMARY).
     * Route: GET /report-data/{werks}/export-excel
     */
    public function exportSelectedExcel(Request $request, string $werks)
    {
        $pernrs = (array) $request->session()->get('report_export.pernrs', []);
        $pernrs = array_values(array_filter(array_map('strval', $pernrs)));

        if (empty($pernrs)) {
            abort(404, 'Tidak ada Personal No. yang dipilih untuk di-export.');
        }

        $werks = strtoupper(trim($werks));

        $rows = $this->getSummaryRows($pernrs, $werks);

        if ($rows->isEmpty()) {
            abort(404, 'Data tidak ditemukan untuk pilihan tersebut.');
        }

        $request->session()->forget('report_export.pernrs');

        $filename = "report-data-{$werks}.xlsx";

        return Excel::download(new ReportSummaryExport($rows, $werks), $filename);
    }

    /**
     * EXPORT DETAIL PDF (multi user + multi tanggal).
     * Route: GET /report-data/{werks}/export-detail-pdf
     */
    public function exportDetailSelected(Request $request, string $werks)
    {
        $items = (array) $request->session()->get('report_export_detail.items', []);

        $pernrs = collect($items)
            ->map(fn($row) => trim((string) ($row['pernr'] ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($pernrs)) {
            abort(404, 'Tidak ada data detail yang dipilih untuk di-export.');
        }

        $werks = strtoupper(trim($werks));

        $start = Carbon::now()->startOfMonth()->format('Ymd');
        $end   = Carbon::now()->subDay()->format('Ymd');

        $rows = ReportData::query()
            ->whereRaw('UPPER(TRIM(werks)) = ?', [$werks])
            ->whereIn('pernr', $pernrs)
            ->whereBetween('begda', [$start, $end])
            ->orderBy('pernr')
            ->orderBy('begda')
            ->get();

        if ($rows->isEmpty()) {
            abort(404, 'Data detail tidak ditemukan untuk pilihan tersebut.');
        }

        $request->session()->forget('report_export_detail.items');

        $pdf = Pdf::loadView('pdf.report-detail', [
            'rows'  => $rows,
            'werks' => $werks,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("report-data-detail-{$werks}.pdf");
    }

    /**
     * EXPORT DETAIL Excel (.xlsx) (multi user + full range tanggal bulan berjalan).
     * Route: GET /report-data/{werks}/export-detail-excel
     */
    public function exportDetailSelectedExcel(Request $request, string $werks)
    {
        $items = (array) $request->session()->get('report_export_detail.items', []);

        $pernrs = collect($items)
            ->map(fn($row) => trim((string) ($row['pernr'] ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($pernrs)) {
            abort(404, 'Tidak ada data detail yang dipilih untuk di-export.');
        }

        $werks = strtoupper(trim($werks));

        $start = Carbon::now()->startOfMonth()->format('Ymd');
        $end   = Carbon::now()->subDay()->format('Ymd');

        $rows = ReportData::query()
            ->whereRaw('UPPER(TRIM(werks)) = ?', [$werks])
            ->whereIn('pernr', $pernrs)
            ->whereBetween('begda', [$start, $end])
            ->orderBy('pernr')
            ->orderBy('begda')
            ->get();

        if ($rows->isEmpty()) {
            abort(404, 'Data detail tidak ditemukan untuk pilihan tersebut.');
        }

        $request->session()->forget('report_export_detail.items');

        $filename = "report-data-detail-{$werks}.xlsx";

        return Excel::download(new ReportDetailExport($rows, $werks), $filename);
    }
}
