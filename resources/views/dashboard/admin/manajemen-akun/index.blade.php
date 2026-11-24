@extends('layouts.dashboard')

@section('title', 'Manajemen Akun')
@section('subtitle', 'Dashboard Admin')

@section('content')
    <style>
        /* ===== Tombol export: tiga keadaan (diam, mengunduh, berhasil) =====
           Gaya semuanya dipandu atribut data-state, jadi JS cukup mengubah satu atribut. */

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

        /* ===== Modal Template Format Data ===== */
        .tpl-modal {
            z-index: 70;
            opacity: 0;
            pointer-events: none;
            transition: opacity .25s ease;
        }

        .tpl-modal[data-open="true"] {
            opacity: 1;
            pointer-events: auto;
        }

        .tpl-backdrop {
            opacity: 0;
            transition: opacity .25s ease;
            background: rgba(15, 61, 115, .6);
        }

        .tpl-modal[data-open="true"] .tpl-backdrop {
            opacity: 1;
        }

        .tpl-panel {
            opacity: 0;
            transform: translateY(16px) scale(.98);
            transition: opacity .28s cubic-bezier(.22, 1, .36, 1),
                        transform .28s cubic-bezier(.22, 1, .36, 1);
        }

        .tpl-modal[data-open="true"] .tpl-panel {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        @media (max-width: 639.98px) {
            .tpl-panel { transform: translateY(100%); }
        }

        .tpl-card {
            transition: border-color .18s ease, background-color .18s ease,
                        box-shadow .18s ease, transform .18s ease;
        }

        .tpl-card:hover {
            border-color: #BFDBFE;
            background-color: #F8FAFF;
        }

        .tpl-card:focus-visible {
            outline: 2px solid #1E86FF;
            outline-offset: 2px;
        }

        .tpl-card.is-selected {
            border-color: #34D399;
            background-color: #ECFDF5;
            box-shadow: 0 10px 24px -18px rgba(5, 150, 105, .65);
        }

        .tpl-card.is-selected:hover {
            border-color: #10B981;
            background-color: #ECFDF5;
        }

        .tpl-card.is-selected .tpl-card-icon {
            background-color: #D1FAE5;
            color: #047857;
        }

        .tpl-card .tpl-card-indicator {
            border: 2px solid #CBD5E1;
            background-color: #FFFFFF;
            color: transparent;
            transition: border-color .18s ease, background-color .18s ease,
                        color .18s ease, transform .18s ease;
        }

        .tpl-card.is-selected .tpl-card-indicator {
            border-color: #10B981;
            background-color: #10B981;
            color: #FFFFFF;
            transform: scale(1.05);
        }

        .tpl-download[disabled] {
            opacity: .55;
            cursor: not-allowed;
            box-shadow: none;
        }

        .tpl-download[data-loading="true"] {
            cursor: progress;
        }

        @media (prefers-reduced-motion: reduce) {
            .tpl-modal, .tpl-backdrop, .tpl-panel, .tpl-card,
            .tpl-card .tpl-card-indicator {
                transition: none;
            }
        }
    </style>

    <div id="akun-page">
        {{-- Toolbar --}}
        <div class="mb-6 flex flex-wrap items-center gap-2">
            <label class="inline-flex h-10 cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-blue-300 bg-white px-4 text-xs font-medium text-primary-60 transition hover:bg-blue-50 sm:h-9 sm:text-sm"
                   title="Pilih file .xlsx / .csv untuk impor">
                <x-lucide-upload class="h-4 w-4" />
                Import
                <input id="import-file" type="file" accept=".xlsx,.xls,.csv,.txt" class="hidden">
            </label>

            <button type="button" id="akun-export" data-state="idle"
               aria-live="polite"
               title="Unduh data akun yang sedang difilter"
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

            <button type="button" id="akun-template"
               title="Unduh template Excel untuk keperluan impor data"
               class="inline-flex h-10 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-blue-300 bg-white px-4 text-xs font-medium text-primary-60 transition hover:bg-blue-50 active:scale-[.98] sm:h-9 sm:text-sm">
                <x-lucide-file-spreadsheet class="h-4 w-4" />
                Template Format Data
            </button>

            <a id="akun-slip-link"
               data-base="{{ route('dashboard.admin.manajemen-akun.slips') }}"
               href="{{ route('dashboard.admin.manajemen-akun.slips', request()->only(['role','jurusan','tingkat','kelas','search'])) }}"
               class="inline-flex h-10 items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-primary-60 px-4 text-xs font-semibold text-white transition hover:bg-primary-70 sm:h-9 sm:text-sm">
                <x-lucide-mail class="h-4 w-4" />
                Slip Password
            </a>
        </div>

        {{-- Filter --}}
        <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm lg:flex-row lg:items-center">
            <div class="relative flex-1">
                <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                <input type="search" id="akun-search" placeholder="Cari nama atau email..." autocomplete="off"
                       class="h-10 w-full rounded-lg border border-slate-300 bg-white pl-10 pr-10 text-base text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20 sm:h-9 sm:text-sm [&::-webkit-search-cancel-button]:appearance-none [&::-webkit-search-decoration]:appearance-none">
                <button type="button" id="akun-search-clear" aria-label="Bersihkan pencarian"
                        class="absolute right-1.5 top-1/2 hidden -translate-y-1/2 items-center justify-center rounded-lg p-1.5 text-slate-400 transition-all duration-200 ease-out hover:bg-slate-400/15 hover:text-slate-600 focus-visible:outline-2 focus-visible:outline-blue-500 active:scale-90">
                    <x-lucide-x class="h-4 w-4" />
                </button>
            </div>

            <div class="grid grid-cols-2 gap-3 lg:flex lg:flex-wrap lg:items-center">
                <select id="akun-filter-role"
                        class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20 sm:h-9">
                    <option value="">Semua Peran</option>
                    <option value="guru" @selected(($filters['role'] ?? '') === 'guru')>Guru</option>
                    <option value="siswa" @selected(($filters['role'] ?? '') === 'siswa')>Siswa</option>
                    <option value="admin" @selected(($filters['role'] ?? '') === 'admin')>Admin</option>
                </select>

                <select id="akun-filter-jurusan"
                        class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20 sm:h-9">
                    <option value="">Semua Jurusan</option>
                    @foreach ($jurusanOptions as $jurusan)
                        <option value="{{ $jurusan }}" @selected(($filters['jurusan'] ?? '') === $jurusan)>{{ $jurusan }}</option>
                    @endforeach
                </select>

                <select id="akun-filter-tingkat" data-kaskade-tingkat
                        class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 sm:h-9">
                    <option value="">Semua Tingkat</option>
                    @foreach ($tingkatOptions as $tingkat)
                        <option value="{{ $tingkat }}" @selected(($filters['tingkat'] ?? '') === $tingkat)>{{ $tingkat }}</option>
                    @endforeach
                </select>

                <select id="akun-filter-kelas" data-kaskade-kelas
                        class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 sm:h-9">
                    <option value="">Semua Kelas</option>
                    @foreach ($kelasOptions as $kelas)
                        <option value="{{ $kelas }}" @selected(($filters['kelas'] ?? '') === $kelas)>{{ $kelas }}</option>
                    @endforeach
                </select>

                <button type="button" id="akun-filter-reset"
                        title="Reset filter"
                        class="inline-flex h-10 items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 active:scale-[.98] sm:h-9">
                    <x-lucide-rotate-ccw class="h-4 w-4" />
                    <span>Reset</span>
                </button>
            </div>
        </div>

        {{-- Tabel --}}
        <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60">
            {{-- Tabel: md ke atas --}}
            <div class="hidden overflow-x-auto overflow-y-hidden md:block">
                <table class="w-full text-center text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-4 text-left font-medium">No</th>
                            <th class="px-4 py-4 text-left font-medium">Nama</th>
                            <th class="px-4 py-4 font-medium">Email</th>
                            <th class="px-4 py-4 font-medium">Kelas</th>
                            <th class="px-4 py-4 font-medium">Peran</th>
                            <th class="px-4 py-4 font-medium">Status</th>
                            <th class="px-4 py-4 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody data-table-body class="divide-y divide-slate-100 text-slate-800"></tbody>
                </table>
            </div>

            {{-- Daftar kartu: HP --}}
            <div data-card-body class="divide-y divide-slate-100 overflow-hidden px-4 md:hidden"></div>

            {{-- Footer + pagination --}}
            <x-ui.table-footer mini label="akun" />
        </div>
    </div>

    {{-- ============ Modal Template Format Data ============ --}}
    <div
        id="akun-template-modal"
        data-open="false"
        aria-hidden="true"
        inert
        class="tpl-modal fixed inset-0 flex items-end justify-center overflow-y-auto overscroll-contain p-0 sm:items-center sm:p-4 lg:left-64"
        role="dialog"
        aria-modal="true"
        aria-labelledby="akun-template-title"
    >
        <div data-template-backdrop class="tpl-backdrop absolute inset-0"></div>

        <div
            data-template-panel
            class="tpl-panel relative z-10 flex max-h-[92dvh] w-full max-w-2xl flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:max-h-[88dvh] sm:rounded-2xl"
        >
            <header class="flex shrink-0 items-start gap-3 border-b border-slate-100 px-5 pb-4 pt-5 sm:px-7 sm:pt-6">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-primary-60">
                    <x-lucide-file-spreadsheet class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <h3 id="akun-template-title" class="text-lg font-bold leading-tight text-primary-70 sm:text-xl">
                        Unduh Template Format Data
                    </h3>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 sm:text-sm">
                        Pilih template Excel yang dibutuhkan untuk pengisian data oleh Guru atau pihak terkait.
                    </p>
                </div>
                <button type="button" data-template-close aria-label="Tutup"
                    class="-mr-1 -mt-1 shrink-0 rounded-lg p-1.5 text-primary-60 transition hover:bg-blue-50 active:scale-90">
                    <x-lucide-x class="h-5 w-5" />
                </button>
            </header>

            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 sm:px-7">
                <div class="flex items-start gap-2.5 rounded-xl border border-blue-100 bg-blue-50/70 px-3.5 py-3 text-xs leading-relaxed text-blue-800 sm:text-sm">
                    <x-lucide-info class="mt-0.5 h-4 w-4 shrink-0 text-primary-60" />
                    <p>
                        Jika memilih lebih dari satu template, seluruh file Excel akan digabungkan
                        dan diunduh sebagai satu file <span class="font-semibold">ZIP</span>.
                    </p>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                    <p data-template-count class="text-xs font-medium text-slate-500 sm:text-sm">
                        0 dari {{ count($templateOptions) }} template dipilih
                    </p>
                    <button type="button" data-template-select-all
                        class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary-60 transition hover:bg-blue-50 active:scale-[.98] sm:text-sm">
                        <x-lucide-check-check class="h-4 w-4" />
                        <span data-template-select-all-label>Pilih Semua Template</span>
                    </button>
                </div>

                <div class="mt-3 space-y-2.5" data-template-list>
                    @foreach ($templateOptions as $option)
                        <button type="button"
                            data-template-card="{{ $option['key'] }}"
                            data-template-file="{{ $option['file'] }}"
                            role="checkbox"
                            aria-checked="false"
                            class="tpl-card flex w-full items-center gap-3 rounded-xl border-2 border-slate-200 bg-white px-3.5 py-3 text-left sm:gap-4 sm:px-4">
                            <span class="tpl-card-icon flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-primary-60">
                                @switch($option['key'])
                                    @case('guru')
                                        <x-lucide-user-round class="h-5 w-5" />
                                        @break
                                    @case('siswa')
                                        <x-lucide-graduation-cap class="h-5 w-5" />
                                        @break
                                    @case('kelas')
                                        <x-lucide-school class="h-5 w-5" />
                                        @break
                                    @case('jurusan')
                                        <x-lucide-layers class="h-5 w-5" />
                                        @break
                                    @default
                                        <x-lucide-book-open class="h-5 w-5" />
                                @endswitch
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-slate-800">{{ $option['label'] }}</span>
                                <span class="mt-0.5 block text-xs leading-relaxed text-slate-500">{{ $option['description'] }}</span>
                                <span class="mt-1 block text-[11px] font-medium text-slate-400">{{ $option['file'] }}</span>
                            </span>
                            <span class="tpl-card-indicator flex h-6 w-6 shrink-0 items-center justify-center rounded-full" aria-hidden="true">
                                <x-lucide-check class="h-3.5 w-3.5" stroke-width="3" />
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>

            <footer class="flex shrink-0 flex-col gap-3 border-t border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                <p class="hidden text-xs text-slate-400 sm:block">
                    File template siap diimpor melalui tombol Import.
                </p>
                <div class="flex w-full flex-col-reverse gap-2 sm:w-auto sm:flex-row sm:items-center">
                    <button type="button" data-template-close
                        class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium text-slate-600 transition hover:bg-slate-50 active:scale-[.98]">
                        Batal
                    </button>
                    <button type="button" data-template-download disabled
                        class="tpl-download inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-primary-60 px-5 text-sm font-semibold text-white shadow-md shadow-primary-60/25 transition hover:bg-primary-70 active:scale-[.98]">
                        <x-lucide-download class="h-4 w-4" />
                        <span data-template-download-label>Unduh Template</span>
                    </button>
                </div>
            </footer>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('akun-page');
        if (!root) return;

        const payload = @json($payload);
        const rows = Array.isArray(payload.akun) ? payload.akun : [];
        const kaskadeAkun = payload.kaskade || [];
        const currentUserId = payload.currentUserId;
        const perPage = 10;

        const $ = (sel) => root.querySelector(sel);

        const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[c]));

        const state = { q: '', role: '', jurusan: '', tingkat: '', kelas: '', page: 1 };

        const awal = payload.filters || {};
        state.q       = awal.search || '';
        state.role    = awal.role || '';
        state.jurusan = awal.jurusan || '';
        state.tingkat = awal.tingkat || '';
        state.kelas   = awal.kelas || '';

        const tableBody = $('[data-table-body]');
        const cardBody  = $('[data-card-body]');
        const pag       = $('[data-pagination]');
        const info      = pag.querySelector('[data-info]');
        const pagesEl   = pag.querySelector('[data-pages]');
        const prevBtn   = pag.querySelector('[data-prev]');
        const nextBtn   = pag.querySelector('[data-next]');
        const label     = pag.dataset.label || 'akun';

        const searchEl  = $('#akun-search');
        const clearBtn  = $('#akun-search-clear');
        const roleEl    = $('#akun-filter-role');
        const jurusanEl = $('#akun-filter-jurusan');
        const tingkatEl = $('#akun-filter-tingkat');
        const kelasEl   = $('#akun-filter-kelas');
        const resetBtn  = $('#akun-filter-reset');
        const slipLink  = $('#akun-slip-link');

        // ===== Kaskade Jurusan -> Tingkat -> Kelas =====
        const tingkatJurusan = (jurusan) => {
            if (!jurusan) {
                const nilai = [];
                const ada = new Set();

                (Array.isArray(kaskadeAkun) ? kaskadeAkun : []).forEach((j) => {
                    (Array.isArray(j.tingkat) ? j.tingkat : []).forEach((t) => {
                        if (!ada.has(String(t.nilai))) {
                            ada.add(String(t.nilai));
                            nilai.push(t);
                        }
                    });
                });

                return nilai.sort((a, b) => Number(b.nilai) - Number(a.nilai));
            }

            const found = kaskadeAkun.find((j) => j.nama === jurusan);
            return found && Array.isArray(found.tingkat) ? found.tingkat : [];
        };

        const rombelTingkat = (jurusan, tingkat) => {
            const nilai = [];
            const ada = new Set();

            (Array.isArray(kaskadeAkun) ? kaskadeAkun : []).forEach((j) => {
                if (jurusan && j.nama !== jurusan) return;

                (Array.isArray(j.tingkat) ? j.tingkat : []).forEach((t) => {
                    if (String(t.nilai) !== String(tingkat)) return;

                    (Array.isArray(t.rombel) ? t.rombel : []).forEach((r) => {
                        if (!ada.has(String(r.nilai))) {
                            ada.add(String(r.nilai));
                            nilai.push(r);
                        }
                    });
                });
            });

            return nilai.sort((a, b) => {
                const aTingkat = Number(String(a.nilai).split('-')[0]);
                const bTingkat = Number(String(b.nilai).split('-')[0]);
                if (aTingkat !== bTingkat) return bTingkat - aTingkat;
                return (Number(String(a.nilai).split('-')[1] || 0)) - (Number(String(b.nilai).split('-')[1] || 0));
            });
        };

        const isiOpsi = (el, daftar, labelKosong, nilaiTerpilih) => {
            const lama = el.value;

            el.innerHTML = '';

            const kosong = document.createElement('option');
            kosong.value = '';
            kosong.textContent = labelKosong;
            el.appendChild(kosong);

            daftar.forEach((o) => {
                const opt = document.createElement('option');
                opt.value = String(o.nilai);
                opt.textContent = String(o.nilai);
                el.appendChild(opt);
            });

            if (nilaiTerpilih !== undefined && nilaiTerpilih !== '') {
                el.value = nilaiTerpilih;
            } else if (daftar.some((o) => String(o.nilai) === lama)) {
                el.value = lama;
            } else {
                el.value = '';
            }

            el.disabled = daftar.length === 0;
        };

        // ===== Filter =====
        const kelasCocok = (kelas, filter) => {
            const k = String(kelas ?? '').trim();
            const f = String(filter ?? '').trim();

            if (!f) return true;
            if (f.includes('-')) return k === f;

            return k === f || k.startsWith(f + '-');
        };

        function filtered() {
            const q = state.q.trim().toLowerCase();

            return rows.filter((u) => {
                if (state.role && u.role !== state.role) return false;
                if (state.jurusan && (u.jurusan || '') !== state.jurusan) return false;
                if (state.tingkat && !kelasCocok(u.kelas, state.tingkat)) return false;
                if (state.kelas && !kelasCocok(u.kelas, state.kelas)) return false;

                if (q) {
                    const hay = `${u.name ?? ''} ${u.email ?? ''}`.toLowerCase();
                    if (!hay.includes(q)) return false;
                }

                return true;
            });
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

        const ICON_RESET = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>';
        const ICON_INFO  = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>';

        const roleBadge = (role) => ({
            'admin': 'bg-blue-50 text-blue-700 ring-blue-200',
            'guru': 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'siswa': 'bg-amber-50 text-amber-700 ring-amber-200',
        }[role] ?? 'bg-slate-100 text-slate-600 ring-slate-200');

        const statusBadge = (mustChange) => mustChange
            ? '<span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 ring-1 ring-amber-200"><span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Password awal</span>'
            : '<span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Sudah diubah</span>';

        const aksi = (u) => {
            if (String(u.id) === String(currentUserId)) {
                return `<span class="inline-flex items-center gap-1.5 text-xs text-slate-400">${ICON_INFO}<span>Akun Anda</span></span>`;
            }

            return `<button type="button" data-reset-password="${u.id}" data-name="${esc(u.name)}"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-blue-300 bg-white px-3 py-1.5 text-xs font-medium text-primary-60 transition hover:bg-blue-50 active:scale-95">
                        ${ICON_RESET}<span>Reset</span>
                    </button>`;
        };

        const rowTable = (u, no) => `
            <tr class="transition hover:bg-slate-50/60">
                <td class="px-4 py-5 text-left text-slate-400">${no}</td>
                <td class="px-4 py-5">
                    <div class="flex items-center gap-3 text-left">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-10 text-xs font-bold text-primary-70">${esc((u.name || '?').charAt(0).toUpperCase())}</span>
                        <span class="font-medium text-slate-800">${esc(u.name)}</span>
                    </div>
                </td>
                <td class="px-4 py-5 text-slate-600">${esc(u.email)}</td>
                <td class="px-4 py-5 text-slate-600">${u.kelas ? esc(u.kelas) : '-'}</td>
                <td class="px-4 py-5"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize ring-1 ${roleBadge(u.role)}">${esc(u.role)}</span></td>
                <td class="px-4 py-5">${statusBadge(u.must_change_password)}</td>
                <td class="px-4 py-5">${aksi(u)}</td>
            </tr>`;

        const rowCard = (u) => `
            <div class="py-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-slate-900" title="${esc(u.name)}">${esc(u.name)}</p>
                        <p class="mt-0.5 truncate text-xs text-slate-500" title="${esc(u.email)}">${esc(u.email)}</p>
                    </div>
                    <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold capitalize ring-1 ${roleBadge(u.role)}">${esc(u.role)}</span>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">${u.kelas ? 'Kelas ' + esc(u.kelas) : 'Tanpa kelas'}</span>
                    ${statusBadge(u.must_change_password)}
                </div>
                <div class="mt-3">${aksi(u)}</div>
            </div>`;

        const syncSearchClear = () => clearBtn.classList.toggle('hidden', searchEl.value.length === 0);

        const syncSlipLink = () => {
            if (!slipLink) return;

            const params = new URLSearchParams();

            for (const key of ['role', 'jurusan', 'tingkat', 'kelas']) {
                if (state[key]) params.set(key, state[key]);
            }

            const q = state.q.trim();
            if (q) params.set('search', q);

            const query = params.toString();
            slipLink.href = slipLink.dataset.base + (query ? '?' + query : '');
        };

        function render() {
            const list  = filtered();
            const total = list.length;
            const last  = Math.max(1, Math.ceil(total / perPage));
            state.page = Math.min(state.page, last);

            const start = (state.page - 1) * perPage;
            const slice = list.slice(start, start + perPage);

            if (!slice.length) {
                const msg = 'Tidak ada akun ditemukan';
                tableBody.innerHTML = `<tr><td colspan="7" class="px-4 py-12 text-center text-sm text-slate-400">${msg}</td></tr>`;
                cardBody.innerHTML  = `<p class="py-10 text-center text-sm text-slate-400">${msg}</p>`;
            } else {
                tableBody.innerHTML = slice.map((u, i) => rowTable(u, start + i + 1)).join('');
                cardBody.innerHTML  = slice.map((u) => rowCard(u)).join('');
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

            syncSlipLink();
        }

        // Samakan kontrol filter dengan state saat halaman dibuka / direset.
        const syncControls = () => {
            searchEl.value  = state.q;
            roleEl.value    = state.role;
            jurusanEl.value = state.jurusan;

            const daftarTingkat = tingkatJurusan(state.jurusan);
            if (state.tingkat && !daftarTingkat.some((o) => String(o.nilai) === state.tingkat)) {
                daftarTingkat.push({ nilai: state.tingkat });
            }

            const daftarKelas = rombelTingkat(state.jurusan, state.tingkat);
            if (state.kelas && !daftarKelas.some((o) => String(o.nilai) === state.kelas)) {
                daftarKelas.push({ nilai: state.kelas });
            }

            isiOpsi(tingkatEl, daftarTingkat, 'Semua Tingkat', state.tingkat);
            isiOpsi(kelasEl, daftarKelas, 'Semua Kelas', state.kelas);

            syncSearchClear();
        };

        searchEl.addEventListener('input', () => {
            state.q = searchEl.value;
            state.page = 1;
            syncSearchClear();
            render();
        });

        searchEl.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            render();
        });

        clearBtn.addEventListener('click', () => {
            searchEl.value = '';
            state.q = '';
            state.page = 1;
            syncSearchClear();
            render();
            searchEl.focus();
        });

        roleEl.addEventListener('change', () => {
            state.role = roleEl.value;
            state.page = 1;
            render();
        });

        jurusanEl.addEventListener('change', () => {
            state.jurusan = jurusanEl.value;
            state.tingkat = '';
            state.kelas = '';

            isiOpsi(tingkatEl, tingkatJurusan(state.jurusan), 'Semua Tingkat', '');
            isiOpsi(kelasEl, rombelTingkat(state.jurusan, ''), 'Semua Kelas', '');

            state.page = 1;
            render();
        });

        tingkatEl.addEventListener('change', () => {
            state.tingkat = tingkatEl.value;
            state.kelas = '';

            isiOpsi(kelasEl, rombelTingkat(state.jurusan, state.tingkat), 'Semua Kelas', '');

            state.page = 1;
            render();
        });

        kelasEl.addEventListener('change', () => {
            state.kelas = kelasEl.value;
            state.page = 1;
            render();
        });

        resetBtn.addEventListener('click', () => {
            Object.assign(state, { q: '', role: '', jurusan: '', tingkat: '', kelas: '', page: 1 });
            syncControls();
            render();
        });

        prevBtn.addEventListener('click', () => { state.page--; render(); });
        nextBtn.addEventListener('click', () => { state.page++; render(); });

        pagesEl.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-page]');
            if (btn) { state.page = Number(btn.dataset.page); render(); }
        });

        // ===== Reset password (delegasi karena baris dirender ulang) =====
        const resetPasswordUrl = '/dashboard/admin/manajemen-akun/{id}/reset-password';

        root.addEventListener('click', async (e) => {
            const button = e.target.closest('[data-reset-password]');
            if (!button) return;

            const id = button.dataset.resetPassword;
            const name = button.dataset.name;

            const { value: confirmText } = await Swal.fire({
                title: 'Reset Password',
                text: `Reset password untuk ${name}? Akun wajib mengubah password saat login berikutnya.`,
                input: 'text',
                inputLabel: 'Ketik "reset" untuk konfirmasi',
                inputPlaceholder: 'reset',
                inputValidator: (value) => value !== 'reset' && 'Ketik "reset" untuk melanjutkan.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Reset Sekarang',
                cancelButtonText: 'Batal',
            });

            if (!confirmText) return;

            button.disabled = true;

            try {
                const res = await fetch(resetPasswordUrl.replace('{id}', id), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                });

                const data = await res.json();

                if (res.ok && data.status === 'success') {
                    const target = rows.find((u) => String(u.id) === String(id));
                    if (target) target.must_change_password = true;
                    render();

                    Swal.fire({
                        icon: 'success',
                        title: 'Password Direset',
                        html: `Password baru untuk <b>${data.data.email}</b>:<br>
                               <code class="mt-2 inline-block rounded-lg bg-slate-100 px-3 py-1 font-mono text-sm text-slate-800">${data.data.password}</code>`,
                        saskansaModal: true,
                        showConfirmButton: true,
                        confirmButtonText: 'Tutup',
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: data.message || 'Terjadi kesalahan.' });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Gagal', text: 'Terjadi kesalahan jaringan.' });
            } finally {
                button.disabled = false;
            }
        });

        // ===== Export Excel =====
        const tombolExport = document.getElementById('akun-export');
        if (tombolExport) {
            const exportUrl = '{{ route('dashboard.admin.manajemen-akun.export-csv') }}';
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

            const namaBerkas = (disposisi) => {
                const cocok = /filename="?([^";]+)"?/i.exec(disposisi || '');

                return cocok ? cocok[1] : 'akun.xlsx';
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
                if (exportState === 'muat') return;

                // Mengikuti filter yang sedang aktif di layar.
                const params = new URLSearchParams();

                for (const key of ['role', 'jurusan', 'tingkat', 'kelas']) {
                    if (state[key]) params.set(key, state[key]);
                }

                const q = state.q.trim();
                if (q) params.set('search', q);

                const query = params.toString();

                setExportState('muat', 'Menyiapkan…');

                try {
                    const respons = await fetch(query ? `${exportUrl}?${query}` : exportUrl, {
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: { Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
                    });

                    const tipe = respons.headers.get('Content-Type') || '';

                    if (!respons.ok || !tipe.includes('spreadsheet')) {
                        throw new Error(
                            respons.status === 403
                                ? 'Anda tidak punya akses untuk mengunduh data ini.'
                                : 'Sesi Anda sudah habis. Muat ulang halaman lalu coba lagi.'
                        );
                    }

                    const total = Number(respons.headers.get('X-Total-Akun') || 0);

                    unduhBlob(await respons.blob(), namaBerkas(respons.headers.get('Content-Disposition')));

                    setExportState('sukses', total > 0 ? `${total} akun diunduh` : 'Tidak ada akun');
                    exportTimer = setTimeout(() => {
                        setExportState('idle', LABEL_AWAL);
                    }, KEMBALI_SUKSES_MS);
                } catch (galat) {
                    setExportState('idle', LABEL_AWAL);
                    Swal.fire({ icon: 'error', title: 'Gagal Mengunduh', text: galat.message || 'Tidak dapat terhubung ke server.' });
                }
            });
        }

        // ===== Import =====
        const importFile = document.getElementById('import-file');
        if (importFile) {
            const escapeHtml = (teks) => String(teks).replace(/[&<>"']/g, (karakter) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            })[karakter]);

            importFile.addEventListener('change', async () => {
                if (!importFile.files.length) return;

                const file = importFile.files[0];
                const form = new FormData();
                form.append('file', file);

                Swal.fire({
                    title: 'Mengimpor data...',
                    html: `
                        <div style="text-align:left;font-size:.875rem;color:#475569">
                            <div style="margin-bottom:.5rem">File: <b>${escapeHtml(file.name)}</b></div>
                            <div style="height:8px;background:#e2e8f0;border-radius:9999px;overflow:hidden">
                                <div id="swal-import-bar" style="height:100%;width:30%;background:#2563eb;border-radius:9999px;transition:width .25s ease"></div>
                            </div>
                            <div id="swal-import-text" style="margin-top:.5rem;font-size:.8rem;color:#64748b">Membaca dan memvalidasi file...</div>
                        </div>
                    `,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading(),
                });

                const bar = document.getElementById('swal-import-bar');
                const txt = document.getElementById('swal-import-text');
                let p = 30;
                const timer = setInterval(() => {
                    p = Math.min(p + 8, 90);
                    if (bar) bar.style.width = p + '%';
                    if (txt && p >= 60) txt.textContent = 'Menyimpan data ke database...';
                }, 350);

                try {
                    const res = await fetch('{{ route('dashboard.admin.manajemen-akun.import') }}', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        },
                        body: form,
                    });

                    const data = await res.json().catch(() => ({}));
                    clearInterval(timer);
                    if (bar) bar.style.width = '100%';

                    if (res.ok && data.status === 'success') {
                        const r = data.data || {};
                        const tipe = escapeHtml(r.type_label || 'Data');
                        const created = Number(r.created) || 0;
                        const skipped = Number(r.skipped) || 0;
                        const errorsCount = Number(r.errors) || 0;
                        const alasanGagal = Array.isArray(r.error_list) ? r.error_list : [];
                        const dilewati = Array.isArray(r.skipped_list) ? r.skipped_list : [];

                        const listBlock = (title, items, color, bg, border) => {
                            if (!items.length) return '';
                            return `
                                <div style="margin-top:.6rem">
                                    <div style="font-size:.75rem;font-weight:600;color:${color};margin-bottom:.25rem">${title} (${items.length})</div>
                                    <div style="max-height:180px;overflow:auto;text-align:left;padding:.5rem;background:${bg};border:1px solid ${border};border-radius:.5rem">
                                        ${items.map((e) => `<div style="font-size:.75rem;color:#334155;margin-bottom:.2rem">Baris ${escapeHtml(String(e.row ?? '?'))}: ${escapeHtml(String(e.reason ?? 'Tidak diketahui.'))}</div>`).join('')}
                                    </div>
                                </div>`;
                        };

                        let rincian = '';
                        if (r.truncated) {
                            const max = Number(r.max_rows) || 5000;
                            rincian += `<div style="margin-top:.5rem;color:#b45309;font-size:.8rem">Hanya ${max.toLocaleString('id-ID')} baris pertama yang diproses.</div>`;
                        }
                        rincian += listBlock('Detail Gagal', alasanGagal, '#b91c1c', '#fff7ed', '#fed7aa');
                        rincian += listBlock('Detail Dilewati', dilewati, '#b45309', '#fffbeb', '#fde68a');

                        const icon = (errorsCount > 0 && created === 0) ? 'error'
                            : (errorsCount > 0 || skipped > 0) ? 'warning'
                            : 'success';
                        const title = (errorsCount > 0 && created === 0) ? `Impor ${tipe} Gagal`
                            : (errorsCount > 0 || skipped > 0) ? `Impor ${tipe} Selesai Sebagian`
                            : `Impor ${tipe} Berhasil`;
                        const hasChanges = created > 0 || dilewati.some((e) => e); // created already checked

                        Swal.fire({
                            icon,
                            title,
                            html: `
                                <div style="text-align:left;font-size:.875rem;color:#334155">
                                    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.5rem;margin-bottom:.25rem">
                                        <div style="padding:.4rem .5rem;border-radius:.5rem;background:#ecfdf5"><div style="font-size:.7rem;color:#047857">Berhasil</div><div style="font-weight:700;color:#059669">${created}</div></div>
                                        <div style="padding:.4rem .5rem;border-radius:.5rem;background:#fffbeb"><div style="font-size:.7rem;color:#b45309">Dilewati</div><div style="font-weight:700;color:#d97706">${skipped}</div></div>
                                        <div style="padding:.4rem .5rem;border-radius:.5rem;background:#fef2f2"><div style="font-size:.7rem;color:#b91c1c">Gagal</div><div style="font-weight:700;color:#dc2626">${errorsCount}</div></div>
                                    </div>
                                    ${rincian}
                                </div>
                            `,
                            confirmButtonText: 'OK',
                            showConfirmButton: true,
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                        }).then(() => {
                            if (created > 0) location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Impor Gagal',
                            html: `<div style="text-align:left;font-size:.875rem;color:#334155">${escapeHtml(data.message || 'Terjadi kesalahan saat memproses file.')}</div>`,
                            confirmButtonText: 'OK',
                            showConfirmButton: true,
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                        });
                    }
                } catch (err) {
                    clearInterval(timer);
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: 'Terjadi kesalahan jaringan. Periksa koneksi lalu coba lagi.',
                        confirmButtonText: 'Tutup',
                    });
                } finally {
                    importFile.value = '';
                }
            });
        }

        // ===== Template Format Data =====
        const templateModal = document.getElementById('akun-template-modal');
        const templateOpen = document.getElementById('akun-template');

        if (templateModal && templateOpen) {
            const templateCards = Array.from(templateModal.querySelectorAll('[data-template-card]'));
            const templateCount = templateModal.querySelector('[data-template-count]');
            const templateSelectAll = templateModal.querySelector('[data-template-select-all]');
            const templateSelectAllLabel = templateModal.querySelector('[data-template-select-all-label]');
            const templateDownload = templateModal.querySelector('[data-template-download]');
            const templateDownloadLabel = templateModal.querySelector('[data-template-download-label]');
            const templateBackdrop = templateModal.querySelector('[data-template-backdrop]');
            const templateUrl = @json(route('dashboard.admin.manajemen-akun.templates'));

            let templateLastFocus = null;
            let templateDownloading = false;

            const selectedTemplates = () => templateCards.filter(
                (card) => card.getAttribute('aria-checked') === 'true'
            );

            const syncTemplates = () => {
                const total = templateCards.length;
                const selected = selectedTemplates();
                const allSelected = total > 0 && selected.length === total;

                templateCards.forEach((card) => {
                    card.classList.toggle('is-selected', card.getAttribute('aria-checked') === 'true');
                });

                templateCount.textContent = `${selected.length} dari ${total} template dipilih`;
                templateSelectAllLabel.textContent = allSelected ? 'Batalkan Semua Pilihan' : 'Pilih Semua Template';
                templateSelectAll.setAttribute('aria-pressed', allSelected ? 'true' : 'false');
                templateDownload.disabled = selected.length === 0 || templateDownloading;
                templateDownloadLabel.textContent = selected.length === 0
                    ? 'Unduh Template'
                    : `Unduh ${selected.length} Template`;
            };

            const setTemplateModal = (open) => {
                templateModal.dataset.open = open ? 'true' : 'false';
                templateModal.setAttribute('aria-hidden', open ? 'false' : 'true');

                if (open) {
                    templateModal.removeAttribute('inert');
                    templateLastFocus = document.activeElement;
                    document.body.style.overflow = 'hidden';
                    window.setTimeout(() => templateCards[0]?.focus({ preventScroll: true }), 120);
                } else {
                    templateModal.setAttribute('inert', '');
                    document.body.style.overflow = '';
                    templateLastFocus?.focus?.({ preventScroll: true });
                    templateLastFocus = null;
                }
            };

            templateOpen.addEventListener('click', () => setTemplateModal(true));

            templateModal.querySelectorAll('[data-template-close]').forEach((button) => {
                button.addEventListener('click', () => setTemplateModal(false));
            });

            templateBackdrop.addEventListener('click', () => setTemplateModal(false));

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && templateModal.dataset.open === 'true' && !templateDownloading) {
                    setTemplateModal(false);
                }
            });

            templateCards.forEach((card) => {
                card.addEventListener('click', () => {
                    const checked = card.getAttribute('aria-checked') === 'true';
                    card.setAttribute('aria-checked', checked ? 'false' : 'true');
                    syncTemplates();
                });
            });

            templateSelectAll.addEventListener('click', () => {
                const shouldSelect = selectedTemplates().length !== templateCards.length;
                templateCards.forEach((card) => {
                    card.setAttribute('aria-checked', shouldSelect ? 'true' : 'false');
                });
                syncTemplates();
            });

            templateDownload.addEventListener('click', async () => {
                const selected = selectedTemplates();
                if (selected.length === 0 || templateDownloading) return;

                const params = new URLSearchParams();
                selected.forEach((card) => params.append('jenis[]', card.dataset.templateCard));

                templateDownloading = true;
                templateDownload.dataset.loading = 'true';
                templateDownload.setAttribute('aria-busy', 'true');
                templateDownload.disabled = true;
                templateDownloadLabel.textContent = 'Menyiapkan...';

                try {
                    const response = await fetch(`${templateUrl}?${params.toString()}`, {
                        headers: {
                            Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/zip, */*',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        let message = 'Template gagal dibuat. Silakan coba lagi.';
                        const contentType = response.headers.get('Content-Type') || '';

                        if (contentType.includes('application/json')) {
                            const data = await response.json().catch(() => null);
                            if (data?.message) message = data.message;
                        } else if (response.status === 422) {
                            message = 'Pilih minimal satu template.';
                        }

                        throw new Error(message);
                    }

                    const blob = await response.blob();
                    if (blob.size === 0) {
                        throw new Error('File template kosong. Silakan coba lagi.');
                    }

                    const disposition = response.headers.get('Content-Disposition') || '';
                    const match = disposition.match(/filename\*?=(?:UTF-8''|")?([^";]+)/i);
                    const fallbackName = selected.length > 1
                        ? 'Template_Format_Data_SASKANSA.zip'
                        : (selected[0].dataset.templateFile || 'Template_Format_Data.xlsx');
                    const filename = match
                        ? decodeURIComponent(match[1].replace(/"/g, '').trim())
                        : fallbackName;

                    const link = document.createElement('a');
                    const objectUrl = URL.createObjectURL(blob);
                    link.href = objectUrl;
                    link.download = filename;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1500);

                    Swal.fire({
                        icon: 'success',
                        title: 'Template Berhasil Diunduh!',
                        text: selected.length > 1
                            ? 'Beberapa template berhasil diunduh dalam satu file ZIP.'
                            : 'Template format data berhasil diunduh dan siap digunakan.',
                        showConfirmButton: false,
                        timer: 1800,
                        timerProgressBar: true,
                    });
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Mengunduh',
                        text: error instanceof Error ? error.message : 'Terjadi kesalahan. Silakan coba lagi.',
                        confirmButtonText: 'Tutup',
                    });
                } finally {
                    templateDownloading = false;
                    templateDownload.dataset.loading = 'false';
                    templateDownload.removeAttribute('aria-busy');
                    syncTemplates();
                }
            });

            syncTemplates();
        }

        // ===== Jalankan =====
        syncControls();
        render();
    });
</script>
@endpush
