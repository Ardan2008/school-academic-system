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
            ['label' => 'Mata Pelajaran', 'route' => 'dashboard.guru.mata-pelajaran.index', 'icon' => 'book-open'],
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
            width: 17rem;
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

    @media (prefers-reduced-motion: reduce) {
        #app-sidebar,
        #app-sidebar[data-state="closed"],
        #sidebar-backdrop {
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
    aria-label="Navigasi utama"
    class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col overscroll-contain border-r border-slate-200 bg-white
           shadow-2xl transition-[transform,width] duration-300 ease-in-out
           lg:translate-x-0 lg:shadow-none"
>

    {{-- Logo + tombol tutup (tombol hanya di mobile/tablet) --}}
    <div class="flex shrink-0 items-center gap-3 px-5 pb-6 pt-5 lg:pb-8 lg:pt-8">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50">
            <x-lucide-graduation-cap class="h-6 w-6 text-primary-60" />
        </span>

        <div class="min-w-0 flex-1">
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
    <nav class="sb-nav min-h-0 flex-1 space-y-1 overflow-y-auto overflow-x-hidden px-3 sm:px-4">
        @foreach ($menus as $menu)
            @php
                $isDashboard = $menu['label'] === 'Dashboard';

                $isActive = $isDashboard
                    ? request()->routeIs($menu['route']) || request()->routeIs('dashboard')
                    : request()->routeIs(Str::beforeLast($menu['route'], '.index') . '.*');
            @endphp

            <a
                href="{{ route($menu['route']) }}"
                data-sidebar-link
                @if ($isActive) aria-current="page" @endif
                @class([
                    'group relative flex items-center gap-3 rounded-xl px-4 py-3 text-sm transition sm:gap-4',
                    'bg-blue-50 font-bold text-primary-70' => $isActive,
                    'font-medium text-[#0F3D73] hover:bg-blue-50/60' => ! $isActive,
                ])
            >
                {{-- Penanda aktif: garis pendek di sisi kiri --}}
                @if ($isActive)
                    <span class="absolute left-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-full bg-primary-60"></span>
                @endif

                <x-dynamic-component
                    :component="'lucide-'.$menu['icon']"
                    :class="$isActive && $isDashboard
                        ? 'h-5 w-5 shrink-0 text-primary-60 fill-current'
                        : 'h-5 w-5 shrink-0 text-primary-60'"
                />
                <span class="truncate" title="{{ $menu['label'] }}">{{ $menu['label'] }}</span>

                @php
                    $badgeCount = isset($menu['badge']) ? ($badges[$menu['badge']] ?? 0) : 0;
                @endphp

                @if ($badgeCount > 0)
                    <span class="ml-auto inline-flex min-w-5 shrink-0 items-center justify-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[11px] font-bold text-amber-700">
                        {{ $badgeCount > 99 ? '99+' : $badgeCount }}
                    </span>
                @endif
            </a>
        @endforeach
    </nav>

    <div class="shrink-0 border-t border-slate-100 px-3 pb-5 pt-4 sm:px-4">
        <form id="logout-form-sidebar" method="POST" action="{{ route('logout') }}">
            @csrf
            <input type="hidden" name="role" value="{{ auth()->user()->role }}">
            <button
                id="logout-btn-sidebar"
                type="submit"
                class="group flex w-full items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-[#0F3D73] transition hover:bg-red-50 hover:text-red-600 sm:gap-4"
            >
                <x-lucide-log-out class="h-5 w-5 shrink-0 text-primary-60 transition group-hover:text-red-600" />
                <span>Log Out</span>
            </button>
        </form>
    </div>
</aside>

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

        window.__sidebarSync = () => {
            watch();
            apply(isDesktop() ? 'closed' : current());
        };

        // Fase capture: tombol hamburger ditangani di sini saja, sehingga
        // tidak terjadi "double toggle" dengan listener lama di tombol yang sama.
        document.addEventListener('click', (e) => {
            if (!$('app-sidebar')) return;
            watch();

            // Tombol hamburger: buka <-> tutup
            if (e.target.closest(TOGGLE)) {
                e.preventDefault();
                e.stopPropagation();
                apply(current() === 'closed' ? 'open' : 'closed');
                return;
            }

            // Tombol X di dalam sidebar, klik menu, atau klik backdrop: langsung tutup
            if (
                e.target.closest('[data-sidebar-close]') ||
                e.target.closest('[data-sidebar-link]') ||
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