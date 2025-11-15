<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// --- Jadwal untuk YPPR079 (Sudah Ada & Synchronous) ---
Schedule::command('Sync:wc_person')
    ->dailyAt('01.00')
    ->timezone('Asia/Jakarta')
    ->before(function () {
        echo now()->format('Y-m-d H:i:s') . ' Running ["artisan" Sync:wc_person]' . PHP_EOL;
    })
    ->sendOutputTo(storage_path('logs/WC_PERSON.log'))
    ->withoutOverlapping();

// ðŸŒŸ --- Jadwal YSDR048 (Diubah menjadi Synchronous) ---
Schedule::command('sync:yppr058')
    ->dailyAt('01.00')
    ->timezone('Asia/Jakarta')
    ->before(function () {
        echo now()->format('Y-m-d H:i:s') . ' Running ["sync:yppr058]' . PHP_EOL;
    })
    ->sendOutputTo(storage_path('logs/YPPR058.log'))
    ->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
