@php
    use Carbon\Carbon;

    // Normalisasi & hitung selection
    $selected = array_map('strval', $selectedPernrs ?? []);
    $selectedCount = count($selected);

    // Pernr yang ada di hasil filter saat ini
    $currentPernrs = $rows->pluck('pernr')->map(fn($p) => (string) $p)->all();

    // Apakah semua pernr hasil filter sekarang sudah terpilih?
    $isAllSelected =
        !empty($currentPernrs) && count(array_intersect($currentPernrs, $selected)) === count($currentPernrs);

    // Lookup table supaya @checked cepat
    $selectedSet = [];
    foreach ($selected as $p) {
        $selectedSet[$p] = true;
    }
@endphp

<div
    class="bg-white overflow-hidden shadow-[0_25px_50px_-12px_rgba(0,0,0,0.25)] sm:rounded-xl p-8 border border-emerald-100/50">

    {{-- HEADER + TOMBOL EXPORT --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mb-6">
        <div>
            <h3 class="text-3xl font-bold text-emerald-800 tracking-wide">
                {{ __('WC Person') }} — wc_person_data
            </h3>
            <p class="mt-1 text-sm text-gray-600">
                Pencarian multi input (spasi/koma). Untuk nama tepat gunakan tanda kutip, contoh nama:
                <code>"***** ****"</code>.
            </p>
        </div>

        {{-- Dropdown Export --}}
        <div class="flex flex-col items-end gap-1">
            <div class="relative inline-block text-left">
                <button id="wc-export-dropdown-button" type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-emerald-500 bg-emerald-600 px-4 py-2
                           text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none
                           focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">

                    {{-- Icon download --}}
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                    </svg>

                    <span>Export</span>

                    {{-- BADGE JUMLAH TERPILIH (langsung dari Livewire) --}}
                    <span
                        class="inline-flex items-center justify-center rounded-full bg-emerald-700/80 px-2 py-0.5
                               text-[11px] font-bold">
                        {{ $selectedCount }}
                    </span>

                    {{-- Icon caret --}}
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div id="wc-export-dropdown-menu"
                    class="hidden origin-top-right absolute right-0 mt-2 w-40 rounded-md shadow-lg bg-white ring-1 ring-black/10 z-20">
                    <div class="py-1">
                        {{-- PDF --}}
                        <button type="button" wire:click.prevent="export('pdf')"
                            class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            PDF
                        </button>

                        {{-- Excel --}}
                        <button type="button" wire:click.prevent="export('excel')"
                            class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            Excel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- FILTER --}}
    <div class="mb-8 p-6 bg-emerald-50 rounded-lg shadow-inner border border-emerald-100">
        <p class="text-lg font-bold text-emerald-700 mb-4">
            {{ __('Pencarian (semua kolom, tanpa ARBID):') }}
        </p>

        <div class="grid grid-cols-1 gap-6">
            <div class="relative">
                <x-text-input type="text" wire:model.live.debounce.500ms="q" placeholder=" "
                    class="floating-input peer block w-full p-3 border-gray-300
                           focus:border-emerald-600 focus:ring-emerald-600
                           rounded-lg shadow-sm transition duration-150" />

                <x-input-label for="q"
                    value="{{ __('Ketik kata kunci: NIK/WC/OBJID/DESC/… (boleh banyak, pisahkan spasi/koma). Nama/Deskripsi gunakan kutip.') }}"
                    class="floating-label absolute text-gray-500 duration-300 transform -translate-y-4 scale-75
                           top-4 left-4 z-10 origin-[0]
                           peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0
                           peer-focus:scale-75 peer-focus:-translate-y-4" />
            </div>
        </div>
        {{-- wire:loading dihapus --}}
    </div>

    {{-- TABEL --}}
    <div class="overflow-x-auto overflow-y-auto max-h-[75vh] shadow-xl sm:rounded-lg border border-gray-200/75">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="sticky top-0 z-10 bg-gradient-to-r from-emerald-700 to-green-800">
                <tr>
                    {{-- Kolom checkbox + label --}}
                    <th scope="col"
                        class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">
                        <label class="inline-flex items-center gap-2 select-none">
                            <input id="wc-check-all" type="checkbox" wire:click="toggleSelectAll"
                                @checked($isAllSelected)
                                class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                            <span>Pilih</span>
                        </label>
                    </th>

                    @foreach ($headers as $header)
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">
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
                    @endphp

                    <tr wire:key="wc-row-{{ $row->id }}" @class([
                        'odd:bg-white even:bg-emerald-50/60 hover:bg-emerald-100 transition-colors duration-200 ease-in-out',
                        'bg-amber-50 hover:bg-amber-100 ring-1 ring-amber-300' => $isInduk,
                    ])>

                        {{-- CHECKBOX PILIH NIK --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <input type="checkbox" value="{{ $pernrKey }}"
                                wire:click="togglePernr('{{ $pernrKey }}')" @checked(isset($selectedSet[$pernrKey]))
                                class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                        </td>

                        {{-- NO --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-extrabold text-emerald-800">
                            {{ $loop->iteration }}
                        </td>

                        {{-- NIK --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-mono">
                            {{ $row->pernr }}
                        </td>

                        {{-- TGL MULAI (begda) --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 font-mono">
                            @if ($row->begda && preg_match('/^\d{8}$/', $row->begda))
                                {{ Carbon::createFromFormat('Ymd', $row->begda)->isoFormat('YY-MM-DD') }}
                            @else
                                {{ $row->begda }}
                            @endif
                        </td>

                        {{-- NAMA (stext) TANPA BADGE --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $row->stext }}
                        </td>

                        {{-- ROLE (INDUK) --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            @if ($isInduk)
                                <span
                                    class="inline-flex items-center gap-2 px-3 py-1 rounded-full
                                           bg-gradient-to-r from-yellow-300 via-amber-300 to-yellow-400
                                           text-[11px] font-extrabold text-amber-900 shadow-sm border border-amber-400/80">
                                    <span class="text-[14px] leading-none">👑</span>
                                    <span>INDUK</span>
                                </span>
                            @endif
                        </td>

                        {{-- WORK CENTER --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $row->arbpl }}
                        </td>

                        {{-- DESKRIPSI WORK CENTER --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                            {{ $row->desc ?? $row->short }}
                        </td>

                        {{-- PLANT --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-mono">
                            {{ $row->werks }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($headers) + 1 }}"
                            class="px-6 py-10 text-center text-lg text-gray-500 bg-gray-50">
                            {{ __('Tidak ada data untuk filter saat ini.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@once
    @push('scripts')
        <script>
            (function() {
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

                // Alert sederhana dari Livewire
                window.addEventListener('wc-person-alert', function(e) {
                    if (e.detail && e.detail.message) {
                        alert(e.detail.message);
                    }
                });

                // Perintah export → buka URL di tab baru
                window.addEventListener('wc-person-export', function(e) {
                    if (e.detail && e.detail.url) {
                        window.open(e.detail.url, '_blank');
                    }
                });
            })
            ();
        </script>
    @endpush
@endonce
