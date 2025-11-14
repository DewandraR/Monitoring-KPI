<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-emerald-800 leading-tight">
            {{ __('Ringkasan Plant (WERKS)') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-100 rounded-xl">
                <p class="text-emerald-800 font-semibold">
                    Klik salah satu Plant untuk melihat laporan terfilter Plant tersebut.
                </p>
            </div>

            @if (($plants ?? collect())->isEmpty())
                <div class="bg-white p-8 rounded-xl shadow text-center text-gray-500">
                    Tidak ada data Plant yang dapat ditampilkan.
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach ($plants as $p)
                        <a href="{{ route('report-data', ['werks' => $p->werks]) }}"
                            class="group block bg-white rounded-2xl border border-gray-200 shadow hover:shadow-lg transition
                                  hover:-translate-y-0.5">
                            <div class="p-6">
                                <div class="flex items-center justify-between">
                                    <div class="text-3xl font-extrabold text-emerald-700">
                                        {{ $p->werks }}
                                    </div>
                                    <div
                                        class="px-3 py-1 rounded-full text-xs font-bold
                                                bg-emerald-100 text-emerald-800">
                                        PLANT
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <div class="text-sm text-gray-500">Jumlah NIK</div>
                                    <div class="text-2xl font-bold text-gray-900">
                                        {{ number_format($p->rows_count) }}
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <span
                                        class="inline-flex items-center text-emerald-700 font-semibold group-hover:underline">
                                        Lihat laporan
                                        <svg class="ms-2 h-4 w-4" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5l7 7-7 7" />
                                        </svg>
                                    </span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
