@extends('layouts.dashboard')

@section('title', 'Mata Pelajaran')
@section('subtitle', 'Dashboard Guru')

@section('content')
    <div id="mapel-page">

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
                        value-class="mapel-stat"
                        :catatan="$stat['subtitle'] ?? null"
                        :count="true"
                    />
                </div>
            @endforeach
        </div>

        @if ($mapelSaya)
            <div data-reveal style="--d: .25s" class="mt-5 overflow-hidden rounded-2xl border border-blue-100 bg-gradient-to-r from-blue-50/80 via-white to-indigo-50/50 p-4 shadow-sm sm:mt-6 sm:p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-1 items-start gap-3.5 min-w-0">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-60 text-white shadow-md shadow-blue-500/20">
                            <x-lucide-book-open class="h-5.5 w-5.5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-bold text-slate-900 sm:text-lg">
                                    {{ $mapelSaya['nama'] }}
                                </h3>
                                @if (!empty($mapelSaya['kode']))
                                    <span class="inline-flex items-center rounded-md bg-blue-100 px-2 py-0.5 font-mono text-xs font-semibold text-primary-70">
                                        {{ $mapelSaya['kode'] }}
                                    </span>
                                @endif
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-medium text-emerald-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    Mata Pelajaran Anda
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500 sm:text-sm">
                                Anda tercatat sebagai guru pengampu mata pelajaran ini.
                                @if ($mapelSaya['total_nilai'] > 0)
                                    Terdapat <strong class="text-slate-700">{{ $mapelSaya['total_nilai'] }} catatan nilai</strong> dari <strong class="text-slate-700">{{ $mapelSaya['total_siswa'] }} siswa</strong>.
                                @else
                                    Belum ada catatan nilai yang tersimpan untuk periode aktif.
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2.5 self-start sm:self-center sm:ml-auto">
                        <button type="button" id="btn-fokus-mapel-saya"
                            class="inline-flex h-9 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-blue-200 bg-white px-3.5 text-xs font-medium text-primary-60 transition hover:bg-blue-50 active:scale-[.98]">
                            <x-lucide-filter class="h-3.5 w-3.5" />
                            Saring Mapel Saya
                        </button>
                        <a href="{{ route('dashboard.guru.input-nilai.index') }}"
                            class="inline-flex h-9 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg bg-primary-60 px-3.5 text-xs font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-primary-70 active:scale-[.98]">
                            <x-lucide-square-pen class="h-3.5 w-3.5" />
                            Input Nilai Siswa
                        </a>
                    </div>
                </div>
            </div>
        @else
            <div data-reveal style="--d: .25s" class="mt-5 rounded-2xl border border-amber-200/70 bg-amber-50/60 p-4 shadow-sm sm:mt-6 sm:p-5">
                <div class="flex items-start gap-3">
                    <x-lucide-alert-circle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                    <div>
                        <h3 class="text-sm font-semibold text-amber-900">Mata Pelajaran Belum Ditugaskan</h3>
                        <p class="mt-0.5 text-xs text-amber-700">Akun Anda belum memiliki mata pelajaran spesifik yang ditetapkan oleh administrator sekolah. Anda tetap dapat melihat seluruh daftar mata pelajaran sekolah di bawah.</p>
                    </div>
                </div>
            </div>
        @endif

        <div data-reveal style="--d: .32s" class="mt-5 rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60 sm:mt-6">

            <div class="rounded-t-2xl bg-slate-50 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <button type="button" id="mapel-export" data-state="idle"
                            aria-live="polite"
                            title="Unduh data mata pelajaran yang sedang difilter"
                            class="ekspor relative inline-flex h-10 flex-1 items-center justify-center gap-2 overflow-hidden whitespace-nowrap rounded-lg border border-blue-300 bg-white px-4 text-xs font-medium text-primary-60 transition hover:bg-blue-50 sm:h-9 sm:flex-none sm:text-sm">
                            <span data-ekspor-kilau class="ekspor-kilau" aria-hidden="true"></span>
                            <x-lucide-download data-ekspor-ikon="idle" class="ekspor-ikon h-4 w-4" />
                            <svg data-ekspor-ikon="muat" class="ekspor-ikon h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                                <path d="M21 12a9 9 0 1 1-6.219-8.56" />
                            </svg>
                            <x-lucide-check data-ekspor-ikon="sukses" class="ekspor-ikon h-4 w-4" />
                            <span data-ekspor-label>Export Excel</span>
                        </button>
                    </div>
                </div>

                <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative w-full sm:w-72">
                        <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-700" />
                        <input type="search" id="mapel-search" placeholder="Cari kode atau nama mapel..." autocomplete="off"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white pl-10 pr-10 text-base text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20 sm:h-9 sm:text-sm [&::-webkit-search-cancel-button]:appearance-none [&::-webkit-search-decoration]:appearance-none">
                        <button
                            type="button"
                            id="mapel-search-clear"
                            aria-label="Bersihkan pencarian"
                            class="absolute right-1.5 top-1/2 hidden -translate-y-1/2 items-center justify-center rounded-lg p-1.5 text-slate-400 transition-all duration-200 ease-out hover:bg-slate-400/15 hover:text-slate-600 focus-visible:outline-2 focus-visible:outline-blue-500 active:scale-90"
                        >
                            <x-lucide-x class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="relative flex justify-end" data-filter-root>
                        <button
                            type="button"
                            data-filter-toggle
                            aria-expanded="false"
                            aria-controls="mapel-filter-panel"
                            class="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 text-xs font-medium text-slate-600 transition hover:border-primary-60 hover:bg-blue-50 hover:text-primary-70 active:scale-[.98] data-[open=true]:border-primary-60 data-[open=true]:bg-blue-50 data-[open=true]:text-primary-70 sm:h-9"
                        >
                            <x-lucide-sliders-horizontal class="h-4 w-4" />
                            <span>Filter</span>
                            <span
                                data-filter-badge
                                class="hidden min-w-4 items-center justify-center rounded-full bg-primary-60 px-1.5 py-px text-[10px] font-semibold leading-4 text-white"
                            >0</span>
                        </button>

                        <div
                            id="mapel-filter-panel"
                            data-filter-panel
                            data-open="false"
                            aria-hidden="true"
                            inert
                            class="invisible absolute left-0 top-full z-30 mt-2 w-full min-w-0 origin-top scale-95 rounded-2xl border border-slate-100 bg-white p-4 opacity-0 shadow-xl shadow-slate-900/10 transition-[opacity,transform,visibility] duration-200 ease-out sm:left-auto sm:right-0 sm:w-80 data-[open=true]:visible data-[open=true]:scale-100 data-[open=true]:opacity-100"
                        >
                            <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Saring Mata Pelajaran</p>

                            @php
                                $filterSelect = 'h-10 w-full min-w-0 appearance-none rounded-lg border border-slate-200 bg-slate-50 px-3 pr-8 text-base text-slate-700 outline-none transition
                                    focus:border-primary-60 focus:bg-white focus:ring-2 focus:ring-primary-60/20
                                    disabled:cursor-not-allowed disabled:border-slate-100 disabled:bg-slate-100/70 disabled:text-slate-400
                                    sm:h-9 sm:text-sm';
                                $filterLabel = 'mb-1 block text-xs font-medium text-slate-600';
                            @endphp

                            <div class="space-y-3">
                                <div>
                                    <label for="mapel-filter-status" class="{{ $filterLabel }}">Kategori Status</label>
                                    <div class="relative">
                                        <select id="mapel-filter-status" data-filter-status class="{{ $filterSelect }}">
                                            <option value="">Semua Mata Pelajaran</option>
                                            <option value="diampu_saya">Mata Pelajaran Saya</option>
                                            <option value="dipakai">Mapel Aktif (Ada Pengampu)</option>
                                            <option value="kosong">Mapel Belum Ada Pengampu</option>
                                        </select>
                                        <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                                    </div>
                                </div>
                            </div>

                            <p data-filter-summary
                                class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-[11px] leading-relaxed text-slate-500"></p>

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
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <th class="w-14 px-4 py-4 text-center font-medium">No</th>
                            <th class="w-28 px-4 py-4 font-medium">Kode</th>
                            <th class="px-4 py-4 font-medium">Mata Pelajaran</th>
                            <th class="px-4 py-4 font-medium">Guru Pengampu</th>
                            <th class="w-36 px-4 py-4 font-medium">Status</th>
                            <th class="w-36 px-4 py-4 font-medium">Nilai Siswa</th>
                            <th class="w-24 px-4 py-4 text-center font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody data-table-body class="divide-y divide-slate-100 text-slate-800"></tbody>
                </table>
            </div>

            <div data-card-body class="divide-y divide-slate-100 overflow-hidden border-t border-slate-100 px-4 md:hidden"></div>

            <div id="mapel-empty" class="hidden flex-col items-center justify-center py-12 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                    <x-lucide-book-x class="h-6 w-6" />
                </div>
                <h4 class="mt-3 text-sm font-semibold text-slate-700">Tidak ada mata pelajaran yang cocok</h4>
                <p class="mt-1 text-xs text-slate-400">Coba ubah kata kunci pencarian atau sesuaikan opsi filter Anda.</p>
                <button type="button" id="btn-empty-reset" class="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-primary-60 hover:bg-slate-50">
                    <x-lucide-rotate-ccw class="h-3 w-3" />
                    Reset Pencarian & Filter
                </button>
            </div>

            <x-ui.table-footer mini label="mapel" />
        </div>
    </div>

    {{-- Modal Detail Mata Pelajaran --}}
    <div
        id="detail-modal"
        data-open="false"
        aria-hidden="true"
        inert
        class="km fixed inset-0 z-60 flex items-end justify-center overflow-y-auto overscroll-contain bg-[#0F3D73]/60 p-0 sm:items-center sm:p-4 lg:left-64"
        role="dialog"
        aria-modal="true"
        aria-labelledby="detail-modal-title"
    >
        <div data-modal-backdrop class="km-backdrop absolute inset-0"></div>

        <div
            data-modal-panel
            class="km-panel relative z-10 flex max-h-[92dvh] w-full max-w-xl flex-col rounded-t-3xl bg-[#FAFAFA] shadow-2xl sm:max-h-[88dvh] sm:rounded-2xl"
        >
            <header class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-100 bg-white px-6 pb-4 pt-6 sm:px-8 sm:pt-7">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span id="dm-kode-badge" class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold text-slate-700"></span>
                        <span id="dm-status-badge" class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-medium"></span>
                    </div>
                    <h3 id="detail-modal-title" class="mt-1 text-xl font-bold text-slate-900 sm:text-2xl"></h3>
                    <p class="mt-0.5 text-xs text-slate-500">Informasi lengkap mata pelajaran dan guru pengampu</p>
                </div>
                <button
                    type="button"
                    data-modal-close
                    aria-label="Tutup"
                    class="km-close -mr-2 -mt-1 shrink-0 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 active:scale-90"
                >
                    <x-lucide-x class="h-6 w-6" />
                </button>
            </header>

            <div class="min-h-0 flex-1 overflow-y-auto p-6 sm:p-8 space-y-6">

                {{-- Banner Khusus jika mapel ini diampu oleh guru --}}
                <div id="dm-banner-saya" class="hidden rounded-xl border border-emerald-200 bg-emerald-50/80 p-3.5 text-xs text-emerald-800">
                    <div class="flex items-center gap-2.5 font-semibold text-emerald-900">
                        <x-lucide-check-circle-2 class="h-4 w-4 text-emerald-600" />
                        <span>Mata Pelajaran yang Anda Ampu</span>
                    </div>
                    <p class="mt-1 pl-6.5 text-[11px] text-emerald-700">Anda dapat langsung melakukan entri penilaian siswa untuk mata pelajaran ini melalui menu Input Nilai.</p>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-xs">
                        <p class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Total Nilai</p>
                        <p id="dm-total-nilai" class="mt-1 text-xl font-bold text-slate-900 tabular-nums">0</p>
                        <p class="text-[10px] text-slate-400">Nilai tersimpan</p>
                    </div>
                    <div class="rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-xs">
                        <p class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Rata-Rata</p>
                        <p id="dm-rata-nilai" class="mt-1 text-xl font-bold text-primary-60 tabular-nums">-</p>
                        <p class="text-[10px] text-slate-400">Rata-rata siswa</p>
                    </div>
                    <div class="rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-xs col-span-2 sm:col-span-1">
                        <p class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Siswa Dinilai</p>
                        <p id="dm-total-siswa" class="mt-1 text-xl font-bold text-slate-900 tabular-nums">0</p>
                        <p class="text-[10px] text-slate-400">Total siswa</p>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                            <x-lucide-users class="h-4 w-4 text-primary-60" />
                            <span>Guru Pengampu (<span id="dm-pengampu-count">0</span>)</span>
                        </h4>
                    </div>

                    <div id="dm-pengampu-list" class="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white"></div>
                    <div id="dm-pengampu-empty" class="hidden mt-3 rounded-xl border border-dashed border-slate-200 p-6 text-center text-xs text-slate-400">
                        Belum ada guru yang ditugaskan untuk mengampu mata pelajaran ini.
                    </div>
                </div>
            </div>

            <footer class="flex shrink-0 items-center justify-end gap-2.5 border-t border-slate-100 bg-slate-50/80 px-6 py-3.5 sm:px-8">
                <button
                    type="button"
                    data-modal-close
                    class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-xs font-medium text-slate-700 transition hover:bg-slate-50 active:scale-[.98]"
                >
                    Tutup
                </button>
                <a
                    id="dm-link-input-nilai"
                    href="{{ route('dashboard.guru.input-nilai.index') }}"
                    class="hidden inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-primary-60 px-4 text-xs font-semibold text-white transition hover:bg-primary-70 active:scale-[.98]"
                >
                    <x-lucide-square-pen class="h-3.5 w-3.5" />
                    Input Nilai Sekarang
                </a>
            </footer>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        /* Tombol toolbar & export */
        #mapel-export {
            transition: translate .2s ease, box-shadow .2s ease, background-color .2s ease;
        }

        #mapel-export:hover {
            translate: 0 -1px;
            box-shadow: 0 6px 14px -8px rgba(37, 99, 235, .55);
        }

        #mapel-export svg {
            transition: transform .3s cubic-bezier(.22, 1, .36, 1);
        }

        #mapel-export:hover svg {
            transform: translateY(2px);
        }

        /* Status Export */
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

        /* Modal detail */
        .km {
            opacity: 0;
            pointer-events: none;
            transition: opacity .25s ease;
        }

        .km[data-open="true"] {
            opacity: 1;
            pointer-events: auto;
        }

        .km-backdrop {
            opacity: 0;
            transition: opacity .25s ease;
        }

        .km[data-open="true"] .km-backdrop { opacity: 1; }

        .km-panel {
            opacity: 0;
            transform: translateY(28px) scale(.97);
            transition:
                opacity .32s cubic-bezier(.22, 1, .36, 1),
                transform .32s cubic-bezier(.22, 1, .36, 1);
        }

        .km[data-open="true"] .km-panel {
            opacity: 1;
            transform: none;
        }

        .km-close { transition: transform .2s ease; }
        .km-close:hover { transform: scale(1.06); }
        .km-close:active { transform: scale(.94); }

        /* Scroll reveal */
        #mapel-page.reveal-ready [data-reveal] {
            opacity: 0;
            transform: translateY(14px);
            filter: blur(2px);
            transition:
                opacity .6s cubic-bezier(.22, 1, .36, 1) var(--d, 0s),
                transform .6s cubic-bezier(.22, 1, .36, 1) var(--d, 0s),
                filter .6s cubic-bezier(.22, 1, .36, 1) var(--d, 0s);
            will-change: opacity, transform;
        }

        #mapel-page.reveal-ready [data-reveal].is-visible {
            opacity: 1;
            transform: none;
            filter: none;
        }

        @media (max-width: 639px) {
            #mapel-page [data-reveal]:not(.mt-5) { --d: 0s !important; }
        }
    </style>
@endpush

@push('scripts')
    <script>
        (() => {
            const rawData = @json($mapelData);
            const allItems = (rawData.mapel || []).map(m => ({ ...m }));
            const userGuruMapel = rawData.guruMapel || '';
            const exportUrl = {!! json_encode(route('dashboard.guru.mata-pelajaran.export')) !!};

            const root = document.getElementById('mapel-page');
            const $ = (sel) => root.querySelector(sel);

            const tableBody = $('[data-table-body]');
            const cardBody  = $('[data-card-body]');
            const emptyEl   = $('#mapel-empty');
            const searchEl  = $('#mapel-search');
            const searchClr = $('#mapel-search-clear');
            const statusEl  = $('#mapel-filter-status');
            const summaryEl = $('[data-filter-summary]');
            const badgeEl   = $('[data-filter-badge]');
            const btnFokusSaya = $('#btn-fokus-mapel-saya');
            const btnEmptyReset = $('#btn-empty-reset');

            const pag       = $('[data-pagination]');
            const info      = pag.querySelector('[data-info]');
            const pagesEl   = pag.querySelector('[data-pages]');
            const prevBtn   = pag.querySelector('[data-prev]');
            const nextBtn   = pag.querySelector('[data-next]');

            const filterToggle = $('[data-filter-toggle]');
            const filterPanel  = $('#mapel-filter-panel');
            const filterReset  = $('[data-filter-reset]');
            const filterDone   = $('[data-filter-done]');

            // Detail Modal elements
            const modalEl      = document.getElementById('detail-modal');
            const modalBackdrop= modalEl.querySelector('[data-modal-backdrop]');
            const modalCloses  = modalEl.querySelectorAll('[data-modal-close]');
            const dmTitle      = document.getElementById('detail-modal-title');
            const dmKodeBadge  = document.getElementById('dm-kode-badge');
            const dmStatusBadge= document.getElementById('dm-status-badge');
            const dmBannerSaya = document.getElementById('dm-banner-saya');
            const dmTotalNilai = document.getElementById('dm-total-nilai');
            const dmRataNilai  = document.getElementById('dm-rata-nilai');
            const dmTotalSiswa = document.getElementById('dm-total-siswa');
            const dmPengampuList = document.getElementById('dm-pengampu-list');
            const dmPengampuEmpty= document.getElementById('dm-pengampu-empty');
            const dmPengampuCount= document.getElementById('dm-pengampu-count');
            const dmLinkInputNilai= document.getElementById('dm-link-input-nilai');

            const perPage = 10;
            const state = {
                q: '',
                status: '',
                page: 1,
            };

            const esc = (s) => (s == null ? '' : String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;'));

            // ===== Filter Data =====
            function getFilteredItems() {
                const q = state.q.trim().toLowerCase();
                return allItems.filter(item => {
                    if (state.status === 'diampu_saya' && !item.diampu_saya) return false;
                    if (state.status === 'dipakai' && !item.dipakai) return false;
                    if (state.status === 'kosong' && item.dipakai) return false;

                    if (q !== '') {
                        const nama = (item.nama || '').toLowerCase();
                        const kode = (item.kode || '').toLowerCase();
                        if (!nama.includes(q) && !kode.includes(q)) return false;
                    }

                    return true;
                });
            }

            // ===== Render Baris Tabel (Desktop) =====
            function renderRow(m, index) {
                const no = index + 1;
                const kodeBadge = m.kode
                    ? `<span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs font-semibold text-slate-700">${esc(m.kode)}</span>`
                    : `<span class="text-xs text-slate-300">—</span>`;

                const namaBadge = m.diampu_saya
                    ? `<span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                           <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Diampu Anda
                       </span>`
                    : '';

                let pengampuHtml = '';
                if (m.pengampu_list && m.pengampu_list.length > 0) {
                    const first = m.pengampu_list[0];
                    const count = m.pengampu_list.length;
                    pengampuHtml = `
                        <div class="flex items-center gap-2">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-[11px] font-bold text-primary-70">
                                ${esc(first.name.charAt(0))}
                            </span>
                            <span class="truncate max-w-[160px] text-xs font-medium text-slate-700" title="${esc(first.name)}">
                                ${esc(first.name)}
                            </span>
                            ${count > 1 ? `<span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-500 font-medium">+${count - 1}</span>` : ''}
                        </div>`;
                } else {
                    pengampuHtml = `<span class="text-xs text-slate-400 italic">Belum ada pengampu</span>`;
                }

                const statusBadge = m.dipakai
                    ? `<span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-[11px] font-medium text-primary-70">Aktif</span>`
                    : `<span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-medium text-slate-500">Belum Ada Pengampu</span>`;

                const nilaiHtml = m.total_nilai > 0
                    ? `<div class="text-xs">
                           <span class="font-semibold text-slate-800">${m.total_nilai} nilai</span>
                           <span class="text-slate-400"> (avg ${m.rata_rata ?? '-'})</span>
                       </div>`
                    : `<span class="text-xs text-slate-400">—</span>`;

                return `
                    <tr class="transition hover:bg-blue-50/40 ${m.diampu_saya ? 'bg-emerald-50/20' : ''}">
                        <td class="px-4 py-3.5 text-center text-xs text-slate-500">${no}</td>
                        <td class="px-4 py-3.5">${kodeBadge}</td>
                        <td class="px-4 py-3.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-slate-900">${esc(m.nama)}</span>
                                ${namaBadge}
                            </div>
                        </td>
                        <td class="px-4 py-3.5">${pengampuHtml}</td>
                        <td class="px-4 py-3.5">${statusBadge}</td>
                        <td class="px-4 py-3.5">${nilaiHtml}</td>
                        <td class="px-4 py-3.5 text-center">
                            <button type="button" data-detail-id="${m.id}" aria-label="Lihat detail ${esc(m.nama)}"
                                class="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-blue-200 bg-white px-2.5 text-xs font-medium text-primary-60 transition hover:bg-blue-50 hover:border-primary-60 active:scale-95">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>
                                </svg>
                                Detail
                            </button>
                        </td>
                    </tr>`;
            }

            // ===== Render Kartu Mobile =====
            function renderCard(m, index) {
                const no = index + 1;
                const kodeBadge = m.kode
                    ? `<span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-semibold text-slate-700">${esc(m.kode)}</span>`
                    : '';

                const namaBadge = m.diampu_saya
                    ? `<span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Diampu Anda</span>`
                    : '';

                const statusBadge = m.dipakai
                    ? `<span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-medium text-primary-70">Aktif</span>`
                    : `<span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-500">Kosong</span>`;

                const guruNama = m.pengampu_list && m.pengampu_list.length > 0
                    ? m.pengampu_list.map(g => g.name).join(', ')
                    : 'Belum ada pengampu';

                return `
                    <div class="py-4 space-y-2.5 ${m.diampu_saya ? 'bg-emerald-50/15 -mx-4 px-4' : ''}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-bold text-slate-400">#${no}</span>
                                    ${kodeBadge}
                                    ${namaBadge}
                                </div>
                                <h4 class="mt-1 text-sm font-bold text-slate-900">${esc(m.nama)}</h4>
                            </div>
                            ${statusBadge}
                        </div>

                        <div class="flex items-center justify-between text-xs text-slate-500 pt-1">
                            <div class="min-w-0 pr-2">
                                <span class="text-[11px] text-slate-400">Guru: </span>
                                <span class="font-medium text-slate-700 truncate" title="${esc(guruNama)}">${esc(guruNama)}</span>
                            </div>
                            <button type="button" data-detail-id="${m.id}"
                                class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-blue-200 bg-white px-2.5 py-1 text-xs font-medium text-primary-60 hover:bg-blue-50">
                                Detail
                            </button>
                        </div>
                    </div>`;
            }

            // ===== Render Utuh =====
            function render() {
                const filtered = getFilteredItems();
                const total = filtered.length;
                const totalPages = Math.max(1, Math.ceil(total / perPage));

                if (state.page > totalPages) state.page = totalPages;
                if (state.page < 1) state.page = 1;

                const startIdx = (state.page - 1) * perPage;
                const endIdx   = Math.min(startIdx + perPage, total);
                const pageItems = filtered.slice(startIdx, endIdx);

                if (total === 0) {
                    tableBody.innerHTML = '';
                    cardBody.innerHTML = '';
                    emptyEl.classList.remove('hidden');
                    emptyEl.classList.add('flex');
                    pag.classList.add('hidden');
                } else {
                    emptyEl.classList.add('hidden');
                    emptyEl.classList.remove('flex');
                    pag.classList.remove('hidden');

                    tableBody.innerHTML = pageItems.map((m, i) => renderRow(m, startIdx + i)).join('');
                    cardBody.innerHTML  = pageItems.map((m, i) => renderCard(m, startIdx + i)).join('');
                }

                info.textContent = total > 0
                    ? `Menampilkan ${startIdx + 1}–${endIdx} dari ${total} mapel`
                    : 'Tidak ada data';

                prevBtn.disabled = (state.page <= 1);
                nextBtn.disabled = (state.page >= totalPages);

                let pagesHtml = '';
                for (let p = 1; p <= totalPages; p++) {
                    if (totalPages > 6 && Math.abs(p - state.page) > 2 && p !== 1 && p !== totalPages) {
                        if (p === 2 || p === totalPages - 1) {
                            pagesHtml += `<span class="px-1 text-slate-400">…</span>`;
                        }
                        continue;
                    }
                    const activeCls = (p === state.page)
                        ? 'bg-primary-60 text-white font-semibold'
                        : 'text-slate-600 hover:bg-slate-100';
                    pagesHtml += `<button type="button" data-page="${p}" class="h-7 w-7 rounded-lg text-xs transition ${activeCls}">${p}</button>`;
                }
                pagesEl.innerHTML = pagesHtml;

                let filterCount = 0;
                let summaryTxt = 'Menampilkan semua mata pelajaran.';
                if (state.status) {
                    filterCount++;
                    if (state.status === 'diampu_saya') summaryTxt = 'Hanya menampilkan mata pelajaran yang Anda ampu.';
                    else if (state.status === 'dipakai') summaryTxt = 'Hanya menampilkan mata pelajaran yang memiliki guru pengampu.';
                    else if (state.status === 'kosong') summaryTxt = 'Hanya menampilkan mata pelajaran yang belum memiliki guru pengampu.';
                }

                if (filterCount > 0) {
                    badgeEl.textContent = filterCount;
                    badgeEl.classList.remove('hidden');
                    badgeEl.classList.add('inline-flex');
                } else {
                    badgeEl.classList.add('hidden');
                    badgeEl.classList.remove('inline-flex');
                }
                summaryEl.textContent = summaryTxt;
            }

            // ===== Modal Detail Logic =====
            function openDetail(id) {
                const m = allItems.find(x => x.id === Number(id));
                if (!m) return;

                dmTitle.textContent = m.nama;
                dmKodeBadge.textContent = m.kode || 'TANPA KODE';

                if (m.dipakai) {
                    dmStatusBadge.className = 'inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-[11px] font-medium text-primary-70';
                    dmStatusBadge.textContent = 'Aktif Digunakan';
                } else {
                    dmStatusBadge.className = 'inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-medium text-slate-600';
                    dmStatusBadge.textContent = 'Belum Ada Pengampu';
                }

                if (m.diampu_saya) {
                    dmBannerSaya.classList.remove('hidden');
                    dmLinkInputNilai.classList.remove('hidden');
                } else {
                    dmBannerSaya.classList.add('hidden');
                    dmLinkInputNilai.classList.add('hidden');
                }

                dmTotalNilai.textContent = (m.total_nilai || 0).toLocaleString('id-ID');
                dmRataNilai.textContent  = m.rata_rata != null ? m.rata_rata : '-';
                dmTotalSiswa.textContent = (m.total_siswa || 0).toLocaleString('id-ID');

                const pengampu = m.pengampu_list || [];
                dmPengampuCount.textContent = pengampu.length;

                if (pengampu.length > 0) {
                    dmPengampuEmpty.classList.add('hidden');
                    dmPengampuList.classList.remove('hidden');
                    dmPengampuList.innerHTML = pengampu.map(g => `
                        <div class="flex items-center justify-between p-3.5">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-primary-70">
                                    ${esc(g.name.charAt(0))}
                                </span>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <p class="truncate text-xs font-semibold text-slate-900" title="${esc(g.name)}">${esc(g.name)}</p>
                                        ${g.is_me ? '<span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">Anda</span>' : ''}
                                    </div>
                                    <p class="truncate text-[11px] text-slate-400" title="NIP: ${esc(g.nip || '-')} • ${esc(g.email)}">NIP: ${esc(g.nip || '-')} • ${esc(g.email)}</p>
                                </div>
                            </div>
                            <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-medium text-slate-600">Guru Pengampu</span>
                        </div>
                    `).join('');
                } else {
                    dmPengampuList.classList.add('hidden');
                    dmPengampuEmpty.classList.remove('hidden');
                }

                modalEl.dataset.open = 'true';
                modalEl.setAttribute('aria-hidden', 'false');
                modalEl.removeAttribute('inert');
                document.body.style.overflow = 'hidden';
            }

            function closeDetail() {
                modalEl.dataset.open = 'false';
                modalEl.setAttribute('aria-hidden', 'true');
                modalEl.setAttribute('inert', '');
                document.body.style.overflow = '';
            }

            // ===== Event Listeners =====
            root.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-detail-id]');
                if (btn) {
                    openDetail(btn.dataset.detailId);
                }
            });

            modalCloses.forEach(btn => btn.addEventListener('click', closeDetail));
            modalBackdrop.addEventListener('click', closeDetail);
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && modalEl.dataset.open === 'true') {
                    closeDetail();
                }
            });

            searchEl.addEventListener('input', () => {
                state.q = searchEl.value;
                state.page = 1;
                searchClr.classList.toggle('hidden', state.q === '');
                render();
            });

            searchClr.addEventListener('click', () => {
                searchEl.value = '';
                state.q = '';
                state.page = 1;
                searchClr.classList.add('hidden');
                searchEl.focus();
                render();
            });

            function toggleFilter(open) {
                const isOpen = open !== undefined ? open : filterPanel.dataset.open !== 'true';
                filterPanel.dataset.open = isOpen ? 'true' : 'false';
                filterPanel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
                filterToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                filterToggle.dataset.open = isOpen ? 'true' : 'false';
                if (isOpen) {
                    filterPanel.removeAttribute('inert');
                } else {
                    filterPanel.setAttribute('inert', '');
                    // Buang perubahan yang belum diterapkan saat panel ditutup
                    statusEl.value = state.status;
                }
            }

            filterToggle.addEventListener('click', () => toggleFilter());
            document.addEventListener('click', (e) => {
                if (!e.target.closest('[data-filter-root]')) {
                    toggleFilter(false);
                }
            });

            filterDone.addEventListener('click', () => {
                state.status = statusEl.value;
                state.page = 1;
                toggleFilter(false);
                render();
            });

            filterReset.addEventListener('click', () => {
                // Reset langsung tanpa perlu menekan "Terapkan".
                searchEl.value = '';
                statusEl.value = '';
                searchClr.classList.add('hidden');
                state.q = '';
                state.status = '';
                state.page = 1;
                render();
            });

            // Tombol "Saring Mapel Saya" dari banner
            if (btnFokusSaya) {
                btnFokusSaya.addEventListener('click', () => {
                    statusEl.value = 'diampu_saya';
                    state.status = 'diampu_saya';
                    state.page = 1;
                    render();
                    const tableContainer = root.querySelector('.border-slate-100.bg-white');
                    if (tableContainer) {
                        tableContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            }

            // Tombol reset pada Empty State
            if (btnEmptyReset) {
                btnEmptyReset.addEventListener('click', () => {
                    searchEl.value = '';
                    state.q = '';
                    searchClr.classList.add('hidden');
                    statusEl.value = '';
                    state.status = '';
                    state.page = 1;
                    render();
                });
            }

            prevBtn.addEventListener('click', () => { if (state.page > 1) { state.page--; render(); } });
            nextBtn.addEventListener('click', () => { state.page++; render(); });
            pagesEl.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-page]');
                if (btn) {
                    state.page = Number(btn.dataset.page);
                    render();
                }
            });

            // ===== Export Excel =====
            const tombolExport = $('#mapel-export');
            const labelExport = tombolExport.querySelector('[data-ekspor-label]');
            const LABEL_AWAL = labelExport ? labelExport.textContent.trim() : 'Export Excel';
            let exportState = 'idle', exportTimer = null, exportGuard = null;

            const setExportState = (s, t) => {
                clearTimeout(exportTimer);
                exportState = s;
                if (tombolExport) {
                    tombolExport.dataset.state = s;
                    tombolExport.disabled = (s === 'muat');
                }
                if (s !== 'muat') {
                    clearTimeout(exportGuard);
                    exportGuard = null;
                }
                if (t && labelExport) labelExport.textContent = t;
            };

            const namaBerkas = (d) => {
                const c = /filename="?([^";]+)"?/i.exec(d || '');
                return c ? c[1] : 'data-mata-pelajaran-guru.xlsx';
            };

            const unduhBlob = (b, n) => {
                const u = URL.createObjectURL(b);
                const a = document.createElement('a');
                a.href = u;
                a.download = n;
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(() => URL.revokeObjectURL(u), 1000);
            };

            tombolExport.addEventListener('click', async () => {
                if (exportState === 'muat') return;

                const params = new URLSearchParams();
                if (state.q) params.set('q', state.q);
                if (state.status) params.set('status', state.status);
                const qStr = params.toString();

                setExportState('muat', 'Menyiapkan...');
                exportGuard = setTimeout(() => {
                    if (exportState === 'muat') {
                        setExportState('idle', LABEL_AWAL);
                        alert('Gagal mengunduh: server terlalu lama merespons.');
                    }
                }, 45000);

                try {
                    const opsi = {
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: { Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }
                    };
                    if (typeof AbortSignal !== 'undefined' && AbortSignal.timeout) {
                        opsi.signal = AbortSignal.timeout(45000);
                    }
                    const res = await fetch(qStr ? `${exportUrl}?${qStr}` : exportUrl, opsi);
                    const tipe = res.headers.get('Content-Type') || '';
                    if (!res.ok) throw new Error('Gagal mengunduh (HTTP ' + res.status + ')');
                    if (!tipe.includes('spreadsheet')) throw new Error('Server tidak mengembalikan file Excel.');

                    const total = Number(res.headers.get('X-Total-Mapel') || 0);
                    unduhBlob(await res.blob(), namaBerkas(res.headers.get('Content-Disposition')));
                    setExportState('sukses', total > 0 ? `${total} mapel diunduh` : 'Unduh selesai');
                    exportTimer = setTimeout(() => setExportState('idle', LABEL_AWAL), 2400);
                } catch (e) {
                    if (exportState !== 'idle') {
                        setExportState('idle', LABEL_AWAL);
                    }
                    alert(e.message || 'Gagal mengunduh file Excel');
                }
            });

            // ===== Count Up Animation =====
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const easeOutExpo = (t) => (t === 1 ? 1 : 1 - Math.pow(2, -10 * t));

            function countTo(el, from, to, ms) {
                if (reduceMotion || from === to) { el.textContent = to; return; }
                const t0 = performance.now();
                const step = (now) => {
                    const t = Math.min(1, (now - t0) / ms);
                    el.textContent = Math.round(from + (to - from) * easeOutExpo(t));
                    if (t < 1) requestAnimationFrame(step);
                };
                requestAnimationFrame(step);
            }

            function initReveal() {
                if (!('IntersectionObserver' in window)) return;
                root.classList.add('reveal-ready');

                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) return;
                        const target = entry.target;
                        target.classList.add('is-visible');
                        observer.unobserve(target);

                        const delay = (parseFloat(getComputedStyle(target).getPropertyValue('--d')) || 0) * 1000;
                        target.querySelectorAll('[data-count]').forEach((el) => {
                            const val = Number(el.dataset.count);
                            if (!isNaN(val) && val > 0) {
                                setTimeout(() => countTo(el, 0, val, 1400), delay + 200);
                            }
                        });
                    });
                }, { threshold: 0.05, rootMargin: '0px 0px -30px 0px' });

                root.querySelectorAll('[data-reveal]').forEach((el) => {
                    observer.observe(el);
                    if (el.getBoundingClientRect().top < window.innerHeight) {
                        el.classList.add('is-visible');
                    }
                });
            }

            // Inisialisasi awal
            const urlQ = new URLSearchParams(window.location.search).get('q') || new URLSearchParams(window.location.search).get('search');
            if (urlQ) {
                state.q = urlQ;
                searchEl.value = urlQ;
                searchClr.classList.remove('hidden');
            }
            render();
            initReveal();
        })();
    </script>
@endpush