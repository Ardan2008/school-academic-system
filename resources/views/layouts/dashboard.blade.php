<!DOCTYPE html>
<html lang="id" style="--sidebar-w:16rem;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Dashboard') - SASKANSA</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>

<body class="min-h-screen bg-white">

    <x-layout.sidebar />

    {{-- Konten --}}
    <div id="app-main" class="min-h-screen transition-[padding-left] duration-300 ease-in-out lg:pl-[var(--sidebar-w)]">

        {{-- Topbar (tanpa garis) --}}
        <header class="sticky top-0 z-30 flex h-14 items-center gap-2.5 bg-white/90 px-4 backdrop-blur-md sm:px-6 lg:px-8">
            <button
                id="sidebar-toggle"
                type="button"
                data-sidebar-toggle
                aria-controls="app-sidebar"
                aria-expanded="false"
                aria-label="Buka menu"
                class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 transition hover:bg-blue-50 hover:text-slate-800 lg:hidden"
            >
                <x-lucide-menu class="h-5 w-5" />
            </button>

            <div class="min-w-0">
                <h1 class="truncate text-base font-semibold text-[#0F3D73] sm:text-lg lg:text-xl" title="@yield('title', 'Dashboard')">
                    @yield('title', 'Dashboard')
                </h1>
            </div>

            <div class="mx-auto hidden w-full max-w-md items-center gap-2.5 rounded-xl bg-blue-50 px-3.5 py-2 md:flex relative data-[mobile-open=true]:fixed data-[mobile-open=true]:inset-x-3 data-[mobile-open=true]:top-2 data-[mobile-open=true]:z-40 data-[mobile-open=true]:flex data-[mobile-open=true]:max-w-none data-[mobile-open=true]:shadow-lg" id="global-search-wrapper">
                <form action="{{ route('dashboard.search') }}" method="GET" class="flex w-full items-center gap-2.5" id="global-search-form">
                    <x-lucide-search class="h-4 w-4 shrink-0 text-blue-500" />
                    <input
                        type="search"
                        name="q"
                        id="global-search-input"
                        autocomplete="off"
                        placeholder="Cari menu, data, atau informasi..."
                        class="w-full border-0 bg-transparent p-0 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-0 [&::-webkit-search-cancel-button]:appearance-none [&::-webkit-search-decoration]:appearance-none"
                    >
                    <button
                        type="button"
                        id="global-search-clear"
                        aria-label="Bersihkan pencarian"
                        class="hidden shrink-0 rounded-lg p-1 text-slate-400 transition hover:bg-blue-100 hover:text-slate-600 focus-visible:outline-none"
                    >
                        <x-lucide-x class="h-3.5 w-3.5" />
                    </button>
                    <button
                        type="button"
                        id="mobile-search-close"
                        aria-label="Tutup pencarian"
                        class="hidden shrink-0 rounded-lg p-1 text-slate-400 transition hover:bg-blue-100 hover:text-slate-600 focus-visible:outline-none md:hidden!"
                    >
                        <x-lucide-x class="h-4 w-4" />
                    </button>
                </form>
                <div id="global-search-results" class="hidden absolute left-0 right-0 top-full mt-2 max-h-96 overflow-y-auto rounded-2xl border border-slate-100 bg-white shadow-xl z-50"></div>
            </div>

            <div class="ml-auto flex items-center gap-2.5 md:ml-0">
                <button
                    id="mobile-search-toggle"
                    type="button"
                    aria-label="Cari menu atau data"
                    class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-600 transition hover:bg-blue-50 md:hidden"
                >
                    <x-lucide-search class="h-5 w-5" />
                </button>

                <div class="relative" id="notification-menu">
                    <button
                        type="button"
                        id="notification-toggle"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-label="Notifikasi"
                        class="relative flex h-9 w-9 items-center justify-center rounded-xl text-slate-600 transition hover:bg-blue-50"
                    >
                        <x-lucide-bell class="h-5 w-5" />
                        <span id="notification-badge" class="hidden absolute -right-1 -top-1 min-w-4.5 h-4.5 px-1 items-center justify-center rounded-full bg-rose-500 text-[10px] font-semibold text-white ring-2 ring-white"></span>
                    </button>

                    <div
                        id="notification-dropdown"
                        class="invisible absolute right-0 top-full z-50 mt-2 w-[320px] origin-top-right scale-95 rounded-2xl border border-slate-100 bg-white opacity-0 shadow-xl shadow-slate-900/10 transition-all duration-150 ease-out sm:w-90"
                    >
                        <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                            <p class="text-sm font-semibold text-slate-900">Notifikasi</p>
                            <button type="button" id="mark-all-read" class="text-xs font-medium text-primary-70 hover:underline">Tandai Semua Sudah Dibaca</button>
                        </div>

                        {{-- Filter kategori: Semua / Belum Dibaca / Sudah Dibaca.
                             Notifikasi yang sudah dibaca tetap tersimpan dan tetap
                             tampil pada filter Semua maupun Sudah Dibaca. --}}
                        <div id="notification-filters" role="tablist" aria-label="Filter notifikasi"
                             class="flex items-center gap-1.5 border-t border-slate-100 px-3 py-2">
                            <button type="button" data-notif-filter="all" role="tab" aria-selected="true"
                                    class="notif-filter inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition text-primary-70 bg-primary-10">
                                Semua
                                <span data-notif-count="all" class="hidden min-w-4.5 rounded-full bg-primary-60 px-1 text-center text-[10px] font-semibold leading-4 text-white"></span>
                            </button>
                            <button type="button" data-notif-filter="unread" role="tab" aria-selected="false"
                                    class="notif-filter inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition text-slate-500 hover:bg-slate-50">
                                Belum Dibaca
                                <span data-notif-count="unread" class="hidden min-w-4.5 rounded-full bg-primary-60 px-1 text-center text-[10px] font-semibold leading-4 text-white"></span>
                            </button>
                            <button type="button" data-notif-filter="read" role="tab" aria-selected="false"
                                    class="notif-filter inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition text-slate-500 hover:bg-slate-50">
                                Sudah Dibaca
                                <span data-notif-count="read" class="hidden min-w-4.5 rounded-full bg-primary-60 px-1 text-center text-[10px] font-semibold leading-4 text-white"></span>
                            </button>
                        </div>

                        <div class="border-t border-slate-100 max-h-105 overflow-y-auto" id="notification-list"></div>
                        <div class="border-t border-slate-100 px-4 py-2 text-center">
                            <a href="{{ route('dashboard') }}" class="text-xs font-medium text-slate-500 hover:text-slate-700">Ke Dashboard</a>
                        </div>
                    </div>
                </div>

                <div id="user-menu" class="relative">
                    @php
                        $user = auth()->user();
                        $roleLabels = ['admin' => 'Administrator', 'guru' => 'Guru', 'siswa' => 'Siswa'];
                        $roleLabel = $roleLabels[$user->role] ?? ucfirst($user->role);
                        $roleColor = [
                            'admin' => 'bg-blue-50 text-blue-700 ring-blue-200',
                            'guru' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                            'siswa' => 'bg-amber-50 text-amber-700 ring-amber-200',
                        ][$user->role] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
                        $profileRoute = Route::has('dashboard.' . $user->role . '.profile.index')
                            ? route('dashboard.' . $user->role . '.profile.index')
                            : route('profile');
                    @endphp

                    <button
                        id="user-menu-toggle"
                        type="button"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-label="Menu pengguna"
                        class="flex items-center gap-2.5 rounded-xl bg-blue-50 py-1.5 pl-2.5 pr-3 shadow-lg shadow-blue-500/10 transition hover:bg-blue-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                    >
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white text-xs font-bold text-[#0F3D73] shadow-sm">
                            {{ strtoupper(substr($user->name ?? '?', 0, 1)) }}
                        </span>
                        <span class="hidden text-left sm:block">
                            <span class="block max-w-36 truncate text-[13px] font-semibold leading-tight text-[#0F3D73]" title="{{ $user->name }}">
                                {{ $user->name }}
                            </span>
                            <span class="block text-[11px] leading-tight text-slate-500">
                                {{ $roleLabel }}
                            </span>
                        </span>
                        <x-lucide-chevron-down id="user-menu-chevron" class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200" />
                    </button>

                    <div
                        id="user-menu-dropdown"
                        class="invisible absolute right-0 top-full z-50 mt-2 w-56 origin-top-right scale-95 rounded-2xl border border-slate-100 bg-white p-2 opacity-0 shadow-xl shadow-slate-900/10 transition-all duration-150 ease-out"
                    >
                        <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-2.5">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">
                                {{ strtoupper(substr($user->name ?? '?', 0, 1)) }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-800" title="{{ $user->name }}">{{ $user->name }}</p>
                                <span class="mt-0.5 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1 {{ $roleColor }}">
                                    {{ $roleLabel }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-1.5 border-t border-slate-100 pt-1.5">
                            <a href="{{ $profileRoute }}"
                               class="flex items-center gap-2.5 rounded-xl px-2.5 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                                <x-lucide-user-round class="h-4 w-4 text-slate-400" />
                                Profil Saya
                            </a>
                            <form id="logout-form" method="POST" action="{{ route('logout') }}">
                                @csrf
                                <input type="hidden" name="role" value="{{ $user->role }}">
                                <button id="logout-btn" type="submit"
                                        class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50">
                                    <x-lucide-log-out class="h-4 w-4" />
                                    Keluar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 pb-8 pt-4 sm:px-6 lg:px-8">
            @yield('content')
        </main>

    </div>

    {{-- Popup Konfirmasi Keluar --}}
    <div
        id="logout-modal"
        data-open="false"
        aria-hidden="true"
        inert
        class="logout-modal fixed inset-0 z-70 flex items-center justify-center p-4"
    >
        <div data-modal-backdrop class="logout-modal-backdrop absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>

        <div
            data-modal-panel
            role="dialog"
            aria-modal="true"
            class="logout-modal-panel relative z-10 w-full max-w-sm rounded-2xl bg-white p-5 text-center shadow-2xl sm:p-6"
        >
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-50 text-red-600">
                <x-lucide-log-out class="h-5 w-5" />
            </div>

            <h3 class="text-base font-semibold text-slate-900">Yakin ingin keluar?</h3>
            <p class="mt-1.5 text-sm text-slate-500">
                Anda akan diminta login kembali untuk mengakses dashboard.
            </p>

            <div class="mt-6 flex gap-3">
                <button
                    type="button"
                    data-logout-no
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
                    data-logout-yes
                    data-key-nav
                    class="flex-1 rounded-xl bg-red-600 py-3 text-sm font-semibold
                           text-white transition hover:bg-red-700
                           focus-visible:outline-2
                           focus-visible:outline-offset-2 focus-visible:outline-red-700
                           sm:py-2.5"
                >
                    Ya, Keluar
                </button>
            </div>
            <p class="mt-3 text-center text-[11px] text-slate-400">
                Gunakan <kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">←</kbd><kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">→</kbd> untuk memilih, <kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">Enter</kbd> untuk konfirmasi.
            </p>
        </div>
    </div>

    <style>
        @media (min-width: 1024px) {
            body [data-open]:has(
                > [data-modal-backdrop],
                > [data-confirm-backdrop],
                > [data-profile-edit-backdrop],
                > [data-template-backdrop],
                > [data-kelas-detail-backdrop],
                > [data-penugasan-backdrop],
                > [data-penugasan-overview-backdrop],
                > [data-jam-backdrop],
                > [data-tugas-backdrop],
                > [data-tugas-edit-backdrop],
                > [data-materi-form-backdrop],
                > [data-tm-backdrop]
            ),
            body .logout-modal,
            body .pwd-confirm,
            body .pwd-modal,
            body .tpl-modal,
            body .km,
            body .gm,
            body .sm,
            body .kconfirm,
            body .gconfirm,
            body .sconfirm,
            body .proses-overlay,
            body [data-tm-modal],
            body [data-tugas-modal],
            body [data-materi-modal],
            body .jg-overlay,
            body #site-loading,
            body .swal2-container {
                left: var(--sidebar-w, 16rem);
            }
        }

        .logout-modal {
            opacity: 0;
            pointer-events: none;
            transition: opacity .3s ease;
        }

        .logout-modal[data-open="true"] {
            opacity: 1;
            pointer-events: auto;
        }

        .logout-modal-backdrop {
            opacity: 0;
            transition: opacity .3s ease;
        }

        .logout-modal[data-open="true"] .logout-modal-backdrop {
            opacity: 1;
        }

        .logout-modal-panel {
            opacity: 0;
            transform: translateY(20px) scale(.97);
            transition: opacity .3s cubic-bezier(.22,1,.36,1), transform .3s cubic-bezier(.22,1,.36,1);
        }

        .logout-modal[data-open="true"] .logout-modal-panel {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        .logout-modal [data-key-nav].logout-key-selected {
            outline: 2px solid #ef4444;
            outline-offset: 2px;
        }

        @media (prefers-reduced-motion: reduce) {
            .logout-modal, .logout-modal-backdrop, .logout-modal-panel {
                transition: none;
            }
        }
    </style>

    {{-- Loading Screen (muncul sebelum logout selesai) --}}
    @include('components.ui.loading-screen')

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const userMenu     = document.getElementById('user-menu');
            const userToggle   = document.getElementById('user-menu-toggle');
            const userDropdown = document.getElementById('user-menu-dropdown');
            const userChevron  = document.getElementById('user-menu-chevron');

            if (userMenu && userToggle && userDropdown) {
                const setOpen = (open) => {
                    userDropdown.classList.toggle('invisible', !open);
                    userDropdown.classList.toggle('opacity-0', !open);
                    userDropdown.classList.toggle('scale-95', !open);
                    userToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    userChevron?.classList.toggle('rotate-180', open);
                };

                userToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    setOpen(userDropdown.classList.contains('invisible'));
                });

                document.addEventListener('click', (e) => {
                    if (!userMenu.contains(e.target)) setOpen(false);
                });

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') setOpen(false);
                });
            }

            // Notifikasi
            const notifMenu = document.getElementById('notification-menu');
            const notifToggle = document.getElementById('notification-toggle');
            const notifDropdown = document.getElementById('notification-dropdown');
            const notifList = document.getElementById('notification-list');
            const notifBadge = document.getElementById('notification-badge');
            const notifFilters = document.getElementById('notification-filters');
            const markAllBtn = document.getElementById('mark-all-read');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            // Role halaman yang sedang dibuka (sama dengan sidebar). Wajib ikut di
            // URL karena endpoint notifikasi di-resolve per guard role.
            const notifRole = @json(auth()->user()?->role ?? 'siswa');
            const notifUrl = (path) => '{{ url('/dashboard') }}/' + notifRole + '/notifications' + path;

            if (notifMenu && notifToggle && notifDropdown && notifList) {
                // Filter aktif: Semua / Belum Dibaca / Sudah Dibaca. Status baca
                // disimpan di database (read_at), bukan di memori halaman, jadi
                // jumlah badge & isi tab selalu diambil ulang dari backend.
                let currentFilter = 'all';
                let lastCounts = { all: 0, unread: 0, read: 0 };

                const setNotifOpen = (open) => {
                    notifDropdown.classList.toggle('invisible', !open);
                    notifDropdown.classList.toggle('opacity-0', !open);
                    notifDropdown.classList.toggle('scale-95', !open);
                    notifToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                };

                const updateBadge = (count) => {
                    if (!notifBadge) return;
                    if (count && count > 0) {
                        notifBadge.textContent = count > 99 ? '99+' : count;
                        notifBadge.classList.remove('hidden');
                        notifBadge.classList.add('flex');
                    } else {
                        notifBadge.classList.add('hidden');
                        notifBadge.classList.remove('flex');
                    }
                };

                const setFilterActive = () => {
                    notifFilters?.querySelectorAll('[data-notif-filter]').forEach((btn) => {
                        const active = btn.dataset.notifFilter === currentFilter;
                        btn.setAttribute('aria-selected', active ? 'true' : 'false');
                        btn.classList.toggle('bg-primary-10', active);
                        btn.classList.toggle('text-primary-70', active);
                        btn.classList.toggle('text-slate-500', !active);
                        btn.classList.toggle('hover:bg-slate-50', !active);
                    });
                };

                const updateFilterCounts = (counts) => {
                    lastCounts = counts || lastCounts;
                    notifFilters?.querySelectorAll('[data-notif-count]').forEach((el) => {
                        const value = lastCounts[el.dataset.notifCount] ?? 0;
                        el.textContent = value;
                        el.classList.toggle('hidden', value <= 0);
                    });
                };

                const emptyState = () => {
                    if (currentFilter === 'unread') {
                        return '<div class="px-4 py-8 text-center"><p class="text-sm font-medium text-slate-700">Tidak ada notifikasi belum dibaca</p><p class="mt-1 text-xs text-slate-400">Semua notifikasi sudah dibaca</p></div>';
                    }
                    if (currentFilter === 'read') {
                        return '<div class="px-4 py-8 text-center"><p class="text-sm font-medium text-slate-700">Belum ada notifikasi yang dibaca</p><p class="mt-1 text-xs text-slate-400">Notifikasi yang Anda buka akan tersimpan di sini</p></div>';
                    }
                    return '<div class="px-4 py-8 text-center"><p class="text-sm font-medium text-slate-700">Tidak ada notifikasi</p><p class="mt-1 text-xs text-slate-400">Belum ada aktivitas yang perlu diberitahukan</p></div>';
                };

                const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

                const renderList = (items) => {
                    if (!items || items.length === 0) {
                        notifList.innerHTML = emptyState();
                        return;
                    }

                    notifList.innerHTML = items.map((n) => {
                        const iconMap = {
                            'megaphone': '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
                            'clipboard-list': '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
                            'award': '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>',
                            'inbox': '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
                            'clock': '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
                            'calendar-days': '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/>',
                            'square-pen': '<path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"/>',
                            'bell': '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
                        };
                        const svg = iconMap[n.icon] || iconMap['bell'];
                        // Notifikasi yang sudah dibaca tetap tampil (dengan titik
                        // pucat); yang belum dibaca bertitik biru.
                        const dot = n.is_read
                            ? '<span class="absolute -left-1 top-1/2 -translate-y-1/2 h-1.5 w-1.5 rounded-full bg-slate-300"></span>'
                            : '<span class="absolute -left-1 top-1/2 -translate-y-1/2 h-1.5 w-1.5 rounded-full bg-blue-500"></span>';
                        const href = esc(n.url || '#');
                        return `
                            <a href="${href}" data-notif-id="${n.id}" class="relative flex items-start gap-3 px-4 py-3 transition hover:bg-slate-50">
                                ${dot}
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${svg}</svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-slate-900 leading-snug">${esc(n.title)}</p>
                                    <p class="mt-0.5 text-xs text-slate-500 line-clamp-2">${esc(n.message)}</p>
                                    <p class="mt-1 text-[11px] text-slate-400">${esc(n.time)}</p>
                                </div>
                            </a>
                        `;
                    }).join('');
                };

                const loadNotifications = () => {
                    fetch(notifUrl('?filter=' + encodeURIComponent(currentFilter)), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' })
                        .then(r => r.ok ? r.json() : { notifications: [], unread_count: 0, counts: null })
                        .then(d => {
                            renderList(d.notifications || []);
                            updateBadge(d.unread_count || 0);
                            if (d.counts) updateFilterCounts(d.counts);
                        })
                        .catch(() => {
                            renderList([]);
                            updateBadge(0);
                        });
                };

                const loadCount = () => {
                    fetch(notifUrl('/count'), { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
                        .then(r => r.ok ? r.json() : { unread_count: 0 })
                        .then(d => updateBadge(d.unread_count || 0))
                        .catch(() => {});
                };

                notifToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isOpen = !notifDropdown.classList.contains('invisible');
                    setNotifOpen(!isOpen);
                    if (!isOpen) {
                        loadNotifications();
                    }
                });

                document.addEventListener('click', (e) => {
                    if (!notifMenu.contains(e.target)) setNotifOpen(false);
                });

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') setNotifOpen(false);
                });

                // Ganti tab filter: Semua / Belum Dibaca / Sudah Dibaca.
                notifFilters?.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-notif-filter]');
                    if (!btn) return;
                    const next = btn.dataset.notifFilter;
                    if (!next || next === currentFilter) return;
                    currentFilter = next;
                    setFilterActive();
                    loadNotifications();
                });

                notifList.addEventListener('click', (e) => {
                    const link = e.target.closest('a[data-notif-id]');
                    if (!link) return;
                    const id = link.getAttribute('data-notif-id');
                    if (!id) return;
                    if ((link.getAttribute('href') || '') === '#') e.preventDefault();
                    // Ubah visual item menjadi "sudah dibaca" tanpa menghapus baris.
                    const dot = link.querySelector('.bg-blue-500');
                    if (dot) {
                        dot.classList.remove('bg-blue-500');
                        dot.classList.add('bg-slate-300');
                        // Penurunan optimistis; angka final selalu diambil ulang
                        // dari response backend agar tetap akurat dengan database.
                        const raw = notifBadge?.textContent ?? '';
                        if (/^\d+$/.test(raw)) updateBadge(Math.max(0, parseInt(raw, 10) - 1));
                    }
                    fetch(notifUrl('/' + encodeURIComponent(id) + '/read'), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf || '', 'Accept': 'application/json' },
                        keepalive: true,
                    })
                        .then(r => r.ok ? r.json() : null)
                        .then(d => { if (d && typeof d.unread_count === 'number') updateBadge(d.unread_count); })
                        .catch(() => {});
                });

                // Tandai semua sebagai sudah dibaca: badge menjadi 0 dan seluruh
                // notifikasi tetap tampil pada filter Semua / Sudah Dibaca.
                markAllBtn?.addEventListener('click', (e) => {
                    e.preventDefault();
                    markAllBtn.disabled = true;
                    fetch(notifUrl('/mark-all-read'), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf || '', 'Accept': 'application/json' }
                    })
                        .then(r => r.ok ? r.json() : null)
                        .then(d => {
                            updateBadge(d && typeof d.unread_count === 'number' ? d.unread_count : 0);
                            loadNotifications();
                        })
                        .catch(() => {})
                        .finally(() => { markAllBtn.disabled = false; });
                });

                setFilterActive();
                loadNotifications();
                loadCount();
                setInterval(loadNotifications, 60000);
                setInterval(loadCount, 30000);
            }
        });
    </script>

    <script>
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                window.location.reload();
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            const logoutBtn    = document.getElementById('logout-btn');
            const logoutForm   = document.getElementById('logout-form');
            const logoutSideBtn  = document.getElementById('logout-btn-sidebar');
            const logoutSideForm = document.getElementById('logout-form-sidebar');
            const logoutModal    = document.getElementById('logout-modal');

            if (!logoutBtn || !logoutForm || !logoutModal) return;

            let activeLogoutForm = logoutForm;

            const noBtn  = logoutModal.querySelector('[data-logout-no]');
            const yesBtn = logoutModal.querySelector('[data-logout-yes]');
            const navBtns = [noBtn, yesBtn];
            let navIndex = 1;

            const openModal = () => {
                logoutModal.removeAttribute('inert');
                logoutModal.setAttribute('aria-hidden', 'false');
                requestAnimationFrame(() => { logoutModal.dataset.open = 'true'; });
                setNavIndex(1);
            };

            const closeModal = () => {
                logoutModal.dataset.open = 'false';
                logoutModal.setAttribute('aria-hidden', 'true');
                const finish = () => logoutModal.setAttribute('inert', '');
                logoutModal.addEventListener('transitionend', finish, { once: true });
                setTimeout(finish, 400);
            };

            const setNavIndex = (index, moveFocus = true) => {
                navIndex = index;
                navBtns.forEach((btn, i) => {
                    btn.classList.toggle('logout-key-selected', i === index);
                });
                if (moveFocus) navBtns[index].focus();
            };

            logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                activeLogoutForm = logoutForm;
                openModal();
            });

            logoutSideBtn?.addEventListener('click', (e) => {
                e.preventDefault();
                activeLogoutForm = logoutSideForm;
                openModal();
            });

            document.addEventListener('keydown', (e) => {
                if (logoutModal.dataset.open !== 'true') return;

                if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) {
                    e.preventDefault();
                    setNavIndex(navIndex === 0 ? 1 : 0);
                    return;
                }

                if (e.key === 'Enter') {
                    e.preventDefault();
                    navBtns[navIndex].click();
                    return;
                }

                if (e.key === 'Escape') {
                    e.preventDefault();
                    closeModal();
                }
            });

            noBtn.addEventListener('click', closeModal);
            logoutModal.querySelector('[data-modal-backdrop]').addEventListener('click', closeModal);

            yesBtn.addEventListener('click', () => {
                const loading = document.getElementById('site-loading');
                if (loading) {
                    const loadingText = loading.querySelector('[data-loading-text]');
                    if (loadingText) loadingText.textContent = 'Keluar dari akun...';
                    loading.removeAttribute('inert');
                    loading.setAttribute('aria-hidden', 'false');
                    requestAnimationFrame(() => { loading.dataset.open = 'true'; });
                }
                setTimeout(() => {
                    activeLogoutForm.submit();
                }, 800);
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const wrapper     = document.getElementById('global-search-wrapper');
            const form        = document.getElementById('global-search-form');
            const input       = document.getElementById('global-search-input');
            const clearBtn    = document.getElementById('global-search-clear');
            const box         = document.getElementById('global-search-results');
            const mobToggle   = document.getElementById('mobile-search-toggle');
            const mobClose    = document.getElementById('mobile-search-close');

            if (!wrapper || !input || !box) return;

            let timer = null;
            let requestId = 0;
            let activeIdx = -1;
            let currentQuery = '';

            const esc = (s) => String(s || '').replace(/[<>&"]/g, c => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;' }[c]));

            const syncClear = () => {
                const hasValue = input.value.trim().length > 0;
                if (clearBtn) clearBtn.classList.toggle('hidden', !hasValue);
            };

            const closeDropdown = () => {
                box.classList.add('hidden');
                box.innerHTML = '';
                activeIdx = -1;
            };

            const closeMobile = () => {
                wrapper.dataset.mobileOpen = 'false';
                if (mobClose) mobClose.classList.add('hidden');
                closeDropdown();
                input.blur();
            };

            if (mobToggle) {
                mobToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isOpen = wrapper.dataset.mobileOpen === 'true';
                    if (!isOpen) {
                        wrapper.dataset.mobileOpen = 'true';
                        if (mobClose) mobClose.classList.remove('hidden');
                        input.focus();
                        fetchSuggest(input.value.trim(), input.value.trim().length === 0);
                    } else {
                        closeMobile();
                    }
                });
            }

            if (mobClose) {
                mobClose.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    closeMobile();
                });
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    input.value = '';
                    syncClear();
                    input.focus();
                    fetchSuggest('', true);
                });
            }

            const getIconSvg = (name) => {
                const icons = {
                    'layout-dashboard': '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
                    'users': '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
                    'graduation-cap': '<path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/>',
                    'school': '<path d="M14 22v-4a2 2 0 1 0-4 0v4"/><path d="m18 10 4 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-8l4-2"/><path d="M18 5v17"/><path d="m4 6 8-4 8 4"/><path d="M6 5v17"/><circle cx="12" cy="9" r="2"/>',
                    'layers': '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.57 3.91a2 2 0 0 0 1.66 0l8.57-3.91a1 1 0 0 0 0-1.83z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
                    'book-open': '<path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/>',
                    'book-marked': '<path d="M10 2v8l3-3 3 3V2"/><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a1 1 0 0 1 0-5H20"/>',
                    'calendar-days': '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/>',
                    'award': '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>',
                    'bell': '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
                    'file-text': '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
                    'square-pen': '<path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"/>',
                    'key-round': '<path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/>',
                    'inbox': '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
                    'database-backup': '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 12a9 3 0 0 0 5 2.69"/><path d="M21 9.3V5"/><path d="M3 5v14a9 3 0 0 0 6.47 2.88"/><path d="M12 12v4h4"/><path d="m13 20 4-4-4-4"/>',
                    'user-round': '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
                };
                return icons[name] || '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>';
            };

            const renderLoading = () => {
                activeIdx = -1;
                box.innerHTML = `
                    <div class="flex items-center gap-2.5 px-4 py-3 text-xs font-medium text-slate-500">
                        <svg class="h-4 w-4 animate-spin text-blue-600" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <span>Mencari...</span>
                    </div>
                `;
                box.classList.remove('hidden');
            };

            const renderEmpty = (initial = false) => {
                activeIdx = -1;
                box.innerHTML = `
                    <div class="flex flex-col items-center justify-center px-4 py-8 text-center">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.3-4.3"></path>
                                <path d="m9.5 9.5 3 3"></path>
                                <path d="m12.5 9.5-3 3"></path>
                            </svg>
                        </span>
                        <p class="mt-2.5 text-sm font-semibold text-slate-700">
                            ${initial ? 'Rekomendasi Menu' : 'Tidak ada hasil yang ditemukan'}
                        </p>
                        <p class="mt-1 text-xs text-slate-400 max-w-xs">
                            ${initial ? 'Pilih menu atau mulai ketik untuk mencari data.' : 'Coba kata kunci lain atau periksa kembali ejaan Anda.'}
                        </p>
                    </div>
                `;
                box.classList.remove('hidden');
            };

            const render = (results, initial = false, q = '') => {
                const list = results || [];
                activeIdx = -1;

                if (!list.length) {
                    renderEmpty(initial);
                    return;
                }

                const headerText = initial ? 'Pintasan Menu' : `Hasil Pencarian (${list.length})`;
                let html = `
                    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2 bg-slate-50/60">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">${headerText}</span>
                        ${!initial && q ? `<span class="text-[11px] text-slate-400">Tekan Enter untuk semua hasil</span>` : ''}
                    </div>
                    <div class="py-1 divide-y divide-slate-50" role="listbox">
                `;

                list.forEach((r, i) => {
                    const label = esc(r.label);
                    const group = esc(r.group || (initial ? 'Menu' : 'Hasil'));
                    const url = r.url || '#';
                    const iconName = r.icon || 'search';
                    const iconSvg = getIconSvg(iconName);

                    html += `
                        <a href="${url}"
                           data-search-item="${i}"
                           role="option"
                           class="search-result-item flex items-center gap-3 px-3.5 py-2.5 transition hover:bg-blue-50/70 focus:bg-blue-50/70 focus:outline-none">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    ${iconSvg}
                                </svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-800 leading-snug">${label}</p>
                                <span class="inline-block text-[11px] font-medium text-slate-400">${group}</span>
                            </div>
                            <svg class="h-4 w-4 shrink-0 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="m9 18 6-6-6-6"/>
                            </svg>
                        </a>
                    `;
                });

                html += '</div>';

                if (!initial && q) {
                    const searchAllUrl = '/dashboard/search?q=' + encodeURIComponent(q);
                    html += `
                        <a href="${searchAllUrl}"
                           data-search-all="true"
                           class="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-4 py-2.5 text-xs font-semibold text-blue-600 hover:bg-blue-100/70 transition">
                            <span>Lihat semua hasil untuk "${esc(q)}"</span>
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>
                        </a>
                    `;
                }

                box.innerHTML = html;
                box.classList.remove('hidden');
            };

            const fetchSuggest = (q, isInitial = false) => {
                const id = ++requestId;
                currentQuery = q;

                if (!isInitial) {
                    renderLoading();
                } else {
                    activeIdx = -1;
                    box.innerHTML = `
                        <div class="flex items-center gap-2 px-4 py-3 text-xs text-slate-400">
                            <svg class="h-4 w-4 animate-spin text-blue-600" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <span>Memuat menu...</span>
                        </div>
                    `;
                    box.classList.remove('hidden');
                }

                fetch('/dashboard/search/suggest?q=' + encodeURIComponent(q), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    cache: 'no-store'
                })
                .then(r => {
                    if (!r.ok) throw new Error('Network error');
                    return r.json();
                })
                .then(d => {
                    if (id !== requestId) return;
                    render(d.results || [], !!d.initial, q);
                })
                .catch(() => {
                    if (id !== requestId) return;
                    renderEmpty(isInitial);
                });
            };

            input.addEventListener('input', () => {
                clearTimeout(timer);
                syncClear();
                const q = input.value.trim();

                if (q.length === 0) {
                    fetchSuggest('', true);
                    return;
                }

                timer = setTimeout(() => {
                    fetchSuggest(q, false);
                }, 180);
            });

            input.addEventListener('focus', () => {
                syncClear();
                const q = input.value.trim();
                if (q.length > 0) {
                    fetchSuggest(q, false);
                } else {
                    fetchSuggest('', true);
                }
            });

            input.addEventListener('keydown', (e) => {
                const items = Array.from(box.querySelectorAll('a[data-search-item], a[data-search-all]'));

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (!items.length) return;
                    activeIdx = (activeIdx + 1) % items.length;
                    items.forEach((item, i) => {
                        const isSel = i === activeIdx;
                        item.classList.toggle('bg-blue-50/90', isSel);
                        item.classList.toggle('ring-1', isSel);
                        item.classList.toggle('ring-inset', isSel);
                        item.classList.toggle('ring-blue-200', isSel);
                    });
                    items[activeIdx].scrollIntoView({ block: 'nearest' });
                    return;
                }

                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (!items.length) return;
                    activeIdx = (activeIdx - 1 + items.length) % items.length;
                    items.forEach((item, i) => {
                        const isSel = i === activeIdx;
                        item.classList.toggle('bg-blue-50/90', isSel);
                        item.classList.toggle('ring-1', isSel);
                        item.classList.toggle('ring-inset', isSel);
                        item.classList.toggle('ring-blue-200', isSel);
                    });
                    items[activeIdx].scrollIntoView({ block: 'nearest' });
                    return;
                }

                if (e.key === 'Enter') {
                    if (activeIdx >= 0 && items[activeIdx]) {
                        e.preventDefault();
                        window.location.href = items[activeIdx].href;
                        return;
                    }

                    const q = input.value.trim();
                    if (q.length === 0) {
                        e.preventDefault();
                    }
                    return;
                }

                if (e.key === 'Escape') {
                    closeDropdown();
                    if (wrapper.dataset.mobileOpen === 'true') {
                        closeMobile();
                    } else {
                        input.blur();
                    }
                }
            });

            // Klik di luar dropdown menutup panel
            document.addEventListener('mousedown', (e) => {
                const target = e.target;
                if (!wrapper.contains(target) && (!mobToggle || !mobToggle.contains(target))) {
                    closeDropdown();
                    if (wrapper.dataset.mobileOpen === 'true') {
                        closeMobile();
                    }
                }
            });

            syncClear();
        });
    </script>
    @stack('scripts')

</body>
</html>