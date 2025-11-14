<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Livewire\ReportGenerator;
use App\Livewire\WcPersonList;   // <— tambahkan ini
use App\Models\ReportData;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $plants = \App\Models\ReportData::query()
        ->selectRaw("UPPER(TRIM(werks)) as werks, COUNT(DISTINCT pernr) as rows_count")
        ->whereNotNull('werks')
        ->whereRaw("TRIM(werks) <> ''")
        ->groupBy('werks')
        ->orderByRaw('CAST(werks AS UNSIGNED), werks')
        ->get();

    return view('dashboard', compact('plants'));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    // Laporan: wajib lewat klik plant
    Route::get('/report-data/{werks}', ReportGenerator::class)
        ->where('werks', '\d{3,4}')
        ->name('report-data');

    // WC Person (list & search all columns)
    Route::get('/wc-person', WcPersonList::class)->name('wc-person');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
