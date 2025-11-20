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
@endphp

{{-- ROOT ELEMENT DIMULAI DI SINI --}}
<div
    class="bg-white overflow-hidden shadow-[0_25px_50px_-12px_rgba(0,0,0,0.15)] sm:rounded-xl p-8 border border-emerald-50 relative">

    {{-- DEKORASI LATAR BELAKANG --}}
    <div
        class="absolute top-0 right-0 -mt-4 -mr-4 w-64 h-64 bg-gradient-to-br from-emerald-100/40 to-transparent rounded-full blur-3xl pointer-events-none">
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
                Data Personalia Work Center.
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

                    {{-- Animasi Kilau --}}
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

                    {{-- PDF Option --}}
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

                    {{-- Excel Option --}}
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
    {{-- BAGIAN 2: FILTER PENCARIAN --}}
    {{-- ======================================================================== --}}
    <div class="mb-6 p-4 bg-emerald-50/50 rounded-xl shadow-inner border border-emerald-100/80 backdrop-blur-sm">
        <p class="text-sm font-bold text-emerald-800 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            {{ __('Filter Data:') }}
        </p>

        <div class="grid grid-cols-1 gap-4">
            <div class="relative group">
                <input type="text" id="q-input" wire:model.live.debounce.500ms="q" placeholder=" "
                    class="peer block w-full pt-5 pb-1.5 px-3 border-gray-300 text-sm text-gray-900 bg-white rounded-lg 
                           shadow-sm focus:border-emerald-500 focus:ring-emerald-500 focus:ring-2 transition-all duration-200
                           placeholder-transparent h-11" />

                <label for="q-input"
                    class="absolute text-gray-500 duration-300 transform 
                           top-3 left-3 z-10 origin-[0] 
                           -translate-y-2.5 scale-75 text-emerald-600 font-bold
                           peer-placeholder-shown:scale-100 
                           peer-placeholder-shown:translate-y-0 
                           peer-placeholder-shown:text-gray-500
                           peer-placeholder-shown:font-normal
                           peer-focus:scale-75 
                           peer-focus:-translate-y-2.5 
                           peer-focus:text-emerald-600
                           peer-focus:font-bold">
                    {{ __('Kata Kunci Pencarian') }}
                </label>

                <p class="mt-1 text-xs text-gray-500">
                    Cari: <span class="font-semibold text-emerald-700">NIK, Nama, WC, Desc WC, Devisi, Plant</span>.
                    Gunakan tanda kutip untuk hasil tepat,
                    misal: <code class="bg-gray-100 px-1 rounded text-emerald-700 font-mono text-[11px]">"Nama
                        Karyawan"</code>,
                    <code class="bg-gray-100 px-1 rounded text-emerald-700 font-mono text-[11px]">"DESC WC"</code>,
                    <code class="bg-gray-100 px-1 rounded text-emerald-700 font-mono text-[11px]">"WOOD WORKING"</code>.
                </p>
            </div>
        </div>
    </div>

    {{-- ======================================================================== --}}
    {{-- BAGIAN 3: TABEL DATA (DIPERKECIL) --}}
    {{-- ======================================================================== --}}
    <div class="overflow-y-auto max-h-[75vh] shadow-xl sm:rounded-xl border border-gray-200/75">
        <table class="min-w-full table-fixed divide-y divide-gray-200 text-xs">

            {{-- ATUR LEBAR KOLOM --}}
            <colgroup>
                <col class="w-[40px]" /> {{-- checkbox --}}
                <col class="w-[40px]" /> {{-- No --}}
                <col class="w-[90px]" /> {{-- NIK --}}
                <col class="w-[80px]" /> {{-- Tgl Mulai --}}
                <col class="w-[160px]" /> {{-- Nama --}}
                <col class="w-[80px]" /> {{-- Role --}}
                <col class="w-[80px]" /> {{-- WC --}}
                <col class="w-[280px]" /> {{-- Deskripsi --}}
                <col class="w-[120px]" /> {{-- Devisi --}}
                <col class="w-[70px]" /> {{-- Plant --}}
            </colgroup>

            {{-- TABLE HEADER --}}
            <thead class="sticky top-0 z-10 bg-gradient-to-r from-emerald-800 to-teal-900 text-white shadow-md">
                <tr>
                    {{-- Kolom Checkbox --}}
                    <th scope="col" class="px-2 py-3 text-left text-[11px] font-bold uppercase tracking-wider">
                        <label class="inline-flex items-center gap-1 select-none cursor-pointer group">
                            <input id="wc-check-all" type="checkbox" wire:click="toggleSelectAll"
                                @checked($isAllSelected)
                                class="rounded border-gray-300 text-emerald-500 focus:ring-emerald-500 transition-colors cursor-pointer bg-white/90 h-3.5 w-3.5" />
                            <span class="group-hover:text-emerald-100 transition-colors text-[10px]">Pilih</span>
                        </label>
                    </th>

                    {{-- Loop Header --}}
                    @foreach ($headers as $header)
                        <th scope="col"
                            class="px-2 py-3 text-left text-[11px] font-bold uppercase tracking-wider truncate">
                            {{ __($header) }}
                        </th>
                    @endforeach
                </tr>
            </thead>

            {{-- TABLE BODY --}}
            <tbody class="bg-white divide-y divide-gray-200 text-[13px]">
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

                        {{-- CHECKBOX --}}
                        <td class="px-2 py-2 whitespace-nowrap align-middle">
                            <input type="checkbox" value="{{ $pernrKey }}"
                                wire:click="togglePernr('{{ $pernrKey }}')" @checked($isChecked)
                                class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer h-4 w-4" />
                        </td>

                        {{-- NO --}}
                        <td class="px-2 py-2 whitespace-nowrap font-bold text-emerald-800/80 text-center align-middle">
                            {{ $loop->iteration }}
                        </td>

                        {{-- NIK --}}
                        <td
                            class="px-2 py-2 whitespace-nowrap text-slate-700 text-right font-mono tracking-tight align-middle">
                            {{ $row->pernr }}
                        </td>

                        {{-- TGL MULAI --}}
                        <td class="px-2 py-2 whitespace-nowrap text-slate-500 font-mono text-center align-middle">
                            @if ($row->begda && preg_match('/^\d{8}$/', $row->begda))
                                {{ Carbon::createFromFormat('Ymd', $row->begda)->isoFormat('YY-MM-DD') }}
                            @else
                                {{ $row->begda }}
                            @endif
                        </td>

                        {{-- NAMA (wrap, max-width kecil) --}}
                        <td
                            class="px-2 py-2 text-slate-800 font-semibold whitespace-normal break-words max-w-[160px] align-middle">
                            {{ $row->stext }}
                        </td>

                        {{-- ROLE --}}
                        <td class="px-2 py-2 whitespace-nowrap text-[11px] align-middle">
                            @if ($isInduk)
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                           bg-gradient-to-b from-yellow-100 to-amber-200
                                           text-[9px] font-extrabold text-amber-900 shadow-sm border border-amber-300 uppercase tracking-wide">
                                    <span>👑</span> INDUK
                                </span>
                            @else
                                <span class="text-slate-400 text-[11px]">-</span>
                            @endif
                        </td>

                        {{-- WORK CENTER --}}
                        <td class="px-2 py-2 whitespace-nowrap text-slate-600 font-medium align-middle">
                            {{ $row->arbpl }}
                        </td>

                        {{-- DESKRIPSI WC (wrap, max-width) --}}
                        <td class="px-2 py-2 text-slate-600 whitespace-normal break-words max-w-[260px] align-middle">
                            {{ $row->desc ?? $row->short }}
                        </td>

                        {{-- DEVISI (wrap sedikit) --}}
                        <td
                            class="px-2 py-2 text-slate-700 font-semibold whitespace-normal break-words max-w-[130px] align-middle">
                            {{ $row->devisi ?? '-' }}
                        </td>

                        {{-- PLANT --}}
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
                                <span class="text-sm">{{ __('Coba ubah kata kunci pencarian Anda.') }}</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- CSS --}}
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

</div> {{-- END OF ROOT DIV --}}

@once
    @push('scripts')
        <script>
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
                        if (!menu.classList.contains('hidden') && !btn.contains(e.target) && !menu.contains(e
                                .target)) {
                            menu.classList.add('hidden');
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
            });
        </script>
    @endpush
@endonce
