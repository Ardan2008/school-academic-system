@extends('layouts.dashboard')

@section('title', 'Data Siswa')
@section('subtitle', 'Dashboard Admin')

@section('content')
    <div id="siswa-page">

        {{-- Kartu statistik --}}
        @php
            // Warna ikon per kartu supaya empat status mudah dibedakan.
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

            {{-- Toolbar --}}
            <div class="rounded-t-2xl bg-slate-50 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <button type="button" id="siswa-add"
                            class="inline-flex h-10 flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-primary-60 px-4 text-xs font-medium text-white transition hover:bg-primary-70 sm:h-9 sm:flex-none sm:text-sm">
                            <x-lucide-plus class="h-4 w-4" />
                            Tambah Siswa
                        </button>
                        <button type="button" id="siswa-export" data-state="idle"
                            aria-live="polite"
                            title="Unduh data siswa yang sedang difilter, termasuk password awal untuk diserahkan ke siswa"
                            class="ekspor relative inline-flex h-10 flex-1 items-center justify-center gap-2 overflow-hidden whitespace-nowrap rounded-lg border border-blue-300 bg-white px-4 text-xs font-medium text-primary-60 transition hover:bg-blue-50 sm:h-9 sm:flex-none sm:text-sm">
                            {{-- Kilau yang jalan hanya saat sedang mengunduh --}}
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
                        <input type="search" id="siswa-search" placeholder="Cari nama, NIS, atau email" autocomplete="off"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white pl-10 pr-10 text-base text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20 sm:h-9 sm:text-sm [&::-webkit-search-cancel-button]:appearance-none [&::-webkit-search-decoration]:appearance-none">
                        <button
                            type="button"
                            id="siswa-search-clear"
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
                            aria-controls="siswa-filter-panel"
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
                            id="siswa-filter-panel"
                            data-filter-panel
                            data-open="false"
                            aria-hidden="true"
                            inert
                            class="invisible absolute left-0 top-full z-30 mt-2 w-full min-w-0 origin-top scale-95 rounded-2xl border border-slate-100 bg-white p-4 opacity-0 shadow-xl shadow-slate-900/10 transition-[opacity,transform,visibility] duration-200 ease-out sm:left-auto sm:right-0 sm:w-96 data-[open=true]:visible data-[open=true]:scale-100 data-[open=true]:opacity-100"
                        >
                            <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Saring Data Siswa</p>

                            @php
                                // Opsi filter berurutan jurusan -> tingkat -> rombel; tingkat & rombel
                                // terkunci sampai pilihan sebelumnya terisi agar tidak ada kombinasi sia-sia.
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
                                        <select id="siswa-filter-jurusan" data-filter-jurusan
                                            class="h-10 w-full min-w-0 appearance-none rounded-lg border border-slate-200 bg-slate-50 px-3 pr-8 text-base text-slate-600 outline-none focus:border-primary-60 sm:h-9 sm:text-sm">
                                            <option value="">Semua Jurusan</option>
                                        </select>
                                        <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                                    </div>
                                </div>
                                <div>
                                    <label for="siswa-filter-tingkat" class="{{ $filterLabel }}">Tingkat / Kelas</label>
                                    <div class="relative">
                                        <select id="siswa-filter-tingkat" data-filter-tingkat disabled
                                            class="h-10 w-full min-w-0 appearance-none rounded-lg border border-slate-200 bg-slate-50 px-3 pr-8 text-base text-slate-600 outline-none focus:border-primary-60 disabled:cursor-not-allowed disabled:border-slate-100 disabled:bg-slate-100/70 sm:h-9 sm:text-sm">
                                            <option value="">Semua Tingkat</option>
                                        </select>
                                        <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                                    </div>
                                </div>
                                <div>
                                    <label for="siswa-filter-rombel" class="{{ $filterLabel }}">Kelas / Rombel</label>
                                    <div class="relative">
                                        <select id="siswa-filter-rombel" data-filter-rombel disabled
                                            class="h-10 w-full min-w-0 appearance-none rounded-lg border border-slate-200 bg-slate-50 px-3 pr-8 text-base text-slate-600 outline-none focus:border-primary-60 disabled:cursor-not-allowed disabled:border-slate-100 disabled:bg-slate-100/70 sm:h-9 sm:text-sm">
                                            <option value="">Semua Rombel</option>
                                        </select>
                                        <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                                    </div>
                                </div>
                                <div>
                                    <label for="siswa-filter-status" class="{{ $filterLabel }}">Status</label>
                                    <div class="relative">
                                        <select id="siswa-filter-status" data-filter-status
                                            class="h-10 w-full min-w-0 appearance-none rounded-lg border border-slate-200 bg-slate-50 px-3 pr-8 text-base text-slate-600 outline-none focus:border-primary-60 sm:h-9 sm:text-sm">
                                            <option value="">Semua Status</option>
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

            {{-- Tabel: md ke atas (overflow-y-hidden mencegah scrollbar vertikal berkedip saat baris beranimasi) --}}
            <div class="hidden overflow-x-auto overflow-y-hidden border-t border-slate-100 md:block">
                <table class="w-full text-center text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-4 text-left font-medium">No</th>
                            <th class="px-4 py-4 font-medium">NIS</th>
                            <th class="px-4 py-4 font-medium">Nama Siswa</th>
                            <th class="px-4 py-4 font-medium">Email</th>
                            <th class="px-4 py-4 font-medium">Kelas</th>
                            <th class="px-4 py-4 font-medium">Jurusan</th>
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

    {{-- ============ Modal Tambah / Ubah Data Siswa (satu modal, dua mode) ============ --}}
    <div
        id="siswa-modal"
        data-open="false"
        aria-hidden="true"
        inert
        class="sm fixed inset-0 z-60 flex items-end justify-center overflow-y-auto overscroll-contain bg-[#0F3D73]/60 p-0 sm:items-center sm:p-4 lg:left-64"
        role="dialog"
        aria-modal="true"
        aria-labelledby="siswa-modal-title"
    >
        <div data-modal-backdrop class="sm-backdrop absolute inset-0"></div>

        <div
            data-modal-panel
            class="sm-panel relative z-10 flex max-h-[92dvh] w-full max-w-2xl flex-col rounded-t-3xl bg-[#FAFAFA] shadow-2xl sm:max-h-[88dvh] sm:rounded-2xl"
        >
            {{-- Kepala --}}
            <header class="flex shrink-0 items-start gap-3 px-6 pb-2 pt-6 sm:px-8 sm:pt-7">
                <div class="min-w-0 flex-1">
                    <h3 id="siswa-modal-title" data-modal-title class="text-2xl font-bold leading-tight text-primary-70">Tambah Siswa</h3>
                    <p data-modal-subtitle class="mt-1 text-sm text-primary-60">Lengkapi data siswa yang akan ditambahkan</p>
                </div>
                <button
                    type="button"
                    data-modal-close
                    aria-label="Tutup"
                    class="sm-close -mr-2 -mt-1 shrink-0 rounded-lg p-1.5 text-primary-60 transition hover:bg-blue-50 active:scale-90"
                >
                    <x-lucide-x class="h-6 w-6" />
                </button>
            </header>

            {{-- Formulir --}}
            <form id="siswa-form" class="grid min-h-0 flex-1 grid-cols-1 content-start gap-x-8 gap-y-4 overflow-y-auto px-6 py-4 sm:grid-cols-2 sm:px-8" autocomplete="off" novalidate>
                @csrf

                @php
                    // Kelas dasar semua kolom; ikon di kiri memakai pl-10
                    $sField = 'h-11 w-full rounded-md border border-slate-300 bg-white pl-10 pr-3 text-base text-slate-800 outline-none transition
                        placeholder:text-xs placeholder:text-slate-400
                        focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20 sm:text-sm sm:placeholder:text-xs';
                    $sIcon  = 'pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-primary-60';
                    $sLabel = 'mb-1.5 block text-sm font-medium text-primary-70';
                @endphp

                {{-- NIS --}}
                <div>
                    <label for="sm-nis" class="{{ $sLabel }}">NIS</label>
                    <div class="relative">
                        <x-lucide-user class="{{ $sIcon }}" />
                        <input type="text" id="sm-nis" name="nis" required maxlength="30" inputmode="numeric"
                            class="{{ $sField }}" placeholder="Masukkan NIS siswa">
                    </div>
                    <p class="mt-1 text-xs text-slate-500">NIS dipakai untuk masuk ke akun siswa.</p>
                </div>

                {{-- Nama --}}
                <div>
                    <label for="sm-nama" class="{{ $sLabel }}">Nama Siswa</label>
                    <div class="relative">
                        <x-lucide-user class="{{ $sIcon }}" />
                        <input type="text" id="sm-nama" name="nama" required maxlength="120"
                            class="{{ $sField }}" placeholder="Masukkan nama lengkap siswa">
                    </div>
                </div>

                {{-- Email --}}
                <div>
                    <label for="sm-email" class="{{ $sLabel }}">Email</label>
                    <div class="relative">
                        <x-lucide-mail class="{{ $sIcon }}" />
                        <input type="email" id="sm-email" name="email" required maxlength="120" autocapitalize="none" spellcheck="false"
                            class="{{ $sField }}" placeholder="nama@saskansa.sch.id">
                    </div>
                </div>

                {{-- Kelas --}}
                <div>
                    <label for="sm-kelas" class="{{ $sLabel }}">Kelas</label>
                    <div class="relative">
                        <x-lucide-building-2 class="{{ $sIcon }}" />
                        <input type="text" id="sm-kelas" name="kelas" required maxlength="5"
                            class="{{ $sField }} placeholder:text-slate-400" placeholder="Contoh: {{ $siswaData['kelas_contoh'] }}">
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        Tulis dengan angka, contoh {{ $siswaData['kelas_contoh'] }}.
                        Kalau satu tingkat punya beberapa rombel, tambahkan nomornya: {{ $siswaData['kelas_contoh_rombel'] }}.
                    </p>
                </div>

                {{-- Jurusan --}}
                <div>
                    <label for="sm-jurusan" class="{{ $sLabel }}">Jurusan</label>
                    <div class="relative">
                        <x-lucide-graduation-cap class="{{ $sIcon }}" />
                        <input type="text" id="sm-jurusan" name="jurusan" required maxlength="120"
                            class="{{ $sField }} placeholder:text-slate-400" placeholder="Contoh: Rekayasa Perangkat Lunak">
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Tulis nama jurusan lengkap, contoh Rekayasa Perangkat Lunak.</p>
                </div>

                {{-- Status akun --}}
                <div>
                    <label for="sm-status" class="{{ $sLabel }}">Status Akun</label>
                    <div class="relative">
                        <x-lucide-shield-check class="{{ $sIcon }}" />
                        <select id="sm-status" name="status" class="{{ $sField }} appearance-none pr-10 text-slate-800">
                            @foreach ($siswaData['statuses'] as $statusOption)
                                <option value="{{ $statusOption }}">{{ $statusOption }}</option>
                            @endforeach
                        </select>
                        <x-lucide-chevron-down class="pointer-events-none absolute right-3 top-1/2 h-5 w-5 -translate-y-1/2 text-primary-60" />
                    </div>
                </div>

                {{-- Password awal (khusus mode tambah; sistem yang membuat) --}}
                <div data-field-password>
                    <label for="sm-password" class="{{ $sLabel }}">Password Awal</label>
                    <div class="relative">
                        <x-lucide-lock class="{{ $sIcon }}" />
                        <input type="text" id="sm-password" name="password" readonly autocomplete="off" autocapitalize="off"
                            spellcheck="false" class="{{ $sField }} pr-20 bg-slate-50 font-mono text-slate-800">
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

                <p data-modal-error
                    class="hidden items-center gap-2 rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs text-red-600 sm:col-span-2"></p>
            </form>

            {{-- Aksi --}}
            <footer class="mx-6 flex shrink-0 items-center justify-end gap-3 border-t border-slate-300 py-4 sm:mx-8">
                <button type="button" data-modal-close
                    class="sm-close inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-md border border-primary-60 bg-white px-5 text-sm font-medium text-primary-70 transition hover:bg-blue-50 active:scale-[.98] sm:flex-none">
                    <x-lucide-x class="h-4 w-4" />
                    Batal
                </button>
                <button type="submit" form="siswa-form" data-modal-save
                    class="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-md bg-primary-60 px-6 text-sm font-medium text-white shadow-md shadow-primary-60/25 transition hover:bg-primary-70 active:scale-[.98] disabled:opacity-60 sm:flex-none">
                    <x-lucide-save class="h-4 w-4" />
                    <span data-modal-save-text>Simpan</span>
                </button>
            </footer>
        </div>
    </div>

    {{-- ============ Popup Konfirmasi Simpan (Tambah / Ubah) ============ --}}
    <div
        id="siswa-confirm-modal"
        data-open="false"
        aria-hidden="true"
        inert
        class="sconfirm fixed inset-0 z-70 flex items-center justify-center p-4"
    >
        <div data-confirm-backdrop class="sconfirm-backdrop absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>

        <div
            data-confirm-panel
            role="dialog"
            aria-modal="true"
            aria-labelledby="siswa-confirm-title"
            class="sconfirm-panel relative z-10 max-h-full w-full max-w-sm overflow-y-auto rounded-2xl bg-white p-5 text-center shadow-2xl sm:p-6"
        >
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-primary-60">
                <x-lucide-save class="h-5 w-5" />
            </div>

            <h3 id="siswa-confirm-title" data-confirm-title class="text-base font-semibold text-slate-900">
                Yakin ingin menyimpan data?
            </h3>
            <p data-confirm-text class="mt-1.5 text-sm text-slate-500">
                Data siswa akan disimpan ke daftar.
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
        #siswa-page.reveal-ready [data-reveal] {
            opacity: 0;
            transform: translateY(14px);
            filter: blur(2px);
            transition:
                opacity .6s cubic-bezier(.22, 1, .36, 1) var(--d, 0s),
                transform .6s cubic-bezier(.22, 1, .36, 1) var(--d, 0s),
                filter .6s cubic-bezier(.22, 1, .36, 1) var(--d, 0s);
            will-change: opacity, transform;
        }

        #siswa-page.reveal-ready [data-reveal].is-visible {
            opacity: 1;
            transform: none;
            filter: none;
        }

        /* Di layar kecil kartu bertumpuk: hilangkan jeda bertahap agar terasa responsif */
        @media (max-width: 639px) {
            #siswa-page [data-reveal]:not(.mt-5) { --d: 0s !important; }
        }

        /* ===== Tombol toolbar ===== */
        #siswa-add, #siswa-export {
            transition: translate .2s ease, box-shadow .2s ease, background-color .2s ease;
        }

        #siswa-add:hover, #siswa-export:hover {
            translate: 0 -1px;
            box-shadow: 0 6px 14px -8px rgba(37, 99, 235, .55);
        }

        #siswa-add svg, #siswa-export svg { transition: transform .3s cubic-bezier(.22, 1, .36, 1); }
        #siswa-add:hover svg { transform: rotate(90deg); }
        #siswa-export:hover svg { transform: translateY(2px); }

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
        #siswa-page [data-filter-toggle] svg:first-child {
            transition: transform .3s cubic-bezier(.22, 1, .36, 1);
        }

        #siswa-page [data-filter-toggle][data-open="true"] svg:first-child {
            transform: rotate(180deg);
        }

        /* ===== Modal tambah/ubah: muncul halus, isi ikut naik bertahap ===== */
        .sm {
            opacity: 0;
            pointer-events: none;
            transition: opacity .25s ease;
        }

        .sm[data-open="true"] {
            opacity: 1;
            pointer-events: auto;
        }

        .sm-backdrop {
            opacity: 0;
            transition: opacity .25s ease;
        }

        .sm[data-open="true"] .sm-backdrop {
            opacity: 1;
        }

        .sm-panel {
            opacity: 0;
            transform: translateY(16px) scale(.98);
            transition: opacity .28s cubic-bezier(.22, 1, .36, 1),
                        transform .28s cubic-bezier(.22, 1, .36, 1);
        }

        .sm[data-open="true"] .sm-panel {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        /* Di layar kecil panel muncul dari bawah seperti bottom sheet */
        @media (max-width: 639.98px) {
            .sm-panel {
                transform: translateY(100%);
            }
        }

        /* Isi formulir muncul satu per satu */
        @keyframes smFieldIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: none; }
        }

        .sm[data-open="true"] #siswa-form > * {
            animation: smFieldIn .4s cubic-bezier(.22, 1, .36, 1) both;
            animation-delay: calc(var(--k, 0) * 50ms + 120ms);
        }

        /* Panah bawaan select disembunyikan (diganti ikon chevron) */
        #siswa-form select { background-image: none; }

        /* ===== Popup konfirmasi simpan: muncul di atas modal formulir ===== */
        .sconfirm {
            opacity: 0;
            pointer-events: none;
            transition: opacity .3s ease;
        }

        .sconfirm[data-open="true"] {
            opacity: 1;
            pointer-events: auto;
        }

        .sconfirm-backdrop {
            opacity: 0;
            transition: opacity .3s ease;
        }

        .sconfirm[data-open="true"] .sconfirm-backdrop {
            opacity: 1;
        }

        .sconfirm-panel {
            opacity: 0;
            transform: translateY(20px) scale(.97);
            transition: opacity .3s cubic-bezier(.22, 1, .36, 1),
                        transform .3s cubic-bezier(.22, 1, .36, 1);
        }

        .sconfirm[data-open="true"] .sconfirm-panel {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        /* Cincin fokus saat pindah pilihan dengan keyboard */
        .sconfirm [data-key-nav].sconfirm-key-selected {
            outline: 2px solid #1E86FF;
            outline-offset: 2px;
        }

        /* ===== Baris tabel & kartu masuk dengan jeda bertahap ===== */
        .siswa-row {
            animation: siswaRowIn .4s cubic-bezier(.22, 1, .36, 1) both;
            animation-delay: calc(var(--i, 0) * 45ms);
            transition: background-color .2s ease;
        }

        .siswa-row:hover { background-color: rgba(248, 250, 252, .9); }

        /* Saat mengetik di pencarian, baris berganti langsung agar tidak berkedip */
        [data-anim="off"] > .siswa-row { animation: none; }

        @keyframes siswaRowIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .siswa-empty { animation: siswaRowIn .4s cubic-bezier(.22, 1, .36, 1) both; }

        /* Tombol aksi: sedikit terangkat saat hover */
        .siswa-act {
            transition: translate .2s ease, background-color .2s ease, color .2s ease, box-shadow .2s ease;
        }

        .siswa-act:hover {
            translate: 0 -2px;
            box-shadow: 0 6px 12px -8px rgba(37, 99, 235, .5);
        }

        /* Baris yang barusan diubah/ditambah diberi cahaya singkat */
        .siswa-row[data-flash="true"] {
            animation: siswaRowFlash 1.1s ease-out;
        }

        @keyframes siswaRowFlash {
            0%   { background-color: rgba(37, 99, 235, .16); }
            100% { background-color: transparent; }
        }

        .siswa-row[data-flash="true"] td,
        .siswa-row[data-flash="true"] > div {
            animation: siswaCellPop .5s cubic-bezier(.22, 1, .36, 1);
        }

        @keyframes siswaCellPop {
            0%   { transform: scale(1); }
            40%  { transform: scale(1.015); }
            100% { transform: scale(1); }
        }

        /* Angka statistik ikut membesar sebentar saat berubah */
        .siswa-stat {
            transform-origin: left center;
        }

        .siswa-stat[data-bump="true"] {
            animation: siswaStatBump .45s cubic-bezier(.22, 1, .36, 1);
        }

        @keyframes siswaStatBump {
            0%   { transform: scale(1); }
            35%  { transform: scale(1.12); color: #2563eb; }
            100% { transform: scale(1); }
        }

        @media (prefers-reduced-motion: reduce) {
            #siswa-page.reveal-ready [data-reveal] {
                opacity: 1;
                transform: none;
                filter: none;
                transition: none;
            }

            #siswa-add:hover svg,
            #siswa-export:hover svg { animation: none; transform: none; }

            #siswa-add:hover, #siswa-export:hover, .siswa-act:hover { translate: none; }

            .sconfirm, .sconfirm-backdrop, .sconfirm-panel { transition: none; }

            .sm, .sm-backdrop, .sm-panel, .siswa-row, .siswa-empty,
            .sm[data-open="true"] #siswa-form > *,
            .siswa-row[data-flash="true"] td,
            .siswa-row[data-flash="true"] > div,
            .siswa-stat[data-bump="true"] {
                transition: none;
                animation: none;
            }

            .sm[data-open="true"] .sm-panel,
            .siswa-row {
                opacity: 1;
                transform: none;
            }
        }
    </style>

    <script>
        (function () {
            // ===== Data dari PHP =====
            const data = @json($siswaData);
            const kelasContoh = data.kelas_contoh || '12';
            const kelasContohRombel = data.kelas_contoh_rombel || '12-1';
                // Pola yang sama dengan validasi server, dikirim dari PHP supaya tidak berbeda antar sisi.
            const kelasRegex = new RegExp(data.kelas_pattern || '^(\\d{1,2})(?:-\\d{1,2})?$');
            let rows = data.siswa.slice();
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

                // Menyalin algoritma server: wajib huruf besar, huruf kecil, dan angka, diacak urutannya.
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

            const root = document.getElementById('siswa-page');
            const $ = (sel) => root.querySelector(sel);

            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[c]));

            const state = { q: '', jurusan: '', tingkat: '', rombel: '', status: '', page: 1 };

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

            const searchEl   = $('#siswa-search');
            const jurusanEl  = $('[data-filter-jurusan]');
            const tingkatEl  = $('[data-filter-tingkat]');
            const rombelEl   = $('[data-filter-rombel]');
            const statusEl   = $('[data-filter-status]');
            const summaryEl  = $('[data-filter-summary]');

            // ===== Struktur Jurusan -> Tingkat -> Rombel (dari server) =====
            // Disusun di PHP supaya rombel yang tampil sama persis dengan yang dipakai siswa,
            // sekaligus menghindari pengurutan kunci angka otomatis JS (10 sebelum 12).
            const struktur = Array.isArray(data.struktur_kelas) ? data.struktur_kelas : [];

            const tingkatJurusan = (jurusan) => {
                const found = struktur.find((j) => j.nama === jurusan);
                return found && Array.isArray(found.tingkat) ? found.tingkat : [];
            };

            const tingkatDipilih = (jurusan, tingkat) =>
                tingkatJurusan(jurusan).find((t) => t.nilai === tingkat) || null;

            const rombelDipilih = (jurusan, tingkat, rombel) => {
                const t = tingkatDipilih(jurusan, tingkat);
                return t && Array.isArray(t.rombel) ? t.rombel.find((r) => r.nilai === rombel) || null : null;
            };

            const jumlahSiswa = (n) => `${n} siswa`;

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
            const badgeKelas = 'bg-blue-100 text-blue-600';
            const badgeStatus = {
                'Aktif':    'bg-emerald-100 text-emerald-600',
                'Nonaktif': 'bg-red-100 text-red-500',
                'Lulus':    'bg-amber-100 text-amber-600',
            };
            const pill = (cls, text) =>
                `<span class="inline-block whitespace-nowrap rounded-full px-2.5 py-0.5 text-[11px] font-medium ${cls}">${esc(text)}</span>`;

            const ICON_EDIT = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg>';
            const ICON_DEL  = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>';

            const actions = (s) => `
                <div class="inline-flex items-center gap-2">
                    <button type="button" data-action="edit" data-id="${s.id}" aria-label="Ubah ${esc(s.nama)}"
                        class="siswa-act flex h-8 w-8 items-center justify-center rounded-lg border border-blue-300 bg-white text-primary-60 transition hover:bg-blue-50 active:scale-90">${ICON_EDIT}</button>
                    <button type="button" data-action="delete" data-id="${s.id}" aria-label="Hapus ${esc(s.nama)}"
                        class="siswa-act flex h-8 w-8 items-center justify-center rounded-lg border border-blue-300 bg-white text-primary-60 transition hover:bg-red-50 hover:text-red-500 active:scale-90">${ICON_DEL}</button>
                </div>`;

            // ===== Template baris =====
            const rowTable = (s, no, i) => `
                <tr data-row-id="${s.id}" class="siswa-row" style="--i:${i}">
                    <td class="px-4 py-5 text-left text-slate-500">${no}</td>
                    <td class="px-4 py-5">${esc(s.nis)}</td>
                    <td class="px-4 py-5 font-medium">${esc(s.nama)}</td>
                    <td class="px-4 py-5">
                        <a href="mailto:${esc(s.email || '')}"
                            class="inline-block max-w-[15rem] truncate align-middle text-primary-60 transition hover:text-primary-70 hover:underline"
                            title="${esc(s.email || '')}">${esc(s.email || '-')}</a>
                    </td>
                    <td class="px-4 py-5">${pill(badgeKelas, s.kelas || '-')}</td>
                    <td class="px-4 py-5 text-slate-600">${esc(s.jurusan || '-')}</td>
                    <td class="px-4 py-5">${pill(badgeStatus[s.status] || 'bg-slate-100 text-slate-500', s.status)}</td>
                    <td class="px-4 py-5">${actions(s)}</td>
                </tr>`;

            const rowCard = (s, i) => `
                <div data-row-id="${s.id}" class="siswa-row" style="--i:${i}">
                    <div class="flex items-start justify-between gap-3 py-4">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800">${esc(s.nama)}</p>
                            <p class="mt-0.5 text-xs text-slate-500">${esc(s.nis)} · ${esc(s.kelas || '-')}</p>
                            <p class="mt-0.5 text-xs text-slate-500">${esc(s.jurusan || '-')}</p>
                            <p class="mt-0.5 break-all text-xs text-slate-400">${esc(s.email || '-')}</p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                ${pill(badgeKelas, s.kelas || '-')}${pill(badgeStatus[s.status] || 'bg-slate-100 text-slate-500', s.status)}
                            </div>
                        </div>
                        <div class="shrink-0">${actions(s)}</div>
                    </div>
                </div>`;

            // ===== Filter data =====
            // Tingkat & rombel disaring terpisah; tingkat dengan satu kelas disaring lewat
            // tingkatnya supaya kelas tersimpan "11-1" ikut terambil saat ditampilkan "11".
            const filtered = () => {
                const q = state.q.trim().toLowerCase();

                return rows.filter((r) => {
                    if (state.status && r.status !== state.status) return false;
                    if (state.jurusan && r.jurusan !== state.jurusan) return false;
                    if (state.tingkat && r.tingkat !== state.tingkat) return false;

                    if (state.rombel) {
                        const rombel = rombelDipilih(state.jurusan, state.tingkat, state.rombel);
                        if (rombel && rombel.match === 'kelas' && r.kelas !== rombel.nilai) return false;
                    }

                    if (q && !(
                        r.nama.toLowerCase().includes(q)
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

            // Tingkat mengikuti jurusan yang dipilih.
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

            // Rombel mengikuti tingkat yang dipilih; tingkat berkelas tunggal tetap punya
            // satu pilihan nomor tingkatnya.
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

            // Satu baris penjelasan supaya aturan rombel antar tingkat kelihatan, bukan ditebak admin.
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

            // Kembalikan kontrol filter ke nilai yang sudah diterapkan (saat panel ditutup tanpa "Terapkan").
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
            const IDLE   = 'hover:bg-slate-100 text-slate-600';

            // render = false dipakai saat mengetik di pencarian supaya baris tidak berkedip
            function render(animate = true) {
                const list     = filtered();
                const total    = list.length;
                const last     = Math.max(1, Math.ceil(total / perPage));
                state.page     = Math.min(state.page, last);

                const start = (state.page - 1) * perPage;
                const slice = list.slice(start, start + perPage);

                tableBody.dataset.anim = animate ? 'on' : 'off';
                cardBody.dataset.anim  = animate ? 'on' : 'off';

                if (!slice.length) {
                    const msg = 'Tidak ada data siswa yang cocok';
                    tableBody.innerHTML = `<tr><td colspan="8" class="siswa-empty px-4 py-12 text-center text-sm text-slate-400">${msg}</td></tr>`;
                    cardBody.innerHTML  = `<p class="siswa-empty py-10 text-center text-sm text-slate-400">${msg}</p>`;
                } else {
                    tableBody.innerHTML = slice.map((s, i) => rowTable(s, start + i + 1, i)).join('');
                    cardBody.innerHTML  = slice.map((s, i) => rowCard(s, i)).join('');
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
                info.textContent = `Menampilkan ${from}-${to} dari ${total} ${label}`;

                pagesEl.innerHTML = pageList(state.page, last).map((n) => n === '...'
                    ? '<span class="px-1 text-slate-400">...</span>'
                    : `<button type="button" data-page="${n}" class="min-w-8 rounded-lg px-2.5 py-1.5 text-sm transition-colors duration-200 sm:min-w-0 sm:py-1 ${n === state.page ? ACTIVE : IDLE}">${n}</button>`
                ).join('');

                prevBtn.disabled = state.page === 1;
                nextBtn.disabled = state.page === last;
            }

            // Angka statistik ikut berubah setiap data disimpan / dihapus
            function updateStats() {
                const set = (key, val) => {
                    const el = root.querySelector(`[data-stat="${key}"]`);
                    if (!el) return;

                    const prev = Number(el.dataset.count);
                    if (prev === val) return;

                    el.dataset.count = val;
                    countTo(el, Number(el.textContent) || prev, val, 800);

                    // Atribut dilepas lagi supaya animasi bisa diulang
                    el.dataset.bump = 'false';
                    void el.offsetWidth;
                    el.dataset.bump = 'true';
                };

                const hit = (status) => rows.filter((r) => r.status === status).length;

                set('total', rows.length);
                set('aktif', hit('Aktif'));
                set('nonaktif', hit('Nonaktif'));
                set('lulus', hit('Lulus'));
            }

            function refreshFilters() {
                fillSelect(statusEl, data.statuses || [], 'Semua Status');
                fillSelect(jurusanEl, struktur.map((j) => ({
                    nilai: j.nama,
                    teks: `${j.nama} (${jumlahSiswa(j.jumlah)})`,
                })), 'Semua Jurusan');

                state.status = statusEl.value;
                state.jurusan = jurusanEl.value;

                // Tingkat lama dipertahankan selama jurusan baru juga punya tingkat itu; kalau tidak, dikosongkan.
                refreshTingkat();
                state.tingkat = tingkatEl.value;
                state.rombel = rombelEl.value;
                syncSummary();
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
                filterBtn.dataset.open = open ? 'true' : 'false';
                filterBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) setTimeout(() => jurusanEl?.focus(), 30);
                // Buang perubahan yang belum diterapkan saat panel ditutup
                else restoreControls();
            };

            // Tingkat dan rombel dihitung sebagai satu filter kelas, jadi badge tidak bertambah dua kali.
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
                // Terapkan pilihan baru ke data
                Object.assign(state, {
                    status: statusEl.value,
                    jurusan: jurusanEl.value,
                    tingkat: tingkatEl.value,
                    rombel: rombelEl.value,
                    page: 1,
                });
                syncBadge();
                render();
                setPanel(false);
            });

            filterPanel.querySelector('[data-filter-reset]').addEventListener('click', (e) => {
                e.stopPropagation();
                // Reset langsung: semua filter & pencarian dikosongkan tanpa perlu menekan "Terapkan".
                searchEl.value = '';
                statusEl.value = jurusanEl.value = tingkatEl.value = rombelEl.value = '';
                Object.assign(state, { q: '', status: '', jurusan: '', tingkat: '', rombel: '', page: 1 });
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

            // Kotak pencarian + tombol bersihkan
            const clearBtn = root.querySelector('#siswa-search-clear');
            const syncSearchClear = () => clearBtn.classList.toggle('hidden', searchEl.value.length === 0);

            searchEl.addEventListener('input', () => {
                state.q = searchEl.value;
                state.page = 1;
                syncSearchClear();
                syncBadge();
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
                render();
                searchEl.focus();
            });

            // Jurusan -> Tingkat -> Rombel berurutan; setiap pilihan di bawah selalu dibangun ulang
            // dari data, bukan disembunyikan. Hanya memperbarui draf; data berubah saat "Terapkan".
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

            // ===== Modal tambah/ubah =====
            const modal      = document.getElementById('siswa-modal');
            const form       = document.getElementById('siswa-form');
            const titleEl    = modal.querySelector('[data-modal-title]');
            const subtitleEl = modal.querySelector('[data-modal-subtitle]');
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
            const confirm    = document.getElementById('siswa-confirm-modal');
            const csrf       = form.querySelector('input[name="_token"]')?.value
                || document.querySelector('meta[name="csrf-token"]')?.content
                || '';

            const f = {
                nis:      document.getElementById('sm-nis'),
                nama:     document.getElementById('sm-nama'),
                email:    document.getElementById('sm-email'),
                kelas:    document.getElementById('sm-kelas'),
                jurusan:  document.getElementById('sm-jurusan'),
                status:   document.getElementById('sm-status'),
                password: document.getElementById('sm-password'),
            };

            let mode = 'add';        // 'add' | 'edit'
            let editingId = null;

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

            // s = null -> mode tambah, s = objek siswa -> mode ubah
            const openModal = (s) => {
                mode        = s ? 'edit' : 'add';
                editingId   = s ? s.id : null;
                lastFocused = document.activeElement;

                form.reset();
                setPasswordVisible(false);
                showError('');
                setBusy(false);

                if (s) {
                    pwField.classList.add('hidden');

                    titleEl.textContent    = 'Ubah Data Siswa';
                    subtitleEl.textContent = `Perbarui data ${s.nama}`;

                    f.nis.value     = s.nis ?? '';
                    f.nama.value    = s.nama ?? '';
                    f.email.value   = s.email ?? '';
                    f.kelas.value   = s.kelas ?? '';
                    f.jurusan.value = s.jurusan ?? '';
                    f.status.value  = s.status;
                } else {
                    pwField.classList.remove('hidden');
                    f.password.value = buatPassword();
                    setPasswordVisible(true);

                    titleEl.textContent    = 'Tambah Siswa';
                    subtitleEl.textContent = 'Lengkapi data siswa yang akan ditambahkan';
                    pwHint.textContent = 'Password dibuat otomatis oleh sistem dan ikut muncul di export Excel.';
                    pwHint.classList.remove('hidden');

                    f.status.value = 'Aktif';
                }

                // Urutan muncul isi formulir (dipakai animasi bertahap di CSS)
                [...form.children].forEach((el, i) => el.style.setProperty('--k', i));

                modal.removeAttribute('inert');
                modal.setAttribute('aria-hidden', 'false');
                requestAnimationFrame(() => { modal.dataset.open = 'true'; });
                document.body.classList.add('overflow-hidden');
                setTimeout(() => (s ? f.nama : f.nis).focus(), 260);
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

            $('#siswa-add').addEventListener('click', () => openModal(null));

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
            let pendingSave = null;           // data yang menunggu konfirmasi

            const setConfirmNavIndex = (index, moveFocus = true) => {
                confirmNavIndex = index;
                confirmBtns.forEach((btn, i) => btn.classList.toggle('sconfirm-key-selected', i === index));
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

            /* ---------- Simpan ke server ---------- */
            const baseUrl = {!! json_encode(route('dashboard.admin.data-siswa.index')) !!};
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

            const saveRow = async (payload) => {
                const isAdd = mode === 'add';
                const loading = showLoading(isAdd ? 'Menyimpan data siswa...' : 'Memperbarui data siswa...');

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

                    const saved = result.data.siswa;
                    const index = rows.findIndex((r) => r.id === saved.id);

                    if (index >= 0) rows[index] = saved;
                    else rows.push(saved);

                    rows.sort((a, b) => Number(b.tingkat) - Number(a.tingkat)
                        || String(a.kelas).localeCompare(String(b.kelas))
                        || a.nama.localeCompare(b.nama));

                    flashId = saved.id;

                    // Bersihkan pencarian & filter supaya baris baru pasti terlihat
                    if (isAdd) {
                        searchEl.value = '';
                        state.q = '';
                        statusEl.value = jurusanEl.value = tingkatEl.value = rombelEl.value = '';
                        state.status = state.jurusan = state.tingkat = state.rombel = '';
                        state.page = Math.ceil(rows.length / perPage);
                    }

                    refreshFilters();
                    syncSearchClear();
                    syncBadge();
                    updateStats();
                    render();
                    setBusy(false);

                    // Formulir ditutup setelah data tersimpan di server
                    confirmReturnFocus = null;
                    closeConfirm();
                    closeModal();

                    hideLoading(loading);

                    const note = result.data.password
                        ? ` Password awal: ${result.data.password}`
                        : '';

                    toast((isAdd ? `${saved.nama} berhasil ditambahkan.${note}`
                                 : `${saved.nama} berhasil diperbarui.${note}`));
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

                // Notifikasi sukses sudah ditangani di dalam saveRow()
                saveRow(payload);
            });

            form.addEventListener('submit', (e) => {
                e.preventDefault();

                const isAdd = mode === 'add';
                const s     = isAdd ? null : rows.find((r) => r.id === editingId);
                if (!isAdd && !s) return;

                const nis      = f.nis.value.trim();
                const nama     = f.nama.value.trim();
                const email    = f.email.value.trim().toLowerCase();
                const kelas    = f.kelas.value.trim().replace(/\s*-\s*/g, '-');
                const jurusan  = f.jurusan.value.trim();
                const status   = f.status.value;
                const password = f.password.value;

                const missing = !nis ? f.nis : !nama ? f.nama : !email ? f.email
                    : !kelas ? f.kelas : !jurusan ? f.jurusan : null;
                if (missing) {
                    showError('NIS, nama, email, kelas, dan jurusan wajib diisi.');
                    missing.focus();
                    return;
                }

                // Kelas ditulis angka saja (12 atau 12-1), sama seperti server.
                if (!kelasRegex.test(kelas)) {
                    showError(`Format kelas harus tingkat saja (${kelasContoh}) atau tingkat-nomor rombel (${kelasContohRombel}).`);
                    f.kelas.focus();
                    return;
                }

                // Pola email sederhana: ada tepat satu "@", ada domain, ada titik setelahnya
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
                    showError('Format email belum benar, contoh: nama@saskansa.sch.id');
                    f.email.focus();
                    return;
                }

                const selfId = isAdd ? null : editingId;

                const dupNis = rows.find((r) => String(r.nis) === nis && r.id !== selfId);
                if (dupNis) {
                    showError(`NIS ${nis} sudah dipakai oleh ${dupNis.nama}.`);
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
                    payload: { nis, nama, email, kelas, jurusan, status, password },
                    isAdd,
                    nama,
                };

                openConfirm(isAdd
                    ? { title: 'Yakin ingin menambahkan data?', text: `Data ${nama} akan ditambahkan ke daftar siswa.` }
                    : { title: 'Yakin ingin menyimpan perubahan?', text: `Perubahan data ${nama} akan disimpan.` });
            });

            root.addEventListener('click', async (e) => {
                const btn = e.target.closest('[data-action]');
                if (!btn) return;

                const id = Number(btn.dataset.id);
                const s  = rows.find((r) => r.id === id);
                if (!s) return;

                if (btn.dataset.action === 'edit') {
                    openModal(s);
                    return;
                }

                // Hapus data siswa
                let ok;
                if (window.Swal) {
                    ok = (await Swal.fire({
                        icon: 'warning',
                        title: 'Hapus data siswa?',
                        text: `${s.nama} akan dihapus dari daftar.`,
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Hapus',
                        cancelButtonText: 'Batal',
                    })).isConfirmed;
                } else {
                    ok = confirm(`Hapus data ${s.nama}?`);
                }
                if (!ok) return;

                const loading = showLoading('Menghapus data siswa...');

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
                    render();

                    hideLoading(loading);
                    toast(`Data ${s.nama} berhasil dihapus.`);
                } catch (e) {
                    hideLoading(loading);
                    alertError('Gagal Menghapus', 'Tidak dapat terhubung ke server. Silakan coba lagi.');
                }
            });

            // Export lewat fetch (bukan navigasi biasa) supaya tombol bisa menampilkan proses
            // dan tahu file sudah benar-benar diterima.
            const exportUrl = {!! json_encode(route('dashboard.admin.data-siswa.export')) !!};

            const tombolExport = $('#siswa-export');
            const labelExport = tombolExport.querySelector('[data-ekspor-label]');
            const LABEL_AWAL = labelExport.textContent.trim();
            const KEMBALI_SUKSES_MS = 2400;

            let exportState = 'idle';
            let exportTimer = null;

            const setExportState = (state, text) => {
                clearTimeout(exportTimer);
                exportState = state;
                tombolExport.dataset.state = state;
                tombolExport.disabled = state === 'muat';

                if (text) labelExport.textContent = text;
            };

            // Nama file diambil dari header server agar sama persis dengan nama yang disiapkan server.
            const namaBerkas = (disposisi) => {
                const cocok = /filename="?([^";]+)"?/i.exec(disposisi || '');

                return cocok ? cocok[1] : 'data-siswa.xlsx';
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
                const rombel = state.rombel
                    ? rombelDipilih(state.jurusan, state.tingkat, state.rombel)
                    : null;

                for (const key of ['q', 'status', 'jurusan', 'tingkat', 'rombel']) {
                    if (state[key]) params.set(key, state[key]);
                }

                if (rombel) params.set('rombel_match', rombel.match);

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

                    const total = Number(respons.headers.get('X-Total-Siswa') || 0);

                    unduhBlob(await respons.blob(), namaBerkas(respons.headers.get('Content-Disposition')));

                    setExportState('sukses', total > 0 ? `${total} siswa diunduh` : 'Tidak ada siswa');
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
            initReveal();
        })();
    </script>
@endsection
