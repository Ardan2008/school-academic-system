@extends('layouts.dashboard')

@section('title', 'Data Mata Pelajaran')
@section('subtitle', 'Dashboard Admin')

@section('content')
    <div id="mapel-page">

        {{-- Kartu statistik --}}
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
                        :count="true"
                    />
                </div>
            @endforeach
        </div>

        {{-- Tabel --}}
        <div data-reveal style="--d: .28s" class="mt-5 rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60 sm:mt-6">

            {{-- Toolbar --}}
            <div class="rounded-t-2xl bg-slate-50 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <button type="button" id="mapel-add"
                            class="inline-flex h-10 flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-primary-60 px-4 text-xs font-medium text-white transition hover:bg-primary-70 sm:h-9 sm:flex-none sm:text-sm">
                            <x-lucide-plus class="h-4 w-4" />
                            Tambah Mapel
                        </button>
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
                    <div class="relative w-full sm:w-64">
                        <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-700" />
                        <input type="search" id="mapel-search" placeholder="Cari kode atau nama mapel" autocomplete="off"
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

                    {{-- Tombol filter + panel popup --}}
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
                            <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Saring Data Mata Pelajaran</p>

                            @php
                                $filterSelect = 'h-10 w-full min-w-0 appearance-none rounded-lg border border-slate-200 bg-slate-50 px-3 pr-8 text-base text-slate-600 outline-none transition
                                    focus:border-primary-60 focus:bg-white focus:ring-2 focus:ring-primary-60/20
                                    disabled:cursor-not-allowed disabled:border-slate-100 disabled:bg-slate-100/70 disabled:text-slate-400
                                    sm:h-9 sm:text-sm';
                                $filterLabel = 'mb-1 block text-xs font-medium text-slate-600';
                            @endphp

                            <div class="space-y-3">
                                <div>
                                    <label for="mapel-filter-isi" class="{{ $filterLabel }}">Pemakaian</label>
                                    <div class="relative">
                                        <select id="mapel-filter-isi" data-filter-isi class="{{ $filterSelect }}">
                                            <option value="">Semua Kondisi</option>
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

            {{-- Tabel: md ke atas --}}
            <div class="hidden overflow-x-auto overflow-y-hidden border-t border-slate-100 md:block">
                <table class="w-full text-center text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3.5 text-center font-semibold">No</th>
                            <th class="px-4 py-3.5 font-semibold">Kode</th>
                            <th class="px-4 py-3.5 font-semibold">Mata Pelajaran</th>
                            <th class="px-4 py-3.5 font-semibold">Jumlah Pengampu</th>
                            <th class="px-4 py-3.5 font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody data-table-body class="divide-y divide-slate-100 text-slate-800"></tbody>
                </table>
            </div>

            {{-- Daftar kartu: HP --}}
            <div data-card-body class="divide-y divide-slate-100 overflow-hidden border-t border-slate-100 px-4 md:hidden"></div>

            {{-- Footer + pagination --}}
            <x-ui.table-footer mini label="mapel" />
        </div>
    </div>

    {{-- ============ Modal Tambah / Ubah Data Mata Pelajaran ============ --}}
    <div
        id="mapel-modal"
        data-open="false"
        aria-hidden="true"
        inert
        class="km fixed inset-0 z-60 flex items-end justify-center overflow-y-auto overscroll-contain bg-[#0F3D73]/60 p-0 sm:items-center sm:p-4 lg:left-64"
        role="dialog"
        aria-modal="true"
        aria-labelledby="mapel-modal-title"
    >
        <div data-modal-backdrop class="km-backdrop absolute inset-0"></div>

        <div
            data-modal-panel
            class="km-panel relative z-10 flex max-h-[92dvh] w-full max-w-2xl flex-col rounded-t-3xl bg-[#FAFAFA] shadow-2xl sm:max-h-[88dvh] sm:rounded-2xl"
        >
            {{-- Kepala --}}
            <header class="flex shrink-0 items-start gap-3 px-6 pb-2 pt-6 sm:px-8 sm:pt-7">
                <div class="min-w-0 flex-1">
                    <h3 id="mapel-modal-title" data-modal-title class="text-2xl font-bold leading-tight text-primary-70">Tambah Mapel</h3>
                    <p data-modal-subtitle class="mt-1 text-sm text-primary-60">Lengkapi data mata pelajaran yang akan ditambahkan</p>
                </div>
                <button
                    type="button"
                    data-modal-close
                    aria-label="Tutup"
                    class="km-close -mr-2 -mt-1 shrink-0 rounded-lg p-1.5 text-primary-60 transition hover:bg-blue-50 active:scale-90"
                >
                    <x-lucide-x class="h-6 w-6" />
                </button>
            </header>

            {{-- Formulir --}}
            <form id="mapel-form" class="grid min-h-0 flex-1 grid-cols-1 content-start gap-x-8 gap-y-4 overflow-y-auto px-6 py-4 sm:grid-cols-2 sm:px-8" autocomplete="off" novalidate>
                @csrf

                @php
                    // Kelas dasar semua kolom; ikon di kiri memakai pl-10
                    $kField = 'h-11 w-full rounded-md border border-slate-300 bg-white pl-10 pr-3 text-base text-slate-800 outline-none transition
                        placeholder:text-xs placeholder:text-slate-400
                        focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20 sm:text-sm sm:placeholder:text-xs';
                    $kIcon  = 'pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-primary-60';
                    $kLabel = 'mb-1.5 block text-sm font-medium text-primary-70';
                @endphp

                {{-- Kode mapel --}}
                <div>
                    <label for="mf-kode" class="{{ $kLabel }}">Kode Mapel</label>
                    <div class="relative">
                        <x-lucide-hash class="{{ $kIcon }}" />
                        <input type="text" id="mf-kode" name="kode" maxlength="20"
                            class="{{ $kField }} placeholder:text-slate-400" placeholder="Contoh: MTK">
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Boleh dikosongkan. Dipakai sebagai tanda pendek di dokumen.</p>
                </div>

                {{-- Nama mapel --}}
                <div>
                    <label for="mf-nama" class="{{ $kLabel }}">Nama Mata Pelajaran</label>
                    <div class="relative">
                        <x-lucide-book-open class="{{ $kIcon }}" />
                        <input type="text" id="mf-nama" name="nama" required maxlength="120"
                            class="{{ $kField }} placeholder:text-slate-400" placeholder="Contoh: Matematika">
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Nama tidak boleh sama dengan mapel yang sudah terdaftar.</p>
                </div>

                {{-- Info mapel ini sudah diampu --}}
                <div data-mapel-terpakai
                    class="hidden items-center gap-2 rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-700 sm:col-span-2">
                    <x-lucide-info class="h-4 w-4 shrink-0" />
                    <span data-mapel-terpakai-teks></span>
                </div>

                <p data-modal-error
                    class="hidden items-center gap-2 rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs text-red-600 sm:col-span-2"></p>
            </form>

            {{-- Aksi --}}
            <footer class="mx-6 flex shrink-0 items-center justify-end gap-3 border-t border-slate-300 py-4 sm:mx-8">
                <button type="button" data-modal-close
                    class="km-close inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-md border border-primary-60 bg-white px-5 text-sm font-medium text-primary-70 transition hover:bg-blue-50 active:scale-[.98] sm:flex-none">
                    <x-lucide-x class="h-4 w-4" />
                    Batal
                </button>
                <button type="submit" form="mapel-form" data-modal-save
                    class="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-md bg-primary-60 px-6 text-sm font-medium text-white shadow-md shadow-primary-60/25 transition hover:bg-primary-70 active:scale-[.98] disabled:opacity-60 sm:flex-none">
                    <x-lucide-save class="h-4 w-4" />
                    <span data-modal-save-text>Simpan</span>
                </button>
            </footer>
        </div>
    </div>

    {{-- ============ Popup Konfirmasi Simpan (Tambah / Ubah) ============ --}}
    <div
        id="mapel-confirm-modal"
        data-open="false"
        aria-hidden="true"
        inert
        class="kconfirm fixed inset-0 z-70 flex items-center justify-center p-4"
    >
        <div data-confirm-backdrop class="kconfirm-backdrop absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>

        <div
            data-confirm-panel
            role="dialog"
            aria-modal="true"
            aria-labelledby="mapel-confirm-title"
            class="kconfirm-panel relative z-10 max-h-full w-full max-w-sm overflow-y-auto rounded-2xl bg-white p-5 text-center shadow-2xl sm:p-6"
        >
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-primary-60">
                <x-lucide-save class="h-5 w-5" />
            </div>

            <h3 id="mapel-confirm-title" data-confirm-title class="text-base font-semibold text-slate-900">
                Yakin ingin menyimpan data?
            </h3>
            <p data-confirm-text class="mt-1.5 text-sm text-slate-500">
                Data mata pelajaran akan disimpan ke daftar.
            </p>

            <div class="mt-6 flex gap-3">
                <button
                    type="button"
                    data-confirm-no
                    data-key-nav
                    class="flex-1 rounded-xl border border-slate-200 py-3 text-sm font-medium
                           text-slate-600 transition hover:bg-slate-50
                           focus-visible:outline-2
                           focus-visible:outline-offset-2 focus-visible:outline-slate-400
                           sm:py-2.5"
                >
                    Tidak
                </button>
                <button
                    type="button"
                    data-confirm-yes
                    data-key-nav
                    class="flex-1 rounded-xl bg-primary-60 py-3 text-sm font-semibold
                           text-white transition hover:bg-primary-70
                           focus-visible:outline-2
                           focus-visible:outline-offset-2 focus-visible:outline-primary-70
                           sm:py-2.5"
                >
                    Ya, Simpan
                </button>
            </div>

            <p class="mt-3 hidden text-center text-[11px] text-slate-400 sm:block">
                Gunakan <kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">&larr;</kbd><kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">&rarr;</kbd> untuk memilih, <kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">Enter</kbd> untuk konfirmasi.
            </p>
        </div>
    </div>

    <style>
        /* ===== Scroll reveal ===== */
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

        /* ===== Tombol toolbar ===== */
        #mapel-add, #mapel-export {
            transition: translate .2s ease, box-shadow .2s ease, background-color .2s ease;
        }

        #mapel-add:hover, #mapel-export:hover {
            translate: 0 -1px;
            box-shadow: 0 6px 14px -8px rgba(37, 99, 235, .55);
        }

        #mapel-add svg, #mapel-export svg { transition: transform .3s cubic-bezier(.22, 1, .36, 1); }
        #mapel-add:hover svg { transform: rotate(90deg); }

        /* ===== Tombol export: tiga keadaan ===== */
        .ekspor-ikon { transition: opacity .18s ease, transform .18s ease; }

        .ekspor[data-state="idle"] [data-ekspor-ikon]:not([data-ekspor-ikon="idle"]),
        .ekspor[data-state="muat"] [data-ekspor-ikon]:not([data-ekspor-ikon="muat"]),
        .ekspor[data-state="sukses"] [data-ekspor-ikon]:not([data-ekspor-ikon="sukses"]) {
            display: none;
        }

        .ekspor[data-state="muat"] {
            cursor: progress;
            border-color: rgb(191 219 254);
            background-color: rgb(239 246 255);
            translate: none;
            box-shadow: none;
        }

        .ekspor[data-state="muat"] [data-ekspor-label] { opacity: .75; }

        .ekspor[data-state="sukses"] {
            cursor: default;
            border-color: rgb(167 243 208);
            background-color: rgb(236 253 245);
            color: rgb(4 120 87);
            translate: none;
            box-shadow: 0 6px 14px -10px rgb(16 185 129);
        }

        .ekspor[data-state="sukses"] [data-ekspor-ikon="sukses"] {
            animation: ekspor-pop .45s cubic-bezier(.34, 1.56, .64, 1);
        }

        @keyframes ekspor-pop {
            0%   { transform: scale(.3) rotate(-25deg); opacity: 0; }
            60%  { transform: scale(1.18) rotate(6deg); opacity: 1; }
            100% { transform: scale(1) rotate(0); opacity: 1; }
        }

        .ekspor-kilau {
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: 0;
            background: linear-gradient(100deg,
                    transparent 20%,
                    rgb(255 255 255 / .85) 50%,
                    transparent 80%);
            transform: translateX(-100%);
        }

        .ekspor[data-state="muat"] .ekspor-kilau {
            opacity: 1;
            animation: ekspor-kilau 1.1s ease-in-out infinite;
        }

        @keyframes ekspor-kilau {
            0%   { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        #mapel-page [data-filter-toggle] svg:first-child {
            transition: transform .3s cubic-bezier(.22, 1, .36, 1);
        }

        #mapel-page [data-filter-toggle][data-open="true"] svg:first-child {
            transform: rotate(180deg);
        }

        /* ===== Modal tambah/ubah ===== */
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

        .km[data-open="true"] .km-panel { opacity: 1; transform: none; }

        .km-close { transition: transform .2s ease; }
        .km-close:hover { transform: scale(1.06); }
        .km-close:active { transform: scale(.94); }

        #mapel-form > * {
            opacity: 0;
            transform: translateY(12px);
        }

        .km[data-open="true"] #mapel-form > * {
            animation: mapelFieldIn .34s cubic-bezier(.22, 1, .36, 1) forwards;
            animation-delay: calc(.06s + var(--k, 0) * .045s);
        }

        @keyframes mapelFieldIn {
            to { opacity: 1; transform: none; }
        }

        /* ===== Baris tabel & kartu ===== */
        .mapel-row { animation: mapelRowIn .38s cubic-bezier(.22, 1, .36, 1) backwards; }

        @keyframes mapelRowIn {
            from { opacity: 0; transform: translateY(8px); }
        }

        [data-table-body][data-anim="on"] .mapel-row,
        [data-card-body][data-anim="on"] .mapel-card {
            animation-delay: calc(var(--i, 0) * .05s);
        }

        [data-table-body][data-anim="off"] .mapel-row,
        [data-card-body][data-anim="off"] .mapel-card {
            animation: none;
        }

        .mapel-card { animation: mapelRowIn .38s cubic-bezier(.22, 1, .36, 1) backwards; }

        .mapel-row[data-flash="true"] td,
        .mapel-row[data-flash="true"] > div {
            animation: mapelFlash 1.3s ease-out;
        }

        @keyframes mapelFlash {
            0%   { background-color: #DBEAFE; }
            100% { background-color: transparent; }
        }

        .mapel-stat[data-bump="true"] { animation: mapelBump .45s cubic-bezier(.22, 1, .36, 1); }

        @keyframes mapelBump {
            0%   { transform: scale(1); }
            45%  { transform: scale(1.14); color: #1E86FF; }
            100% { transform: scale(1); }
        }

        .mapel-act { transition: translate .2s ease, box-shadow .2s ease; }
        .mapel-act:hover { translate: 0 -1px; }

        .mapel-act:disabled,
        .mapel-act[data-disabled="true"] {
            cursor: not-allowed;
            opacity: .45;
            translate: none;
            box-shadow: none;
        }

        .mapel-empty { animation: mapelRowIn .3s ease-out; }

        /* ===== Popup konfirmasi ===== */
        .kconfirm { opacity: 0; pointer-events: none; transition: opacity .25s ease; }
        .kconfirm[data-open="true"] { opacity: 1; pointer-events: auto; }

        .kconfirm-backdrop { opacity: 0; transition: opacity .25s ease; }
        .kconfirm[data-open="true"] .kconfirm-backdrop { opacity: 1; }

        .kconfirm-panel {
            opacity: 0;
            transform: translateY(16px) scale(.96);
            transition:
                opacity .28s cubic-bezier(.22, 1, .36, 1),
                transform .28s cubic-bezier(.22, 1, .36, 1);
        }

        .kconfirm[data-open="true"] .kconfirm-panel { opacity: 1; transform: none; }

        .kconfirm-key-selected {
            outline: 2px solid #1E86FF;
            outline-offset: 2px;
        }

        @media (prefers-reduced-motion: reduce) {
            #mapel-page.reveal-ready [data-reveal] {
                opacity: 1;
                transform: none;
                filter: none;
                transition: none;
            }

            #mapel-add:hover svg,
            #mapel-export:hover svg { animation: none; transform: none; }

            .ekspor[data-state="muat"] .ekspor-kilau { animation: none; opacity: .5; }
            .ekspor[data-state="sukses"] [data-ekspor-ikon="sukses"] { animation: none; }

            #mapel-add:hover, #mapel-export:hover, .mapel-act:hover { translate: none; }

            .kconfirm, .kconfirm-backdrop, .kconfirm-panel { transition: none; }

            .km, .km-backdrop, .km-panel, .mapel-row, .mapel-card, .mapel-empty,
            .km[data-open="true"] #mapel-form > *,
            .mapel-row[data-flash="true"] td,
            .mapel-row[data-flash="true"] > div,
            .mapel-stat[data-bump="true"] {
                transition: none;
                animation: none;
            }

            .km[data-open="true"] .km-panel,
            .mapel-row {
                opacity: 1;
                transform: none;
            }
        }
    </style>

    <script>
        (function () {
            // ===== Data dari PHP =====
            const data = @json($mapelData);
            let rows = (data.mapel || []).slice();
            const perPage = 5;

            const root = document.getElementById('mapel-page');
            const $ = (sel) => root.querySelector(sel);

            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[c]));

            const state = { q: '', isi: '', page: 1 };

            // ID yang baru saja diubah/ditambah, supaya barisnya bisa diberi FLASH
            let flashId = null;
            let lastFocused = null;

            const tableBody = $('[data-table-body]');
            const cardBody  = $('[data-card-body]');
            const pag       = $('[data-pagination]');
            const info      = pag.querySelector('[data-info]');
            const pagesEl   = pag.querySelector('[data-pages]');
            const prevBtn   = pag.querySelector('[data-prev]');
            const nextBtn   = pag.querySelector('[data-next]');
            const label     = pag.dataset.label || 'mapel';

            const searchEl  = $('#mapel-search');
            const isiEl     = $('[data-filter-isi]');
            const summaryEl = $('[data-filter-summary]');

            const badgeKode    = 'bg-slate-100 font-mono text-slate-600';
            const badgeDipakai = 'bg-emerald-100 text-emerald-700';
            const badgeKosong  = 'bg-slate-100 text-slate-500';
            const badgePengampu = 'bg-indigo-100 text-indigo-600';

            const pill = (cls, text) =>
                `<span class="inline-block whitespace-nowrap rounded-full px-2.5 py-0.5 text-[11px] font-medium ${cls}">${esc(text)}</span>`;

            const jumlahPengampu = (n) => (n === 1 ? '1 pengampu' : `${n} pengampu`);

            const labelMapel = (m) => m.kode ? `${m.kode} • ${m.nama}` : m.nama;

            // ===== Animasi angka (count up) =====
            const easeOutExpo = (t) => (t === 1 ? 1 : 1 - Math.pow(2, -10 * t));

            function countTo(el, from, to, ms) {
                cancelAnimationFrame(el._raf);
                if (reduceMotion || from === to) { el.textContent = to; return; }

                const t0 = performance.now();
                const step = (now) => {
                    const t = Math.min(1, (now - t0) / ms);
                    el.textContent = Math.round(from + (to - from) * easeOutExpo(t));
                    if (t < 1) el._raf = requestAnimationFrame(step);
                };
                el._raf = requestAnimationFrame(step);
            }

            // ===== Scroll reveal + angka statistik menghitung naik =====
            function initReveal() {
                if (!('IntersectionObserver' in window)) return;

                root.classList.add('reveal-ready');
                if (!reduceMotion) {
                    root.querySelectorAll('[data-count]').forEach((el) => { el.textContent = '0'; });
                }

                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) return;

                        const target = entry.target;
                        target.classList.add('is-visible');
                        observer.unobserve(target);

                        const delay = (parseFloat(getComputedStyle(target).getPropertyValue('--d')) || 0) * 1000;
                        target.querySelectorAll('[data-count]').forEach((el) => {
                            setTimeout(() => countTo(el, 0, Number(el.dataset.count), 1800), delay + 300);
                        });
                    });
                }, { threshold: 0.1, rootMargin: '0px 0px -30px 0px' });

                root.querySelectorAll('[data-reveal]').forEach((el) => observer.observe(el));
            }

            const ICON_EDIT = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>';
            const ICON_DEL  = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>';

            // Mapel yang diampu tidak bisa dihapus; tombol ditampilkan mati dengan alasan yang jelas.
            const actions = (m) => {
                const kunci = m.dipakai;

                return `
                    <div class="inline-flex items-center gap-2">
                        <button type="button" data-action="edit" data-id="${m.id}" aria-label="Ubah ${esc(labelMapel(m))}"
                            class="mapel-act flex h-8 w-8 items-center justify-center rounded-lg border border-blue-300 bg-white text-primary-60 transition hover:bg-blue-50 active:scale-90">${ICON_EDIT}</button>
                        <button type="button" data-action="delete" data-id="${m.id}" aria-label="Hapus ${esc(labelMapel(m))}"
                            ${kunci ? 'disabled title="Mata pelajaran ini masih diampu guru, jadi belum bisa dihapus."' : ''}
                            class="mapel-act flex h-8 w-8 items-center justify-center rounded-lg border border-blue-300 bg-white text-primary-60 transition hover:bg-red-50 hover:text-red-500 active:scale-90">${ICON_DEL}</button>
                    </div>`;
            };

            // ===== Template baris =====
            const kodePill = (m) =>
                m.kode ? pill(badgeKode, m.kode) : pill('bg-slate-50 text-slate-300', '—');

            const dipakaiPill = (m) =>
                m.dipakai ? pill(badgeDipakai, 'Dipakai') : pill(badgeKosong, 'Belum dipakai');

            const rowTable = (m, no, i) => `
                <tr data-row-id="${m.id}" class="mapel-row transition hover:bg-slate-50/70" style="--i:${i}">
                    <td class="px-4 py-3 text-center text-slate-500">${no}</td>
                    <td class="px-4 py-3">${kodePill(m)}</td>
                    <td class="px-4 py-3 font-medium text-slate-900">${esc(m.nama)}</td>
                    <td class="px-4 py-3 tabular-nums text-slate-700">${m.jumlah_pengampu}</td>
                    <td class="px-4 py-3">${actions(m)}</td>
                </tr>`;

            const rowCard = (m, i) => `
                <div data-row-id="${m.id}" class="mapel-card py-4" style="--i:${i}">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-900" title="${esc(m.nama)}">${esc(m.nama)}</p>
                            <p class="mt-0.5 truncate text-xs text-slate-500" title="${esc(m.kode || 'Tanpa kode')}">${esc(m.kode || 'Tanpa kode')}</p>
                        </div>
                        ${dipakaiPill(m)}
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                        ${pill(badgePengampu, jumlahPengampu(m.jumlah_pengampu))}
                    </div>
                    <div class="mt-3">${actions(m)}</div>
                </div>`;

            // ===== Filter =====
            const fillSelect = (sel, items, placeholder) => {
                const current = sel.value;
                [...sel.options].slice(1).forEach((o) => o.remove());

                items.forEach((i) => sel.add(new Option(i.teks, i.nilai)));

                if (sel.options[0]) sel.options[0].textContent = placeholder;
                sel.value = items.some((i) => i.nilai === current) ? current : '';
            };

            function filtered() {
                const q = state.q.trim().toLowerCase();

                return rows.filter((m) => {
                    if (state.isi === 'kosong' && m.dipakai) return false;
                    if (state.isi === 'dipakai' && !m.dipakai) return false;

                    if (q) {
                        const haystack = [m.nama, m.kode]
                            .filter((v) => v !== null && v !== undefined)
                            .join(' ')
                            .toLowerCase();
                        if (!haystack.includes(q)) return false;
                    }

                    return true;
                });
            }

            // Satu baris penjelasan: berapa mapel yang tampil dan berapa pengampu.
            function syncSummary() {
                const list = filtered();
                const pengampu = list.reduce((t, m) => t + m.jumlah_pengampu, 0);
                const kosong = list.filter((m) => !m.dipakai).length;

                summaryEl.textContent = `${list.length} mapel, ${pengampu} guru pengampu. `
                    + (kosong ? `${kosong} mapel belum diampu guru.` : 'Semua mapel sudah diampu guru.');
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
            const IDLE   = 'hover:bg-slate-100 text-slate-600';

            function render(animate = true) {
                const list  = filtered();
                const total = list.length;
                const last  = Math.max(1, Math.ceil(total / perPage));
                state.page = Math.min(state.page, last);

                const start = (state.page - 1) * perPage;
                const slice = list.slice(start, start + perPage);

                tableBody.dataset.anim = animate ? 'on' : 'off';
                cardBody.dataset.anim  = animate ? 'on' : 'off';

                if (!slice.length) {
                    const msg = 'Tidak ada data mata pelajaran yang cocok';
                    tableBody.innerHTML = `<tr><td colspan="5" class="mapel-empty px-4 py-12 text-center text-sm text-slate-400">${msg}</td></tr>`;
                    cardBody.innerHTML  = `<p class="mapel-empty py-10 text-center text-sm text-slate-400">${msg}</p>`;
                } else {
                    tableBody.innerHTML = slice.map((m, i) => rowTable(m, start + i + 1, i)).join('');
                    cardBody.innerHTML  = slice.map((m, i) => rowCard(m, i)).join('');
                }

                // Baris hasil perubahan diberi FLASH, lalu atributnya dibuang
                if (flashId !== null) {
                    const done = () => { flashId = null; };
                    requestAnimationFrame(() => {
                        const el = root.querySelector(`[data-row-id="${flashId}"]`);
                        if (!el) return;
                        el.dataset.flash = 'true';
                        el.addEventListener('animationend', done, { once: true });
                        setTimeout(done, 1400);
                    });
                }

                const from = total === 0 ? 0 : start + 1;
                const to   = Math.min(state.page * perPage, total);
                info.textContent = `Menampilkan ${from}–${to} dari ${total} ${label}`;

                pagesEl.innerHTML = pageList(state.page, last).map((n) => n === '...'
                    ? '<span class="px-1 text-slate-400">...</span>'
                    : `<button type="button" data-page="${n}" class="min-w-8 rounded-lg px-2.5 py-1.5 text-sm transition-colors duration-200 sm:min-w-0 sm:py-1 ${n === state.page ? ACTIVE : IDLE}">${n}</button>`
                ).join('');

                prevBtn.disabled = state.page === 1;
                nextBtn.disabled = state.page === last;
            }

            // Angka statistik ikut berubah setiap mapel disimpan / dihapus
            function updateStats() {
                const set = (key, val) => {
                    const el = root.querySelector(`[data-stat="${key}"]`);
                    if (!el) return;

                    const prev = Number(el.dataset.count);
                    if (prev === val) return;

                    el.dataset.count = val;
                    countTo(el, Number(el.textContent) || prev, val, 800);

                    el.dataset.bump = 'false';
                    void el.offsetWidth;
                    el.dataset.bump = 'true';
                };

                set('total', rows.length);
                set('dipakai', rows.filter((r) => r.dipakai).length);
                set('kosong', rows.filter((r) => !r.dipakai).length);
                set('pengampu', rows.reduce((t, m) => t + m.jumlah_pengampu, 0));
            }

            function refreshFilters() {
                fillSelect(isiEl, [
                    { nilai: 'dipakai', teks: 'Sudah diampu' },
                    { nilai: 'kosong', teks: 'Belum diampu' },
                ], 'Semua Kondisi');

                state.isi = isiEl.value;
            }

            // ===== Event =====
            const filterRoot  = $('[data-filter-root]');
            const filterBtn   = $('[data-filter-toggle]');
            const filterPanel = $('[data-filter-panel]');
            const filterBadge = $('[data-filter-badge]');

            const setPanel = (open) => {
                filterPanel.dataset.open = open ? 'true' : 'false';
                filterPanel.setAttribute('aria-hidden', open ? 'false' : 'true');
                filterPanel.toggleAttribute('inert', !open);
                filterBtn.dataset.open = open ? 'true' : 'false';
                filterBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) setTimeout(() => isiEl?.focus(), 30);
                // Buang perubahan yang belum diterapkan saat panel ditutup
                else isiEl.value = state.isi;
            };

            const syncBadge = () => {
                const n = (state.isi ? 1 : 0) + (state.q.trim() ? 1 : 0);

                filterBadge.classList.toggle('hidden', n === 0);
                filterBadge.textContent = n;
            };

            filterBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                setPanel(filterPanel.dataset.open !== 'true');
            });

            filterPanel.querySelector('[data-filter-done]').addEventListener('click', (e) => {
                e.stopPropagation();
                // Terapkan pilihan baru ke data
                state.isi = isiEl.value;
                state.page = 1;
                syncBadge();
                syncSummary();
                render();
                setPanel(false);
            });

            filterPanel.querySelector('[data-filter-reset]').addEventListener('click', (e) => {
                e.stopPropagation();
                // Reset langsung: semua filter & pencarian dikosongkan tanpa perlu menekan "Terapkan".
                searchEl.value = '';
                isiEl.value = '';
                Object.assign(state, { q: '', isi: '', page: 1 });
                refreshFilters();
                syncSearchClear();
                syncBadge();
                syncSummary();
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

            // Kotak pencarian + tombol bersihkan
            const clearBtn = root.querySelector('#mapel-search-clear');
            const syncSearchClear = () => clearBtn.classList.toggle('hidden', searchEl.value.length === 0);

            searchEl.addEventListener('input', () => {
                state.q = searchEl.value;
                state.page = 1;
                syncSearchClear();
                syncBadge();
                syncSummary();
                render(false);
            });

            searchEl.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                render();
            });

            clearBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                searchEl.value = '';
                state.q = '';
                state.page = 1;
                syncSearchClear();
                syncBadge();
                syncSummary();
                render();
                searchEl.focus();
            });

            // Filter disimpan sebagai draf dan diterapkan saat "Terapkan"; pencarian (state.q) langsung.

            prevBtn.addEventListener('click', () => { state.page--; render(); });
            nextBtn.addEventListener('click', () => { state.page++; render(); });
            pagesEl.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-page]');
                if (btn) { state.page = Number(btn.dataset.page); render(); }
            });

            // ===== Modal tambah/ubah =====
            const modal      = document.getElementById('mapel-modal');
            const form       = document.getElementById('mapel-form');
            const titleEl    = modal.querySelector('[data-modal-title]');
            const subtitleEl = modal.querySelector('[data-modal-subtitle]');
            const errorEl    = modal.querySelector('[data-modal-error]');
            const saveBtn    = modal.querySelector('[data-modal-save]');
            const saveText   = modal.querySelector('[data-modal-save-text]');
            const terpakaiEl = modal.querySelector('[data-mapel-terpakai]');
            const terpakaiTx = modal.querySelector('[data-mapel-terpakai-teks]');
            const confirm    = document.getElementById('mapel-confirm-modal');
            const csrf       = form.querySelector('input[name="_token"]')?.value
                || document.querySelector('meta[name="csrf-token"]')?.content
                || '';

            const f = {
                kode: document.getElementById('mf-kode'),
                nama: document.getElementById('mf-nama'),
            };

            let mode = 'add';        // 'add' | 'edit'
            let editingId = null;

            const showError = (msg) => {
                errorEl.textContent = msg;
                errorEl.classList.toggle('hidden', !msg);
                errorEl.classList.toggle('flex', !!msg);
            };

            const setBusy = (busy) => {
                saveBtn.disabled = busy;
                saveBtn.classList.toggle('opacity-60', busy);
                saveText.textContent = busy ? 'Menyimpan...' : 'Simpan';
            };

            const showLoading = (text) => {
                const loading = document.getElementById('site-loading');
                if (!loading) return null;
                const t = loading.querySelector('[data-loading-text]');
                if (t) t.textContent = text;
                loading.removeAttribute('inert');
                loading.setAttribute('aria-hidden', 'false');
                requestAnimationFrame(() => { loading.dataset.open = 'true'; });
                return loading;
            };

            const hideLoading = (loading) => {
                if (!loading) return;
                loading.dataset.open = 'false';
                loading.setAttribute('aria-hidden', 'true');
                const finish = () => loading.setAttribute('inert', '');
                loading.addEventListener('transitionend', finish, { once: true });
                setTimeout(finish, 400);
            };

            // m = null -> mode tambah, m = objek mapel -> mode ubah
            const openModal = (m) => {
                mode        = m ? 'edit' : 'add';
                editingId   = m ? m.id : null;
                lastFocused = document.activeElement;

                form.reset();
                showError('');
                setBusy(false);

                if (m) {
                    titleEl.textContent    = 'Ubah Data Mata Pelajaran';
                    subtitleEl.textContent = `Perbarui mapel ${m.nama}`;

                    f.kode.value = (m.kode ?? '').toUpperCase();
                    f.nama.value = m.nama ?? '';
                } else {
                    titleEl.textContent    = 'Tambah Mapel';
                    subtitleEl.textContent = 'Lengkapi data mata pelajaran yang akan ditambahkan';
                }

                // Mapel yang diampu dikunci pada namanya supaya alasannya terlihat lebih awal.
                // Kodenya tetap bisa diganti.
                const terkunci = !!(m && m.dipakai);
                terpakaiEl.classList.toggle('hidden', !terkunci);
                terpakaiEl.classList.toggle('flex', terkunci);

                if (terkunci) {
                    terpakaiTx.textContent = `Mata pelajaran ini masih diampu ${jumlahPengampu(m.jumlah_pengampu)}, `
                        + 'jadi namanya tidak bisa diubah. Kodenya masih bisa diganti.';
                    f.nama.readOnly = true;
                    f.nama.classList.add('bg-slate-100', 'text-slate-500');
                } else {
                    terpakaiTx.textContent = '';
                    f.nama.readOnly = false;
                    f.nama.classList.remove('bg-slate-100', 'text-slate-500');
                }

                // Urutan muncul isi formulir (dipakai animasi bertahap di CSS)
                [...form.children].forEach((el, i) => el.style.setProperty('--k', i));

                modal.removeAttribute('inert');
                modal.setAttribute('aria-hidden', 'false');
                requestAnimationFrame(() => { modal.dataset.open = 'true'; });
                document.body.classList.add('overflow-hidden');
                setTimeout(() => f.nama.focus(), 260);
            };

            const closeModal = () => {
                if (modal.dataset.open !== 'true') return;
                modal.dataset.open = 'false';
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
                const finish = () => { modal.setAttribute('inert', ''); editingId = null; };
                modal.addEventListener('transitionend', finish, { once: true });
                setTimeout(finish, 400);
                lastFocused?.focus();
            };

            modal.querySelectorAll('[data-modal-close]').forEach((b) => b.addEventListener('click', closeModal));
            modal.querySelector('[data-modal-backdrop]').addEventListener('click', closeModal);

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && modal.dataset.open === 'true') {
                    // Popup konfirmasi lebih tinggi, jadi tutup itu dulu
                    if (confirm.dataset.open === 'true') {
                        closeConfirm();
                        return;
                    }
                    closeModal();
                }
            });

            $('#mapel-add').addEventListener('click', () => openModal(null));

            const toast = (text) => {
                if (!window.Swal) return;
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text,
                    showConfirmButton: false,
                    timer: 1400,
                    timerProgressBar: true,
                    allowOutsideClick: false,
                });
            };

            const alertError = (title, text) => {
                if (window.Swal) {
                    Swal.fire({ icon: 'error', title, text, confirmButtonText: 'Coba Lagi' });
                } else {
                    window.alert(text);
                }
            };

            /* ---------- Popup konfirmasi ---------- */
            const confirmTitle  = confirm.querySelector('[data-confirm-title]');
            const confirmText   = confirm.querySelector('[data-confirm-text]');
            const confirmNo     = confirm.querySelector('[data-confirm-no]');
            const confirmYes    = confirm.querySelector('[data-confirm-yes]');
            const confirmBtns   = [confirmNo, confirmYes];
            let confirmNavIndex = 1;          // default fokus = "Ya, Simpan"
            let confirmReturnFocus = null;
            let pendingSave = null;

            const setConfirmNavIndex = (index, moveFocus = true) => {
                confirmNavIndex = index;
                confirmBtns.forEach((btn, i) => btn.classList.toggle('kconfirm-key-selected', i === index));
                if (moveFocus) confirmBtns[index].focus();
            };

            const openConfirm = ({ title, text }) => {
                confirmTitle.textContent = title;
                confirmText.textContent  = text;
                confirmReturnFocus = document.activeElement;

                confirm.removeAttribute('inert');
                confirm.setAttribute('aria-hidden', 'false');
                requestAnimationFrame(() => { confirm.dataset.open = 'true'; });
                setTimeout(() => setConfirmNavIndex(1), 30);
            };

            const closeConfirm = () => {
                if (confirm.dataset.open !== 'true') return;
                pendingSave = null;
                confirm.dataset.open = 'false';
                confirm.setAttribute('aria-hidden', 'true');
                const finish = () => confirm.setAttribute('inert', '');
                confirm.addEventListener('transitionend', finish, { once: true });
                setTimeout(finish, 400);
                confirmReturnFocus?.focus();
            };

            document.addEventListener('keydown', (e) => {
                if (confirm.dataset.open !== 'true') return;

                if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) {
                    e.preventDefault();
                    setConfirmNavIndex(confirmNavIndex === 0 ? 1 : 0);
                    return;
                }

                if (e.key === 'Enter') {
                    e.preventDefault();
                    confirmBtns[confirmNavIndex].click();
                    return;
                }

                if (e.key === 'Escape') {
                    e.preventDefault();
                    closeConfirm();
                }
            });

            confirmNo.addEventListener('click', closeConfirm);
            confirm.querySelector('[data-confirm-backdrop]').addEventListener('click', closeConfirm);

            /* ---------- Simpan ke server ---------- */
            const baseUrl = {!! json_encode(route('dashboard.admin.data-mata-pelajaran.index')) !!};
            const urlFor = (id) => (id ? baseUrl + '/' + id : baseUrl);

            const jsonRequest = (url, method, payload = null) => fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: payload === null ? undefined : JSON.stringify(payload),
            });

            // Urutan baris harus sama dengan yang dikirim server.
            const urutRows = () => rows.sort((a, b) =>
                String(a.nama).localeCompare(String(b.nama)));

            const saveRow = async (payload) => {
                const isAdd = mode === 'add';
                const loading = showLoading(isAdd ? 'Menyimpan data mapel...' : 'Memperbarui data mapel...');

                setBusy(true);
                showError('');

                try {
                    const res = await jsonRequest(
                        urlFor(isAdd ? null : editingId),
                        isAdd ? 'POST' : 'PUT',
                        payload
                    );

                    const result = await res.json().catch(() => null);

                    if (!res.ok || !result || result.status !== 'success') {
                        const errors = (result && result.errors) || {};
                        showError(Object.values(errors).flat()[0]
                            || (result && result.message)
                            || 'Gagal menyimpan data. Coba lagi.');
                        setBusy(false);
                        hideLoading(loading);
                        return;
                    }

                    const saved = result.data.mapel;
                    const index = rows.findIndex((r) => r.id === saved.id);

                    if (index >= 0) rows[index] = saved;
                    else rows.push(saved);

                    urutRows();
                    flashId = saved.id;

                    // Bersihkan pencarian & filter supaya mapel baru pasti terlihat
                    if (isAdd) {
                        searchEl.value = '';
                        state.q = '';
                        isiEl.value = '';
                        state.isi = '';
                        state.page = Math.ceil(rows.length / perPage);
                    }

                    refreshFilters();
                    syncSearchClear();
                    syncBadge();
                    updateStats();
                    syncSummary();
                    render();
                    setBusy(false);

                    // Formulir ditutup setelah data tersimpan di server
                    confirmReturnFocus = null;
                    closeConfirm();
                    closeModal();

                    hideLoading(loading);
                    toast(result.message);
                } catch (e) {
                    showError('Tidak dapat terhubung ke server. Silakan coba lagi.');
                    setBusy(false);
                    hideLoading(loading);
                }
            };

            confirmYes.addEventListener('click', () => {
                if (!pendingSave) return;

                const { payload } = pendingSave;
                pendingSave = null;

                // Hanya popup konfirmasi yang ditutup; formulir tetap terbuka agar pesan galat server masih terlihat.
                confirmReturnFocus = saveBtn;
                closeConfirm();

                saveRow(payload);
            });

            form.addEventListener('submit', (e) => {
                e.preventDefault();

                const isAdd = mode === 'add';
                const m     = isAdd ? null : rows.find((r) => r.id === editingId);
                if (!isAdd && !m) return;

                const kode = f.kode.value.trim().toUpperCase();
                const nama = f.nama.value.trim();

                if (!nama) {
                    showError('Nama mata pelajaran wajib diisi.');
                    f.nama.focus();
                    return;
                }

                if (kode && !/^[A-Za-z0-9\-\.\/]+$/.test(kode)) {
                    showError('Kode hanya boleh berisi huruf, angka, tanda hubung, titik, atau garis miring.');
                    f.kode.focus();
                    return;
                }

                const selfId = isAdd ? null : editingId;

                const dup = rows.find((r) => String(r.id) !== String(selfId)
                    && r.nama.toLowerCase() === nama.toLowerCase());

                if (dup) {
                    showError(`Mata pelajaran ${nama} sudah terdaftar.`);
                    f.nama.focus();
                    return;
                }

                // Validasi lolos, simpan dulu lalu minta konfirmasi
                pendingSave = { payload: { kode: kode || null, nama } };

                openConfirm(isAdd
                    ? { title: 'Yakin ingin menambahkan mapel?', text: `Mapel ${nama} akan ditambahkan ke daftar.` }
                    : { title: 'Yakin ingin menyimpan perubahan?', text: `Perubahan mapel ${m.nama} akan disimpan.` });
            });

            root.addEventListener('click', async (e) => {
                const btn = e.target.closest('[data-action]');
                if (!btn) return;

                const id = Number(btn.dataset.id);
                const m  = rows.find((r) => r.id === id);
                if (!m) return;

                if (btn.dataset.action === 'edit') {
                    openModal(m);
                    return;
                }

                // Mapel yang diampu tidak sampai ke sini karena tombolnya sudah nonaktif, tapi dicek ulang di server.
                if (m.dipakai) {
                    alertError('Belum Bisa Dihapus', `Mata pelajaran ${m.nama} masih diampu oleh guru.`);
                    return;
                }

                let ok;
                if (window.Swal) {
                    ok = (await Swal.fire({
                        icon: 'warning',
                        title: 'Hapus data mata pelajaran?',
                        text: `Mapel ${m.nama} akan dihapus dari daftar.`,
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Hapus',
                        cancelButtonText: 'Batal',
                    })).isConfirmed;
                } else {
                    ok = confirm(`Hapus mapel ${m.nama}?`);
                }
                if (!ok) return;

                const loading = showLoading('Menghapus data mapel...');

                try {
                    const res = await jsonRequest(urlFor(id), 'DELETE');

                    const result = await res.json().catch(() => null);

                    if (!res.ok || !result || result.status !== 'success') {
                        hideLoading(loading);
                        alertError('Gagal Menghapus', (result && result.message) || 'Terjadi kesalahan. Silakan coba lagi.');
                        return;
                    }

                    rows = rows.filter((r) => r.id !== id);

                    refreshFilters();
                    updateStats();
                    syncSummary();
                    render();

                    hideLoading(loading);
                    toast(result.message);
                } catch (e) {
                    hideLoading(loading);
                    alertError('Gagal Menghapus', 'Tidak dapat terhubung ke server. Silakan coba lagi.');
                }
            });

            // Export lewat fetch (bukan navigasi biasa) supaya tombol bisa menampilkan proses.
            const exportUrl = {!! json_encode(route('dashboard.admin.data-mata-pelajaran.export')) !!};

            const tombolExport = $('#mapel-export');
            const labelExport = tombolExport.querySelector('[data-ekspor-label]');
            const LABEL_AWAL = labelExport.textContent.trim();
            const KEMBALI_SUKSES_MS = 2400;

            let exportState = 'idle';
            let exportTimer = null;

            const setExportState = (stateBtn, text) => {
                clearTimeout(exportTimer);
                exportState = stateBtn;
                tombolExport.dataset.state = stateBtn;
                tombolExport.disabled = stateBtn === 'muat';

                if (text) labelExport.textContent = text;
            };

            const namaBerkas = (disposisi) => {
                const cocok = /filename="?([^";]+)"?/i.exec(disposisi || '');

                return cocok ? cocok[1] : 'data-mata-pelajaran.xlsx';
            };

            const unduhBlob = (blob, nama) => {
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = nama;
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(() => URL.revokeObjectURL(url), 1000);
            };

            tombolExport.addEventListener('click', async () => {
                // Klik kedua saat proses berjalan diabaikan.
                if (exportState === 'muat') return;

                const params = new URLSearchParams();

                for (const key of ['q', 'isi']) {
                    if (state[key]) params.set(key, state[key]);
                }

                const query = params.toString();

                setExportState('muat', 'Menyiapkan…');

                try {
                    const respons = await fetch(query ? `${exportUrl}?${query}` : exportUrl, {
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: { Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
                    });

                    // Sesi habis diarahkan ke login dengan respons HTML; tanpa cek ini, admin
                    // akan mengunduh halaman login berekstensi .xlsx.
                    const tipe = respons.headers.get('Content-Type') || '';

                    if (!respons.ok || !tipe.includes('spreadsheet')) {
                        throw new Error(
                            respons.status === 403
                                ? 'Anda tidak punya akses untuk mengunduh data ini.'
                                : 'Sesi Anda sudah habis. Muat ulang halaman lalu coba lagi.'
                        );
                    }

                    const total = Number(respons.headers.get('X-Total-Mapel') || 0);

                    unduhBlob(await respons.blob(), namaBerkas(respons.headers.get('Content-Disposition')));

                    setExportState('sukses', total > 0 ? `${total} mapel diunduh` : 'Tidak ada mapel');
                    exportTimer = setTimeout(() => {
                        setExportState('idle', LABEL_AWAL);
                    }, KEMBALI_SUKSES_MS);
                } catch (galat) {
                    setExportState('idle', LABEL_AWAL);
                    alertError('Gagal Mengunduh', galat.message || 'Tidak dapat terhubung ke server.');
                }
            });

            // ===== Jalankan =====
            const urlQ = new URLSearchParams(window.location.search).get('q') || new URLSearchParams(window.location.search).get('search');
            if (urlQ) {
                state.q = urlQ;
                searchEl.value = urlQ;
                syncSearchClear();
            }
            refreshFilters();
            render();
            syncBadge();
            syncSummary();
            initReveal();
        })();
    </script>
@endsection