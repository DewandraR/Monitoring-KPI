<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KpiController;

Route::middleware(['auth:sanctum'])->group(function () {
    // optional: GET lama tetap jalan
    Route::get('/get-nik-confirmasi', [KpiController::class, 'getConfirmedNik']);

    // 🔥 baru: POST dengan filter kode_laravel
    Route::post('/get-nik-confirmasi', [KpiController::class, 'getConfirmedNik']);
});