<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// TAMBAH INI:
use App\Http\Middleware\DataScopeMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        /**
         * PENTING:
         * Kita append middleware ini ke group "web"
         * agar request biasa + request Livewire juga kena filter scope.
         */
        $middleware->appendToGroup('web', DataScopeMiddleware::class);

        /**
         * OPTIONAL:
         * Kalau mau bisa dipakai sebagai route middleware (alias).
         * (Tidak wajib kalau sudah appendToGroup web)
         */
        $middleware->alias([
            'data.scope' => DataScopeMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
