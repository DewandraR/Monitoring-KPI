@php
    use Carbon\Carbon;

    // 1. Normalisasi & hitung selection
    $selected = array_map('strval', $selectedPernrs ?? []);
    $selectedCount = count($selected);

    // 2. Ambil Pernr dari halaman saat ini untuk cek "Select All"
    $currentPernrs = $rows->pluck('pernr')->map(fn($p) => (string) $p)->all();

    // 3. Cek apakah semua item di halaman ini sudah terpilih
    $isAllSelected =
        !empty($currentPernrs) && count(array_intersect($currentPernrs, $selected)) === count($currentPernrs);

    // 4. Buat Lookup table
    $selectedSet = array_flip($selected);

    // Opsi Plant (Ditambahkan untuk digunakan oleh Radio Buttons)
    $plantOptions = [
        'ALL' => 'ALL PLANT',
        '1000' => 'Plant 1000',
        '1001' => 'Plant 1001',
        '2000' => 'Plant 2000',
        '3000' => 'Plant 3000',
    ];

    // nilai plant dari Livewire (dipass dari controller)
    $currentPlant = $plant ?? 'ALL';
@endphp

{{-- ROOT ELEMENT DIMULAI DI SINI --}}
<div
    class="bg-white overflow-hidden shadow-[0_25px_50px_-12px_rgba(0,0,0,0.15)] sm:rounded-xl p-8 border border-emerald-50 relative">

    {{-- DEKORASI LATAR BELAKANG --}}
    <div
        class="absolute top-0 right-0 -mt-4 -mr-4 w-64 h-64 bg-gradient-to-br from-emerald-100/40 to-transparent rounded-full blur-3xl pointer-events-none">
    </div>

    {{-- ======================================================================== --}}
    {{-- BAGIAN TAMBAHAN: SYNC WORK CENTER MANUAL --}}
    {{-- ======================================================================== --}}
    <div
        class="mb-6 p-6 bg-white rounded-xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-emerald-100 relative z-20 overflow-visible">


        <div class="relative z-10">
            <h4 class="text-lg font-bold text-emerald-800 mb-1 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                {{ __('Sync Data SAP') }}
            </h4>
            <p class="text-sm text-slate-500 mb-4">
                Tarik data terbaru (Personil, Role Induk, Deskripsi) untuk Work Center tertentu.
            </p>

            <div class="flex flex-col sm:flex-row items-end gap-4">
                {{-- INPUT WORK CENTER --}}
                <div class="w-full sm:w-1/3">
                    <label for="manual-arbpl" class="block text-xs font-bold text-gray-500 uppercase mb-1 ml-1">Work
                        Center</label>
                    <input type="text" id="manual-arbpl" placeholder="Contoh: WC007"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm uppercase font-mono font-bold h-10 placeholder:font-sans placeholder:font-normal">
                </div>

                {{-- INPUT PLANT (CUSTOM DROPDOWN) --}}
                <div class="w-full sm:w-1/4">
                    <label for="wc-plant-dropdown-button"
                        class="block text-xs font-bold text-emerald-700 uppercase mb-1 ml-1">Plant</label>

                    <div class="relative" id="wc-plant-dropdown">
                        {{-- Tombol tampilan --}}
                        <button type="button" id="wc-plant-dropdown-button"
                            class="inline-flex items-center justify-between w-full
                                        rounded-lg border border-emerald-300
                                        bg-gradient-to-r from-emerald-50 via-white to-teal-50
                                        px-3 py-2 h-10 text-xs sm:text-sm font-semibold text-emerald-900
                                        shadow-sm transition-all
                                        hover:border-emerald-400 hover:bg-emerald-50/80
                                        focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            <span id="wc-plant-dropdown-label" class="truncate text-emerald-400">
                                Pilih Plant
                            </span>
                            <span class="ml-2 flex items-center justify-center rounded-full bg-emerald-100/80 p-1">
                                <svg xmlns="http://www.w3.org/2000/svg" id="wc-plant-dropdown-chevron"
                                    class="h-4 w-4 text-emerald-600 transition-transform duration-200" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </button>

                        {{-- Menu pilihan --}}
                        <div id="wc-plant-dropdown-menu"
                            class="hidden absolute z-30 mt-1 w-full origin-top rounded-xl
                                        bg-white shadow-2xl shadow-emerald-900/10 ring-1 ring-black/5
                                        border border-emerald-100 overflow-hidden">
                            <div
                                class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-400 bg-emerald-50/70">
                                Pilih Plant
                            </div>

                            @php
                                $plants = [
                                    ['value' => '1000', 'label' => 'Plant 1000'],
                                    ['value' => '1001', 'label' => 'Plant 1001'],
                                    ['value' => '2000', 'label' => 'Plant 2000'],
                                    ['value' => '3000', 'label' => 'Plant 3000'],
                                ];
                            @endphp

                            @foreach ($plants as $plantItem)
                                <button type="button"
                                    class="flex w-full items-center justify-between px-3 py-2 text-xs text-emerald-900
                                            hover:bg-emerald-50 hover:text-emerald-800 transition-colors"
                                    data-value="{{ $plantItem['value'] }}" data-label="{{ $plantItem['label'] }}">
                                    <span class="font-semibold text-emerald-700">
                                        {{ $plantItem['label'] }}
                                    </span>
                                    <span
                                        class="option-check hidden h-4 w-4 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 text-[10px]">
                                        ✓
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- hidden input untuk JS sinkronisasi --}}
                    <input type="hidden" id="manual-werks" value="">
                </div>

                {{-- BUTTON ACTION --}}
                <div class="w-full sm:w-auto">
                    <button type="button" id="btn-manual-sync"
                        class="w-full sm:w-auto inline-flex justify-center items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-md hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-all h-10">
                        <span>Sync Sekarang</span>
                        <svg id="icon-sync-ready" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <svg id="icon-sync-loading" class="animate-spin h-4 w-4 hidden"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z">
                            </path>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- LOG CARD: RINGKASAN PROSES SINKRON --}}
            <div id="manual-sync-log-wrapper" class="mt-4 hidden">
                <div
                    class="relative overflow-hidden rounded-xl border border-emerald-100 bg-gradient-to-br from-emerald-50 via-white to-teal-50 shadow-[0_18px_45px_rgba(16,185,129,0.30)]">
                    {{-- efek glow --}}
                    <div class="pointer-events-none absolute inset-0">
                        <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-emerald-200/40 blur-3xl">
                        </div>
                        <div class="absolute -left-16 bottom-0 h-32 w-32 rounded-full bg-teal-200/40 blur-3xl"></div>
                    </div>

                    <div class="relative p-4 sm:p-5">
                        {{-- HEADER LOG --}}
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-600 text-white shadow-lg shadow-emerald-400/60">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-emerald-600">
                                        Log Sinkronisasi
                                    </p>
                                    <p id="manual-sync-log-title"
                                        class="text-sm sm:text-base font-bold text-emerald-950">
                                        Menunggu permintaan...
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-col items-end gap-1">
                                <span id="manual-sync-status-pill"
                                    class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 text-[10px] font-semibold uppercase text-slate-700">
                                    Idle
                                </span>
                                <span id="manual-sync-time" class="text-[11px] font-mono text-emerald-900/60">-</span>
                            </div>
                        </div>

                        {{-- ISI LOG --}}
                        <div id="manual-sync-feedback"
                            class="mt-4 space-y-1.5 text-xs font-medium leading-relaxed text-slate-700 sm:text-[13px]">
                            {{-- baris log diisi via JS --}}
                        </div>

                        {{-- PROGRESS BAR --}}
                        <div class="mt-4">
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-emerald-100/80">
                                <div id="manual-sync-progress"
                                    class="h-full w-0 rounded-full bg-gradient-to-r from-emerald-500 via-emerald-400 to-teal-500 transition-all duration-500">
                                </div>
                            </div>
                            <div class="mt-1.5 flex items-center justify-between text-[11px] text-slate-500">
                                <span id="manual-sync-progress-label">Menunggu...</span>
                                <span id="manual-sync-progress-percent">0%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- ======================================================================== --}}
    {{-- BAGIAN 1: HEADER MEWAH & TOMBOL EXPORT --}}
    {{-- ======================================================================== --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6 relative z-10">
        {{-- JUDUL HALAMAN --}}
        <div>
            <h3
                class="text-2xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-emerald-800 to-teal-600 tracking-tight drop-shadow-sm">
                {{ __('WC Person') }} <span class="text-emerald-900/20 font-light">—</span> wc_person_data
            </h3>
            <p class="mt-1 text-xs text-slate-500 max-w-2xl leading-relaxed">
                Data Personalia Work Center (Master Data).
            </p>
        </div>

        {{-- TOMBOL EXPORT --}}
        <div class="flex flex-col items-end">
            <div class="relative inline-block text-left group">
                {{-- Tombol Utama --}}
                <button id="wc-export-dropdown-button" type="button"
                    class="group relative inline-flex items-center gap-2 rounded-full 
                            bg-gradient-to-br from-emerald-600 via-emerald-700 to-teal-800 
                            px-4 py-2 text-xs font-bold text-white shadow-lg shadow-emerald-600/30 
                            ring-1 ring-white/20 transition-all duration-300 ease-out 
                            hover:scale-[1.02] hover:shadow-emerald-600/50 hover:ring-white/40 hover:from-emerald-500 hover:to-teal-700
                            focus:outline-none focus:ring-4 focus:ring-emerald-500/30">

                    <div
                        class="absolute inset-0 rounded-full bg-gradient-to-r from-transparent via-white/20 to-transparent opacity-0 group-hover:animate-shine pointer-events-none">
                    </div>

                    <svg xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-4 text-emerald-100 transition-transform duration-300 group-hover:-translate-y-0.5"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>

                    <span class="tracking-wide text-shadow-sm">Export Data</span>

                    @if ($selectedCount > 0)
                        <span
                            class="flex h-5 w-5 items-center justify-center rounded-full bg-white text-emerald-700 text-[9px] font-black shadow-inner shadow-gray-200 transition-transform duration-300 group-hover:scale-110">
                            {{ $selectedCount }}
                        </span>
                    @endif

                    <svg xmlns="http://www.w3.org/2000/svg"
                        class="h-3.5 w-3.5 text-emerald-200/70 transition-transform duration-300 group-hover:rotate-180"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                {{-- Dropdown Menu --}}
                <div id="wc-export-dropdown-menu"
                    class="hidden absolute right-0 mt-3 w-48 origin-top-right rounded-xl bg-white p-2 shadow-2xl shadow-emerald-900/10 ring-1 ring-black/5 focus:outline-none z-50 transform transition-all duration-200 border border-gray-100">
                    <div
                        class="px-3 py-1.5 text-[9px] font-bold uppercase tracking-wider text-gray-400 border-b border-gray-100 mb-1">
                        Pilih Format
                    </div>
                    <button type="button" wire:click.prevent="export('pdf')"
                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-gray-700 transition-all hover:bg-red-50 hover:text-red-700 group/item mb-1">
                        <div
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-red-100 text-red-600 group-hover/item:bg-red-200 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <span>Download PDF</span>
                    </button>
                    <button type="button" wire:click.prevent="export('excel')"
                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-gray-700 transition-all hover:bg-emerald-50 hover:text-emerald-700 group/item">
                        <div
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 group-hover/item:bg-emerald-200 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <span>Download Excel</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ======================================================================== --}}
    {{-- BAGIAN 2: FILTER PENCARIAN (REVISI TATA LETAK RADIO BUTTONS) --}}
    {{-- ======================================================================== --}}
    <div class="mb-6 p-4 bg-emerald-50/50 rounded-xl shadow-inner border border-emerald-100/80 backdrop-blur-sm">

        {{-- Header Filter & Radio Buttons (Dibuat sejajar: Kiri-Kanan) --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-3">
            {{-- Kiri: Judul Filter --}}
            <p class="text-sm font-bold text-emerald-800 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                {{ __('Filter Data:') }}
            </p>

            {{-- Kanan: Radio Buttons Plant --}}
            <div class="mt-2 sm:mt-0 flex flex-wrap gap-x-6 gap-y-2 justify-start sm:justify-end">
                {{-- Label Teks (Dibuat lebih kecil) --}}
                <label class="block text-xs font-bold text-gray-500 uppercase self-center whitespace-nowrap">
                    PILIH PLANT:
                </label>
                {{-- Loop Radio Buttons --}}
                @foreach ($plantOptions as $code => $label)
                    @php
                        $isActive = $currentPlant === $code;
                    @endphp
                    <div class="inline-flex items-center" wire:key="plant-radio-{{ $code }}">
                        <input id="plant-radio-{{ $code }}" name="plant-filter" type="radio"
                            value="{{ $code }}" wire:model.live="plant" @checked($isActive)
                            class="h-4 w-4 text-emerald-600 border-gray-300 focus:ring-emerald-500 cursor-pointer">
                        <label for="plant-radio-{{ $code }}"
                            class="ml-2 block text-sm font-medium cursor-pointer @if ($isActive) text-emerald-700 font-semibold @else text-gray-700 @endif">
                            {{ $label }}
                        </label>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Input kata kunci (Dipisahkan dengan garis tipis untuk tampilan yang lebih rapi) --}}
        <div class="mt-4 border-t border-emerald-100 pt-4">
            <div class="grid grid-cols-1 gap-4">
                <div class="relative group">
                    <input type="text" id="q-input" wire:model.live.debounce.500ms="q" placeholder=" "
                        class="peer block w-full pt-5 pb-1.5 px-3 border-gray-300 text-sm text-gray-900 bg-white rounded-lg shadow-sm focus:border-emerald-500 focus:ring-emerald-500 focus:ring-2 transition-all duration-200 placeholder-transparent h-11" />

                    <label for="q-input"
                        class="absolute text-gray-500 duration-300 transform top-3 left-3 z-10 origin-[0] -translate-y-2.5 scale-75 text-emerald-600 font-bold peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:text-gray-500 peer-placeholder-shown:font-normal peer-focus:scale-75 peer-focus:-translate-y-2.5 peer-focus:text-emerald-600 peer-focus:font-bold">
                        {{ __('Kata Kunci Pencarian') }}
                    </label>

                    <p class="mt-1 text-xs text-gray-500">
                        Cari:
                        <span class="font-semibold text-emerald-700">
                            NIK, Nama, WC, Desc WC, Devisi
                        </span>.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- ======================================================================== --}}
    {{-- BAGIAN 3: TABEL DATA --}}
    {{-- ======================================================================== --}}
    <div class="overflow-y-auto max-h-[75vh] shadow-xl sm:rounded-xl border border-gray-200/75">
        <table class="wc-person-table min-w-full table-fixed divide-y divide-gray-200">
            <colgroup>
                <col class="w-[40px]" />
                <col class="w-[40px]" />
                <col class="w-[90px]" />
                <col class="w-[80px]" />
                <col class="w-[160px]" />
                <col class="w-[80px]" />
                <col class="w-[80px]" />
                <col class="w-[280px]" />
                <col class="w-[120px]" />
                <col class="w-[70px]" />
            </colgroup>
            <thead class="sticky top-0 z-10 bg-gradient-to-r from-emerald-800 to-teal-900 text-white shadow-md">
                <tr>
                    <th scope="col" class="px-2 py-3 text-center text-[11px] font-bold uppercase tracking-wider">
                        <label class="inline-flex items-center gap-1 select-none cursor-pointer group">
                            <input id="wc-check-all" type="checkbox" wire:click="toggleSelectAll"
                                @checked($isAllSelected)
                                class="rounded border-gray-300 text-emerald-500 focus:ring-emerald-500 transition-colors cursor-pointer bg-white/90 h-3.5 w-3.5" />
                        </label>
                    </th>
                    @foreach ($headers as $header)
                        <th scope="col"
                            class="px-2 py-3 text-center text-[11px] font-bold uppercase tracking-wider truncate">
                            {{ __($header) }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($rows as $row)
                    @php
                        $isInduk = isset($row->role) && strtoupper($row->role) === 'INDUK';
                        $pernrKey = (string) $row->pernr;
                        $isChecked = isset($selectedSet[$pernrKey]);
                    @endphp
                    <tr wire:key="wc-row-{{ $row->id }}" @class([
                        'transition-all duration-200 ease-in-out hover:bg-emerald-50',
                        'bg-white' => $loop->odd && !$isChecked,
                        'bg-slate-50/50' => $loop->even && !$isChecked,
                        'bg-emerald-50 ring-1 ring-inset ring-emerald-200' => $isChecked,
                    ])>
                        {{-- Checkbox --}}
                        <td class="px-2 py-2 whitespace-nowrap align-middle text-center">
                            <input type="checkbox" value="{{ $pernrKey }}"
                                wire:click="togglePernr('{{ $pernrKey }}')" @checked($isChecked)
                                class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer h-4 w-4" />
                        </td>

                        {{-- No --}}
                        <td class="px-2 py-2 whitespace-nowrap font-bold text-emerald-800/80 text-center align-middle">
                            {{ $loop->iteration }}
                        </td>

                        {{-- NIK --}}
                        <td
                            class="px-2 py-2 whitespace-nowrap text-slate-700 text-center font-mono tracking-tight align-middle">
                            {{ $row->pernr }}
                        </td>

                        {{-- Tgl Mulai --}}
                        <td class="px-2 py-2 whitespace-nowrap text-slate-500 font-mono text-center align-middle">
                            @if ($row->begda && preg_match('/^\d{8}$/', $row->begda))
                                {{ Carbon::createFromFormat('Ymd', $row->begda)->isoFormat('YY-MM-DD') }}
                            @else
                                {{ $row->begda }}
                            @endif
                        </td>

                        {{-- Nama (tetap rata kiri) --}}
                        <td
                            class="px-2 py-2 text-slate-800 font-semibold whitespace-normal break-words max-w-[160px] align-middle text-left">
                            {{ $row->stext }}
                        </td>

                        {{-- Role --}}
                        <td class="px-2 py-2 whitespace-nowrap align-middle text-center">
                            @if ($isInduk)
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                   bg-gradient-to-b from-yellow-100 to-amber-200
                   text-[11px] font-extrabold text-amber-900
                   shadow-sm border border-amber-300 uppercase tracking-wide">
                                    <span class="leading-none">👑</span>
                                    <span>INDUK</span>
                                </span>
                            @else
                                <span class="text-slate-400 text-[11px]">-</span>
                            @endif
                        </td>

                        {{-- Work Center --}}
                        <td class="px-2 py-2 whitespace-nowrap text-slate-600 font-medium align-middle text-center">
                            {{ $row->arbpl }}
                        </td>

                        {{-- Deskripsi Work Center --}}
                        <td
                            class="px-2 py-2 text-slate-600 whitespace-normal break-words max-w-[260px] align-middle text-center">
                            {{ $row->desc ?? $row->short }}
                        </td>

                        {{-- Devisi --}}
                        <td
                            class="px-2 py-2 text-slate-700 font-semibold whitespace-normal break-words max-w-[130px] align-middle text-center">
                            {{ $row->devisi ?? '-' }}
                        </td>

                        {{-- Plant --}}
                        <td
                            class="px-2 py-2 whitespace-nowrap text-slate-700 text-center font-mono bg-slate-50/50 align-middle">
                            {{ $row->werks }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($headers) + 1 }}" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center justify-center text-gray-400">
                                <svg class="w-12 h-12 mb-3 text-gray-300" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                                    </path>
                                </svg>
                                <span class="text-lg font-medium">{{ __('Tidak ada data ditemukan.') }}</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- CSS --}}
    <style>
        .animate-shine {
            animation: shine 3s infinite;
        }

        /* Ukuran dasar untuk seluruh tabel */
        .wc-person-table {
            font-size: 0.85rem;
            /* ±13.6px, sedikit lebih besar dari sebelumnya */
        }

        /* Header tabel (judul kolom) */
        .wc-person-table thead th {
            font-size: 0.8rem;
            /* ±12px, lebih besar tapi tetap rapat */
        }

        /* Isi tabel MAINKAN DI SINI*/
        .wc-person-table tbody td {
            font-size: 0.9rem;
            /* ±14.4px, enak dibaca */
            line-height: 1.3;
        }

        /* Kurangi padding kiri-kanan supaya tidak melebar */
        .wc-person-table th,
        .wc-person-table td {
            padding-left: 0.35rem;
            /* < dari px-2 (0.5rem) */
            padding-right: 0.35rem;
        }

        /* Di layar kecil, sedikit dikecilkan lagi supaya tetap muat */
        @media (max-width: 1024px) {
            .wc-person-table {
                font-size: 0.8rem;
            }

            .wc-person-table tbody td {
                font-size: 0.85rem;
            }
        }

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

</div> {{-- END OF ROOT DIV --}}

@once
    @push('scripts')
        <script>
            // ==========================================================
            // KONSTANTA LOCAL STORAGE (UNTUK PREFILL & SUMMARY)
            // ==========================================================
            const WC_PERSON_LS_PREFILL = 'wc_person_prefill_q';
            const WC_PERSON_LS_SUMMARY = 'wc_person_sync_summary';

            // ==========================================================
            // 1. LOGIKA EXPORT DROPDOWN + AFTER RELOAD + PLANT DROPDOWN
            // ==========================================================
            document.addEventListener('livewire:initialized', () => {
                const btn = document.getElementById('wc-export-dropdown-button');
                const menu = document.getElementById('wc-export-dropdown-menu');

                if (btn && menu) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        menu.classList.toggle('hidden');
                    });

                    document.addEventListener('click', function(e) {
                        if (
                            !menu.classList.contains('hidden') &&
                            !btn.contains(e.target) &&
                            !menu.contains(e.target)
                        ) {
                            menu.classList.add('hidden');
                        }
                    });
                }

                // ---- Custom dropdown PLANT ----
                const plantBtn = document.getElementById('wc-plant-dropdown-button');
                const plantMenu = document.getElementById('wc-plant-dropdown-menu');
                const plantLabel = document.getElementById('wc-plant-dropdown-label');
                const plantChevron = document.getElementById('wc-plant-dropdown-chevron');
                const plantHidden = document.getElementById('manual-werks');

                function closePlantMenu() {
                    if (!plantMenu) return;
                    plantMenu.classList.add('hidden');
                    if (plantChevron) plantChevron.classList.remove('rotate-180');
                }

                if (plantBtn && plantMenu && plantLabel && plantHidden) {
                    plantBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const isHidden = plantMenu.classList.contains('hidden');
                        if (isHidden) {
                            plantMenu.classList.remove('hidden');
                            if (plantChevron) plantChevron.classList.add('rotate-180');
                        } else {
                            closePlantMenu();
                        }
                    });

                    plantMenu.querySelectorAll('button[data-value]').forEach((opt) => {
                        opt.addEventListener('click', () => {
                            const val = opt.getAttribute('data-value');
                            const label = opt.getAttribute('data-label') || opt.textContent.trim();

                            plantHidden.value = val || '';
                            plantLabel.textContent = label || 'Pilih Plant';
                            plantLabel.classList.remove('text-emerald-400');
                            plantLabel.classList.add('text-emerald-900');

                            // reset check icon
                            plantMenu.querySelectorAll('button[data-value]').forEach((b) => {
                                const check = b.querySelector('.option-check');
                                if (check) {
                                    check.classList.add('hidden');
                                    check.classList.remove('flex');
                                }
                            });
                            const check = opt.querySelector('.option-check');
                            if (check) {
                                check.classList.remove('hidden');
                                check.classList.add('flex');
                            }

                            closePlantMenu();
                        });
                    });

                    document.addEventListener('click', function(e) {
                        if (!plantBtn.contains(e.target) && !plantMenu.contains(e.target)) {
                            closePlantMenu();
                        }
                    });
                }

                Livewire.on('wc-person-alert', (data) => {
                    const msg = Array.isArray(data) ? data[0].message : data.message;
                    alert(msg);
                });

                Livewire.on('wc-person-export', (data) => {
                    const url = Array.isArray(data) ? data[0].url : data.url;
                    if (url) window.open(url, '_blank');
                    if (menu) menu.classList.add('hidden');
                });

                // Jalankan task setelah reload sekali saja
                if (!window.__wcPersonAfterReloadDone) {
                    window.__wcPersonAfterReloadDone = true;
                    wcPersonAfterReloadTasks();
                }
            });

            // ==========================================================
            // AFTER RELOAD: Prefill kolom cari + tampilkan summary toast
            // ==========================================================
            function wcPersonAfterReloadTasks() {
                try {
                    // Prefill kolom pencarian dengan WC yang terakhir di-sync
                    const qVal = window.localStorage.getItem(WC_PERSON_LS_PREFILL);
                    if (qVal) {
                        const input = document.getElementById('q-input');
                        if (input) {
                            input.value = qVal;
                            // Trigger Livewire (wire:model.live)
                            input.dispatchEvent(new Event('input', {
                                bubbles: true
                            }));
                        }
                        window.localStorage.removeItem(WC_PERSON_LS_PREFILL);
                    }

                    // Baca summary sync & tampilkan toast
                    const summaryStr = window.localStorage.getItem(WC_PERSON_LS_SUMMARY);
                    if (summaryStr) {
                        let summary = null;
                        try {
                            summary = JSON.parse(summaryStr);
                        } catch (e) {
                            summary = null;
                        }
                        if (summary) {
                            showWcPersonSummaryToast(summary);
                        }
                        window.localStorage.removeItem(WC_PERSON_LS_SUMMARY);
                    }
                } catch (e) {
                    console.error('WC Person after reload error', e);
                }
            }

            // ==========================================================
            // TOAST HELPER UNTUK SUMMARY SETELAH RELOAD
            // ==========================================================
            let wcToastStack = null;

            function ensureWcToastStack() {
                if (!wcToastStack) {
                    wcToastStack = document.createElement('div');
                    wcToastStack.id = 'wc-person-toast-stack';
                    wcToastStack.className = 'fixed bottom-6 right-6 z-[9999] space-y-3';
                    document.body.appendChild(wcToastStack);
                }
            }

            function makeWcToast(html) {
                ensureWcToastStack();
                const card = document.createElement('div');
                card.className = 'pointer-events-auto w-[360px] rounded-xl border bg-white shadow-2xl ring-1 ring-black/5';
                card.innerHTML = html;
                wcToastStack.appendChild(card);
                return card;
            }

            function showWcPersonSummaryToast(summary) {
                const {
                    wc = '',
                        werks = '',
                        wcInserted = 0,
                        wcDeleted = 0,
                        wcTotalPulled = 0,
                        refreshTotal = 0,
                        refreshOk = 0,
                        refreshFail = 0,
                        ok = true,
                        errorMessage = null,
                        // info NIK baru & lama (opsional)
                        newPernrsCount = null,
                        oldPernrsCount = null,
                } = summary || {};

                const statusBorder = ok && !errorMessage ? 'border-emerald-500' : 'border-amber-500';
                const title = ok && !errorMessage ?
                    'Sinkronisasi WC Person selesai' :
                    'Sinkronisasi WC Person (dengan catatan)';

                let nikInfoHtml = '';
                if (newPernrsCount !== null) {
                    nikInfoHtml = `
                        <p>
                            NIK baru: <span class="font-semibold text-emerald-800">${newPernrsCount}</span>
                            ${oldPernrsCount !== null
                        ? `, NIK lama (sudah ada di DB sebelumnya): <span class="font-mono">${oldPernrsCount}</span>`
                        : ''
                    }.
                        </p>
                    `;
                }

                const html = `
                    <div class="p-4 border-l-4 ${statusBorder}">
                        <div class="flex items-start gap-3">
                            <div
                                class="mt-1 flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-sm font-bold text-gray-900">${title}</h4>
                                <p class="mt-0.5 text-xs text-gray-500">
                                    WC <span class="font-mono font-semibold text-emerald-700">${wc || '-'}</span>
                                    <span class="mx-1 text-gray-400">•</span>
                                    Plant <span class="font-semibold">${werks || '-'}</span>
                                </p>
                                <div class="mt-2 space-y-1.5 text-xs text-gray-700">
                                    <p>
                                        <span class="font-semibold text-emerald-800">${wcTotalPulled}</span>
                                        data WC Person tertarik (insert: ${wcInserted}, hapus: ${wcDeleted}).
                                    </p>
                                    ${nikInfoHtml}
                                    <p>
                                        Refresh <code>yppr058_data</code>:
                                        target <span class="font-semibold">${refreshTotal}</span>,
                                        berhasil <span class="font-semibold text-emerald-700">${refreshOk}</span>,
                                        gagal <span class="font-semibold text-red-600">${refreshFail}</span>.
                                    </p>
                                    ${errorMessage ? `<p class="text-[11px] text-red-600 mt-1">Catatan: ${errorMessage}</p>` : ''}
                                </div>
                            </div>
                            <button type="button"
                                class="ml-2 text-gray-400 hover:text-gray-600"
                                onclick="this.closest('div.pointer-events-auto').remove()">
                                ✕
                            </button>
                        </div>
                    </div>
                `;
                const card = makeWcToast(html);
                setTimeout(() => {
                    card.remove();
                }, 8000);
            }

            // ==========================================================
            // 2. LOGIKA SYNC WC PERSON + LOG CARD + LOCAL STORAGE
            // ==========================================================
            (function() {
                const API_URL_SYNC_WC = 'http://127.0.0.1:5010/api/wc_person/sync';
                const API_URL_REFRESH_YPPR = 'http://127.0.0.1:5010/api/yppr058/refresh';
                const API_URL_PROGRESS = 'http://127.0.0.1:5010/api/yppr058/progress';

                const btn = document.getElementById('btn-manual-sync');
                const inpArbpl = document.getElementById('manual-arbpl');
                const inpWerks = document.getElementById('manual-werks');

                const logWrapper = document.getElementById('manual-sync-log-wrapper');
                const logTitle = document.getElementById('manual-sync-log-title');
                const statusPill = document.getElementById('manual-sync-status-pill');
                const statusTime = document.getElementById('manual-sync-time');
                const feedback = document.getElementById('manual-sync-feedback');
                const progressBar = document.getElementById('manual-sync-progress');
                const progressLabel = document.getElementById('manual-sync-progress-label');
                const progressPercent = document.getElementById('manual-sync-progress-percent');

                const iconReady = document.getElementById('icon-sync-ready');
                const iconLoad = document.getElementById('icon-sync-loading');

                if (!btn || !inpArbpl || !inpWerks) return;

                function showLogCard() {
                    if (logWrapper) {
                        logWrapper.classList.remove('hidden');
                    }
                }

                function setStatus(text, mode) {
                    if (!statusPill) return;
                    let base =
                        'inline-flex items-center gap-1 rounded-full px-3 py-1 text-[10px] font-semibold uppercase ';
                    if (mode === 'error') {
                        base += 'bg-red-100 text-red-700';
                    } else if (mode === 'success') {
                        base += 'bg-emerald-600 text-emerald-50';
                    } else if (mode === 'warn') {
                        base += 'bg-amber-100 text-amber-800';
                    } else {
                        base += 'bg-slate-100 text-slate-700';
                    }
                    statusPill.className = base;
                    statusPill.textContent = text;
                }

                function setProgress(percent, label) {
                    const p = Math.max(0, Math.min(100, percent || 0));
                    if (progressBar) {
                        progressBar.style.width = p + '%';
                    }
                    if (progressPercent) {
                        progressPercent.textContent = Math.round(p) + '%';
                    }
                    if (progressLabel && label) {
                        progressLabel.textContent = label;
                    }
                }

                function appendLogLine(text, type, options) {
                    if (!feedback) return;
                    const mode = type || 'info';
                    const asHtml = !options || options.asHtml !== false;

                    const row = document.createElement('div');
                    row.className = 'flex items-start gap-2';

                    const icon = document.createElement('span');
                    icon.className =
                        'mt-[2px] inline-flex h-4 w-4 flex-none items-center justify-center rounded-full text-[10px]';

                    if (mode === 'error') {
                        icon.className += ' bg-red-100 text-red-600';
                        icon.textContent = '!';
                    } else if (mode === 'success') {
                        icon.className += ' bg-emerald-100 text-emerald-600';
                        icon.textContent = '✓';
                    } else if (mode === 'warn') {
                        icon.className += ' bg-amber-50 text-amber-500';
                        icon.textContent = '•';
                    } else {
                        icon.className += ' bg-slate-100 text-slate-400';
                        icon.textContent = '•';
                    }

                    const body = document.createElement('span');
                    body.className = 'flex-1';
                    if (asHtml) {
                        body.innerHTML = text;
                    } else {
                        body.textContent = text;
                    }

                    row.appendChild(icon);
                    row.appendChild(body);
                    feedback.appendChild(row);
                }

                function resetLog() {
                    showLogCard();
                    if (feedback) {
                        feedback.innerHTML = '';
                    }
                    if (logTitle) {
                        logTitle.textContent = 'Menjalankan sinkronisasi...';
                    }
                    if (statusTime) {
                        const now = new Date();
                        const pad = (n) => String(n).padStart(2, '0');
                        statusTime.textContent =
                            pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
                    }
                    setStatus('Sedang berjalan', 'warn');
                    setProgress(8, 'Menyiapkan koneksi SAP...');
                }

                // ======================================================
                // HELPER: PROGRESS SEBENARNYA (via /api/yppr058/progress)
                // ======================================================
                let refreshProgressTimer = null;

                function stopRefreshProgress() {
                    if (refreshProgressTimer) {
                        clearInterval(refreshProgressTimer);
                        refreshProgressTimer = null;
                    }
                }

                function startRefreshProgress(jobId, totalCombos, totalDays) {
                    stopRefreshProgress();
                    if (!jobId || !totalCombos) return;

                    const base = 70;
                    const span = 25; // progress refresh akan bermain di 70..95, sisanya final 100

                    refreshProgressTimer = setInterval(async () => {
                        try {
                            const resp = await fetch(
                                `${API_URL_PROGRESS}?job_id=${encodeURIComponent(jobId)}`, {
                                    method: 'GET',
                                    headers: {
                                        'Accept': 'application/json',
                                    },
                                }
                            );

                            if (!resp.ok) {
                                // kalau 404 / error lain, biarkan saja -> coba lagi di tick berikutnya
                                return;
                            }

                            const data = await resp.json().catch(() => ({}));
                            if (!data || !data.ok || !data.progress) return;

                            const prog = data.progress;
                            const doneItems = typeof prog.done_items === 'number' ? prog.done_items : 0;
                            const totalItems = totalCombos || 1;
                            const percentItems = typeof prog.percent_items === 'number' ?
                                prog.percent_items :
                                (doneItems * 100 / totalItems);

                            const p = base + (percentItems * span / 100);
                            const capped = Math.max(70, Math.min(97,
                                p)); // jangan sampai 100, biar final yg set

                            const label = 'Refresh yppr058_data: ' +
                                doneItems + '/' + totalItems + ' kombinasi diproses' +
                                (totalDays ? (' (maks. ' + totalDays + ' hari)') : '');

                            setProgress(capped, label);

                            if (prog.status === 'done' || prog.status === 'error' || doneItems >= totalItems) {
                                stopRefreshProgress();
                            }
                        } catch (e) {
                            console.error('Error polling progress yppr058:', e);
                        }
                    }, 1500);
                }

                // ======================================================
                // HELPER: BANGUN ITEMS UNTUK NIK BARU & RANGE TANGGAL
                // ======================================================
                function buildMonthlyItemsForNewPernrs(pernrsNew, arbpl, werks) {
                    const items = [];
                    const today = new Date();
                    const year = today.getFullYear();
                    const monthIndex = today.getMonth(); // 0-11
                    const day = today.getDate();

                    const formatDats = (y, mIndex, d) => {
                        const mm = String(mIndex + 1).padStart(2, '0');
                        const dd = String(d).padStart(2, '0');
                        return `${y}${mm}${dd}`;
                    };

                    const dates = [];

                    if (day > 1) {
                        // Contoh: 5 November -> 20251104..20251101
                        for (let d = day - 1; d >= 1; d--) {
                            dates.push(formatDats(year, monthIndex, d));
                        }
                    } else {
                        // Tgl 1 -> ambil satu bulan sebelumnya full
                        // Contoh: 1 Des -> 20251130..20251101
                        const prevMonthDate = new Date(year, monthIndex, 0); // day 0 = last day prev month
                        const prevYear = prevMonthDate.getFullYear();
                        const prevMonthIndex = prevMonthDate.getMonth();
                        const lastDayPrev = prevMonthDate.getDate();
                        for (let d = lastDayPrev; d >= 1; d--) {
                            dates.push(formatDats(prevYear, prevMonthIndex, d));
                        }
                    }

                    const upperArbpl = (arbpl || '').toUpperCase();

                    pernrsNew.forEach((p) => {
                        const pernr = String(p).padStart(8, '0');
                        dates.forEach((dats) => {
                            items.push({
                                pernr: pernr,
                                werks: werks,
                                arbpl: upperArbpl,
                                begda: dats,
                                endda: dats,
                            });
                        });
                    });

                    return items;
                }

                // ======================================================
                // EVENT HANDLER TOMBOL SYNC
                // ======================================================
                btn.addEventListener('click', async () => {
                    const arbpl = inpArbpl.value.trim();
                    const werks = inpWerks.value;

                    if (!arbpl || !werks) {
                        alert('Harap isi Work Center dan pilih Plant!');
                        return;
                    }

                    // UI Loading
                    btn.disabled = true;
                    btn.classList.add('opacity-75', 'cursor-not-allowed');
                    if (iconReady) iconReady.classList.add('hidden');
                    if (iconLoad) iconLoad.classList.remove('hidden');

                    resetLog();
                    appendLogLine(
                        'Menghubungkan ke <b>SAP</b> untuk <span class="font-mono font-semibold">' +
                        arbpl.toUpperCase() +
                        '</span> (Plant ' +
                        werks +
                        ')...',
                        'info'
                    );

                    // Summary yang akan disimpan ke localStorage
                    let summary = {
                        wc: arbpl.toUpperCase(),
                        werks: werks,
                        ts: Date.now(),
                        wcInserted: 0,
                        wcDeleted: 0,
                        wcTotalPulled: 0,
                        refreshTotal: 0,
                        refreshOk: 0,
                        refreshFail: 0,
                        ok: false,
                        errorMessage: null,
                        // info NIK baru & lama
                        newPernrsCount: null,
                        oldPernrsCount: null,
                        jobId: null,
                    };

                    let shouldReload = false;

                    try {
                        // 1) SYNC MASTER WC PERSON
                        const response = await fetch(API_URL_SYNC_WC, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                arbpl: arbpl,
                                werks: werks,
                            }),
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            throw new Error(data.message || 'Terjadi kesalahan pada server API.');
                        }

                        const inserted = typeof data.inserted === 'number' ? data.inserted : 0;
                        const deleted = typeof data.deleted === 'number' ? data.deleted : 0;
                        const wcTotalPulled =
                            typeof data.pernrs_count === 'number' ?
                            data.pernrs_count :
                            (typeof data.total === 'number' ?
                                data.total :
                                (typeof data.count === 'number' ?
                                    data.count :
                                    (Array.isArray(data.pernrs) ?
                                        data.pernrs.length :
                                        inserted)));

                        summary.wcInserted = inserted;
                        summary.wcDeleted = deleted;
                        summary.wcTotalPulled = wcTotalPulled;

                        // --- ambil daftar PERNR all & PERNR baru dari API ---
                        const pernrsAll = Array.isArray(data.pernrs) ? data.pernrs : [];
                        const pernrsNew = Array.isArray(data.pernrs_new) ? data.pernrs_new :
                            pernrsAll; // fallback
                        const pernrsRemoved = Array.isArray(data.pernrs_removed) ? data.pernrs_removed : [];

                        const totalAll = pernrsAll.length;
                        const totalNew = pernrsNew.length;
                        const totalOld = typeof data.pernrs_old_count === 'number' ?
                            data.pernrs_old_count :
                            Math.max(0, totalAll - totalNew);

                        const deletedYppr = typeof data.yppr058_deleted === 'number' ?
                            data.yppr058_deleted :
                            0;

                        summary.newPernrsCount = totalNew;
                        summary.oldPernrsCount = totalOld;

                        let logLine =
                            '✅ Sinkron WC <span class="font-mono font-semibold">' +
                            arbpl.toUpperCase() +
                            '</span> selesai. Tarikan data: <b>' +
                            wcTotalPulled +
                            '</b> baris (insert: ' +
                            inserted +
                            ', hapus: ' +
                            deleted +
                            ')';

                        if (totalNew || totalOld) {
                            logLine +=
                                '. NIK baru: <b>' + totalNew + '</b>' +
                                ', NIK lama (sudah ada sebelumnya): <span class="font-mono">' + totalOld +
                                '</span>.';
                        } else {
                            logLine += '.';
                        }

                        appendLogLine(logLine, 'success');

                        if (data.desc) {
                            appendLogLine(
                                '📋 Deskripsi WC: <span class="italic text-emerald-800">' +
                                String(data.desc) +
                                '</span>',
                                'info'
                            );
                        }
                        setProgress(45, 'Master WC berhasil disinkronkan.');

                        // ===============================
                        // HANYA REFRESH NIK BARU SAJA
                        // ===============================
                        if (pernrsNew.length > 0) {
                            // Buat ITEMS sesuai aturan tanggal / bulan
                            const items = buildMonthlyItemsForNewPernrs(pernrsNew, arbpl, werks);
                            summary.refreshTotal = items.length;

                            // Berapa hari unik yang direfresh
                            const daySet = new Set(items.map(it => it.begda));
                            const daysCount = daySet.size;

                            // Tampilkan beberapa tanggal pertama di log untuk memperjelas
                            const sortedDates = Array.from(daySet).sort().reverse(); // terbaru dulu

                            appendLogLine(
                                'Menyiapkan refresh <code>yppr058_data</code> untuk <b>' +
                                pernrsNew.length +
                                '</b> NIK <b>baru</b> pada <b>' +
                                daysCount +
                                '</b> tanggal (' +
                                items.length +
                                ' kombinasi NIK-tanggal). ',
                                'info'
                            );

                            // job_id untuk tracking progress sebenarnya
                            const jobId = 'wc-' + Date.now() + '-' + Math.floor(Math.random() * 1000000);
                            summary.jobId = jobId;

                            setProgress(70, 'Mengirim permintaan refresh yppr058_data untuk NIK baru...');
                            appendLogLine(
                                'Permintaan refresh akan dikirim ke backend. ' +
                                'Server akan memproses <b>' + items.length + '</b> kombinasi ' +
                                '(' + pernrsNew.length + ' NIK × ' + daysCount + ' tanggal). ' +
                                'Progress bar akan mengikuti status nyata dari server.',
                                'info'
                            );

                            try {
                                // Mulai polling progress nyata
                                startRefreshProgress(jobId, items.length, daysCount);

                                const resp2 = await fetch(API_URL_REFRESH_YPPR, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                    },
                                    body: JSON.stringify({
                                        job_id: jobId,
                                        items: items,
                                    }),
                                });

                                const data2 = await resp2.json().catch(() => ({}));

                                // Hentikan polling, lanjutkan progress final
                                stopRefreshProgress();

                                if (!resp2.ok || !data2.ok) {
                                    throw new Error(data2.error || 'Refresh yppr058_data gagal.');
                                }

                                let okCount = 0;
                                let failCount = 0;
                                if (Array.isArray(data2.results)) {
                                    data2.results.forEach((r) => {
                                        if (r && r.ok) {
                                            okCount++;
                                        } else {
                                            failCount++;
                                        }
                                    });
                                } else {
                                    okCount = items.length;
                                    failCount = 0;
                                }

                                summary.refreshOk = okCount;
                                summary.refreshFail = failCount;
                                summary.ok = failCount === 0;

                                appendLogLine(
                                    'Hasil refresh <code>yppr058_data</code> untuk NIK baru: <b>' +
                                    okCount +
                                    '</b> kombinasi berhasil, <b>' +
                                    failCount +
                                    '</b> kombinasi gagal ' +
                                    `(target ${items.length} kombinasi, sekitar ${daysCount} hari).`,
                                    failCount ? 'warn' : 'success'
                                );

                                setProgress(100, 'Sinkronisasi dan refresh NIK baru selesai.');
                                setStatus(
                                    failCount ? 'Selesai (dengan catatan)' : 'Selesai',
                                    failCount ? 'warn' : 'success'
                                );
                            } catch (e2) {
                                console.error(e2);
                                stopRefreshProgress();

                                summary.refreshFail = summary.refreshTotal || items.length;
                                summary.refreshOk = 0;
                                summary.ok = false;
                                summary.errorMessage = e2 && e2.message ? e2.message : String(e2);

                                appendLogLine(
                                    'Gagal refresh <code>yppr058_data</code> untuk NIK baru: ' +
                                    summary.errorMessage,
                                    'error', {
                                        asHtml: true
                                    }
                                );
                                setProgress(100, 'Sinkronisasi selesai dengan beberapa kegagalan.');
                                setStatus('Gagal', 'error');
                            }
                        } else {
                            summary.ok = true;
                            summary.refreshTotal = 0;

                            if (wcTotalPulled === 0) {
                                // Kasus: WC di SAP sudah benar-benar kosong
                                appendLogLine(
                                    'SAP tidak lagi mengembalikan personil untuk WC ini. ' +
                                    'Semua data <code>yppr058_data</code> dengan WC ' +
                                    arbpl.toUpperCase() + ' & Plant ' + werks +
                                    ' telah dihapus (' + deletedYppr + ' baris).',
                                    'warn'
                                );
                                setProgress(100,
                                    'Sinkronisasi master WC selesai (data yppr058_data dikosongkan).');
                            } else if (pernrsRemoved.length > 0) {
                                // Kasus: masih ada personil di WC tsb, tapi ada beberapa NIK yang hilang (contoh 002)
                                appendLogLine(
                                    'Tidak ada NIK baru, tetapi terdapat <b>' + pernrsRemoved.length +
                                    '</b> NIK yang sekarang <b>tidak lagi terdaftar</b> di WC ini. ' +
                                    'Data <code>yppr058_data</code> untuk NIK tersebut telah dihapus ' +
                                    '(' + deletedYppr + ' baris): ' +
                                    '<span class="font-mono">' + pernrsRemoved.join(', ') + '</span>.',
                                    'warn'
                                );
                                setProgress(100,
                                    'Sinkronisasi master WC selesai (hapus data yppr058_data untuk NIK yang tidak aktif).'
                                );
                            } else {
                                // Kasus lama: benar-benar tidak ada perubahan NIK
                                appendLogLine(
                                    'Tidak ada NIK baru pada WC ini (hanya NIK lama yang sudah ada di DB). ' +
                                    'Refresh <code>yppr058_data</code> dilewati.',
                                    'info'
                                );
                                setProgress(100,
                                    'Sinkronisasi master WC selesai (tanpa refresh yppr058_data).');
                            }

                            setStatus('Selesai', 'success');
                        }


                        // Bersihkan input WC di form
                        inpArbpl.value = '';
                        shouldReload = true;
                    } catch (error) {
                        console.error(error);
                        stopRefreshProgress();

                        summary.ok = false;
                        summary.errorMessage = error && error.message ? error.message : String(error);

                        appendLogLine(
                            'Gagal: ' + summary.errorMessage,
                            'error', {
                                asHtml: false
                            }
                        );
                        setProgress(100, 'Terjadi kesalahan saat sinkronisasi.');
                        setStatus('Gagal', 'error');
                    } finally {
                        // Simpan ke localStorage & reload hanya kalau proses utama sukses
                        if (shouldReload) {
                            try {
                                window.localStorage.setItem(WC_PERSON_LS_PREFILL, arbpl.toUpperCase());
                                window.localStorage.setItem(WC_PERSON_LS_SUMMARY, JSON.stringify(summary));
                            } catch (e) {
                                console.error('localStorage WC Person error', e);
                            }

                            setTimeout(() => {
                                window.location.reload();
                            }, 2500);
                        }

                        btn.disabled = false;
                        btn.classList.remove('opacity-75', 'cursor-not-allowed');
                        if (iconReady) iconReady.classList.remove('hidden');
                        if (iconLoad) iconLoad.classList.add('hidden');
                    }
                });
            })();
        </script>
    @endpush
@endonce
