<?php

namespace App\Http\Controllers;

use App\Exports\WcPersonExport;
use App\Models\WcPersonData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class WcPersonExportController extends Controller
{
    /**
     * Ambil data WC Person hanya untuk NIK yang disimpan di session
     * / atau dikirim lewat query string, TANPA duplikat pernr.
     */
    protected function getRows(Request $request)
    {
        // 1) Prioritas: session
        $pernrs = session()->get('wc_person_export.pernrs', []);

        // 2) Fallback: query string
        if (!is_array($pernrs) || empty($pernrs)) {
            $pernrs = $request->query('pernrs', []);
        }

        if (!is_array($pernrs)) {
            $pernrs = [];
        }

        // Rapikan & unik di level input
        $pernrs = array_values(array_unique(
            array_filter(array_map('strval', $pernrs))
        ));

        if (empty($pernrs)) {
            return collect();
        }

        // === PENTING: samakan dengan view → unik per NIK ===
        return WcPersonData::query()
            ->whereIn('pernr', $pernrs)
            ->orderByRaw('CAST(werks AS UNSIGNED), werks')
            ->orderBy('pernr')
            ->get()
            ->unique('pernr')   // buang duplikat pernr dari DB
            ->values();         // reset index collection
    }

    /**
     * Export PDF untuk NIK terpilih.
     * Route: GET /wc-person/export-pdf
     */
    public function exportPdf(Request $request)
    {
        $rows = $this->getRows($request);

        if ($rows->isEmpty()) {
            abort(404, 'Tidak ada NIK yang dipilih untuk di-export atau data tidak ditemukan.');
        }

        $q = (string) session()->get('wc_person_export.q', $request->query('q', ''));

        $pdf = Pdf::loadView('pdf.wc-person', [
            'rows' => $rows,
            'q'    => $q,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('wc-person.pdf');
    }

    /**
     * Export Excel (.xlsx) untuk NIK terpilih.
     * Route: GET /wc-person/export-excel
     */
    public function exportExcel(Request $request)
    {
        $rows = $this->getRows($request);

        if ($rows->isEmpty()) {
            abort(404, 'Tidak ada NIK yang dipilih untuk di-export atau data tidak ditemukan.');
        }

        $q = (string) session()->get('wc_person_export.q', $request->query('q', ''));

        return Excel::download(new WcPersonExport($rows, $q), 'wc-person.xlsx');
    }
}
