<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
 |-----------------------------------------------------------------------
 | WC PERSON & YPPR058 (FULL RANGE) – JAM 01:00 (HARIAN)
 |-----------------------------------------------------------------------
 | Urutan: sync:wc_person jalan dulu, baru sync:yppr058.
 */

// 1. Jadwal untuk WC PERSON
Schedule::command('sync:wc_person')
    ->dailyAt('01:00') // Setiap hari jam 01:00
    ->timezone('Asia/Jakarta')
    ->before(function () {
        echo now()->format('Y-m-d H:i:s') . ' Running ["artisan" sync:wc_person]' . PHP_EOL;
    })
    ->sendOutputTo(storage_path('logs/WC_PERSON.log'))
    ->withoutOverlapping();

// 2. Jadwal untuk YPPR058
Schedule::command('sync:yppr058')
    ->dailyAt('01:00') // Setiap hari jam 01:00 (antri setelah wc_person)
    ->timezone('Asia/Jakarta')
    ->before(function () {
        echo now()->format('Y-m-d H:i:s') . ' Running ["artisan" sync:yppr058]' . PHP_EOL;
    })
    ->sendOutputTo(storage_path('logs/YPPR058.log'))
    ->withoutOverlapping();

/*
 |-----------------------------------------------------------------------
 | WEEKLY OWNER REPORT – JAM 07:00 (KHUSUS SENIN)
 |-----------------------------------------------------------------------
 | Mengirim email PDF report Korlap (Data Senin-Minggu lalu) ke Owner.
 */

Schedule::command('report:send-weekly-owner')
    ->weeklyOn(1, '07:00') // 1 = Senin, Jam 07:00 Pagi
    ->timezone('Asia/Jakarta')
    ->before(function () {
        echo now()->format('Y-m-d H:i:s') . ' Running ["artisan" report:send-weekly-owner]' . PHP_EOL;
    })
    ->sendOutputTo(storage_path('logs/WEEKLY_OWNER_REPORT.log')) // Log khusus email
    ->withoutOverlapping();

/*
 |-----------------------------------------------------------------------
 | YPPR058 YESTERDAY ONLY – JAM 10:00 PAGI (HARIAN)
 |-----------------------------------------------------------------------
 | Menjalankan Python dengan argumen --yesterday.
 */

Schedule::command('app:yppr058_yesterday')
    ->dailyAt('10:00') // Setiap hari jam 10:00
    ->timezone('Asia/Jakarta')
    ->before(function () {
        echo now()->format('Y-m-d H:i:s') . ' Running ["artisan" app:yppr058_yesterday]' . PHP_EOL;
    })
    ->sendOutputTo(storage_path('logs/YPPR058_YESTERDAY.log'))
    ->withoutOverlapping();


// --- Command Bawaan ---
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');