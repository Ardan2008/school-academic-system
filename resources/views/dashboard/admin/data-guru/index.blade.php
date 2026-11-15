@extends('layouts.dashboard')

@section('title', 'Data Guru')
@section('subtitle', 'Dashboard Admin')

@section('content')
    <div id="guru-page">

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
                        <button type="button" id="guru-add"
                            class="inline-flex h-10 flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-primary-60 px-4 text-xs font-medium text-white transition hover:bg-primary-70 sm:h-9 sm:flex-none sm:text-sm">
                            <x-lucide-plus class="h-4 w-4" />
                            Tambah Guru
                        </button>
                        <button type="button" id="guru-export" data-state="idle"
                            aria-live="polite"
                            title="Unduh data guru yang sedang difilter"
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
                        <input type="search" id="guru-search" placeholder="Cari nama, NIP, atau email" autocomplete="off"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white pl-10 pr-10 text-base text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20 sm:h-9 sm:text-sm [&::-webkit-search-cancel-button]:appearance-none [&::-webkit-search-decoration]:appearance-none">
                        <button
                            type="button"
                            id="guru-search-clear"
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
                            aria-controls="guru-filter-panel"
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
                            id="guru-filter-panel"
                            data-filter-panel
                            data-open="false"
                            aria-hidden="true"
                            inert
                            class="invisible absolute left-0 top-full z-30 mt-2 w-full min-w-0 origin-top scale-95 rounded-2xl border border-slate-100 bg-white p-4 opacity-0 shadow-xl shadow-slate-900/10 transition-[opacity,transform,visibility] duration-200 ease-out sm:left-auto sm:right-0 sm:w-80 data-[open=true]:visible data-[open=true]:scale-100 data-[open=true]:opacity-100"
                        >
                            <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Saring Data Guru</p>

                            <div class="space-y-3">
                                <div>
                                    <label for="guru-filter-status" class="mb-1 block text-xs font-medium text-slate-600">Status</label>
                                    <div class="relative">
                                        <select id="guru-filter-status"
                                            class="h-10 w-full min-w-0 appearance-none rounded-lg border border-slate-200 bg-slate-50 px-3 pr-8 text-base text-slate-600 outline-none focus:border-primary-60 sm:h-9 sm:text-sm">
                                            <option value="">Semua Status</option>
                                        </select>
                                        <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                                    </div>
                                </div>
                                <div>
                                    <label for="guru-filter-mapel" class="mb-1 block text-xs font-medium text-slate-600">Mata Pelajaran</label>
                                    <div class="relative">
                                        <select id="guru-filter-mapel"
                                            class="h-10 w-full min-w-0 appearance-none rounded-lg border border-slate-200 bg-slate-50 px-3 pr-8 text-base text-slate-600 outline-none focus:border-primary-60 sm:h-9 sm:text-sm">
                                            <option value="">Semua Mata Pelajaran</option>
                                        </select>
                                        <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center gap-2 border-t border-slate-100 pt-3">
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

            {{-- Tabel: md ke atas (overflow-y-hidden mencegah scrollbar vertikal berkedip saat baris beranimasi) --}}
            <div class="hidden overflow-x-auto overflow-y-hidden border-t border-slate-100 md:block">
                <table class="w-full text-center text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-4 text-left font-medium">No</th>
                            <th class="px-4 py-4 font-medium">NIP</th>
                            <th class="px-4 py-4 font-medium">Nama Guru</th>
                            <th class="px-4 py-4 font-medium">Email</th>
                            <th class="px-4 py-4 font-medium">Mata Pelajaran</th>
                            <th class="px-4 py-4 font-medium">Status</th>
                            <th class="px-4 py-4 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody data-table-body class="divide-y divide-slate-100 text-slate-800"></tbody>
                </table>
            </div>

            {{-- Daftar kartu: HP --}}
            <div data-card-body class="divide-y divide-slate-100 overflow-hidden border-t border-slate-100 px-4 md:hidden"></div>

            {{-- Footer + pagination --}}
            <x-ui.table-footer mini label="data" />
        </div>
    </div>

    {{-- ============ Modal Tambah / Ubah Data Guru (satu modal, dua mode) ============ --}}
    <div
        id="guru-modal"
        data-open="false"
        aria-hidden="true"
        inert
        class="gm fixed inset-0 z-60 flex items-end justify-center overflow-y-auto overscroll-contain bg-[#0F3D73]/60 p-0 sm:items-center sm:p-4 lg:left-64"
        role="dialog"
        aria-modal="true"
        aria-labelledby="guru-modal-title"
    >
        <div data-modal-backdrop class="gm-backdrop absolute inset-0"></div>

        <div
            data-modal-panel
            class="gm-panel relative z-10 flex max-h-[92dvh] w-full max-w-2xl flex-col rounded-t-3xl bg-[#FAFAFA] shadow-2xl sm:max-h-[88dvh] sm:rounded-2xl"
        >
            {{-- Kepala --}}
            <header class="flex shrink-0 items-start gap-3 px-6 pb-2 pt-6 sm:px-8 sm:pt-7">
                <div class="min-w-0 flex-1">
                    <h3 id="guru-modal-title" data-modal-title class="text-2xl font-bold leading-tight text-primary-70">Tambah Guru</h3>
                    <p data-modal-subtitle class="mt-1 text-sm text-primary-60">Lengkapi data guru yang akan ditambahkan</p>
                </div>
                <button
                    type="button"
                    data-modal-close
                    aria-label="Tutup"
                    class="gm-close -mr-2 -mt-1 shrink-0 rounded-lg p-1.5 text-primary-60 transition hover:bg-blue-50 active:scale-90"
                >
                    <x-lucide-x class="h-6 w-6" />
                </button>
            </header>

            {{-- Formulir --}}
            <form id="guru-form" class="grid min-h-0 flex-1 grid-cols-1 content-start gap-x-8 gap-y-4 overflow-y-auto px-6 py-4 sm:grid-cols-2 sm:px-8" autocomplete="off" novalidate>
                @php
                    // Kelas dasar semua kolom; ikon di kiri memakai pl-10
                    $gField = 'h-11 w-full rounded-md border border-slate-300 bg-white pl-10 pr-3 text-base text-slate-800 outline-none transition
                        placeholder:text-xs placeholder:text-slate-400
                        focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20 sm:text-sm sm:placeholder:text-xs';
                    $gIcon  = 'pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-primary-60';
                    $gLabel = 'mb-1.5 block text-sm font-medium text-primary-70';
                @endphp

                {{-- NIP --}}
                <div>
                    <label for="gm-nis" class="{{ $gLabel }}">NIP</label>
                    <div class="relative">
                        <x-lucide-user class="{{ $gIcon }}" />
                        <input type="text" id="gm-nis" name="nis" required maxlength="20" inputmode="numeric"
                            class="{{ $gField }}" placeholder="Masukkan NIP guru">
                    </div>
                </div>

                {{-- Nama --}}
                <div>
                    <label for="gm-nama" class="{{ $gLabel }}">Nama Guru</label>
                    <div class="relative">
                        <x-lucide-user class="{{ $gIcon }}" />
                        <input type="text" id="gm-nama" name="nama" required maxlength="100"
                            class="{{ $gField }}" placeholder="Masukkan nama lengkap guru">
                    </div>
                </div>

                {{-- Mata pelajaran --}}
                <div>
                    <label for="gm-mapel" class="{{ $gLabel }}">Mata Pelajaran</label>
                    <div class="relative">
                        <x-lucide-book-open class="{{ $gIcon }}" />
                        <select id="gm-mapel" name="mapel" required class="{{ $gField }} appearance-none pr-10 text-slate-800">
                            <option value="" disabled selected>Pilih mata pelajaran</option>
                        </select>
                        <x-lucide-chevron-down class="pointer-events-none absolute right-3 top-1/2 h-5 w-5 -translate-y-1/2 text-primary-60" />
                    </div>
                </div>

                {{-- Email --}}
                <div>
                    <label for="gm-email" class="{{ $gLabel }}">Email</label>
                    <div class="relative">
                        <x-lucide-mail class="{{ $gIcon }}" />
                        <input type="email" id="gm-email" name="email" required maxlength="100" autocapitalize="none" spellcheck="false"
                            class="{{ $gField }}" placeholder="nama@saskansa.sch.id">
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Email dipakai untuk masuk ke akun guru.</p>
                </div>

                {{-- Password awal (khusus mode tambah; sistem yang membuat) --}}
                <div data-field-password>
                    <label for="gm-password" class="{{ $gLabel }}">Password Awal</label>
                    <div class="relative">
                        <x-lucide-lock class="{{ $gIcon }}" />
                        <input type="text" id="gm-password" name="password" readonly autocomplete="off" autocapitalize="off"
                            spellcheck="false" class="{{ $gField }} pr-20 bg-slate-50 font-mono text-slate-800">
                        <div class="absolute right-1.5 top-1/2 flex -translate-y-1/2 items-center">
                            <button type="button" data-generate-password title="Buat ulang password" aria-label="Buat ulang password"
                                class="rounded-md p-2 text-primary-60 transition hover:bg-blue-50 active:scale-90">
                                <x-lucide-wand-sparkles class="h-4 w-4" />
                            </button>
                            <button type="button" data-copy-password title="Salin password" aria-label="Salin password"
                                class="rounded-md p-2 text-primary-60 transition hover:bg-blue-50 active:scale-90">
                                <x-lucide-copy class="h-4 w-4" data-copy-icon />
                                <x-lucide-check class="hidden h-4 w-4 text-emerald-600" data-copy-ok />
                            </button>
                            <button type="button" data-toggle-password aria-label="Sembunyikan password" aria-pressed="true"
                                class="rounded-md p-2 text-primary-60 transition hover:bg-blue-50 active:scale-90">
                                <x-lucide-eye class="h-4 w-4" data-eye-on />
                                <x-lucide-eye-off class="hidden h-4 w-4" data-eye-off />
                            </button>
                        </div>
                    </div>
                    <p data-password-hint class="mt-1 hidden text-xs text-slate-500"></p>
                </div>

                {{-- Status akun --}}
                <div>
                    <label for="gm-status" class="{{ $gLabel }}">Status Akun</label>
                    <div class="relative">
                        <x-lucide-shield-check class="{{ $gIcon }}" />
                        <select id="gm-status" name="status" class="{{ $gField }} appearance-none pr-10 text-slate-800">
                            <option value="Aktif">Aktif</option>
                            <option value="Nonaktif">Nonaktif</option>
                            <option value="Cuti">Cuti</option>
                        </select>
                        <x-lucide-chevron-down class="pointer-events-none absolute right-3 top-1/2 h-5 w-5 -translate-y-1/2 text-primary-60" />
                    </div>
                </div>

                <p data-modal-error
                    class="hidden items-center gap-2 rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs text-red-600 sm:col-span-2"></p>
            </form>

            {{-- Aksi --}}
            <footer class="mx-6 flex shrink-0 items-center justify-end gap-3 border-t border-slate-300 py-4 sm:mx-8">
                <button type="button" data-modal-close
                    class="gm-close inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-md border border-primary-60 bg-white px-5 text-sm font-medium text-primary-70 transition hover:bg-blue-50 active:scale-[.98] sm:flex-none">
                    <x-lucide-x class="h-4 w-4" />
                    Batal
                </button>
                <button type="submit" form="guru-form" data-modal-save
                    class="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-md bg-primary-60 px-6 text-sm font-medium text-white shadow-md shadow-primary-60/25 transition hover:bg-primary-70 active:scale-[.98] disabled:opacity-60 sm:flex-none">
                    <x-lucide-save class="h-4 w-4" />
                    <span data-modal-save-text>Simpan</span>
                </button>
            </footer>
        </div>
    </div>

    {{-- ============ Popup Konfirmasi Simpan (Tambah / Ubah) ============ --}}
    <div
        id="guru-confirm-modal"
        data-open="false"
        aria-hidden="true"
        inert
        class="gconfirm fixed inset-0 z-70 flex items-center justify-center p-4"
    >
        <div data-confirm-backdrop class="gconfirm-backdrop absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>

        <div
            data-confirm-panel
            role="dialog"
            aria-modal="true"
            aria-labelledby="guru-confirm-title"
            class="gconfirm-panel relative z-10 max-h-full w-full max-w-sm overflow-y-auto rounded-2xl bg-white p-5 text-center shadow-2xl sm:p-6"
        >
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-primary-60">
                <x-lucide-save class="h-5 w-5" />
            </div>

            <h3 id="guru-confirm-title" data-confirm-title class="text-base font-semibold text-slate-900">
                Yakin ingin menyimpan data?
            </h3>
            <p data-confirm-text class="mt-1.5 text-sm text-slate-500">
                Data guru akan disimpan ke daftar.
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

            {{-- Petunjuk keyboard hanya relevan di layar besar --}}
            <p class="mt-3 hidden text-center text-[11px] text-slate-400 sm:block">
                Gunakan <kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">←</kbd><kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">→</kbd> untuk memilih, <kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">Enter</kbd> untuk konfirmasi.
            </p>
        </div>
    </div>

    <style>
        /* ===== Scroll reveal ===== */
        #guru-page.reveal-ready [data-reveal] {
            opacity: 0;
            transform: translateY(14px);
            filter: blur(2px);
            transition:
                opacity .6s cubic-bezier(.22, 1, .36, 1) var(--d, 0s),
                transform .6s cubic-bezier(.22, 1, .36, 1) var(--d, 0s),
                filter .6s cubic-bezier(.22, 1, .36, 1) var(--d, 0s);
            will-change: opacity, transform;
        }

        #guru-page.reveal-ready [data-reveal].is-visible {
            opacity: 1;
            transform: none;
            filter: none;
        }

        /* Di layar kecil kartu bertumpuk: hilangkan jeda bertahap agar terasa responsif */
        @media (max-width: 639px) {
            #guru-page [data-reveal]:not(.mt-5) { --d: 0s !important; }
        }

        /* ===== Tombol toolbar ===== */
        #guru-add, #guru-export {
            transition: translate .2s ease, box-shadow .2s ease, background-color .2s ease;
        }

        #guru-add:hover, #guru-export:hover {
            translate: 0 -1px;
            box-shadow: 0 6px 14px -8px rgba(37, 99, 235, .55);
        }

        #guru-add svg, #guru-export svg { transition: transform .3s cubic-bezier(.22, 1, .36, 1); }
        #guru-add:hover svg { transform: rotate(90deg); }

        /* ===== Tombol export: tiga keadaan (diam, mengunduh, berhasil) =====
           Tampilannya semua berasal dari atribut data-state, jadi JavaScript
           cukup mengubah satu atribut dan tidak perlu mengatur class satu per satu. */

        .ekspor-ikon { transition: opacity .18s ease, transform .18s ease; }

        /* Ikon yang tidak dipakai pada keadaan sekarang disembunyikan. */
        .ekspor[data-state="idle"] [data-ekspor-ikon]:not([data-ekspor-ikon="idle"]),
        .ekspor[data-state="muat"] [data-ekspor-ikon]:not([data-ekspor-ikon="muat"]),
        .ekspor[data-state="sukses"] [data-ekspor-ikon]:not([data-ekspor-ikon="sukses"]) {
            display: none;
        }

        /* Keadaan mengunduh: tombol mati agar tidak bisa diklik dua kali. */
        .ekspor[data-state="muat"] {
            cursor: progress;
            border-color: rgb(191 219 254);
            background-color: rgb(239 246 255);
            translate: none;
            box-shadow: none;
        }

        .ekspor[data-state="muat"]:hover { transform: none; }
        .ekspor[data-state="muat"] [data-ekspor-label] { opacity: .75; }

        /* Keadaan berhasil: hijau sejenak sebagai tanda file sudah masuk. */
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

        /* Cahaya bergerak melintas tombol selama proses berjalan. */
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

        @media (prefers-reduced-motion: reduce) {
            .ekspor[data-state="muat"] .ekspor-kilau { animation: none; opacity: .5; }
            .ekspor[data-state="sukses"] [data-ekspor-ikon="sukses"] { animation: none; }
        }

        /* Ikon filter berputar saat panel terbuka */
        #guru-page [data-filter-toggle] svg:first-child {
            transition: transform .3s cubic-bezier(.22, 1, .36, 1);
        }

        #guru-page [data-filter-toggle][data-open="true"] svg:first-child {
            transform: rotate(180deg);
        }

        /* ===== Modal tambah/ubah: muncul halus, isi ikut naik bertahap ===== */
        .gm {
            opacity: 0;
            pointer-events: none;
            transition: opacity .25s ease;
        }

        .gm[data-open="true"] {
            opacity: 1;
            pointer-events: auto;
        }

        .gm-backdrop {
            opacity: 0;
            transition: opacity .25s ease;
        }

        .gm[data-open="true"] .gm-backdrop {
            opacity: 1;
        }

        .gm-panel {
            opacity: 0;
            transform: translateY(16px) scale(.98);
            transition: opacity .28s cubic-bezier(.22, 1, .36, 1),
                        transform .28s cubic-bezier(.22, 1, .36, 1);
        }

        .gm[data-open="true"] .gm-panel {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        /* Di layar kecil panel muncul dari bawah seperti bottom sheet */
        @media (max-width: 639.98px) {
            .gm-panel {
                transform: translateY(100%);
            }
        }

        /* Isi formulir muncul satu per satu */
        @keyframes gmFieldIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: none; }
        }

        .gm[data-open="true"] #guru-form > * {
            animation: gmFieldIn .4s cubic-bezier(.22, 1, .36, 1) both;
            animation-delay: calc(var(--k, 0) * 50ms + 120ms);
        }

        /* Panah bawaan select disembunyikan (diganti ikon chevron) */
        #guru-form select { background-image: none; }

        /* ===== Popup konfirmasi simpan: muncul di atas modal formulir ===== */
        .gconfirm {
            opacity: 0;
            pointer-events: none;
            transition: opacity .3s ease;
        }

        .gconfirm[data-open="true"] {
            opacity: 1;
            pointer-events: auto;
        }

        .gconfirm-backdrop {
            opacity: 0;
            transition: opacity .3s ease;
        }

        .gconfirm[data-open="true"] .gconfirm-backdrop {
            opacity: 1;
        }

        .gconfirm-panel {
            opacity: 0;
            transform: translateY(20px) scale(.97);
            transition: opacity .3s cubic-bezier(.22, 1, .36, 1),
                        transform .3s cubic-bezier(.22, 1, .36, 1);
        }

        .gconfirm[data-open="true"] .gconfirm-panel {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        /* Cincin fokus saat pindah pilihan dengan keyboard */
        .gconfirm [data-key-nav].gconfirm-key-selected {
            outline: 2px solid #1E86FF;
            outline-offset: 2px;
        }

        /* ===== Baris tabel & kartu masuk dengan jeda bertahap ===== */
        .guru-row {
            animation: guruRowIn .4s cubic-bezier(.22, 1, .36, 1) both;
            animation-delay: calc(var(--i, 0) * 45ms);
            transition: background-color .2s ease;
        }

        .guru-row:hover { background-color: rgba(248, 250, 252, .9); }

        /* Saat mengetik di pencarian, baris berganti langsung agar tidak berkedip */
        [data-anim="off"] > .guru-row { animation: none; }

        @keyframes guruRowIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .guru-empty { animation: guruRowIn .4s cubic-bezier(.22, 1, .36, 1) both; }

        /* Tombol aksi: sedikit terangkat saat hover */
        .guru-act {
            transition: translate .2s ease, background-color .2s ease, color .2s ease, box-shadow .2s ease;
        }

        .guru-act:hover {
            translate: 0 -2px;
            box-shadow: 0 6px 12px -8px rgba(37, 99, 235, .5);
        }

        /* Baris yang barusan diubah/ditambah diberi cahaya singkat */
        .guru-row[data-flash="true"] {
            animation: guruRowFlash 1.1s ease-out;
        }

        @keyframes guruRowFlash {
            0%   { background-color: rgba(37, 99, 235, .16); }
            100% { background-color: transparent; }
        }

        .guru-row[data-flash="true"] td,
        .guru-row[data-flash="true"] > div {
            animation: guruCellPop .5s cubic-bezier(.22, 1, .36, 1);
        }

        @keyframes guruCellPop {
            0%   { transform: scale(1); }
            40%  { transform: scale(1.015); }
            100% { transform: scale(1); }
        }

        /* Angka statistik ikut membesar sebentar saat berubah */
        .guru-stat {
            transform-origin: left center;
        }

        .guru-stat[data-bump="true"] {
            animation: guruStatBump .45s cubic-bezier(.22, 1, .36, 1);
        }

        @keyframes guruStatBump {
            0%   { transform: scale(1); }
            35%  { transform: scale(1.12); color: #2563eb; }
            100% { transform: scale(1); }
        }

        @media (prefers-reduced-motion: reduce) {
            #guru-page.reveal-ready [data-reveal] {
                opacity: 1;
                transform: none;
                filter: none;
                transition: none;
            }

            #guru-add:hover svg,
            #guru-export:hover svg { animation: none; transform: none; }

            .ekspor[data-state="muat"] .ekspor-kilau { animation: none; opacity: .5; }
            .ekspor[data-state="sukses"] [data-ekspor-ikon="sukses"] { animation: none; }

            #guru-add:hover, #guru-export:hover, .guru-act:hover { translate: none; }

            .gconfirm, .gconfirm-backdrop, .gconfirm-panel { transition: none; }

            .gm, .gm-backdrop, .gm-panel, .guru-row, .guru-empty,
            .gm[data-open="true"] #guru-form > *,
            .guru-row[data-flash="true"] td,
            .guru-row[data-flash="true"] > div,
            .guru-stat[data-bump="true"] {
                transition: none;
                animation: none;
            }

            .gm[data-open="true"] .gm-panel,
            .guru-row {
                opacity: 1;
                transform: none;
            }
        }
    </style>

    <script>
        (function () {
            // ===== Data dari PHP =====
            const data = @json($guruData);
            let rows = data.guru.slice();
            const baseMapel = data.mapel.slice();
            const perPage = 5;

            // ===== Password acak =====
            // Set karakter & panjang diambil dari server (App\Support\Credentials) agar formatnya
            // sama persis dengan yang dibuat sistem, contoh "n6STpguX".
            const passwordCharsets = data.password_charsets || ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnpqrstuvwxyz', '23456789'];
            const passwordLength = Number(data.password_length || 8);

            const indexAcak = (n) => {
                if (window.crypto && window.crypto.getRandomValues) {
                    const buf = new Uint32Array(1);
                    window.crypto.getRandomValues(buf);
                    return buf[0] % n;
                }
                return Math.floor(Math.random() * n);
            };

            const charAcak = (set) => set[indexAcak(set.length)];

            // Menyalin algoritma server: wajib huruf besar, huruf kecil, dan angka,
            // lalu diacak urutannya supaya polanya tidak tertebak.
            const buatPassword = () => {
                const chars = passwordCharsets.map(charAcak);

                while (chars.length < passwordLength) {
                    chars.push(charAcak(passwordCharsets.join('')));
                }

                for (let i = chars.length - 1; i > 0; i--) {
                    const j = indexAcak(i + 1);
                    [chars[i], chars[j]] = [chars[j], chars[i]];
                }

                return chars.join('');
            };

            const root = document.getElementById('guru-page');
            const $ = (sel) => root.querySelector(sel);

            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            const esc = (v) => String(v).replace(/[&<>"']/g, (c) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[c]));

            const state = { q: '', status: '', mapel: '', page: 1 };

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
            const label     = pag.dataset.label || 'data';

            const searchEl = $('#guru-search');
            const statusEl = $('#guru-filter-status');
            const mapelEl  = $('#guru-filter-mapel');

            // ===== Animasi angka (count up), easing sama seperti section Tentang =====
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

                        // angka mulai berjalan setelah kartu tampil
                        const delay = (parseFloat(getComputedStyle(target).getPropertyValue('--d')) || 0) * 1000;
                        target.querySelectorAll('[data-count]').forEach((el) => {
                            setTimeout(() => countTo(el, 0, Number(el.dataset.count), 1800), delay + 300);
                        });
                    });
                }, { threshold: 0.1, rootMargin: '0px 0px -30px 0px' });

                root.querySelectorAll('[data-reveal]').forEach((el) => observer.observe(el));
            }

            // ===== Badge & ikon =====
            const badgeStatus = {
                'Aktif':    'bg-blue-100 text-blue-600',
                'Nonaktif': 'bg-red-100 text-red-500',
                'Cuti':     'bg-yellow-100 text-yellow-600',
            };
            const pill = (cls, text) =>
                `<span class="inline-block whitespace-nowrap rounded-full px-2.5 py-0.5 text-[11px] font-medium ${cls}">${esc(text)}</span>`;

            const ICON_EDIT = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>';
            const ICON_DEL  = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>';

            const actions = (g) => `
                <div class="inline-flex items-center gap-2">
                    <button type="button" data-action="edit" data-id="${g.id}" aria-label="Ubah ${esc(g.nama)}"
                        class="guru-act flex h-8 w-8 items-center justify-center rounded-lg border border-blue-300 bg-white text-primary-60 transition hover:bg-blue-50 active:scale-90">${ICON_EDIT}</button>
                    <button type="button" data-action="delete" data-id="${g.id}" aria-label="Hapus ${esc(g.nama)}"
                        class="guru-act flex h-8 w-8 items-center justify-center rounded-lg border border-blue-300 bg-white text-primary-60 transition hover:bg-red-50 hover:text-red-500 active:scale-90">${ICON_DEL}</button>
                </div>`;

            // ===== Template baris =====
            const rowTable = (g, no, i) => `
                <tr data-row-id="${g.id}" class="guru-row" style="--i:${i}">
                    <td class="px-4 py-5 text-left text-slate-500">${no}</td>
                    <td class="px-4 py-5">${esc(g.nip)}</td>
                    <td class="px-4 py-5 font-medium">${esc(g.nama)}</td>
                    <td class="px-4 py-5">
                        <a href="mailto:${esc(g.email || '')}"
                            class="inline-block max-w-[15rem] truncate align-middle text-primary-60 transition hover:text-primary-70 hover:underline"
                            title="${esc(g.email || '')}">${esc(g.email || '-')}</a>
                    </td>
                    <td class="px-4 py-5">${esc(g.mapel)}</td>
                    <td class="px-4 py-5">${pill(badgeStatus[g.status] || 'bg-slate-100 text-slate-500', g.status)}</td>
                    <td class="px-4 py-5">${actions(g)}</td>
                </tr>`;

            const rowCard = (g, i) => `
                <div data-row-id="${g.id}" class="guru-row" style="--i:${i}">
                    <div class="flex items-start justify-between gap-3 py-4">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800">${esc(g.nama)}</p>
                            <p class="mt-0.5 text-xs text-slate-500">${esc(g.nip)} · ${esc(g.mapel)}</p>
                            <p class="mt-0.5 break-all text-xs text-slate-400">${esc(g.email || '-')}</p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                ${pill(badgeStatus[g.status] || 'bg-slate-100 text-slate-500', g.status)}
                            </div>
                        </div>
                        <div class="shrink-0">${actions(g)}</div>
                    </div>
                </div>`;

            // ===== Filter =====
            const filtered = () => {
                const q = state.q.trim().toLowerCase();
                return rows.filter((r) =>
                    (!state.status || r.status === state.status)
                    && (!state.mapel || r.mapel === state.mapel)
                    && (!q || r.nama.toLowerCase().includes(q)
                              || String(r.nip).includes(q)
                              || (r.email || '').toLowerCase().includes(q)));
            };

            const fillSelect = (sel, values) => {
                const current = sel.value;
                [...sel.options].slice(1).forEach((o) => o.remove());
                values.forEach((v) => sel.add(new Option(v, v)));
                sel.value = values.includes(current) ? current : '';
            };

            const unique = (key) => [...new Set(rows.map((r) => r[key]))].sort();

            // Daftar mapel untuk dropdown formulir: master + yang sudah dipakai data
            const mapelChoices = () => [...new Set([...baseMapel, ...rows.map((r) => r.mapel)])].sort();

            // ===== Nomor halaman (dengan titik-titik bila banyak) =====
            const pageList = (cur, last) => {
                if (last <= 5) return Array.from({ length: last }, (_, i) => i + 1);
                const set = new Set([1, last, cur - 1, cur, cur + 1].filter((n) => n >= 1 && n <= last));
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

            // ===== Render =====
            // animate = false dipakai saat mengetik di pencarian supaya baris tidak berkedip
            function render(animate = true) {
                const list     = filtered();
                const total    = list.length;
                const lastPage = Math.max(1, Math.ceil(total / perPage));
                state.page     = Math.min(state.page, lastPage);

                const start = (state.page - 1) * perPage;
                const slice = list.slice(start, start + perPage);

                tableBody.dataset.anim = animate ? 'on' : 'off';
                cardBody.dataset.anim  = animate ? 'on' : 'off';

                if (!slice.length) {
                    const msg = 'Tidak ada data guru yang cocok';
                    tableBody.innerHTML = `<tr><td colspan="7" class="guru-empty px-4 py-12 text-center text-sm text-slate-400">${msg}</td></tr>`;
                    cardBody.innerHTML  = `<p class="guru-empty py-10 text-center text-sm text-slate-400">${msg}</p>`;
                } else {
                    tableBody.innerHTML = slice.map((g, i) => rowTable(g, start + i + 1, i)).join('');
                    cardBody.innerHTML  = slice.map((g, i) => rowCard(g, i)).join('');
                }

                // Baris hasil perubahan diberi FLASH, lalu atributnya dibuang
                if (flashId !== null) {
                    const done = () => { flashId = null; };
                    requestAnimationFrame(() => {
                        root.querySelectorAll(`[data-row-id="${flashId}"]`).forEach((el) => {
                            el.dataset.flash = 'true';
                            el.addEventListener('animationend', done, { once: true });
                        });
                        setTimeout(done, 1400);
                    });
                }

                const from = total === 0 ? 0 : start + 1;
                const to   = Math.min(state.page * perPage, total);
                info.textContent = `Menampilkan ${from}–${to} dari ${total} ${label}`;

                pagesEl.innerHTML = pageList(state.page, lastPage).map((n) => n === '...'
                    ? '<span class="px-1.5 text-slate-400">...</span>'
                    : `<button type="button" data-page="${n}" class="min-w-8 rounded-lg px-2.5 py-1.5 text-sm transition-colors duration-200 sm:min-w-0 sm:py-1 ${n === state.page ? ACTIVE : IDLE}">${n}</button>`
                ).join('');

                prevBtn.disabled = state.page === 1;
                nextBtn.disabled = state.page === lastPage;
            }

            // Angka statistik menghitung ke nilai baru + membesar sebentar
            function updateStats() {
                const set = (key, val) => {
                    const el = root.querySelector(`[data-stat="${key}"]`);
                    if (!el) return;

                    const prev = Number(el.dataset.count);
                    if (prev === val) return;

                    el.dataset.count = val;
                    countTo(el, Number(el.textContent) || prev, val, 800);

                    el.classList.add('guru-stat');
                    // Atribut dilepas lagi supaya animasi bisa diputar ulang
                    el.dataset.bump = 'false';
                    void el.offsetWidth;
                    el.dataset.bump = 'true';
                };
                set('total', rows.length);
                set('aktif', rows.filter((r) => r.status === 'Aktif').length);
                set('nonaktif', rows.filter((r) => r.status === 'Nonaktif').length);
                set('cuti', rows.filter((r) => r.status === 'Cuti').length);
            }

            function refreshFilters() {
                fillSelect(statusEl, unique('status'));
                fillSelect(mapelEl, unique('mapel'));
                state.status = statusEl.value;
                state.mapel  = mapelEl.value;
            }

            // ===== Event =====
            // Tombol filter + panel popup
            const filterRoot  = $('[data-filter-root]');
            const filterBtn   = $('[data-filter-toggle]');
            const filterPanel = $('[data-filter-panel]');
            const filterBadge = $('[data-filter-badge]');

            const setPanel = (open) => {
                filterPanel.dataset.open = open ? 'true' : 'false';
                filterPanel.setAttribute('aria-hidden', open ? 'false' : 'true');
                filterPanel.toggleAttribute('inert', !open);
                filterBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                filterBtn.dataset.open = open ? 'true' : 'false';
                if (open) requestAnimationFrame(() => statusEl?.focus());
                else {
                    document.body.style.removeProperty('overflow');
                    // Buang perubahan yang belum diterapkan saat panel ditutup
                    statusEl.value = state.status;
                    mapelEl.value  = state.mapel;
                }
            };

            const syncBadge = () => {
                const n = [state.status, state.mapel, state.q].filter(Boolean).length;
                filterBadge.classList.toggle('hidden', n === 0);
                filterBadge.textContent = n;
            };

            filterBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const open = filterPanel.dataset.open !== 'true';
                setPanel(open);
                if (open) document.body.style.overflow = 'hidden';
            });

            filterPanel.querySelector('[data-filter-done]').addEventListener('click', () => {
                // Terapkan pilihan baru ke data
                Object.assign(state, { status: statusEl.value, mapel: mapelEl.value, page: 1 });
                syncBadge();
                render();
                setPanel(false);
            });

            filterPanel.querySelector('[data-filter-reset]').addEventListener('click', () => {
                // Reset langsung: semua filter & pencarian dikosongkan tanpa perlu menekan "Terapkan".
                searchEl.value = '';
                statusEl.value = mapelEl.value = '';
                Object.assign(state, { q: '', status: '', mapel: '', page: 1 });
                syncClear();
                syncBadge();
                render();
            });

            document.addEventListener('click', (e) => {
                if (filterPanel.dataset.open !== 'true') return;
                if (filterPanel.contains(e.target) || filterBtn.contains(e.target)) return;
                setPanel(false);
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && filterPanel.dataset.open === 'true') setPanel(false);
            });

            // Tombol X custom untuk mencari (X bawaan browser disembunyikan)
            const clearBtn = document.getElementById('guru-search-clear');
            const syncClear = () => clearBtn.classList.toggle('hidden', searchEl.value.length === 0);

            searchEl.addEventListener('input', () => {
                state.q = searchEl.value; state.page = 1;
                syncClear();
                syncBadge();
                render(false);
            });

            clearBtn.addEventListener('click', () => {
                searchEl.value = '';
                state.q = '';
                state.page = 1;
                syncClear();
                syncBadge();
                render(false);
                searchEl.focus();
            });

            // Filter disimpan sebagai draf dan diterapkan saat "Terapkan"; pencarian (state.q) langsung.

            prevBtn.addEventListener('click', () => { state.page--; render(); });
            nextBtn.addEventListener('click', () => { state.page++; render(); });
            pagesEl.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-page]');
                if (btn) { state.page = Number(btn.dataset.page); render(); }
            });

            // ===== Modal tambah / ubah =====
            const modal      = document.getElementById('guru-modal');
            const form       = document.getElementById('guru-form');
            const titleEl    = modal.querySelector('[data-modal-title]');
            const subtitle   = modal.querySelector('[data-modal-subtitle]');
            const errorEl    = modal.querySelector('[data-modal-error]');
            const saveBtn    = modal.querySelector('[data-modal-save]');
            const saveText   = modal.querySelector('[data-modal-save-text]');
            const pwHint     = modal.querySelector('[data-password-hint]');
            const pwToggle   = modal.querySelector('[data-toggle-password]');
            const pwGen      = modal.querySelector('[data-generate-password]');
            const pwField    = modal.querySelector('[data-field-password]');
            const pwCopy     = modal.querySelector('[data-copy-password]');
            const pwCopyIcon = pwCopy.querySelector('[data-copy-icon]');
            const pwCopyOk   = pwCopy.querySelector('[data-copy-ok]');
            const confirm     = document.getElementById('guru-confirm-modal');
            const f = {
                nis:      document.getElementById('gm-nis'),
                nama:     document.getElementById('gm-nama'),
                mapel:    document.getElementById('gm-mapel'),
                email:    document.getElementById('gm-email'),
                password: document.getElementById('gm-password'),
                status:   document.getElementById('gm-status'),
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

            // Tampil/sembunyikan password
            const setPasswordVisible = (show) => {
                f.password.type = show ? 'text' : 'password';
                pwToggle.setAttribute('aria-pressed', show ? 'true' : 'false');
                pwToggle.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
                pwToggle.querySelector('[data-eye-on]').classList.toggle('hidden', show);
                pwToggle.querySelector('[data-eye-off]').classList.toggle('hidden', !show);
            };
            pwToggle.addEventListener('click', () => setPasswordVisible(f.password.type === 'password'));

            // Tombol tongkat ajaib: buat ulang password di kolom preview.
            pwGen.addEventListener('click', () => {
                f.password.value = buatPassword();
                setPasswordVisible(true);
                f.password.focus();
            });

            // Tombol salin: fallback ke cara lama bila clipboard API tidak tersedia.
            const copyPassword = (value) => {
                const flash = () => {
                    pwCopyIcon.classList.add('hidden');
                    pwCopyOk.classList.remove('hidden');
                    setTimeout(() => {
                        pwCopyIcon.classList.remove('hidden');
                        pwCopyOk.classList.add('hidden');
                    }, 1200);
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(value).then(flash).catch(() => fallback());
                    return;
                }

                fallback();

                function fallback() {
                    const ta = document.createElement('textarea');
                    ta.value = value;
                    ta.setAttribute('readonly', '');
                    ta.style.cssText = 'position:fixed;opacity:0';
                    document.body.appendChild(ta);
                    ta.select();
                    try { document.execCommand('copy'); flash(); } catch (e) { /* batal */ }
                    document.body.removeChild(ta);
                }
            };
            pwCopy.addEventListener('click', () => {
                if (f.password.value) copyPassword(f.password.value);
            });

            // Isi dropdown mata pelajaran; nilai terpilih dipertahankan
            const fillMapelSelect = (selected = '') => {
                f.mapel.innerHTML = '<option value="" disabled>Pilih mata pelajaran</option>'
                    + mapelChoices().map((v) => `<option value="${esc(v)}">${esc(v)}</option>`).join('');
                f.mapel.value = selected;
            };

            // g = null → mode tambah, g = objek guru → mode ubah
            const openModal = (g) => {
                mode        = g ? 'edit' : 'add';
                editingId   = g ? g.id : null;
                lastFocused = document.activeElement;

                form.reset();
                setPasswordVisible(false);
                showError('');
                setBusy(false);

                if (g) {
                    pwField.classList.add('hidden');

                    titleEl.textContent  = 'Ubah Data Guru';
                    subtitle.textContent = `Perbarui data ${g.nama}`;

                    f.nis.value    = g.nip ?? '';
                    f.nama.value   = g.nama;
                    f.email.value  = g.email || '';
                    f.status.value = g.status;
                    fillMapelSelect(g.mapel);
                } else {
                    pwField.classList.remove('hidden');
                    f.password.value = buatPassword();
                    setPasswordVisible(true);

                    titleEl.textContent  = 'Tambah Guru';
                    subtitle.textContent = 'Lengkapi data guru yang akan ditambahkan';
                    pwHint.textContent = 'Password dibuat otomatis oleh sistem dan ikut muncul di export Excel.';
                    pwHint.classList.remove('hidden');

                    f.status.value = 'Aktif';
                    fillMapelSelect('');
                }

                // Urutan muncul isi formulir (dipakai animasi bertahap di CSS)
                [...form.children].forEach((el, i) => el.style.setProperty('--k', i));

                modal.removeAttribute('inert');
                modal.setAttribute('aria-hidden', 'false');
                requestAnimationFrame(() => { modal.dataset.open = 'true'; });
                document.body.classList.add('overflow-hidden');
                setTimeout(() => (g ? f.nama : f.nis).focus(), 260);
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

            $('#guru-add').addEventListener('click', () => openModal(null));

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

            /* ---------- Popup konfirmasi simpan ---------- */
            const confirmTitle = confirm.querySelector('[data-confirm-title]');
            const confirmText  = confirm.querySelector('[data-confirm-text]');
            const confirmNo    = confirm.querySelector('[data-confirm-no]');
            const confirmYes   = confirm.querySelector('[data-confirm-yes]');
            const confirmBtns  = [confirmNo, confirmYes];
            let confirmNavIndex = 1;          // default fokus = "Ya, Simpan"
            let confirmReturnFocus = null;
            let pendingSave = null;           // data yang menunggu konfirmasi

            const setConfirmNavIndex = (index, moveFocus = true) => {
                confirmNavIndex = index;
                confirmBtns.forEach((btn, i) => btn.classList.toggle('gconfirm-key-selected', i === index));
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

            // Panah kiri/kanan/atas/bawah memindah fokus; Enter mengonfirmasi; Escape membatalkan
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

            /* ---------- Loading screen ---------- */
            const showLoading = (text) => {
                const loading = document.getElementById('site-loading');
                if (!loading) return null;
                const lt = loading.querySelector('[data-loading-text]');
                if (lt) lt.textContent = text;
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

            /* ---------- Simpan ke server ---------- */
            const baseUrl = {!! json_encode(route('dashboard.admin.data-guru.index')) !!};
            const urlFor = (id) => (id ? baseUrl + '/' + id : baseUrl);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

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

            const saveRow = async (payload) => {
                const isAdd = mode === 'add';
                const loading = showLoading(isAdd ? 'Menyimpan data guru...' : 'Memperbarui data guru...');

                setBusy(true);
                showError('');

                try {
                    const res = await jsonRequest(urlFor(isAdd ? null : editingId), isAdd ? 'POST' : 'PUT', payload);

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

                    const saved = result.data.guru;
                    const index = rows.findIndex((r) => r.id === saved.id);

                    if (index >= 0) rows[index] = saved;
                    else rows.push(saved);

                    rows.sort((a, b) => String(a.nip).localeCompare(String(b.nip)) || a.nama.localeCompare(b.nama));

                    flashId = saved.id;

                    // Bersihkan pencarian & filter supaya baris baru pasti terlihat
                    if (isAdd) {
                        searchEl.value = '';
                        statusEl.value = mapelEl.value = '';
                        Object.assign(state, { q: '', status: '', mapel: '' });
                        syncClear();
                        state.page = Math.ceil(rows.length / perPage);
                    }

                    refreshFilters();
                    syncBadge();
                    updateStats();
                    render();
                    setBusy(false);

                    // Formulir ditutup setelah data tersimpan di server
                    confirmReturnFocus = null;
                    closeConfirm();
                    closeModal();

                    hideLoading(loading);

                    toast((isAdd ? `${saved.nama} berhasil ditambahkan.` : `${saved.nama} berhasil diperbarui.`)
                        + (result.data.password ? ` Password awal: ${result.data.password}` : ''));
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
                const g     = isAdd ? null : rows.find((r) => r.id === editingId);
                if (!isAdd && !g) return;

                const nis      = f.nis.value.trim();
                const nama     = f.nama.value.trim();
                const mapel    = f.mapel.value.trim();
                const email    = f.email.value.trim().toLowerCase();
                const password = f.password.value;

                const missing = !nis ? f.nis : !nama ? f.nama : !mapel ? f.mapel : !email ? f.email : null;
                if (missing) {
                    showError('NIP, nama, mata pelajaran, dan email wajib diisi.');
                    missing.focus();
                    return;
                }

                // Pola email sederhana: ada tepat satu "@", ada dominio, ada titik setelahnya
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
                    showError('Format email belum benar, contoh: nama@saskansa.sch.id');
                    f.email.focus();
                    return;
                }

                const selfId = isAdd ? null : editingId;

                const dupNis = rows.find((r) => r.nip === nis && r.id !== selfId);
                if (dupNis) {
                    showError(`NIP ${nis} sudah dipakai oleh ${dupNis.nama}.`);
                    f.nis.focus();
                    return;
                }

                const dupEmail = rows.find((r) => (r.email || '').toLowerCase() === email && r.id !== selfId);
                if (dupEmail) {
                    showError(`Email ${email} sudah dipakai oleh ${dupEmail.nama}.`);
                    f.email.focus();
                    return;
                }

                // Validasi lolos, simpan dulu lalu minta konfirmasi
                pendingSave = {
                    payload: { nis, nama, mapel, email, status: f.status.value, password },
                };

                openConfirm(isAdd
                    ? { title: 'Yakin ingin menambahkan data?', text: `Data ${nama} akan ditambahkan ke daftar guru.` }
                    : { title: 'Yakin ingin menyimpan perubahan?', text: `Perubahan data ${nama} akan disimpan.` });
            });

            root.addEventListener('click', async (e) => {
                const btn = e.target.closest('[data-action]');
                if (!btn) return;

                const id = Number(btn.dataset.id);
                const g  = rows.find((r) => r.id === id);
                if (!g) return;

                if (btn.dataset.action === 'edit') {
                    openModal(g);
                    return;
                }

                // Hapus data guru
                let ok;
                if (window.Swal) {
                    ok = (await Swal.fire({
                        icon: 'warning',
                        title: 'Hapus data guru?',
                        text: `${g.nama} akan dihapus dari daftar.`,
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Hapus',
                        cancelButtonText: 'Batal',
                    })).isConfirmed;
                } else {
                    ok = confirm(`Hapus data ${g.nama}?`);
                }
                if (!ok) return;

                const loading = showLoading('Menghapus data guru...');

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
                    syncBadge();
                    updateStats();
                    render();

                    hideLoading(loading);
                    toast(`Data ${g.nama} berhasil dihapus.`);
                } catch (e) {
                    hideLoading(loading);
                    alertError('Gagal Menghapus', 'Tidak dapat terhubung ke server. Silakan coba lagi.');
                }
            });

            // Export lewat fetch (bukan navigasi biasa) supaya tombol bisa menampilkan proses
            // dan tahu file sudah benar-benar diterima.
            const exportUrl = {!! json_encode(route('dashboard.admin.data-guru.export')) !!};

            const tombolExport = $('#guru-export');
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

            // Nama file diambil dari header server agar sama persis dengan nama yang disiapkan server.
            const namaBerkas = (disposisi) => {
                const cocok = /filename="?([^";]+)"?/i.exec(disposisi || '');

                return cocok ? cocok[1] : 'data-guru.xlsx';
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

                for (const key of ['q', 'status', 'mapel']) {
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

                    const total = Number(respons.headers.get('X-Total-Guru') || 0);

                    unduhBlob(await respons.blob(), namaBerkas(respons.headers.get('Content-Disposition')));

                    setExportState('sukses', total > 0 ? `${total} guru diunduh` : 'Tidak ada guru');
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
                syncClear();
            }
            refreshFilters();
            render();
            syncBadge();
            initReveal();
        })();
    </script>
@endsection