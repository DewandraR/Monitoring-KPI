<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportPdfController;
use Illuminate\Support\Facades\Route;
use App\Livewire\ReportGenerator;
use App\Livewire\WcPersonList;
use App\Http\Controllers\WcPersonExportController;
use Illuminate\Support\Facades\DB;
use App\Livewire\WiDailyReport;
use App\Models\DailyTimeWi;
use App\Http\Controllers\WiReportPdfController;
use App\Models\ReportData;


// ==== ROOT: langsung ke halaman login (tidak pakai welcome lagi) ====
Route::redirect('/', '/login');

// ==== Semua halaman utama wajib login + verified ====
Route::middleware(['auth', 'verified', 'data.scope'])->group(function () {

    // Dashboard
    Route::get('/dashboard', function () {

        // ===== ambil scope dari middleware =====
        $scopeAll   = (bool) request()->attributes->get('data_scope_all', false);
        $scopeDev   = (array) request()->attributes->get('data_scope_devisi', []);
        $scopeArbpl = (array) request()->attributes->get('data_scope_arbpl', []);

        // normalisasi supaya aman dari spasi/case
        $scopeDevUpper = array_values(array_unique(array_filter(array_map(
            fn ($v) => strtoupper(trim((string) $v)),
            $scopeDev
        ))));

        $scopeArbplUpper = array_values(array_unique(array_filter(array_map(
            fn ($v) => strtoupper(trim((string) $v)),
            $scopeArbpl
        ))));

        $query = \App\Models\ReportData::query()
            ->whereNotNull('werks')
            ->whereRaw("TRIM(werks) <> ''");

        // ===== apply scope (DEVISI / ARBPL) =====
        if (!$scopeAll) {
            if (empty($scopeDevUpper) && empty($scopeArbplUpper)) {
                // tidak punya akses apa-apa
                $query->whereRaw('1=0');
            } else {
                $query->where(function ($q) use ($scopeDevUpper, $scopeArbplUpper) {

                    if (!empty($scopeDevUpper)) {
                        $q->orWhereIn(DB::raw('UPPER(TRIM(devisi))'), $scopeDevUpper);
                    }

                    if (!empty($scopeArbplUpper)) {
                        $q->orWhereIn(DB::raw('UPPER(TRIM(arbpl))'), $scopeArbplUpper)
                        ->orWhereIn(DB::raw('UPPER(TRIM(arbpl2))'), $scopeArbplUpper);
                    }
                });
            }
        }

        // ===== summary plant hanya dari data yg boleh dilihat user =====
        $plants = $query
            ->selectRaw("UPPER(TRIM(werks)) as werks, COUNT(DISTINCT pernr) as rows_count")
            ->groupBy(DB::raw("UPPER(TRIM(werks))"))
            ->orderByRaw("MIN(CAST(TRIM(werks) AS UNSIGNED)) ASC")
            ->orderByRaw("UPPER(TRIM(werks)) ASC")
            ->get();

         $wiSub = DailyTimeWi::query()
            ->whereNotNull('kode_laravel')
            ->whereRaw("TRIM(kode_laravel) <> ''")
            ->selectRaw("
                CONCAT(LEFT(TRIM(kode_laravel),1),'000') as plant,
                TRIM(nik) as nik
            ")
            ->toBase();

        $wiQuery = DB::query()->fromSub($wiSub, 'x');

        if (!$scopeAll) {
            if (empty($scopeDevUpper) && empty($scopeArbplUpper)) {
                // tidak punya akses apa-apa
                $wiQuery->whereRaw('1=0');
            } else {

                // subquery dari yppr058_data (pakai Eloquent biar global scope ReportData ikut)
                $rdSub = ReportData::query()
                    ->selectRaw("
                        TRIM(pernr) as pernr,
                        TRIM(werks) as werks,
                        devisi,
                        arbpl,
                        arbpl2
                    ")
                    ->toBase();

                $wiQuery->whereExists(function ($sq) use ($rdSub, $scopeDevUpper, $scopeArbplUpper) {
                    $sq->selectRaw('1')
                        ->fromSub($rdSub, 'y')
                        // match NIK
                        ->whereColumn('y.pernr', 'x.nik')
                        // match plant (werks) supaya plant 1000/2000 tidak ikut kalau scope-nya cuma plant 3000
                        ->whereColumn('y.werks', 'x.plant')
                        // apply scope devisi/arbpl
                        ->where(function ($q) use ($scopeDevUpper, $scopeArbplUpper) {

                            if (!empty($scopeDevUpper)) {
                                $q->orWhereIn(DB::raw('UPPER(TRIM(y.devisi))'), $scopeDevUpper);
                            }

                            if (!empty($scopeArbplUpper)) {
                                $q->orWhereIn(DB::raw('UPPER(TRIM(y.arbpl))'), $scopeArbplUpper)
                                ->orWhereIn(DB::raw('UPPER(TRIM(y.arbpl2))'), $scopeArbplUpper);
                            }
                        });
                });
            }
        }

        // hasil final untuk cards WI
        $wiPlants = $wiQuery
            ->selectRaw("x.plant, COUNT(DISTINCT x.nik) as rows_count")
            ->groupBy('x.plant')
            ->orderByRaw("CAST(x.plant AS UNSIGNED) ASC")
            ->get();

        return view('dashboard', compact('plants', 'wiPlants'));
    })->name('dashboard');


    // Laporan: wajib lewat klik plant
    Route::get('/report-data/{werks}', ReportGenerator::class)
        ->where('werks', '\d{3,4}')
        ->name('report-data');

    // ====== EXPORT SUMMARY ======

    // Export PDF ringkasan (row yang dicentang)
    Route::get('/report-data/{werks}/export-pdf', [ReportPdfController::class, 'exportSelected'])
        ->where('werks', '\d{3,4}')
        ->name('report-data.export-pdf');

    // Export Excel ringkasan
    Route::get('/report-data/{werks}/export-excel', [ReportPdfController::class, 'exportSelectedExcel'])
        ->where('werks', '\d{3,4}')
        ->name('report-data.export-excel');

    // ====== EXPORT DETAIL (MULTI USER + MULTI TANGGAL) ======

    // Detail PDF
    Route::get('/report-data/{werks}/export-detail-pdf', [ReportPdfController::class, 'exportDetailSelected'])
        ->where('werks', '\d{3,4}')
        ->name('report-data.export-detail-pdf');

    // Detail Excel
    Route::get('/report-data/{werks}/export-detail-excel', [ReportPdfController::class, 'exportDetailSelectedExcel'])
        ->where('werks', '\d{3,4}')
        ->name('report-data.export-detail-excel');

    Route::get('/wi-daily-report/{plant}', WiDailyReport::class)
        ->where('plant', '\d{4}')
        ->name('wi-daily-report');

    Route::get('/wi-daily/{plant}/export-summary-pdf', [WiReportPdfController::class, 'exportSummaryPdf'])
        ->name('wi.export.summary.pdf');

    Route::get('/wi-daily/{plant}/export-detail-pdf', [WiReportPdfController::class, 'exportDetailPdf'])
        ->name('wi.export.detail.pdf');

    Route::get('/wi-daily/{plant}/export-summary-excel', [WiReportPdfController::class, 'exportSummaryExcel'])
        ->name('wi.export.summary.excel');

    Route::get('/wi-daily/{plant}/export-detail-excel', [WiReportPdfController::class, 'exportDetailExcel'])
        ->name('wi.export.detail.excel');

    // WC Person (list & search all columns)
    Route::get('/wc-person', WcPersonList::class)->name('wc-person');

    Route::get('/wc-person/export-pdf', [WcPersonExportController::class, 'exportPdf'])
        ->name('wc-person.export-pdf');

    Route::get('/wc-person/export-excel', [WcPersonExportController::class, 'exportExcel'])
        ->name('wc-person.export-excel');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
