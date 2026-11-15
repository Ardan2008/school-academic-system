@extends('layouts.dashboard')

@php
    $user = auth()->user();

    $stats = $stats ?? [];
    $recentActivities = $recentActivities ?? collect();

    // Grafik & rekap nilai dihitung dari tabel nilai (StatistikNilai), bukan angka contoh.
    $nilaiKelas = $nilaiKelas ?? collect();
    $kelasTertinggi = $kelasTertinggi ?? collect();

    $roleBadge = [
        'admin' => 'bg-blue-50 text-blue-700 ring-blue-200',
        'guru' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'siswa' => 'bg-amber-50 text-amber-700 ring-amber-200',
    ];

    $dashboardData = [
        'nilaiKelas'     => $nilaiKelas,
        'kelasTertinggi' => $kelasTertinggi,
        'aktivitas'      => $recentActivities->map(function ($a) use ($roleBadge) {
            $role = $a['role'] ?? '';

            return [
                'judul'    => $a['judul'] ?? '',
                'detail'   => $a['detail'] ?? '',
                'pengguna' => $a['pengguna'] ?? 'Admin',
                'role'     => $role,
                'role_label' => $a['role_label'] ?? ucfirst((string) $role),
                'role_badge' => $roleBadge[$role] ?? 'bg-slate-100 text-slate-600 ring-slate-200',
                'waktu'    => \App\Support\Waktu::lengkap($a['waktu'] ?? null) ?? '-',
            ];
        })->values(),
    ];
@endphp

@section('content')

<style>
    /* ---------- Scroll Reveal ---------- */
    #dashboard-content.reveal-ready [data-reveal] {
        opacity: 0;
        transform: translateY(14px);
        filter: blur(2px);
        transition:
            opacity .6s cubic-bezier(.22, 1, .36, 1) var(--d, 0s),
            transform .6s cubic-bezier(.22, 1, .36, 1) var(--d, 0s),
            filter .6s cubic-bezier(.22, 1, .36, 1) var(--d, 0s);
        will-change: opacity, transform;
    }

    #dashboard-content.reveal-ready [data-reveal].is-visible {
        opacity: 1;
        transform: none;
        filter: none;
    }

    /* ---------- Panel: hover halus ---------- */
    #dashboard-content .panel-card {
        transition: box-shadow .3s ease, border-color .3s ease;
    }

    #dashboard-content .panel-card:hover {
        box-shadow: 0 10px 26px -16px rgba(15, 23, 42, .18);
        border-color: rgba(148, 163, 184, .3);
    }

    /* ---------- Paginator body: fade + geser saat ganti halaman ---------- */
    #dashboard-content [data-body] {
        transition: opacity .2s ease-in-out, transform .2s ease-in-out;
    }

    #dashboard-content [data-body].opacity-0 {
        transform: translateY(4px);
    }

    /* ---------- Filter button: ikon berputar saat panel terbuka ---------- */
    #dashboard-content [data-filter-toggle] svg:first-child {
        transition: transform .3s cubic-bezier(.22, 1, .36, 1);
    }

    #dashboard-content [data-filter-toggle][data-open="true"] svg:first-child {
        transform: rotate(180deg);
    }

    /* ---------- Baris tabel/list: muncul lembut bergantian ---------- */
    @keyframes row-in {
        from { opacity: 0; transform: translateY(8px); }
        to   { opacity: 1; transform: none; }
    }

    #dashboard-content [data-body] > * {
        animation: row-in .4s cubic-bezier(.22, 1, .36, 1) both;
        animation-delay: calc(var(--i, 0) * 45ms);
    }

    /* ---------- Mini bar nilai: mengisi dari 0 ---------- */
    @keyframes fill-in { from { width: 0; } }

    #dashboard-content .bar-fill {
        width: var(--w);
        animation: fill-in .8s cubic-bezier(.22, 1, .36, 1) both;
        animation-delay: calc(var(--i, 0) * 45ms + .15s);
    }

    /* ---------- Baris hover: latar lembut ---------- */
    #dashboard-content [data-body] > * { transition: background-color .2s ease; }
    #dashboard-content [data-body] > *:hover { background-color: rgba(248, 250, 252, .9); }

    /* ---------- Panah link bergeser saat hover ---------- */
    #dashboard-content a svg { transition: transform .25s ease; }
    #dashboard-content a:hover svg { transform: translateX(3px); }

    @media (prefers-reduced-motion: reduce) {
        #dashboard-content.reveal-ready [data-reveal] {
            opacity: 1;
            transform: none;
            filter: none;
            transition: none;
        }

        #dashboard-content .panel-card:hover {
            animation: none;
            transform: none;
        }

        #dashboard-content [data-body] > *,
        #dashboard-content .bar-fill { animation: none !important; }

        #dashboard-content .bar-fill { width: var(--w); }
    }
</style>

<div id="dashboard-content">

    {{-- Kartu statistik --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div data-reveal style="--d: {{ $loop->index * .08 }}s">
                <x-ui.stat-card
                    layout="stacked"
                    :label="$stat['label']"
                    :value="$stat['value']"
                    :icon="$stat['icon']"
                    :tone="$stat['bg'].' '.$stat['text']"
                    :accent="$stat['accent'] ?? '#1E86FF'"
                    :catatan="$stat['desc']"
                    :count="true"
                />
            </div>
        @endforeach
    </div>

    {{-- Layout 3 kolom baru aktif di xl, karena di lg lebar konten sudah berkurang oleh sidebar --}}
    <div class="mt-4 grid grid-cols-1 gap-4 sm:mt-6 sm:gap-6 xl:grid-cols-3">
        {{-- Grafik rata-rata nilai kelas --}}
        <div data-reveal style="--d: .32s" class="min-w-0 xl:col-span-2">
        <div id="chart-nilai" class="panel-card flex h-full min-w-0 flex-col rounded-2xl border border-slate-100 bg-white p-4 shadow-sm sm:p-6">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-slate-900">Grafik Rata-Rata Nilai Kelas</h3>
                @if (Route::has('dashboard.admin.laporan-akademik.index'))
                    <a href="{{ route('dashboard.admin.laporan-akademik.index') }}"
                       class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap text-xs font-medium text-primary-70 transition hover:gap-1.5 hover:underline">
                        Lihat Rekap <x-lucide-arrow-right class="h-3.5 w-3.5 transition-transform" />
                    </a>
                @endif
            </div>

            {{-- Filter: tombol + panel muncul di atas grafik --}}
            <div class="relative mt-4 flex justify-end" data-filter-root>
                <button
                    type="button"
                    data-filter-toggle
                    aria-expanded="false"
                    aria-controls="filter-panel"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-medium text-slate-600 transition hover:border-primary-40 hover:bg-blue-50 hover:text-primary-70 active:scale-[.98] data-[open=true]:border-primary-60 data-[open=true]:bg-blue-50 data-[open=true]:text-primary-70"
                >
                    <x-lucide-sliders-horizontal class="h-4 w-4" />
                    <span>Filter</span>
                    <span
                        data-filter-badge
                        class="hidden min-w-4 items-center justify-center rounded-full bg-primary-60 px-1.5 py-px text-[10px] font-semibold leading-4 text-white"
                    >0</span>
                </button>

                <div
                    id="filter-panel"
                    data-filter-panel
                    data-open="false"
                    aria-hidden="true"
                    inert
                    class="invisible absolute left-0 top-full z-30 mt-2 w-full origin-top scale-95 rounded-2xl border border-slate-100 bg-white p-4 opacity-0 shadow-xl shadow-slate-900/10 transition-[opacity,transform,visibility] duration-200 ease-out sm:left-auto sm:right-0 sm:w-80 data-[open=true]:visible data-[open=true]:scale-100 data-[open=true]:opacity-100"
                >
                    <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Saring Data</p>

                    {{-- text-base di HP agar Safari/iOS tidak zoom otomatis saat fokus --}}
                    <div class="space-y-3">
                        <div>
                            <div class="relative">
                                <select id="filter-kelas" data-filter="kelas"
                                    class="h-10 w-full min-w-0 appearance-none rounded-lg border border-slate-200 bg-slate-50 px-3 pr-8 text-base text-slate-600 focus:border-primary-40 focus:outline-none sm:h-9 sm:text-sm">
                                    <option value="">Semua Kelas</option>
                                </select>
                                <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                            </div>
                        </div>

                        <div>
                            <div class="relative">
                                <select id="filter-tahun" data-filter="tahun"
                                    class="h-10 w-full min-w-0 appearance-none rounded-lg border border-slate-200 bg-slate-50 px-3 pr-8 text-base text-slate-600 focus:border-primary-40 focus:outline-none sm:h-9 sm:text-sm">
                                    <option value="">Semua Tahun Ajaran</option>
                                </select>
                                <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                            </div>
                        </div>

                        <div>
                            <div class="relative">
                                <select id="filter-semester" data-filter="semester"
                                    class="h-10 w-full min-w-0 appearance-none rounded-lg border border-slate-200 bg-slate-50 px-3 pr-8 text-base text-slate-600 focus:border-primary-40 focus:outline-none sm:h-9 sm:text-sm">
                                    <option value="">Semua Semester</option>
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

            {{-- Area chart mengisi sisa tinggi card; label sumbu X (h-6/bottom-6) dipisahkan dari area plot. --}}
            <div class="relative mt-5 flex min-h-54 flex-1 gap-2 sm:mt-6 sm:min-h-62 sm:gap-4" data-chart>
                <div class="flex flex-col text-xs text-slate-400">
                    <div class="flex flex-1 flex-col justify-between py-1">
                        <span>100</span><span>90</span><span>80</span><span>70</span><span>60</span>
                    </div>
                    <div class="h-6 shrink-0"></div>
                </div>

                <div class="relative min-w-0 flex-1">
                    <div class="pointer-events-none absolute bottom-6 left-0 top-0 border-l border-slate-100"></div>

                    {{-- Garis panduan nilai saat hover / tap --}}
                    <div class="pointer-events-none absolute inset-x-0 bottom-6 top-0">
                        <div data-guide
                             class="absolute inset-x-0 border-t border-dashed border-slate-300 opacity-0 transition-[bottom,opacity] duration-300 ease-out"
                             style="bottom: 0%"></div>
                    </div>

                    <div class="absolute inset-0 flex items-stretch pl-1 sm:pl-4" data-columns></div>

                    <p data-empty
                       class="pointer-events-none absolute inset-x-0 bottom-6 top-0 flex items-center justify-center px-4 text-center text-sm text-slate-400 opacity-0 transition-opacity duration-300">
                        Tidak ada data untuk filter ini
                    </p>
                </div>

                {{-- Tooltip --}}
                <div data-tooltip
                     class="pointer-events-none absolute left-0 top-0 z-20 w-52 rounded-2xl border border-slate-100 bg-white p-4 opacity-0 shadow-2xl shadow-slate-900/10 sm:w-60">
                    <span data-arrow class="absolute h-3 w-3 rotate-45 border-slate-100 bg-white" style="border-style: solid"></span>
                    <div data-tip-body class="relative"></div>
                </div>
            </div>

            <p class="mt-3 text-center text-[11px] text-slate-400 [@media(hover:hover)]:hidden">
                Ketuk batang untuk melihat detail
            </p>
        </div>
        </div>

        {{-- Nilai kelas tertinggi --}}
        <div data-reveal style="--d: .4s" class="min-w-0">
        <div class="panel-card flex h-full min-w-0 flex-col rounded-2xl border border-slate-100 bg-white shadow-sm" data-paginator="kelasTertinggi" data-per-page="5">
            <div class="flex items-center justify-between gap-3 p-4 sm:p-5">
                <h3 class="text-sm font-semibold text-slate-900">Nilai Kelas Tertinggi</h3>
                @if (Route::has('dashboard.admin.laporan-akademik.index'))
                    <a href="{{ route('dashboard.admin.laporan-akademik.index') }}"
                       class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap text-xs font-medium text-primary-70 transition hover:gap-1.5 hover:underline">
                        Lihat Rekap <x-lucide-arrow-right class="h-3.5 w-3.5" />
                    </a>
                @endif
            </div>

            <div class="flex-1 overflow-x-auto overflow-y-hidden border-t border-slate-100" data-hbody>
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3.5 text-center font-semibold">No</th>
                            <th class="px-4 py-3.5 font-semibold">Kelas</th>
                            <th class="px-4 py-3.5 font-semibold">Jurusan</th>
                            <th class="px-4 py-3.5 font-semibold">Nilai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-800" data-body></tbody>
                </table>
            </div>

            <x-ui.table-footer mini label="kelas" />
        </div>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:mt-6 sm:gap-6 xl:grid-cols-3">
        {{-- Aktivitas terbaru: memakai grid (bukan <table>) agar di HP baris bertumpuk tanpa menggeser halaman. --}}
        <div data-reveal style="--d: .48s" class="min-w-0 xl:col-span-2">
        <div class="panel-card flex h-full min-w-0 flex-col rounded-2xl border border-slate-100 bg-white shadow-sm" data-paginator="aktivitas" data-per-page="5">
            <div class="flex items-center justify-between gap-3 p-4 sm:p-5">
                <h3 class="text-sm font-semibold text-slate-900">Aktivitas Terbaru</h3>
                <a href="{{ route('dashboard.admin.log-aktivitas.index') }}" class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap text-xs font-medium text-primary-70 transition hover:gap-1.5 hover:underline">
                    Lihat Semua <x-lucide-arrow-right class="h-3.5 w-3.5" />
                </a>
            </div>

            {{-- Header kolom: hanya tampil di sm ke atas --}}
            <div class="hidden grid-cols-[2rem_minmax(0,1fr)_6rem_4.5rem_9rem] gap-x-3 border-y border-slate-100 bg-slate-50 px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:grid sm:px-5">
                <span>No</span>
                <span>Aktivitas</span>
                <span>Pengguna</span>
                <span>Role</span>
                <span>Tanggal &amp; Waktu</span>
            </div>

            <div class="flex-1 divide-y divide-slate-100 border-t border-slate-100 sm:border-t-0" data-body data-hbody></div>

            <x-ui.table-footer mini label="aktivitas" />
        </div>
        </div>

        {{-- Notifikasi sistem --}}
        <div data-reveal style="--d: .56s" class="min-w-0">
        <div class="panel-card flex h-full min-w-0 flex-col rounded-2xl border border-slate-100 bg-white shadow-sm" data-paginator="notifikasi" data-per-page="5">
            <div class="flex items-center justify-between gap-3 p-4 sm:p-5">
                <h3 class="text-sm font-semibold text-slate-900">Notifikasi Sistem</h3>
            </div>

            <div class="flex-1 divide-y divide-slate-100 border-t border-slate-100" data-body data-hbody></div>

            <x-ui.table-footer mini label="notifikasi" />
        </div>
        </div>
    </div>

</div>

{{-- Scroll reveal + angka kartu statistik menghitung naik --}}
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const scope = document.getElementById('dashboard-content');
        if (!scope || !('IntersectionObserver' in window)) return;

        const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

        const easeOutExpo = (t) => (t === 1 ? 1 : 1 - Math.pow(2, -10 * t));

        const countUp = (el, ms = 1800) => {
            const to = Number(el.dataset.count);
            if (isNaN(to) || reduce) return;
            const t0 = performance.now();
            const step = (now) => {
                const t = Math.min(1, (now - t0) / ms);
                const eased = easeOutExpo(t);
                el.textContent = Math.round(to * eased);
                if (t < 1) requestAnimationFrame(step);
            };
            el.textContent = 0;
            requestAnimationFrame(step);
        };

        scope.classList.add('reveal-ready');

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                const target = entry.target;
                target.classList.add('is-visible');
                observer.unobserve(target);

                // angka mulai menghitung mengikuti delay reveal kartunya
                const delay = (parseFloat(getComputedStyle(target).getPropertyValue('--d')) || 0) * 1000;
                target.querySelectorAll('[data-count]').forEach((el) => {
                    if (!reduce) el.textContent = 0;
                    setTimeout(() => countUp(el), delay + 300);
                });
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -30px 0px' });

        scope.querySelectorAll('[data-reveal]').forEach((el) => observer.observe(el));
    });
</script>

<script>
    (function () {
        // ===== Data dari PHP =====
        const data = @json($dashboardData);

        const esc = (v) => String(v).replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));

        // Badge role memakai kelas yang sama dengan halaman Log Aktivitas.
        const roleBadgeHtml = (act, extra = '') => `
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ${act.role_badge || 'bg-slate-100 text-slate-600 ring-slate-200'} ${extra}">
                ${esc(act.role_label || act.role || '-')}
            </span>`;

        // ===== Template baris tiap widget =====
        const renderers = {
            // Nilai: angka menghitung naik + mini bar terisi
            kelasTertinggi: (row, no) => `
                <tr class="transition hover:bg-slate-50/70">
                    <td class="px-4 py-3 text-center tabular-nums text-slate-500">${no}</td>
                    <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-900">${esc(row.kelas)}</td>
                    <td class="px-4 py-3 leading-relaxed text-slate-600">${esc(row.jurusan)}</td>
                    <td class="px-4 py-3 text-slate-800">
                        <div class="flex flex-col items-start gap-1.5">
                            <span data-count="${esc(row.nilai)}" class="font-semibold tabular-nums">${esc(row.nilai)}</span>
                            <div class="h-1 w-16 overflow-hidden rounded-full bg-slate-100">
                                <div class="bar-fill h-full rounded-full ${row.nilai >= 90 ? 'bg-emerald-500' : row.nilai >= 85 ? 'bg-primary-60' : row.nilai >= 75 ? 'bg-amber-500' : 'bg-rose-500'}" style="--w:${esc(row.nilai)}%"></div>
                            </div>
                        </div>
                    </td>
                </tr>`,

            // HP: nomor + isi bertumpuk; sm ke atas: 5 kolom sejajar header.
            aktivitas: (act, no) => `
                <div class="grid grid-cols-[1.75rem_minmax(0,1fr)] gap-x-2 px-4 py-3 text-sm sm:grid-cols-[2rem_minmax(0,1fr)_6rem_4.5rem_9rem] sm:gap-x-3 sm:px-5">
                    <span class="text-slate-500">${no}</span>
                    <div class="min-w-0">
                        <p class="font-medium text-slate-800">${esc(act.judul)}</p>
                        <p class="text-xs text-slate-400">${esc(act.detail)}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-500 sm:hidden">
                            <span class="truncate">${esc(act.pengguna)}</span>
                            ${roleBadgeHtml(act, 'shrink-0')}
                            <span class="text-slate-300">·</span>
                            <span class="whitespace-nowrap">${esc(act.waktu)}</span>
                        </div>
                    </div>
                    <span class="hidden truncate text-slate-600 sm:block" title="${esc(act.pengguna)}">${esc(act.pengguna)}</span>
                    <span class="hidden sm:block">${roleBadgeHtml(act)}</span>
                    <span class="hidden whitespace-nowrap text-slate-500 sm:block">${esc(act.waktu)}</span>
                </div>`,
        };

        // ===== Pagination (dengan fade saat ganti halaman) =====
        const ACTIVE_CLS = ['bg-primary-60', 'font-semibold', 'text-white'];
        const IDLE_CLS   = ['hover:bg-slate-50'];

        function initPaginator(root) {
            const name    = root.dataset.paginator;
            const items   = data[name] || [];
            const render  = renderers[name];
            const perPage = Number(root.dataset.perPage) || 5;

            const body    = root.querySelector('[data-body]');
            const hbody   = root.querySelector('[data-hbody]') || body;
            const pag     = root.querySelector('[data-pagination]');
            const info    = pag.querySelector('[data-info]');
            const pagesEl = pag.querySelector('[data-pages]');
            const prevBtn = pag.querySelector('[data-prev]');
            const nextBtn = pag.querySelector('[data-next]');
            const label   = pag.dataset.label || 'data';

            const total    = items.length;
            const lastPage = Math.max(1, Math.ceil(total / perPage));
            let page = 1;
            let timer = null;
            let reservedH = 0;

            body.classList.add('transition-opacity', 'duration-200', 'ease-in-out');

            // Tombol halaman dibuat sekali, hanya kelasnya yang diganti; area tap lebih besar di HP.
            const pageBtns = Array.from({ length: lastPage }, (_, i) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.dataset.page = i + 1;
                btn.textContent = i + 1;
                btn.className = 'min-w-8 rounded-lg px-2.5 py-1.5 transition-colors duration-200 sm:min-w-0 sm:py-1';
                pagesEl.appendChild(btn);
                return btn;
            });

            // Cadangkan tinggi setara perPage baris agar card tetap stabil saat halaman terakhir berisi lebih sedikit data.
            function reserveHeight() {
                hbody.style.minHeight = '';

                if (total <= perPage) return;

                const rows = [...body.children];
                if (!rows.length) return;

                reservedH = rows.length >= perPage
                    ? hbody.getBoundingClientRect().height
                    : rows[0].getBoundingClientRect().height * perPage;

                hbody.style.minHeight = Math.ceil(reservedH) + 'px';
            }

            // Baris masuk bergantian (stagger) + angka menghitung naik
            function renderBody() {
                const start = (page - 1) * perPage;
                body.innerHTML = items.slice(start, start + perPage)
                    .map((row, i) => render(row, start + i + 1)).join('');

                [...body.children].forEach((el, i) => {
                    el.style.setProperty('--i', i);
                    el.querySelectorAll('[data-count]').forEach((c) => {
                        setTimeout(() => tween(c, 0, Number(c.dataset.count), 1400), i * 45 + 120);
                    });
                });

                reserveHeight();
            }

            function updateControls() {
                const from = total === 0 ? 0 : (page - 1) * perPage + 1;
                const to   = Math.min(page * perPage, total);
                info.textContent = `Menampilkan ${from}–${to} dari ${total} ${label}`;

                pageBtns.forEach((btn, i) => {
                    const on = i + 1 === page;
                    btn.classList.remove(...(on ? IDLE_CLS : ACTIVE_CLS));
                    btn.classList.add(...(on ? ACTIVE_CLS : IDLE_CLS));
                });

                prevBtn.disabled = page === 1;
                nextBtn.disabled = page === lastPage;
            }

            function goTo(n) {
                n = Math.max(1, Math.min(lastPage, n));
                if (n === page) return;
                page = n;
                updateControls();

                // fade out -> ganti isi -> fade in
                clearTimeout(timer);
                body.classList.add('opacity-0');
                timer = setTimeout(() => {
                    renderBody();
                    body.classList.remove('opacity-0');
                }, 200);
            }

            prevBtn.addEventListener('click', () => goTo(page - 1));
            nextBtn.addEventListener('click', () => goTo(page + 1));
            pagesEl.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-page]');
                if (btn) goTo(Number(btn.dataset.page));
            });

            renderBody();
            updateControls();

            return { reserve: reserveHeight };
        }

        // ===== Animasi angka (count up/down) =====
        function tween(el, from, to, ms = 700) {
            cancelAnimationFrame(el._raf);
            const t0 = performance.now();
            const step = (now) => {
                const t = Math.min(1, (now - t0) / ms);
                const eased = t === 1 ? 1 : 1 - Math.pow(2, -10 * t);   // easeOutExpo
                el.textContent = t < 1 ? Math.round((from + (to - from) * eased) * 10) / 10 : to;
                if (t < 1) el._raf = requestAnimationFrame(step);
            };
            el._raf = requestAnimationFrame(step);
        }

        // ===== Notifikasi Sistem =====
        // Data diambil dari endpoint notifikasi role admin (sumber yang sama
        // dengan lonceng di header: seluruhnya baris tabel notifications yang
        // dibuat oleh aktivitas nyata), lalu diperbarui berkala. Klik baris
        // menandai notifikasi dibaca sekaligus membuka detailnya; baris yang
        // sudah dibaca tetap tampil dengan titik pucat.
        function initNotifikasiPanel(root) {
            const body    = root.querySelector('[data-body]');
            const hbody   = root.querySelector('[data-hbody]') || body;
            const pag     = root.querySelector('[data-pagination]');
            const info    = pag.querySelector('[data-info]');
            const pagesEl = pag.querySelector('[data-pages]');
            const prevBtn = pag.querySelector('[data-prev]');
            const nextBtn = pag.querySelector('[data-next]');
            const label   = pag.dataset.label || 'notifikasi';
            const perPage = Number(root.dataset.perPage) || 5;

            const csrf    = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const apiUrl  = @json(route('dashboard.notifications.index', 'admin'));
            const readUrl = (id) => apiUrl + '/' + encodeURIComponent(id) + '/read';

            const ACTIVE_CLS = ['bg-primary-60', 'font-semibold', 'text-white'];
            const IDLE_CLS   = ['hover:bg-slate-50'];

            let items = [];
            let page = 1;
            let lastPage = 1;
            let timer = null;
            let fetching = false;
            let seq = 0;

            body.classList.add('transition-opacity', 'duration-200', 'ease-in-out');

            // Baris notifikasi: titik biru = belum dibaca, titik pucat = sudah dibaca.
            const row = (n) => `
                <a href="${esc(n.url || '#')}" data-notif-id="${esc(n.id)}" class="flex items-start justify-between gap-3 px-4 py-3 sm:px-5">
                    <div class="flex min-w-0 items-start gap-2.5">
                        <span class="relative mt-1.5 flex h-2 w-2 shrink-0">
                            <span class="relative inline-flex h-2 w-2 rounded-full ${n.is_read ? 'bg-slate-300' : 'bg-primary-60'}"${n.is_read ? '' : ' title="Belum dibaca"'}></span>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800">${esc(n.judul)}</p>
                            <p class="truncate text-xs text-slate-400" title="${esc(n.detail)}">${esc(n.detail)}</p>
                        </div>
                    </div>
                    <span class="shrink-0 text-xs text-slate-400">${esc(n.waktu)}</span>
                </a>`;

            const stateHtml = (judul, tambahan = '') => `
                <div class="px-4 py-8 text-center">
                    <p class="text-sm font-medium text-slate-700">${judul}</p>
                    <p class="mt-1 text-xs text-slate-400">${tambahan}</p>
                </div>`;

            // Kondisi memuat / kosong / gagal masing-masing punya tampilannya sendiri.
            function setState(jenis) {
                if (jenis === 'loading') {
                    body.innerHTML = stateHtml('Memuat notifikasi\u2026', 'Mohon tunggu sebentar');
                } else if (jenis === 'empty') {
                    body.innerHTML = stateHtml('Tidak ada notifikasi', 'Semua sudah diperbarui');
                } else {
                    body.innerHTML = stateHtml(
                        'Gagal memuat notifikasi',
                        '<button type="button" data-notif-retry class="text-xs font-medium text-primary-70 hover:underline">Coba lagi</button>'
                    );
                }

                hbody.style.minHeight = '';
                info.textContent = jenis === 'loading' ? 'Memuat notifikasi\u2026' : (jenis === 'error' ? 'Gagal memuat notifikasi' : `Menampilkan 0\u20130 dari 0 ${label}`);
                [...pagesEl.children].forEach((btn) => btn.classList.remove(...ACTIVE_CLS));
                prevBtn.disabled = true;
                nextBtn.disabled = true;
            }

            // Tombol halaman dibuat ulang tiap jumlah halaman berubah (data bisa bertambah / berkurang saat disegarkan).
            function buildPageButtons() {
                pagesEl.innerHTML = '';
                for (let i = 1; i <= lastPage; i++) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.dataset.page = i;
                    btn.textContent = i;
                    btn.className = 'min-w-8 rounded-lg px-2.5 py-1.5 transition-colors duration-200 sm:min-w-0 sm:py-1';
                    pagesEl.appendChild(btn);
                }
            }

            // Cadangkan tinggi setara perPage baris agar card tetap stabil saat halaman terakhir berisi lebih sedikit data.
            // Kondisi memuat/kosong/gagal hanya muncul saat data kosong, jadi tidak ikut diukur.
            function reserveHeight() {
                hbody.style.minHeight = '';

                if (items.length <= perPage) return;

                const rows = [...body.children];
                if (!rows.length) return;

                const reservedH = rows.length >= perPage
                    ? hbody.getBoundingClientRect().height
                    : rows[0].getBoundingClientRect().height * perPage;

                hbody.style.minHeight = Math.ceil(reservedH) + 'px';
            }

            function renderBody() {
                const start = (page - 1) * perPage;
                body.innerHTML = items.slice(start, start + perPage).map(row).join('');

                [...body.children].forEach((el, i) => el.style.setProperty('--i', i));

                reserveHeight();
            }

            function updateControls() {
                const total = items.length;
                const from  = total === 0 ? 0 : (page - 1) * perPage + 1;
                const to    = Math.min(page * perPage, total);
                info.textContent = `Menampilkan ${from}\u2013${to} dari ${total} ${label}`;

                [...pagesEl.children].forEach((btn, i) => {
                    const on = i + 1 === page;
                    btn.classList.remove(...(on ? IDLE_CLS : ACTIVE_CLS));
                    btn.classList.add(...(on ? ACTIVE_CLS : IDLE_CLS));
                });

                prevBtn.disabled = page === 1;
                nextBtn.disabled = page === lastPage;
            }

            function goTo(n) {
                n = Math.max(1, Math.min(lastPage, n));
                if (n === page) return;
                page = n;
                updateControls();

                // fade out -> ganti isi -> fade in
                clearTimeout(timer);
                body.classList.add('opacity-0');
                timer = setTimeout(() => {
                    renderBody();
                    body.classList.remove('opacity-0');
                }, 200);
            }

            function applyData(notifications) {
                // Samakan nama field endpoint (title, message, time, is_read) dengan template baris.
                items = (notifications || []).map((n) => ({
                    id: n.id,
                    judul: n.title ?? '',
                    detail: n.message ?? '',
                    waktu: n.time ?? '',
                    url: n.url || null,
                    is_read: !!n.is_read,
                }));
                lastPage = Math.max(1, Math.ceil(items.length / perPage));
                page = Math.min(page, lastPage);
                buildPageButtons();

                if (items.length === 0) {
                    setState('empty');
                    return;
                }

                renderBody();
                updateControls();
            }

            function load() {
                if (fetching) return;
                fetching = true;
                const mySeq = ++seq;

                // Indikator memuat hanya tampil saat belum ada data yang bisa ditampilkan.
                if (items.length === 0) setState('loading');

                fetch(apiUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' })
                    .then((r) => (r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status))))
                    .then((d) => {
                        if (mySeq !== seq) return;
                        applyData(d.notifications || []);
                    })
                    .catch(() => {
                        if (mySeq !== seq) return;
                        // Data lama dipertahankan bila segarkan gagal; pesan galat
                        // hanya menggantikan isi yang memang kosong.
                        if (items.length === 0) setState('error');
                    })
                    .finally(() => { fetching = false; });
            }

            prevBtn.addEventListener('click', () => goTo(page - 1));
            nextBtn.addEventListener('click', () => goTo(page + 1));
            pagesEl.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-page]');
                if (btn) goTo(Number(btn.dataset.page));
            });

            body.addEventListener('click', (e) => {
                // Coba lagi setelah gagal memuat
                if (e.target.closest('[data-notif-retry]')) {
                    e.preventDefault();
                    load();
                    return;
                }

                // Klik baris: tandai dibaca, lalu biarkan tautan membuka detailnya.
                const link = e.target.closest('a[data-notif-id]');
                if (!link) return;

                const id = link.getAttribute('data-notif-id');
                if (!id) return;

                const item = items.find((n) => String(n.id) === String(id));
                if (!item || item.is_read) return;

                item.is_read = true;

                const dot = link.querySelector('.bg-primary-60');
                if (dot) {
                    dot.classList.remove('bg-primary-60');
                    dot.classList.add('bg-slate-300');
                    dot.removeAttribute('title');
                }

                fetch(readUrl(id), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                }).catch(() => {});
            });

            load();
            setInterval(load, 60000);

            return { reserve: reserveHeight };
        }

        // ===== Grafik rata-rata nilai kelas =====
        const ICON_CHART = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>';
        const ICON_UP    = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 7-7 7 7"/><path d="M12 19V5"/></svg>';
        const ICON_DOWN  = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>';

        function grade(v) {
            if (v >= 90) return { text: 'Sangat Baik',        badge: 'bg-emerald-50 text-emerald-600', bar: 'bg-emerald-500' };
            if (v >= 80) return { text: 'Baik',               badge: 'bg-primary-10 text-primary-70',  bar: 'bg-primary-60' };
            if (v >= 75) return { text: 'Cukup',              badge: 'bg-amber-50 text-amber-600',     bar: 'bg-amber-500' };
            return           { text: 'Perlu Ditingkatkan', badge: 'bg-rose-50 text-rose-600',       bar: 'bg-rose-500' };
        }

        function initChart(root) {
            const rows    = data.nilaiKelas;
            const min     = 60;
            const max     = 100;
            const fields  = { kelas: 'label', tahun: 'tahun', semester: 'semester' };
            const filters = { kelas: '', tahun: '', semester: '' };

            const wrap    = root.querySelector('[data-chart]');
            const colsEl  = root.querySelector('[data-columns]');
            const emptyEl = root.querySelector('[data-empty]');
            const guide   = root.querySelector('[data-guide]');
            const tip     = root.querySelector('[data-tooltip]');
            const tipBody = tip.querySelector('[data-tip-body]');
            const arrow   = tip.querySelector('[data-arrow]');

            const pct = (n) => Math.max(0, Math.min(100, (n - min) / (max - min) * 100));
            const avg = (list) => Math.round(list.reduce((a, r) => a + r.nilai, 0) / list.length * 10) / 10;

            const filterRoot  = root.querySelector('[data-filter-root]');
            const filterBtn   = root.querySelector('[data-filter-toggle]');
            const filterPanel = root.querySelector('[data-filter-panel]');
            const filterBadge = root.querySelector('[data-filter-badge]');
            const selects     = root.querySelectorAll('select[data-filter]');

            const setPanel = (open) => {
                filterPanel.dataset.open = open ? 'true' : 'false';
                filterPanel.setAttribute('aria-hidden', open ? 'false' : 'true');
                filterPanel.toggleAttribute('inert', !open);
                filterBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                filterBtn.dataset.open = open ? 'true' : 'false';
                if (open) requestAnimationFrame(() => selects[0]?.focus());
                else {
                    document.body.style.removeProperty('overflow');
                    // Buang perubahan yang belum diterapkan saat panel ditutup
                    selects.forEach((sel) => { sel.value = filters[sel.dataset.filter] || ''; });
                }
            };

            const syncBadge = () => {
                const n = Object.values(filters).filter(Boolean).length;
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
                // Simpan pilihan ke filter yang diterapkan, baru perbarui data
                selects.forEach((sel) => { filters[sel.dataset.filter] = sel.value; });
                syncBadge();
                draw();
                setPanel(false);
            });

            filterPanel.querySelector('[data-filter-reset]').addEventListener('click', () => {
                // Reset langsung: semua filter dikosongkan dan data kembali ke kondisi awal
                Object.keys(filters).forEach((key) => { filters[key] = ''; });
                selects.forEach((sel) => { sel.value = ''; });
                syncBadge();
                draw();
            });

            document.addEventListener('click', (e) => {
                if (filterPanel.dataset.open !== 'true') return;
                if (filterPanel.contains(e.target) || filterBtn.contains(e.target)) return;
                setPanel(false);
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && filterPanel.dataset.open === 'true') setPanel(false);
            });

            // Isi opsi filter; perubahan hanya draf dan diterapkan saat "Terapkan" ditekan.
            selects.forEach((sel) => {
                const key = sel.dataset.filter;
                [...new Set(rows.map((r) => r[fields[key]]))].forEach((v) => sel.add(new Option(v, v)));
            });

            const filtered = (label) => rows.filter((r) =>
                r.label === label
                && (!filters.kelas    || r.label    === filters.kelas)
                && (!filters.tahun    || r.tahun    === filters.tahun)
                && (!filters.semester || r.semester === filters.semester));

            // ----- Tooltip -----
            let tipVisible = false;
            let hideTimer  = null;
            let lastNum    = min;
            let activeCol  = null;

            function place(col) {
                const box     = wrap.getBoundingClientRect();
                const barBox  = col.bar.getBoundingClientRect();
                const zone    = col.bar.parentElement.getBoundingClientRect();
                const w = tip.offsetWidth;
                const h = tip.offsetHeight;
                const gap = 14;

                // sisi kanan batang; kalau tidak muat pindah ke kiri
                let side = 'right';
                let x = barBox.right - box.left + gap;
                if (x + w > box.width) { side = 'left'; x = barBox.left - box.left - gap - w; }
                x = Math.max(0, Math.min(box.width - w, x));

                // vertikal: sejajar tengah batang (pakai tinggi target, bukan tinggi saat animasi)
                const cy = zone.top - box.top + zone.height - zone.height * col.pct / 200;
                const y  = Math.max(0, Math.min(box.height - h, cy - h / 2));

                tip.style.transform = `translate(${x}px, ${y}px)`;

                // panah menunjuk ke batang
                arrow.style.top = `${Math.max(20, Math.min(h - 20, cy - y)) - 7}px`;
                if (side === 'right') {
                    arrow.style.left = '-7px'; arrow.style.right = 'auto'; arrow.style.borderWidth = '0 0 1px 1px';
                } else {
                    arrow.style.right = '-7px'; arrow.style.left = 'auto'; arrow.style.borderWidth = '1px 1px 0 0';
                }
            }

            function tipHtml(col, vis) {
                const list  = filtered(col.label);
                const g     = grade(col.value);
                const sorted = vis.map((c) => c.value).sort((a, b) => b - a);
                const rank  = sorted.indexOf(col.value) + 1;

                let delta = '';
                if (vis.length > 1) {
                    const overall = vis.reduce((a, c) => a + c.value, 0) / vis.length;
                    const diff = Math.round((col.value - overall) * 10) / 10;
                    if (Math.abs(diff) < 0.05) {
                        delta = `<p class="mt-3 text-[11px] text-slate-400">Sama dengan rata-rata semua kelas</p>`;
                    } else {
                        const up = diff > 0;
                        delta = `
                            <div class="mt-3 flex items-center gap-2 text-[11px] text-slate-400">
                                <span class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 font-semibold ${up ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'}">
                                    ${up ? ICON_UP : ICON_DOWN}${Math.abs(diff)}
                                </span>
                                <span>dari rata-rata semua kelas</span>
                            </div>`;
                    }
                }

                const detail = list.slice(0, 4).map((r) => {
                    const rg = grade(r.nilai);
                    return `
                        <div>
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-500">${esc(r.tahun)} · ${esc(r.semester)}</span>
                                <span class="font-semibold text-slate-800">${esc(r.nilai)}</span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div data-w="${r.nilai}" class="h-full rounded-full ${rg.bar} transition-[width] duration-700 ease-out" style="width: 0%"></div>
                            </div>
                        </div>`;
                }).join('');

                return `
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-10 text-primary-70">${ICON_CHART}</span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900" title="${esc(col.label)}">${esc(col.label)}</p>
                            <p class="text-[11px] text-slate-400">Peringkat ${rank} dari ${vis.length} kelas</p>
                        </div>
                    </div>

                    <div class="mt-4 flex items-end justify-between gap-2">
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Rata-rata nilai</p>
                            <p data-num class="mt-1 text-3xl font-bold leading-none tabular-nums text-slate-900">${col.value}</p>
                        </div>
                        <span class="rounded-full px-2 py-1 text-[11px] font-semibold ${g.badge}">${g.text}</span>
                    </div>

                    ${delta}

                    <div class="mt-4 space-y-3 border-t border-slate-100 pt-3">${detail}</div>`;
            }

            function showTip(col) {
                if (!col.active) return;
                clearTimeout(hideTimer);
                activeCol = col;

                const vis = cols.filter((c) => c.active);
                tipBody.innerHTML = tipHtml(col, vis);

                const first = !tipVisible;
                if (first) tip.style.transition = 'opacity 150ms ease';   // muncul tanpa meluncur dari pojok

                place(col);   // sekaligus memaksa layout, jadi lebar progress bar mulai dari 0

                tipBody.querySelectorAll('[data-w]').forEach((f) => { f.style.width = f.dataset.w + '%'; });
                tween(tipBody.querySelector('[data-num]'), first ? min : lastNum, col.value, 500);
                lastNum = col.value;

                tip.style.opacity = 1;
                if (first) {
                    tipVisible = true;
                    requestAnimationFrame(() => {
                        tip.style.transition = 'opacity 150ms ease, transform 300ms cubic-bezier(.22, 1, .36, 1)';
                    });
                }

                // garis panduan + sorot batang, redupkan yang lain
                guide.style.bottom = col.pct + '%';
                guide.style.opacity = 1;
                cols.forEach((c) => {
                    const on = c === col;
                    c.inner.style.opacity = on ? 1 : 0.4;
                    c.bar.classList.toggle('brightness-110', on);
                    c.bar.classList.toggle('shadow-xl', on);
                });
            }

            function resetTip() {
                tip.style.opacity = 0;
                guide.style.opacity = 0;
                tipVisible = false;
                activeCol = null;
                cols.forEach((c) => {
                    c.inner.style.opacity = 1;
                    c.bar.classList.remove('brightness-110', 'shadow-xl');
                });
            }

            // jeda kecil supaya tooltip meluncur antar batang, bukan hilang lalu muncul lagi
            function hideTip(now) {
                clearTimeout(hideTimer);
                if (now) resetTip(); else hideTimer = setTimeout(resetTip, 90);
            }

            // ----- Kolom dibuat sekali per kelas, lalu ditampilkan / disembunyikan -----
            const cols = [...new Set(rows.map((r) => r.label))].map((label) => {
                const el = document.createElement('div');
                el.className = 'flex min-w-0 flex-col overflow-hidden transition-[flex-grow,opacity] duration-500 ease-out';
                el.style.flex = '0 1 0%';
                el.style.opacity = 0;
                // Zona batang mengisi tinggi tersedia; batang absolut dari bawah agar persentasenya mengikuti tinggi zona.
                el.innerHTML = `
                    <div data-inner class="flex flex-1 flex-col px-1.5 transition-opacity duration-300 sm:px-3">
                        <div class="relative flex-1">
                            <div data-bar class="absolute inset-x-0 bottom-0 mx-auto w-full max-w-17.5 overflow-hidden rounded-t-lg bg-primary-60 transition-[height,filter,box-shadow] duration-700 ease-out" style="height: 0%">
                                <span class="pointer-events-none absolute inset-0" style="background: linear-gradient(180deg, rgba(255,255,255,.28), rgba(255,255,255,0) 70%)"></span>
                                <span data-val class="absolute inset-x-0 top-1.5 text-center text-xs font-semibold text-white"></span>
                            </div>
                        </div>
                        <p class="h-6 shrink-0 truncate pt-2 text-center text-xs font-medium leading-4 text-slate-500" title="${esc(label)}">${esc(label)}</p>
                    </div>`;

                const col = {
                    label, el, active: false, value: min, pct: 0,
                    inner: el.querySelector('[data-inner]'),
                    bar:   el.querySelector('[data-bar]'),
                    val:   el.querySelector('[data-val]'),
                };

                // Mouse: tooltip mengikuti hover. Sentuh/pena: pointerleave muncul saat jari
                // diangkat, jadi ketuk untuk membuka/menutup.
                let lastType = 'mouse';
                el.addEventListener('pointerdown', (e) => { lastType = e.pointerType; });
                el.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse') showTip(col); });
                el.addEventListener('pointerleave', (e) => { if (e.pointerType === 'mouse') hideTip(false); });
                el.addEventListener('click', () => {
                    if (lastType === 'mouse') return;
                    if (tipVisible && activeCol === col) hideTip(true); else showTip(col);
                });

                colsEl.appendChild(el);
                return col;
            });

            // Ketuk di luar grafik / ubah ukuran layar: tutup tooltip
            document.addEventListener('pointerdown', (e) => {
                if (tipVisible && !wrap.contains(e.target)) hideTip(true);
            });
            window.addEventListener('resize', () => hideTip(true));

            // Batang tumbuh bergantian dari kiri ke kanan
            function draw() {
                hideTip(true);
                let visible = 0;

                cols.forEach((col, i) => {
                    const list = filtered(col.label);

                    if (list.length) {
                        const value = avg(list);
                        visible++;
                        const from = col.active ? col.value : min;
                        setTimeout(() => tween(col.val, from, value, 1400), i * 60);
                        col.active = true;
                        col.value  = value;
                        col.pct    = pct(value);
                        col.el.style.flexGrow = 1;
                        col.el.style.opacity  = 1;
                        col.el.classList.remove('pointer-events-none');
                        // delay hanya untuk tinggi; filter & shadow (hover) tetap instan
                        col.bar.style.transitionDelay = `${i * 60}ms, 0ms, 0ms`;
                        col.bar.style.height = col.pct + '%';
                    } else {
                        col.active = false;
                        col.pct    = 0;
                        col.el.style.flexGrow = 0;
                        col.el.style.opacity  = 0;
                        col.el.classList.add('pointer-events-none');
                        col.bar.style.transitionDelay = '0ms, 0ms, 0ms';
                        col.bar.style.height = '0%';
                    }
                });

                emptyEl.classList.toggle('opacity-0', visible > 0);
            }

            void colsEl.offsetHeight; // paksa reflow supaya animasi awal berjalan dari 0
            draw();
            syncBadge();
        }

        // ===== Jalankan =====
        function init() {
            // Panel Notifikasi Sistem punya pengelolaan sendiri (data dari
            // endpoint notifikasi), jadi tidak lewat paginator generik.
            const notifPanel = document.querySelector('[data-paginator="notifikasi"]');

            const paginators = [...document.querySelectorAll('[data-paginator]')]
                .filter((el) => el !== notifPanel)
                .map(initPaginator);
            if (notifPanel) paginators.push(initNotifikasiPanel(notifPanel));

            const chart = document.getElementById('chart-nilai');
            if (chart) initChart(chart);

            // Hitung ulang tinggi cadangan saat ukuran layar berubah
            let resizeTimer = null;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => paginators.forEach((p) => p.reserve()), 150);
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
</script>
@endsection