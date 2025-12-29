<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DataScopeMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if (!$user) {
            return $next($request);
        }

        $all = (bool) ($user->scope_all ?? false);

        $devisi = (array) ($user->scope_devisi ?? []);
        $arbpl  = (array) ($user->scope_arbpl ?? []);

        // normalisasi
        $devisi = array_values(array_unique(array_filter(array_map(
            fn ($v) => strtoupper(trim((string) $v)),
            $devisi
        ))));

        $arbpl = array_values(array_unique(array_filter(array_map(
            fn ($v) => strtoupper(trim((string) $v)),
            $arbpl
        ))));

        // simpan ke request attributes agar bisa dipakai di routes/Livewire
        $request->attributes->set('data_scope_all', $all);
        $request->attributes->set('data_scope_devisi', $devisi);
        $request->attributes->set('data_scope_arbpl', $arbpl);

        return $next($request);
    }
}
