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
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" id="guru-add"
                            class="inline-flex h-10 items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-primary-60 px-4 text-sm font-semibold text-white transition hover:bg-primary-70 sm:h-9">
                            <x-lucide-plus class="h-4 w-4" />
                            Tambah Guru
                        </button>
                        <button type="button" id="guru-penugasan"
                            class="inline-flex h-10 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-blue-300 bg-white px-4 text-sm font-medium text-primary-60 transition hover:bg-blue-50 sm:h-9">
                            <x-lucide-clipboard-list class="h-4 w-4" />
                            Penugasan Mengajar
                        </button>
                        <button type="button" id="guru-export" data-state="idle"
                            aria-live="polite"
                            title="Unduh data guru yang sedang difilter"
                            class="ekspor relative inline-flex h-10 items-center justify-center gap-2 overflow-hidden whitespace-nowrap rounded-lg border border-blue-300 bg-white px-4 text-xs font-medium text-primary-60 transition hover:bg-blue-50 sm:h-9 sm:text-sm">
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
                            <th class="px-4 py-4 text-left font-medium">Kelas Diampu</th>
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

                <input type="hidden" id="gm-mapel" name="mapel" value="">

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

                <div class="sm:col-span-2 rounded-xl border border-slate-200 bg-white p-4" data-guru-penugasan-section>
                    <div class="mb-3">
                        <p class="text-sm font-semibold text-primary-70">Penugasan Mengajar</p>
                        <p class="mt-0.5 text-xs text-slate-500">Pilih 1 mata pelajaran dan minimal 1 kelas yang diampu guru.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="gm-penugasan-mapel" class="{{ $gLabel }}">Mata Pelajaran</label>
                            <div class="relative">
                                <x-lucide-book-open class="{{ $gIcon }}" />
                                <select id="gm-penugasan-mapel" data-guru-penugasan-mapel class="{{ $gField }} appearance-none pr-10 text-slate-800">
                                    <option value="">Pilih mata pelajaran</option>
                                </select>
                                <x-lucide-chevron-down class="pointer-events-none absolute right-3 top-1/2 h-5 w-5 -translate-y-1/2 text-primary-60" />
                            </div>
                            <p data-guru-penugasan-mapel-empty class="mt-1 hidden text-xs text-amber-600">Data mata pelajaran belum tersedia. Tambahkan lewat menu Data Mata Pelajaran.</p>
                        </div>
                        <div>
                            <div class="mb-1.5 flex items-center justify-between gap-2">
                                <p class="{{ $gLabel }} mb-0">Kelas Diampu</p>
                                <span data-guru-kelas-count class="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-primary-60">0 dipilih</span>
                            </div>
                            <div data-guru-kelas-filter class="mb-2 grid grid-cols-4 gap-1 rounded-lg border border-slate-200 bg-slate-100 p-1">
                                <button type="button" data-filter="semua" class="rounded-md bg-white px-2 py-1.5 text-[11px] font-semibold text-primary-70 shadow-sm">Semua</button>
                                <button type="button" data-filter="10" class="rounded-md px-2 py-1.5 text-[11px] font-semibold text-slate-500 hover:text-primary-70">Kelas 10</button>
                                <button type="button" data-filter="11" class="rounded-md px-2 py-1.5 text-[11px] font-semibold text-slate-500 hover:text-primary-70">Kelas 11</button>
                                <button type="button" data-filter="12" class="rounded-md px-2 py-1.5 text-[11px] font-semibold text-slate-500 hover:text-primary-70">Kelas 12</button>
                            </div>
                            <div data-guru-penugasan-kelas class="grid max-h-52 grid-cols-2 gap-2 overflow-y-auto pr-1"></div>
                            <p data-guru-penugasan-kelas-empty class="mt-1 hidden text-xs text-amber-600">Data kelas belum tersedia. Tambahkan lewat menu Data Kelas.</p>
                            <div class="mt-3 border-t border-slate-100 pt-3">
                                <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Kelas Terpilih</p>
                                <div data-guru-kelas-selected class="flex min-h-7 flex-wrap gap-1.5"></div>
                            </div>
                        </div>
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

    {{-- ============ Modal Penugasan Mengajar (Keseluruhan) ============ --}}
    <div
        id="penugasan-overview-modal"
        data-open="false"
        aria-hidden="true"
        inert
        class="gm fixed inset-0 z-60 flex items-end justify-center overflow-hidden overscroll-contain bg-[#0F3D73]/60 p-0 sm:items-center sm:p-4 lg:left-64"
        role="dialog"
        aria-modal="true"
        aria-labelledby="penugasan-overview-title"
    >
        <div data-penugasan-overview-backdrop class="gm-backdrop absolute inset-0"></div>

        <div class="gm-panel relative z-10 flex max-h-[92dvh] w-full max-w-4xl flex-col overflow-hidden rounded-t-3xl bg-[#FAFAFA] shadow-2xl sm:max-h-[88dvh] sm:rounded-2xl">
            <header class="flex shrink-0 items-start gap-3 px-6 pb-2 pt-6 sm:px-8 sm:pt-7">
                <div class="min-w-0 flex-1">
                    <h3 id="penugasan-overview-title" class="text-2xl font-bold leading-tight text-primary-70">Penugasan Mengajar</h3>
                    <p class="mt-1 text-sm text-primary-60">Kelola penugasan mengajar seluruh guru</p>
                </div>
                <button type="button" data-penugasan-overview-tutup aria-label="Tutup"
                    class="gm-close -mr-2 -mt-1 shrink-0 rounded-lg p-1.5 text-primary-60 transition hover:bg-blue-50 active:scale-90">
                    <x-lucide-x class="h-6 w-6" />
                </button>
            </header>

            <div class="shrink-0 border-b border-slate-200 bg-[#FAFAFA] px-6 pb-3 pt-1 sm:px-8">
                <div class="relative">
                    <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input type="search" id="penugasan-overview-search" data-penugasan-overview-search
                        aria-label="Cari guru pada penugasan mengajar"
                        placeholder="Cari nama, NIP, email, mapel, atau kelas" autocomplete="off"
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white pl-10 pr-10 text-base text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20 sm:h-9 sm:text-sm [&::-webkit-search-cancel-button]:appearance-none [&::-webkit-search-decoration]:appearance-none">
                    <button type="button" data-penugasan-overview-search-clear aria-label="Hapus kata pencarian"
                        class="absolute right-1.5 top-1/2 hidden -translate-y-1/2 items-center justify-center rounded-lg p-1.5 text-slate-400 transition-all duration-200 ease-out hover:bg-slate-400/15 hover:text-slate-600 focus-visible:outline-2 focus-visible:outline-blue-500 active:scale-90">
                        <x-lucide-x class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div data-penugasan-overview-scroll
                class="penugasan-overview-scroll min-h-0 flex-1 overflow-y-auto overscroll-contain px-6 pb-5 pt-4 sm:px-8">
                <div data-penugasan-overview-list class="space-y-3"></div>
            </div>

            <footer class="mx-6 flex shrink-0 justify-end border-t border-slate-300 bg-[#FAFAFA] py-4 sm:mx-8">
                <button type="button" data-penugasan-overview-tutup
                    class="gm-close inline-flex h-11 items-center justify-center rounded-md border border-primary-60 bg-white px-5 text-sm font-medium text-primary-70 transition hover:bg-blue-50 active:scale-[.98]">
                    Tutup
                </button>
            </footer>
        </div>
    </div>

    {{-- ============ Modal Kelola Penugasan Mengajar ============ --}}
    <div
        id="penugasan-modal"
        data-open="false"
        aria-hidden="true"
        inert
        class="gm fixed inset-0 z-60 flex items-end justify-center overflow-y-auto overscroll-contain bg-[#0F3D73]/60 p-0 sm:items-center sm:p-4 lg:left-64"
        role="dialog"
        aria-modal="true"
        aria-labelledby="penugasan-modal-title"
    >
        <div data-penugasan-backdrop class="gm-backdrop absolute inset-0"></div>

        <div class="gm-panel relative z-10 flex max-h-[92dvh] w-full max-w-3xl flex-col rounded-t-3xl bg-[#FAFAFA] shadow-2xl sm:max-h-[88dvh] sm:rounded-2xl">
            <header class="flex shrink-0 items-start gap-3 px-6 pb-2 pt-6 sm:px-8 sm:pt-7">
                <div class="min-w-0 flex-1">
                    <h3 id="penugasan-modal-title" class="text-2xl font-bold leading-tight text-primary-70">Kelola Penugasan Mengajar</h3>
                    <p data-penugasan-subtitle class="mt-1 text-sm text-primary-60">Atur mata pelajaran dan kelas yang diajar guru</p>
                </div>
                <button type="button" data-penugasan-tutup aria-label="Tutup"
                    class="gm-close -mr-2 -mt-1 shrink-0 rounded-lg p-1.5 text-primary-60 transition hover:bg-blue-50 active:scale-90">
                    <x-lucide-x class="h-6 w-6" />
                </button>
            </header>

            <form id="penugasan-form" class="grid min-h-0 flex-1 content-start gap-3 overflow-y-auto px-6 py-3 sm:px-8 sm:py-4" novalidate>
                <div data-penugasan-loading class="flex items-center gap-2 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-primary-70">
                    <x-lucide-loader-circle class="h-4 w-4 animate-spin" />
                    Memuat penugasan...
                </div>

                <div data-penugasan-empty class="hidden rounded-xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center">
                    <p class="text-sm font-medium text-slate-700">Belum ada penugasan mengajar</p>
                    <p class="mt-1 text-xs text-slate-500">Pilih kelas yang diajar untuk setiap mata pelajaran.</p>
                </div>

                <div data-penugasan-tanpa-mapel class="hidden rounded-xl border border-dashed border-slate-300 bg-white px-4 py-6 text-center">
                    <p class="text-sm font-medium text-slate-700">Mata pelajaran belum tersedia</p>
                    <p class="mt-1 text-xs text-slate-500">Perbarui Mata Pelajaran pada data guru terlebih dahulu.</p>
                </div>

                <div data-penugasan-list class="grid gap-3"></div>

                <p data-penugasan-error class="hidden rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs text-red-600"></p>
            </form>

            <footer class="mx-6 flex shrink-0 items-center justify-end gap-3 border-t border-slate-300 py-4 sm:mx-8">
                <button type="button" data-penugasan-batal
                    class="gm-close inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-md border border-primary-60 bg-white px-5 text-sm font-medium text-primary-70 transition hover:bg-blue-50 active:scale-[.98] sm:flex-none">
                    <x-lucide-x class="h-4 w-4" />
                    Batal
                </button>
                <button type="submit" form="penugasan-form" data-penugasan-simpan
                    class="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-md bg-primary-60 px-6 text-sm font-medium text-white shadow-md shadow-primary-60/25 transition hover:bg-primary-70 active:scale-[.98] disabled:opacity-60 sm:flex-none">
                    <x-lucide-save class="h-4 w-4" />
                    Simpan Penugasan
                </button>
            </footer>
        </div>
    </div>

    {{-- ============ Modal Detail Kelas yang Diampu ============ --}}
    <div
        id="kelas-detail-modal"
        data-open="false"
        aria-hidden="true"
        inert
        class="gm fixed inset-0 z-60 flex items-end justify-center overflow-y-auto overscroll-contain bg-[#0F3D73]/60 p-0 sm:items-center sm:p-4 lg:left-64"
        role="dialog"
        aria-modal="true"
        aria-labelledby="kelas-detail-title"
    >
        <div data-kelas-detail-backdrop class="gm-backdrop absolute inset-0"></div>

        <div class="gm-panel relative z-10 flex max-h-[92dvh] w-full max-w-xl flex-col rounded-t-3xl bg-[#FAFAFA] shadow-2xl sm:max-h-[88dvh] sm:rounded-2xl">
            <header class="flex shrink-0 items-start gap-3 px-6 pb-3 pt-6 sm:px-8 sm:pt-7">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-primary-60">
                    <x-lucide-school class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <h3 id="kelas-detail-title" class="text-xl font-bold leading-tight text-primary-70 sm:text-2xl">Detail Kelas yang Diampu</h3>
                    <p data-kelas-detail-subtitle class="mt-1 text-xs leading-relaxed text-slate-500 sm:text-sm">Ringkasan penugasan mengajar guru.</p>
                </div>
                <button type="button" data-kelas-detail-tutup aria-label="Tutup"
                    class="-mr-2 -mt-1 shrink-0 rounded-lg p-1.5 text-primary-60 transition hover:bg-blue-50 active:scale-90">
                    <x-lucide-x class="h-6 w-6" />
                </button>
            </header>

            <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4 sm:px-8">
                <div class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Nama Guru</p>
                        <p data-kelas-detail-nama class="mt-0.5 text-sm font-semibold text-slate-800">-</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">NIP</p>
                        <p data-kelas-detail-nip class="mt-0.5 text-sm font-medium text-slate-700">-</p>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Email</p>
                        <p data-kelas-detail-email class="mt-0.5 break-all text-sm text-slate-700">-</p>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Mata Pelajaran</p>
                        <p data-kelas-detail-mapel class="mt-0.5 text-sm text-slate-700">-</p>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Penugasan per Mata Pelajaran</p>
                    <p data-kelas-detail-total class="text-xs font-medium text-slate-500">0 kelas</p>
                </div>

                <div data-kelas-detail-list class="mt-3 space-y-3"></div>
            </div>

            <footer class="mx-6 flex shrink-0 justify-end border-t border-slate-300 py-4 sm:mx-8">
                <button type="button" data-kelas-detail-tutup
                    class="inline-flex h-11 flex-1 items-center justify-center rounded-md border border-primary-60 bg-white px-5 text-sm font-medium text-primary-70 transition hover:bg-blue-50 active:scale-[.98] sm:flex-none">
                    Tutup
                </button>
            </footer>
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
        #guru-add, #guru-penugasan, #guru-export {
            transition: transform .25s ease, box-shadow .25s ease,
                        background-color .2s ease, border-color .2s ease;
        }

        #guru-add:hover, #guru-penugasan:hover, #guru-export:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px -4px rgba(30, 134, 255, .35);
        }

        #guru-add svg, #guru-penugasan svg, #guru-export svg { transition: transform .3s cubic-bezier(.22, 1, .36, 1); }
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

        #penugasan-tooltip {
            position: fixed;
            z-index: 90;
            max-width: 14rem;
            border-radius: .375rem;
            background: #1e293b;
            padding: .35rem .6rem;
            color: #fff;
            font-size: .75rem;
            font-weight: 500;
            line-height: 1.1;
            box-shadow: 0 6px 16px rgba(15, 23, 42, .18);
            opacity: 0;
            pointer-events: none;
            transform: translateY(4px);
            transition: opacity .18s ease-out, transform .18s ease-out;
            white-space: nowrap;
        }

        #penugasan-tooltip[data-visible="true"] {
            opacity: 1;
            transform: translateY(0);
        }

        .penugasan-kelas-popover {
            opacity: 0;
            transform: translateY(4px) scale(.98);
            transition: opacity .18s ease-out, transform .18s ease-out;
        }

        .penugasan-kelas-popover[data-visible="true"] {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        @media (prefers-reduced-motion: reduce) {
            .ekspor[data-state="muat"] .ekspor-kilau { animation: none; opacity: .5; }
            .ekspor[data-state="sukses"] [data-ekspor-ikon="sukses"] { animation: none; }
            #penugasan-tooltip,
            .penugasan-kelas-popover { transition: none; }
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

        .penugasan-kelas-scroll,
        .penugasan-overview-scroll,
        .kelas-popover-scroll {
            scrollbar-width: thin;
            scrollbar-color: #CBD5E1 transparent;
        }

        .penugasan-kelas-scroll::-webkit-scrollbar,
        .penugasan-overview-scroll::-webkit-scrollbar,
        .kelas-popover-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .penugasan-kelas-scroll::-webkit-scrollbar-thumb,
        .penugasan-overview-scroll::-webkit-scrollbar-thumb,
        .kelas-popover-scroll::-webkit-scrollbar-thumb {
            background-color: #CBD5E1;
            border-radius: 9999px;
        }

        .penugasan-kelas-card {
            cursor: pointer;
            transition: border-color .18s ease, background-color .18s ease,
                        box-shadow .18s ease, transform .18s ease;
        }

        .penugasan-kelas-card:hover {
            border-color: #BFDBFE;
            background-color: #F8FAFF;
        }

        .penugasan-kelas-card:focus-visible {
            outline: 2px solid #1E86FF;
            outline-offset: 2px;
        }

        .penugasan-kelas-card.is-selected {
            border-color: #34D399;
            background-color: #ECFDF5;
            box-shadow: 0 10px 24px -18px rgba(5, 150, 105, .65);
        }

        .penugasan-kelas-card.is-selected:hover {
            border-color: #10B981;
            background-color: #ECFDF5;
        }

        .penugasan-kelas-card.is-selected .penugasan-kelas-card-icon {
            background-color: #D1FAE5;
            color: #047857;
        }

        .penugasan-kelas-card .penugasan-kelas-card-indicator {
            border: 2px solid #CBD5E1;
            background-color: #FFFFFF;
            color: transparent;
            transition: border-color .18s ease, background-color .18s ease,
                        color .18s ease, transform .18s ease;
        }

        .penugasan-kelas-card.is-selected .penugasan-kelas-card-indicator {
            border-color: #10B981;
            background-color: #10B981;
            color: #FFFFFF;
            transform: scale(1.05);
        }

        .penugasan-filter-btn {
            color: #64748B;
            background-color: transparent;
        }

        .penugasan-filter-btn:hover {
            color: #334155;
            background-color: rgba(255, 255, 255, .7);
        }

        .penugasan-filter-btn[aria-selected="true"] {
            background-color: #1E86FF;
            color: #FFFFFF;
            box-shadow: 0 1px 2px rgba(30, 134, 255, .25);
        }

        .penugasan-filter-btn:focus-visible {
            outline: 2px solid #1E86FF;
            outline-offset: 1px;
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
            #guru-penugasan:hover svg,
            #guru-export:hover svg { animation: none; transform: none; }

            .ekspor[data-state="muat"] .ekspor-kilau { animation: none; opacity: .5; }
            .ekspor[data-state="sukses"] [data-ekspor-ikon="sukses"] { animation: none; }

            #guru-add:hover, #guru-penugasan:hover, #guru-export:hover, .guru-act:hover { translate: none; }

            .gconfirm, .gconfirm-backdrop, .gconfirm-panel { transition: none; }

            .gm, .gm-backdrop, .gm-panel, .guru-row, .guru-empty,
            .penugasan-kelas-card, .penugasan-kelas-card .penugasan-kelas-card-indicator,
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

            const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({
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

            const ICON_LAYERS = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0 text-primary-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 12.17-9.17 4.16a2 2 0 0 1-1.66 0L2 12.17"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/></svg>';
            const ICON_EYE   = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>';
            const ICON_EDIT = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>';
            const ICON_DEL  = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>';
            const ICON_PENUGASAN = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M9 12h6"/><path d="M9 16h6"/></svg>';
            const ICON_LOCK = '<svg xmlns="http://www.w3.org/2000/svg" class="lucide lucide-lock pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
            const ICON_SEARCH_X = '<svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-8 w-8 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="m9 9 6 6"/><path d="m15 9-6 6"/></svg>';
            const ICON_ROTATE = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>';

            const actions = (g) => `
                <div class="inline-flex items-center gap-2">
                    <button type="button" data-action="detail-kelas" data-id="${g.id}" aria-label="Lihat Detail Guru ${esc(g.nama)}" title="Lihat Detail Guru"
                        class="guru-act flex h-8 w-8 items-center justify-center rounded-lg border border-blue-300 bg-white text-primary-60 transition hover:bg-blue-50 active:scale-90">${ICON_EYE}</button>
                    <button type="button" data-action="penugasan" data-id="${g.id}" aria-label="Penugasan Mengajar ${esc(g.nama)}" title="Penugasan Mengajar"
                        class="guru-act flex h-8 w-8 items-center justify-center rounded-lg border border-blue-300 bg-white text-primary-60 transition hover:bg-blue-50 active:scale-90">${ICON_PENUGASAN}</button>
                    <button type="button" data-action="edit" data-id="${g.id}" aria-label="Ubah ${esc(g.nama)}" title="Ubah ${esc(g.nama)}"
                        class="guru-act flex h-8 w-8 items-center justify-center rounded-lg border border-blue-300 bg-white text-primary-60 transition hover:bg-blue-50 active:scale-90">${ICON_EDIT}</button>
                    <button type="button" data-action="delete" data-id="${g.id}" aria-label="Hapus ${esc(g.nama)}" title="Hapus ${esc(g.nama)}"
                        class="guru-act flex h-8 w-8 items-center justify-center rounded-lg border border-red-300 bg-white text-red-500 transition hover:bg-red-50 active:scale-90">${ICON_DEL}</button>
                </div>`;

            const kelasRingkas = (g) => {
                const daftar = Array.isArray(g.kelas_diampu) ? g.kelas_diampu : [];

                if (daftar.length === 0) {
                    return '<span class="text-slate-400">—</span>';
                }

                const badge = (label, judul = '') =>
                    `<span class="inline-flex items-center rounded-full border border-blue-100 bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-primary-70"${judul ? ` title="${esc(judul)}"` : ''}>${esc(label)}</span>`;
                const sisa = daftar.length - 2;
                const badgeKelas = daftar.slice(0, 2)
                    .map((kelas) => badge(kelas.nama, kelas.jurusan))
                    .join('');

                return `
                    <div class="flex flex-col items-start gap-1">
                        <div class="flex flex-wrap gap-1">
                            ${badgeKelas}
                            ${sisa > 0 ? `<button type="button" data-kelas-popover="${g.id}" aria-haspopup="dialog" aria-expanded="false" aria-label="Lihat semua ${daftar.length} kelas yang diajar ${esc(g.nama)}" class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-600 transition hover:border-blue-300 hover:bg-blue-50 hover:text-primary-70 focus-visible:outline-2 focus-visible:outline-blue-500 active:scale-95">+${sisa} lainnya</button>` : ''}
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-md border border-blue-100 bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-primary-70">${ICON_LAYERS}<span class="font-semibold">${daftar.length}</span> kelas</span>
                    </div>`;
            };

            const kelasScrollClass = (jumlah) => jumlah > 10
                ? 'kelas-popover-scroll max-h-64 overflow-y-auto overscroll-contain'
                : '';

            let popoverRingkas = null;

            const tutupPopoverRingkas = () => {
                if (!popoverRingkas) return false;
                const { button, element } = popoverRingkas;
                popoverRingkas = null;
                element.dataset.visible = 'false';
                element.style.pointerEvents = 'none';
                button.setAttribute('aria-expanded', 'false');
                setTimeout(() => element.remove(), 180);
                return true;
            };

            const bukaPopoverRingkas = (button) => {
                if (button?.getAttribute('aria-expanded') === 'true') {
                    tutupPopoverRingkas();
                    return;
                }
                tutupPopoverRingkas();
                const g = rows.find((r) => String(r.id) === String(button?.dataset.kelasPopover));
                if (!g) return;
                const daftar = Array.isArray(g.kelas_diampu) ? g.kelas_diampu : [];
                const pop = document.createElement('div');
                pop.className = 'penugasan-kelas-popover fixed z-[90] w-72 max-w-[calc(100vw-1rem)] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10';
                pop.setAttribute('role', 'dialog');
                pop.setAttribute('aria-label', `Semua kelas yang diajar ${g.nama || ''}`);
                pop.innerHTML = `
                    <div class="flex shrink-0 items-center justify-between gap-2 border-b border-slate-100 bg-white px-3 py-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">SEMUA KELAS YANG DIAJAR</p>
                        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-50 px-1.5 text-[11px] font-semibold text-primary-60">${daftar.length}</span>
                    </div>
                    <div class="space-y-1.5 p-3 ${kelasScrollClass(daftar.length)}">
                        ${daftar.map((kelas) => {
                            const nama = kelas.nama || kelas.kelas || '-';
                            const jurusan = kelas.jurusan || '';
                            const label = jurusan ? `${nama} · ${jurusan}` : nama;

                            return `<div class="break-words rounded-lg border border-blue-100 bg-blue-50 px-2.5 py-1.5 text-[11px] font-medium leading-snug text-primary-70">${esc(label)}</div>`;
                        }).join('')}
                    </div>`;
                document.body.appendChild(pop);
                const rect = button.getBoundingClientRect();
                const lebar = pop.offsetWidth;
                const tinggi = pop.offsetHeight;
                let kiri = rect.left;
                let atas = rect.bottom + 8;
                if (kiri + lebar > window.innerWidth - 8) kiri = window.innerWidth - lebar - 8;
                if (kiri < 8) kiri = 8;
                if (atas + tinggi > window.innerHeight - 8) atas = Math.max(8, rect.top - tinggi - 8);
                pop.style.left = `${kiri}px`;
                pop.style.top = `${atas}px`;
                button.setAttribute('aria-expanded', 'true');
                popoverRingkas = { button, element: pop };
                requestAnimationFrame(() => { if (pop.isConnected) pop.dataset.visible = 'true'; });
            };

            // ===== Template baris =====
            const rowTable = (g, no, i) => `
                <tr data-row-id="${g.id}" class="guru-row" style="--i:${i}">
                    <td class="px-4 py-5 text-left text-slate-500">${no}</td>
                    <td class="px-4 py-5">${esc(g.nip ?? '')}</td>
                    <td class="px-4 py-5 font-medium">${esc(g.nama)}</td>
                    <td class="px-4 py-5">
                        <a href="mailto:${esc(g.email || '')}"
                            class="inline-block max-w-[15rem] truncate align-middle text-primary-60 transition hover:text-primary-70 hover:underline"
                            title="${esc(g.email || '')}">${esc(g.email || '-')}</a>
                    </td>
                    <td class="px-4 py-5">${esc(g.mapel || '-')}</td>
                    <td class="px-4 py-5 text-left">${kelasRingkas(g)}</td>
                    <td class="px-4 py-5">${pill(badgeStatus[g.status] || 'bg-slate-100 text-slate-500', g.status)}</td>
                    <td class="px-4 py-5">${actions(g)}</td>
                </tr>`;

            const rowCard = (g, i) => `
                <div data-row-id="${g.id}" class="guru-row" style="--i:${i}">
                    <div class="flex items-start justify-between gap-3 py-4">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800">${esc(g.nama)}</p>
                            <p class="mt-0.5 text-xs text-slate-500">${esc(g.nip ?? '')} · ${esc(g.mapel || '-')}</p>
                            <p class="mt-0.5 break-all text-xs text-slate-400">${esc(g.email || '-')}</p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                ${pill(badgeStatus[g.status] || 'bg-slate-100 text-slate-500', g.status)}
                            </div>
                            <div class="mt-2 border-t border-slate-100 pt-2">${kelasRingkas(g)}</div>
                        </div>
                        <div class="shrink-0">${actions(g)}</div>
                    </div>
                </div>`;

            // ===== Filter =====
            const mapelValues = (r) => (Array.isArray(r.mapel_list) ? r.mapel_list : [r.mapel])
                .map((v) => String(v ?? '').trim())
                .filter(Boolean);

            const filtered = () => {
                const q = state.q.trim().toLowerCase();
                return rows.filter((r) =>
                    (!state.status || r.status === state.status)
                    && (!state.mapel || mapelValues(r).includes(state.mapel))
                    && (!q || r.nama.toLowerCase().includes(q)
                              || String(r.nip ?? '').includes(q)
                              || (r.email || '').toLowerCase().includes(q)));
            };

            const fillSelect = (sel, values) => {
                const current = sel.value;
                [...sel.options].slice(1).forEach((o) => o.remove());
                values.forEach((v) => sel.add(new Option(v, v)));
                sel.value = values.includes(current) ? current : '';
            };

            const unique = (key) => [...new Set(rows.map((r) => r[key]).filter((v) => v !== null && v !== undefined && v !== ''))].sort();

            const semuaMapel = () => [...new Set(rows.flatMap(mapelValues))].sort();

            // Daftar mapel untuk dropdown formulir: master + yang sudah dipakai data
            const mapelChoices = () => [...new Set([...baseMapel, ...semuaMapel()])].sort();

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
                tutupPopoverRingkas();
                tutupTooltipPenugasan();
                const list     = filtered();
                const total    = list.length;
                const lastPage = Math.max(1, Math.ceil(total / perPage));
                state.page     = Math.min(state.page, lastPage);

                const start = (state.page - 1) * perPage;
                const slice = list.slice(start, start + perPage);

                tableBody.dataset.anim = animate ? 'on' : 'off';
                cardBody.dataset.anim  = animate ? 'on' : 'off';

                if (!slice.length) {
                    const msg = rows.length === 0 ? 'Belum ada data guru' : 'Tidak ada data guru yang cocok';
                    tableBody.innerHTML = `<tr><td colspan="8" class="guru-empty px-4 py-12 text-center text-sm text-slate-400">${msg}</td></tr>`;
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
                fillSelect(mapelEl, semuaMapel());
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

            const penugasanMapelSelect = form.querySelector('[data-guru-penugasan-mapel]');
            const penugasanMapelEmpty = form.querySelector('[data-guru-penugasan-mapel-empty]');
            const mapelOptions = Array.isArray(data.mapel_options) ? data.mapel_options : [];
            const kelasOptions = Array.isArray(data.penugasan_kelas) ? data.penugasan_kelas : [];
            const tingkatKelas = (k) => String(k?.kelas ?? '').split('-')[0].trim();
            const labelKelas = (k) => k?.label || ((k?.jurusan || '-') + ' / ' + (k?.kelas || '-'));

            const createKelasPicker = ({ wrap, filterWrap, selectedWrap, countEl, emptyEl, options, refreshForm }) => {
                let selected = new Set();
                let filter = 'semua';
                const render = () => {
                    const list = (options || []).filter((k) => filter === 'semua' || tingkatKelas(k) === filter);
                    if (wrap) {
                        wrap.innerHTML = list.length ? list.map((k) => {
                            const id = String(k.id); const active = selected.has(id);
                            return `<button type="button" data-kelas-card="${id}" aria-pressed="${active?'true':'false'}" class="group flex items-center gap-2 rounded-xl border px-3 py-2.5 text-left transition ${active ? 'border-emerald-300 bg-emerald-50/80 ring-1 ring-emerald-200' : 'border-slate-200 bg-white hover:border-blue-200 hover:bg-blue-50/30'}">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-xs font-semibold ${active?'text-emerald-800':'text-slate-700'}">${esc(k.kelas || '-')}</span>
                                    <span class="block truncate text-[11px] ${active?'text-emerald-600':'text-slate-400'}">${esc(k.jurusan || '-')}</span>
                                </span>
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border ${active ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300 text-transparent group-hover:border-blue-300'}"><svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg></span>
                            </button>`;
                        }).join('') : `<div class="col-span-2 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-6 text-center text-xs text-slate-400">Tidak ada kelas pada filter ini.</div>`;
                    }
                    if (selectedWrap) {
                        const selectedItems = (options || []).filter((k) => selected.has(String(k.id)));
                        selectedWrap.innerHTML = selectedItems.length ? selectedItems.map((k) => `<span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-medium text-emerald-700">${esc(labelKelas(k))}<button type="button" data-remove-kelas="${k.id}" class="rounded-full p-0.5 text-emerald-500 hover:bg-emerald-100 hover:text-emerald-700" aria-label="Hapus ${esc(labelKelas(k))}"><svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button></span>`).join('') : '<span class="text-xs text-slate-400">Belum ada kelas yang dipilih.</span>';
                    }
                    if (countEl) countEl.textContent = `${selected.size} dipilih`;
                    emptyEl?.classList.toggle('hidden', (options || []).length > 0);
                    filterWrap?.querySelectorAll('[data-filter]').forEach((btn) => {
                        const active = btn.dataset.filter === filter;
                        btn.className = `rounded-md px-2 py-1.5 text-[11px] font-semibold transition ${active ? 'bg-white text-primary-70 shadow-sm' : 'text-slate-500 hover:text-primary-70'}`;
                    });
                };
                wrap?.addEventListener('click', (e) => {
                    const card = e.target.closest('[data-kelas-card]'); if (!card) return;
                    const id = String(card.dataset.kelasCard);
                    if (selected.has(id)) selected.delete(id); else selected.add(id);
                    render();
                    window.saskansaChange?.refresh?.(refreshForm);
                });
                selectedWrap?.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-remove-kelas]'); if (!btn) return;
                    selected.delete(String(btn.dataset.removeKelas)); render();
                    window.saskansaChange?.refresh?.(refreshForm);
                });
                filterWrap?.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-filter]'); if (!btn) return;
                    filter = btn.dataset.filter || 'semua'; render();
                });
                return {
                    render,
                    setSelected: (ids = []) => { selected = new Set((ids || []).map((id) => String(id))); render(); },
                    getSelected: () => [...selected].map((id) => Number(id)).filter(Boolean),
                };
            };

            const guruKelasPicker = createKelasPicker({
                wrap: form.querySelector('[data-guru-penugasan-kelas]'),
                filterWrap: form.querySelector('[data-guru-kelas-filter]'),
                selectedWrap: form.querySelector('[data-guru-kelas-selected]'),
                countEl: form.querySelector('[data-guru-kelas-count]'),
                emptyEl: form.querySelector('[data-guru-penugasan-kelas-empty]'),
                options: kelasOptions,
                refreshForm: form,
            });

            const fillPenugasanMapel = (selectedId = '') => {
                penugasanMapelSelect.innerHTML = '<option value="">Pilih mata pelajaran</option>';
                mapelOptions.forEach((m) => {
                    const opt = document.createElement('option');
                    opt.value = String(m.id);
                    opt.textContent = m.kode ? `${m.nama} (${m.kode})` : m.nama;
                    if (String(selectedId) === String(m.id)) opt.selected = true;
                    penugasanMapelSelect.appendChild(opt);
                });
                penugasanMapelEmpty?.classList.toggle('hidden', mapelOptions.length > 0);
            };

            const selectedKelasIds = () => guruKelasPicker.getSelected();
            const setPenugasanFields = (g = null) => {
                fillPenugasanMapel(g?.mata_pelajaran_id || '');
                guruKelasPicker.setSelected(g?.kelas_ids || []);
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
                    f.mapel.value  = g.mapel || '';
                } else {
                    pwField.classList.remove('hidden');
                    f.password.value = buatPassword();
                    setPasswordVisible(true);

                    titleEl.textContent  = 'Tambah Guru';
                    subtitle.textContent = 'Lengkapi data guru yang akan ditambahkan';
                    pwHint.textContent = 'Password dibuat otomatis oleh sistem dan ikut muncul di export Excel.';
                    pwHint.classList.remove('hidden');

                    f.status.value = 'Aktif';
                    f.mapel.value = '';
                }
                setPenugasanFields(g);

                const perubahanGuru = window.saskansaChange?.bind?.(form, {
                    save: '[data-modal-save]',
                    sources: () => ({
                        kelas_ids: selectedKelasIds(),
                        mata_pelajaran_id: penugasanMapelSelect.value,
                    }),
                    always: !g,
                });
                perubahanGuru?.busy?.(false);
                if (g) perubahanGuru?.snapshot?.();

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

            const alertError = (title, text) => {
                if (window.Swal) {
                    Swal.fire({ icon: 'error', title, text });
                    return;
                }

                window.alert(`${title}: ${text}`);
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

                const missing = !nis ? f.nis : !nama ? f.nama : !email ? f.email : null;
                if (missing) {
                    showError('NIP, nama, dan email wajib diisi.');
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

                const mataPelajaranId = penugasanMapelSelect.value ? Number(penugasanMapelSelect.value) : null;
                const kelasIds = selectedKelasIds();
                if (mataPelajaranId && kelasIds.length === 0) {
                    showError('Pilih minimal satu kelas untuk mata pelajaran yang dipilih.');
                    return;
                }
                if (!mataPelajaranId && kelasIds.length > 0) {
                    showError('Pilih mata pelajaran sebelum memilih kelas.');
                    penugasanMapelSelect.focus();
                    return;
                }

                pendingSave = {
                    payload: { nis, nama, mapel, email, status: f.status.value, password, mata_pelajaran_id: mataPelajaranId, kelas_ids: kelasIds },
                };

                openConfirm(isAdd
                    ? { title: 'Yakin ingin menambahkan data?', text: `Data ${nama} akan ditambahkan ke daftar guru.` }
                    : { title: 'Yakin ingin menyimpan perubahan?', text: `Perubahan data ${nama} akan disimpan.` });
            });

            root.addEventListener('click', async (e) => {
                const kelasBtn = e.target.closest?.('[data-kelas-popover]');
                if (kelasBtn) {
                    bukaPopoverRingkas(kelasBtn);
                    return;
                }

                const btn = e.target.closest('[data-action]');
                if (!btn) return;

                const id = Number(btn.dataset.id);
                const g  = rows.find((r) => r.id === id);
                if (!g) return;

                if (btn.dataset.action === 'edit') {
                    openModal(g);
                    return;
                }

                if (btn.dataset.action === 'detail-kelas') {
                    tutupTooltipPenugasan();
                    bukaDetailKelas(g);
                    return;
                }

                if (btn.dataset.action === 'penugasan') {
                    await bukaPenugasan(g);
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

            const penugasanModal = document.getElementById('penugasan-modal');
            const penugasanForm = document.getElementById('penugasan-form');
            const penugasanList = penugasanForm.querySelector('[data-penugasan-list]');
            const penugasanLoading = penugasanForm.querySelector('[data-penugasan-loading]');
            const penugasanEmpty = penugasanForm.querySelector('[data-penugasan-empty]');
            const penugasanError = penugasanForm.querySelector('[data-penugasan-error]');
            const penugasanTanpaMapel = penugasanForm.querySelector('[data-penugasan-tanpa-mapel]');
            const penugasanSimpan = penugasanModal.querySelector('[data-penugasan-simpan]');
            const penugasanGuruBtn = document.getElementById('guru-penugasan');
            const penugasanOverview = document.getElementById('penugasan-overview-modal');
            const penugasanOverviewList = penugasanOverview?.querySelector('[data-penugasan-overview-list]');
            const penugasanOverviewScroll = penugasanOverview?.querySelector('[data-penugasan-overview-scroll]');
            const penugasanOverviewSearch = penugasanOverview?.querySelector('[data-penugasan-overview-search]');
            const penugasanOverviewSearchClear = penugasanOverview?.querySelector('[data-penugasan-overview-search-clear]');
            const tambahPenugasanBtn = penugasanForm.querySelector('[data-penugasan-tambah]');
            if (tambahPenugasanBtn) tambahPenugasanBtn.remove();
            let penugasanGuru = null;
            let penugasanReturnFocus = null;
            let penugasanOverviewQuery = '';

            const penugasanPesan = (text) => { penugasanError.textContent = text || ''; penugasanError.classList.toggle('hidden', !text); };
            const pMapelOptions = Array.isArray(data.mapel_options) ? data.mapel_options : [];
            const pKelasOptions = Array.isArray(data.penugasan_kelas) ? data.penugasan_kelas : [];

            let penugasanKelasPicker = null;
            let penugasanMapelId = null;
            const renderPenugasanModal = (selectedMapelId = '', selectedKelasIds = [], mapelLabel = '') => {
                penugasanMapelId = selectedMapelId ? Number(selectedMapelId) : null;
                const noMapel = !penugasanMapelId;
                const noKelas = pKelasOptions.length === 0;
                penugasanTanpaMapel.classList.toggle('hidden', !noKelas);
                penugasanEmpty.classList.add('hidden');
                if (noKelas) {
                    penugasanTanpaMapel.innerHTML = `<p class="text-sm font-medium text-slate-700">Data kelas belum tersedia</p><p class="mt-1 text-xs text-slate-500">Tambahkan data lewat menu Data Kelas terlebih dahulu.</p>`;
                }
                const mapelText = mapelLabel || pMapelOptions.find((m) => String(m.id) === String(penugasanMapelId))?.nama || (penugasanGuru?.mapel_list?.[0] || penugasanGuru?.mapel || '-');
                const mapelKode = pMapelOptions.find((m) => String(m.id) === String(penugasanMapelId))?.kode;
                const mapelTampil = mapelKode ? `${mapelText} (${mapelKode})` : mapelText;
                penugasanList.innerHTML = `
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-primary-70">Mata Pelajaran</label>
                                <div class="relative">
                                    <input type="text" value="${esc(mapelTampil || '-')}" readonly disabled aria-readonly="true" tabindex="-1"
                                        class="h-11 w-full cursor-not-allowed rounded-md border border-slate-300 bg-slate-50 px-3 pr-10 text-sm text-slate-600 outline-none">
                                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400" title="Mata pelajaran tidak dapat diubah">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 1 1 8 0v3"/></svg>
                                    </span>
                                </div>
                                <p class="mt-1 text-[11px] text-slate-400">Mata pelajaran ditetapkan dari data guru dan tidak dapat diubah di sini.</p>
                                ${noMapel ? '<p class="mt-1 text-xs text-amber-600">Guru belum memiliki mata pelajaran. Atur lewat Tambah/Edit Data Guru.</p>' : ''}
                            </div>
                            <div>
                                <div class="mb-1.5 flex items-center justify-between gap-2">
                                    <p class="text-sm font-medium text-primary-70">Kelas Diampu</p>
                                    <span data-p-kelas-count class="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-primary-60">0 dipilih</span>
                                </div>
                                <div data-p-kelas-filter class="mb-2 grid grid-cols-4 gap-1 rounded-lg border border-slate-200 bg-slate-100 p-1">
                                    <button type="button" data-filter="semua" class="rounded-md bg-white px-2 py-1.5 text-[11px] font-semibold text-primary-70 shadow-sm">Semua</button>
                                    <button type="button" data-filter="10" class="rounded-md px-2 py-1.5 text-[11px] font-semibold text-slate-500 hover:text-primary-70">Kelas 10</button>
                                    <button type="button" data-filter="11" class="rounded-md px-2 py-1.5 text-[11px] font-semibold text-slate-500 hover:text-primary-70">Kelas 11</button>
                                    <button type="button" data-filter="12" class="rounded-md px-2 py-1.5 text-[11px] font-semibold text-slate-500 hover:text-primary-70">Kelas 12</button>
                                </div>
                                <div data-p-kelas-wrap class="grid max-h-52 grid-cols-2 gap-2 overflow-y-auto pr-1"></div>
                                <p data-p-kelas-empty class="mt-1 hidden text-xs text-amber-600">Data kelas belum tersedia. Tambahkan lewat menu Data Kelas.</p>
                                <div class="mt-3 border-t border-slate-100 pt-3">
                                    <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Kelas Terpilih</p>
                                    <div data-p-kelas-selected class="flex min-h-7 flex-wrap gap-1.5"></div>
                                </div>
                            </div>
                        </div>
                    </div>`;
                penugasanKelasPicker = createKelasPicker({
                    wrap: penugasanList.querySelector('[data-p-kelas-wrap]'),
                    filterWrap: penugasanList.querySelector('[data-p-kelas-filter]'),
                    selectedWrap: penugasanList.querySelector('[data-p-kelas-selected]'),
                    countEl: penugasanList.querySelector('[data-p-kelas-count]'),
                    emptyEl: penugasanList.querySelector('[data-p-kelas-empty]'),
                    options: pKelasOptions,
                    refreshForm: penugasanForm,
                });
                penugasanKelasPicker.setSelected(selectedKelasIds || []);
            };

            const getPenugasanModalPayload = () => {
                const kelasIds = penugasanKelasPicker ? penugasanKelasPicker.getSelected() : [];
                return { mata_pelajaran_id: penugasanMapelId ? Number(penugasanMapelId) : null, kelas_ids: kelasIds };
            };

            const siapkanPenugasanChange = () => {
                if (!window.saskansaChange) {
                    penugasanSimpan.disabled = false;
                    return;
                }
                const perubahanPenugasan = window.saskansaChange.bind(penugasanForm, {
                    save: '[data-penugasan-simpan]',
                    sources: () => ({
                        kelas_ids: penugasanKelasPicker ? penugasanKelasPicker.getSelected() : [],
                        mata_pelajaran_id: penugasanMapelId ?? '',
                    }),
                });
                perubahanPenugasan?.busy?.(false);
                perubahanPenugasan?.snapshot?.();
            };

            const tutupPenugasan = () => {
                penugasanModal.dataset.open = 'false';
                penugasanModal.setAttribute('aria-hidden', 'true');
                penugasanModal.setAttribute('inert', '');
                penugasanReturnFocus?.focus();
            };

            const teksPenugasan = (nilai) => String(nilai ?? '').toLocaleLowerCase('id-ID').replace(/\s+/g, ' ').trim();

            const cocokPenugasan = (g, query) => {
                const kata = teksPenugasan(query).split(' ').filter(Boolean);
                if (kata.length === 0) return true;
                const kelas = Array.isArray(g.kelas_diampu) ? g.kelas_diampu : [];
                const mapel = Array.isArray(g.mapel_list) && g.mapel_list.length ? g.mapel_list : [g.mapel || ''];
                const sumber = [
                    g.nama,
                    g.nip,
                    g.email,
                    ...mapel,
                    ...kelas.flatMap((k) => [k.nama, k.kelas, k.jurusan]),
                ];
                const gabungan = teksPenugasan(sumber.join(' '));
                return kata.every((potongan) => gabungan.includes(potongan));
            };

            const sinkronHapusPencarian = () => {
                if (!penugasanOverviewSearchClear) return;
                const ada = Boolean(penugasanOverviewSearch?.value);
                penugasanOverviewSearchClear.classList.toggle('hidden', !ada);
            };

            const resetPenugasanOverviewScroll = () => {
                if (penugasanOverviewScroll) penugasanOverviewScroll.scrollTop = 0;
            };

            const hapusPencarianOverview = () => {
                if (!penugasanOverviewSearch) return;
                penugasanOverviewSearch.value = '';
                penugasanOverviewQuery = '';
                sinkronHapusPencarian();
                renderPenugasanOverview();
                resetPenugasanOverviewScroll();
                penugasanOverviewSearch.focus();
            };

            const renderPenugasanOverview = () => {
                if (!penugasanOverviewList) return;
                if (!rows.length) {
                    penugasanOverviewList.innerHTML = '<div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-500">Belum ada data guru.</div>';
                    return;
                }
                const tersaring = rows.filter((g) => cocokPenugasan(g, penugasanOverviewQuery));
                if (tersaring.length === 0) {
                    penugasanOverviewList.innerHTML = `
                        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center">
                            ${ICON_SEARCH_X}
                            <p class="mt-3 text-sm font-semibold text-slate-700">Guru tidak ditemukan</p>
                            <p class="mt-1 text-xs text-slate-500">Tidak ada guru yang cocok dengan kata kunci tersebut.</p>
                            <button type="button" data-penugasan-overview-reset
                                class="mt-4 inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-600 transition hover:bg-slate-50 active:scale-[.98]">
                                ${ICON_ROTATE}
                                Hapus pencarian
                            </button>
                        </div>`;
                    return;
                }
                penugasanOverviewList.innerHTML = tersaring.map((g) => {
                    const mapel = (Array.isArray(g.mapel_list) && g.mapel_list.length) ? g.mapel_list[0] : (g.mapel || '-');
                    const kelas = Array.isArray(g.kelas_diampu) ? g.kelas_diampu : [];
                    const kelasBadges = kelas.length
                        ? kelas.slice(0, 2).map((k) => `<span class="inline-flex items-center rounded-full border border-blue-100 bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-primary-70">${esc(k.nama || k.kelas || '-')}</span>`).join('')
                          + (kelas.length > 2 ? `<button type="button" data-overview-kelas-popover="${g.id}" aria-haspopup="dialog" aria-expanded="false" aria-label="Lihat ${kelas.length} kelas yang diajar ${esc(g.nama || '')}" class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-600 transition hover:border-blue-300 hover:bg-blue-50 hover:text-primary-70 focus-visible:outline-2 focus-visible:outline-blue-500 active:scale-95">+${kelas.length - 2} lainnya</button>` : '')
                        : '<span class="text-xs text-slate-400">-</span>';
                    return `
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-slate-800">${esc(g.nama || '-')}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">${esc(g.nip || '-')} · ${esc(g.email || '-')}</p>
                                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                        <div><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Mata Pelajaran</p><p class="mt-0.5 text-sm text-slate-700">${esc(mapel || '-')}</p></div>
                                        <div><p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Kelas yang Diajar</p><div class="mt-1 flex flex-wrap gap-1.5">${kelasBadges}</div></div>
                                    </div>
                                </div>
                                <button type="button" data-overview-kelola="${g.id}" data-overview-tooltip="Kelola Penugasan Mengajar" aria-label="Kelola penugasan ${esc(g.nama || '')}" class="inline-flex h-9 w-9 shrink-0 items-center justify-center self-start rounded-lg border border-blue-300 bg-white text-primary-60 transition hover:bg-blue-50 active:scale-90 focus-visible:outline-2 focus-visible:outline-blue-500">${ICON_PENUGASAN}</button>
                            </div>
                        </div>`;
                }).join('');
            };

            let popoverKelas = null;
            let tooltipPenugasan = null;
            let tooltipPenugasanTarget = null;
            let tooltipTimer = null;

            const tutupPopoverKelas = () => {
                if (!popoverKelas) return false;
                const { button, element } = popoverKelas;
                popoverKelas = null;
                element.remove();
                button.setAttribute('aria-expanded', 'false');
                return true;
            };

            const bukaPopoverKelas = (button) => {
                if (button?.getAttribute('aria-expanded') === 'true') {
                    tutupPopoverKelas();
                    return;
                }
                tutupPopoverKelas();
                tutupTooltipPenugasan();
                const g = rows.find((r) => String(r.id) === String(button?.dataset.overviewKelasPopover));
                if (!g) return;
                const daftar = Array.isArray(g.kelas_diampu) ? g.kelas_diampu : [];
                const pop = document.createElement('div');
                pop.className = 'penugasan-kelas-popover fixed z-[90] w-64 max-w-[calc(100vw-1rem)] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10';
                pop.setAttribute('role', 'dialog');
                pop.setAttribute('aria-label', `Semua kelas yang diajar ${g.nama || ''}`);
                pop.innerHTML = `
                    <div class="flex shrink-0 items-center justify-between gap-2 border-b border-slate-100 bg-white px-3 py-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">SEMUA KELAS YANG DIAJAR</p>
                        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-primary-60">${daftar.length}</span>
                    </div>
                    <div class="space-y-1.5 px-3 py-2 ${kelasScrollClass(daftar.length)}">
                        ${daftar.map((k) => {
                            const nama = k.nama || k.kelas || '-';
                            const jurusan = k.jurusan || '';
                            const label = jurusan ? `${nama} · ${jurusan}` : nama;
                            return `<span class="block break-words rounded-full border border-blue-100 bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-primary-70" title="${esc(jurusan)}">${esc(label)}</span>`;
                        }).join('')}
                    </div>`;
                document.body.appendChild(pop);
                const rect = button.getBoundingClientRect();
                const lebar = pop.offsetWidth;
                const tinggi = pop.offsetHeight;
                let kiri = rect.left;
                let atas = rect.bottom + 8;
                if (kiri + lebar > window.innerWidth - 8) kiri = window.innerWidth - lebar - 8;
                if (kiri < 8) kiri = 8;
                if (atas + tinggi > window.innerHeight - 8) atas = Math.max(8, rect.top - tinggi - 8);
                pop.style.left = `${kiri}px`;
                pop.style.top = `${atas}px`;
                button.setAttribute('aria-expanded', 'true');
                popoverKelas = { button, element: pop };
                requestAnimationFrame(() => { if (pop.isConnected) pop.dataset.visible = 'true'; });
            };

            const tutupTooltipPenugasan = () => {
                clearTimeout(tooltipTimer);
                tooltipPenugasanTarget?.removeAttribute('aria-describedby');
                tooltipPenugasanTarget = null;
                if (tooltipPenugasan) tooltipPenugasan.dataset.visible = 'false';
            };

            const tampilkanTooltipPenugasan = (button) => {
                const label = button?.dataset.overviewTooltip;
                if (!label || !button.isConnected) return;
                clearTimeout(tooltipTimer);
                tooltipTimer = setTimeout(() => {
                    if (!button.isConnected) return;
                    let tip = document.getElementById('penugasan-tooltip');
                    if (!tip) {
                        tip = document.createElement('div');
                        tip.id = 'penugasan-tooltip';
                        tip.setAttribute('role', 'tooltip');
                        document.body.appendChild(tip);
                    }
                    tip.textContent = label;
                    tip.dataset.visible = 'true';
                    tooltipPenugasan = tip;
                    tooltipPenugasanTarget?.removeAttribute('aria-describedby');
                    tooltipPenugasanTarget = button;
                    button.setAttribute('aria-describedby', 'penugasan-tooltip');
                    const rect = button.getBoundingClientRect();
                    const lebar = tip.offsetWidth;
                    const tinggi = tip.offsetHeight;
                    let kiri = rect.left + rect.width / 2 - lebar / 2;
                    let atas = rect.top - tinggi - 10;
                    if (atas < 8) atas = Math.min(window.innerHeight - tinggi - 8, rect.bottom + 10);
                    if (kiri + lebar > window.innerWidth - 8) kiri = window.innerWidth - lebar - 8;
                    if (kiri < 8) kiri = 8;
                    tip.style.left = `${kiri}px`;
                    tip.style.top = `${atas}px`;
                }, 80);
            };

            const bukaPenugasanOverview = () => {
                if (!penugasanOverview) return;
                penugasanOverviewQuery = '';
                if (penugasanOverviewSearch) penugasanOverviewSearch.value = '';
                sinkronHapusPencarian();
                renderPenugasanOverview();
                resetPenugasanOverviewScroll();
                penugasanReturnFocus = document.activeElement;
                penugasanOverview.dataset.open = 'true';
                penugasanOverview.removeAttribute('aria-hidden');
                penugasanOverview.removeAttribute('inert');
                requestAnimationFrame(() => penugasanOverviewSearch?.focus());
            };
            const tutupPenugasanOverview = () => {
                if (!penugasanOverview) return;
                tutupPopoverKelas();
                tutupTooltipPenugasan();
                penugasanOverview.dataset.open = 'false';
                penugasanOverview.setAttribute('aria-hidden', 'true');
                penugasanOverview.setAttribute('inert', '');
                if (!penugasanModal || penugasanModal.dataset.open !== 'true') penugasanReturnFocus?.focus();
            };

            async function bukaPenugasan(g) {
                penugasanGuru = g;
                penugasanReturnFocus = document.activeElement;
                penugasanModal.querySelector('[data-penugasan-subtitle]').textContent = `Atur mata pelajaran dan kelas yang diajar ${g.nama}`;
                penugasanModal.removeAttribute('inert');
                penugasanModal.setAttribute('aria-hidden', 'false');
                requestAnimationFrame(() => { penugasanModal.dataset.open = 'true'; });
                penugasanLoading.classList.remove('hidden');
                penugasanSimpan.disabled = true;
                penugasanPesan('');
                try {
                    const res = await jsonRequest(urlFor(g.id) + '/penugasan', 'GET');
                    const result = await res.json().catch(() => null);
                    if (!res.ok || !result || result.status !== 'success') throw new Error((result && result.message) || 'Gagal memuat penugasan.');
                    const freshGuru = result.data.guru || g;
                    const idx = rows.findIndex((r) => r.id === freshGuru.id); if (idx >= 0) rows[idx] = freshGuru;
                    renderPenugasanModal(freshGuru.mata_pelajaran_id || '', freshGuru.kelas_ids || []);
                    siapkanPenugasanChange();
                } catch (err) {
                    penugasanPesan(err.message || 'Gagal memuat penugasan. Coba lagi.');
                    renderPenugasanModal(g.mata_pelajaran_id || '', g.kelas_ids || []);
                    siapkanPenugasanChange();
                } finally {
                    penugasanLoading.classList.add('hidden');
                }
            }

            penugasanForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!penugasanGuru) return;
                const payload = getPenugasanModalPayload();
                if (!payload.mata_pelajaran_id) { penugasanPesan('Mata pelajaran guru belum ditetapkan. Atur lewat Tambah/Edit Data Guru.'); return; }
                if (payload.kelas_ids.length === 0) { penugasanPesan('Pilih minimal satu kelas untuk guru ini.'); return; }
                penugasanPesan('');
                const loading = showLoading('Menyimpan penugasan mengajar...');
                penugasanSimpan.disabled = true;
                try {
                    const res = await jsonRequest(urlFor(penugasanGuru.id) + '/penugasan', 'PUT', payload);
                    const result = await res.json().catch(() => null);
                    if (!res.ok || !result || result.status !== 'success') {
                        const errors = (result && result.errors) || {};
                        penugasanPesan(Object.values(errors).flat()[0] || (result && result.message) || 'Gagal menyimpan penugasan.');
                        return;
                    }
                    const saved = result.data.guru;
                    const index = rows.findIndex((r) => r.id === saved.id);
                    if (index >= 0) rows[index] = saved;
                    refreshFilters(); syncBadge(); updateStats(); render();
                    tutupPenugasan();
                    toast(`Penugasan mengajar ${saved.nama} berhasil disimpan.`);
                } catch (err) {
                    penugasanPesan('Tidak dapat terhubung ke server. Silakan coba lagi.');
                } finally {
                    penugasanSimpan.disabled = false;
                    hideLoading(loading);
                }
            });

            penugasanModal.querySelector('[data-penugasan-backdrop]').addEventListener('click', tutupPenugasan);
            penugasanModal.querySelector('[data-penugasan-tutup]').addEventListener('click', tutupPenugasan);
            penugasanModal.querySelector('[data-penugasan-batal]').addEventListener('click', tutupPenugasan);

            const tooltipTarget = (node) => (node instanceof Element ? node.closest('[data-overview-tooltip]') : null);
            const tooltipOwns = (target, related) => related instanceof Node && target.contains(related);

            if (penugasanOverview) {
                penugasanOverview.querySelectorAll('[data-penugasan-overview-tutup]').forEach((btn) => btn.addEventListener('click', tutupPenugasanOverview));
                penugasanOverview.querySelector('[data-penugasan-overview-backdrop]')?.addEventListener('click', tutupPenugasanOverview);
                penugasanOverviewSearch?.addEventListener('input', () => {
                    tutupPopoverKelas();
                    tutupTooltipPenugasan();
                    penugasanOverviewQuery = penugasanOverviewSearch.value;
                    sinkronHapusPencarian();
                    renderPenugasanOverview();
                    resetPenugasanOverviewScroll();
                });
                penugasanOverviewSearchClear?.addEventListener('click', () => {
                    tutupPopoverKelas();
                    tutupTooltipPenugasan();
                    hapusPencarianOverview();
                });
                penugasanOverview.addEventListener('click', async (e) => {
                    if (e.target.closest?.('[data-penugasan-overview-reset]')) {
                        hapusPencarianOverview();
                        return;
                    }
                    const kelasBtn = e.target.closest?.('[data-overview-kelas-popover]');
                    if (kelasBtn) {
                        bukaPopoverKelas(kelasBtn);
                        return;
                    }
                    const btn = e.target.closest?.('[data-overview-kelola]');
                    if (!btn) return;
                    const g = rows.find((r) => String(r.id) === String(btn.dataset.overviewKelola));
                    if (!g) return;
                    tutupPenugasanOverview();
                    await bukaPenugasan(g);
                });
                penugasanOverview.addEventListener('pointerover', (e) => {
                    const target = tooltipTarget(e.target);
                    if (!target || tooltipOwns(target, e.relatedTarget)) return;
                    tampilkanTooltipPenugasan(target);
                });
                penugasanOverview.addEventListener('pointerout', (e) => {
                    const target = tooltipTarget(e.target);
                    if (!target || tooltipOwns(target, e.relatedTarget)) return;
                    tutupTooltipPenugasan();
                });
                penugasanOverview.addEventListener('focusin', (e) => {
                    const target = tooltipTarget(e.target);
                    if (target) tampilkanTooltipPenugasan(target);
                });
                penugasanOverview.addEventListener('focusout', (e) => {
                    if (tooltipTarget(e.target)) tutupTooltipPenugasan();
                });
                penugasanOverviewScroll?.addEventListener('scroll', () => {
                    tutupPopoverKelas();
                    tutupTooltipPenugasan();
                });
            }
            document.addEventListener('pointerdown', (e) => {
                if (popoverRingkas && !popoverRingkas.element.contains(e.target) && !popoverRingkas.button.contains(e.target)) {
                    tutupPopoverRingkas();
                }
                if (popoverKelas && !popoverKelas.element.contains(e.target) && !popoverKelas.button.contains(e.target)) {
                    tutupPopoverKelas();
                }
            });
            window.addEventListener('resize', () => {
                tutupPopoverRingkas();
                tutupPopoverKelas();
                tutupTooltipPenugasan();
            });
            window.addEventListener('scroll', (e) => {
                if (popoverRingkas && !popoverRingkas.element.contains(e.target)) tutupPopoverRingkas();
            }, true);
            penugasanGuruBtn?.addEventListener('click', bukaPenugasanOverview);

            document.addEventListener('keydown', (e) => {
                if (e.key !== 'Escape') return;
                if (tutupPopoverRingkas()) return;
                if (tutupPopoverKelas()) return;
                if (tooltipPenugasan?.dataset.visible === 'true') { tutupTooltipPenugasan(); return; }
                if (penugasanModal.dataset.open === 'true') { tutupPenugasan(); return; }
                if (penugasanOverview?.dataset.open === 'true') tutupPenugasanOverview();
            });

            const detailModal = document.getElementById('kelas-detail-modal');
            const detailList = detailModal.querySelector('[data-kelas-detail-list]');
            let detailReturnFocus = null;

            const tutupDetailKelas = () => {
                detailModal.dataset.open = 'false';
                detailModal.setAttribute('aria-hidden', 'true');
                detailModal.setAttribute('inert', '');
                detailReturnFocus?.focus();
            };

            function bukaDetailKelas(g) {
                const grupList = Array.isArray(g.kelas_detail) ? g.kelas_detail : [];
                const totalKelas = Array.isArray(g.kelas_diampu) ? g.kelas_diampu.length : 0;

                detailReturnFocus = document.activeElement;
                detailModal.querySelector('[data-kelas-detail-subtitle]').textContent = `Daftar kelas yang diajar oleh ${g.nama}.`;
                detailModal.querySelector('[data-kelas-detail-nama]').textContent = g.nama || '-';
                detailModal.querySelector('[data-kelas-detail-nip]').textContent = g.nip || '-';
                detailModal.querySelector('[data-kelas-detail-email]').textContent = g.email || '-';
                detailModal.querySelector('[data-kelas-detail-mapel]').textContent = g.mapel || '-';
                detailModal.querySelector('[data-kelas-detail-total]').textContent = `${totalKelas} kelas`;

                detailList.innerHTML = grupList.length === 0
                    ? `<div class="guru-empty rounded-xl border border-dashed border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-400">Belum ada penugasan kelas untuk guru ini.</div>`
                    : grupList.map((grup, i) => {
                        const kode = String(grup.kode ?? '').trim();

                        return `
                            <div class="guru-row rounded-xl border border-slate-200 bg-white p-4" style="--i:${i}">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-800">
                                            ${esc(grup.nama)}${kode ? ` <span class="text-xs font-normal text-slate-400">(${esc(kode)})</span>` : ''}
                                        </p>
                                        <p class="mt-0.5 text-[11px] text-slate-400">Mata Pelajaran</p>
                                    </div>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-500">${grup.kelas.length} kelas</span>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    ${grup.kelas.map((kelas) => {
                                        const label = kelas.jurusan
                                            ? `Kelas ${kelas.nama} · ${kelas.jurusan}`
                                            : `Kelas ${kelas.nama}`;

                                        return `<span class="inline-flex items-center rounded-full border border-blue-100 bg-blue-50 px-3 py-1 text-xs font-medium text-primary-70">${esc(label)}</span>`;
                                    }).join('')}
                                </div>
                            </div>`;
                    }).join('');

                detailModal.removeAttribute('inert');
                detailModal.setAttribute('aria-hidden', 'false');
                requestAnimationFrame(() => { detailModal.dataset.open = 'true'; });
            }

            detailModal.querySelector('[data-kelas-detail-backdrop]').addEventListener('click', tutupDetailKelas);
            detailModal.querySelectorAll('[data-kelas-detail-tutup]').forEach((btn) => btn.addEventListener('click', tutupDetailKelas));
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && detailModal.dataset.open === 'true') tutupDetailKelas();
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
