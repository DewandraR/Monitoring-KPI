<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KpiController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/get-nik-confirmasi', [KpiController::class, 'getConfirmedNik']);
});
