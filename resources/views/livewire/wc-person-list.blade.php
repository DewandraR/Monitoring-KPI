@php use Carbon\Carbon; @endphp

<div
    class="bg-white overflow-hidden shadow-[0_25px_50px_-12px_rgba(0,0,0,0.25)] sm:rounded-xl p-8 border border-emerald-100/50">
    <h3 class="text-3xl font-bold text-emerald-800 tracking-wide">
        {{ __('WC Person') }} — wc_person_data
    </h3>
    <p class="mt-1 mb-6 text-sm text-gray-600">
        Pencarian multi input (spasi/koma). Untuk nama tepat gunakan tanda kutip, contoh nama: <code>"*****
            ****"</code>.
    </p>

    <div class="mb-8 p-6 bg-emerald-50 rounded-lg shadow-inner border border-emerald-100">
        <p class="text-lg font-bold text-emerald-700 mb-4">
            {{ __('Pencarian (semua kolom, tanpa ARBID):') }}
        </p>

        <div class="grid grid-cols-1 gap-6">
            <div class="relative">
                <x-text-input type="text" wire:model.live.debounce.500ms="q" placeholder=" "
                    class="floating-input peer block w-full p-3 border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-lg shadow-sm transition duration-150" />
                <x-input-label for="q"
                    value="{{ __('Ketik kata kunci: NIK/WC/OBJID/… (boleh banyak, pisahkan spasi/koma). Nama gunakan kutip.') }}"
                    class="floating-label absolute text-gray-500 duration-300 transform -translate-y-4 scale-75 top-4 left-4 z-10 origin-[0]
                                peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0
                                peer-focus:scale-75 peer-focus:-translate-y-4" />
            </div>
        </div>

        <div wire:loading class="mt-4 text-center text-emerald-700 font-semibold transition duration-150">
            {{ __('Memuat data...') }}
        </div>
    </div>

    {{-- [PERUBAHAN] Container tabel dibuat lebih bersih --}}
    <div class="overflow-x-auto shadow-xl sm:rounded-lg border border-gray-200/75">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-emerald-700 to-green-800">
                <tr>
                    @foreach ($headers as $header)
                        <th scope="col" {{-- [PERUBAHAN] Padding header dibuat lebih ramping (py-3) --}}
                            class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">
                            {{ __($header) }}
                        </th>
                    @endforeach
                </tr>
            </thead>

            {{-- [PERUBAHAN] Divider antar baris dibuat sedikit lebih jelas --}}
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($rows as $row)
                    {{-- [PERUBAHAN BESAR] Zebra-striping & hover effect --}}
                    <tr
                        class="odd:bg-white even:bg-emerald-50/60 hover:bg-emerald-100 transition-colors duration-200 ease-in-out">

                        {{-- No --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-extrabold text-emerald-800">
                            {{ $loop->iteration }}
                        </td>

                        {{-- [PERUBAHAN] Teks kode dibuat lebih ringan --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $row->otype }}</td>

                        {{-- [PERUBAHAN] Data numerik/ID: rata kanan & font-mono --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-mono">
                            {{ $row->objid }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-mono">
                            {{ $row->pernr }}</td>

                        {{-- [PERUBAHAN] Tanggal: font-mono agar rapi --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 font-mono">
                            @if ($row->begda && preg_match('/^\d{8}$/', $row->begda))
                                {{ Carbon::createFromFormat('Ymd', $row->begda)->isoFormat('YY-MM-DD') }}
                            @else
                                {{ $row->begda }}
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 font-mono">
                            @if ($row->endda && preg_match('/^\d{8}$/', $row->endda))
                                {{ Carbon::createFromFormat('Ymd', $row->endda)->isoFormat('YY-MM-DD') }}
                            @else
                                {{ $row->endda }}
                            @endif
                        </td>

                        {{-- [PERUBAHAN] Nama/Teks Utama: dibuat font-medium agar menonjol --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $row->short }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $row->stext }}
                        </td>

                        {{-- [PERUBAHAN] Teks kode dibuat lebih ringan --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $row->arbpl }}</td>

                        {{-- [PERUBAHAN] Data numerik/ID: rata kanan & font-mono --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-mono">
                            {{ $row->werks }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($headers) }}"
                            class="px-6 py-10 text-center text-lg text-gray-500 bg-gray-50">
                            {{ __('Tidak ada data untuk filter saat ini.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
