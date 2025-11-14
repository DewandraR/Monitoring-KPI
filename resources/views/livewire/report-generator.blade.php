@php
    use Carbon\Carbon;

    // Hilangkan "Shift" dari header asli lalu tambahkan sekali di ujung
    $headersNoShift = array_values(array_filter($headers ?? [], fn($h) => mb_strtolower(trim($h)) !== 'shift'));
    $headersSummary = array_merge($headersNoShift, ['Shift']);

    // Header modal: "Pilih" + header asli (tanpa Shift) + "Shift"
    $headersModal = array_merge(['Pilih'], $headersNoShift, ['Shift']);
@endphp

<div
    class="bg-white overflow-hidden shadow-[0_25px_50px_-12px_rgba(0,0,0,0.25)] sm:rounded-xl p-8 border border-emerald-100/50">
    <h3 class="text-3xl font-bold text-emerald-800 tracking-wide">
        {{ __('Report Data - yppr058_data') }} (Ringkasan per Personal No.)
    </h3>
    <p class="mt-1 mb-6 text-sm text-gray-600">
        Plant terpilih:
        <span class="font-bold text-emerald-700">{{ $werks ?? request()->route('werks') }}</span>
    </p>

    {{-- ===== Filter ===== --}}
    <div class="mb-8 p-6 bg-emerald-50 rounded-lg shadow-inner border border-emerald-100">
        <p class="text-lg font-bold text-emerald-700 mb-4">{{ __('Filter Data Berdasarkan Kriteria:') }}</p>
        <div class="grid grid-cols-1 gap-6">
            <div class="relative">
                {{-- id agar bisa diprefill setelah reload --}}
                <x-text-input id="q-input" type="text" wire:model.live.debounce.500ms="q" placeholder=" "
                    class="floating-input peer block w-full p-3 border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-lg shadow-sm transition duration-150" />
                <x-input-label for="q"
                    value='Cari NIK / WC. Untuk nama tepat gunakan tanda kutip, contoh nama: "***** ****".'
                    class="floating-label absolute text-gray-500 duration-300 transform -translate-y-4 scale-75 top-4 left-4 z-10 origin-[0]
                           peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0
                           peer-focus:scale-75 peer-focus:-translate-y-4" />
                @error('q')
                    <span class="text-xs text-red-500">{{ $message }}</span>
                @enderror
            </div>
        </div>
        <div wire:loading class="mt-4 text-center text-emerald-700 font-semibold transition duration-150">
            {{ __('Memuat data...') }}
        </div>
    </div>

    {{-- ===== Ringkasan ===== --}}
    <div wire:key="summary-{{ md5(($werks ?? request()->route('werks')) . '|' . $q) }}">
        <div class="overflow-x-auto overflow-y-auto max-h-[75vh] shadow-xl sm:rounded-lg border border-gray-200/75">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="sticky top-0 z-10 bg-gradient-to-r from-emerald-700 to-green-800">
                    <tr>
                        @foreach ($headersSummary as $header)
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">
                                {{ __($header) }}
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($reportData as $data)
                        <tr wire:key="report-row-{{ $data->pernr }}"
                            wire:click="showPernrDetail({{ \Illuminate\Support\Js::from((string) $data->pernr) }})"
                            class="odd:bg-white even:bg-emerald-50/60 hover:bg-emerald-100 transition-colors duration-200 ease-in-out cursor-pointer">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-extrabold text-emerald-800">
                                {{ $loop->iteration }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $data->pernr }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ Carbon::createFromFormat('Ymd', $data->min_begda)->isoFormat('YY-MM-DD') }} -
                                {{ Carbon::createFromFormat('Ymd', $data->max_begda)->isoFormat('YY-MM-DD') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-mono">
                                {{ number_format($data->total_jam, 1) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-mono">
                                {{ (int) $data->mint2 }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-mono">
                                {{ (int) $data->mintu }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-mono">
                                {{ (int) $data->mintu2 }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-mono">
                                {{ (int) $data->mintu3 }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $data->cname }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-mono">
                                {{ number_format($data->gji, 2) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-mono">
                                {{ number_format($data->gji2, 2) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-mono">
                                {{ number_format($data->varnt, 2) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-mono">
                                {{ number_format($data->varnt1, 2) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $data->arbpl }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $data->arbpl2 }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $data->werks }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-center font-mono">
                                {{ is_null($data->shift) ? '-' : (int) $data->shift }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($headersSummary) }}"
                                class="px-6 py-10 text-center text-lg text-gray-500 bg-gray-50">
                                {{ __('Tidak ada data untuk filter saat ini.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ===== Modal Detail ===== --}}
    @if ($showDetailModal)
        <div id="yppr058-modal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog"
            aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" aria-hidden="true"
                    wire:click="closeDetailModal"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div
                    class="inline-block align-bottom bg-gray-50 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-7xl sm:w-full">
                    <div
                        class="bg-gradient-to-r from-emerald-600 to-green-700 px-6 py-5 sm:px-8 flex justify-between items-center">
                        <h3 class="text-xl leading-6 font-extrabold text-white" id="modal-title">
                            Detail Tanggal (Personal No.: {{ $selectedPernr }})
                        </h3>
                        <button wire:click="closeDetailModal" type="button" class="text-white hover:text-gray-200">
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="px-6 py-6 sm:px-8 sm:py-8">
                        <div
                            class="overflow-x-auto overflow-y-auto max-h-[60vh] shadow-lg sm:rounded-lg bg-white border border-gray-200/75">
                            <table class="min-w-full divide-y divide-gray-100">
                                {{-- THEAD: checkbox pilih semua di kolom pertama --}}
                                <thead class="sticky top-0 z-10 bg-white">
                                    <tr>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-emerald-700 uppercase tracking-wider border-b-2 border-gray-200">
                                            <label class="inline-flex items-center gap-2 select-none">
                                                <input id="check-all-detail" type="checkbox"
                                                    class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                                Pilih
                                            </label>
                                        </th>
                                        @foreach ($headersNoShift as $header)
                                            <th scope="col"
                                                class="px-6 py-4 text-left text-xs font-semibold text-emerald-700 uppercase tracking-wider border-b-2 border-gray-200">
                                                {{ __($header) }}
                                            </th>
                                        @endforeach
                                        <th scope="col"
                                            class="px-6 py-4 text-left text-xs font-semibold text-emerald-700 uppercase tracking-wider border-b-2 border-gray-200">
                                            Shift
                                        </th>
                                    </tr>
                                </thead>

                                <tbody class="bg-white divide-y divide-gray-100">
                                    @foreach ($detailData as $data)
                                        <tr wire:key="detail-row-{{ $selectedPernr }}-{{ $data['begda'] ?? $loop->iteration }}"
                                            class="hover:bg-emerald-50/70 transition-colors duration-150 ease-in-out">
                                            {{-- PILIH --}}
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <input type="checkbox"
                                                    class="refresh-check rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                                    title="{{ empty($data['arbpl']) || empty($data['werks']) ? 'WC/Plant kosong: server akan mencari otomatis' : 'Siap kirim' }}"
                                                    data-pernr="{{ $data['pernr'] ?? '' }}"
                                                    data-werks="{{ $data['werks'] ?? '' }}"
                                                    data-arbpl="{{ $data['arbpl'] ?? '' }}"
                                                    data-date="{{ $data['begda'] ?? '' }}">
                                            </td>

                                            {{-- NO --}}
                                            <td
                                                class="px-6 py-4 whitespace-nowrap text-sm font-extrabold text-emerald-800">
                                                {{ $loop->iteration }}</td>

                                            @php
                                                $detailColumns = [
                                                    'pernr',
                                                    'begda',
                                                    'total_jam',
                                                    'mint2',
                                                    'mintu',
                                                    'mintu2',
                                                    'mintu3',
                                                    'cname',
                                                    'gji',
                                                    'gji2',
                                                    'varnt',
                                                    'varnt1',
                                                    'arbpl',
                                                    'arbpl2',
                                                    'werks',
                                                    'shift',
                                                ];
                                            @endphp

                                            @foreach ($detailColumns as $column)
                                                @php
                                                    $value = $data[$column] ?? null;
                                                    $isCurrency = in_array(
                                                        $column,
                                                        ['gji', 'gji2', 'varnt', 'varnt1'],
                                                        true,
                                                    );
                                                    $isDecimal = $column === 'total_jam';
                                                    $isInteger = in_array(
                                                        $column,
                                                        ['mint2', 'mintu', 'mintu2', 'mintu3', 'shift'],
                                                        true,
                                                    );
                                                    $isDate = $column === 'begda';
                                                    $isPernr = $column === 'pernr';
                                                    $isName = $column === 'cname';
                                                    $isCode = in_array($column, ['arbpl', 'arbpl2', 'werks'], true);
                                                @endphp
                                                <td @class([
                                                    'px-6 py-4 whitespace-nowrap text-sm',
                                                    'font-mono text-right text-gray-800' =>
                                                        $isCurrency || $isDecimal || $isInteger,
                                                    'font-mono text-gray-700' => $isPernr,
                                                    'font-mono text-gray-600' => $isDate,
                                                    'font-medium text-gray-900' => $isName,
                                                    'text-gray-600' => $isCode,
                                                    'text-gray-800' => !(
                                                        $isCurrency ||
                                                        $isDecimal ||
                                                        $isInteger ||
                                                        $isPernr ||
                                                        $isDate ||
                                                        $isName ||
                                                        $isCode
                                                    ),
                                                ])>
                                                    @if (is_null($value) || $value === '')
                                                        -
                                                    @elseif ($isCurrency)
                                                        {{ number_format($value, 2) }}
                                                    @elseif ($isDecimal)
                                                        {{ number_format($value, 1) }}
                                                    @elseif ($isInteger)
                                                        {{ (int) $value }}
                                                    @elseif ($isDate)
                                                        {{ Carbon::createFromFormat('Ymd', $value)->isoFormat('YY-MM-DD') }}
                                                    @else
                                                        {{ $value }}
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div
                        class="bg-gray-50 px-6 py-4 sm:px-8 sm:flex sm:flex-row-reverse items-center gap-3 border-t border-gray-200">
                        <button id="btn-refresh-sap" type="button"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-emerald-600 text-base font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-all">
                            Refresh dari SAP (terpilih)
                        </button>

                        <button wire:click="closeDetailModal" type="button"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-all">
                            Tutup
                        </button>

                        <div class="mt-3 sm:mt-0 sm:mr-auto text-sm text-gray-600">
                            <span id="refresh-progress">Ready.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

{{-- ======= STYLE kecil utk cursor not-allowed saat busy ======= --}}
@push('styles')
    <style>
        /* Tombol yang sedang diproses */
        #btn-refresh-sap.is-busy,
        #btn-refresh-sap.is-busy:hover,
        #btn-refresh-sap.is-busy:focus {
            cursor: not-allowed !important;
        }

        #btn-refresh-sap.is-busy * {
            pointer-events: none !important;
        }

        /* GLOBAL: kalau ada proses refresh, SEMUA tombol refresh di mana pun
                                           (modal baru sekalipun) kelihatan "dilarang" */
        body.yppr058-refresh-busy #btn-refresh-sap,
        body.yppr058-refresh-busy #btn-refresh-sap:hover,
        body.yppr058-refresh-busy #btn-refresh-sap:focus {
            cursor: not-allowed !important;
            opacity: 0.6;
        }

        /* Anak-anak tombol juga tidak bisa diklik saat global busy */
        body.yppr058-refresh-busy #btn-refresh-sap * {
            pointer-events: none !important;
        }
    </style>
@endpush


{{-- ======= SCRIPTS (dibind sekali) ======= --}}
@once
    @push('scripts')
        <script>
            (function() {
                // cegah double binding setelah re-render livewire
                if (window.__yppr058Bound) return;
                window.__yppr058Bound = true;

                const API_BASE = 'http://127.0.0.1:5010';
                const LS_PREFILL = 'yppr058_prefill_q';
                const LS_SUMMARY = 'yppr058_refresh_summary';

                const $ = (sel, root = document) => root.querySelector(sel);
                const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

                // ===== Toast helpers (progress & summary) =====
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
                    card.className = 'pointer-events-auto w-[360px] rounded-xl border border-gray-200 bg-white shadow-2xl';
                    card.innerHTML = html;
                    toastStack.appendChild(card);
                    return card;
                }

                function progressCard(statusText) {
                    if (!window.__yppr058ProgressCard) {
                        window.__yppr058ProgressCard = makeCard(`
        <div class="p-4">
          <div class="flex items-start gap-3">
            <div class="h-10 w-10 rounded-full bg-emerald-100 flex items-center justify-center">
              <svg class="animate-spin h-5 w-5 text-emerald-700" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
              </svg>
            </div>
            <div class="flex-1">
              <div class="font-bold text-emerald-800">Refreshing dari SAP…</div>
              <div id="pc-msg" class="text-sm text-gray-700 mt-0.5"></div>
            </div>
            <button class="text-gray-400 hover:text-gray-600" onclick="this.closest('div.pointer-events-auto').remove()">✕</button>
          </div>
          <div class="mt-3 h-1.5 w-full bg-gray-100 rounded">
            <div id="pc-bar" class="h-1.5 bg-emerald-500 rounded" style="width:0%"></div>
          </div>
        </div>`);
                    }
                    const card = window.__yppr058ProgressCard;
                    const msg = $('#pc-msg', card);
                    if (msg) msg.textContent = statusText || '';
                    return card;
                }

                function updateProgress(current, total) {
                    const card = window.__yppr058ProgressCard;
                    if (!card) return;
                    const bar = $('#pc-bar', card);
                    if (bar) {
                        const pct = Math.round((current / Math.max(1, total)) * 100);
                        bar.style.width = pct + '%';
                    }
                    const msg = $('#pc-msg', card);
                    if (msg) msg.textContent = `Proses ${current}/${total}…`;
                }

                function hideProgress() {
                    if (window.__yppr058ProgressCard) {
                        window.__yppr058ProgressCard.remove();
                        window.__yppr058ProgressCard = null;
                    }
                }

                function showSummaryToast(summary) {
                    const {
                        ok = 0, fail = 0, total = 0, pernrs = []
                    } = summary || {};
                    const html = `
      <div class="p-4">
        <div class="flex items-start gap-3">
          <div class="h-10 w-10 rounded-full ${fail ? 'bg-amber-100' : 'bg-emerald-100'} flex items-center justify-center">
            <svg class="h-6 w-6 ${fail ? 'text-amber-700' : 'text-emerald-700'}" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2a10 10 0 100 20 10 10 0 000-20zM11 6h2v8h-2V6zm0 10h2v2h-2v-2z"/>
            </svg>
          </div>
          <div class="flex-1">
            <div class="font-bold ${fail ? 'text-amber-800' : 'text-emerald-800'}">Refresh selesai</div>
            <div class="text-sm text-gray-700 mt-0.5">Berhasil: <b>${ok}</b> • Gagal: <b>${fail}</b> • Total: <b>${total}</b></div>
            <div class="text-xs text-gray-500 mt-1">NIK: ${pernrs.join(', ')}</div>
          </div>
          <button class="text-gray-400 hover:text-gray-600" onclick="this.closest('div.pointer-events-auto').remove()">✕</button>
        </div>
      </div>`;
                    const card = makeCard(html);
                    setTimeout(() => card.remove(), 7000);
                }

                // ===== Checkbox select-all di modal =====
                function onChange(e) {
                    if (e.target && e.target.id === 'check-all-detail') {
                        const modal = $('#yppr058-modal') || document;
                        const checked = e.target.checked;
                        $$('.refresh-check', modal).forEach(cb => cb.checked = checked);
                    }
                }
                document.addEventListener('change', onChange);

                // ===== Single-running guard =====
                let busy = false;

                // Toggle state tombol (hanya tombol yang dapat cursor not-allowed)
                function setButtonBusy(btn, on) {
                    // tandai global di body → semua tombol refresh ikut kelihatan "mati"
                    document.body.classList.toggle('yppr058-refresh-busy', !!on);

                    if (!btn) return;

                    if (on) {
                        btn.disabled = true;
                        btn.classList.add('opacity-60', 'cursor-not-allowed', 'is-busy');
                        btn.setAttribute('aria-busy', 'true');
                        btn.title = 'Sedang memproses… tunggu selesai';
                    } else {
                        btn.disabled = false;
                        btn.classList.remove('opacity-60', 'cursor-not-allowed', 'is-busy');
                        btn.removeAttribute('aria-busy');
                        btn.title = 'Refresh dari SAP (terpilih)';
                    }
                }

                // ===== Kirim request 1 per 1 & kunci tombol selama proses =====
                async function refreshSelected() {
                    if (busy) return;
                    const modal = $('#yppr058-modal') || document;
                    const btn = $('#btn-refresh-sap');

                    const checks = $$('.refresh-check:checked', modal)
                        .filter(el => el.dataset.pernr && el.dataset.date); // WC/Plant boleh kosong (server resolve)

                    if (!checks.length) {
                        alert('Pilih minimal satu baris.');
                        return;
                    }

                    busy = true;
                    setButtonBusy(btn, true);

                    const total = checks.length;
                    let done = 0,
                        ok = 0,
                        fail = 0;
                    const nikSet = new Set();

                    progressCard(`Menjalankan ${total} proses… (0/${total})`);

                    for (const el of checks) {
                        done += 1;
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
                            const okItem = resp.ok && data.ok && Array.isArray(data.results) && data.results[0]?.ok;
                            if (okItem) {
                                ok++;
                                el.closest('tr')?.classList.add('bg-emerald-50');
                            } else {
                                fail++;
                                el.closest('tr')?.classList.add('bg-red-50');
                            }
                        } catch {
                            fail++;
                            el.closest('tr')?.classList.add('bg-red-50');
                        }
                    }

                    hideProgress();

                    // Simpan ringkasan & prefill lalu reload
                    const pernrs = Array.from(nikSet);
                    localStorage.setItem(LS_PREFILL, pernrs.join(' '));
                    localStorage.setItem(LS_SUMMARY, JSON.stringify({
                        ok,
                        fail,
                        total,
                        pernrs,
                        ts: Date.now()
                    }));

                    // lepas kunci sebelum reload (kalau reload gagal, tombol kembali aktif)
                    setButtonBusy(btn, false);
                    busy = false;

                    window.location.reload();
                }

                // tombol refresh (delegation yang tahan klik di child elemen)
                function onClick(e) {
                    const targetBtn = e.target && e.target.closest && e.target.closest('#btn-refresh-sap');
                    if (!targetBtn) return;

                    if (busy || targetBtn.disabled || targetBtn.getAttribute('aria-busy') === 'true') {
                        e.preventDefault();
                        e.stopPropagation();
                        if (typeof e.stopImmediatePropagation === 'function') {
                            e.stopImmediatePropagation();
                        }
                        return;
                    }

                    refreshSelected();
                }

                document.addEventListener('click', onClick, true); // pakai capture supaya intercept duluan


                // ===== Setelah halaman reload: prefill search + tampilkan summary toast =====
                function afterReloadTasks() {
                    const qVal = localStorage.getItem(LS_PREFILL);
                    if (qVal) {
                        const inp = document.getElementById('q-input');
                        if (inp) {
                            inp.value = qVal;
                            inp.dispatchEvent(new Event('input', {
                                bubbles: true
                            }));
                            try {
                                inp.focus();
                                inp.select();
                            } catch {}
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

                // jalankan saat DOM siap (termasuk Livewire)
                window.addEventListener('DOMContentLoaded', afterReloadTasks);
                document.addEventListener('livewire:load', afterReloadTasks);
            })
            ();
        </script>
    @endpush
@endonce
