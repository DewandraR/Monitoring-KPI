<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportPdfController;
use Illuminate\Support\Facades\Route;
use App\Livewire\ReportGenerator;
use App\Livewire\WcPersonList;
use App\Http\Controllers\WcPersonExportController;

// ==== ROOT: langsung ke halaman login (tidak pakai welcome lagi) ====
Route::redirect('/', '/login');

// ==== Semua halaman utama wajib login + verified ====
Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', function () {
        $plants = \App\Models\ReportData::query()
            ->selectRaw("UPPER(TRIM(werks)) as werks, COUNT(DISTINCT pernr) as rows_count")
            ->whereNotNull('werks')
            ->whereRaw("TRIM(werks) <> ''")
            ->groupBy('werks')
            ->orderByRaw('CAST(werks AS UNSIGNED), werks')
            ->get();

        return view('dashboard', compact('plants'));
    })->name('dashboard');

    // Laporan: wajib lewat klik plant
    Route::get('/report-data/{werks}', ReportGenerator::class)
        ->where('werks', '\d{3,4}')
        ->name('report-data');

    // Export PDF ringkasan (row yang dicentang)
    Route::get('/report-data/{werks}/export-pdf', [ReportPdfController::class, 'exportSelected'])
        ->where('werks', '\d{3,4}')
        ->name('report-data.export-pdf');

    Route::get('/report-data/{werks}/export-excel', [ReportPdfController::class, 'exportSelectedExcel'])
        ->where('werks', '\d{3,4}')
        ->name('report-data.export-excel');

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
