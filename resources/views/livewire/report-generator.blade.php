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
        'DESC WC',
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
        'DESC WC',
        'Menit Hadir',
        'Menit Conf',
        'Menit Inspect',
        'Detik Inspect',
        'Detik Konfirmasi',
        'Upah Hadir', // gji
        'Upah Inspect', // gji2
        'Var Upah',
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

        {{-- TOMBOL EXPORT (SUMMARY + DETAIL) --}}
        <div class="flex flex-col items-end">
            <div class="flex flex-col sm:flex-row items-end gap-3">

                {{-- EXPORT SUMMARY --}}
                <div class="relative inline-block text-left group">
                    {{-- Tombol Utama --}}
                    <button id="export-dropdown-button" type="button"
                        class="group relative inline-flex items-center gap-3 rounded-full 
                               bg-gradient-to-br from-emerald-600 via-emerald-700 to-teal-800 
                               px-6 py-2.5 text-sm font-bold text-white shadow-lg shadow-emerald-600/30 
                               ring-1 ring-white/20 transition-all duration-300 ease-out 
                               hover:scale-[1.02] hover:shadow-emerald-600/50 hover:ring-white/40 hover:from-emerald-500 hover:to-teal-700
                               focus:outline-none focus:ring-4 focus:ring-emerald-500/30">

                        {{-- Animasi Kilau --}}
                        <div
                            class="absolute inset-0 rounded-full bg-gradient-to-r from-transparent via-white/20 to-transparent opacity-0 group-hover:animate-shine pointer-events-none">
                        </div>

                        {{-- Icon Download --}}
                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5 text-emerald-100 transition-transform duration-300 group-hover:-translate-y-0.5"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>

                        <span class="tracking-wide text-shadow-sm">Export Report</span>

                        {{-- Badge Jumlah Terpilih (summary) --}}
                        @if ($selectedCount > 0)
                            <span
                                class="flex h-6 w-6 items-center justify-center rounded-full bg-white text-emerald-700 text-[10px] font-black shadow-inner shadow-gray-200 transition-transform duration-300 group-hover:scale-110">
                                {{ $selectedCount }}
                            </span>
                        @endif

                        {{-- Icon Chevron --}}
                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4 text-emerald-200/70 transition-transform duration-300 group-hover:rotate-180"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    {{-- Dropdown Menu --}}
                    <div id="export-dropdown-menu"
                        class="hidden absolute right-0 mt-3 w-52 origin-top-right rounded-xl bg-white p-2 shadow-2xl shadow-emerald-900/10 ring-1 ring-black/5 focus:outline-none z-50 transform transition-all duration-200 border border-gray-100">

                        <div
                            class="px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-gray-400 border-b border-gray-100 mb-1">
                            Summary Report
                        </div>

                        {{-- PDF Option --}}
                        <button type="button" wire:click="export('pdf')"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-700 transition-all hover:bg-red-50 hover:text-red-700 group/item mb-1">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-red-100 text-red-600 group-hover/item:bg-red-200 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <span>Download PDF</span>
                        </button>

                        {{-- Excel Option --}}
                        <button type="button" wire:click="export('excel')"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-700 transition-all hover:bg-emerald-50 hover:text-emerald-700 group/item">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 group-hover/item:bg-emerald-200 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <span>Download Excel</span>
                        </button>
                    </div>
                </div>

                {{-- EXPORT DETAIL (di luar modal) --}}
                <div class="relative inline-block text-left group">
                    <button id="export-detail-dropdown-button" type="button"
                        class="group relative inline-flex items-center gap-3 rounded-full 
                               bg-gradient-to-br from-slate-600 via-slate-700 to-slate-800 
                               px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-slate-600/30 
                               ring-1 ring-white/20 transition-all duration-300 ease-out 
                               hover:scale-[1.02] hover:shadow-slate-600/50 hover:ring-white/40 hover:from-slate-500 hover:to-slate-700
                               focus:outline-none focus:ring-4 focus:ring-slate-500/30">

                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5 text-emerald-100 transition-transform duration-300 group-hover:-translate-y-0.5"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>

                        <span class="tracking-wide text-shadow-sm">Export Detail</span>

                        {{-- Badge jumlah NIK untuk detail --}}
                        @if ($detailSelectedCount > 0)
                            <span
                                class="flex h-6 w-6 items-center justify-center rounded-full bg-white text-slate-700 text-[10px] font-black shadow-inner shadow-gray-200 transition-transform duration-300 group-hover:scale-110">
                                {{ $detailSelectedCount }}
                            </span>
                        @endif

                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4 text-emerald-200/70 transition-transform duration-300 group-hover:rotate-180"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div id="export-detail-dropdown-menu"
                        class="hidden absolute right-0 mt-3 w-60 origin-top-right rounded-xl bg-white p-2 shadow-2xl shadow-slate-900/10 ring-1 ring-black/5 focus:outline-none z-50 transform transition-all duration-200 border border-gray-100">

                        <div
                            class="px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-gray-400 border-b border-gray-100 mb-1">
                            Detail Tanggal (multi NIK)
                        </div>

                        <button type="button" wire:click="exportDetail('pdf')"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-700 transition-all hover:bg-red-50 hover:text-red-700 group/item mb-1">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-red-100 text-red-600 group-hover/item:bg-red-200 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <span>Export Detail PDF</span>
                        </button>

                        <button type="button" wire:click="exportDetail('excel')"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-700 transition-all hover:bg-emerald-50 hover:text-emerald-700 group/item">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 group-hover/item:bg-emerald-200 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <span>Export Detail Excel</span>
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
                    <span class="font-semibold text-emerald-700">Work Center</span>, atau
                    <span class="font-semibold text-emerald-700">Deskripsi</span>.
                    <br>Gunakan tanda kutip untuk hasil tepat, contoh:
                    <code class="bg-gray-100 px-1 rounded text-emerald-700 font-mono text-xs">"Nama Lengkap"</code>
                    atau
                    <code class="bg-gray-100 px-1 rounded text-emerald-700 font-mono text-xs">"DESC WC"</code>.
                </p>

                @error('q')
                    <span class="text-xs text-red-500 mt-1 ml-1 block font-medium">{{ $message }}</span>
                @enderror
            </div>
        </div>
    </div>

    {{-- ======================================================================== --}}
    {{-- BAGIAN 3: TABEL RINGKASAN --}}
    {{-- ======================================================================== --}}
    <div wire:key="summary-{{ md5(($werks ?? request()->route('werks')) . '|' . $q) }}">

        <div class="overflow-x-auto overflow-y-auto max-h-[75vh] shadow-xl sm:rounded-xl border border-gray-200/75">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="sticky top-0 z-10 bg-gradient-to-r from-emerald-800 to-teal-900 text-white shadow-md">
                    <tr>
                        @php
                            $pagePernrs = array_map('strval', $currentPagePernrs ?? []);
                            $selectedP = array_map('strval', $selectedPernrs ?? []);
                            $allCurrentSelected =
                                !empty($pagePernrs) &&
                                count(array_intersect($pagePernrs, $selectedP)) === count($pagePernrs);
                        @endphp

                        {{-- Kolom checkbox + label (select all) --}}
                        <th scope="col"
                            class="px-6 py-4 text-left text-sm font-bold uppercase tracking-wider w-10">
                            <label class="inline-flex items-center gap-2 select-none cursor-pointer group">
                                <input id="check-all-summary" type="checkbox"
                                    class="rounded border-gray-300 text-emerald-500 focus:ring-emerald-500 transition-colors cursor-pointer bg-white/90 h-4 w-4"
                                    @checked($allCurrentSelected)>
                                <span class="group-hover:text-emerald-100 transition-colors text-xs">Pilih</span>
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
                            class="transition-all duration-200 ease-in-out hover:bg-emerald-50 cursor-pointer odd:bg-white even:bg-slate-50/50">

                            {{-- Checkbox pilih NIK untuk export --}}
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" wire:model.live="selectedPernrs"
                                    value="{{ (string) $data->pernr }}"
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

                            {{-- DESC WC --}}
                            <td class="px-6 py-4 text-slate-600 text-sm min-w-[250px]">
                                {{ Str::limit($data->desc, 40) }}
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
                                <thead class="sticky top-0 z-10 bg-emerald-50">
                                    <tr>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-bold text-emerald-800 uppercase tracking-wider border-b-2 border-emerald-100">
                                            <label class="inline-flex items-center gap-2 select-none">
                                                <input id="check-all-detail" type="checkbox"
                                                    class="rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500">
                                                Pilih
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
                                            class="hover:bg-emerald-50/50 transition-colors">

                                            <td class="px-6 py-3 whitespace-nowrap text-sm">
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
                                                    'desc', // DESC WC

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
                const LS_PREFILL = 'yppr058_prefill_q';
                const LS_SUMMARY = 'yppr058_refresh_summary';
                const $ = (sel, root = document) => root.querySelector(sel);
                const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

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

                // Checkbox Logic
                document.addEventListener('change', function(e) {
                    if (e.target && e.target.id === 'check-all-detail') {
                        const modal = $('#yppr058-modal') || document;
                        $$('.refresh-check', modal).forEach(cb => {
                            cb.checked = e.target.checked;
                            // trigger event supaya wire:model selectedDetailKeys ikut update
                            cb.dispatchEvent(new Event('change', {
                                bubbles: true
                            }));
                        });
                    }
                    if (e.target && e.target.id === 'check-all-summary') {
                        $$('.summary-check').forEach(cb => {
                            cb.checked = e.target.checked;
                            cb.dispatchEvent(new Event('change', {
                                bubbles: true
                            }));
                        });
                    }
                });

                // Refresh Logic
                let busy = false;

                function setButtonBusy(btn, on) {
                    document.body.classList.toggle('yppr058-refresh-busy', !!on);
                    if (!btn) return;
                    if (on) {
                        btn.disabled = true;
                        btn.classList.add('is-busy');
                        btn.innerHTML =
                            '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg> Memproses...';
                    } else {
                        btn.disabled = false;
                        btn.classList.remove('is-busy');
                        btn.innerHTML = 'Refresh dari SAP (terpilih)';
                    }
                }

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

                document.addEventListener('click', function(e) {
                    const targetBtn = e.target && e.target.closest && e.target.closest('#btn-refresh-sap');
                    if (!targetBtn) return;
                    if (busy || targetBtn.disabled) {
                        e.preventDefault();
                        e.stopPropagation();
                        return;
                    }
                    refreshSelected();
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
            })
            ();
        </script>
    @endpush
@endonce
