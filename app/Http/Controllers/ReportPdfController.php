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
use Illuminate\Support\Facades\DB;

class ReportPdfController extends Controller
{
    protected function getDateRangeForMonth(?string $monthFilter): array
    {
        $today = Carbon::today();
        $monthFilter = $monthFilter === 'prev' ? 'prev' : 'this';

        if ($monthFilter === 'prev') {
            // Bulan kemarin full
            $start = $today->copy()->subMonth()->startOfMonth();
            $end   = $today->copy()->subMonth()->endOfMonth();
        } else {
            // Bulan ini: 1 .. H-1 (kemarin),
            // kalau hari ini tgl 1 → fallback bulan kemarin full (sama seperti Livewire)
            if ($today->day === 1) {
                $start = $today->copy()->subMonth()->startOfMonth();
                $end   = $today->copy()->subMonth()->endOfMonth();
            } else {
                $start = $today->copy()->startOfMonth();
                $end   = $today->copy()->subDay();
            }
        }

        return [$start->format('Ymd'), $end->format('Ymd')];
    }

    /**
     * Ambil summary rows (sama persis dengan query di ReportGenerator)
     *
     * @param  array  $pernrs
     * @param  string $werks
     * @return \Illuminate\Support\Collection
     */
    protected function getSummaryRows(array $pernrs, string $werks, ?array $dateRange = null): Collection
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

        $query = ReportData::query()
            ->whereRaw('UPPER(TRIM(werks)) = ?', [$werks])
            ->whereIn('pernr', $pernrs);

        // <<< FILTER TANGGAL SESUAI BULAN >>>
        if ($dateRange) {
            [$start, $end] = $dateRange;
            $query->whereBetween('begda', [$start, $end]);
        }

        // === De-duplicate per date (pernr + begda) ===
        // sub Selects untuk picking one record per date
        $subSelects = array_merge(
            ['pernr', 'begda'],
            array_map(fn($col) => "MAX(`$col`) as `$col`" , array_merge($aggregateColumns, ['cname', 'arbpl', 'desc', 'arbpl2', 'werks', 'role', 'devisi', 'shift']))
        );

        $subquery = DB::table(DB::raw("({$query->toSql()}) as filtered"))
            ->mergeBindings($query->getQuery())
            ->selectRaw(implode(', ', $subSelects))
            ->groupBy('pernr', 'begda');

        return DB::table(DB::raw("({$subquery->toSql()}) as deduplicated"))
            ->mergeBindings($subquery)
            ->selectRaw(implode(', ', $selects))
            ->groupBy('pernr')
            ->orderByRaw("COALESCE(NULLIF(TRIM(MAX(`arbpl`)), ''), 'ZZZZ') ASC") // WC Personal dulu (kosong belakangan)
            ->orderByRaw("CAST(`pernr` AS UNSIGNED) ASC")                       // lalu NIK
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
            ->orderByRaw("COALESCE(NULLIF(TRIM(`arbpl`), ''), 'ZZZZ') ASC")
            ->orderByRaw("CAST(`pernr` AS UNSIGNED) ASC")
            ->orderBy('begda', 'asc')
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

        $monthFilter = (string) $request->session()->get('report_export.month_filter', 'this');
        $dateRange   = $this->getDateRangeForMonth($monthFilter);

        $rows = $this->getSummaryRows($pernrs, $werks, $dateRange);

        if ($rows->isEmpty()) {
            abort(404, 'Data tidak ditemukan untuk pilihan tersebut.');
        }

        $request->session()->forget(['report_export.pernrs', 'report_export.month_filter']);

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

        $monthFilter = (string) $request->session()->get('report_export.month_filter', 'this');
        $dateRange   = $this->getDateRangeForMonth($monthFilter);

        $rows = $this->getSummaryRows($pernrs, $werks, $dateRange);

        if ($rows->isEmpty()) {
            abort(404, 'Data tidak ditemukan untuk pilihan tersebut.');
        }

        $request->session()->forget(['report_export.pernrs', 'report_export.month_filter']);

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

        // Ambil list pernr unik dari session
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

        $monthFilter = (string) $request->session()->get('report_export_detail.month_filter', 'this');
        // ✅ range tanggal yang benar (handle kasus tanggal 1 + filter bulan)
        [$start, $end] = $this->getDetailDateRange($monthFilter);

        $rows = ReportData::query()
            ->whereRaw('UPPER(TRIM(werks)) = ?', [$werks])
            ->whereIn('pernr', $pernrs)
            ->whereBetween('begda', [$start, $end])
            ->orderByRaw("COALESCE(NULLIF(TRIM(`arbpl`), ''), 'ZZZZ') ASC") // WC dulu
            ->orderByRaw("CAST(`pernr` AS UNSIGNED) ASC")                  // lalu NIK
            ->orderBy('begda', 'asc')                                      // lalu tanggal
            ->get();

        if ($rows->isEmpty()) {
            abort(404, 'Data detail tidak ditemukan untuk pilihan tersebut.');
        }

        // bersihkan session setelah dipakai
        $request->session()->forget(['report_export_detail.items', 'report_export_detail.month_filter']);

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

        $monthFilter = (string) $request->session()->get('report_export_detail.month_filter', 'this');
        // ✅ pakai helper yang sama
        [$start, $end] = $this->getDetailDateRange($monthFilter);

        $rows = ReportData::query()
            ->whereRaw('UPPER(TRIM(werks)) = ?', [$werks])
            ->whereIn('pernr', $pernrs)
            ->whereBetween('begda', [$start, $end])
            ->orderByRaw("COALESCE(NULLIF(TRIM(`arbpl`), ''), 'ZZZZ') ASC")
            ->orderByRaw("CAST(`pernr` AS UNSIGNED) ASC")
            ->orderBy('begda', 'asc')
            ->get();

        if ($rows->isEmpty()) {
            abort(404, 'Data detail tidak ditemukan untuk pilihan tersebut.');
        }

        $request->session()->forget(['report_export_detail.items', 'report_export_detail.month_filter']);

        $filename = "report-data-detail-{$werks}.xlsx";

        return Excel::download(new ReportDetailExport($rows, $werks), $filename);
    }

    protected function getDetailDateRange(?string $monthFilter = null): array
    {
        return $this->getDateRangeForMonth($monthFilter);
    }
}
