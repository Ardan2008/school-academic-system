@extends('layouts.dashboard')

@section('title', 'Data Siswa')
@section('subtitle', 'Dashboard Guru')

@section('content')
    <x-dashboard.animations />

    <div id="siswa-page" data-dashboard>

        {{-- Statistik --}}
        @php
            $statTone = [
                'total' => ['bg-blue-100 text-primary-60', '#1E86FF'],
                'aktif' => ['bg-emerald-100 text-emerald-600', '#059669'],
                'nonaktif' => ['bg-red-100 text-red-500', '#EF4444'],
                'lulus' => ['bg-amber-100 text-amber-600', '#D97706'],
            ];
        @endphp
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-4">
            @foreach ($stats as $stat)
                @php
                    $statPill = $statTone[$stat['key']][0] ?? 'bg-blue-100 text-primary-60';
                    $statAccent = $statTone[$stat['key']][1] ?? '#1E86FF';
                @endphp
                <div data-reveal style="--d: {{ $loop->index * .08 }}s">
                    <x-ui.stat-card
                        :label="$stat['label']"
                        :value="$stat['value']"
                        :icon="$stat['icon']"
                        :tone="$statPill"
                        :accent="$statAccent"
                        :stat-key="$stat['key']"
                        value-class="siswa-stat"
                        :count="true"
                    />
                </div>
            @endforeach
        </div>

        {{-- Tabel --}}
        <div data-reveal style="--d: .28s" class="mt-5 rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60 sm:mt-6">
            <div class="rounded-t-2xl bg-slate-50 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <button type="button" id="siswa-export" data-state="idle" aria-live="polite" title="Unduh data siswa yang sedang difilter"
                            class="ekspor relative inline-flex h-10 flex-1 items-center justify-center gap-2 overflow-hidden whitespace-nowrap rounded-lg border border-blue-300 bg-white px-4 text-xs font-medium text-primary-60 transition hover:bg-blue-50 sm:h-9 sm:flex-none sm:text-sm">
                            <span data-ekspor-kilau class="ekspor-kilau" aria-hidden="true"></span>
                            <x-lucide-download data-ekspor-ikon="idle" class="ekspor-ikon h-4 w-4" />
                            <svg data-ekspor-ikon="muat" class="ekspor-ikon h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                                <path d="M21 12a9 9 0 1 1-6.219-8.56" />
                            </svg>
                            <x-lucide-check data-ekspor-ikon="sukses" class="ekspor-ikon h-4 w-4" />
                            <span data-ekspor-label>Export Excel</span>
                        </button>
                    </div>
                </div>

                <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative w-full sm:w-64">
                        <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-700" />
                        <input type="search" id="siswa-search" placeholder="Cari nama, NIS, atau email" autocomplete="off"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white pl-10 pr-10 text-base text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20 sm:h-9 sm:text-sm">
                        <button type="button" id="siswa-search-clear" aria-label="Bersihkan pencarian"
                            class="absolute right-1.5 top-1/2 hidden -translate-y-1/2 items-center justify-center rounded-lg p-1.5 text-slate-400 transition-all duration-200 ease-out hover:bg-slate-400/15 hover:text-slate-600 focus-visible:outline-2 focus-visible:outline-blue-500 active:scale-90">
                            <x-lucide-x class="h-4 w-4" />
                        </button>
                    </div>

                    {{-- Tombol filter + panel popup --}}
                    <div class="relative flex justify-end" data-filter-root>
                        <button type="button" data-filter-toggle aria-expanded="false" aria-controls="siswa-filter-panel"
                            class="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 text-xs font-medium text-slate-600 transition hover:border-primary-60 hover:bg-blue-50 hover:text-primary-70 active:scale-[.98] data-[open=true]:border-primary-60 data-[open=true]:bg-blue-50 data-[open=true]:text-primary-70 sm:h-9">
                            <x-lucide-sliders-horizontal class="h-4 w-4" />
                            <span>Filter</span>
                            <span data-filter-badge class="hidden min-w-4 items-center justify-center rounded-full bg-primary-60 px-1.5 py-px text-[10px] font-semibold leading-4 text-white">0</span>
                        </button>

                        <div id="siswa-filter-panel" data-filter-panel data-open="false" aria-hidden="true" inert
                            class="invisible absolute left-0 top-full z-30 mt-2 w-full min-w-0 origin-top scale-95 rounded-2xl border border-slate-100 bg-white p-4 opacity-0 shadow-xl shadow-slate-900/10 transition-[opacity,transform,visibility] duration-200 ease-out sm:left-auto sm:right-0 sm:w-96 data-[open=true]:visible data-[open=true]:scale-100 data-[open=true]:opacity-100">
                            <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Saring Data Siswa</p>

                            @php
                                $filterSelect = 'h-10 w-full min-w-0 appearance-none rounded-lg border border-slate-200 bg-slate-50 px-3 pr-8 text-base text-slate-600 outline-none transition
                                    focus:border-primary-60 focus:bg-white focus:ring-2 focus:ring-primary-60/20
                                    disabled:cursor-not-allowed disabled:border-slate-100 disabled:bg-slate-100/70 disabled:text-slate-400
                                    sm:h-9 sm:text-sm';
                                $filterLabel = 'mb-1 block text-xs font-medium text-slate-600';
                            @endphp

                            <div class="max-h-[60vh] space-y-3 overflow-y-auto sm:max-h-[22rem]">
                                <div>
                                    <label for="siswa-filter-jurusan" class="{{ $filterLabel }}">Jurusan</label>
                                    <div class="relative">
                                        <select id="siswa-filter-jurusan" data-filter-jurusan class="{{ $filterSelect }}">
                                            <option value="">Semua Jurusan</option>
                                        </select>
                                        <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                                    </div>
                                </div>
                                <div>
                                    <label for="siswa-filter-tingkat" class="{{ $filterLabel }}">Tingkat / Kelas</label>
                                    <div class="relative">
                                        <select id="siswa-filter-tingkat" data-filter-tingkat disabled class="{{ $filterSelect }}">
                                            <option value="">Semua Tingkat</option>
                                        </select>
                                        <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                                    </div>
                                </div>
                                <div>
                                    <label for="siswa-filter-rombel" class="{{ $filterLabel }}">Kelas / Rombel</label>
                                    <div class="relative">
                                        <select id="siswa-filter-rombel" data-filter-rombel disabled class="{{ $filterSelect }}">
                                            <option value="">Semua Rombel</option>
                                        </select>
                                        <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                                    </div>
                                </div>
                                <div>
                                    <label for="siswa-filter-status" class="{{ $filterLabel }}">Status</label>
                                    <div class="relative">
                                        <select id="siswa-filter-status" data-filter-status class="{{ $filterSelect }}">
                                            <option value="">Semua Status</option>
                                        </select>
                                        <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                                    </div>
                                </div>
                            </div>

                            <p data-filter-summary class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-[11px] leading-relaxed text-slate-500"></p>

                            <div class="mt-3 flex items-center gap-2 border-t border-slate-100 pt-3">
                                <button type="button" data-filter-reset
                                    class="inline-flex h-9 flex-1 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-600 transition hover:bg-slate-50 active:scale-[.98]">
                                    <x-lucide-rotate-ccw class="h-3.5 w-3.5" />
                                    Reset
                                </button>
                                <button type="button" data-filter-done
                                    class="inline-flex h-9 flex-1 items-center justify-center rounded-lg bg-primary-60 px-3 text-xs font-semibold text-white transition hover:bg-primary-70 active:scale-[.98]">
                                    Terapkan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hidden overflow-x-auto overflow-y-hidden border-t border-slate-100 md:block">
                <table class="w-full table-auto text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-5 py-4 text-center font-semibold w-14">No</th>
                            <th class="px-5 py-4 text-left font-semibold w-28">NIS</th>
                            <th class="px-5 py-4 text-left font-semibold min-w-[220px]">Nama Siswa</th>
                            <th class="px-5 py-4 text-left font-semibold min-w-[240px]">Email</th>
                            <th class="px-5 py-4 text-center font-semibold w-24">Kelas</th>
                            <th class="px-5 py-4 text-center font-semibold min-w-[160px]">Jurusan</th>
                            <th class="px-5 py-4 text-center font-semibold w-28">Status</th>
                            <th class="px-5 py-4 text-center font-semibold w-40">Password Awal</th>
                            <th class="px-5 py-4 text-center font-semibold min-w-[200px]">Status Password</th>
                        </tr>
                    </thead>
                    <tbody id="siswa-tbody" class="divide-y divide-slate-100 bg-white text-slate-800"></tbody>
                </table>
            </div>

            <div id="siswa-card-body" class="divide-y divide-slate-100 overflow-hidden border-t border-slate-100 px-4 md:hidden"></div>
            <div id="siswa-empty" class="hidden px-6 py-16 text-center text-sm text-slate-500">Tidak ada data siswa yang sesuai filter.</div>

            {{-- Footer + pagination --}}
            <x-ui.table-footer mini label="siswa" />
        </div>
    </div>

    @push('styles')
        <style>
            #siswa-export svg {
                transition: transform .3s cubic-bezier(.22, 1, .36, 1);
            }
            #siswa-export:hover svg {
                transform: translateY(2px);
            }
            .ekspor-ikon {
                transition: opacity .18s ease, transform .18s ease;
            }
            .ekspor[data-state="idle"] [data-ekspor-ikon]:not([data-ekspor-ikon="idle"]),
            .ekspor[data-state="muat"] [data-ekspor-ikon]:not([data-ekspor-ikon="muat"]),
            .ekspor[data-state="sukses"] [data-ekspor-ikon]:not([data-ekspor-ikon="sukses"]) {
                display: none;
            }
            .ekspor[data-state="muat"] {
                cursor: not-allowed;
                opacity: .92;
            }
            .ekspor[data-state="muat"]::before {
                content: "";
                position: absolute;
                inset: 0;
                background-image: radial-gradient(120% 120% at 0% 0%, rgba(30, 134, 255, .14) 0%, rgba(255, 255, 255, 0) 70%);
                animation: eksporKilau 1.4s ease-in-out infinite alternate;
                pointer-events: none;
            }
            @keyframes eksporKilau {
                0% { opacity: .25; transform: translateX(-10%); }
                100% { opacity: .6; transform: translateX(10%); }
            }
            .ekspor-kilau {
                position: absolute;
                inset-y: 0;
                left: -40%;
                width: 40%;
                background-image: linear-gradient(120deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, .55) 50%, rgba(255, 255, 255, 0) 100%);
                transform: skewX(-18deg);
                pointer-events: none;
                opacity: 0;
                transition: opacity .25s ease;
            }
            .ekspor[data-state="muat"] .ekspor-kilau {
                animation: eksporKilauMeluncur 1.6s ease-in-out infinite;
                opacity: 1;
            }
            @keyframes eksporKilauMeluncur {
                0% { left: -40%; }
                100% { left: 140%; }
            }
            .ekspor[data-state="sukses"] {
                border-color: rgb(16 185 129 / .55);
                color: #047857;
                background-color: rgb(236 253 245 / .9);
            }
            .ekspor[data-state="sukses"] svg {
                transform: scale(1.05);
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            (() => {
                const siswaData = @json($siswaData);
                const struktur = @json($struktur_kelas);
                const rows = (siswaData.rows || []).map(r => ({ ...r }));
                const perPage = 5;

                const root = document.getElementById('siswa-page');
                const $ = (sel) => root.querySelector(sel);

                const tbody = $('#siswa-tbody');
                const cardBody = $('#siswa-card-body');
                const emptyEl = $('#siswa-empty');
                const searchInput = $('#siswa-search');
                const searchClear = $('#siswa-search-clear');

                const pag = $('[data-pagination]');
                const info = pag.querySelector('[data-info]');
                const pagesEl = pag.querySelector('[data-pages]');
                const prevBtn = pag.querySelector('[data-prev]');
                const nextBtn = pag.querySelector('[data-next]');
                const label = pag.dataset.label || 'data';

                const jurusanEl = $('[data-filter-jurusan]');
                const tingkatEl = $('[data-filter-tingkat]');
                const rombelEl = $('[data-filter-rombel]');
                const statusEl = $('[data-filter-status]');
                const summaryEl = $('[data-filter-summary]');

                const state = { q: '', status: '', jurusan: '', tingkat: '', rombel: '', rombel_match: '', page: 1 };

                const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
                }[c]));

                // ===== Struktur Jurusan -> Tingkat -> Rombel (dari server) =====
                // Rombel dibangun dari kelas yang benar-benar dipakai siswa, bukan daftar tetap.
                const daftarStruktur = Array.isArray(struktur) ? struktur : [];

                const tingkatJurusan = (jurusan) => {
                    const found = daftarStruktur.find((j) => j.nama === jurusan);
                    return found && Array.isArray(found.tingkat) ? found.tingkat : [];
                };

                const tingkatDipilih = (jurusan, tingkat) =>
                    tingkatJurusan(jurusan).find((t) => t.nilai === tingkat) || null;

                const rombelDipilih = (jurusan, tingkat, rombel) => {
                    const t = tingkatDipilih(jurusan, tingkat);
                    return t && Array.isArray(t.rombel) ? t.rombel.find((r) => r.nilai === rombel) || null : null;
                };

                const jumlahSiswa = (n) => `${n} siswa`;

                const normClass = (v) => String(v || '').replace(/\s+/g, '').replace(/\./g, '-').toLowerCase();

                // ===== Saring data =====
                const filtered = () => {
                    const q = state.q.trim().toLowerCase();

                    return rows.filter((r) => {
                        if (state.status && r.status !== state.status) return false;
                        if (state.jurusan && r.jurusan !== state.jurusan) return false;
                        if (state.tingkat && r.tingkat !== state.tingkat) return false;

                        if (state.rombel) {
                            const rombel = rombelDipilih(state.jurusan, state.tingkat, state.rombel);
                            if (rombel) {
                                if (rombel.match === 'kelas') {
                                    if (normClass(r.kelas) !== normClass(rombel.nilai)) return false;
                                } else {
                                    if (normClass(r.kelas) !== normClass(rombel.nilai)) {
                                        if (String(r.tingkat || '') !== String(rombel.nilai)) return false;
                                    }
                                }
                            } else {
                                if (String(r.tingkat || '') !== String(state.tingkat)) return false;
                            }
                        }

                        if (q && !(
                            (r.nama || '').toLowerCase().includes(q)
                            || String(r.nis ?? '').includes(q)
                            || (r.email || '').toLowerCase().includes(q)
                        )) return false;

                        return true;
                    });
                };

                // Isi ulang <select>; nilai lama dipertahankan selama masih ada di daftar baru.
                const fillSelect = (sel, items, placeholder) => {
                    const current = sel.value;
                    [...sel.options].slice(1).forEach((o) => o.remove());

                    const list = items.map((i) => (typeof i === 'string' ? { nilai: i, teks: i } : i));
                    list.forEach((i) => sel.add(new Option(i.teks, i.nilai)));

                    if (sel.options[0]) sel.options[0].textContent = placeholder;
                    sel.value = list.some((i) => i.nilai === current) ? current : '';
                };

                const setDisabled = (sel, disabled) => {
                    sel.disabled = disabled;
                    sel.setAttribute('aria-disabled', disabled ? 'true' : 'false');
                };

                function refreshTingkat() {
                    fillSelect(
                        tingkatEl,
                        tingkatJurusan(jurusanEl.value).map((t) => ({
                            nilai: t.nilai,
                            teks: `${t.nilai} (${jumlahSiswa(t.jumlah)})`,
                        })),
                        'Semua Tingkat'
                    );

                    setDisabled(tingkatEl, jurusanEl.value === '');

                    refreshRombel();
                }

                function refreshRombel() {
                    const tingkat = tingkatDipilih(jurusanEl.value, tingkatEl.value);
                    const rombel = Array.isArray(tingkat && tingkat.rombel) ? tingkat.rombel : [];

                    fillSelect(
                        rombelEl,
                        rombel.map((r) => ({ nilai: r.nilai, teks: `${r.nilai} (${jumlahSiswa(r.jumlah)})` })),
                        tingkat && tingkat.berrombel ? 'Semua Rombel' : 'Semua Kelas'
                    );

                    setDisabled(rombelEl, !tingkat);
                }

                function syncSummary() {
                    const jurusan = jurusanEl.value;
                    const tingkat = tingkatDipilih(jurusan, tingkatEl.value);

                    let teks;
                    if (!jurusan) {
                        teks = 'Pilih jurusan dulu, lalu pilih tingkat dan rombelnya.';
                    } else if (!tingkat) {
                        teks = `${jurusan}: pilih tingkat 10, 11, atau 12.`;
                    } else if (tingkat.berrombel) {
                        teks = `${jurusan} · kelas ${tingkat.nilai} punya ${tingkat.rombel.length} rombel.`;
                    } else {
                        teks = `${jurusan} · kelas ${tingkat.nilai} hanya punya satu kelas.`;
                    }

                    summaryEl.textContent = teks;
                }

                // Kembalikan kontrol filter ke nilai yang sudah diterapkan (panel ditutup tanpa "Terapkan").
                function restoreControls() {
                    statusEl.value = state.status;
                    jurusanEl.value = state.jurusan;
                    refreshTingkat();
                    tingkatEl.value = state.tingkat;
                    refreshRombel();
                    rombelEl.value = state.rombel;
                    syncSummary();
                }

                // ===== Halaman & pagination =====
                const pageList = (cur, last) => {
                    if (last <= 5) return Array.from({ length: last }, (_, i) => i + 1);
                    const set = [1, last, cur - 1, cur, cur + 1].filter((n) => n >= 1 && n <= last);
                    const sorted = [...set].sort((a, b) => a - b);
                    const out = [];
                    sorted.forEach((n, i) => {
                        if (i && n - sorted[i - 1] > 1) out.push('...');
                        out.push(n);
                    });
                    return out;
                };

                const ACTIVE = 'bg-primary-60 font-semibold text-white';
                const IDLE = 'hover:bg-slate-100 text-slate-600';

                const statusPill = (s) => {
                    const map = {
                        'Aktif': 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/10',
                        'Nonaktif': 'bg-red-50 text-red-700 ring-1 ring-red-600/10',
                        'Lulus': 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/10',
                    };
                    return map[s] || 'bg-blue-50 text-blue-700 ring-1 ring-blue-600/10';
                };

                const rowHtml = (r, no) => `
                    <tr class="transition hover:bg-slate-50/70">
                        <td class="px-5 py-4 text-sm text-slate-600 text-center align-middle">${no}</td>
                        <td class="px-5 py-4 text-sm font-medium text-slate-900 whitespace-nowrap align-middle">${esc(r.nis)}</td>
                        <td class="px-5 py-4 text-sm text-slate-900 whitespace-normal break-words align-middle">${esc(r.nama)}</td>
                        <td class="px-5 py-4 text-sm text-slate-600 whitespace-normal break-words align-middle">${esc(r.email)}</td>
                        <td class="px-5 py-4 text-sm text-slate-600 whitespace-nowrap text-center align-middle">${esc(r.kelas)}</td>
                        <td class="px-5 py-4 text-sm text-slate-600 whitespace-normal text-center align-middle">${esc(r.jurusan)}</td>
                        <td class="px-5 py-4 text-center align-middle"><span class="inline-flex items-center justify-center rounded-full px-2.5 py-0.5 text-xs ${statusPill(r.status)}">${esc(r.status)}</span></td>
                        <td class="px-5 py-4 font-mono text-xs text-slate-600 whitespace-nowrap text-center align-middle">${esc(r.password_awal) || '-'}</td>
                        <td class="px-5 py-4 text-xs text-slate-600 whitespace-normal text-center align-middle leading-relaxed">${esc(r.status_password)}</td>
                    </tr>`;

                const cardHtml = (r, no) => `
                    <div class="flex flex-col gap-3 py-4">
                        <div class="flex items-center justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">#${no}</p>
                                <p class="mt-1 truncate text-base font-semibold text-slate-900" title="${esc(r.nama)}">${esc(r.nama)}</p>
                                <p class="truncate text-sm text-slate-500" title="${esc(r.nis)} • ${esc(r.email)}">${esc(r.nis)} • ${esc(r.email)}</p>
                            </div>
                            <span class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-xs ${statusPill(r.status)}">${esc(r.status)}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-xs text-slate-400">Kelas</p>
                                <p class="font-medium text-slate-800">${esc(r.kelas)}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400">Jurusan</p>
                                <p class="font-medium text-slate-800">${esc(r.jurusan)}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-xs text-slate-400">Password Awal</p>
                                <p class="font-mono text-xs text-slate-700">${esc(r.password_awal) || '-'}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-xs text-slate-400">Status Password</p>
                                <p class="text-sm text-slate-700">${esc(r.status_password)}</p>
                            </div>
                        </div>
                    </div>`;

                function render() {
                    const list = filtered();
                    const total = list.length;
                    const last = Math.max(1, Math.ceil(total / perPage));
                    state.page = Math.min(state.page, last);

                    const start = (state.page - 1) * perPage;
                    const slice = list.slice(start, start + perPage);

                    tbody.innerHTML = slice.map((r, i) => rowHtml(r, start + i + 1)).join('');
                    if (cardBody) {
                        cardBody.innerHTML = slice.map((r, i) => cardHtml(r, start + i + 1)).join('');
                    }
                    emptyEl.classList.toggle('hidden', total > 0);

                    const from = total === 0 ? 0 : start + 1;
                    const to = Math.min(state.page * perPage, total);
                    info.textContent = `Menampilkan ${from}–${to} dari ${total} ${label}`;

                    pagesEl.innerHTML = pageList(state.page, last).map((n) => n === '...'
                        ? '<span class="px-1 text-slate-400">...</span>'
                        : `<button type="button" data-page="${n}" class="min-w-8 rounded-lg px-2.5 py-1.5 text-sm transition-colors duration-200 sm:min-w-0 sm:py-1 ${n === state.page ? ACTIVE : IDLE}">${n}</button>`
                    ).join('');

                    prevBtn.disabled = state.page === 1;
                    nextBtn.disabled = state.page === last;
                }

                function refreshFilters() {
                    fillSelect(statusEl, siswaData.statuses || [], 'Semua Status');
                    fillSelect(jurusanEl, daftarStruktur.map((j) => ({
                        nilai: j.nama,
                        teks: `${j.nama} (${jumlahSiswa(j.jumlah)})`,
                    })), 'Semua Jurusan');

                    state.status = statusEl.value;
                    state.jurusan = jurusanEl.value;

                    refreshTingkat();
                    state.tingkat = tingkatEl.value;
                    state.rombel = rombelEl.value;
                    state.rombel_match = state.rombel
                        ? (rombelDipilih(state.jurusan, state.tingkat, state.rombel)?.match || '')
                        : '';
                    syncSummary();
                }

                // ===== Panel filter =====
                const filterRoot = $('[data-filter-root]');
                const filterBtn = $('[data-filter-toggle]');
                const filterPanel = $('[data-filter-panel]');
                const filterBadge = $('[data-filter-badge]');

                const setPanel = (open) => {
                    filterPanel.dataset.open = open ? 'true' : 'false';
                    filterPanel.setAttribute('aria-hidden', open ? 'false' : 'true');
                    filterPanel.toggleAttribute('inert', !open);
                    filterBtn.dataset.open = open ? 'true' : 'false';
                    filterBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                    if (open) setTimeout(() => jurusanEl?.focus(), 30);
                    // Buang perubahan yang belum diterapkan saat panel ditutup
                    else restoreControls();
                };

                // Tingkat & rombel dihitung sebagai satu filter kelas agar badge tidak bertambah dua kali.
                const filterCount = () => [
                    state.status,
                    state.jurusan,
                    state.tingkat || state.rombel,
                ].filter(Boolean).length;

                const syncBadge = () => {
                    const n = filterCount() + (state.q.trim() ? 1 : 0);
                    filterBadge.classList.toggle('hidden', n === 0);
                    filterBadge.textContent = n;
                };

                filterBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    setPanel(filterPanel.dataset.open !== 'true');
                });

                filterPanel.querySelector('[data-filter-done]').addEventListener('click', (e) => {
                    e.stopPropagation();
                    state.status = statusEl.value;
                    state.jurusan = jurusanEl.value;
                    state.tingkat = tingkatEl.value;
                    state.rombel = rombelEl.value;
                    const dipilih = state.rombel
                        ? rombelDipilih(state.jurusan, state.tingkat, state.rombel)
                        : null;
                    state.rombel_match = dipilih ? dipilih.match : '';
                    state.page = 1;
                    syncBadge();
                    render();
                    setPanel(false);
                });

                filterPanel.querySelector('[data-filter-reset]').addEventListener('click', (e) => {
                    e.stopPropagation();
                    // Reset langsung: semua filter & pencarian dikosongkan tanpa perlu "Terapkan".
                    searchInput.value = '';
                    statusEl.value = jurusanEl.value = tingkatEl.value = rombelEl.value = '';
                    Object.assign(state, {
                        q: '', status: '', jurusan: '', tingkat: '', rombel: '', rombel_match: '', page: 1,
                    });
                    refreshFilters();
                    syncSearchClear();
                    syncBadge();
                    render();
                });

                document.addEventListener('click', (e) => {
                    if (filterPanel.dataset.open !== 'true') return;
                    if (filterPanel.contains(e.target) || filterBtn.contains(e.target)) return;
                    setPanel(false);
                });

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && filterPanel.dataset.open === 'true') {
                        setPanel(false);
                    }
                });

                // ===== Pencarian =====
                const syncSearchClear = () => searchClear.classList.toggle('hidden', searchInput.value.length === 0);

                searchInput.addEventListener('input', () => {
                    state.q = searchInput.value;
                    state.page = 1;
                    syncSearchClear();
                    syncBadge();
                    render();
                });

                searchInput.addEventListener('keydown', (e) => {
                    if (e.key !== 'Enter') return;
                    e.preventDefault();
                    render();
                });

                searchClear.addEventListener('click', (e) => {
                    e.stopPropagation();
                    searchInput.value = '';
                    state.q = '';
                    state.page = 1;
                    syncSearchClear();
                    syncBadge();
                    render();
                    searchInput.focus();
                });

                // ===== Jurusan -> Tingkat -> Rombel =====
                // Hanya memperbarui draf; data baru berubah saat "Terapkan".
                jurusanEl.addEventListener('change', () => {
                    rombelEl.value = '';
                    refreshTingkat();
                    syncSummary();
                });

                tingkatEl.addEventListener('change', () => {
                    rombelEl.value = '';
                    refreshRombel();
                    syncSummary();
                });

                prevBtn.addEventListener('click', () => { state.page--; render(); });
                nextBtn.addEventListener('click', () => { state.page++; render(); });
                pagesEl.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-page]');
                    if (btn) { state.page = Number(btn.dataset.page); render(); }
                });

                // ===== Export Excel =====
                const exportUrl = {!! json_encode(route('dashboard.guru.data-siswa.export')) !!};
                const tombolExport = $('#siswa-export');
                const labelExport = tombolExport.querySelector('[data-ekspor-label]');
                const LABEL_AWAL = labelExport ? labelExport.textContent.trim() : 'Export Excel';
                let exportState='idle', exportTimer=null, exportGuard=null;
                const setExportState=(s,t)=>{
                    clearTimeout(exportTimer);
                    exportState=s;
                    if(tombolExport){
                        tombolExport.dataset.state=s;
                        tombolExport.disabled = (s==='muat');
                    }
                    if(s!=='muat'){
                        clearTimeout(exportGuard);
                        exportGuard=null;
                    }
                    if(t && labelExport) labelExport.textContent=t;
                };
                const namaBerkas=(d)=>{const c=/filename="?([^";]+)"?/i.exec(d||'');return c?c[1]:'data-siswa-guru.xlsx';};
                const unduhBlob=(b,n)=>{const u=URL.createObjectURL(b);const a=document.createElement('a');a.href=u;a.download=n;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(u),1000);};
                tombolExport?.addEventListener('click', async ()=>{
                    if(exportState==='muat') return;
                    const p=new URLSearchParams();
                    ['q','status','jurusan','tingkat','rombel','rombel_match'].forEach(k=>{if(state[k]) p.set(k,state[k]);});
                    const q=p.toString();
                    setExportState('muat','Menyiapkan...');
                    exportGuard=setTimeout(()=>{
                        if(exportState==='muat'){
                            setExportState('idle',LABEL_AWAL);
                            alert('Gagal mengunduh: server terlalu lama merespons.');
                        }
                    },45000);
                    try{
                        const opsi={credentials:'same-origin',cache:'no-store',headers:{Accept:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'}};
                        if(typeof AbortSignal!=='undefined'&&AbortSignal.timeout) opsi.signal=AbortSignal.timeout(45000);
                        const r=await fetch(q?`${exportUrl}?${q}`:exportUrl,opsi);
                        const tipe=r.headers.get('Content-Type')||'';
                        if(!r.ok) throw new Error('Gagal mengunduh (HTTP '+r.status+')');
                        if(!tipe.includes('spreadsheet')) throw new Error('Gagal mengunduh: server tidak mengembalikan file Excel.');
                        const total=Number(r.headers.get('X-Total-Siswa')||0);
                        unduhBlob(await r.blob(), namaBerkas(r.headers.get('Content-Disposition')));
                        setExportState('sukses', total>0?`${total} siswa diunduh`:'Tidak ada siswa');
                        exportTimer=setTimeout(()=>setExportState('idle',LABEL_AWAL),2400);
                    }catch(e){
                        if(exportState!=='idle'){
                            setExportState('idle',LABEL_AWAL);
                        }
                        alert(e.message||'Gagal mengunduh');
                    }
                });

                const urlQ = new URLSearchParams(window.location.search).get('q') || new URLSearchParams(window.location.search).get('search');
                if (urlQ) {
                    state.q = urlQ;
                    searchInput.value = urlQ;
                }
                refreshFilters();
                syncSearchClear();
                syncBadge();
                render();
            })();
        </script>
    @endpush
@endsection