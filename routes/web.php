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

        $today = \Carbon\Carbon::today();
        
        // Cek session untuk filter bulan (default 'this')
        $monthFilter = session('wi_daily.month_filter', session('yppr058.month_filter', 'this'));
        
        if ($monthFilter === 'prev') {
            $start = $today->copy()->subMonth()->startOfMonth();
            $end   = $today->copy()->subMonth()->endOfMonth();
        } else {
            if ($today->day === 1) {
                $start = $today->copy()->subMonth()->startOfMonth();
                $end   = $today->copy()->subMonth()->endOfMonth();
            } else {
                $start = $today->copy()->startOfMonth();
                $end   = $today->copy()->subDay();
            }
        }
        
        // Khusus untuk WI Daily Report cutoff dataVisibleFrom '2025-12-01'
        $vfRaw = '2025-12-01';
        $vf = \Carbon\Carbon::parse($vfRaw)->startOfDay();
        if ($vf->lte($end) && $start->lt($vf)) {
            $start = $vf->copy();
        }

        $begdaStart = $start->format('Ymd');
        $begdaEnd   = $end->format('Ymd');

        $baseQuery = \App\Models\ReportData::query()
            ->whereNotNull('werks')
            ->whereRaw("TRIM(werks) <> ''")
            ->whereBetween('begda', [$begdaStart, $begdaEnd]);

        // ===== apply scope (DEVISI / ARBPL) =====
        if (!$scopeAll) {
            if (empty($scopeDevUpper) && empty($scopeArbplUpper)) {
                // tidak punya akses apa-apa
                $baseQuery->whereRaw('1=0');
            } else {
                $baseQuery->where(function ($q) use ($scopeDevUpper, $scopeArbplUpper) {

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
        $plants = (clone $baseQuery)
            ->selectRaw("UPPER(TRIM(werks)) as werks, COUNT(DISTINCT pernr) as rows_count")
            ->groupBy(DB::raw("UPPER(TRIM(werks))"))
            ->orderByRaw("MIN(CAST(TRIM(werks) AS UNSIGNED)) ASC")
            ->orderByRaw("UPPER(TRIM(werks)) ASC")
            ->get();

        // ======================
        // WI DAILY (GROUPING MENU)
        // ======================
        $wiMode = session('wi_daily.wi_mode', 'with');
        if ($wiMode === 'has')  $wiMode = 'with';
        if ($wiMode === 'none') $wiMode = 'without';
        $wiMode = in_array($wiMode, ['all', 'with', 'without'], true) ? $wiMode : 'all';

        $wiPlantsQuery = (clone $baseQuery)
            ->whereRaw("UPPER(TRIM(role)) = 'INDUK'")
            ->selectRaw("
                CASE
                    WHEN TRIM(werks) IN ('1001','1002','1003','1015') THEN '1001'
                    WHEN LEFT(TRIM(werks),1)='1' THEN '1000'
                    ELSE CONCAT(LEFT(TRIM(werks),1),'000')
                END as plant,
                pernr
            ");

        if ($wiMode === 'with' || $wiMode === 'without') {
            $wiTimeSubQ = \App\Models\DailyTimeWi::query()
                ->selectRaw("
                    nik,
                    CASE
                        WHEN LEFT(TRIM(kode_laravel),4) IN ('1001','1002','1003','1015') THEN '1001'
                        WHEN LEFT(TRIM(kode_laravel),1) = '1' THEN '1000'
                        ELSE CONCAT(LEFT(TRIM(kode_laravel),1),'000')
                    END as plant,
                    sum(total_time_wi) as time_wi
                ")
                ->whereNotNull('kode_laravel')
                ->whereRaw("TRIM(kode_laravel) <> ''")
                ->whereRaw("TRIM(kode_laravel) REGEXP '^[0-9]{4}'")
                ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
                ->where('tanggal', '>=', $vf->toDateString()) // matching cutoff from WiDailyReport logic
                ->groupByRaw("
                    nik,
                    CASE
                        WHEN LEFT(TRIM(kode_laravel),4) IN ('1001','1002','1003','1015') THEN '1001'
                        WHEN LEFT(TRIM(kode_laravel),1) = '1' THEN '1000'
                        ELSE CONCAT(LEFT(TRIM(kode_laravel),1),'000')
                    END
                ");

            // Apply join
            $wiPlantsQuery = \Illuminate\Support\Facades\DB::query()
                ->fromSub($wiPlantsQuery, 'qm_base') // use simply the 'plant' and 'pernr' inner query without any aggregate functions
                ->leftJoinSub($wiTimeSubQ, 'wi', function ($join) {
                    $join->on('wi.nik', '=', 'qm_base.pernr')
                         ->on('wi.plant', '=', 'qm_base.plant');
                })
                ->selectRaw("qm_base.plant, COUNT(DISTINCT qm_base.pernr) as rows_count");
                
            if ($wiMode === 'with') {
                $wiPlantsQuery->where('wi.time_wi', '>', 0);
            } else {
                $wiPlantsQuery->where(function ($q) {
                    $q->whereNull('wi.time_wi')
                      ->orWhere('wi.time_wi', '=', 0);
                });
            }
        } else {
             // fall-back if wiMode is all: outer count aggregate
             $wiPlantsQuery = \Illuminate\Support\Facades\DB::query()
                ->fromSub($wiPlantsQuery, 'qm_base')
                ->selectRaw("qm_base.plant, COUNT(DISTINCT qm_base.pernr) as rows_count");
        }

        $wiPlantsRaw = $wiPlantsQuery
            ->groupBy('qm_base.plant')
            ->orderByRaw("CAST(qm_base.plant AS UNSIGNED) ASC")
            ->get();

        // paksa selalu ada 4 card (biar desktop rapi & ga bolong)
        $wiPlants = collect(['1000','1001','2000','3000'])->map(function ($plant) use ($wiPlantsRaw) {
    $row = $wiPlantsRaw->firstWhere('plant', $plant);
    return (object) [
        'plant' => $plant,
        'rows_count' => (int) ($row->rows_count ?? 0),
    ];
})
->filter(fn($x) => (int)$x->rows_count > 0)
->values();


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

    Route::get('/wi/export/korlap/pdf/{plant}', [WiReportPdfController::class, 'exportKorlapPdf'])
        ->name('wi.export.korlap.pdf');

    Route::get('/wi/export/korlap/excel/{plant}', [WiReportPdfController::class, 'exportKorlapExcel'])
        ->name('wi.export.korlap.excel');

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
