@php
    use Illuminate\Support\Str;

    $role = auth()->user()->role ?? 'siswa';

    $allMenus = [
        'admin' => [
            ['label' => 'Dashboard', 'route' => 'dashboard.admin.index', 'icon' => 'layout-dashboard'],
            ['label' => 'Data Guru', 'route' => 'dashboard.admin.data-guru.index', 'icon' => 'users'],
            ['label' => 'Data Siswa', 'route' => 'dashboard.admin.data-siswa.index', 'icon' => 'graduation-cap'],
            ['label' => 'Data Kelas', 'route' => 'dashboard.admin.data-kelas.index', 'icon' => 'school'],
            ['label' => 'Data Jurusan', 'route' => 'dashboard.admin.data-jurusan.index', 'icon' => 'layers'],
            ['label' => 'Data Mata Pelajaran', 'route' => 'dashboard.admin.data-mata-pelajaran.index', 'icon' => 'book-open'],
            ['label' => 'Pengaturan Jam Belajar', 'route' => 'dashboard.admin.jam-belajar.index', 'icon' => 'clock'],
            ['label' => 'Jadwal Mengajar', 'route' => 'dashboard.admin.jadwal-mengajar.index', 'icon' => 'calendar-days'],
            ['label' => 'Manajemen Akun', 'route' => 'dashboard.admin.manajemen-akun.index', 'icon' => 'key-round'],
            ['label' => 'Pengajuan Reset Password', 'route' => 'dashboard.admin.pengajuan-reset-password.index', 'icon' => 'inbox', 'badge' => 'passwordResetPending'],
            ['label' => 'Kendala Akun', 'route' => 'dashboard.admin.kendala-akun.index', 'icon' => 'life-buoy', 'badge' => 'itHelpBaru'],
            ['label' => 'Laporan Akademik', 'route' => 'dashboard.admin.laporan-akademik.index', 'icon' => 'bar-chart-3'],
            ['label' => 'Log Aktivitas', 'route' => 'dashboard.admin.log-aktivitas.index', 'icon' => 'history'],
            ['label' => 'Backup Database', 'route' => 'dashboard.admin.backup-database.index', 'icon' => 'database-backup'],
        ],

        'guru' => [
            ['label' => 'Dashboard', 'route' => 'dashboard.guru.index', 'icon' => 'layout-dashboard'],
            ['label' => 'Data Siswa', 'route' => 'dashboard.guru.data-siswa.index', 'icon' => 'users'],
            ['label' => 'Jadwal Mengajar', 'route' => 'dashboard.guru.jadwal-pelajaran.index', 'icon' => 'calendar-days'],
            ['label' => 'Tugas & Materi', 'route' => 'dashboard.guru.tugas-materi.index', 'icon' => 'book-marked'],
            ['label' => 'Input Nilai', 'route' => 'dashboard.guru.input-nilai.index', 'icon' => 'square-pen'],
            ['label' => 'Rekap Nilai', 'route' => 'dashboard.guru.rekap-nilai.index', 'icon' => 'file-text'],
            ['label' => 'Laporan', 'route' => 'dashboard.guru.laporan.index', 'icon' => 'bar-chart-3'],
        ],

        'siswa' => [
            ['label' => 'Dashboard', 'route' => 'dashboard.siswa.index', 'icon' => 'layout-dashboard'],
            ['label' => 'Jadwal Pelajaran', 'route' => 'dashboard.siswa.jadwal-pelajaran.index', 'icon' => 'calendar-days'],
            ['label' => 'Tugas & Materi', 'route' => 'dashboard.siswa.tugas-materi.index', 'icon' => 'book-marked'],
            ['label' => 'Riwayat Aktivitas', 'route' => 'dashboard.siswa.riwayat-aktivitas.index', 'icon' => 'activity'],
            ['label' => 'Riwayat Akademik', 'route' => 'dashboard.siswa.riwayat-akademik.index', 'icon' => 'history'],
            ['label' => 'Rapor', 'route' => 'dashboard.siswa.rapor.index', 'icon' => 'award'],
        ],
    ];

    $adminGroups = [
        ['label' => 'Dashboard', 'standalone' => true, 'routes' => ['dashboard.admin.index']],
        ['label' => 'Data Master', 'items' => [
            'dashboard.admin.data-guru.index',
            'dashboard.admin.data-siswa.index',
            'dashboard.admin.data-kelas.index',
            'dashboard.admin.data-jurusan.index',
            'dashboard.admin.data-mata-pelajaran.index',
        ]],
        ['label' => 'Pengelolaan Akademik', 'items' => [
            'dashboard.admin.jam-belajar.index',
            'dashboard.admin.jadwal-mengajar.index',
        ]],
        ['label' => 'Manajemen Pengguna', 'items' => [
            'dashboard.admin.manajemen-akun.index',
            'dashboard.admin.pengajuan-reset-password.index',
            'dashboard.admin.kendala-akun.index',
        ]],
        ['label' => 'Laporan & Aktivitas', 'items' => [
            'dashboard.admin.laporan-akademik.index',
            'dashboard.admin.log-aktivitas.index',
        ]],
        ['label' => 'Sistem', 'items' => [
            'dashboard.admin.backup-database.index',
        ]],
    ];

    // Hanya tampilkan menu yang routing-nya sudah tersedia
    $menus = array_values(array_filter($allMenus[$role] ?? [], fn ($item) => Route::has($item['route'])));

    // Badge jumlah item yang menunggu tindakan admin (reset password & kendala akun).
    $badges = [];

    if (Route::has('dashboard.admin.pengajuan-reset-password.index')) {
        $badges['passwordResetPending'] = rescue(
            fn () => \App\Models\PasswordResetRequest::query()->pending()->count(),
            0,
            false
        );
    }

    if (Route::has('dashboard.admin.kendala-akun.index')) {
        $badges['itHelpBaru'] = rescue(
            fn () => \App\Models\ItHelpRequest::query()->baru()->count(),
            0,
            false
        );
    }
@endphp

<style>
    /* ===== Desktop (>= 1024px): sidebar tetap terlihat, tidak diubah ===== */
    #sidebar-backdrop { opacity: 0; pointer-events: none; }
    #sidebar-backdrop[data-open="true"] { opacity: 1; pointer-events: auto; }

    /* ===== Mobile/tablet (< 1024px): panel melayang, buka / tutup saja ===== */
    @media (max-width: 1023.98px) {
        #app-sidebar {
            top: max(.75rem, env(safe-area-inset-top));
            bottom: max(.75rem, env(safe-area-inset-bottom));
            left: max(.75rem, env(safe-area-inset-left));
            height: auto;
            width: 17rem !important;
            max-width: calc(100vw - 1.5rem);
            border-width: 1px;
            border-radius: 1.25rem;
            overflow: hidden;
            translate: none;
            transform: none;
            transition: transform .3s ease-in-out, visibility 0s;
        }

        /* Tertutup: geser keluar layar. Bayangan dimatikan & panel disembunyikan
           setelah animasi selesai, supaya tidak ada bayangan yang bocor di tepi layar. */
        #app-sidebar[data-state="closed"] {
            transform: translateX(calc(-100% - 4rem));
            box-shadow: none;
            visibility: hidden;
            transition: transform .3s ease-in-out, visibility 0s linear .3s;
        }

        /* Sembunyikan scrollbar di daftar menu (tetap bisa di-scroll) */
        #app-sidebar .sb-nav {
            scrollbar-width: none;          /* Firefox */
            -ms-overflow-style: none;       /* Edge lama */
        }

        #app-sidebar .sb-nav::-webkit-scrollbar {
            display: none;                  /* Chrome, Safari, Edge */
        }
    }

    @media (min-width: 1024px) {
        #app-sidebar[data-collapsed="true"] { width: 4.5rem; }
        #app-sidebar[data-collapsed="true"] .sb-header {
            justify-content: center;
            padding: 1.25rem .75rem;
        }
        #app-sidebar[data-collapsed="true"] .sb-brand,
        #app-sidebar[data-collapsed="true"] .sb-label,
        #app-sidebar[data-collapsed="true"] .sb-group-label,
        #app-sidebar[data-collapsed="true"] .sb-group-chevron,
        #app-sidebar[data-collapsed="true"] .sb-badge { display: none; }
        #sidebar-collapse-toggle {
            position: fixed;
            left: var(--sidebar-w, 16rem);
            top: 1.75rem;
            z-index: 60;
            transform: translate(-50%, -50%);
            transition: left .3s ease-in-out, background-color .15s ease-out, color .15s ease-out;
        }
        #sidebar-collapse-toggle [data-collapse-icon] {
            position: absolute;
            left: 50%;
            top: 50%;
            visibility: hidden;
            opacity: 0;
            pointer-events: none;
            transform: translate(-50%, -50%) translateX(4px);
        }
        #sidebar-collapse-toggle [data-collapse-icon="close"] {
            visibility: visible;
            opacity: 1;
            transform: translate(-50%, -50%);
        }
        #sidebar-collapse-toggle[data-ready="true"] [data-collapse-icon] {
            transition: opacity .25s ease-in-out, transform .25s ease-in-out, visibility .25s ease-in-out;
        }
        #app-sidebar[data-collapsed="true"] .sb-link {
            align-items: center;
            justify-content: center;
            gap: 0;
            width: 100%;
            min-height: 2.75rem;
            padding-left: .5rem;
            padding-right: .5rem;
            cursor: pointer;
            transition: background-color .15s ease-out, color .15s ease-out;
        }
        #app-sidebar[data-collapsed="true"] .sb-link > svg {
            width: 1.25rem;
            height: 1.25rem;
            flex-shrink: 0;
            transition: color .15s ease-out;
        }
        #app-sidebar[data-collapsed="true"] .sb-link:not([aria-current="page"]):hover { background-color: var(--color-secondary-50); }
        #app-sidebar[data-collapsed="true"] .sb-link:not([aria-current="page"]):hover > svg { color: var(--color-primary-70); }
        #app-sidebar[data-collapsed="true"] .sb-link[aria-current="page"] > svg { color: var(--color-primary-60); }
        #app-sidebar[data-collapsed="true"] [data-sidebar-group] {
            margin-top: .25rem;
            padding-top: .5rem;
            border-top: 1px solid #f1f5f9;
        }
        #app-sidebar[data-collapsed="true"] .sb-group-btn { display: none; }
        #app-sidebar[data-collapsed="true"] .sb-nav { padding-left: .75rem; padding-right: .75rem; }
        #app-sidebar[data-collapsed="true"] .sb-nav > div { padding-left: 0; padding-right: 0; }
        #app-sidebar[data-collapsed="true"] .sb-group-panel { grid-template-rows: 1fr !important; opacity: 1 !important; }
        #app-sidebar[data-collapsed="true"] > div:last-of-type { padding-left: .75rem; padding-right: .75rem; }
        #app-sidebar[data-collapsed="true"] .sb-nav *,
        #app-sidebar[data-collapsed="true"] > div:last-of-type * { overflow: visible; }
        #sb-tooltip {
            position: fixed;
            z-index: 70;
            max-width: 14rem;
            border-radius: .5rem;
            background: #1e293b;
            padding: .35rem .6rem;
            color: #fff;
            font-size: .75rem;
            font-weight: 500;
            line-height: 1.1;
            box-shadow: 0 6px 16px rgba(15, 23, 42, .18);
            opacity: 0;
            pointer-events: none;
            transform: translate(-.25rem, -50%);
            transition: opacity .15s ease-out, transform .15s ease-out;
            white-space: nowrap;
        }
        #sb-tooltip[data-visible="true"] { opacity: 1; transform: translate(0, -50%); }
    }

    @media (max-width: 1023.98px) {
        #app-sidebar[data-collapsed="true"] .sb-brand,
        #app-sidebar[data-collapsed="true"] .sb-label,
        #app-sidebar[data-collapsed="true"] .sb-group-label,
        #app-sidebar[data-collapsed="true"] .sb-group-chevron,
        #app-sidebar[data-collapsed="true"] .sb-badge,
        #app-sidebar[data-collapsed="true"] .sb-group-mini-icon { display: revert; }
    }

    @media (prefers-reduced-motion: reduce) {
        #app-sidebar,
        #app-sidebar[data-state="closed"],
        #sidebar-backdrop,
        #sidebar-collapse-toggle,
        #sidebar-collapse-toggle [data-collapse-icon],
        #sb-tooltip,
        #app-main {
            transition: none;
        }
    }
</style>

{{-- Backdrop (hanya mobile/tablet) --}}
<div
    id="sidebar-backdrop"
    data-open="false"
    aria-hidden="true"
    class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-[1px] transition-opacity duration-300 lg:hidden"
></div>

<aside
    id="app-sidebar"
    data-state="closed"
    data-collapsed="false"
    data-user-id="{{ auth()->id() }}"
    aria-label="Navigasi utama"
    class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col overscroll-contain border-r border-slate-200 bg-white
           shadow-2xl transition-[transform,width] duration-300 ease-in-out
           lg:translate-x-0 lg:shadow-none"
>

    {{-- Logo + tombol tutup (tombol hanya di mobile/tablet) --}}
    <div class="sb-header flex shrink-0 items-center gap-3 px-5 pb-6 pt-5 lg:pb-8 lg:pt-8">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50">
            <x-lucide-graduation-cap class="h-6 w-6 text-primary-60" />
        </span>

        <div class="sb-brand min-w-0 flex-1">
            <p class="text-xl font-bold leading-none tracking-tight text-[#0F3D73] lg:text-2xl">SASKANSA</p>
            <p class="mt-1 truncate text-xs font-medium text-primary-60" title="Sistem Akademik Sekolah">Sistem Akademik Sekolah</p>
        </div>

        <button
            type="button"
            data-sidebar-close
            aria-label="Tutup menu"
            class="-mr-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-slate-500 transition
                   hover:bg-slate-100 hover:text-slate-700
                   focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60
                   lg:hidden"
        >
            <x-lucide-x class="h-5 w-5" />
        </button>
    </div>

    {{-- Menu --}}
    <nav class="sb-nav min-h-0 flex-1 overflow-y-auto overflow-x-hidden px-3 sm:px-4" data-sidebar-nav>
        @if ($role === 'admin')
            @php
                $adminMenuByRoute = collect($menus)->keyBy('route');
                $isMenuActive = function (string $route, string $label): bool {
                    $isDashboard = $label === 'Dashboard';
                    return $isDashboard
                        ? request()->routeIs($route) || request()->routeIs('dashboard')
                        : request()->routeIs(Str::beforeLast($route, '.index').'.*');
                };
            @endphp
            <div class="space-y-1 pb-1">
                @foreach ($adminGroups as $group)
                    @if (! empty($group['standalone']))
                        @php
                            $menu = $adminMenuByRoute[$group['routes'][0]] ?? null;
                            $isActive = $menu ? $isMenuActive($menu['route'], $menu['label']) : false;
                        @endphp
                        @if ($menu)
                            <a
                                href="{{ route($menu['route']) }}"
                                data-sidebar-link
                                data-sidebar-tooltip="{{ $menu['label'] }}"
                                @if ($isActive) aria-current="page" @endif
                                @class([
                                    'sb-link group relative flex items-center gap-3 rounded-xl px-4 py-3 text-sm transition sm:gap-4',
                                    'bg-blue-50 font-bold text-primary-70' => $isActive,
                                    'font-medium text-[#0F3D73] hover:bg-blue-50/60' => ! $isActive,
                                ])
                            >
                                @if ($isActive)
                                    <span class="absolute left-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-full bg-primary-60"></span>
                                @endif
                                <x-dynamic-component
                                    :component="'lucide-'.$menu['icon']"
                                    :class="$isActive ? 'h-5 w-5 shrink-0 text-primary-60 fill-current' : 'h-5 w-5 shrink-0 text-primary-60'"
                                />
                                <span class="sb-label min-w-0 flex-1 truncate" title="{{ $menu['label'] }}">{{ $menu['label'] }}</span>
                            </a>
                        @endif
                    @else
                        @php
                            $groupItems = collect($group['items'])->map(fn ($r) => $adminMenuByRoute[$r] ?? null)->filter()->values();
                            $groupHasActive = $groupItems->contains(fn ($m) => $isMenuActive($m['route'], $m['label']));
                            $groupKey = Str::slug($group['label']);
                        @endphp
                        <div class="pt-2" data-sidebar-group data-group-key="{{ $groupKey }}" data-group-active="{{ $groupHasActive ? 'true' : 'false' }}">
                            <button
                                type="button"
                                data-sidebar-group-toggle
                                data-sidebar-tooltip="{{ $group['label'] }}"
                                aria-expanded="{{ $groupHasActive ? 'true' : 'false' }}"
                                aria-controls="sidebar-group-{{ $groupKey }}"
                                class="sb-group-btn flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-[11px] font-semibold tracking-wide text-slate-500 transition hover:bg-slate-50 hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60"
                            >
                                <span class="sb-group-label min-w-0 flex-1 truncate uppercase" title="{{ $group['label'] }}">{{ $group['label'] }}</span>
                                <x-lucide-chevron-right class="sb-group-chevron h-4 w-4 shrink-0 text-slate-400" data-group-chevron="closed" aria-hidden="true" />
                                <x-lucide-chevron-down class="sb-group-chevron hidden h-4 w-4 shrink-0 text-slate-400" data-group-chevron="open" aria-hidden="true" />
                                <x-lucide-folder class="sb-group-mini-icon hidden h-5 w-5 shrink-0 text-primary-60" aria-hidden="true" />
                            </button>
                            <div
                                id="sidebar-group-{{ $groupKey }}"
                                data-sidebar-group-panel
                                class="sb-group-panel grid transition-[grid-template-rows,opacity] duration-200 ease-out"
                                style="{{ $groupHasActive ? 'grid-template-rows:1fr;opacity:1;' : 'grid-template-rows:0fr;opacity:0;' }}"
                            >
                                <div class="overflow-hidden">
                                    <div class="space-y-1 pb-1 pt-1">
                                        @foreach ($groupItems as $menu)
                                            @php
                                                $isActive = $isMenuActive($menu['route'], $menu['label']);
                                                $badgeCount = isset($menu['badge']) ? ($badges[$menu['badge']] ?? 0) : 0;
                                            @endphp
                                            <a
                                                href="{{ route($menu['route']) }}"
                                                data-sidebar-link
                                                data-sidebar-tooltip="{{ $menu['label'] }}"
                                                @if ($isActive) aria-current="page" @endif
                                                @class([
                                                    'sb-link group relative flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm transition sm:gap-4',
                                                    'bg-blue-50 font-bold text-primary-70' => $isActive,
                                                    'font-medium text-[#0F3D73] hover:bg-blue-50/60' => ! $isActive,
                                                ])
                                            >
                                                @if ($isActive)
                                                    <span class="absolute left-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-full bg-primary-60"></span>
                                                @endif
                                                <x-dynamic-component
                                                    :component="'lucide-'.$menu['icon']"
                                                    class="h-5 w-5 shrink-0 text-primary-60"
                                                />
                                                <span class="sb-label min-w-0 flex-1 truncate" title="{{ $menu['label'] }}">{{ $menu['label'] }}</span>
                                                @if ($badgeCount > 0)
                                                    <span class="sb-badge inline-flex min-w-5 shrink-0 items-center justify-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-700">
                                                        {{ $badgeCount > 99 ? '99+' : $badgeCount }}
                                                    </span>
                                                @endif
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @else
            <div class="space-y-1">
                @foreach ($menus as $menu)
                    @php
                        $isDashboard = $menu['label'] === 'Dashboard';
                        $isActive = $isDashboard
                            ? request()->routeIs($menu['route']) || request()->routeIs('dashboard')
                            : request()->routeIs(Str::beforeLast($menu['route'], '.index').'.*');
                    @endphp
                    <a
                        href="{{ route($menu['route']) }}"
                        data-sidebar-link
                        data-sidebar-tooltip="{{ $menu['label'] }}"
                        @if ($isActive) aria-current="page" @endif
                        @class([
                            'sb-link group relative flex items-center gap-3 rounded-xl px-4 py-3 text-sm transition sm:gap-4',
                            'bg-blue-50 font-bold text-primary-70' => $isActive,
                            'font-medium text-[#0F3D73] hover:bg-blue-50/60' => ! $isActive,
                        ])
                    >
                        @if ($isActive)
                            <span class="absolute left-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-full bg-primary-60"></span>
                        @endif
                        <x-dynamic-component
                            :component="'lucide-'.$menu['icon']"
                            :class="$isActive && $isDashboard
                                ? 'h-5 w-5 shrink-0 text-primary-60 fill-current'
                                : 'h-5 w-5 shrink-0 text-primary-60'"
                        />
                        <span class="sb-label min-w-0 flex-1 truncate" title="{{ $menu['label'] }}">{{ $menu['label'] }}</span>
                        @php
                            $badgeCount = isset($menu['badge']) ? ($badges[$menu['badge']] ?? 0) : 0;
                        @endphp
                        @if ($badgeCount > 0)
                            <span class="sb-badge inline-flex min-w-5 shrink-0 items-center justify-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-700">
                                {{ $badgeCount > 99 ? '99+' : $badgeCount }}
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </nav>

    <div class="shrink-0 border-t border-slate-100 px-3 pb-5 pt-4 sm:px-4">
        <form id="logout-form-sidebar" method="POST" action="{{ route('logout') }}">
            @csrf
            <input type="hidden" name="role" value="{{ auth()->user()->role }}">
            <button
                id="logout-btn-sidebar"
                type="submit"
                data-sidebar-tooltip="Log Out"
                class="sb-link group flex w-full items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-[#0F3D73] transition hover:bg-red-50 hover:text-red-600 sm:gap-4"
            >
                <x-lucide-log-out class="h-5 w-5 shrink-0 text-primary-60 transition group-hover:text-red-600" />
                <span class="sb-label min-w-0 flex-1 truncate text-left">Log Out</span>
            </button>
        </form>
    </div>
</aside>

{{-- Tombol collapse berdiri di garis batas sidebar/konten agar tidak mengganggu header --}}
<button
    id="sidebar-collapse-toggle"
    type="button"
    data-sidebar-collapse-toggle
    data-sidebar-tooltip="Tutup Sidebar"
    aria-label="Tutup Sidebar"
    aria-pressed="false"
    aria-controls="app-sidebar"
    class="hidden h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:bg-blue-50 hover:text-primary-70 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60 lg:flex"
>
    <x-lucide-panel-left-close class="h-[18px] w-[18px]" data-collapse-icon="close" aria-hidden="true" />
    <x-lucide-panel-left-open class="h-[18px] w-[18px]" data-collapse-icon="open" aria-hidden="true" />
</button>

<script>
    (() => {
        // Elemen selalu dicari ulang (tidak disimpan), supaya tetap jalan
        // setelah navigasi Turbo / Livewire yang mengganti isi halaman.
        if (window.__sidebarBound) { window.__sidebarSync?.(); return; }
        window.__sidebarBound = true;

        const HIDDEN = '-translate-x-full';
        const TOGGLE = '[data-sidebar-toggle], [aria-controls="app-sidebar"]';

        const $ = (id) => document.getElementById(id);
        const mq = window.matchMedia('(min-width: 1024px)');
        const isDesktop = () => mq.matches;

        // Hanya dua kondisi: 'closed' atau 'open'
        const apply = (state) => {
            const sb = $('app-sidebar');
            const bd = $('sidebar-backdrop');
            if (!sb) return;

            const mobile = !isDesktop();
            const open = state === 'open';

            sb.dataset.state = state;
            sb.classList.toggle(HIDDEN, !open);
            sb.inert = mobile && !open; // menu tersembunyi tidak bisa difokus keyboard
            if (bd) bd.dataset.open = String(open && mobile);
            document.documentElement.classList.toggle('overflow-hidden', open && mobile);

            document.querySelectorAll(TOGGLE)
                .forEach((b) => b.setAttribute('aria-expanded', String(open)));
        };

        const current = () => $('app-sidebar')?.dataset.state || 'closed';

        let tooltipTimer = null;
        let tooltipHideTimer = null;

        const showTooltip = (target) => {
            const sb = $('app-sidebar');
            const label = target?.dataset.sidebarTooltip;
            if (!sb || sb.dataset.collapsed !== 'true' || !label) return;
            clearTimeout(tooltipHideTimer);
            clearTimeout(tooltipTimer);
            tooltipTimer = setTimeout(() => {
                if (sb.dataset.collapsed !== 'true' || !target.isConnected) return;
                let tip = $('sb-tooltip');
                if (!tip) {
                    tip = document.createElement('div');
                    tip.id = 'sb-tooltip';
                    tip.setAttribute('role', 'tooltip');
                    document.body.appendChild(tip);
                }
                tip.textContent = label;
                const rect = target.getBoundingClientRect();
                tip.style.left = `${rect.right + 12}px`;
                tip.style.top = `${rect.top + rect.height / 2}px`;
                tip.dataset.visible = 'true';
            }, 80);
        };

        const hideTooltip = (grace = false) => {
            clearTimeout(tooltipTimer);
            clearTimeout(tooltipHideTimer);
            const hide = () => {
                const tip = $('sb-tooltip');
                if (tip) tip.dataset.visible = 'false';
            };
            if (grace) tooltipHideTimer = setTimeout(hide, 160);
            else hide();
        };

        const tooltipTarget = (node) => (node instanceof Element ? node.closest('[data-sidebar-tooltip]') : null);
        const tooltipOwns = (target, related) => related instanceof Node && target.contains(related);

        document.addEventListener('pointerover', (e) => {
            const target = tooltipTarget(e.target);
            if (!target || tooltipOwns(target, e.relatedTarget)) return;
            showTooltip(target);
        });

        document.addEventListener('pointerout', (e) => {
            const target = tooltipTarget(e.target);
            if (!target || tooltipOwns(target, e.relatedTarget)) return;
            hideTooltip(true);
        });

        document.addEventListener('focusin', (e) => {
            const target = tooltipTarget(e.target);
            if (target) showTooltip(target);
        });

        document.addEventListener('focusout', (e) => {
            const target = tooltipTarget(e.target);
            if (target) hideTooltip(true);
        });

        const prefKey = () => `saskansa.sidebar.collapsed.${$('app-sidebar')?.dataset.userId || ''}`;
        const applyCollapsed = (collapsed, persist = false) => {
            const sb = $('app-sidebar');
            if (!sb) return;
            hideTooltip();
            sb.dataset.collapsed = collapsed ? 'true' : 'false';
            document.documentElement.style.setProperty('--sidebar-w', collapsed ? '4.5rem' : '16rem');
            document.querySelectorAll('[data-sidebar-collapse-toggle]').forEach((btn) => {
                btn.setAttribute('aria-pressed', String(collapsed));
                btn.setAttribute('aria-label', collapsed ? 'Buka Sidebar' : 'Tutup Sidebar');
                btn.dataset.sidebarTooltip = collapsed ? 'Buka Sidebar' : 'Tutup Sidebar';
                btn.dataset.collapsed = collapsed ? 'true' : 'false';
                btn.dataset.activeIcon = collapsed ? 'open' : 'close';

                const close = btn.querySelector('[data-collapse-icon="close"]');
                const open = btn.querySelector('[data-collapse-icon="open"]');
                if (close) {
                    close.style.visibility = collapsed ? 'hidden' : 'visible';
                    close.style.opacity = collapsed ? '0' : '1';
                    close.style.transform = `translate(-50%, -50%) translateX(${collapsed ? '-4px' : '0'})`;
                }
                if (open) {
                    open.style.visibility = collapsed ? 'visible' : 'hidden';
                    open.style.opacity = collapsed ? '1' : '0';
                    open.style.transform = `translate(-50%, -50%) translateX(${collapsed ? '0' : '4px'})`;
                }

                btn.dataset.ready = 'true';
            });
            if (persist) {
                try { localStorage.setItem(prefKey(), collapsed ? 'collapsed' : 'expanded'); } catch (_) {}
            }
        };

        // Kompatibilitas: jika script lain (mis. script hamburger lama) hanya
        // menambah/menghapus class "-translate-x-full", ikuti perubahannya.
        const onClassChange = () => {
            const sb = $('app-sidebar');
            if (!sb || isDesktop()) return;
            const hidden = sb.classList.contains(HIDDEN);
            const state = sb.dataset.state;
            if (hidden && state !== 'closed') apply('closed');
            else if (!hidden && state === 'closed') apply('open');
        };

        const observer = new MutationObserver(onClassChange);
        let watched = null;
        const watch = () => {
            const sb = $('app-sidebar');
            if (sb && sb !== watched) {
                observer.disconnect();
                observer.observe(sb, { attributes: true, attributeFilter: ['class'] });
                watched = sb;
            }
        };

        const groupKey = (el) => `saskansa.sidebar.group.${el.dataset.groupKey || ''}`;
        const setGroupOpen = (group, open) => {
            const btn = group.querySelector('[data-sidebar-group-toggle]');
            const panel = group.querySelector('[data-sidebar-group-panel]');
            const chevronClosed = group.querySelector('[data-group-chevron="closed"]');
            const chevronOpen = group.querySelector('[data-group-chevron="open"]');
            if (btn) btn.setAttribute('aria-expanded', String(open));
            if (panel) {
                panel.style.gridTemplateRows = open ? '1fr' : '0fr';
                panel.style.opacity = open ? '1' : '0';
            }
            if (chevronClosed) chevronClosed.classList.toggle('hidden', open);
            if (chevronOpen) chevronOpen.classList.toggle('hidden', !open);
            group.dataset.open = open ? 'true' : 'false';
        };

        const closeGroupFlyout = () => {
            document.getElementById('sidebar-group-flyout')?.remove();
        };

        const openGroupFlyout = (group, toggle) => {
            closeGroupFlyout();
            const panel = group.querySelector('[data-sidebar-group-panel]');
            const links = [...(panel?.querySelectorAll('[data-sidebar-link]') ?? [])].map((a) => ({
                label: a.dataset.sidebarTooltip || a.textContent.trim(),
                href: a.getAttribute('href'),
                current: a.getAttribute('aria-current') === 'page',
            }));
            if (!links.length) return;

            const flyout = document.createElement('div');
            flyout.id = 'sidebar-group-flyout';
            flyout.className = 'fixed z-60 w-60 rounded-xl border border-slate-200 bg-white p-2 shadow-xl';
            const rect = toggle.getBoundingClientRect();
            flyout.style.left = `${rect.right + 12}px`;
            flyout.style.top = `${Math.max(12, Math.min(rect.top - 8, window.innerHeight - 12 - (links.length * 44 + 30)))}px`;
            flyout.innerHTML = `
                <p class="px-2 pb-1 pt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">${group.dataset.groupKey.replaceAll('-', ' ')}</p>
                ${links.map((l) => `<a href="${l.href}" ${l.current ? 'aria-current="page"' : ''} class="flex items-center gap-2 rounded-lg px-2 py-2 text-sm ${l.current ? 'bg-blue-50 font-bold text-primary-70' : 'font-medium text-[#0F3D73] hover:bg-blue-50/60'}"><span class="truncate">${l.label}</span></a>`).join('')}
            `;
            document.body.appendChild(flyout);
        };

        window.__sidebarSyncGroups = () => {
            closeGroupFlyout();
            document.querySelectorAll('[data-sidebar-group]').forEach((group) => {
                const active = group.dataset.groupActive === 'true';
                let open = active;
                try {
                    const stored = sessionStorage.getItem(groupKey(group));
                    if (!active && stored !== null) open = stored === 'open';
                } catch (_) {}
                setGroupOpen(group, open);
            });
        };

        window.__sidebarSync = () => {
            watch();
            hideTooltip();
            let saved = null;
            try { saved = localStorage.getItem(prefKey()); } catch (_) {}
            applyCollapsed(saved === 'collapsed');
            window.__sidebarSyncGroups?.();
            apply(isDesktop() ? 'closed' : current());
        };

        // Fase capture: tombol hamburger ditangani di sini saja, sehingga
        // tidak terjadi "double toggle" dengan listener lama di tombol yang sama.
        document.addEventListener('click', (e) => {
            if (!$('app-sidebar')) return;
            watch();

            const collapseToggle = e.target.closest('[data-sidebar-collapse-toggle]');
            if (collapseToggle) {
                e.preventDefault();
                e.stopPropagation();
                applyCollapsed($('app-sidebar')?.dataset.collapsed !== 'true', true);
                return;
            }

            const groupToggle = e.target.closest('[data-sidebar-group-toggle]');
            if (groupToggle) {
                e.preventDefault();
                const group = groupToggle.closest('[data-sidebar-group]');
                if (!group) return;
                if ($('app-sidebar')?.dataset.collapsed === 'true') {
                    if (document.getElementById('sidebar-group-flyout')) closeGroupFlyout();
                    else openGroupFlyout(group, groupToggle);
                    return;
                }
                const open = group.dataset.open !== 'true';
                setGroupOpen(group, open);
                try { sessionStorage.setItem(groupKey(group), open ? 'open' : 'closed'); } catch (_) {}
                return;
            }

            // Tombol hamburger: buka <-> tutup
            if (e.target.closest(TOGGLE)) {
                e.preventDefault();
                e.stopPropagation();
                apply(current() === 'closed' ? 'open' : 'closed');
                return;
            }

            if (!e.target.closest('#sidebar-group-flyout') && !e.target.closest('[data-sidebar-group-toggle]')) {
                closeGroupFlyout();
            }

            // Tombol X di dalam sidebar, klik menu, atau klik backdrop: langsung tutup
            if (
                e.target.closest('[data-sidebar-close]') ||
                e.target.closest('[data-sidebar-link]') ||
                e.target.closest('#sidebar-group-flyout a') ||
                e.target === $('sidebar-backdrop')
            ) {
                if (!isDesktop()) apply('closed');
            }
        }, true);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !isDesktop()) apply('closed');
        });

        mq.addEventListener('change', () => apply('closed'));

        ['turbo:load', 'livewire:navigated', 'pageshow'].forEach((ev) =>
            document.addEventListener(ev, () => window.__sidebarSync())
        );

        window.__sidebarSync();
    })();
</script>