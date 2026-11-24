@extends('layouts.dashboard')

@section('title', 'Backup Database')

@section('content')
    <div id="backup-page">

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
                        value-class="backup-stat"
                    />
                </div>
            @endforeach
        </div>

        {{-- Panel aksi: buat backup baru --}}
        <div data-reveal style="--d: .28s" class="mt-5 rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60 sm:mt-6">
            <div class="rounded-t-2xl bg-slate-50 p-4 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="text-base font-bold text-slate-900">Buat Backup Baru</h2>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">
                            File <span class="font-mono">.sql</span> berisi seluruh data sekolah: akun, nilai, kelas, dan master data.
                            Proses ini bisa memakan waktu beberapa detik pada database yang besar.
                        </p>
                    </div>

                    <button type="button" id="backup-create" data-state="idle"
                        class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-primary-60 px-4 text-xs font-medium text-white transition hover:bg-primary-70 active:scale-[.98] disabled:opacity-60 sm:h-9 sm:text-sm">
                        <x-lucide-database-backup data-create-ikon="idle" class="h-4 w-4" />
                        <svg data-create-ikon="muat" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                            <path d="M21 12a9 9 0 1 1-6.219-8.56" />
                        </svg>
                        <x-lucide-check data-create-ikon="sukses" class="h-4 w-4" />
                        <span data-create-label>Buat Backup Sekarang</span>
                    </button>
                </div>

                {{-- Catatan opsional --}}
                <div class="mt-4">
                    <label for="backup-catatan" class="mb-1.5 block text-xs font-medium text-slate-600">
                        Catatan <span class="font-normal text-slate-400">(opsional)</span>
                    </label>
                    <div class="relative">
                        <x-lucide-sticky-note class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-primary-60" />
                        <input type="text" id="backup-catatan" maxlength="191" autocomplete="off"
                            placeholder="Contoh: sebelum ubah data rapor semester genap"
                            class="h-11 w-full rounded-lg border border-slate-300 bg-white pl-10 pr-3 text-base text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20 sm:h-10 sm:text-sm">
                    </div>
                </div>
            </div>

            {{-- Informasi sumber database --}}
            <div class="grid grid-cols-1 gap-3 border-t border-slate-100 p-4 sm:grid-cols-3 sm:p-5">
                <div class="rounded-xl bg-slate-50 px-4 py-3">
                    <p class="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-slate-500">
                        <x-lucide-server class="h-3.5 w-3.5" />
                        Sumber
                    </p>
                    <p class="mt-1 truncate font-mono text-sm font-medium text-slate-800" data-info="database" title="{{ $backupData['database'] }}">{{ $backupData['database'] }}</p>
                </div>

                <div class="rounded-xl bg-slate-50 px-4 py-3">
                    <p class="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-slate-500">
                        <x-lucide-database class="h-3.5 w-3.5" />
                        Driver
                    </p>
                    <p class="mt-1 font-mono text-sm font-medium text-slate-800" data-info="driver">{{ $backupData['driver'] }}</p>
                </div>

                <div class="rounded-xl bg-slate-50 px-4 py-3">
                    <p class="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-slate-500">
                        <x-lucide-layers class="h-3.5 w-3.5" />
                        Disimpan
                    </p>
                    <p class="mt-1 text-sm font-medium text-slate-800">
                        <span data-info="maks">{{ $backupData['maks'] }}</span> backup terbaru
                    </p>
                </div>
            </div>
        </div>

        {{-- Tabel riwayat --}}
        <div data-reveal style="--d: .34s" class="mt-5 rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60">

            {{-- Toolbar --}}
            <div class="rounded-t-2xl bg-slate-50 p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative w-full sm:w-72">
                        <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-700" />
                        <input type="search" id="backup-search" placeholder="Cari nama file atau catatan" autocomplete="off"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white pl-10 pr-10 text-base text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20 sm:h-9 sm:text-sm [&::-webkit-search-cancel-button]:appearance-none [&::-webkit-search-decoration]:appearance-none">
                        <button type="button" id="backup-search-clear" aria-label="Bersihkan pencarian"
                            class="absolute right-1.5 top-1/2 hidden -translate-y-1/2 items-center justify-center rounded-lg p-1.5 text-slate-400 transition-all duration-200 ease-out hover:bg-slate-400/15 hover:text-slate-600 focus-visible:outline-2 focus-visible:outline-blue-500 active:scale-90">
                            <x-lucide-x class="h-4 w-4" />
                        </button>
                    </div>

                    <p data-info-summary class="text-xs text-slate-500"></p>
                </div>
            </div>

            {{-- Tabel: md ke atas --}}
            <div class="hidden overflow-x-auto overflow-y-hidden border-t border-slate-100 md:block">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-4 font-medium">No</th>
                            <th class="px-4 py-4 font-medium">File Backup</th>
                            <th class="px-4 py-4 font-medium">Dibuat</th>
                            <th class="px-4 py-4 font-medium">Ukuran</th>
                            <th class="px-4 py-4 font-medium">Tabel</th>
                            <th class="px-4 py-4 font-medium">Catatan</th>
                            <th class="px-4 py-4 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody data-table-body class="divide-y divide-slate-100 text-slate-800"></tbody>
                </table>
            </div>

            {{-- Daftar kartu: HP --}}
            <div data-card-body class="divide-y divide-slate-100 overflow-hidden border-t border-slate-100 px-4 md:hidden"></div>

            {{-- Footer + pagination --}}
            <x-ui.table-footer mini label="backup" />
        </div>
    </div>

    {{-- ============ Popup Konfirmasi Hapus ============ --}}
    <div id="backup-confirm-modal" data-open="false" aria-hidden="true" inert
        class="kconfirm fixed inset-0 z-70 flex items-center justify-center p-4">
        <div data-confirm-backdrop class="kconfirm-backdrop absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>

        <div data-confirm-panel role="dialog" aria-modal="true" aria-labelledby="backup-confirm-title"
            class="kconfirm-panel relative z-10 max-h-full w-full max-w-sm overflow-y-auto rounded-2xl bg-white p-5 text-center shadow-2xl sm:p-6">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-50 text-red-600">
                <x-lucide-trash-2 class="h-5 w-5" />
            </div>

            <h3 id="backup-confirm-title" data-confirm-title class="text-base font-semibold text-slate-900">
                Yakin ingin menghapus backup ini?
            </h3>
            <p data-confirm-text class="mt-1.5 text-sm text-slate-500">
                File .sql akan dihapus permanen dan tidak bisa dikembalikan.
            </p>

            <div class="mt-6 flex gap-3">
                <button type="button" data-confirm-no data-key-nav
                    class="flex-1 rounded-xl border border-slate-200 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-400 sm:py-2.5">
                    Batal
                </button>
                <button type="button" data-confirm-yes data-key-nav
                    class="flex-1 rounded-xl bg-red-600 py-3 text-sm font-semibold text-white transition hover:bg-red-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 sm:py-2.5">
                    Ya, Hapus
                </button>
            </div>

            <p class="mt-3 text-center text-[11px] text-slate-400">
                Gunakan <kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">←</kbd>
                <kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">→</kbd>
                untuk memilih,
                <kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">Enter</kbd> untuk mengonfirmasi,
                <kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">Esc</kbd> untuk membatalkan.
            </p>
        </div>
    </div>

    @push('scripts')
        <style>
            /* ===== Reveal saat elemen masuk layar ===== */
            #backup-page.reveal-ready [data-reveal] {
                opacity: 0;
                transform: translateY(10px);
            }
            #backup-page.reveal-ready [data-reveal].is-visible {
                opacity: 1;
                transform: none;
                transition: opacity .45s ease var(--d, 0s), transform .45s ease var(--d, 0s);
            }

            /* ===== Tombol buat backup: tiga status (diam / memuat / selesai) ===== */
            [data-create-ikon] {
                display: none;
            }
            #backup-create[data-state="idle"] [data-create-ikon="idle"],
            #backup-create[data-state="muat"] [data-create-ikon="muat"],
            #backup-create[data-state="sukses"] [data-create-ikon="sukses"] {
                display: block;
            }
            #backup-create[data-state="muat"] {
                cursor: progress;
            }
            #backup-create[data-state="sukses"] {
                background: #059669;
            }

            /* ===== Baris dan kartu daftar ===== */
            [data-table-body][data-anim="on"] .backup-row,
            [data-card-body][data-anim="on"] .backup-card {
                animation: backup-row-in .28s ease both;
                animation-delay: calc(var(--i, 0) * .04s);
            }
            @keyframes backup-row-in {
                from { opacity: 0; transform: translateY(6px); }
                to { opacity: 1; transform: none; }
            }

            .backup-file {
                transition: background-color .2s ease;
            }
            .backup-row:hover .backup-file {
                background-color: #EFF6FF;
            }

            /* Baris yang baru dibuat berkedip singkat supaya mudah dilihat. */
            .backup-row[data-flash="true"] .backup-file,
            .backup-card[data-flash="true"] {
                animation: backup-flash 1.4s ease;
            }
            @keyframes backup-flash {
                0%, 100% { background-color: transparent; }
                25% { background-color: #DBEAFE; }
            }

            .backup-empty {
                animation: none;
            }

            /* ===== Tombol baris ===== */
            .backup-btn {
                transition: transform .15s ease, background-color .2s ease, color .2s ease, opacity .2s ease;
            }
            .backup-btn:hover {
                transform: translateY(-1px);
            }
            .backup-btn:active {
                transform: translateY(0) scale(.96);
            }
            .backup-btn:disabled {
                opacity: .45;
                cursor: not-allowed;
                transform: none;
            }

            /* ===== Konfirmasi ===== */
            .kconfirm-backdrop {
                opacity: 0;
                transition: opacity .25s ease;
            }
            .kconfirm-panel {
                opacity: 0;
                transform: translateY(16px) scale(.96);
                transition: opacity .25s ease, transform .25s ease;
            }
            .kconfirm[data-open="true"] .kconfirm-backdrop {
                opacity: 1;
            }
            .kconfirm[data-open="true"] .kconfirm-panel {
                opacity: 1;
                transform: none;
            }
            .kconfirm[data-key-nav].backup-key-selected {
                outline: 2px solid #ef4444;
                outline-offset: 2px;
            }

            @media (prefers-reduced-motion: reduce) {
                [data-reveal], .backup-row, .backup-card,
                .backup-row[data-flash="true"] .backup-file, .backup-card[data-flash="true"],
                .kconfirm-backdrop, .kconfirm-panel, .backup-file {
                    transition: none;
                    animation: none;
                }
                .kconfirm[data-open="true"] .kconfirm-panel { opacity: 1; transform: none; }
            }
        </style>

        <script>
            (function () {
                const data = @json($backupData);
                let rows = (data.backup || []).slice();
                const perPage = 5;

                const root = document.getElementById('backup-page');
                const $ = (sel) => root.querySelector(sel);

                const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
                }[c]));

                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

                const state = { q: '', page: 1 };

                // ID backup yang baru dibuat, supaya barisnya bisa diberi FLASH
                let flashId = null;

                const tableBody = $('[data-table-body]');
                const cardBody = $('[data-card-body]');
                const pag = $('[data-pagination]');
                const info = pag.querySelector('[data-info]');
                const pagesEl = pag.querySelector('[data-pages]');
                const prevBtn = pag.querySelector('[data-prev]');
                const nextBtn = pag.querySelector('[data-next]');
                const label = pag.dataset.label || 'backup';
                const summaryEl = $('[data-info-summary]');
                const searchEl = $('#backup-search');
                const searchClear = $('#backup-search-clear');
                const createBtn = $('#backup-create');
                const createLabel = $('[data-create-label]');
                const catatanEl = $('#backup-catatan');

                const confirm = document.getElementById('backup-confirm-modal');
                const confirmTitle = confirm.querySelector('[data-confirm-title]');
                const confirmText = confirm.querySelector('[data-confirm-text]');
                const confirmNo = confirm.querySelector('[data-confirm-no]');
                const confirmYes = confirm.querySelector('[data-confirm-yes]');
                const confirmBtns = [confirmNo, confirmYes];

                let confirmNavIndex = 1;      // fokus awal = "Ya, Hapus"
                let confirmReturnFocus = null;
                let pendingDelete = null;     // backup yang menunggu konfirmasi

                // ===== Format tanggal Indonesia =====
                // Server menyimpan waktu UTC; dikonversi ke WIB di sini supaya label
                // "x jam lalu" tidak meleset 7 jam.
                const ZONA_WIB = @json(\App\Support\Waktu::ZONA);

                const BULAN_PENDEK = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
                    'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

                function keTanggal(v) {
                    if (!v) return null;
                    const d = new Date(v);
                    return Number.isNaN(d.getTime()) ? null : d;
                }

                // "01 Okt 2026"
                function tanggalIndo(v) {
                    const d = keTanggal(v);
                    if (!d) return '-';

                    const bagian = {};
                    for (const p of d.toLocaleDateString('id-ID', {
                        timeZone: ZONA_WIB, day: '2-digit', month: 'short', year: 'numeric',
                    }).match(/(\d+)|(\p{L}+)/gu) ?? []) {
                        if (!bagian.hari && /^\d+$/.test(p)) bagian.hari = p;
                        else if (!bagian.bulan) bagian.bulan = p;
                        else bagian.tahun = p;
                    }

                    const bulan = BULAN_PENDEK.indexOf(
                        (bagian.bulan ?? '').replace('.', '').toLowerCase()
                    );

                    return [bagian.hari, bulan >= 0 ? BULAN_PENDEK[bulan] : bagian.bulan, bagian.tahun]
                        .filter(Boolean).join(' ');
                }

                // Jam saja, format 24 jam, contoh "17:24".
                function jamIndo(v) {
                    const d = keTanggal(v);
                    if (!d) return '';
                    return d.toLocaleTimeString('id-ID', {
                        timeZone: ZONA_WIB, hour: '2-digit', minute: '2-digit', hour12: false,
                    });
                }

                // Selisih relatif agar admin tahu backup terakhir sudah berapa lama.
                function lalu(v) {
                    const tanggal = keTanggal(v);
                    if (!tanggal) return '';
                    // Dijaga agar tidak negatif kalau jam server meleset sedikit.
                    const d = Math.max(0, Math.floor((Date.now() - tanggal.getTime()) / 1000));
                    if (d < 60) return 'baru saja';
                    if (d < 3600) return `${Math.floor(d / 60)} menit lalu`;
                    if (d < 86400) return `${Math.floor(d / 3600)} jam lalu`;
                    if (d < 2592000) return `${Math.floor(d / 86400)} hari lalu`;
                    return `${Math.floor(d / 2592000)} bulan lalu`;
                }

                const urlIndex = @json(route('dashboard.admin.backup-database.index'));
                const urlDownload = (id) => `${urlIndex}/${id}/unduh`;
                const urlDelete = (id) => `${urlIndex}/${id}`;

                // ===== Ikon (SVG inline supaya tidak perlu render ulang lewat blade) =====
                const ICON_DOWNLOAD = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>';
                const ICON_TRASH = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>';

                const pill = (cls, text) =>
                    `<span class="inline-block whitespace-nowrap rounded-full px-2.5 py-0.5 text-[11px] font-medium ${cls}">${esc(text)}</span>`;

                // File hilang dari storage ditandai jelas; tombol unduh dimatikan agar tidak menunggu unduhan gagal.
                const badgeStatus = (b) => b.ada_file
                    ? pill('bg-emerald-100 text-emerald-700', 'Tersimpan')
                    : pill('bg-red-100 text-red-600', 'File Hilang');

                const actions = (b) => `
                    <div class="inline-flex items-center justify-end gap-2">
                        <a href="${esc(urlDownload(b.id))}" ${b.ada_file ? '' : 'aria-disabled="true" tabindex="-1"'}
                           data-action="download" data-id="${b.id}"
                           class="backup-btn inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-white px-3 py-1.5 text-xs font-medium text-primary-70 transition hover:bg-blue-50 ${b.ada_file ? '' : 'pointer-events-none opacity-45'}"
                           title="${b.ada_file ? 'Unduh file .sql' : 'File sudah tidak ada di penyimpanan'}">
                            ${ICON_DOWNLOAD}
                            Unduh
                        </a>
                        <button type="button" data-action="delete" data-id="${b.id}"
                            class="backup-btn inline-flex h-8 w-8 items-center justify-center rounded-lg border border-red-300 bg-white text-red-500 transition hover:bg-red-50 active:scale-90"
                            aria-label="Hapus ${esc(b.nama)}" title="Hapus ${esc(b.nama)}">
                            ${ICON_TRASH}
                        </button>
                    </div>`;

                const rowTable = (b, no, i) => {
                    const relatif = lalu(b.tanggal);

                    return `
                    <tr data-row-id="${b.id}" class="backup-row" style="--i:${i}">
                        <td class="px-4 py-4 text-slate-400 tabular-nums">${no}</td>
                        <td class="px-4 py-4">
                            <div class="backup-file -mx-2 rounded-lg px-2 py-1 transition-colors">
                                <p class="flex items-center gap-2 font-mono text-xs font-medium text-slate-800">
                                    <span class="truncate" title="${esc(b.nama)}">${esc(b.nama)}</span>
                                </p>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    ${badgeStatus(b)}
                                    ${b.pembuat ? pill('bg-slate-100 text-slate-500', b.pembuat) : ''}
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <span class="block text-xs font-medium text-slate-700">${esc(tanggalIndo(b.tanggal))}</span>
                            <span class="mt-0.5 block font-mono text-[11px] text-slate-400">${esc(jamIndo(b.tanggal))} WIB${relatif ? ` (${esc(relatif)})` : ''}</span>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-xs text-slate-600 tabular-nums">${esc(b.ukuran_teks)}</td>
                        <td class="px-4 py-4 text-xs text-slate-600 tabular-nums">${esc(b.jumlah_tabel)}</td>
                        <td class="px-4 py-4">
                            <p class="max-w-[16rem] truncate text-xs text-slate-600" title="${esc(b.catatan || '')}">${esc(b.catatan || '-')}</p>
                        </td>
                        <td class="px-4 py-4">${actions(b)}</td>
                    </tr>`;
                };

                const rowCard = (b, i) => `
                    <div data-row-id="${b.id}" class="backup-card py-4" style="--i:${i}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-mono text-xs font-medium text-slate-800" title="${esc(b.nama)}">${esc(b.nama)}</p>
                                <p class="mt-1 text-xs text-slate-500">${esc(tanggalIndo(b.tanggal))} · ${esc(jamIndo(b.tanggal))} WIB · ${esc(b.ukuran_teks)}</p>
                            </div>
                            ${badgeStatus(b)}
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                            ${pill('bg-slate-100 text-slate-500', b.jumlah_tabel + ' tabel')}
                            ${pill('bg-slate-100 text-slate-500', b.durasi_teks)}
                            ${b.pembuat ? pill('bg-slate-100 text-slate-500', b.pembuat) : ''}
                        </div>
                        ${b.catatan ? `<p class="mt-2 text-xs italic text-slate-500">${esc(b.catatan)}</p>` : ''}
                        <div class="mt-3">${actions(b)}</div>
                    </div>`;

                // ===== Filter =====
                function filtered() {
                    const q = state.q.trim().toLowerCase();
                    if (!q) return rows;

                    return rows.filter((b) => [b.nama, b.catatan, b.pembuat, b.database]
                        .filter((v) => v !== null && v !== undefined)
                        .join(' ')
                        .toLowerCase()
                        .includes(q));
                }

                // Baris ringkasan ikut menyesuaikan isi daftar, termasuk setelah backup dibuat/dihapus.
                function syncSummary() {
                    const tampil = filtered().length;

                    summaryEl.textContent = rows.length === 0
                        ? 'Belum ada backup.'
                        : (tampil === rows.length
                            ? `${rows.length} file tersimpan di ${data.maks} slot rotasi.`
                            : `${tampil} dari ${rows.length} file cocok dengan pencarian.`);
                }

                function totalUkuran() {
                    return rows.reduce((t, b) => t + (b.ukuran || 0), 0);
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

                // render = false dipakai saat mengetik di pencarian supaya baris tidak berkedip
                function render(animate = true) {
                    const list = filtered();
                    const total = list.length;
                    const last = Math.max(1, Math.ceil(total / perPage));
                    state.page = Math.min(state.page, last);

                    const start = (state.page - 1) * perPage;
                    const slice = list.slice(start, start + perPage);

                    tableBody.dataset.anim = animate ? 'on' : 'off';
                    cardBody.dataset.anim = animate ? 'on' : 'off';

                    if (!slice.length) {
                        const msg = rows.length === 0
                            ? 'Belum ada backup. Tekan "Buat Backup Sekarang" untuk membuat yang pertama.'
                            : 'Tidak ada backup yang cocok dengan pencarian.';

                        tableBody.innerHTML = `<tr><td colspan="7" class="backup-empty px-4 py-12 text-center text-sm text-slate-400">${msg}</td></tr>`;
                        cardBody.innerHTML = `<p class="backup-empty py-10 text-center text-sm text-slate-400">${msg}</p>`;
                    } else {
                        tableBody.innerHTML = slice.map((b, i) => rowTable(b, start + i + 1, i)).join('');
                        cardBody.innerHTML = slice.map((b, i) => rowCard(b, i)).join('');
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
                    const to = Math.min(state.page * perPage, total);
                    info.textContent = `Menampilkan ${from}-${to} dari ${total} ${label}`;

                    pagesEl.innerHTML = pageList(state.page, last).map((n) => n === '...'
                        ? '<span class="px-1 text-slate-400">...</span>'
                        : `<button type="button" data-page="${n}" class="min-w-8 rounded-lg px-2.5 py-1.5 text-sm transition-colors duration-200 sm:min-w-0 sm:py-1 ${n === state.page ? ACTIVE : IDLE}">${n}</button>`
                    ).join('');

                    prevBtn.disabled = state.page === 1;
                    nextBtn.disabled = state.page === last;
                }

                // ===== Statistik kartu atas ikut menyesuaikan setelah backup dibuat/dihapus, tanpa muat ulang halaman. =====
                function setStat(key, value) {
                    const el = root.querySelector(`[data-stat="${key}"]`);
                    if (el) el.textContent = value;
                }

                function syncStats() {
                    setStat('total', String(rows.length));
                    setStat('ukuran', formatUkuran(totalUkuran()));
                    setStat('terakhir', rows.length ? tanggalIndo(rows[0].tanggal) : 'Belum ada');
                }

                // Sama persis dengan format di server supaya kartu dan daftar tidak pernah beda angka.
                function formatUkuran(byte) {
                    if (byte < 1024) return byte + ' B';
                    const satuan = ['KB', 'MB', 'GB', 'TB'];
                    let n = byte / 1024;
                    let i = 0;
                    while (n >= 1024 && i < satuan.length - 1) { n /= 1024; i++; }
                    const desimal = n >= 100 ? 0 : 1;
                    return n.toFixed(desimal).replace('.', ',') + ' ' + satuan[i];
                }

                // ===== Reveal saat elemen masuk layar =====
                function initReveal() {
                    if (!('IntersectionObserver' in window)) {
                        root.querySelectorAll('[data-reveal]').forEach((el) => el.classList.add('is-visible'));
                        return;
                    }

                    root.classList.add('reveal-ready');

                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach((entry) => {
                            if (!entry.isIntersecting) return;
                            entry.target.classList.add('is-visible');
                            observer.unobserve(entry.target);
                        });
                    }, { threshold: 0.1, rootMargin: '0px 0px -30px 0px' });

                    root.querySelectorAll('[data-reveal]').forEach((el) => observer.observe(el));
                }

                // ===== Loading & notifikasi =====
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

                const toast = (text) => {
                    if (!window.Swal) return;
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text,
                        showConfirmButton: false,
                        timer: 1600,
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

                // Pesan error server: validasi spesifik lebih dulu, baru pesan umum controller.
                const pesanError = (result, bawaan) => {
                    const errors = (result && result.errors) || {};
                    return Object.values(errors).flat().filter(Boolean)[0]
                        || (result && result.message)
                        || bawaan;
                };

                // ===== Konfirmasi hapus =====
                const setConfirmNavIndex = (index, moveFocus = true) => {
                    confirmNavIndex = index;
                    confirmBtns.forEach((btn, i) => btn.classList.toggle('backup-key-selected', i === index));
                    if (moveFocus) confirmBtns[index].focus();
                };

                const openConfirm = ({ title, text }) => {
                    confirmTitle.textContent = title;
                    confirmText.textContent = text;
                    confirmReturnFocus = document.activeElement;

                    confirm.removeAttribute('inert');
                    confirm.setAttribute('aria-hidden', 'false');
                    requestAnimationFrame(() => { confirm.dataset.open = 'true'; });
                    setTimeout(() => setConfirmNavIndex(1), 30);
                };

                const closeConfirm = () => {
                    if (confirm.dataset.open !== 'true') return;
                    pendingDelete = null;
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

                // ===== Aksi: buat backup =====
                let createBusy = false;

                createBtn.addEventListener('click', async () => {
                    // Dikunci supaya klik ganda tidak membuat dua file backup.
                    if (createBusy) return;
                    createBusy = true;

                    createBtn.disabled = true;
                    createBtn.dataset.state = 'muat';
                    createLabel.textContent = 'Membuat backup...';

                    const loading = showLoading('Mencadangkan database, mohon tunggu...');

                    try {
                        const res = await jsonRequest(urlIndex, 'POST', { catatan: catatanEl.value });
                        const result = await res.json().catch(() => null);

                        if (!res.ok || !result || result.status !== 'success') {
                            alertError('Backup Gagal', pesanError(result, 'Terjadi kesalahan saat membuat backup.'));
                            return;
                        }

                        const baru = result.data.backup;

                        // Pencarian aktif bisa menyembunyikan backup baru, jadi dikosongkan dulu.
                        searchEl.value = '';
                        state.q = '';
                        state.page = 1;
                        searchClear.classList.add('hidden');

                        // Backup baru selalu masuk paling atas.
                        rows.unshift(baru);
                        catatanEl.value = '';
                        flashId = baru.id;

                        render();
                        syncStats();
                        syncSummary();

                        createBtn.dataset.state = 'sukses';
                        createLabel.textContent = 'Backup Berhasil';
                        toast(result.message);
                    } catch (e) {
                        alertError('Backup Gagal', 'Tidak dapat menghubungi server. Periksa koneksi lalu coba lagi.');
                    } finally {
                        hideLoading(loading);
                        createBusy = false;
                        createBtn.disabled = false;

                        // Kembalikan tombol ke keadaan semula setelah centang hijau.
                        setTimeout(() => {
                            createBtn.dataset.state = 'idle';
                            createLabel.textContent = 'Buat Backup Sekarang';
                        }, 1600);
                    }
                });

                // ===== Aksi: unduh dan hapus (event delegation) =====
                root.addEventListener('click', (e) => {
                    const hapus = e.target.closest('[data-action="delete"]');
                    if (hapus) {
                        e.preventDefault();
                        const backup = rows.find((b) => String(b.id) === hapus.dataset.id);
                        if (!backup) return;

                        openConfirm({
                            title: 'Hapus backup ini?',
                            text: `File ${backup.nama} akan dihapus permanen dan tidak bisa diunduh lagi.`,
                        });

                        pendingDelete = backup;
                        return;
                    }
                });

                confirmYes.addEventListener('click', async () => {
                    if (!pendingDelete) { closeConfirm(); return; }

                    const backup = pendingDelete;
                    const loading = showLoading('Menghapus file backup...');

                    try {
                        const res = await jsonRequest(urlDelete(backup.id), 'DELETE');
                        const result = await res.json().catch(() => null);

                        if (!res.ok || !result || result.status !== 'success') {
                            alertError('Gagal Menghapus', pesanError(result, 'Terjadi kesalahan saat menghapus backup.'));
                            return;
                        }

                        rows = rows.filter((b) => b.id !== backup.id);

                        render();
                        syncStats();
                        syncSummary();

                        toast(result.message);
                    } catch (e) {
                        alertError('Gagal Menghapus', 'Tidak dapat menghubungi server. Periksa koneksi lalu coba lagi.');
                    } finally {
                        hideLoading(loading);
                        closeConfirm();
                    }
                });

                // ===== Pagination =====
                pagesEl.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-page]');
                    if (!btn) return;
                    state.page = Number(btn.dataset.page);
                    render();
                });

                prevBtn.addEventListener('click', () => {
                    if (state.page > 1) { state.page--; render(); }
                });

                nextBtn.addEventListener('click', () => {
                    state.page++;
                    render();
                });

                // ===== Pencarian =====
                searchEl.addEventListener('input', () => {
                    state.q = searchEl.value;
                    state.page = 1;
                    searchClear.classList.toggle('hidden', state.q === '');
                    render(false);
                    syncSummary();
                });

                searchClear.addEventListener('click', () => {
                    searchEl.value = '';
                    state.q = '';
                    state.page = 1;
                    searchClear.classList.add('hidden');
                    searchEl.focus();
                    render();
                    syncSummary();
                });

                // Escape di kolom pencarian membersihkan isinya, seperti di browser.
                searchEl.addEventListener('keydown', (e) => {
                    if (e.key !== 'Escape' || searchEl.value === '') return;
                    e.preventDefault();
                    searchClear.click();
                });

                // ===== Jalankan =====
                render();
                syncSummary();
                syncStats();
                initReveal();
            })();
        </script>
    @endpush
@endsection
