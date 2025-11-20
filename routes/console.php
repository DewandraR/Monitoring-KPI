<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
 |-----------------------------------------------------------------------
 | WC PERSON & YPPR058 (FULL RANGE) – JAM 01:00
 |-----------------------------------------------------------------------
 | Urutan penting:
 | 1) sync:wc_person jalan dulu
 | 2) setelah selesai, baru sync:yppr058
 | 
 | Karena keduanya didefinisikan berurutan di file ini dengan jam yang sama,
 | saat `php artisan schedule:run` dieksekusi, Laravel akan menjalankan
 | event schedule secara berurutan: wc_person dulu, lalu yppr058.
*/

// --- Jadwal untuk WC PERSON (YPPR079 / WC-person) ---
Schedule::command('sync:wc_person')
    ->dailyAt('01:00') // jam 1 subuh
    ->timezone('Asia/Jakarta')
    ->before(function () {
        echo now()->format('Y-m-d H:i:s') . ' Running ["artisan" sync:wc_person]' . PHP_EOL;
    })
    ->sendOutputTo(storage_path('logs/WC_PERSON.log'))
    ->withoutOverlapping();

// --- Jadwal untuk YPPR058 (mode default: kemarin → tanggal 1) ---
Schedule::command('sync:yppr058')
    ->dailyAt('01:00') // jam 1 subuh, setelah wc_person
    ->timezone('Asia/Jakarta')
    ->before(function () {
        echo now()->format('Y-m-d H:i:s') . ' Running ["artisan" sync:yppr058]' . PHP_EOL;
    })
    ->sendOutputTo(storage_path('logs/YPPR058.log'))
    ->withoutOverlapping();

/*
 |-----------------------------------------------------------------------
 | YPPR058 YESTERDAY ONLY – JAM 10:00 PAGI
 |-----------------------------------------------------------------------
 | Command: app:yppr058_yesterday
 | Ini yang menjalankan Python dengan argumen --yesterday.
*/

Schedule::command('app:yppr058_yesterday')
    ->dailyAt('10:00') // jam 10 pagi
    ->timezone('Asia/Jakarta')
    ->before(function () {
        echo now()->format('Y-m-d H:i:s') . ' Running ["artisan" app:yppr058_yesterday]' . PHP_EOL;
    })
    ->sendOutputTo(storage_path('logs/YPPR058_YESTERDAY.log'))
    ->withoutOverlapping();


// Command bawaan "inspire" (biarkan saja)
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
