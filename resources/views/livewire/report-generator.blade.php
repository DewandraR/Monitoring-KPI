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
<div
    class="bg-white overflow-hidden shadow-[0_25px_50px_-12px_rgba(0,0,0,0.15)] sm:rounded-xl p-8 border border-emerald-50 relative">

    {{-- DEKORASI LATAR BELAKANG --}}
    <div
        class="absolute top-0 right-0 -mt-4 -mr-4 w-64 h-64 bg-gradient-to-br from-emerald-100/40 to-transparent rounded-full blur-3xl pointer-events-none">
    </div>

    {{-- ======================================================================== --}}
    {{-- BAGIAN 1: HEADER MEWAH & TOMBOL EXPORT --}}
    {{-- ======================================================================== --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-8 relative z-10">

        {{-- JUDUL HALAMAN --}}
        <div>
            <h3
                class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-emerald-800 to-teal-600 tracking-tight drop-shadow-sm">
                {{ __('Report Data') }} <span class="text-emerald-900/20 font-light">—</span> yppr058_data
            </h3>
            <p class="mt-1.5 text-sm text-slate-500">
                Plant terpilih:
                <span
                    class="font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">{{ $werks ?? request()->route('werks') }}</span>
            </p>
            <p class="mt-0.5 text-xs text-gray-400">
                (Ringkasan per Personal No.)
            </p>
        </div>

        {{-- TOMBOL AKSI MODERN (HORIZONTAL LAYOUT) --}}
        <div class="flex items-center gap-3">

            {{-- BARIS HORIZONTAL: SEMUA TOMBOL --}}
            <div class="flex items-center gap-2.5">

                {{-- TOMBOL REFRESH BULAN INI --}}
                <button id="btn-refresh-summary" type="button"
                    class="group relative inline-flex items-center gap-2.5 rounded-xl 
                   bg-gradient-to-r from-emerald-600 via-emerald-700 to-teal-700
                   px-5 py-3 text-sm font-bold text-white 
                   shadow-lg shadow-emerald-600/30 
                   ring-1 ring-emerald-500/20
                   transition-all duration-300 ease-out 
                   hover:shadow-2xl hover:shadow-emerald-600/40 hover:scale-[1.02]
                   hover:ring-emerald-400/40
                   focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2
                   disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100">

                    {{-- Glow Effect --}}
                    <div
                        class="absolute inset-0 rounded-xl bg-gradient-to-r from-emerald-400/0 via-white/25 to-emerald-400/0 
                        opacity-0 group-hover:opacity-100 blur-xl transition-opacity duration-500">
                    </div>

                    <svg xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5 relative z-10 transition-transform duration-300 group-hover:rotate-180"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>

                    <span class="relative z-10">Refresh Bulan Ini</span>

                    @if ($selectedCount > 0)
                        <span
                            class="flex h-6 w-6 items-center justify-center rounded-lg 
                             bg-white text-emerald-700 text-[11px] font-black 
                             shadow-md ring-2 ring-emerald-500/30 relative z-10
                             transition-transform duration-300 group-hover:scale-110 group-hover:ring-emerald-400/50">
                            {{ $selectedCount }}
                        </span>
                    @endif
                </button>

                {{-- TOMBOL COPY NIK --}}
                <button id="btn-copy-nik" type="button"
                    class="group relative inline-flex items-center gap-2.5 rounded-xl
                   bg-gradient-to-r from-teal-600 via-emerald-600 to-emerald-700
                   px-5 py-3 text-sm font-bold text-white
                   shadow-lg shadow-teal-600/30
                   ring-1 ring-teal-500/20
                   transition-all duration-300 ease-out
                   hover:shadow-2xl hover:shadow-teal-600/40 hover:scale-[1.02]
                   hover:ring-teal-400/40
                   focus:outline-none focus:ring-2 focus:ring-teal-400 focus:ring-offset-2
                   disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100">

                    {{-- Glow Effect --}}
                    <div
                        class="absolute inset-0 rounded-xl bg-gradient-to-r from-teal-400/0 via-white/25 to-teal-400/0 
                        opacity-0 group-hover:opacity-100 blur-xl transition-opacity duration-500">
                    </div>

                    <svg xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5 relative z-10 transition-all duration-300 group-hover:scale-110" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>

                    <span class="relative z-10">Copy NIK</span>

                    @if ($selectedCount > 0)
                        <span
                            class="flex h-6 w-6 items-center justify-center rounded-lg 
                             bg-white text-teal-700 text-[11px] font-black 
                             shadow-md ring-2 ring-teal-500/30 relative z-10
                             transition-transform duration-300 group-hover:scale-110 group-hover:ring-teal-400/50">
                            {{ $selectedCount }}
                        </span>
                    @endif
                </button>

                {{-- DIVIDER --}}
                <div class="h-10 w-px bg-gradient-to-b from-transparent via-gray-300 to-transparent"></div>

                {{-- EXPORT REPORT (SUMMARY) --}}
                <div class="relative inline-block text-left">
                    <button id="export-dropdown-button" type="button"
                        class="group relative inline-flex items-center gap-2.5 rounded-xl
                       bg-gradient-to-r from-emerald-700 via-emerald-800 to-teal-900
                       px-5 py-3 text-sm font-bold text-white
                       shadow-lg shadow-emerald-700/30
                       ring-1 ring-emerald-600/20
                       transition-all duration-300 ease-out
                       hover:shadow-2xl hover:shadow-emerald-700/40 hover:scale-[1.02]
                       hover:ring-emerald-500/40
                       focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">

                        {{-- Glow Effect --}}
                        <div
                            class="absolute inset-0 rounded-xl bg-gradient-to-r from-emerald-500/0 via-white/20 to-emerald-500/0 
                            opacity-0 group-hover:opacity-100 blur-xl transition-opacity duration-500">
                        </div>

                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 relative z-10" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>

                        <span class="relative z-10">Export Report</span>

                        @if ($selectedCount > 0)
                            <span
                                class="flex h-6 w-6 items-center justify-center rounded-lg 
                                 bg-white text-emerald-800 text-[11px] font-black 
                                 shadow-md ring-2 ring-emerald-600/30 relative z-10
                                 transition-transform duration-300 group-hover:scale-110 group-hover:ring-emerald-500/50">
                                {{ $selectedCount }}
                            </span>
                        @endif

                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4 relative z-10 transition-transform duration-300 group-hover:rotate-180"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    {{-- Dropdown Menu - Tema Hijau --}}
                    <div id="export-dropdown-menu"
                        class="hidden absolute right-0 mt-2 w-52 origin-top-right 
                       rounded-xl bg-white shadow-2xl ring-1 ring-emerald-900/10
                       focus:outline-none z-50 overflow-hidden
                       border border-emerald-100">

                        <div
                            class="px-4 py-2.5 text-[10px] font-bold uppercase tracking-widest 
                            text-emerald-700 bg-gradient-to-r from-emerald-50 to-teal-50 
                            border-b border-emerald-100">
                            📊 Summary Report
                        </div>

                        <div class="p-2">
                            {{-- PDF Option --}}
                            <button type="button" wire:click="export('pdf')"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 
                               text-sm font-semibold text-gray-700 
                               transition-all hover:bg-red-50 hover:text-red-700 
                               group/item">
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-lg 
                                    bg-gradient-to-br from-red-100 to-red-200 text-red-600 
                                    shadow-sm group-hover/item:shadow-md 
                                    group-hover/item:scale-110 transition-all">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <span>Download PDF</span>
                            </button>

                            {{-- Excel Option --}}
                            <button type="button" wire:click="export('excel')"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 
                               text-sm font-semibold text-gray-700 
                               transition-all hover:bg-emerald-50 hover:text-emerald-700 
                               group/item">
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-lg 
                                    bg-gradient-to-br from-emerald-100 to-emerald-200 text-emerald-600 
                                    shadow-sm group-hover/item:shadow-md 
                                    group-hover/item:scale-110 transition-all">
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

                {{-- EXPORT DETAIL --}}
                <div class="relative inline-block text-left">
                    <button id="export-detail-dropdown-button" type="button"
                        class="group relative inline-flex items-center gap-2.5 rounded-xl
                       bg-gradient-to-r from-slate-700 via-slate-800 to-gray-900
                       px-5 py-3 text-sm font-bold text-white
                       shadow-lg shadow-slate-700/30
                       ring-1 ring-slate-600/20
                       transition-all duration-300 ease-out
                       hover:shadow-2xl hover:shadow-slate-700/40 hover:scale-[1.02]
                       hover:ring-slate-500/40
                       focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">

                        {{-- Glow Effect --}}
                        <div
                            class="absolute inset-0 rounded-xl bg-gradient-to-r from-slate-500/0 via-white/15 to-slate-500/0 
                            opacity-0 group-hover:opacity-100 blur-xl transition-opacity duration-500">
                        </div>

                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>

                        <span>Export Detail</span>

                        @if ($detailSelectedCount > 0)
                            <span
                                class="flex h-5 w-5 items-center justify-center rounded-full 
                                 bg-white/90 text-slate-700 text-[10px] font-black 
                                 shadow-sm ring-1 ring-slate-900/10
                                 transition-transform duration-300 group-hover:scale-110">
                                {{ $detailSelectedCount }}
                            </span>
                        @endif

                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-3.5 w-3.5 transition-transform duration-300 group-hover:rotate-180"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    {{-- Dropdown Menu --}}
                    <div id="export-detail-dropdown-menu"
                        class="hidden absolute right-0 mt-2 w-48 origin-top-right 
                       rounded-lg bg-white shadow-xl ring-1 ring-black/5 
                       focus:outline-none z-50 overflow-hidden
                       border border-gray-100">

                        <div
                            class="px-3 py-2 text-[10px] font-bold uppercase tracking-wider 
                            text-gray-500 bg-gray-50 border-b border-gray-100">
                            Detail Report
                        </div>

                        <div class="p-1">
                            {{-- PDF Option --}}
                            <button type="button" wire:click="exportDetail('pdf')"
                                class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 
                               text-sm font-medium text-gray-700 
                               transition-colors hover:bg-red-50 hover:text-red-700 
                               group/item">
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

                            {{-- Excel Option --}}
                            <button type="button" wire:click="exportDetail('excel')"
                                class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 
                               text-sm font-medium text-gray-700 
                               transition-colors hover:bg-emerald-50 hover:text-emerald-700 
                               group/item">
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
    {{-- BAGIAN 3: TABEL RINGKASAN --}}
    {{-- ======================================================================== --}}
    <div wire:key="summary-{{ md5(($werks ?? request()->route('werks')) . '|' . $q . '|' . (int) $onlyInduk) }}">

        <div class="overflow-x-auto overflow-y-auto max-h-[75vh] shadow-xl sm:rounded-xl border border-gray-200/75">
            <table class="min-w-full divide-y divide-gray-200">
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
                            class="sticky left-0 z-30 px-6 py-4 text-left text-sm font-bold uppercase tracking-wider w-10 bg-emerald-800">
                            <label class="inline-flex items-center gap-2 select-none cursor-pointer group">
                                <input id="check-all-summary" type="checkbox"
                                    class="rounded border-gray-300 text-emerald-500 focus:ring-emerald-500 transition-colors cursor-pointer bg-white/90 h-4 w-4"
                                    @checked($allCurrentSelected)>
                                {{-- Tulisan "Pilih" DIHAPUS --}}
                            </label>
                        </th>

                        @foreach ($headersSummary as $header)
                            <th scope="col"
                                class="px-6 py-4 text-left text-sm font-bold uppercase tracking-wider whitespace-nowrap">
                                {{ __($header) }}
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200 text-base">
                    @forelse ($reportData as $data)
                        <tr wire:key="report-row-{{ $data->pernr }}"
                            wire:click="showPernrDetail({{ \Illuminate\Support\Js::from((string) $data->pernr) }})"
                            class="group/row transition-all duration-200 ease-in-out hover:bg-emerald-50 cursor-pointer odd:bg-white even:bg-slate-50/50">

                            {{-- Checkbox STICKY LEFT - Body --}}
                            {{-- Menggunakan group-even/row untuk mencocokkan warna row saat sel ini sticky --}}
                            <td
                                class="sticky left-0 z-10 px-6 py-4 whitespace-nowrap bg-white group-even/row:bg-slate-50/50 group-hover/row:bg-emerald-50">
                                <input type="checkbox" wire:model.live="selectedPernrs"
                                    value="{{ (string) $data->pernr }}" data-arbpl="{{ $data->arbpl }}"
                                    data-werks="{{ $data->werks ?? ($werks ?? request()->route('werks')) }}"
                                    class="summary-check rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer h-5 w-5"
                                    wire:click.stop>
                            </td>

                            {{-- NO --}}
                            <td class="px-6 py-4 whitespace-nowrap font-extrabold text-emerald-800/80">
                                {{ $loop->iteration }}
                            </td>

                            {{-- PERSONAL NO --}}
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">
                                {{ $data->pernr }}
                            </td>

                            {{-- RENTANG TANGGAL --}}
                            <td class="px-6 py-4 whitespace-nowrap text-gray-600 font-mono text-sm">
                                {{ Carbon::createFromFormat('Ymd', $data->min_begda)->isoFormat('YY-MM-DD') }}
                                <span class="text-emerald-400 mx-1">➜</span>
                                {{ Carbon::createFromFormat('Ymd', $data->max_begda)->isoFormat('YY-MM-DD') }}
                            </td>

                            {{-- NAMA --}}
                            <td class="px-6 py-4 whitespace-nowrap font-semibold text-slate-800 capitalize">
                                {{ strtolower($data->cname) }}
                            </td>

                            {{-- WC PERSONAL --}}
                            <td class="px-6 py-4 whitespace-nowrap text-slate-700 font-medium">
                                {{ $data->arbpl }}
                            </td>

                            {{-- WC KONFIRMASI (arbpl2) --}}
                            <td class="px-6 py-4 whitespace-nowrap text-slate-700 font-medium">
                                {{ $data->arbpl2 ?? '-' }}
                            </td>

                            {{-- DESC WC --}}
                            <td class="px-6 py-4 text-slate-600 text-sm min-w-[250px]">
                                {{ Str::limit($data->desc, 40) }}
                            </td>

                            {{-- ROLE --}}
                            <td class="px-6 py-4 whitespace-nowrap text-center text-xs font-semibold">
                                {{ $data->role ?? '-' }}
                            </td>

                            {{-- DEVISI --}}
                            <td class="px-6 py-4 whitespace-nowrap text-slate-700 text-sm">
                                {{ $data->devisi ?? '-' }}
                            </td>

                            {{-- MENIT HADIR (total_jam) --}}
                            <td class="px-6 py-4 whitespace-nowrap text-gray-800 text-right font-mono tracking-tight">
                                {{ number_format($data->total_jam, 1) }}
                            </td>

                            {{-- MENIT CONF (mint2) --}}
                            <td class="px-6 py-4 whitespace-nowrap text-gray-800 text-right font-mono tracking-tight">
                                {{ (int) $data->mint2 }}
                            </td>

                            {{-- MENIT INSPECT (mintu) --}}
                            <td class="px-6 py-4 whitespace-nowrap text-gray-800 text-right font-mono tracking-tight">
                                {{ (int) $data->mintu }}
                            </td>

                            {{-- DETIK INSPECT (mintu2) --}}
                            <td class="px-6 py-4 whitespace-nowrap text-gray-800 text-right font-mono tracking-tight">
                                {{ (int) $data->mintu2 }}
                            </td>

                            {{-- DETIK KONFIRMASI (mintu3) --}}
                            <td class="px-6 py-4 whitespace-nowrap text-gray-800 text-right font-mono tracking-tight">
                                {{ (int) $data->mintu3 }}
                            </td>

                            {{-- UPAH HADIR (gji) --}}
                            <td class="px-6 py-4 whitespace-nowrap text-right font-mono text-gray-800 tracking-tight">
                                {{ number_format((float) $data->gji, 2) }}
                            </td>

                            {{-- UPAH INSPECT (gji2) --}}
                            <td class="px-6 py-4 whitespace-nowrap text-right font-mono text-gray-800 tracking-tight">
                                {{ number_format((float) $data->gji2, 2) }}
                            </td>

                            {{-- VAR UPAH (varnt) --}}
                            <td
                                class="px-6 py-4 whitespace-nowrap text-right font-mono {{ $data->varnt < 0 ? 'text-red-600 font-bold' : 'text-gray-800' }}">
                                {{ number_format($data->varnt, 2) }}
                            </td>

                            {{-- PERSENTASE VAR (varnt1) --}}
                            <td class="px-6 py-4 whitespace-nowrap text-right font-mono">
                                <span
                                    class="inline-flex items-center px-2.5 py-1 rounded text-sm font-medium {{ $data->varnt1 < 100 ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800' }}">
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
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="sticky top-0 z-20 bg-emerald-50">
                                    <tr>
                                        {{-- Checkbox Detail STICKY LEFT - Header --}}
                                        <th
                                            class="sticky left-0 z-30 px-6 py-4 text-left text-xs font-bold text-emerald-800 uppercase tracking-wider border-b-2 border-emerald-100 bg-emerald-50">
                                            <label class="inline-flex items-center gap-2 select-none">
                                                <input id="check-all-detail" type="checkbox"
                                                    class="rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500">
                                                {{-- Tulisan "Pilih" DIHAPUS --}}
                                            </label>
                                        </th>

                                        @foreach ($headersDetail as $header)
                                            <th scope="col"
                                                class="px-6 py-4 text-left text-xs font-bold text-emerald-800 uppercase tracking-wider border-b-2 border-emerald-100 whitespace-nowrap">
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

                                            <td class="px-6 py-3 whitespace-nowrap text-sm font-bold text-emerald-800">
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

                                                        if ($gji != 0) {
                                                            $val = ($varnt / $gji) * 100;
                                                        } else {
                                                            // kalau upah hadir 0, anggap 0% (atau bisa null kalau mau "-")
                                                            $val = 0;
                                                        }
                                                    }

                                                    $isMoney = in_array($column, ['gji', 'gji2', 'varnt']);
                                                    $isNum = in_array($column, ['mint2', 'mintu', 'mintu2', 'mintu3']);
                                                    $isDate = $column === 'begda';
                                                @endphp

                                                <td @class([
                                                    'px-6 py-3 whitespace-nowrap text-sm',
                                                    'text-right font-mono' => $isMoney || $isNum || $column === 'total_jam',
                                                    'text-emerald-700 font-medium' => $isMoney,
                                                ])>
                                                    @if ($val === '' || is_null($val))
                                                        -
                                                    @elseif ($column === 'total_jam')
                                                        {{ number_format($val, 1) }}
                                                    @elseif ($isMoney)
                                                        {{ number_format($val, 2) }}
                                                    @elseif ($isNum)
                                                        {{ (int) $val }}
                                                    @elseif ($isDate)
                                                        {{ Carbon::createFromFormat('Ymd', $val)->isoFormat('YY-MM-DD') }}
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

    {{-- CSS ANIMASI --}}
    <style>
        @keyframes shine {
            0% {
                transform: translateX(-150%) skewX(-20deg);
            }

            100% {
                transform: translateX(150%) skewX(-20deg);
            }
        }

        .animate-shine {
            animation: shine 3s infinite;
        }
    </style>
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
                const CURRENT_WERKS = @json($werks ?? request()->route('werks'));
                const LS_PREFILL = 'yppr058_prefill_q';
                const LS_SUMMARY = 'yppr058_refresh_summary';
                const $ = (sel, root = document) => root.querySelector(sel);
                const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

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
                        if (!exportDetailMenu.classList.contains('hidden') && !exportDetailBtn.contains(e.target) &&
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
                            const resp = await fetch(`${API_BASE}/api/yppr058/refresh`, {
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
                async function refreshSummarySelected() {
                    if (busy) return;

                    const btn = $('#btn-refresh-summary');

                    // 1. Ambil semua NIK yang sudah pernah dicentang (cache)
                    const selectedPernrs = Array.from(selectedPernrsSet);
                    if (!selectedPernrs.length) {
                        alert('Pilih minimal satu NIK di tabel ringkasan.');
                        return;
                    }

                    // 2. Bangun grup per (arbpl, werks)
                    //    key = "<arbpl>||<werks>"
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
                                pernrs: [],
                            };
                        }

                        if (!groupsByKey[key].pernrs.includes(pernr)) {
                            groupsByKey[key].pernrs.push(pernr);
                        }
                    }

                    // 3. Daftar semua NIK (flatten) untuk dipakai di localStorage / toast
                    const pernrs = Object.values(groupsByKey)
                        .flatMap(group => group.pernrs);

                    if (!pernrs.length) {
                        alert('Pilih minimal satu NIK di tabel ringkasan.');
                        return;
                    }

                    // 4. Hitung rentang tanggal (1..H-1 atau full bulan lalu kalau hari ini tgl 1)
                    const today = new Date();
                    let year = today.getFullYear();
                    let month = today.getMonth() + 1; // 1..12
                    const todayDate = today.getDate();
                    let startDay = 1;
                    let endDay;

                    if (todayDate === 1) {
                        // Kalau hari ini tgl 1 -> pakai full bulan sebelumnya
                        month -= 1;
                        if (month === 0) {
                            month = 12;
                            year -= 1;
                        }
                        endDay = new Date(year, month, 0).getDate(); // last day bulan tsb
                    } else {
                        // 1 .. H-1 (kemarin)
                        endDay = todayDate - 1;
                    }

                    if (endDay < startDay) {
                        alert('Rentang tanggal kosong. Tidak ada hari yang perlu di-refresh.');
                        return;
                    }

                    const pad2 = n => n.toString().padStart(2, '0');
                    const items = [];

                    // 5. Bentuk kombinasi: setiap GROUP (WC+WERKS) × setiap tanggal
                    for (const group of Object.values(groupsByKey)) {
                        const {
                            arbpl,
                            werks,
                            pernrs: groupPernrs
                        } = group;
                        if (!groupPernrs.length) continue;

                        for (let d = endDay; d >= startDay; d--) {
                            const ymd = `${year}${pad2(month)}${pad2(d)}`;

                            items.push({
                                // pernr pertama hanya untuk keperluan log di backend
                                pernr: groupPernrs[0],
                                // <<< multi NIK dalam 1 paket
                                pernrs: groupPernrs,
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

                    // 6. Kirim ke API Flask, tetap satu per item (per WC+tanggal)
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
                            const resp = await fetch(`${API_BASE}/api/yppr058/refresh`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    items: [item] // <-- 1 paket (bisa banyak NIK) per request
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

                    // 7. Simpan info ke localStorage (supaya setelah reload, search + toast tetap jalan)
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

                    const summaryBtn = e.target && e.target.closest && e.target.closest('#btn-refresh-summary');
                    if (summaryBtn) {
                        if (busy || summaryBtn.disabled) {
                            e.preventDefault();
                            e.stopPropagation();
                            return;
                        }
                        refreshSummarySelected();
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
        </script>
    @endpush
@endonce
