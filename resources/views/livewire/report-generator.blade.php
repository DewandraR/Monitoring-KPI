@php
    use Carbon\Carbon;
    use Illuminate\Support\Str;

    // Header untuk tabel ringkasan (summary)
    $headersSummary = [
        'No',
        'Personal No.',
        'Rentang Tanggal',
        'Nama',
        'WC Personal',
        'WC Konfirmasi', // <<< BARU
        'DESC WC',
        'Role',
        'Devisi',
        'Menit Hadir', // total_jam
        'Menit Conf', // mint2
        'Menit Inspect', // mintu
        'Detik Inspect', // mintu2
        'Detik Konfirmasi', // mintu3
        'Upah Hadir', // gji
        'Upah Inspect', // gji2
        'Var Upah', // varnt
        'Persentase Var', // varnt1
    ];

    // Header untuk modal detail
    $headersDetail = [
        'No',
        'Personal No.',
        'Tanggal',
        'Nama',
        'WC Personal',
        'WC Konfirmasi', // <<< BARU
        'DESC WC',
        'Role',
        'Devisi',
        'Shift', // <<< TAMBAH INI
        'Menit Hadir',
        'Menit Conf',
        'Menit Inspect',
        'Detik Inspect',
        'Detik Konfirmasi',
        'Upah Hadir', // gji
        'Upah Inspect', // gji2
        'Var Upah', // varnt
    ];

    // Hitung berapa NIK yang terseleksi di SUMMARY (untuk Export Report)
    $selectedCount = is_iterable($selectedPernrs ?? []) ? count($selectedPernrs) : 0;

    // =====================================================================
    // Hitung berapa "hari" yang akan di-export DETAIL
    //  - NIK yang dicentang di summary  -> dihitung full range 1..H-1 (per NIK)
    //  - Baris tanggal di modal         -> dihitung per baris (per tanggal),
    //    tapi TIDAK dobel untuk NIK yang sudah ikut summary.
    // =====================================================================

    $detailSelectedCount = 0;

    try {
        // 1. Map: pernr => jumlah hari (berdasarkan min_begda & max_begda)
        $pernrDaysMap = collect($reportData ?? [])->mapWithKeys(function ($row) {
            try {
                $start = Carbon::createFromFormat('Ymd', (string) $row->min_begda);
                $end = Carbon::createFromFormat('Ymd', (string) $row->max_begda);

                $days = $end->lt($start) ? 0 : $start->diffInDays($end) + 1;
            } catch (\Throwable $e) {
                $days = 0;
            }

            return [(string) $row->pernr => $days];
        });

        // 2. NIK yang dicentang di SUMMARY
        $summaryPernrs = collect($selectedPernrs ?? [])
            ->map(fn($p) => trim((string) $p))
            ->filter()
            ->unique();

        // Total hari dari summary (tiap NIK full range min_begda..max_begda)
        $summaryTotalDays = $summaryPernrs->map(fn($p) => $pernrDaysMap->get($p, 0))->sum();

        // 3. Baris DETAIL yang dicentang di modal: pernr|begda
        $detailPairs = collect($selectedDetailKeys ?? [])
            ->map(function ($key) {
                if (!is_string($key) || $key === '') {
                    return null;
                }

                [$pernr, $begda] = array_pad(explode('|', (string) $key, 2), 2, '');
                $pernr = trim((string) $pernr);
                $begda = trim((string) $begda);

                if ($pernr === '' || $begda === '') {
                    return null;
                }

                return [
                    'pernr' => $pernr,
                    'begda' => $begda,
                    'key' => $pernr . '|' . $begda,
                ];
            })
            ->filter()
            ->unique('key'); // unik per (pernr, begda)

        // Hitung jumlah tanggal per NIK dari modal
        $detailByPernr = $detailPairs->groupBy('pernr')->map(function ($items) {
            return collect($items)->pluck('begda')->unique()->count();
        });

        // 4. Detail ONLY = NIK yang tidak dicentang di summary
        $detailOnlyTotal = $detailByPernr->except($summaryPernrs->all())->sum();

        // 5. Total untuk badge
        $detailSelectedCount = $summaryTotalDays + $detailOnlyTotal;
    } catch (\Throwable $e) {
        $detailSelectedCount = 0;
    }
@endphp

{{-- ROOT ELEMENT --}}
<div id="yppr058-root" data-month-filter="{{ $monthFilter ?? 'this' }}" data-range-start="{{ $rangeStart }}"
    data-range-end="{{ $rangeEnd }}"
    class="bg-white overflow-hidden shadow-[0_25px_50px_-12px_rgba(0,0,0,0.15)] sm:rounded-xl p-8 border border-emerald-50 relative">
    {{-- DEKORASI LATAR BELAKANG --}}
    <div
        class="absolute top-0 right-0 -mt-4 -mr-4 w-64 h-64 bg-gradient-to-br from-emerald-100/40 to-transparent rounded-full blur-3xl pointer-events-none">
    </div>

    {{-- BAGIAN 1: HEADER MEWAH & TOMBOL EXPORT (FULLY RESPONSIVE) --}}
    <div class="flex flex-col gap-6 mb-8 relative z-10">

        {{-- BARIS 1: JUDUL HALAMAN --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            {{-- JUDUL --}}
            <div class="space-y-2">
                <h3
                    class="text-3xl lg:text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-emerald-800 to-teal-600 tracking-tight drop-shadow-sm animate-fade-in">
                    {{ __('Report Data') }}
                    <span class="text-emerald-900/20 font-light">—</span>
                    <span class="text-2xl lg:text-3xl">yppr058_data</span>
                </h3>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-slate-500">Plant terpilih:</span>
                    <span class="font-bold text-emerald-700 bg-emerald-50 px-3 py-1 rounded-lg shadow-sm">
                        {{ $werks ?? request()->route('werks') }}
                    </span>
                    <span class="text-xs text-gray-400 italic">
                        (Ringkasan per Personal No.)
                    </span>
                </div>
            </div>

            {{-- TOGGLE BULAN GLOBAL (kanan judul) --}}
            {{-- TOGGLE BULAN GLOBAL (kanan judul) - SUPER FANCY VERSION --}}
            <div class="flex items-center gap-3 group/toggle">
                <span
                    class="hidden sm:inline text-[11px] uppercase tracking-widest text-emerald-900/70 font-black flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600 animate-pulse" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Periode
                </span>

                <div
                    class="relative inline-flex rounded-2xl bg-gradient-to-r from-emerald-100 via-teal-100 to-emerald-100 p-1.5 shadow-lg ring-2 ring-emerald-200/50 backdrop-blur-sm">
                    {{-- Background Slider Animasi --}}
                    <div class="absolute inset-1.5 rounded-xl bg-gradient-to-r from-white via-emerald-50 to-white shadow-inner transition-all duration-500 ease-out {{ $monthFilter === 'this' ? 'translate-x-0' : 'translate-x-[calc(100%-4px)]' }}"
                        style="width: calc(50% - 2px);"></div>

                    {{-- Tombol Bulan Ini --}}
                    <button type="button" wire:click="setMonthFilter('this')"
                        class="relative z-10 px-5 py-2.5 rounded-xl text-sm font-bold transition-all duration-300 ease-out flex items-center gap-2 group/btn
                        {{ $monthFilter === 'this'
                            ? 'text-emerald-700 scale-105'
                            : 'text-emerald-600/60 hover:text-emerald-700 hover:scale-105' }}">

                        {{-- Icon Calendar dengan animasi --}}
                        <svg class="w-4 h-4 transition-all duration-300 {{ $monthFilter === 'this' ? 'rotate-0 scale-110' : 'rotate-12 scale-90 opacity-70' }}"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>

                        <span class="whitespace-nowrap">Bulan Ini</span>

                        {{-- Sparkle Effect saat aktif --}}
                        @if ($monthFilter === 'this')
                            <span class="absolute -top-1 -right-1 flex h-3 w-3">
                                <span
                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span
                                    class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500 shadow-lg shadow-emerald-500/50"></span>
                            </span>
                        @endif
                    </button>

                    {{-- Tombol Bulan Kemarin --}}
                    <button type="button" wire:click="setMonthFilter('prev')"
                        class="relative z-10 px-5 py-2.5 rounded-xl text-sm font-bold transition-all duration-300 ease-out flex items-center gap-2 group/btn
                        {{ $monthFilter === 'prev' ? 'text-teal-700 scale-105' : 'text-teal-600/60 hover:text-teal-700 hover:scale-105' }}">

                        {{-- Icon History dengan animasi --}}
                        <svg class="w-4 h-4 transition-all duration-300 {{ $monthFilter === 'prev' ? 'rotate-0 scale-110' : '-rotate-12 scale-90 opacity-70' }}"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>

                        <span class="whitespace-nowrap">Bulan Lalu</span>

                        {{-- Sparkle Effect saat aktif --}}
                        @if ($monthFilter === 'prev')
                            <span class="absolute -top-1 -right-1 flex h-3 w-3">
                                <span
                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-teal-400 opacity-75"></span>
                                <span
                                    class="relative inline-flex rounded-full h-3 w-3 bg-teal-500 shadow-lg shadow-teal-500/50"></span>
                            </span>
                        @endif
                    </button>

                    {{-- Decorative Glow Effect --}}
                    <div
                        class="absolute -inset-1 bg-gradient-to-r from-emerald-400/20 via-teal-400/20 to-emerald-400/20 rounded-2xl blur-lg opacity-0 group-hover/toggle:opacity-100 transition-opacity duration-500 -z-10">
                    </div>
                </div>
            </div>
        </div>

        {{-- BARIS 2: SEMUA TOMBOL AKSI (GRID RESPONSIVE) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3">

            {{-- TOMBOL 1: REFRESH (DROPDOWN) - GREEN HERO & COMPACT VERSION --}}
            <div class="relative inline-block w-full group/main h-full">

                {{-- 1. GLOW EFFECT (Nuansa Hijau Neon) --}}
                <div
                    class="absolute -inset-0.5 bg-gradient-to-r from-emerald-400 to-lime-400 rounded-xl blur opacity-20 group-hover/main:opacity-60 transition duration-500">
                </div>

                {{-- 2. TOMBOL UTAMA (Deep Green & Tinggi Sinkron) --}}
                <button id="btn-refresh-dropdown" type="button" onclick="toggleRefreshMenu()"
                    class="relative w-full h-full overflow-hidden rounded-xl 
                           bg-gradient-to-br from-emerald-900 via-teal-900 to-emerald-950
                           px-4 py-2.5 text-white shadow-xl ring-1 ring-white/10 
                           transition-all duration-300 hover:scale-[1.02] 
                           focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2
                           flex items-center justify-between gap-3">

                    {{-- Background Shine Effect --}}
                    <div
                        class="absolute inset-0 bg-gradient-to-br from-white/10 via-transparent to-transparent opacity-0 group-hover/main:opacity-100 transition-opacity duration-500">
                    </div>

                    {{-- Bagian KIRI: Ikon & Teks (Layout Rapat) --}}
                    <div class="flex items-center gap-3 relative z-10">
                        {{-- Ikon Sync (Ukuran disesuaikan agar tidak membuat tombol terlalu tinggi) --}}
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-b from-emerald-500 to-teal-600 shadow-lg shadow-emerald-900/50 group-hover/main:shadow-emerald-500/40 transition-all duration-300 border-t border-white/20">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5 text-white transition-transform duration-700 ease-in-out group-hover/main:rotate-[360deg]"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </div>

                        {{-- Teks (Leading diperketat agar pas di tinggi tombol standar) --}}
                        <div class="flex flex-col items-start text-left justify-center">
                            <span
                                class="text-[10px] font-bold text-emerald-300 uppercase tracking-wider leading-none mb-0.5">Tarik
                                Data</span>
                            <span
                                class="text-sm font-extrabold text-white tracking-tight leading-none shadow-black drop-shadow-md">Sync
                                SAP</span>
                        </div>
                    </div>

                    {{-- Bagian KANAN: Badge Count & Caret --}}
                    <div class="flex items-center gap-2 relative z-10">
                        @if ($selectedCount > 0)
                            <span
                                class="hidden xl:flex items-center justify-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-white/20 text-white border border-white/10 animate-pulse">
                                {{ $selectedCount }}
                            </span>
                        @endif

                        {{-- Caret Icon --}}
                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4 text-emerald-400/70 transition-transform duration-300 group-hover/main:text-white group-hover/main:translate-y-0.5"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </button>

                {{-- 3. MENU DROPDOWN --}}
                <div id="refresh-menu"
                    class="hidden absolute right-0 left-0 mt-2 w-full origin-top-right rounded-xl bg-white p-1.5 shadow-2xl ring-1 ring-emerald-900/5 backdrop-blur-xl z-50 transform transition-all animate-scale-in border border-emerald-100/50">

                    {{-- Opsi 1: Bulan Ini --}}
                    <button id="btn-refresh-month" type="button"
                        class="group/item flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all hover:bg-emerald-50 border border-transparent hover:border-emerald-100">

                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-emerald-100 text-emerald-600 group-hover/item:bg-emerald-500 group-hover/item:text-white transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>

                        <div class="flex flex-col items-start">
                            <span class="font-bold text-slate-700 group-hover/item:text-emerald-800 text-xs">Bulan
                                Ini</span>
                            <span
                                class="text-[10px] text-slate-400 group-hover/item:text-emerald-600/80 leading-tight">Tarik
                                ulang data full</span>
                        </div>
                    </button>

                    {{-- Separator Tipis --}}
                    <div class="h-px bg-slate-50 my-1"></div>

                    {{-- Opsi 2: Tanggal Terakhir --}}
                    <button id="btn-refresh-lastday" type="button"
                        class="group/item flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all hover:bg-teal-50 border border-transparent hover:border-teal-100">

                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-teal-100 text-teal-600 group-hover/item:bg-teal-500 group-hover/item:text-white transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>

                        <div class="flex flex-col items-start">
                            <span class="font-bold text-slate-700 group-hover/item:text-teal-800 text-xs">Tanggal
                                Terakhir</span>
                            <span
                                class="text-[10px] text-slate-400 group-hover/item:text-teal-600/80 leading-tight">Hanya
                                update hari terakhir</span>
                        </div>
                    </button>
                </div>
            </div>


            {{-- TOMBOL 2: COPY NIK --}}
            <button id="btn-copy-nik" type="button"
                class="group relative overflow-hidden rounded-xl
                   bg-gradient-to-br from-teal-600 via-emerald-600 to-emerald-700
                   px-5 py-4 text-sm font-bold text-white
                   shadow-lg shadow-teal-600/30
                   ring-1 ring-teal-500/20
                   transition-all duration-300 ease-out
                   hover:shadow-2xl hover:shadow-teal-600/50 hover:-translate-y-1
                   hover:ring-teal-400/40
                   focus:outline-none focus:ring-2 focus:ring-teal-400 focus:ring-offset-2
                   disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none
                   flex items-center justify-center gap-2.5">

                <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                    <div
                        class="absolute inset-0 bg-gradient-to-r from-transparent via-white/25 to-transparent transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-1000">
                    </div>
                </div>

                <svg xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5 relative z-10 transition-all duration-300 group-hover:scale-110" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>

                <span class="relative z-10 whitespace-nowrap">Copy NIK</span>

                @if ($selectedCount > 0)
                    <span
                        class="flex h-6 min-w-[24px] items-center justify-center rounded-lg 
                             bg-white/95 text-teal-700 text-[11px] font-black 
                             shadow-md ring-2 ring-white/50 relative z-10 px-1.5
                             transition-transform duration-300 group-hover:scale-110 animate-pulse">
                        {{ $selectedCount }}
                    </span>
                @endif
            </button>

            {{-- TOMBOL 3: SAVE KE SAP --}}
            <button type="button" wire:click="openSaveSapModal"
                class="group relative overflow-hidden rounded-xl
                   bg-gradient-to-br from-blue-600 via-indigo-600 to-indigo-700
                   px-5 py-4 text-sm font-bold text-white
                   shadow-lg shadow-blue-500/30
                   ring-1 ring-white/20
                   transition-all duration-300 ease-out
                   hover:shadow-2xl hover:shadow-indigo-500/50 hover:-translate-y-1
                   focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2
                   disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none
                   flex items-center justify-center gap-2.5">

                {{-- Animated Shine --}}
                <div class="absolute inset-0 overflow-hidden">
                    <div
                        class="absolute top-0 -left-[100%] h-full w-[50%] 
                            bg-gradient-to-r from-transparent via-white/20 to-transparent 
                            transform -skew-x-12 transition-all duration-1000 ease-in-out 
                            group-hover:left-[200%]">
                    </div>
                </div>

                <svg xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5 relative z-10 transition-transform duration-300 group-hover:scale-110"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                </svg>

                <span class="relative z-10 whitespace-nowrap">Save ke SAP</span>

                @if ($selectedCount > 0)
                    <span
                        class="flex h-6 min-w-[24px] items-center justify-center rounded-lg 
                             bg-white/95 text-indigo-700 text-[11px] font-black 
                             shadow-md ring-1 ring-indigo-500/30 relative z-10 px-1.5
                             transition-all duration-300 group-hover:scale-110 animate-pulse">
                        {{ $selectedCount }}
                    </span>
                @endif
            </button>

            {{-- TOMBOL 4: EXPORT REPORT (SUMMARY) --}}
            <div class="relative inline-block w-full">
                <button id="export-dropdown-button" type="button"
                    class="group relative overflow-hidden rounded-xl w-full
                       bg-gradient-to-br from-emerald-700 via-emerald-800 to-teal-900
                       px-5 py-4 text-sm font-bold text-white
                       shadow-lg shadow-emerald-700/30
                       ring-1 ring-emerald-600/20
                       transition-all duration-300 ease-out
                       hover:shadow-2xl hover:shadow-emerald-700/50 hover:-translate-y-1
                       hover:ring-emerald-500/40
                       focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2
                       flex items-center justify-center gap-2.5">

                    <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                        <div
                            class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-1000">
                        </div>
                    </div>

                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 relative z-10" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>

                    <span class="relative z-10 whitespace-nowrap">Export Report</span>

                    @if ($selectedCount > 0)
                        <span
                            class="flex h-6 min-w-[24px] items-center justify-center rounded-lg 
                                 bg-white/95 text-emerald-800 text-[11px] font-black 
                                 shadow-md ring-2 ring-white/50 relative z-10 px-1.5
                                 transition-transform duration-300 group-hover:scale-110 animate-pulse">
                            {{ $selectedCount }}
                        </span>
                    @endif

                    <svg xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-4 relative z-10 transition-transform duration-300 group-hover:rotate-180"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                {{-- Dropdown Menu --}}
                <div id="export-dropdown-menu"
                    class="hidden absolute right-0 left-0 sm:left-auto sm:right-0 mt-2 sm:w-52 w-full origin-top
                       rounded-xl bg-white shadow-2xl ring-1 ring-emerald-900/10
                       focus:outline-none z-50 overflow-hidden
                       border border-emerald-100 animate-scale-in">

                    <div
                        class="px-4 py-2.5 text-[10px] font-bold uppercase tracking-widest 
                            text-emerald-700 bg-gradient-to-r from-emerald-50 to-teal-50 
                            border-b border-emerald-100">
                        📊 Summary Report
                    </div>

                    <div class="p-2">
                        <button type="button" wire:click="export('pdf')"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 
                               text-sm font-semibold text-gray-700 
                               transition-all hover:bg-red-50 hover:text-red-700 group/item">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-lg 
                                    bg-gradient-to-br from-red-100 to-red-200 text-red-600 
                                    shadow-sm group-hover/item:shadow-md group-hover/item:scale-110 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <span>Download PDF</span>
                        </button>

                        <button type="button" wire:click="export('excel')"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 
                               text-sm font-semibold text-gray-700 
                               transition-all hover:bg-emerald-50 hover:text-emerald-700 group/item">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-lg 
                                    bg-gradient-to-br from-emerald-100 to-emerald-200 text-emerald-600 
                                    shadow-sm group-hover/item:shadow-md group-hover/item:scale-110 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <span>Download Excel</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- TOMBOL 5: EXPORT DETAIL --}}
            <div class="relative inline-block w-full">
                <button id="export-detail-dropdown-button" type="button"
                    class="group relative overflow-hidden rounded-xl w-full
                       bg-gradient-to-br from-slate-700 via-slate-800 to-gray-900
                       px-5 py-4 text-sm font-bold text-white
                       shadow-lg shadow-slate-700/30
                       ring-1 ring-slate-600/20
                       transition-all duration-300 ease-out
                       hover:shadow-2xl hover:shadow-slate-700/50 hover:-translate-y-1
                       hover:ring-slate-500/40
                       focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2
                       flex items-center justify-center gap-2.5">

                    <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                        <div
                            class="absolute inset-0 bg-gradient-to-r from-transparent via-white/15 to-transparent transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-1000">
                        </div>
                    </div>

                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 relative z-10" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>

                    <span class="relative z-10 whitespace-nowrap">Export Detail</span>

                    @if ($detailSelectedCount > 0)
                        <span
                            class="flex h-6 min-w-[24px] items-center justify-center rounded-lg 
                                 bg-white/95 text-slate-700 text-[11px] font-black 
                                 shadow-sm ring-1 ring-slate-900/20 relative z-10 px-1.5
                                 transition-transform duration-300 group-hover:scale-110 animate-pulse">
                            {{ $detailSelectedCount }}
                        </span>
                    @endif

                    <svg xmlns="http://www.w3.org/2000/svg"
                        class="h-3.5 w-3.5 relative z-10 transition-transform duration-300 group-hover:rotate-180"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                {{-- Dropdown Menu --}}
                <div id="export-detail-dropdown-menu"
                    class="hidden absolute right-0 left-0 sm:left-auto sm:right-0 mt-2 sm:w-48 w-full origin-top
                       rounded-lg bg-white shadow-xl ring-1 ring-black/5 
                       focus:outline-none z-50 overflow-hidden
                       border border-gray-100 animate-scale-in">

                    <div
                        class="px-3 py-2 text-[10px] font-bold uppercase tracking-wider 
                            text-gray-500 bg-gray-50 border-b border-gray-100">
                        📅 Detail Report
                    </div>

                    <div class="p-1">
                        <button type="button" wire:click="exportDetail('pdf')"
                            class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 
                               text-sm font-medium text-gray-700 
                               transition-colors hover:bg-red-50 hover:text-red-700 group/item">
                            <div
                                class="flex h-7 w-7 items-center justify-center rounded-md 
                                    bg-red-100 text-red-600 
                                    group-hover/item:bg-red-200 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <span>PDF</span>
                        </button>

                        <button type="button" wire:click="exportDetail('excel')"
                            class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 
                               text-sm font-medium text-gray-700 
                               transition-colors hover:bg-emerald-50 hover:text-emerald-700 group/item">
                            <div
                                class="flex h-7 w-7 items-center justify-center rounded-md 
                                    bg-emerald-100 text-emerald-600 
                                    group-hover/item:bg-emerald-200 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <span>Excel</span>
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>


    {{-- ======================================================================== --}}
    {{-- BAGIAN 2: FILTER PENCARIAN --}}
    {{-- ======================================================================== --}}
    <div class="mb-8 p-6 bg-emerald-50/50 rounded-xl shadow-inner border border-emerald-100/80 backdrop-blur-sm">
        <p class="text-lg font-bold text-emerald-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            {{ __('Filter Data Berdasarkan Kriteria:') }}
        </p>

        <div class="grid grid-cols-1 gap-6">
            <div class="relative group">
                {{-- INPUT --}}
                <input id="q-input" type="text" wire:model.live.debounce.500ms="q" placeholder=" "
                    class="peer block w-full pt-6 pb-2 px-4 border-gray-300 text-gray-900 bg-white rounded-lg 
                           shadow-sm focus:border-emerald-500 focus:ring-emerald-500 focus:ring-2 transition-all duration-200 h-14" />

                {{-- LABEL --}}
                <label for="q-input"
                    class="absolute text-gray-500 duration-300 transform 
                           top-4 left-4 z-10 origin-[0] 
                           -translate-y-3 scale-75 text-emerald-600 font-bold
                           peer-placeholder-shown:scale-100 
                           peer-placeholder-shown:translate-y-0 
                           peer-placeholder-shown:text-gray-500
                           peer-placeholder-shown:font-normal
                           peer-focus:scale-75 
                           peer-focus:-translate-y-3 
                           peer-focus:text-emerald-600
                           peer-focus:font-bold">
                    {{ __('Kata Kunci Pencarian') }}
                </label>

                <p class="mt-2 text-sm text-gray-500">
                    Cari berdasarkan:
                    <span class="font-semibold text-emerald-700">NIK</span>,
                    <span class="font-semibold text-emerald-700">Work Center</span>,
                    <span class="font-semibold text-emerald-700">Devisi</span>,
                    atau
                    <span class="font-semibold text-emerald-700">Deskripsi/Nama</span>.
                    <br>Gunakan tanda kutip untuk hasil tepat, contoh:
                    <code class="bg-gray-100 px-1 rounded text-emerald-700 font-mono text-xs">"Nama Lengkap"</code>,
                    <code class="bg-gray-100 px-1 rounded text-emerald-700 font-mono text-xs">"DESC WC"</code>,
                    atau
                    <code class="bg-gray-100 px-1 rounded text-emerald-700 font-mono text-xs">"Nama Devisi"</code>.
                </p>

                @error('q')
                    <span class="text-xs text-red-500 mt-1 ml-1 block font-medium">{{ $message }}</span>
                @enderror

                {{-- TOGGLE ROLE INDUK --}}
                <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model.live="onlyInduk"
                            class="rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-sm text-emerald-800 font-semibold">
                            Tampilkan hanya yang ber-Role "INDUK"
                        </span>
                    </label>

                    <span class="text-[11px] text-gray-500 italic">
                        Nonaktif: tampilkan semua role (termasuk yang tidak memiliki Role Induk)
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- ======================================================================== --}}
    {{-- TOAST NOTIFIKASI HASIL SAVE (FLOATING CARD POJOK KANAN BAWAH) --}}
    {{-- ======================================================================== --}}
    @if (!empty($saveResults))
        @php
            $sRes = collect($saveResults);
            $sOk = $sRes->where('ok', true)->count();
            $sFail = $sRes->count() - $sOk;
            $sTotal = $sRes->count();
        @endphp
        <div x-data="{ show: true }" x-show="show" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 scale-95" x-init="setTimeout(() => show = false, 6000)"
            class="fixed bottom-6 right-6 z-[100] w-[380px] bg-white rounded-xl shadow-2xl border border-gray-100 overflow-hidden ring-1 ring-black/5 animate-slide-in-right">

            {{-- Header Toast --}}
            <div
                class="px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="h-10 w-10 rounded-full flex items-center justify-center {{ $sFail > 0 ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600' }}">
                        @if ($sFail > 0)
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        @endif
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-900 leading-tight">Proses Simpan Selesai</h4>
                        <div class="text-xs text-gray-500 mt-1">
                            Total Diproses: <span class="font-mono font-bold">{{ $sTotal }}</span> data
                        </div>
                    </div>
                </div>
                <button @click="show = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            {{-- Body Toast --}}
            <div class="px-5 py-3 bg-white">
                <div class="flex items-center justify-between text-sm mb-3">
                    <div
                        class="flex items-center gap-1.5 text-emerald-700 font-semibold bg-emerald-50 px-2 py-1 rounded">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 13l4 4L19 7" />
                        </svg>
                        Berhasil: {{ $sOk }}
                    </div>
                    <div class="flex items-center gap-1.5 text-red-700 font-semibold bg-red-50 px-2 py-1 rounded">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Gagal: {{ $sFail }}
                    </div>
                </div>

                {{-- List Log --}}
                <div
                    class="bg-slate-50 rounded-lg p-2.5 max-h-[150px] overflow-y-auto space-y-1.5 border border-slate-100">
                    @foreach ($saveResults as $row)
                        <div
                            class="text-[10px] flex items-start gap-2 leading-snug border-b border-gray-100 last:border-0 pb-1 last:pb-0">
                            <span class="font-mono text-gray-500 whitespace-nowrap">{{ $row['pernr'] ?? '?' }}</span>
                            <div class="flex-1">
                                <span
                                    class="font-semibold {{ $row['ok'] ?? false ? 'text-emerald-600' : 'text-red-600' }}">
                                    {{ $row['ok'] ?? false ? 'OK' : 'ERR' }}
                                </span>
                                <span class="text-gray-600">
                                    - {{ Str::limit($row['return_message'] ?? 'No message', 50) }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Progress Bar for Auto Close --}}
            <div class="h-1 w-full bg-gray-100">
                <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 origin-left animate-shrink-width">
                </div>
            </div>
        </div>
    @endif

    {{-- ======================================================================== --}}
    {{-- BAGIAN 3: TABEL RINGKASAN --}}
    {{-- ======================================================================== --}}
    <div wire:key="summary-{{ md5(($werks ?? request()->route('werks')) . '|' . $q . '|' . (int) $onlyInduk) }}">

        <div class="overflow-y-auto max-h-[75vh] shadow-xl sm:rounded-xl border border-gray-200/75">
            <table id="web-summary-table" class="min-w-full divide-y divide-gray-200">
                <thead class="sticky top-0 z-20 bg-gradient-to-r from-emerald-800 to-teal-900 text-white shadow-md">
                    <tr>
                        @php
                            $pagePernrs = array_map('strval', $currentPagePernrs ?? []);
                            $selectedP = array_map('strval', $selectedPernrs ?? []);
                            $allCurrentSelected =
                                !empty($pagePernrs) &&
                                count(array_intersect($pagePernrs, $selectedP)) === count($pagePernrs);
                        @endphp

                        {{-- Kolom checkbox STICKY LEFT - Header --}}
                        <th scope="col"
                            class="sticky left-0 z-30 px-6 py-4 text-center text-sm font-bold uppercase tracking-wider w-10 bg-emerald-800">
                            <label
                                class="inline-flex items-center justify-center gap-2 select-none cursor-pointer group">
                                <input id="check-all-summary" type="checkbox"
                                    class="rounded border-gray-300 text-emerald-500 focus:ring-emerald-500 transition-colors cursor-pointer bg-white/90 h-4 w-4"
                                    @checked($allCurrentSelected)>
                            </label>
                        </th>

                        @foreach ($headersSummary as $header)
                            <th scope="col"
                                class="px-6 py-4 text-center text-sm font-bold uppercase tracking-wider whitespace-nowrap">
                                {{ __($header) }}
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($reportData as $data)
                        <tr wire:key="report-row-{{ $data->pernr }}"
                            wire:click="showPernrDetail({{ \Illuminate\Support\Js::from((string) $data->pernr) }})"
                            class="group/row transition-all duration-200 ease-in-out hover:bg-emerald-50 cursor-pointer odd:bg-white even:bg-slate-50/50">

                            {{-- Checkbox STICKY LEFT - Body --}}
                            <td
                                class="sticky left-0 z-10 px-6 py-4 bg-white group-even/row:bg-slate-50/50 group-hover/row:bg-emerald-50">
                                <input type="checkbox" wire:model.live="selectedPernrs"
                                    value="{{ (string) $data->pernr }}" data-arbpl="{{ $data->arbpl }}"
                                    data-werks="{{ $data->werks ?? ($werks ?? request()->route('werks')) }}"
                                    class="summary-check rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer h-5 w-5"
                                    wire:click.stop>
                            </td>

                            {{-- NO --}}
                            <td class="px-6 py-4 text-center font-extrabold text-emerald-800/80">
                                {{ $loop->iteration }}
                            </td>

                            {{-- PERSONAL NO --}}
                            <td class="px-6 py-4 text-center font-medium text-gray-900">
                                {{ $data->pernr }}
                            </td>

                            {{-- RENTANG TANGGAL (format vertikal: yyyy / mm-dd / ↓ / yyyy / mm-dd) --}}
                            @php
                                $minDate = Carbon::createFromFormat('Ymd', $data->min_begda);
                                $maxDate = Carbon::createFromFormat('Ymd', $data->max_begda);
                            @endphp
                            <td class="px-6 py-4 text-gray-600 text-xs">
                                <div class="flex flex-col items-center font-mono leading-tight">
                                    <span>{{ $minDate->format('Y') }}</span>
                                    <span>{{ $minDate->format('m-d') }}</span>

                                    <span class="text-emerald-500 text-xs my-0.5">↓</span>

                                    <span>{{ $maxDate->format('Y') }}</span>
                                    <span>{{ $maxDate->format('m-d') }}</span>
                                </div>
                            </td>

                            {{-- NAMA (tetap rata kiri, bisa 2 baris) --}}
                            <td class="px-6 py-4 font-semibold text-slate-800 capitalize text-left">
                                {{ strtolower($data->cname) }}
                            </td>

                            {{-- WC PERSONAL --}}
                            <td class="px-6 py-4 text-center text-slate-700 font-medium">
                                {{ $data->arbpl }}
                            </td>

                            {{-- WC KONFIRMASI (arbpl2) --}}
                            <td class="px-6 py-4 text-center text-slate-700 font-medium">
                                {{ $data->arbpl2 ?? '-' }}
                            </td>

                            {{-- DESC WC (tetap rata kiri, bisa 2 baris) --}}
                            <td class="px-6 py-4 text-slate-600 text-[12px] leading-snug min-w-[200px] text-left">
                                {{ Str::limit($data->desc, 60) }}
                            </td>

                            {{-- ROLE --}}
                            <td class="px-6 py-4 text-center text-[12px] font-semibold">
                                {{ $data->role ?? '-' }}
                            </td>

                            {{-- DEVISI --}}
                            <td class="px-6 py-4 text-center text-slate-700 text-[12px]">
                                {{ $data->devisi ?? '-' }}
                            </td>

                            {{-- MENIT HADIR (total_jam) --}}
                            <td class="px-6 py-4 text-center text-gray-900 font-semibold tracking-tight">
                                {{ number_format($data->total_jam, 1) }}
                            </td>

                            {{-- MENIT CONF (mint2) --}}
                            <td class="px-6 py-4 text-center text-gray-900 font-semibold tracking-tight">
                                {{ number_format((int) $data->mint2, 0, ',', '.') }}
                            </td>

                            {{-- MENIT INSPECT (mintu) --}}
                            <td class="px-6 py-4 text-center text-gray-900 font-semibold tracking-tight">
                                {{ number_format((int) $data->mintu, 0, ',', '.') }}
                            </td>

                            {{-- DETIK INSPECT (mintu2) --}}
                            <td class="px-6 py-4 text-center text-gray-900 font-semibold tracking-tight">
                                {{ number_format((int) $data->mintu2, 0, ',', '.') }}
                            </td>

                            {{-- DETIK KONFIRMASI (mintu3) --}}
                            <td class="px-6 py-4 text-center text-gray-900 font-semibold tracking-tight">
                                {{ number_format((int) $data->mintu3, 0, ',', '.') }}
                            </td>

                            {{-- UPAH HADIR (gji) --}}
                            <td class="px-6 py-4 text-center text-gray-900 font-semibold tracking-tight">
                                {{ number_format((float) $data->gji, 2) }}
                            </td>

                            {{-- UPAH INSPECT (gji2) --}}
                            <td class="px-6 py-4 text-center text-gray-900 font-semibold tracking-tight">
                                {{ number_format((float) $data->gji2, 2) }}
                            </td>

                            {{-- VAR UPAH (varnt) --}}
                            <td
                                class="px-6 py-4 text-center font-semibold {{ $data->varnt < 0 ? 'text-red-600' : 'text-gray-800' }}">
                                {{ number_format($data->varnt, 2) }}
                            </td>

                            {{-- PERSENTASE VAR (varnt1) --}}
                            <td class="px-6 py-4 text-center">
                                <span
                                    class="inline-flex items-center px-2.5 py-1 rounded text-[11px] font-semibold {{ $data->varnt1 < 100 ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800' }}">
                                    {{ number_format($data->varnt1, 2) }}%
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($headersSummary) + 1 }}" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center text-gray-400">
                                    <svg class="w-16 h-16 mb-4 text-gray-300" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                                        </path>
                                    </svg>
                                    <span
                                        class="text-xl font-medium text-gray-500">{{ __('Tidak ada data untuk filter saat ini.') }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{-- TOMBOL TOTAL UPAH (PIN) DI BAWAH TABEL - SECURE VAULT STYLE --}}
    <div
        class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-4 rounded-xl bg-gradient-to-r from-emerald-50 to-white border border-emerald-100 shadow-sm">

        <div class="flex items-start gap-3">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="text-xs text-gray-500 leading-relaxed">
                <span class="block font-bold text-emerald-800 text-sm mb-0.5">Informasi Sensitif</span>
                Total upah dihitung berdasarkan Plant
                <span
                    class="font-bold text-emerald-700 bg-emerald-100/50 px-1 rounded">{{ $werks ?? request()->route('werks') }}</span>
                dan periode
                <span class="font-bold text-emerald-700 bg-emerald-100/50 px-1 rounded">
                    {{ $monthFilter === 'prev' ? 'bulan lalu' : 'bulan ini' }}
                </span>.
            </div>
        </div>

        <button type="button" wire:click="openTotalUpahModal"
            class="group relative inline-flex items-center justify-center gap-2 rounded-lg
                   bg-slate-800 px-6 py-3 text-sm font-bold text-white
                   shadow-lg shadow-slate-900/20 ring-1 ring-white/10
                   transition-all duration-300
                   hover:bg-slate-700 hover:shadow-xl hover:shadow-slate-900/30 hover:-translate-y-0.5 
                   focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">

            {{-- Efek Kunci --}}
            <svg xmlns="http://www.w3.org/2000/svg"
                class="h-4 w-4 text-emerald-400 transition-transform group-hover:scale-110" fill="none"
                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>

            <span>Buka Total Upah</span>

            {{-- Indikator PIN --}}
            <span class="ml-1 flex gap-0.5">
                <span
                    class="h-1 w-1 rounded-full bg-slate-500 group-hover:bg-emerald-400 transition-colors delay-75"></span>
                <span
                    class="h-1 w-1 rounded-full bg-slate-500 group-hover:bg-emerald-400 transition-colors delay-100"></span>
                <span
                    class="h-1 w-1 rounded-full bg-slate-500 group-hover:bg-emerald-400 transition-colors delay-150"></span>
            </span>
        </button>
    </div>

    {{-- ======================================================================== --}}
    {{-- BAGIAN 4: MODAL DETAIL --}}
    {{-- ======================================================================== --}}
    @if ($showDetailModal)
        <div id="yppr058-modal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title"
            role="dialog" aria-modal="true">
            <div class="flex min-h-full items-end sm:items-center justify-center pt-4 px-4 pb-20 text-center sm:p-0">

                {{-- Backdrop --}}
                <div class="fixed inset-0 bg-slate-900/75 transition-opacity backdrop-blur-sm" aria-hidden="true"
                    wire:click="closeDetailModal"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                {{-- Modal Panel --}}
                <div
                    class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-7xl sm:w-full border border-gray-200">

                    {{-- Modal Header --}}
                    <div
                        class="bg-gradient-to-r from-emerald-700 to-teal-800 px-6 py-4 sm:px-8 flex justify-between items-center">
                        <h3 class="text-xl leading-6 font-bold text-white tracking-wide flex items-center gap-2"
                            id="modal-title">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-emerald-200" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Detail Tanggal
                            <span
                                class="text-emerald-200 font-mono bg-white/10 px-2 rounded ml-1 text-lg">{{ $selectedPernr }}</span>
                        </h3>
                        <button wire:click="closeDetailModal" type="button"
                            class="text-emerald-100 hover:text-white transition-colors focus:outline-none">
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- Modal Body --}}
                    <div class="px-6 py-6 sm:px-8 sm:py-8 bg-slate-50">
                        <div
                            class="overflow-x-auto overflow-y-auto max-h-[60vh] shadow-md sm:rounded-lg bg-white border border-gray-200">
                            <table id="detail-table" class="min-w-full divide-y divide-gray-100">
                                <thead class="sticky top-0 z-20 bg-emerald-50">
                                    <tr>
                                        {{-- Checkbox Detail STICKY LEFT - Header --}}
                                        <th
                                            class="sticky left-0 z-30 px-4 py-3 text-center text-xs font-bold text-emerald-800 uppercase tracking-wider border-b-2 border-emerald-100 bg-emerald-50">
                                            <label class="inline-flex items-center gap-2 select-none">
                                                <input id="check-all-detail" type="checkbox"
                                                    class="rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500">
                                            </label>
                                        </th>

                                        @foreach ($headersDetail as $header)
                                            <th scope="col"
                                                class="px-4 py-3 text-center text-xs font-bold text-emerald-800 uppercase tracking-wider border-b-2 border-emerald-100 whitespace-nowrap">
                                                {{ __($header) }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>

                                <tbody class="bg-white divide-y divide-gray-100">
                                    @foreach ($detailData as $data)
                                        <tr wire:key="detail-row-{{ $selectedPernr }}-{{ $data['begda'] ?? $loop->iteration }}"
                                            class="group/detail hover:bg-emerald-50/50 transition-colors">

                                            {{-- Checkbox Detail STICKY LEFT - Body --}}
                                            <td
                                                class="sticky left-0 z-10 px-6 py-3 whitespace-nowrap text-sm bg-white group-hover/detail:bg-emerald-50/50">
                                                <input type="checkbox"
                                                    class="refresh-check rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                                                    title="{{ empty($data['arbpl']) || empty($data['werks']) ? 'WC/Plant kosong: server akan mencari otomatis' : 'Siap kirim' }}"
                                                    data-pernr="{{ $data['pernr'] ?? '' }}"
                                                    data-werks="{{ $data['werks'] ?? '' }}"
                                                    data-arbpl="{{ $data['arbpl'] ?? '' }}"
                                                    data-date="{{ $data['begda'] ?? '' }}"
                                                    wire:model.live="selectedDetailKeys"
                                                    value="{{ ($data['pernr'] ?? '') . '|' . ($data['begda'] ?? '') }}">
                                            </td>

                                            <td
                                                class="px-4 py-2 whitespace-nowrap text-sm font-bold text-emerald-800 text-center">
                                                {{ $loop->iteration }}
                                            </td>

                                            @php
                                                $detailColumns = [
                                                    'pernr', // Personal No.
                                                    'begda', // Tanggal
                                                    'cname', // Nama
                                                    'arbpl', // WC Personal
                                                    'arbpl2', // WC Konfirmasi
                                                    'desc', // DESC WC
                                                    'role', // Role
                                                    'devisi', // Devisi
                                                    'shift', // <<< TAMBAH: Shift

                                                    'total_jam', // Menit Hadir (TOTAL_JAM)
                                                    'mint2', // Menit Conf (MINT2)
                                                    'mintu', // Menit Inspect (MINTU)
                                                    'mintu2', // Detik Inspect (MINTU2)
                                                    'mintu3', // Detik Konfirmasi (MINTU3)

                                                    'gji', // Upah Hadir
                                                    'gji2', // Upah Inspect
                                                    'varnt', // Var Upah
                                                ];
                                            @endphp

                                            @foreach ($detailColumns as $column)
                                                @php
                                                    $val = $data[$column] ?? null;

                                                    // KHUSUS varnt1: hitung dari varnt & gji
                                                    if ($column === 'varnt1') {
                                                        $gji = isset($data['gji']) ? (float) $data['gji'] : 0;
                                                        $varnt = isset($data['varnt']) ? (float) $data['varnt'] : 0;

                                                        $val = $gji != 0 ? ($varnt / $gji) * 100 : 0;
                                                    }

                                                    $isMoney = in_array($column, ['gji', 'gji2', 'varnt']);
                                                    $isNum = in_array($column, ['mint2', 'mintu', 'mintu2', 'mintu3']);
                                                    $isDate = $column === 'begda';

                                                    $isDesc = $column === 'desc'; // DESC WC
                                                    $isDevisi = $column === 'devisi'; // DEVISI
                                                    $isLeftName = $column === 'cname'; // Nama

                                                    $isLeft = $isLeftName || $isDesc;
                                                    $isMultiline = $isDesc || $isDevisi; // DESC & DEVISI boleh 2 baris
                                                @endphp

                                                <td @class([
                                                    'px-4 py-2 text-[11px]',
                                                    'whitespace-nowrap' => !$isMultiline, // selain DESC/DEVISI: 1 baris
                                                    'whitespace-normal break-words leading-snug' => $isMultiline, // DESC & DEVISI: boleh bungkus
                                                    'text-left' => $isLeft,
                                                    'text-center' => !$isLeft,
                                                    'font-mono' => $isMoney || $isNum || $column === 'total_jam',
                                                    'text-emerald-700 font-medium' => $isMoney,
                                                ])>
                                                    @if ($val === '' || is_null($val))
                                                        -
                                                    @elseif ($column === 'devisi' && is_string($val) && str_contains($val, '-'))
                                                        @php
                                                            [$before, $after] = array_pad(explode('-', $val, 2), 2, '');
                                                        @endphp

                                                        <div class="flex flex-col leading-tight">
                                                            <span>{{ trim($before) }}</span>
                                                            @if (trim($after) !== '')
                                                                <span>- {{ trim($after) }}</span>
                                                            @endif
                                                        </div>
                                                    @elseif ($column === 'total_jam')
                                                        {{ number_format($val, 1) }}
                                                    @elseif ($isMoney)
                                                        {{ number_format($val, 2) }}
                                                    @elseif ($isNum)
                                                        {{ (int) $val }}
                                                    @elseif ($isDate)
                                                        @php
                                                            $dt = Carbon::createFromFormat('Ymd', $val);
                                                        @endphp
                                                        <div
                                                            class="flex flex-col items-center leading-tight font-mono text-[12px]">
                                                            <span>{{ $dt->format('Y') }}</span>
                                                            <span>{{ $dt->format('m-d') }}</span>
                                                        </div>
                                                    @else
                                                        {{ $val }}
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Modal Footer --}}
                    <div
                        class="bg-white px-6 py-4 sm:px-8 sm:flex sm:flex-row-reverse items-center gap-3 border-t border-gray-200">

                        <div class="flex flex-col sm:flex-row-reverse gap-2 w-full sm:w-auto">
                            {{-- REFRESH DARI SAP --}}
                            <button id="btn-refresh-sap" type="button"
                                class="w-full inline-flex justify-center items-center gap-2 rounded-lg border border-transparent shadow-lg shadow-emerald-200 px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 text-base font-bold text-white hover:from-emerald-700 hover:to-teal-700 focus:outline-none sm:w-auto sm:text-sm transition-all transform hover:scale-105">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Refresh dari SAP (terpilih)
                            </button>

                            {{-- TUTUP --}}
                            <button wire:click="closeDetailModal" type="button"
                                class="mt-3 sm:mt-0 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-5 py-2.5 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:w-auto sm:text-sm transition-all">
                                Tutup
                            </button>
                        </div>

                        <div class="mt-3 sm:mt-0 sm:mr-auto text-sm text-gray-500 italic">
                            <span id="refresh-progress">Siap memproses data...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ======================================================================== --}}
    {{-- MODAL SAVE KE SAP (LOGIN SAP USER) --}}
    {{-- ======================================================================== --}}
    @if ($showSaveSapModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl border border-gray-200 w-full max-w-md p-6 animate-scale-in">
                <h3 class="text-lg font-bold text-emerald-800 mb-4">
                    Save ke SAP (Z_RFC_SAVE_YPPR058)
                </h3>

                @if ($sapAuthError)
                    <div
                        class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 flex gap-3 items-start shadow-sm">
                        <div
                            class="mt-0.5 flex h-8 w-8 items-center justify-center rounded-full bg-red-100 text-red-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 9v3m0 4h.01M4.93 4.93l14.14 14.14M12 4a8 8 0 100 16 8 8 0 000-16z" />
                            </svg>
                        </div>
                        <div class="text-sm text-red-800">
                            <p class="font-semibold mb-1">
                                SAP User tidak memiliki otorisasi
                            </p>
                            <p class="leading-snug">
                                {{ $sapAuthError }}
                            </p>
                        </div>
                    </div>
                @endif
                <p class="text-xs text-gray-500 mb-4">
                    Masukkan <span class="font-semibold text-emerald-700">SAP User</span> dan
                    <span class="font-semibold text-emerald-700">Password</span> Anda.
                    Data akan disimpan ke <code class="font-mono">ZTABLEYPPR058</code> dengan
                    <code class="font-mono">createby = sy-uname</code>.
                </p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">SAP User</label>
                        <input type="text" wire:model.defer="sapUser"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm
                               focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                        @error('sapUser')
                            <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">SAP Password</label>
                        <input type="password" wire:model.defer="sapPass"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm
                               focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                        @error('sapPass')
                            <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3 items-center">
                    {{-- Status kecil di kiri saat sedang proses --}}
                    <div class="mr-auto text-xs text-gray-500" wire:loading wire:target="saveToSap">
                        Sedang mengirim data ke SAP, mohon tunggu...
                    </div>

                    {{-- Tombol Batal --}}
                    <button type="button" wire:click="closeSaveSapModal"
                        class="px-4 py-2 text-sm rounded-md border border-gray-300
               bg-white text-gray-700 hover:bg-gray-50">
                        Batal
                    </button>

                    {{-- Tombol Save dengan state loading --}}
                    <button type="button" wire:click="saveToSap" wire:loading.attr="disabled"
                        wire:target="saveToSap"
                        class="relative px-4 py-2 text-sm rounded-md bg-emerald-600 text-white
               hover:bg-emerald-700 shadow-sm flex items-center gap-2">

                        {{-- Icon spinner saat loading --}}
                        <svg wire:loading wire:target="saveToSap" class="h-4 w-4 animate-spin"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                        </svg>

                        {{-- Teks normal --}}
                        <span wire:loading.remove wire:target="saveToSap">
                            Save ke SAP
                        </span>

                        {{-- Teks saat loading --}}
                        <span wire:loading wire:target="saveToSap">
                            Proses...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif
    {{-- ======================================================================== --}}
    {{-- MODAL TOTAL UPAH (MOBILE BANKING PIN STYLE) --}}
    {{-- ======================================================================== --}}
    @if ($showTotalUpahModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto overflow-x-hidden bg-slate-900/80 backdrop-blur-sm transition-all p-4 sm:p-0"
            x-data="{
                pin: ['', '', '', '', '', ''],
                loading: false,
                focusNext(index) {
                    if (this.pin[index].length === 1) {
                        if (index < 5) {
                            this.$refs['pin' + (index + 1)].focus();
                        } else {
                            // Submit otomatis jika digit ke-6 terisi
                            this.submitPin();
                        }
                    }
                },
                focusPrev(index) {
                    if (this.pin[index].length === 0 && index > 0) {
                        this.$refs['pin' + (index - 1)].focus();
                    }
                },
                submitPin() {
                    let fullPin = this.pin.join('');
                    if (fullPin.length === 6) {
                        this.loading = true;
                        @this.set('totalUpahPin', fullPin);
                        @this.call('calculateTotalUpah').then(() => {
                            this.loading = false;
                        });
                    }
                }
            }" x-init="$nextTick(() => $refs.pin0.focus())">

            <div
                class="relative w-full max-w-sm transform rounded-2xl bg-white shadow-2xl transition-all animate-scale-in overflow-hidden border border-gray-100">

                {{-- Header Modal --}}
                <div class="bg-slate-50 px-6 py-6 text-center border-b border-gray-100">
                    <div
                        class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 ring-4 ring-emerald-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800">Keamanan Diperlukan</h3>
                    <p class="mt-2 text-xs text-gray-500 px-4">
                        Masukkan <span class="font-bold text-slate-900">6 Digit PIN</span> otorisasi Anda untuk membuka
                        data sensitif Upah Plant {{ $werks }}.
                    </p>
                </div>

                <div class="px-6 py-6">
                    {{-- JIKA BELUM ADA HASIL (INPUT PIN STATE) --}}
                    @if (is_null($totalUpahGji) || is_null($totalUpahGji2))

                        {{-- 6 Digit Input Boxes --}}
                        <div class="mb-6 flex justify-center gap-2 sm:gap-3">
                            <template x-for="(digit, index) in pin" :key="index">
                                <input type="password" inputmode="numeric" pattern="[0-9]*" maxlength="1"
                                    {{-- ATRIBUT ANTI-AUTOFILL --}} autocomplete="one-time-code" data-lpignore="true"
                                    :name="'pin_digit_' + index" x-model="pin[index]" :x-ref="'pin' + index"
                                    @input="focusNext(index)" @keydown.backspace="focusPrev(index)"
                                    @focus="$event.target.select()"
                                    class="h-12 w-10 sm:h-14 sm:w-12 rounded-lg border-2 border-gray-200 text-center text-xl font-bold text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-500/20 transition-all caret-emerald-500"
                                    :class="{
                                        'border-red-300 focus:border-red-500 focus:ring-red-200': '{{ $totalUpahError }}'
                                        !== ''
                                    }" />
                            </template>
                        </div>

                        {{-- Pesan Error --}}
                        @if ($totalUpahError)
                            <div
                                class="mb-4 flex items-center justify-center gap-2 text-xs font-medium text-red-600 bg-red-50 py-2 rounded-lg animate-pulse">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                {{ $totalUpahError }}
                            </div>
                        @endif

                        {{-- Loading Indicator --}}
                        <div x-show="loading"
                            class="text-center text-xs text-emerald-600 font-semibold mb-4 flex justify-center items-center gap-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                    stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z">
                                </path>
                            </svg>
                            Memverifikasi PIN...
                        </div>
                    @else
                        {{-- JIKA SUDAH BERHASIL (RECEIPT STATE) --}}
                        <div
                            class="relative overflow-hidden rounded-xl border border-emerald-100 bg-emerald-50/50 p-5 animate-fade-in">
                            {{-- Dekorasi bulat --}}
                            <div class="absolute -right-6 -top-6 h-20 w-20 rounded-full bg-emerald-100 blur-2xl"></div>
                            <div class="absolute -left-6 -bottom-6 h-20 w-20 rounded-full bg-teal-100 blur-2xl"></div>

                            <div class="relative z-10">
                                <div class="text-[10px] font-bold uppercase tracking-widest text-emerald-600/70 mb-2">
                                    Rincian Total Upah
                                </div>
                                <div
                                    class="text-xs font-mono text-slate-500 mb-4 border-b border-dashed border-emerald-200 pb-3">
                                    Periode: {{ $totalUpahPeriodLabel }}
                                </div>

                                <div class="space-y-4">
                                    <div class="flex justify-between items-end">
                                        <span class="text-sm text-slate-600 font-medium">Upah Hadir (GJI)</span>
                                        <span class="text-base font-bold text-slate-800 font-mono tracking-tight">
                                            Rp {{ number_format($totalUpahGji, 2, ',', '.') }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-end">
                                        <span class="text-sm text-slate-600 font-medium">Upah Inspect (GJI2)</span>
                                        <span class="text-base font-bold text-slate-800 font-mono tracking-tight">
                                            Rp {{ number_format($totalUpahGji2, 2, ',', '.') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Footer Modal --}}
                <div class="bg-gray-50 px-6 py-4 flex justify-between items-center">
                    <button type="button" wire:click="closeTotalUpahModal"
                        class="text-sm font-semibold text-slate-500 hover:text-slate-800 transition-colors focus:outline-none">
                        Tutup
                    </button>

                    @if (is_null($totalUpahGji) || is_null($totalUpahGji2))
                        <button type="button" @click="submitPin()"
                            class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-bold text-white hover:bg-slate-800 focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 transition-all shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="pin.join('').length < 6 || loading">
                            Buka Data
                        </button>
                    @else
                        {{-- Tombol Print/Copy Dummy (Opsional) --}}
                        <div class="flex gap-2">
                            <span
                                class="text-xs text-emerald-600 font-bold bg-emerald-100 px-2 py-1 rounded flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                                Verified
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>


{{-- ======= STYLE & SCRIPT BAWAAN ======= --}}
@push('styles')
    <style>
        /* State Busy Button */
        #btn-refresh-sap.is-busy,
        #btn-refresh-sap.is-busy:hover {
            cursor: not-allowed !important;
            opacity: 0.7;
            background: #6b7280;
            /* Gray-500 */
            transform: none;
            box-shadow: none;
        }

        body.yppr058-refresh-busy * {
            cursor: wait !important;
        }
    </style>
@endpush

@once
    @push('scripts')
        <script>
            (function() {
                if (window.__yppr058Bound) return;
                window.__yppr058Bound = true;

                const API_BASE = 'http://127.0.0.1:5010';
                const API_REFRESH_URL = `/api/yppr058/refresh`;
                const CURRENT_WERKS = @json($werks ?? request()->route('werks'));
                const LS_PREFILL = 'yppr058_prefill_q';
                const LS_SUMMARY = 'yppr058_refresh_summary';
                const $ = (sel, root = document) => root.querySelector(sel);
                const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

                function getMonthFilter() {
                    const root = document.getElementById('yppr058-root');
                    return (root && root.dataset.monthFilter) ? root.dataset.monthFilter : 'this';
                }

                // === CACHE PILIHAN SUMMARY (SUPAYA TETAP INGAT WALAU FILTER BERUBAH) ===

                // Set NIK yang pernah dicentang di summary
                const selectedPernrsSet = window.__yppr058SelectedPernrs || new Set();
                window.__yppr058SelectedPernrs = selectedPernrsSet;

                // Map: pernr -> { arbpl, werks }
                const wcMap = window.__yppr058WCMap || {};
                window.__yppr058WCMap = wcMap;

                function registerSummaryCheckbox(cb) {
                    const pernr = (cb.value || '').trim();
                    if (!pernr) return;

                    const arbpl = (cb.dataset.arbpl || '').trim();
                    const werks = (cb.dataset.werks || CURRENT_WERKS || '').trim();

                    if (arbpl || werks) {
                        wcMap[pernr] = {
                            arbpl,
                            werks
                        };
                    }
                }

                // Dipanggil setiap kali habis Livewire render
                function rescanSummaryCheckboxes() {
                    $$('.summary-check', document).forEach(cb => {
                        registerSummaryCheckbox(cb);
                        if (cb.checked) {
                            const pernr = (cb.value || '').trim();
                            if (pernr) selectedPernrsSet.add(pernr);
                        }
                    });
                }

                // Pertama kali: setelah view ini dirender
                rescanSummaryCheckboxes();

                // Tombol COPY NIK
                const copyBtn = document.getElementById('btn-copy-nik');
                if (copyBtn) {
                    copyBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        copySelectedPernrs();
                    });
                }

                // Dropdown Export Summary
                const exportBtn = document.getElementById('export-dropdown-button');
                const exportMenu = document.getElementById('export-dropdown-menu');
                if (exportBtn && exportMenu) {
                    exportBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        exportMenu.classList.toggle('hidden');
                    });
                    document.addEventListener('click', function(e) {
                        if (!exportMenu.classList.contains('hidden') && !exportBtn.contains(e.target) &&
                            !exportMenu.contains(e.target)) {
                            exportMenu.classList.add('hidden');
                        }
                    });
                }

                // Dropdown Export Detail
                const exportDetailBtn = document.getElementById('export-detail-dropdown-button');
                const exportDetailMenu = document.getElementById('export-detail-dropdown-menu');
                if (exportDetailBtn && exportDetailMenu) {
                    exportDetailBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        exportDetailMenu.classList.toggle('hidden');
                    });
                    document.addEventListener('click', function(e) {
                        if (!exportDetailMenu.classList.contains('hidden') && !exportDetailBtn.contains(e
                                .target) &&
                            !exportDetailMenu.contains(e.target)) {
                            exportDetailMenu.classList.add('hidden');
                        }
                    });
                }

                // Toast Helpers
                let toastStack;

                function ensureToastStack() {
                    if (!toastStack) {
                        toastStack = document.createElement('div');
                        toastStack.id = 'toast-stack';
                        toastStack.className = 'fixed bottom-6 right-6 z-[9999] space-y-3';
                        document.body.appendChild(toastStack);
                    }
                }

                function makeCard(html) {
                    ensureToastStack();
                    const card = document.createElement('div');
                    card.className =
                        'pointer-events-auto w-[360px] rounded-xl border border-emerald-100 bg-white shadow-2xl ring-1 ring-black/5';
                    card.innerHTML = html;
                    toastStack.appendChild(card);
                    return card;
                }

                function progressCard(statusText) {
                    if (!window.__yppr058ProgressCard) {
                        window.__yppr058ProgressCard = makeCard(
                            `
                        <div class="p-4"><div class="flex items-start gap-3">
                            <div class="h-10 w-10 rounded-full bg-emerald-100 flex items-center justify-center animate-pulse">
                                <svg class="h-5 w-5 text-emerald-700" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                            </div>
                            <div class="flex-1"><div class="font-bold text-emerald-900">Sinkronisasi SAP...</div><div id="pc-msg" class="text-sm text-slate-600 mt-0.5"></div></div>
                        </div><div class="mt-3 h-1.5 w-full bg-gray-100 rounded-full overflow-hidden"><div id="pc-bar" class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 rounded-full transition-all duration-300" style="width:0%"></div></div></div>`
                        );
                    }
                    const card = window.__yppr058ProgressCard;
                    $('#pc-msg', card).textContent = statusText || '';
                    return card;
                }

                function updateProgress(current, total) {
                    const card = window.__yppr058ProgressCard;
                    if (!card) return;
                    const pct = Math.round((current / Math.max(1, total)) * 100);
                    $('#pc-bar', card).style.width = pct + '%';
                    $('#pc-msg', card).textContent = `Memproses ${current} dari ${total} data...`;
                }

                function hideProgress() {
                    if (window.__yppr058ProgressCard) {
                        window.__yppr058ProgressCard.remove();
                        window.__yppr058ProgressCard = null;
                    }
                }

                function showSummaryToast(summary) {
                    const {
                        ok = 0, fail = 0, total = 0
                    } = summary || {};
                    const html = `
                        <div class="p-4 border-l-4 ${fail ? 'border-amber-500' : 'border-emerald-500'}">
                            <div class="flex items-start gap-3">
                                <div class="flex-1">
                                    <h4 class="font-bold text-gray-900">Sinkronisasi Selesai</h4>
                                    <p class="text-sm text-gray-600 mt-1">Berhasil: <b class="text-emerald-600">${ok}</b> &bull; Gagal: <b class="text-red-600">${fail}</b></p>
                                </div>
                                <button class="text-gray-400 hover:text-gray-600" onclick="this.closest('div.pointer-events-auto').remove()">✕</button>
                            </div>
                        </div>`;
                    const card = makeCard(html);
                    setTimeout(() => card.remove(), 6000);
                }

                // === COPY NIK TERPILIH KE CLIPBOARD ===
                async function copySelectedPernrs() {
                    const pernrs = Array.from(selectedPernrsSet);

                    if (!pernrs.length) {
                        alert('Belum ada NIK yang dipilih di tabel ringkasan.');
                        return;
                    }

                    // Format: 001 002 003 (TANPA PETIK)
                    const text = pernrs.join(' ');

                    try {
                        // Browser modern
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            await navigator.clipboard.writeText(text);
                        } else {
                            // Fallback cara lama
                            const ta = document.createElement('textarea');
                            ta.value = text;
                            ta.style.position = 'fixed';
                            ta.style.left = '-9999px';
                            document.body.appendChild(ta);
                            ta.select();
                            document.execCommand('copy');
                            document.body.removeChild(ta);
                        }

                        // Tampilkan toast sukses
                        const html = `
                            <div class="p-4 border-l-4 border-emerald-500">
                                <div class="flex items-start gap-3">
                                    <div class="h-8 w-8 rounded-full bg-emerald-100 flex items-center justify-center">
                                        <svg class="h-4 w-4 text-emerald-700" viewBox="0 0 24 24" fill="none">
                                            <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2"
                                                  stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="font-bold text-gray-900">NIK tersalin ke clipboard</h4>
                                        <p class="text-xs text-gray-600 mt-1 break-all">${text}</p>
                                    </div>
                                    <button class="text-gray-400 hover:text-gray-600"
                                            onclick="this.closest('div.pointer-events-auto').remove()">✕</button>
                                </div>
                            </div>`;
                        const card = makeCard(html);
                        setTimeout(() => card.remove(), 5000);
                    } catch (err) {
                        alert('Gagal menyalin ke clipboard. Silakan copy manual:\n' + text);
                    }
                }

                // Checkbox Logic
                // Checkbox Logic
                document.addEventListener('change', function(e) {
                    // CHECK ALL DI MODAL DETAIL
                    if (e.target && e.target.id === 'check-all-detail') {
                        const modal = $('#yppr058-modal') || document;
                        $$('.refresh-check', modal).forEach(cb => {
                            cb.checked = e.target.checked;
                            cb.dispatchEvent(new Event('change', {
                                bubbles: true
                            }));
                        });
                    }

                    // CHECK ALL DI SUMMARY (HALAMAN UTAMA)
                    if (e.target && e.target.id === 'check-all-summary') {
                        $$('.summary-check').forEach(cb => {
                            cb.checked = e.target.checked;
                            cb.dispatchEvent(new Event('change', {
                                bubbles: true
                            }));
                        });
                    }

                    // Checkbox individu di SUMMARY
                    if (e.target && e.target.classList && e.target.classList.contains('summary-check')) {
                        const cb = e.target;
                        registerSummaryCheckbox(cb); // pastikan wcMap terisi
                        const pernr = (cb.value || '').trim();
                        if (!pernr) return;

                        if (cb.checked) {
                            selectedPernrsSet.add(pernr);
                        } else {
                            selectedPernrsSet.delete(pernr);
                        }
                    }
                });

                // Refresh Logic
                let busy = false;

                function setButtonBusy(btn, on) {
                    document.body.classList.toggle('yppr058-refresh-busy', !!on);
                    if (!btn) return;
                    if (on) {
                        if (!btn.dataset.originalHtml) {
                            btn.dataset.originalHtml = btn.innerHTML;
                        }
                        btn.disabled = true;
                        btn.classList.add('is-busy');
                        btn.innerHTML =
                            '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg> Memproses...';
                    } else {
                        btn.disabled = false;
                        btn.classList.remove('is-busy');
                        if (btn.dataset.originalHtml) {
                            btn.innerHTML = btn.dataset.originalHtml;
                        }
                    }
                }

                // ===== REFRESH DETAIL (dari modal) – SAMA seperti sebelumnya =====
                async function refreshSelected() {
                    if (busy) return;
                    const modal = $('#yppr058-modal') || document;
                    const btn = $('#btn-refresh-sap');
                    const checks = $$('.refresh-check:checked', modal).filter(el => el.dataset.pernr && el.dataset
                        .date);

                    if (!checks.length) {
                        alert('Pilih minimal satu baris data.');
                        return;
                    }

                    busy = true;
                    setButtonBusy(btn, true);
                    const total = checks.length;
                    let done = 0,
                        ok = 0,
                        fail = 0;
                    const nikSet = new Set();

                    progressCard(`Menyiapkan ${total} antrian...`);

                    for (const el of checks) {
                        done++;
                        nikSet.add(el.dataset.pernr);
                        updateProgress(done, total);

                        const item = {
                            pernr: el.dataset.pernr,
                            werks: el.dataset.werks || "",
                            arbpl: el.dataset.arbpl || "",
                            begda: el.dataset.date,
                            endda: el.dataset.date
                        };

                        try {
                            const resp = await fetch(API_REFRESH_URL, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    items: [item]
                                }),
                            });
                            const data = await resp.json().catch(() => ({}));
                            if (resp.ok && data.ok && Array.isArray(data.results) && data.results[0]?.ok) {
                                ok++;
                                el.closest('tr')?.classList.add('bg-emerald-100');
                            } else {
                                fail++;
                                el.closest('tr')?.classList.add('bg-red-100');
                            }
                        } catch {
                            fail++;
                            el.closest('tr')?.classList.add('bg-red-100');
                        }
                    }

                    hideProgress();
                    const pernrs = Array.from(nikSet);
                    localStorage.setItem(LS_PREFILL, pernrs.join(' '));
                    localStorage.setItem(LS_SUMMARY, JSON.stringify({
                        ok,
                        fail,
                        total,
                        pernrs,
                        ts: Date.now()
                    }));

                    setButtonBusy(btn, false);
                    busy = false;
                    window.location.reload();
                }

                // ===== REFRESH SUMMARY BULAN INI (group per WC+WERKS, multi NIK per paket) =====
                // ===== REFRESH SUMMARY (bulan penuh / tanggal terakhir) =====
                async function refreshSummarySelected(mode = 'month') {
                    if (busy) return;

                    const btn = $('#btn-refresh-dropdown') || $('#btn-refresh-summary');

                    const root = document.getElementById('yppr058-root');
                    if (!root) {
                        alert('Elemen root tidak ditemukan.');
                        return;
                    }

                    const rangeStart = (root.dataset.rangeStart || '').trim(); // contoh: 20251201
                    const rangeEnd = (root.dataset.rangeEnd || '').trim(); // contoh: 20251217

                    if (!rangeStart || !rangeEnd) {
                        alert('Range tanggal tidak tersedia di halaman.');
                        return;
                    }

                    // 1. Ambil semua NIK yang sudah pernah dicentang di summary
                    const selectedPernrs = Array.from(selectedPernrsSet);
                    if (!selectedPernrs.length) {
                        alert('Pilih minimal satu NIK di tabel ringkasan.');
                        return;
                    }

                    // 2. Grup per (arbpl, werks)
                    const groupsByKey = {};
                    for (const pernr of selectedPernrs) {
                        const meta = wcMap[pernr] || {};
                        const arbpl = (meta.arbpl || '').trim();
                        const werks = (meta.werks || CURRENT_WERKS || '').trim();
                        const key = `${arbpl}||${werks}`;

                        if (!groupsByKey[key]) {
                            groupsByKey[key] = {
                                arbpl,
                                werks,
                                pernrs: []
                            };
                        }
                        if (!groupsByKey[key].pernrs.includes(pernr)) {
                            groupsByKey[key].pernrs.push(pernr);
                        }
                    }

                    const pernrs = Object.values(groupsByKey).flatMap(g => g.pernrs);
                    if (!pernrs.length) {
                        alert('Pilih minimal satu NIK di tabel ringkasan.');
                        return;
                    }

                    // 3. Susun list tanggal berdasarkan rangeStart & rangeEnd
                    const year = parseInt(rangeStart.slice(0, 4), 10);
                    const month = parseInt(rangeStart.slice(4, 6), 10);
                    const startDay = parseInt(rangeStart.slice(6, 8), 10);
                    const endDay = parseInt(rangeEnd.slice(6, 8), 10);

                    const pad2 = n => n.toString().padStart(2, '0');
                    const dates = [];

                    if (mode === 'last-day') {
                        // hanya tanggal terakhir
                        dates.push(rangeEnd);
                    } else {
                        // semua tanggal dari start..end
                        for (let d = endDay; d >= startDay; d--) {
                            dates.push(`${year}${pad2(month)}${pad2(d)}`);
                        }
                    }

                    if (!dates.length) {
                        alert('Tidak ada tanggal yang valid untuk di-refresh.');
                        return;
                    }

                    // 4. Build items: setiap group (WC+WERKS) × setiap tanggal
                    const items = [];
                    for (const group of Object.values(groupsByKey)) {
                        const {
                            arbpl,
                            werks,
                            pernrs: groupPernrs
                        } = group;
                        if (!groupPernrs.length) continue;

                        for (const ymd of dates) {
                            items.push({
                                pernr: groupPernrs[0], // dipakai di log backend
                                pernrs: groupPernrs, // multi NIK satu paket
                                werks: werks || CURRENT_WERKS || "",
                                arbpl: arbpl || "",
                                begda: ymd,
                                endda: ymd,
                            });
                        }
                    }

                    if (!items.length) {
                        alert('Tidak ada item yang valid untuk di-refresh.');
                        return;
                    }

                    // 5. Kirim ke API Flask
                    busy = true;
                    setButtonBusy(btn, true);

                    const total = items.length;
                    let done = 0,
                        ok = 0,
                        fail = 0;

                    progressCard(`Menyiapkan ${total} antrian...`);

                    for (const item of items) {
                        done++;
                        updateProgress(done, total);

                        try {
                            const resp = await fetch(API_REFRESH_URL, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    items: [item]
                                }),
                            });
                            const data = await resp.json().catch(() => ({}));
                            if (resp.ok && data.ok && Array.isArray(data.results) && data.results[0]?.ok) {
                                ok++;
                            } else {
                                fail++;
                            }
                        } catch {
                            fail++;
                        }
                    }

                    hideProgress();

                    // simpan info untuk prefill + toast setelah reload
                    localStorage.setItem(LS_PREFILL, pernrs.join(' '));
                    localStorage.setItem(LS_SUMMARY, JSON.stringify({
                        ok,
                        fail,
                        total,
                        pernrs,
                        ts: Date.now(),
                    }));

                    setButtonBusy(btn, false);
                    busy = false;
                    window.location.reload();
                }


                // Event click: bedakan tombol detail vs summary
                document.addEventListener('click', function(e) {
                    const detailBtn = e.target && e.target.closest && e.target.closest('#btn-refresh-sap');
                    if (detailBtn) {
                        if (busy || detailBtn.disabled) {
                            e.preventDefault();
                            e.stopPropagation();
                            return;
                        }
                        refreshSelected();
                        return;
                    }

                    const summaryBtn = e.target && e.target.closest && e.target.closest('#btn-refresh-dropdown');
                    if (summaryBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        toggleRefreshMenu();
                        return;
                    }

                    // klik menu: Refresh bulan ini
                    const monthBtn = e.target && e.target.closest && e.target.closest('#btn-refresh-month');
                    if (monthBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        toggleRefreshMenu();
                        if (!(busy || monthBtn.disabled)) {
                            refreshSummarySelected('month');
                        }
                        return;
                    }

                    // klik menu: Refresh tanggal terakhir
                    const lastBtn = e.target && e.target.closest && e.target.closest('#btn-refresh-lastday');
                    if (lastBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        toggleRefreshMenu();
                        if (!(busy || lastBtn.disabled)) {
                            refreshSummarySelected('last-day');
                        }
                        return;
                    }
                }, true);

                function afterReloadTasks() {
                    const qVal = localStorage.getItem(LS_PREFILL);
                    if (qVal) {
                        const inp = document.getElementById('q-input');
                        if (inp) {
                            inp.value = qVal;
                            inp.dispatchEvent(new Event('input', {
                                bubbles: true
                            }));
                        }
                        localStorage.removeItem(LS_PREFILL);
                    }
                    const ss = localStorage.getItem(LS_SUMMARY);
                    if (ss) {
                        try {
                            showSummaryToast(JSON.parse(ss));
                        } catch {}
                        localStorage.removeItem(LS_SUMMARY);
                    }
                }

                window.addEventListener('DOMContentLoaded', afterReloadTasks);
                document.addEventListener('livewire:load', afterReloadTasks);
                document.addEventListener('livewire:load', function() {
                    // setiap pesan Livewire selesai diproses -> periksa ulang checkbox summary
                    if (window.Livewire && typeof window.Livewire.hook === 'function') {
                        window.Livewire.hook('message.processed', () => {
                            rescanSummaryCheckboxes();
                        });
                    }
                });
            })
            ();

            function toggleRefreshMenu() {
                const menu = document.getElementById('refresh-menu');
                if (!menu) return;
                menu.classList.toggle('hidden');
            }

            // Tutup dropdown kalau klik di luar
            document.addEventListener('click', function(e) {
                const menu = document.getElementById('refresh-menu');
                const btn = document.getElementById('btn-refresh-dropdown');
                if (!menu || !btn) return;

                if (!menu.contains(e.target) && !btn.contains(e.target)) {
                    menu.classList.add('hidden');
                }
            });
        </script>
    @endpush
@endonce
