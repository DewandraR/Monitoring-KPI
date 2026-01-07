@php
    use Carbon\Carbon;
    use Illuminate\Support\Str;

    /**
     * =========================================================
     * HEADER (SUMMARY) & DETAIL HEADERS (untuk style konsisten)
     * =========================================================
     */
    $headersSummary = ['No','NIK','Rentang Tanggal','Nama','WC','Devisi','Time WI','Time CONF','Time QM','% KPI Qty','% KPI Quality'];
    $headersDetail  = ['No','NIK','Tanggal','Nama','WC','Devisi','Time WI','Time CONF','Time QM','% KPI Qty','% KPI Quality'];

    /**
     * =========================================================
     * NORMALISASI SELECTION (ANTI ERROR count(null))
     * =========================================================
     */
    $selectedNiksArr = collect($selectedNiks ?? [])
        ->map(fn($v) => trim((string)$v))
        ->filter()
        ->unique()
        ->values();

    $selectedDetailKeysArr = collect($selectedDetailKeys ?? [])
        ->map(fn($v) => trim((string)$v))
        ->filter()
        ->unique()
        ->values();

    $selectedKorlapsArr = collect($selectedKorlaps ?? [])
        ->map(fn($v) => trim((string)$v))
        ->filter()
        ->unique()
        ->values();

    // Mode
    $isKorlapMode = (($reportMode ?? 'wi') === 'korlap');
    $isWiMode     = !$isKorlapMode;

    // Badge count: Korlap -> selectedKorlaps, WI -> selectedNiks
    $selectedCount = $isKorlapMode ? (int)$selectedKorlapsArr->count() : (int)$selectedNiksArr->count();

    /**
     * =========================================================
     * HITUNG BADGE EXPORT DETAIL (JUMLAH "BARIS" YANG AKAN DIPRINT)
     * (Hanya relevan di mode WI; di Korlap tetap aman karena reportData kosong)
     * =========================================================
     */
    $detailSelectedCount = 0;

    try {
        // Map nik => jumlah hari berdasarkan min_tanggal & max_tanggal dari reportData
        $nikDaysMap = collect($reportData ?? [])->mapWithKeys(function ($row) {
            try {
                $start = !empty($row->min_tanggal) ? Carbon::parse($row->min_tanggal) : null;
                $end   = !empty($row->max_tanggal) ? Carbon::parse($row->max_tanggal) : null;

                if (!$start || !$end) $days = 0;
                else $days = $end->lt($start) ? 0 : ($start->diffInDays($end) + 1);
            } catch (\Throwable $e) {
                $days = 0;
            }

            return [(string)($row->nik ?? '') => (int)$days];
        });

        // NIK yang dicentang di SUMMARY
        $summaryNiks = $selectedNiksArr;

        // Total hari dari summary (tiap NIK full range min..max)
        $summaryTotalDays = (int) $summaryNiks->map(fn($n) => (int)$nikDaysMap->get($n, 0))->sum();

        // Pair detail di modal: nik|tanggal (unik)
        $detailPairs = $selectedDetailKeysArr
            ->map(function ($key) {
                if (!is_string($key) || $key === '') return null;

                [$nik, $tanggal] = array_pad(explode('|', (string)$key, 2), 2, '');
                $nik = trim((string)$nik);
                $tanggal = trim((string)$tanggal);

                if ($nik === '' || $tanggal === '') return null;

                return [
                    'nik' => $nik,
                    'tanggal' => $tanggal,
                    'key' => $nik.'|'.$tanggal,
                ];
            })
            ->filter()
            ->unique('key');

        // hitung jumlah tanggal per nik (unik)
        $detailByNik = $detailPairs
            ->groupBy('nik')
            ->map(fn($items) => collect($items)->pluck('tanggal')->unique()->count());

        // Detail ONLY = nik yang tidak dicentang summary
        $detailOnlyTotal = (int) $detailByNik->except($summaryNiks->all())->sum();

        // total export detail
        $detailSelectedCount = (int)$summaryTotalDays + (int)$detailOnlyTotal;
    } catch (\Throwable $e) {
        $detailSelectedCount = 0;
    }
@endphp

{{-- ROOT ELEMENT --}}
<div id="wi-root"
     data-report-mode="{{ $reportMode ?? 'wi' }}"
     data-month-filter="{{ $monthFilter ?? 'this' }}"
     data-range-start="{{ $rangeStart ?? '' }}"
     data-range-end="{{ $rangeEnd ?? '' }}"
     data-selected-niks='@json($selectedNiksArr->values()->all())'
     data-selected-korlaps='@json($selectedKorlapsArr->values()->all())'
     data-selected-detail-keys='@json($selectedDetailKeysArr->values()->all())'
     class="bg-white overflow-hidden shadow-[0_25px_50px_-12px_rgba(0,0,0,0.15)] sm:rounded-xl p-8 border border-emerald-50 relative">

    {{-- TOAST CONTAINER --}}
    <div id="wi-toast-container" class="fixed top-5 right-5 z-[9999] space-y-2"></div>

    {{-- DEKORASI LATAR --}}
    <div class="absolute top-0 right-0 -mt-4 -mr-4 w-64 h-64 bg-gradient-to-br from-emerald-100/40 to-transparent rounded-full blur-3xl pointer-events-none"></div>

    {{-- ========================================================= --}}
    {{-- BAGIAN 1: HEADER + TOGGLE PERIODE + AKSI BUTTONS --}}
    {{-- ========================================================= --}}
    <div class="flex flex-col gap-6 mb-8 relative z-10">

        {{-- BARIS 1: JUDUL --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">

            <div class="space-y-2">
                <h3 class="text-3xl lg:text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-emerald-800 to-teal-600 tracking-tight drop-shadow-sm animate-fade-in">
                    {{ __('WI Daily Report') }}
                    <span class="text-emerald-900/20 font-light">—</span>
                    <span class="text-2xl lg:text-3xl font-mono">{{ $plant ?? ($werks ?? request()->route('werks')) }}</span>
                </h3>

                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-slate-500">Periode:</span>
                    <span class="font-bold text-emerald-700 bg-emerald-50 px-3 py-1 rounded-lg shadow-sm">
                        {{ $rangeStart ?? '-' }} s.d. {{ $rangeEnd ?? '-' }}
                    </span>

                    {{-- ✅ TOGGLE REPORT MODE (WI / KORLAP) --}}
                    <div class="inline-flex items-center rounded-xl bg-white/70 p-1 ring-1 ring-emerald-200 shadow-sm ml-2">
                        <button type="button"
                                wire:click="setReportMode('wi')"
                                class="px-3 py-1.5 rounded-lg text-xs font-black tracking-wide transition-all
                                    {{ $isWiMode ? 'bg-emerald-600 text-white shadow' : 'text-slate-600 hover:text-emerald-700' }}">
                            Per NIK
                        </button>

                        <button type="button"
                                wire:click="setReportMode('korlap')"
                                class="px-3 py-1.5 rounded-lg text-xs font-black tracking-wide transition-all
                                    {{ $isKorlapMode ? 'bg-emerald-900 text-white shadow' : 'text-slate-600 hover:text-emerald-800' }}">
                            Per Korlap
                        </button>
                    </div>

                    <span class="text-xs text-gray-400 italic">
                        {{ $isKorlapMode ? '(Ringkasan per Korlap)' : '(Ringkasan per NIK)' }}
                    </span>
                </div>
            </div>

            {{-- TOGGLE BULAN --}}
            <div class="flex items-center gap-3 group/toggle">
                <span class="hidden sm:inline text-[11px] uppercase tracking-widest text-emerald-900/70 font-black flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Periode
                </span>

                <div class="relative inline-flex rounded-2xl bg-gradient-to-r from-emerald-100 via-teal-100 to-emerald-100 p-1.5 shadow-lg ring-2 ring-emerald-200/50 backdrop-blur-sm">
                    <div class="absolute inset-1.5 rounded-xl bg-gradient-to-r from-white via-emerald-50 to-white shadow-inner transition-all duration-500 ease-out {{ ($monthFilter ?? 'this') === 'this' ? 'translate-x-0' : 'translate-x-[calc(100%-4px)]' }}"
                         style="width: calc(50% - 2px);"></div>

                    <button type="button" wire:click="setMonthFilter('this')"
                            class="relative z-10 px-5 py-2.5 rounded-xl text-sm font-bold transition-all duration-300 ease-out flex items-center gap-2
                                   {{ ($monthFilter ?? 'this') === 'this'
                                     ? 'text-emerald-700 scale-105'
                                     : 'text-emerald-600/60 hover:text-emerald-700 hover:scale-105' }}">
                        <svg class="w-4 h-4 transition-all duration-300 {{ ($monthFilter ?? 'this') === 'this' ? 'rotate-0 scale-110' : 'rotate-12 scale-90 opacity-70' }}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span class="whitespace-nowrap">Bulan Ini</span>

                        @if (($monthFilter ?? 'this') === 'this')
                            <span class="absolute -top-1 -right-1 flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500 shadow-lg shadow-emerald-500/50"></span>
                            </span>
                        @endif
                    </button>

                    <button type="button" wire:click="setMonthFilter('prev')"
                            class="relative z-10 px-5 py-2.5 rounded-xl text-sm font-bold transition-all duration-300 ease-out flex items-center gap-2
                                   {{ ($monthFilter ?? 'this') === 'prev'
                                     ? 'text-teal-700 scale-105'
                                     : 'text-teal-600/60 hover:text-teal-700 hover:scale-105' }}">
                        <svg class="w-4 h-4 transition-all duration-300 {{ ($monthFilter ?? 'this') === 'prev' ? 'rotate-0 scale-110' : '-rotate-12 scale-90 opacity-70' }}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="whitespace-nowrap">Bulan Lalu</span>

                        @if (($monthFilter ?? 'this') === 'prev')
                            <span class="absolute -top-1 -right-1 flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-teal-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-teal-500 shadow-lg shadow-teal-500/50"></span>
                            </span>
                        @endif
                    </button>

                    <div class="absolute -inset-1 bg-gradient-to-r from-emerald-400/20 via-teal-400/20 to-emerald-400/20 rounded-2xl blur-lg opacity-0 group-hover/toggle:opacity-100 transition-opacity duration-500 -z-10"></div>
                </div>
            </div>
        </div>

        {{-- BARIS 2: BUTTONS --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 {{ $isWiMode ? 'lg:grid-cols-3' : '' }} gap-3">

            {{-- (1) COPY NIK/KORLAP --}}
            <button id="wi-btn-copy-nik" type="button"
                    class="group relative overflow-hidden rounded-xl
                       bg-gradient-to-br from-teal-600 via-emerald-600 to-emerald-700
                       px-5 py-4 text-sm font-bold text-white
                       shadow-lg shadow-teal-600/30 ring-1 ring-teal-500/20
                       transition-all duration-300 ease-out
                       hover:shadow-2xl hover:shadow-teal-600/50 hover:-translate-y-1
                       hover:ring-teal-400/40
                       focus:outline-none focus:ring-2 focus:ring-teal-400 focus:ring-offset-2
                       flex items-center justify-center gap-2.5">

                <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/25 to-transparent transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
                </div>

                <svg xmlns="http://www.w3.org/2000/svg"
                     class="h-5 w-5 relative z-10 transition-all duration-300 group-hover:scale-110"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>

                <span class="relative z-10 whitespace-nowrap">Copy {{ $isKorlapMode ? 'NIK Korlap' : 'NIK' }}</span>

                @if ($selectedCount > 0)
                    <span class="flex h-6 min-w-[24px] items-center justify-center rounded-lg
                                   bg-white/95 text-teal-700 text-[11px] font-black
                                   shadow-md ring-2 ring-white/50 relative z-10 px-1.5
                                   transition-transform duration-300 group-hover:scale-110">
                       {{ $selectedCount }}
                    </span>
                @endif
            </button>

            {{-- (2) EXPORT REPORT (SUMMARY) --}}
            <div class="relative inline-block w-full">

                <button id="wi-export-dropdown-button" type="button"
                        class="group relative overflow-hidden rounded-xl w-full
                               bg-gradient-to-br from-emerald-700 via-emerald-800 to-teal-900
                               px-5 py-4 text-sm font-bold text-white
                               shadow-lg shadow-emerald-700/30 ring-1 ring-emerald-600/20
                               transition-all duration-300 ease-out
                               hover:shadow-2xl hover:shadow-emerald-700/50 hover:-translate-y-1
                               hover:ring-emerald-500/40
                               focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2
                               flex items-center justify-center gap-2.5">

                    <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
                    </div>

                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 relative z-10" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>

                    <span class="relative z-10 whitespace-nowrap">Export Report</span>

                    @if ($selectedCount > 0)
                        <span class="flex h-6 min-w-[24px] items-center justify-center rounded-lg
                                             bg-white/95 text-emerald-800 text-[11px] font-black
                                             shadow-md ring-2 ring-white/50 relative z-10 px-1.5">
                            {{ $selectedCount }}
                        </span>
                    @endif

                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="h-4 w-4 relative z-10 transition-transform duration-300 group-hover:rotate-180"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div id="wi-export-dropdown-menu"
                     class="hidden absolute right-0 left-0 sm:left-auto sm:right-0 mt-2 sm:w-56 w-full origin-top
                               rounded-xl bg-white shadow-2xl ring-1 ring-emerald-900/10
                               focus:outline-none z-50 overflow-hidden
                               border border-emerald-100 animate-scale-in">

                    <div class="px-4 py-2.5 text-[10px] font-bold uppercase tracking-widest
                                   text-emerald-700 bg-gradient-to-r from-emerald-50 to-teal-50
                                   border-b border-emerald-100 flex items-center justify-between gap-2">
                        <span>📊 Summary Report</span>

                        @if ($selectedCount > 0)
                            <span class="inline-flex items-center rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5 text-[10px] font-black">
                                {{ $selectedCount }} {{ $isKorlapMode ? 'KORLAP' : 'NIK' }}
                            </span>
                        @endif
                    </div>

                    <div class="p-2 space-y-1">

                        {{-- PDF --}}
                        <button type="button" wire:click="export('pdf')"
                                class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5
                                   text-sm font-semibold text-gray-700
                                   transition-all hover:bg-red-50 hover:text-red-700 group/item">
                            <span class="flex items-center gap-3">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg
                                             bg-gradient-to-br from-red-100 to-red-200 text-red-600
                                             shadow-sm group-hover/item:shadow-md group-hover/item:scale-110 transition-all">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    </svg>
                                </span>
                                <span>Download PDF</span>
                            </span>
                        </button>

                        {{-- EXCEL --}}
                        <button type="button" wire:click="export('xlsx')"
                                class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5
                                   text-sm font-semibold text-gray-700
                                   transition-all hover:bg-emerald-50 hover:text-emerald-700 group/item">
                            <span class="flex items-center gap-3">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg
                                             bg-gradient-to-br from-emerald-100 to-emerald-200 text-emerald-700
                                             shadow-sm group-hover/item:shadow-md group-hover/item:scale-110 transition-all">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M9 17v-2m3 2v-4m3 4v-6M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    </svg>
                                </span>
                                <span>Download Excel</span>
                            </span>
                        </button>

                    </div>
                </div>
            </div>

            {{-- (3) EXPORT DETAIL (HANYA MODE WI) --}}
            @if($isWiMode)
                <div class="relative inline-block w-full">

                    <button id="wi-export-detail-dropdown-button" type="button"
                            class="group relative overflow-hidden rounded-xl w-full
                               bg-gradient-to-br from-slate-700 via-slate-800 to-gray-900
                               px-5 py-4 text-sm font-bold text-white
                               shadow-lg shadow-slate-700/30 ring-1 ring-slate-600/20
                               transition-all duration-300 ease-out
                               hover:shadow-2xl hover:shadow-slate-700/50 hover:-translate-y-1
                               hover:ring-slate-500/40
                               focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2
                               flex items-center justify-center gap-2.5">

                        <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/15 to-transparent transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
                        </div>

                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 relative z-10" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>

                        <span class="relative z-10 whitespace-nowrap">Export Detail</span>

                        @if ((int)$detailSelectedCount > 0)
                            <span class="flex h-6 min-w-[24px] items-center justify-center rounded-lg
                                             bg-white/95 text-slate-800 text-[11px] font-black
                                             shadow-md ring-2 ring-white/50 relative z-10 px-1.5">
                                {{ (int)$detailSelectedCount }}
                            </span>
                        @endif

                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="h-3.5 w-3.5 relative z-10 transition-transform duration-300 group-hover:rotate-180"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div id="wi-export-detail-dropdown-menu"
                         class="hidden absolute right-0 left-0 sm:left-auto sm:right-0 mt-2 sm:w-56 w-full origin-top
                               rounded-xl bg-white shadow-xl ring-1 ring-black/5
                               focus:outline-none z-50 overflow-hidden
                               border border-gray-100 animate-scale-in">

                        <div class="px-4 py-2.5 text-[10px] font-bold uppercase tracking-widest
                                   text-gray-600 bg-gray-50 border-b border-gray-100 flex items-center justify-between gap-2">
                            <span>📅 Detail Report</span>

                            @if ((int)$detailSelectedCount > 0)
                                <span class="inline-flex items-center rounded-full bg-slate-200 text-slate-800 px-2 py-0.5 text-[10px] font-black">
                                    {{ (int)$detailSelectedCount }} data
                                </span>
                            @endif
                        </div>

                        <div class="p-2 space-y-1">

                            {{-- PDF --}}
                            <button type="button" wire:click="exportDetail('pdf')"
                                    class="flex w-full items-center justify-between gap-2.5 rounded-lg px-3 py-2.5
                                   text-sm font-semibold text-gray-700
                                   transition-colors hover:bg-red-50 hover:text-red-700 group/item">
                                <span class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg
                                             bg-red-100 text-red-600
                                             group-hover/item:bg-red-200 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                    </span>
                                    <span>Download PDF</span>
                                </span>
                            </button>

                            {{-- EXCEL --}}
                            <button type="button" wire:click="exportDetail('xlsx')"
                                    class="flex w-full items-center justify-between gap-2.5 rounded-lg px-3 py-2.5
                                   text-sm font-semibold text-gray-700
                                   transition-colors hover:bg-emerald-50 hover:text-emerald-700 group/item">
                                <span class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg
                                             bg-emerald-100 text-emerald-700
                                             group-hover/item:bg-emerald-200 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M9 17v-2m3 2v-4m3 4v-6M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                    </span>
                                    <span>Download Excel</span>
                                </span>
                            </button>

                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ========================================================= --}}
    {{-- BAGIAN 2: FILTER SEARCH --}}
    {{-- ========================================================= --}}
    <div class="mb-8 p-6 bg-emerald-50/50 rounded-xl shadow-inner border border-emerald-100/80 backdrop-blur-sm">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-4">

            {{-- KIRI: Judul --}}
            <p class="text-lg font-bold text-emerald-800 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                {{ __('Filter Data Berdasarkan Kriteria:') }}
            </p>

            {{-- KANAN: Radio WI Mode --}}
            <div class="w-full lg:w-auto lg:ml-auto flex flex-wrap items-center justify-start lg:justify-end gap-x-6 gap-y-2"
                 wire:key="wi-mode-radios">

                <span class="text-[11px] uppercase tracking-widest text-emerald-900/70 font-black whitespace-nowrap self-center">
                    FILTER WI:
                </span>

                @php
                    $currentMode = $wiMode ?? 'with';
                    $options = [
                        'all'     => 'Semua',
                        'with'    => 'Ada WI',
                        'without' => 'Belum Ada WI',
                    ];
                @endphp

                @foreach($options as $val => $label)
                    @php $isActive = $currentMode === $val; @endphp
                    <div class="inline-flex items-center group cursor-pointer" wire:key="wi-opt-{{ $val }}">
                        <input id="wi-mode-{{ $val }}"
                               name="wiMode"
                               type="radio"
                               class="h-4 w-4 text-emerald-600 border-gray-300 focus:ring-emerald-500 cursor-pointer rounded-full transition-all duration-200"
                               wire:model.live="wiMode"
                               value="{{ $val }}"
                               @checked($isActive)>

                        <label for="wi-mode-{{ $val }}"
                               class="ml-2 block text-sm cursor-pointer transition-colors duration-200 select-none
                                      {{ $isActive ? 'text-emerald-700 font-bold' : 'text-slate-600 font-medium hover:text-emerald-700' }}">
                            {{ $label }}
                        </label>
                    </div>
                @endforeach

            </div>

        </div>

        <div class="grid grid-cols-1 gap-6">
            <div class="relative group">
                <input id="wi-q-input" type="text" wire:model.live.debounce.400ms="q" placeholder=" "
                       class="peer block w-full pt-6 pb-2 px-4 border-gray-300 text-gray-900 bg-white rounded-lg
                              shadow-sm focus:border-emerald-500 focus:ring-emerald-500 focus:ring-2 transition-all duration-200 h-14" />

                <label for="wi-q-input"
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
                    <span class="font-semibold text-emerald-700">Nama</span>,
                    <span class="font-semibold text-emerald-700">Tanggal</span>,
                    <span class="font-semibold text-emerald-700">WC</span>,
                    <span class="font-semibold text-emerald-700">Kode</span>.
                    <br>Gunakan tanda kutip untuk hasil tepat, contoh:
                    <code class="bg-gray-100 px-1 rounded text-emerald-700 font-mono text-xs">"yudha"</code>,
                    <code class="bg-gray-100 px-1 rounded text-emerald-700 font-mono text-xs">"2026-01-02"</code>,
                    <code class="bg-gray-100 px-1 rounded text-emerald-700 font-mono text-xs">"3015"</code>.
                </p>

                @error('q')
                    <span class="text-xs text-red-500 mt-1 ml-1 block font-medium">{{ $message }}</span>
                @enderror
            </div>
        </div>
    </div>

    {{-- ========================================================= --}}
    {{-- BAGIAN 3: TABEL SUMMARY (WI MODE / KORLAP MODE) --}}
    {{-- ========================================================= --}}
    <div wire:key="wi-summary-{{ md5(($plant ?? ($werks ?? request()->route('werks'))) . '|' . ($q ?? '') . '|' . ($monthFilter ?? 'this') . '|' . ($wiMode ?? 'all') . '|' . ($reportMode ?? 'wi')) }}">

        {{-- ======================= --}}
        {{-- ✅ MODE WI (TABEL NIK) --}}
        {{-- ======================= --}}
        @if($isWiMode)

            <div class="overflow-y-auto max-h-[75vh] shadow-xl sm:rounded-xl border border-gray-200/75">
                <table id="wi-summary-table" class="min-w-full divide-y divide-gray-200">

                    <thead class="sticky top-0 z-20 bg-gradient-to-r from-emerald-800 to-teal-900 text-white shadow-md">
                        <tr>
                            @php
                                $pageNiks = collect($reportData ?? [])->pluck('nik')->map(fn($v) => (string)$v)->all();
                                $selectedN = $selectedNiksArr->all();
                                $allCurrentSelected = !empty($pageNiks) && count(array_intersect($pageNiks, $selectedN)) === count($pageNiks);
                            @endphp

                            {{-- CHECKBOX ALL (SUMMARY) --}}
                            <th scope="col"
                                class="sticky left-0 z-30 px-6 py-4 text-center text-sm font-bold uppercase tracking-wider w-10 bg-emerald-800">
                                <label class="inline-flex items-center justify-center gap-2 select-none cursor-pointer group">
                                    <input id="wi-check-all-summary" type="checkbox"
                                           class="rounded border-gray-300 text-emerald-500 focus:ring-emerald-500 transition-colors cursor-pointer bg-white/90 h-4 w-4"
                                           @checked($allCurrentSelected)>
                                </label>
                            </th>

                            @foreach ($headersSummary as $header)
                                <th scope="col" class="px-6 py-4 text-center text-sm font-bold uppercase tracking-wider whitespace-nowrap">
                                    {{ __($header) }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($reportData as $row)
                            @php
                                $minDate = !empty($row->min_tanggal) ? Carbon::parse($row->min_tanggal) : null;
                                $maxDate = !empty($row->max_tanggal) ? Carbon::parse($row->max_tanggal) : null;

                                $wiSum   = (float)($row->time_wi_sum ?? 0);
                                $confSum = (float)($row->time_conf_sum ?? 0);
                                $qmSum   = (float)($row->time_qm_sum ?? 0);

                                // KPI Qty  = WI / CONF
                                $kpiQty = isset($row->kpi_qty_pct)
                                    ? (float)$row->kpi_qty_pct
                                    : ($confSum == 0 ? 0 : (($wiSum / $confSum) * 100));

                                // KPI Quality = QM / WI
                                $kpiQuality = isset($row->kpi_quality_pct)
                                    ? (float)$row->kpi_quality_pct
                                    : ($wiSum == 0 ? 0 : (($qmSum / $wiSum) * 100));
                            @endphp

                            <tr wire:key="wi-row-{{ (string)$row->nik }}"
                                wire:click="showNikDetail({{ \Illuminate\Support\Js::from((string)$row->nik) }})"
                                class="group/row transition-all duration-200 ease-in-out hover:bg-emerald-50 cursor-pointer odd:bg-white even:bg-slate-50/50">

                                {{-- CHECKBOX (SUMMARY) STICKY LEFT --}}
                                <td class="sticky left-0 z-10 px-6 py-4 bg-white group-even/row:bg-slate-50/50 group-hover/row:bg-emerald-50">
                                    <input type="checkbox"
                                           wire:model.live="selectedNiks"
                                           value="{{ (string)$row->nik }}"
                                           class="wi-summary-check rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer h-5 w-5"
                                           wire:click.stop>
                                </td>

                                {{-- NO --}}
                                <td class="px-6 py-4 text-center font-extrabold text-emerald-800/80">
                                    {{ $loop->iteration }}
                                </td>

                                {{-- NIK --}}
                                <td class="px-6 py-4 text-center font-mono text-gray-900">
                                    {{ $row->nik }}
                                </td>

                                {{-- RENTANG TANGGAL --}}
                                <td class="px-6 py-4 text-gray-600 text-xs">
                                    @if($minDate && $maxDate)
                                        <div class="flex flex-col items-center font-mono leading-tight">
                                            <span>{{ $minDate->format('Y') }}</span>
                                            <span>{{ $minDate->format('m-d') }}</span>
                                            <span class="text-emerald-500 text-xs my-0.5">↓</span>
                                            <span>{{ $maxDate->format('Y') }}</span>
                                            <span>{{ $maxDate->format('m-d') }}</span>
                                        </div>
                                    @else
                                        -
                                    @endif
                                </td>

                                {{-- NAMA --}}
                                <td class="px-6 py-4 font-semibold text-slate-800 capitalize text-left">
                                    {{ strtolower((string)($row->nama ?? '-')) }}
                                </td>

                                {{-- WC --}}
                                <td class="px-6 py-4 text-center text-slate-700 font-medium">
                                    {{ $row->wc ?? '-' }}
                                </td>

                                {{-- DEVISI --}}
                                <td class="px-6 py-4 text-left text-slate-700 font-semibold">
                                    {{ $row->devisi ?? '-' }}
                                </td>

                                {{-- Time WI --}}
                                <td class="px-6 py-4 text-center text-gray-900 font-semibold tracking-tight">
                                    {{ number_format($wiSum, 2) }}
                                </td>

                                {{-- Time CONF --}}
                                <td class="px-6 py-4 text-center text-gray-900 font-semibold tracking-tight">
                                    {{ number_format($confSum, 2) }}
                                </td>

                                {{-- Time QM --}}
                                <td class="px-6 py-4 text-center text-gray-900 font-semibold tracking-tight">
                                    {{ number_format($qmSum, 2) }}
                                </td>

                                {{-- KPI Qty (WI/CONF) --}}
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded text-[11px] font-semibold
                                        {{ $kpiQty < 100 ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">
                                        {{ number_format($kpiQty, 2) }}%
                                    </span>
                                </td>

                                {{-- KPI Quality (QM/WI) --}}
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded text-[11px] font-semibold
                                        {{ $kpiQuality < 100 ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800' }}">
                                        {{ number_format($kpiQuality, 2) }}%
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($headersSummary) + 1 }}" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-400">
                                        <svg class="w-16 h-16 mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span class="text-xl font-medium text-gray-500">{{ __('Tidak ada data untuk filter saat ini.') }}</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                </table>
            </div>

            {{-- ✅ Missing NIK (hanya WI mode) --}}
            @if(!empty($missingNiks ?? []))
                <div class="mt-3 px-3 py-2 rounded-lg border border-amber-200 bg-amber-50 text-amber-800 text-[14px]">
                    <div class="flex items-start gap-2">
                        <span class="font-extrabold whitespace-nowrap">Tidak ditemukan:</span>
                        <div class="font-mono overflow-x-auto whitespace-nowrap w-full">
                            {{ implode(', ', $missingNiks) }}
                        </div>
                    </div>
                </div>
            @endif

        {{-- =========================== --}}
        {{-- ✅ MODE KORLAP (TABEL KORLAP) --}}
        {{-- =========================== --}}
        @else

            <div class="overflow-y-auto max-h-[75vh] shadow-xl sm:rounded-xl border border-gray-200/75">
                <table class="min-w-full divide-y divide-gray-200">

                    <thead class="sticky top-0 z-20 bg-gradient-to-r from-emerald-900 to-teal-950 text-white shadow-md">
                        <tr>
                            {{-- CHECKBOX KORLAP (Select All) --}}
                            <th class="sticky left-0 z-30 px-4 py-4 text-center w-10 bg-emerald-900 border-r border-emerald-800/30">
                                <input type="checkbox" id="wi-check-all-korlap"
                                       class="rounded border-gray-400 text-emerald-500 focus:ring-emerald-400 cursor-pointer bg-white/90 h-4 w-4">
                            </th>

                            <th class="px-6 py-4 text-center text-sm font-bold uppercase tracking-wider whitespace-nowrap text-emerald-50/90">No</th>
                            <th class="px-6 py-4 text-center text-sm font-bold uppercase tracking-wider whitespace-nowrap text-emerald-50/90">NIK Korlap</th>
                            <th class="px-6 py-4 text-left   text-sm font-bold uppercase tracking-wider whitespace-nowrap text-emerald-50/90">Nama Korlap</th>
                            <th class="px-6 py-4 text-left   text-sm font-bold uppercase tracking-wider whitespace-nowrap text-emerald-50/90">WC Anggota</th>
                            <th class="px-6 py-4 text-center text-sm font-bold uppercase tracking-wider whitespace-nowrap text-emerald-50/90">Jumlah NIK WI</th>
                            <th class="px-6 py-4 text-center text-sm font-bold uppercase tracking-wider whitespace-nowrap text-emerald-50/90">Time WI</th>
                            <th class="px-6 py-4 text-center text-sm font-bold uppercase tracking-wider whitespace-nowrap text-emerald-50/90">Time CONF</th>
                            <th class="px-6 py-4 text-center text-sm font-bold uppercase tracking-wider whitespace-nowrap text-emerald-50/90">Time QM</th>
                            <th class="px-6 py-4 text-center text-sm font-bold uppercase tracking-wider whitespace-nowrap text-emerald-50/90">% KPI Qty</th>
                            <th class="px-6 py-4 text-center text-sm font-bold uppercase tracking-wider whitespace-nowrap text-emerald-50/90">% KPI Quality</th>
                        </tr>
                    </thead>

                    <tbody class="bg-white divide-y divide-gray-200">

                        @forelse(($korlapData ?? []) as $k)
                            @php
                                $korlapNik = (string)($k['korlap_nik'] ?? '');

                                $kpiQty = (float)($k['kpi_qty_pct'] ?? 0);
                                $kpiQuality = (float)($k['kpi_quality_pct'] ?? 0);

                                $wcList = $k['wc_korlap'] ?? [];
                                if (!is_array($wcList)) $wcList = [];
                                $wcList = array_values(array_unique(array_map('trim', $wcList)));
                                sort($wcList);
                                $wcPreview = implode(', ', $wcList);

                                $isExpanded = in_array($korlapNik, ($expandedKorlaps ?? []), true);
                                $childRows = $korlapNikSummaries[$korlapNik] ?? [];
                            @endphp

                            {{-- ROW KORLAP --}}
                            <tr wire:key="korlap-row-{{ $korlapNik }}"
                                class="cursor-pointer group/korlap hover:bg-emerald-50/60 transition-all border-l-4 border-transparent {{ $isExpanded ? 'border-emerald-600 bg-emerald-50/80' : 'hover:border-emerald-300' }}">

                                {{-- CHECKBOX KORLAP (Sticky Left) --}}
                                <td class="sticky left-0 z-10 px-4 py-4 text-center bg-white group-hover/korlap:bg-emerald-50/60 border-r border-emerald-100/50"
                                    onclick="event.stopPropagation()">
                                    <input type="checkbox"
                                           wire:model.live="selectedKorlaps"
                                           value="{{ $korlapNik }}"
                                           class="wi-korlap-check rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer h-5 w-5 shadow-sm transition-all hover:scale-110">
                                </td>

                                <td wire:click="toggleKorlap({{ \Illuminate\Support\Js::from($korlapNik) }})" class="px-6 py-4 text-center font-bold text-slate-700">
                                    <span>{{ $loop->iteration }}</span>
                                </td>

                                <td wire:click="toggleKorlap({{ \Illuminate\Support\Js::from($korlapNik) }})" class="px-6 py-4 text-center font-mono font-semibold text-emerald-900/80">
                                    {{ $korlapNik ?: '-' }}
                                </td>

                                <td wire:click="toggleKorlap({{ \Illuminate\Support\Js::from($korlapNik) }})" class="px-6 py-4 text-left font-bold text-emerald-900 capitalize">
                                    {{ strtolower((string)($k['korlap_nama'] ?? '-')) }}
                                </td>

                                <td wire:click="toggleKorlap({{ \Illuminate\Support\Js::from($korlapNik) }})" class="px-6 py-4 text-left text-sm font-mono text-slate-600 whitespace-normal break-words">
                                    {{ $wcPreview ?: '-' }}
                                </td>

                                <td wire:click="toggleKorlap({{ \Illuminate\Support\Js::from($korlapNik) }})" class="px-6 py-4 text-center font-extrabold text-slate-800">
                                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-xs">
                                        {{ (int)($k['nik_count'] ?? 0) }} Orang
                                    </span>
                                </td>

                                <td wire:click="toggleKorlap({{ \Illuminate\Support\Js::from($korlapNik) }})" class="px-6 py-4 text-center font-semibold text-slate-700">
                                    {{ number_format((float)($k['time_wi_sum'] ?? 0), 2) }}
                                </td>

                                <td wire:click="toggleKorlap({{ \Illuminate\Support\Js::from($korlapNik) }})" class="px-6 py-4 text-center font-semibold text-slate-700">
                                    {{ number_format((float)($k['time_conf_sum'] ?? 0), 2) }}
                                </td>

                                <td wire:click="toggleKorlap({{ \Illuminate\Support\Js::from($korlapNik) }})" class="px-6 py-4 text-center font-semibold text-slate-700">
                                    {{ number_format((float)($k['time_qm_sum'] ?? 0), 2) }}
                                </td>

                                {{-- KPI Qty --}}
                                <td wire:click="toggleKorlap({{ \Illuminate\Support\Js::from($korlapNik) }})" class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-bold shadow-sm
                                        {{ $kpiQty < 100 ? 'bg-amber-100 text-amber-800 ring-1 ring-amber-200' : 'bg-blue-100 text-blue-800 ring-1 ring-blue-200' }}">
                                        {{ number_format($kpiQty, 2) }}%
                                    </span>
                                </td>

                                {{-- KPI Quality --}}
                                <td wire:click="toggleKorlap({{ \Illuminate\Support\Js::from($korlapNik) }})" class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-bold shadow-sm
                                        {{ $kpiQuality < 100 ? 'bg-red-100 text-red-800 ring-1 ring-red-200' : 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200' }}">
                                        {{ number_format($kpiQuality, 2) }}%
                                    </span>
                                </td>
                            </tr>

                            {{-- EXPAND AREA: LIST NIK SUMMARY --}}
                            @if($isExpanded)
                                <tr wire:key="korlap-expand-{{ $korlapNik }}">
                                    {{-- COLSPAN 11 (checkbox + 10 kolom) --}}
                                    <td colspan="11" class="p-0 border-b border-emerald-100/50">

                                        <div class="px-6 py-6 bg-gradient-to-b from-emerald-50/50 to-white shadow-inner">
                                            <div class="flex items-center justify-between mb-4 px-1">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-1 h-8 bg-emerald-500 rounded-full"></div>
                                                    <div>
                                                        <div class="text-sm font-extrabold text-emerald-900">
                                                            Detail Tim Korlap: <span class="uppercase">{{ strtolower((string)($k['korlap_nama'] ?? '-')) }}</span>
                                                        </div>
                                                        <div class="text-xs text-emerald-600/70 italic">
                                                            Klik baris NIK untuk melihat detail harian.
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="overflow-hidden rounded-xl border border-emerald-100 shadow-lg shadow-emerald-100/50 bg-white">
                                                <table class="min-w-full divide-y divide-emerald-50">

                                                    <thead class="bg-emerald-50/50">
                                                        <tr>
                                                            <th class="px-4 py-3 text-center text-xs font-black text-emerald-800/70 uppercase">No</th>
                                                            <th class="px-4 py-3 text-center text-xs font-black text-emerald-800/70 uppercase">NIK</th>
                                                            <th class="px-4 py-3 text-left   text-xs font-black text-emerald-800/70 uppercase">Nama</th>
                                                            <th class="px-4 py-3 text-center text-xs font-black text-emerald-800/70 uppercase">WC</th>
                                                            <th class="px-4 py-3 text-left   text-xs font-black text-emerald-800/70 uppercase">Devisi</th>
                                                            <th class="px-4 py-3 text-center text-xs font-black text-emerald-800/70 uppercase">Time WI</th>
                                                            <th class="px-4 py-3 text-center text-xs font-black text-emerald-800/70 uppercase">Time CONF</th>
                                                            <th class="px-4 py-3 text-center text-xs font-black text-emerald-800/70 uppercase">Time QM</th>
                                                            <th class="px-4 py-3 text-center text-xs font-black text-emerald-800/70 uppercase">% KPI Qty</th>
                                                            <th class="px-4 py-3 text-center text-xs font-black text-emerald-800/70 uppercase">% KPI Quality</th>
                                                        </tr>
                                                    </thead>

                                                    <tbody class="divide-y divide-emerald-50/50">
                                                        @forelse($childRows as $idx => $r)
                                                            @php
                                                                $nikChild  = (string)($r['nik'] ?? '');
                                                                $wiSum2    = (float)($r['time_wi_sum'] ?? 0);
                                                                $confSum2  = (float)($r['time_conf_sum'] ?? 0);
                                                                $qmSum2    = (float)($r['time_qm_sum'] ?? 0);
                                                                $kpiQty2     = (float)($r['kpi_qty_pct'] ?? 0);
                                                                $kpiQuality2 = (float)($r['kpi_quality_pct'] ?? 0);
                                                            @endphp

                                                            <tr class="hover:bg-emerald-50 transition-colors cursor-pointer group/child"
                                                                wire:click.stop="showNikDetail({{ \Illuminate\Support\Js::from($nikChild) }})">
                                                                <td class="px-4 py-3 text-center text-sm font-bold text-emerald-600/60">{{ $idx + 1 }}</td>
                                                                <td class="px-4 py-3 text-center text-sm font-mono text-slate-600 font-semibold group-hover/child:text-emerald-700">{{ $nikChild }}</td>
                                                                <td class="px-4 py-3 text-left text-sm font-semibold text-slate-700 capitalize group-hover/child:text-emerald-900">{{ strtolower((string)($r['nama'] ?? '-')) }}</td>
                                                                <td class="px-4 py-3 text-center text-sm font-mono text-slate-500">{{ $r['wc'] ?? '-' }}</td>
                                                                <td class="px-4 py-3 text-left text-sm font-medium text-slate-600">{{ $r['devisi'] ?? '-' }}</td>

                                                                <td class="px-4 py-3 text-center text-sm font-bold font-mono text-slate-700">
                                                                    {{ number_format($wiSum2, 2) }}
                                                                </td>

                                                                <td class="px-4 py-3 text-center text-sm font-bold font-mono text-slate-700">
                                                                    {{ number_format($confSum2, 2) }}
                                                                </td>

                                                                <td class="px-4 py-3 text-center text-sm font-bold font-mono text-slate-700">
                                                                    {{ number_format($qmSum2, 2) }}
                                                                </td>

                                                                <td class="px-4 py-3 text-center">
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold
                                                                        {{ $kpiQty2 < 100 ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700' }}">
                                                                        {{ number_format($kpiQty2, 2) }}%
                                                                    </span>
                                                                </td>

                                                                <td class="px-4 py-3 text-center">
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold
                                                                        {{ $kpiQuality2 < 100 ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-600' }}">
                                                                        {{ number_format($kpiQuality2, 2) }}%
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="10" class="px-4 py-8 text-center text-slate-400 italic">
                                                                    Tidak ada NIK yang match untuk WC korlap ini.
                                                                </td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>

                                                </table>
                                            </div>
                                        </div>

                                    </td>
                                </tr>
                            @endif

                        @empty
                            <tr>
                                <td colspan="11" class="px-6 py-16 text-center text-slate-500">
                                    <div class="flex flex-col items-center">
                                        <span class="text-lg font-medium text-slate-400">Tidak ada data Korlap untuk filter saat ini.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse

                    </tbody>
                </table>
            </div>

        @endif
    </div>

    {{-- ========================================================= --}}
    {{-- BAGIAN 4: MODAL DETAIL --}}
    {{-- ========================================================= --}}
    @if($showDetailModal ?? false)
        <div id="wi-modal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="wi-modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-full items-end sm:items-center justify-center pt-4 px-4 pb-20 text-center sm:p-0">

                {{-- Backdrop --}}
                <div class="fixed inset-0 bg-slate-900/75 transition-opacity backdrop-blur-sm" aria-hidden="true"
                     wire:click="closeDetailModal"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                {{-- Panel --}}
                <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-7xl sm:w-full border border-gray-200">

                    {{-- Header --}}
                    <div class="bg-gradient-to-r from-emerald-700 to-teal-800 px-6 py-4 sm:px-8 flex justify-between items-center">
                        <div>
                            <h3 class="text-xl leading-6 font-bold text-white tracking-wide flex items-center gap-2" id="wi-modal-title">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-emerald-200" fill="none"
                                     viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Detail Tanggal
                                <span class="text-emerald-200 font-mono bg-white/10 px-2 rounded ml-1 text-lg">{{ $selectedNik ?? '-' }}</span>
                            </h3>

                            {{-- TOTAL DI MODAL --}}
                            <div class="text-xs text-emerald-100 mt-1">
                                Total WI: <b>{{ number_format((float)($detailTotalWi ?? 0), 2) }}</b>
                                &nbsp;|&nbsp;
                                Total CONF: <b>{{ number_format((float)($detailTotalConf ?? 0), 2) }}</b>
                                &nbsp;|&nbsp;
                                Total QM: <b>{{ number_format((float)($detailTotalQm ?? 0), 2) }}</b>
                                &nbsp;|&nbsp;
                                KPI Qty: <b>{{ number_format((float)($detailKpiQty ?? 0), 2) }}%</b>
                                &nbsp;|&nbsp;
                                KPI Quality: <b>{{ number_format((float)($detailKpiQuality ?? 0), 2) }}%</b>
                            </div>
                        </div>

                        <button wire:click="closeDetailModal" type="button"
                                class="text-emerald-100 hover:text-white transition-colors focus:outline-none">
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none"
                                 viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-6 sm:px-8 sm:py-8 bg-slate-50">
                        <div class="overflow-x-auto overflow-y-auto max-h-[60vh] shadow-md sm:rounded-lg bg-white border border-gray-200">
                            <table id="wi-detail-table" class="min-w-full divide-y divide-gray-100">

                                <thead class="sticky top-0 z-20 bg-emerald-50">
                                    <tr>
                                        {{-- CHECKBOX ALL (DETAIL) --}}
                                        <th class="sticky left-0 z-30 px-4 py-3 text-center text-xs font-bold text-emerald-800 uppercase tracking-wider border-b-2 border-emerald-100 bg-emerald-50">
                                            <label class="inline-flex items-center gap-2 select-none">
                                                <input id="wi-check-all-detail" type="checkbox"
                                                       class="rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500">
                                            </label>
                                        </th>

                                        @foreach($headersDetail as $header)
                                            <th scope="col"
                                                class="px-4 py-3 text-center text-xs font-bold text-emerald-800 uppercase tracking-wider border-b-2 border-emerald-100 whitespace-nowrap">
                                                {{ __($header) }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>

                                <tbody class="bg-white divide-y divide-gray-100">
                                    @foreach(($detailData ?? []) as $d)
                                        @php
                                            $timeWi   = $d['time_wi'] ?? null;
                                            $timeConf = $d['time_conf'] ?? null;
                                            $timeQm   = $d['time_qm'] ?? null;

                                            $tanggalVal = (string)($d['tanggal'] ?? '');
                                            $nikVal = (string)($d['nik'] ?? ($selectedNik ?? ''));
                                            $detailKey = $nikVal . '|' . $tanggalVal;

                                            $kpiQtyRow = $d['kpi_qty_pct'] ?? null;
                                            $kpiQualityRow = $d['kpi_quality_pct'] ?? null;

                                            // Kalau WI null => KPI ditampilkan "-"
                                            if (is_null($timeWi)) {
                                                $kpiQtyRow = null;
                                                $kpiQualityRow = null;
                                            } else {
                                                // fallback kalau tidak ada
                                                if (is_null($kpiQualityRow)) {
                                                    $kpiQualityRow = ((float)$timeWi == 0) ? 0 : (((float)($timeQm ?? 0) / (float)$timeWi) * 100);
                                                }
                                                if (is_null($kpiQtyRow)) {
                                                    $confBase = (float)($timeConf ?? 0);
                                                    $kpiQtyRow = ($confBase == 0) ? 0 : (((float)($timeWi ?? 0) / $confBase) * 100);
                                                }
                                            }
                                        @endphp

                                        <tr class="group/detail hover:bg-emerald-50/50 transition-colors"
                                            wire:key="wi-detail-row-{{ $detailKey }}">

                                            {{-- CHECKBOX (DETAIL) STICKY LEFT --}}
                                            <td class="sticky left-0 z-10 px-6 py-3 whitespace-nowrap text-sm bg-white group-hover/detail:bg-emerald-50/50">
                                                <input type="checkbox"
                                                       class="wi-detail-check rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                                                       wire:model.live="selectedDetailKeys"
                                                       value="{{ $detailKey }}">
                                            </td>

                                            {{-- NO --}}
                                            <td class="px-4 py-2 whitespace-nowrap text-sm font-bold text-emerald-800 text-center">
                                                {{ $loop->iteration }}
                                            </td>

                                            {{-- NIK --}}
                                            <td class="px-4 py-2 whitespace-nowrap text-sm font-mono text-center">
                                                {{ $nikVal ?: '-' }}
                                            </td>

                                            {{-- TANGGAL --}}
                                            <td class="px-4 py-2 whitespace-nowrap text-sm font-mono text-center">
                                                {{ $tanggalVal ?: '-' }}
                                            </td>

                                            {{-- NAMA --}}
                                            <td class="px-4 py-2 text-sm font-semibold text-left capitalize">
                                                {{ strtolower((string)($d['nama'] ?? '-')) }}
                                            </td>

                                            {{-- WC --}}
                                            <td class="px-4 py-2 text-sm font-mono text-center">
                                                {{ $d['wc'] ?? '-' }}
                                            </td>

                                            {{-- DEVISI --}}
                                            <td class="px-4 py-2 text-sm font-semibold text-left">
                                                {{ $d['devisi'] ?? '-' }}
                                            </td>

                                            {{-- Time WI --}}
                                            <td class="px-4 py-2 text-sm font-bold text-center font-mono">
                                                {{ is_null($timeWi) ? '-' : number_format((float)$timeWi, 2) }}
                                            </td>

                                            {{-- Time CONF --}}
                                            <td class="px-4 py-2 text-sm font-bold text-center font-mono">
                                                {{ is_null($timeConf) ? '-' : number_format((float)$timeConf, 2) }}
                                            </td>

                                            {{-- Time QM --}}
                                            <td class="px-4 py-2 text-sm font-bold text-center font-mono">
                                                {{ is_null($timeQm) ? '-' : number_format((float)$timeQm, 2) }}
                                            </td>

                                            {{-- KPI Qty --}}
                                            <td class="px-4 py-2 text-center">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded text-[11px] font-semibold
                                                    {{ is_null($kpiQtyRow) ? 'bg-slate-100 text-slate-500' : ($kpiQtyRow < 100 ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800') }}">
                                                    {{ is_null($kpiQtyRow) ? '-' : (number_format((float)$kpiQtyRow, 2) . '%') }}
                                                </span>
                                            </td>

                                            {{-- KPI Quality --}}
                                            <td class="px-4 py-2 text-center">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded text-[11px] font-semibold
                                                    {{ is_null($kpiQualityRow) ? 'bg-slate-100 text-slate-500' : ($kpiQualityRow < 100 ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800') }}">
                                                    {{ is_null($kpiQualityRow) ? '-' : (number_format((float)$kpiQualityRow, 2) . '%') }}
                                                </span>
                                            </td>

                                        </tr>
                                    @endforeach
                                </tbody>

                            </table>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="bg-white px-6 py-4 sm:px-8 sm:flex sm:flex-row-reverse items-center gap-3 border-t border-gray-200">
                        <div class="flex flex-col sm:flex-row-reverse gap-2 w-full sm:w-auto">
                            <button wire:click="closeDetailModal" type="button"
                                    class="mt-3 sm:mt-0 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-5 py-2.5 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:w-auto sm:text-sm transition-all">
                                Tutup
                            </button>
                        </div>

                        <div class="mt-3 sm:mt-0 sm:mr-auto text-sm text-gray-500 italic">
                            Pilih tanggal untuk menambah pilihan Export Detail (badge akan ikut bertambah).
                        </div>
                    </div>

                </div>
            </div>
        </div>
    @endif

</div>

{{-- ========================================================= --}}
{{-- SCRIPT (Dropdown + Toast + Copy + Check All) --}}
{{-- ========================================================= --}}
@once
    @push('scripts')
        <script>
            (function () {
              if (window.__wiBound) return;
              window.__wiBound = true;

              const $  = (sel, root = document) => root.querySelector(sel);
              const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

              function showToast(message, type = 'success') {
                const container = $('#wi-toast-container');
                if (!container) return;

                const colors = {
                  success: 'bg-emerald-600',
                  warning: 'bg-amber-600',
                  error:   'bg-rose-600',
                  info:    'bg-slate-700',
                };

                const el = document.createElement('div');
                el.className = `text-white ${colors[type] || colors.info} shadow-2xl rounded-xl px-4 py-3 text-sm font-semibold flex items-start gap-3 max-w-[360px]`;
                el.innerHTML = `
                  <div class="mt-0.5">
                    <svg class="w-5 h-5 opacity-95" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01M12 5.5a6.5 6.5 0 100 13 6.5 6.5 0 000-13z"/>
                    </svg>
                  </div>
                  <div class="flex-1 leading-snug">${String(message || '')}</div>
                  <button type="button" class="ml-2 opacity-80 hover:opacity-100">✕</button>
                `;

                el.querySelector('button')?.addEventListener('click', () => el.remove());
                container.appendChild(el);

                setTimeout(() => {
                  el.style.transition = 'all 300ms ease';
                  el.style.opacity = '0';
                  el.style.transform = 'translateY(-6px)';
                  setTimeout(() => el.remove(), 320);
                }, 3000);
              }

              function getSelectedForCopy() {
                const root = $('#wi-root');
                if (!root) return [];

                const mode = String(root.dataset.reportMode || 'wi').trim();
                const key  = mode === 'korlap' ? 'selectedKorlaps' : 'selectedNiks';

                try { return JSON.parse(root.dataset[key] || '[]') || []; }
                catch { return []; }
              }

              async function copySelected() {
                const root = $('#wi-root');
                const mode = String(root?.dataset?.reportMode || 'wi').trim();

                const arr = getSelectedForCopy().map(v => String(v || '').trim()).filter(Boolean);

                if (!arr.length) {
                  showToast(mode === 'korlap'
                    ? 'Belum ada NIK Korlap yang dipilih di tabel ringkasan.'
                    : 'Belum ada NIK yang dipilih di tabel ringkasan.'
                  , 'warning');
                  return;
                }

                const text = arr.join(' ');

                try {
                  if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(text);
                  } else {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                  }
                  showToast(`Berhasil copy ${arr.length} data.`, 'success');
                } catch {
                  showToast('Gagal menyalin. Silakan copy manual dari pilihan.', 'error');
                }
              }

              // CLICK HANDLER (delegation)
              document.addEventListener('click', (e) => {
                const exportBtn       = e.target.closest('#wi-export-dropdown-button');
                const exportDetailBtn = e.target.closest('#wi-export-detail-dropdown-button');
                const copyBtn         = e.target.closest('#wi-btn-copy-nik');

                const exportMenu       = $('#wi-export-dropdown-menu');
                const exportDetailMenu = $('#wi-export-detail-dropdown-menu');

                if (exportBtn) {
                  e.preventDefault(); e.stopPropagation();
                  exportMenu?.classList.toggle('hidden');
                  exportDetailMenu?.classList.add('hidden');
                  return;
                }

                if (exportDetailBtn) {
                  e.preventDefault(); e.stopPropagation();
                  exportDetailMenu?.classList.toggle('hidden');
                  exportMenu?.classList.add('hidden');
                  return;
                }

                if (copyBtn) {
                  e.preventDefault(); e.stopPropagation();
                  copySelected();
                  return;
                }

                // klik di luar -> tutup dropdown
                if (exportMenu && !exportMenu.classList.contains('hidden') && !exportMenu.contains(e.target)) {
                  exportMenu.classList.add('hidden');
                }
                if (exportDetailMenu && !exportDetailMenu.classList.contains('hidden') && !exportDetailMenu.contains(e.target)) {
                  exportDetailMenu.classList.add('hidden');
                }
              });

              // CHECK ALL (delegation)
              document.addEventListener('change', (e) => {
                // 1. Check All NIK Summary
                if (e.target && e.target.id === 'wi-check-all-summary') {
                  $$('.wi-summary-check').forEach(cb => {
                    cb.checked = e.target.checked;
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                    cb.dispatchEvent(new Event('input', { bubbles: true }));
                  });
                }

                // 2. Check All Detail Modal
                if (e.target && e.target.id === 'wi-check-all-detail') {
                  const modal = $('#wi-modal') || document;
                  $$('.wi-detail-check', modal).forEach(cb => {
                    cb.checked = e.target.checked;
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                    cb.dispatchEvent(new Event('input', { bubbles: true }));
                  });
                }

                // 3. Check All Korlap
                if (e.target && e.target.id === 'wi-check-all-korlap') {
                  $$('.wi-korlap-check').forEach(cb => {
                    cb.checked = e.target.checked;
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                    cb.dispatchEvent(new Event('input', { bubbles: true }));
                  });
                }
              });

              // Livewire browser events
              document.addEventListener('wi-open-url', (e) => {
                const url = e?.detail?.url;
                if (url) window.open(url, '_blank');
              });

              document.addEventListener('wi-toast', (e) => {
                showToast(e?.detail?.message || '', e?.detail?.type || 'info');
              });
            })();
        </script>
    @endpush
@endonce
