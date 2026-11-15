@extends('layouts.auth')

@section('title', 'Login - SASKANSA')

@php
    $selectedField = $roles[$selectedRole]['field'] ?? 'email';
    $roleError = $errors->get('role')[0] ?? null;
    $identifierError = $errors->get($selectedField)[0] ?? null;
    $passwordError = $errors->get('password')[0] ?? null;
@endphp

@section('content')

<div class="h-dvh w-full overflow-hidden bg-slate-50 text-slate-900">

    <div class="flex h-dvh items-center justify-center px-4 py-4 sm:px-6 sm:py-6 overflow-y-auto">

        <div
            class="grid w-full max-w-4xl max-h-full overflow-y-auto rounded-2xl bg-white
                   shadow-xl ring-1 ring-slate-100 sm:rounded-3xl lg:grid-cols-2"
        >

            {{-- Panel Form --}}
            <div class="relative flex flex-col justify-center px-6 pb-6 pt-10 sm:px-10 sm:py-8">

                {{-- Tombol Back (area tap diperbesar) --}}
                <a
                    href="{{ route('beranda') }}"
                    id="back-btn"
                    data-loading-back
                    data-loading-text="Kembali..."
                    class="absolute left-3 top-3 rounded-full p-2.5 text-slate-400
                           transition hover:bg-slate-50 hover:text-blue-600
                           focus-visible:outline-2
                           focus-visible:outline-offset-2 focus-visible:outline-blue-500
                           sm:left-5 sm:top-5"
                    aria-label="Kembali"
                >
                    <x-lucide-arrow-left class="h-5 w-5" />
                </a>

                <div class="mx-auto w-full max-w-sm">

                    {{-- Icon + Header --}}
                    <div class="mb-4 text-center">
                        <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center text-blue-600">
                            <x-lucide-graduation-cap class="h-8 w-8" />
                        </div>

                        <h1 class="text-lg font-bold tracking-tight text-slate-900">
                            Selamat Datang Kembali
                        </h1>

                        <p class="mt-1 text-sm font-semibold text-blue-600">
                            Sistem Akademik Sekolah
                        </p>

                        <p class="mt-2 text-xs leading-relaxed text-slate-400">
                            Pilih role akunmu, lalu masuk dengan identitas yang terdaftar.
                        </p>
                    </div>

                    <form
                        id="login-form"
                        action="{{ route('login.store') }}"
                        method="POST"
                        class="space-y-4"
                        novalidate
                    >

                        @csrf

                        <div>
                            <label for="role" class="mb-1.5 block text-sm font-semibold text-slate-800">
                                Role
                            </label>

                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                    <x-lucide-user-round-cog class="h-4.5 w-4.5" />
                                </span>

                                <select
                                    id="role"
                                    name="role"
                                    required
                                    class="w-full appearance-none rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-10
                                           text-base text-slate-900 outline-none transition
                                           focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10
                                           sm:text-sm"
                                >
                                    @foreach ($roles as $roleKey => $roleMeta)
                                        <option value="{{ $roleKey }}" @selected($roleKey === $selectedRole)>
                                            {{ $roleMeta['label'] }}
                                        </option>
                                    @endforeach
                                </select>

                                <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400">
                                    <x-lucide-chevron-down class="h-4 w-4" />
                                </span>
                            </div>

                            <p data-role-error class="mt-2 hidden text-xs text-red-500"></p>
                        </div>

                        {{-- Identifier (Email / NIP / NIS, mengikuti role) --}}
                        <div>
                            <label for="identifier" class="mb-1.5 block text-sm font-semibold text-slate-800">
                                <span data-identifier-label>{{ $roles[$selectedRole]['input_label'] ?? 'Email' }}</span>
                            </label>

                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                    <x-lucide-mail data-icon-mail class="h-4.5 w-4.5" />
                                    <x-lucide-id-card data-icon-id-card class="hidden h-4.5 w-4.5" />
                                </span>

                                {{-- text-base (16px) di HP agar Safari/iOS tidak zoom otomatis saat fokus --}}
                                <input
                                    type="{{ $selectedField === 'email' ? 'email' : 'text' }}"
                                    id="identifier"
                                    name="{{ $selectedField }}"
                                    value="{{ old($selectedField) }}"
                                    placeholder="{{ $roles[$selectedRole]['placeholder'] ?? '' }}"
                                    maxlength="{{ $roles[$selectedRole]['maxlength'] ?? 120 }}"
                                    required
                                    autofocus
                                    autocomplete="{{ $roles[$selectedRole]['autocomplete'] ?? 'off' }}"
                                    inputmode="{{ $roles[$selectedRole]['input_mode'] ?? 'text' }}"
                                    class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-4
                                           text-base text-slate-900 outline-none transition
                                           placeholder:text-slate-400
                                           focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10
                                           sm:text-sm"
                                >
                            </div>

                            <p data-identifier-error class="mt-2 hidden text-xs text-red-500"></p>
                        </div>

                        <div>
                            <div class="mb-1.5 flex items-center justify-between gap-3">
                                <label for="password" class="block text-sm font-semibold text-slate-800">
                                    Password
                                </label>

                                <button
                                    type="button"
                                    id="forgot-password-btn"
                                    class="shrink-0 py-1 text-xs font-medium text-blue-600
                                           hover:text-blue-700
                                           focus-visible:outline-2
                                           focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                                >
                                    Lupa password?
                                </button>
                            </div>

                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                    <x-lucide-lock class="h-4.5 w-4.5" />
                                </span>

                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    placeholder="Masukkan password anda"
                                    required
                                    autocomplete="current-password"
                                    class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-10
                                           text-base text-slate-900 outline-none transition
                                           placeholder:text-slate-400
                                           focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10
                                           sm:text-sm"
                                >
                                <button
                                    type="button"
                                    id="toggle-password"
                                    aria-label="Tampilkan password"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-primary-60"
                                >
                                    <x-lucide-eye class="toggle-eye-show h-5 w-5" />
                                    <x-lucide-eye-off class="toggle-eye-hide hidden h-5 w-5" />
                                </button>
                            </div>

                            <p data-password-error class="mt-2 hidden text-xs text-red-500"></p>
                        </div>

                        {{-- Banner lockout (countdown real-time) --}}
                        <div
                            id="lockout-banner"
                            class="hidden rounded-xl border border-red-200 bg-red-50 p-4"
                        >
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
                                    <x-lucide-shield-alert class="h-4.5 w-4.5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold text-red-700 sm:text-sm">
                                        Terlalu banyak percobaan login
                                    </p>
                                    <p class="mt-0.5 text-xs text-red-500">
                                        Akun sementara dikunci. Coba lagi dalam
                                        <span data-lockout-time class="font-mono font-semibold text-red-700">--:--</span>.
                                    </p>
                                </div>
                            </div>
                            <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-red-100">
                                <div
                                    data-lockout-bar
                                    class="h-full w-0 rounded-full bg-red-500 transition-[width] duration-1000 ease-linear"
                                ></div>
                            </div>
                        </div>

                        {{-- Data lockout dari server (agar countdown tetap aktif meski halaman di-refresh) --}}
                        <div id="initial-lock" class="hidden" data-lock="{{ json_encode($lock ?? null) }}"></div>

                        <button
                            type="submit"
                            id="submit-btn"
                            class="flex w-full items-center justify-center gap-2 rounded-2xl bg-blue-700 px-4 py-3
                                   text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition
                                   hover:bg-blue-800 focus-visible:outline-2
                                   focus-visible:outline-offset-2 focus-visible:outline-blue-700
                                   active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-70"
                        >
                            <x-lucide-log-in class="h-4 w-4" />
                            Masuk
                        </button>

                    </form>

                </div>
            </div>

            {{-- Panel Foto (desktop) --}}
            <div class="relative hidden overflow-hidden lg:block">
                <img
                    src="{{ asset('image/login/login.webp') }}"
                    alt="Siswa SASKANSA"
                    loading="eager"
                    class="absolute inset-0 h-full w-full object-cover"
                >

                <div class="absolute inset-0 bg-linear-to-t from-black/25 via-transparent to-transparent"></div>

                {{-- Glass Card --}}
                <div class="absolute inset-x-0 bottom-0 p-5">
                    <div class="rounded-2xl border border-white/25 bg-white/10 p-6 text-white shadow-2xl backdrop-blur-2xl">
                        <h3 class="text-xl font-bold leading-tight drop-shadow-sm">
                            Sistem Akademik Sekolah
                        </h3>
                        <p class="mt-1 text-sm font-semibold text-white/90 drop-shadow-sm">
                            Terintegrasi, Efisien, Transparan
                        </p>

                        <div class="my-4 h-0.5 w-12 rounded-full bg-linear-to-r from-blue-300 to-white/60"></div>

                        <p class="text-sm leading-relaxed text-white/85 drop-shadow-sm">
                            Platform terpusat untuk mengelola data akademik sekolah secara digital. Memudahkan administrasi, meningkatkan komunikasi, dan mendukung proses belajar mengajar yang lebih efektif.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Popup Konfirmasi --}}
    <div
        id="confirm-modal"
        data-open="false"
        aria-hidden="true"
        inert
        class="auth-modal fixed inset-0 z-70 flex items-center justify-center p-4"
    >
        <div data-modal-backdrop class="auth-modal-backdrop absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>

        <div
            data-modal-panel
            role="dialog"
            aria-modal="true"
            class="auth-modal-panel relative z-10 w-full max-w-sm rounded-2xl bg-white p-5 text-center shadow-2xl sm:p-6"
        >
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                <x-lucide-log-in class="h-5 w-5" />
            </div>

            <h3 class="text-base font-semibold text-slate-900">Yakin ingin masuk?</h3>
            <p data-confirm-text class="mt-1.5 text-sm text-slate-500">
                Pastikan identitas dan password yang kamu masukkan sudah benar sebelum melanjutkan.
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
                    class="flex-1 rounded-xl bg-blue-600 py-3 text-sm font-semibold
                           text-white transition hover:bg-blue-700
                           focus-visible:outline-2
                           focus-visible:outline-offset-2 focus-visible:outline-blue-700
                           sm:py-2.5"
                >
                    Ya, Masuk
                </button>
            </div>
            <p class="mt-3 text-center text-[11px] text-slate-400">
                Gunakan <kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">←</kbd><kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">→</kbd> untuk memilih, <kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">Enter</kbd> untuk konfirmasi.
            </p>
        </div>
    </div>

    {{-- Popup Lupa Password (form pengajuan) --}}
    <div
        id="forgot-password-modal"
        data-open="false"
        aria-hidden="true"
        inert
        class="auth-modal fixed inset-0 z-70 flex items-center justify-center p-4"
    >
        <div data-modal-backdrop class="auth-modal-backdrop absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>

        <div
            data-modal-panel
            role="dialog"
            aria-modal="true"
            aria-labelledby="forgot-password-title"
            class="auth-modal-panel relative z-10 max-h-[85dvh] w-full max-w-sm overflow-y-auto overscroll-contain
                   rounded-2xl bg-white p-5 text-left shadow-2xl sm:p-6"
        >
            <button
                type="button"
                data-modal-close
                class="absolute right-3 top-3 rounded-full p-2 text-slate-400
                       transition hover:bg-slate-50 hover:text-slate-600
                       focus-visible:outline-2
                       focus-visible:outline-offset-2 focus-visible:outline-slate-400"
                aria-label="Tutup"
            >
                <x-lucide-x class="h-4.5 w-4.5" />
            </button>

            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                <x-lucide-key-round class="h-5 w-5" />
            </div>

            <h3 id="forgot-password-title" class="text-center text-base font-semibold text-slate-900">
                Atur Ulang Password
            </h3>
            <p class="mt-1.5 text-center text-sm leading-relaxed text-slate-500">
                Ajukan pengaturan ulang password. Admin akan memverifikasi, lalu tautan atur ulang
                dikirim ke email sekolah akunmu.
            </p>

            <form id="forgot-password-form" class="mt-5 space-y-4" novalidate>

                <div>
                    <label for="forgot-role" class="mb-2 block text-sm font-semibold text-slate-800">
                        Role
                    </label>

                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <x-lucide-user-round-cog class="h-4.5 w-4.5" />
                        </span>

                        <select
                            id="forgot-role"
                            name="role"
                            class="w-full appearance-none rounded-xl border border-slate-200 bg-white py-3 pl-10 pr-10
                                   text-base text-slate-900 outline-none transition
                                   focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10
                                   sm:text-sm"
                        >
                            @php
                                $initialForgotRole = in_array($selectedRole, \App\Support\LoginIdentifier::RESETTABLE_ROLES, true) ? $selectedRole : 'guru';
                            @endphp
                            @foreach ($roles as $roleKey => $roleMeta)
                                @if (in_array($roleKey, \App\Support\LoginIdentifier::RESETTABLE_ROLES, true))
                                    <option value="{{ $roleKey }}" @selected($roleKey === $initialForgotRole)>
                                        {{ $roleMeta['label'] }}
                                    </option>
                                @endif
                            @endforeach
                        </select>

                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400">
                            <x-lucide-chevron-down class="h-4 w-4" />
                        </span>
                    </div>
                </div>

                <div>
                    <label for="forgot-identifier" class="mb-2 block text-sm font-semibold text-slate-800">
                        <span data-forgot-label>{{ $roles[$initialForgotRole]['input_label'] ?? 'NIP' }}</span>
                    </label>

                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <x-lucide-id-card class="h-4.5 w-4.5" />
                        </span>

                        <input
                            type="text"
                            id="forgot-identifier"
                            name="{{ $roles[$initialForgotRole]['field'] ?? 'nip' }}"
                            placeholder="{{ $roles[$initialForgotRole]['placeholder'] ?? 'Masukkan NIP anda' }}"
                            required
                            autocomplete="off"
                            inputmode="numeric"
                            class="w-full rounded-xl border border-slate-200 bg-white py-3 pl-10 pr-4
                                   text-base text-slate-900 outline-none transition
                                   placeholder:text-slate-400
                                   focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10
                                   sm:text-sm"
                        >
                    </div>

                    <p data-forgot-error class="mt-2 hidden text-xs text-red-500"></p>
                </div>

                <button
                    type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl bg-blue-700 px-4 py-3.5
                           text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition
                           hover:bg-blue-800 focus-visible:outline-2
                           focus-visible:outline-offset-2 focus-visible:outline-blue-700
                           active:scale-[0.98]"
                >
                    <x-lucide-send class="h-4 w-4" />
                    Ajukan Reset
                </button>

            </form>

            <p class="mt-4 text-center text-xs text-slate-400">
                Password tidak pernah dikirim lewat email. Admin hanya mengirim tautan atur ulang.
            </p>
        </div>
    </div>

    {{-- Popup Lupa Password (pengajuan tercatat) --}}
    <div
        id="forgot-password-success-modal"
        data-open="false"
        aria-hidden="true"
        inert
        class="auth-modal fixed inset-0 z-70 flex items-center justify-center p-4"
    >
        <div data-modal-backdrop class="auth-modal-backdrop absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>

        <div
            data-modal-panel
            role="dialog"
            aria-modal="true"
            aria-labelledby="forgot-password-success-title"
            class="auth-modal-panel relative z-10 w-full max-w-sm rounded-2xl bg-white p-5 text-center shadow-2xl sm:p-6"
        >
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                <x-lucide-mail-check class="h-5 w-5" />
            </div>

            <h3 id="forgot-password-success-title" class="text-base font-semibold text-slate-900">
                Pengajuan Diterima
            </h3>
            <p data-forgot-success-text class="mt-1.5 text-sm leading-relaxed text-slate-500">
                Pengajuan sudah kami teruskan ke admin sekolah. Mohon menunggu persetujuan.
            </p>

            <button
                type="button"
                data-modal-close
                class="mt-5 w-full rounded-xl bg-blue-700 py-3 text-sm font-semibold text-white transition
                       hover:bg-blue-800 focus-visible:outline-2
                       focus-visible:outline-offset-2 focus-visible:outline-blue-700 sm:py-2.5"
            >
                Mengerti
            </button>
        </div>
    </div>

</div>

<style>
    .auth-modal {
        opacity: 0;
        pointer-events: none;
        transition: opacity .3s ease;
    }

    .auth-modal[data-open="true"] {
        opacity: 1;
        pointer-events: auto;
    }

    .auth-modal-backdrop {
        opacity: 0;
        transition: opacity .3s ease;
    }

    .auth-modal[data-open="true"] .auth-modal-backdrop {
        opacity: 1;
    }

    .auth-modal-panel {
        opacity: 0;
        transform: translateY(20px) scale(.97);
        transition: opacity .3s cubic-bezier(.22,1,.36,1), transform .3s cubic-bezier(.22,1,.36,1);
    }

    .auth-modal[data-open="true"] .auth-modal-panel {
        opacity: 1;
        transform: translateY(0) scale(1);
    }

    /* Sembunyikan scrollbar panel tanpa mematikan scroll; petunjuk halus di
       tepi atas/bawah hanya muncul selama masih ada isi yang bisa di-scroll. */
    .auth-modal-panel {
        scrollbar-width: none;      /* Firefox */
        -ms-overflow-style: none;   /* Edge lama */
        background-image:
            linear-gradient(to bottom, #fff 30%, rgba(255, 255, 255, 0)),
            linear-gradient(to top, #fff 30%, rgba(255, 255, 255, 0)),
            radial-gradient(farthest-side at 50% 0, rgba(15, 23, 42, .16), rgba(15, 23, 42, 0)),
            radial-gradient(farthest-side at 50% 100%, rgba(15, 23, 42, .16), rgba(15, 23, 42, 0));
        background-position: top, bottom, top, bottom;
        background-size: 100% 24px, 100% 24px, 100% 9px, 100% 9px;
        background-repeat: no-repeat;
        background-attachment: local, local, scroll, scroll;
    }

    .auth-modal-panel::-webkit-scrollbar {
        display: none;              /* Chrome, Safari, Edge */
    }

    .auth-modal [data-key-nav].auth-key-selected {
        outline: 2px solid #2563eb;
        outline-offset: 2px;
    }

    @media (prefers-reduced-motion: reduce) {
        .auth-modal, .auth-modal-backdrop, .auth-modal-panel {
            transition: none;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form            = document.getElementById('login-form');
        const roleEl          = document.getElementById('role');
        const identifierEl    = document.getElementById('identifier');
        const identifierLabel = form.querySelector('[data-identifier-label]');
        const roleErr         = form.querySelector('[data-role-error]');
        const identifierErr   = form.querySelector('[data-identifier-error]');
        const passwordEl      = document.getElementById('password');
        const passwordVisBtn  = document.getElementById('toggle-password');
        const passwordErr     = form.querySelector('[data-password-error]');
        const confirmModal    = document.getElementById('confirm-modal');
        const confirmText     = confirmModal.querySelector('[data-confirm-text]');
        const confirmYes      = confirmModal.querySelector('[data-confirm-yes]');
        const confirmNo       = confirmModal.querySelector('[data-confirm-no]');
        const iconMail        = form.querySelector('[data-icon-mail]');
        const iconIdCard      = form.querySelector('[data-icon-id-card]');
        const loading         = document.getElementById('site-loading');
        const loadingText     = loading.querySelector('[data-loading-text]');
        const submitBtn       = document.getElementById('submit-btn');

        const forgotBtn       = document.getElementById('forgot-password-btn');
        const forgotModal     = document.getElementById('forgot-password-modal');
        const forgotForm      = document.getElementById('forgot-password-form');
        const forgotRoleEl    = document.getElementById('forgot-role');
        const forgotIdEl      = document.getElementById('forgot-identifier');
        const forgotIdLabel   = forgotForm.querySelector('[data-forgot-label]');
        const forgotErr       = forgotForm.querySelector('[data-forgot-error]');
        const forgotSuccess   = document.getElementById('forgot-password-success-modal');
        const forgotSuccessText = forgotSuccess.querySelector('[data-forgot-success-text]');

        // Definisi role dikirim dari server (App\Support\LoginIdentifier) supaya
        // nama field, label, dan aturan input selalu sama dengan backend.
        const ROLES = @json($roles);
        const SERVER_ERRORS = {
            role: @json($roleError),
            identifier: @json($identifierError),
            password: @json($passwordError),
        };
        const FORGOT_ENDPOINT = @json(route('auth.buat-password'));

        const roleConfig = () => ROLES[roleEl.value] || ROLES.admin;

        const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        const isValidCode = (value) => /^[A-Za-z0-9\-\/]+$/.test(value);

        const setFieldError = (input, errorEl, message) => {
            if (message) {
                errorEl.textContent = message;
                errorEl.classList.remove('hidden');
                input.classList.add('border-red-400', 'ring-4', 'ring-red-500/10');
            } else {
                errorEl.textContent = '';
                errorEl.classList.add('hidden');
                input.classList.remove('border-red-400', 'ring-4', 'ring-red-500/10');
            }
        };

        /* ---------- Tampilkan / sembunyikan password ---------- */
        passwordVisBtn.addEventListener('click', () => {
            const show = passwordEl.type === 'password';
            passwordEl.type = show ? 'text' : 'password';
            passwordVisBtn.querySelector('.toggle-eye-show').classList.toggle('hidden', show);
            passwordVisBtn.querySelector('.toggle-eye-hide').classList.toggle('hidden', !show);
            passwordVisBtn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
        });

        /* ---------- Role -> field identitas ---------- */
        const applyRole = (role, { clearValue = true, focus = false } = {}) => {
            const cfg = ROLES[role] || ROLES.admin;

            roleEl.value = ROLES[role] ? role : 'admin';

            identifierLabel.textContent = cfg.input_label;
            identifierEl.name = cfg.field;
            identifierEl.type = cfg.field === 'email' ? 'email' : 'text';
            identifierEl.placeholder = cfg.placeholder;
            identifierEl.autocomplete = cfg.autocomplete;
            identifierEl.inputMode = cfg.input_mode;
            identifierEl.maxLength = cfg.maxlength;

            const isEmail = cfg.icon === 'mail';
            iconMail.classList.toggle('hidden', !isEmail);
            iconIdCard.classList.toggle('hidden', isEmail);

            if (clearValue) {
                identifierEl.value = '';
                setFieldError(identifierEl, identifierErr, null);
            }

            confirmText.textContent =
                `Pastikan ${cfg.input_label.toLowerCase()} dan password yang kamu masukkan sudah benar sebelum melanjutkan.`;

            applyForgotRole(role, { clearValue: false });

            if (focus) {
                identifierEl.focus();
            }
        };

        const applyForgotRole = (role, { clearValue = false } = {}) => {
            if (!forgotRoleEl) {
                return;
            }

            // Role yang boleh mengajukan reset hanya Guru dan Siswa.
            // Jika role sekarang adalah 'admin' atau tidak dikenal, default ke 'guru'.
            const targetRole = (role === 'siswa') ? 'siswa' : 'guru';

            if (forgotRoleEl.value !== targetRole) {
                forgotRoleEl.value = targetRole;
            }

            const cfg = ROLES[targetRole] || ROLES.guru;
            forgotIdEl.name = cfg.field;
            forgotIdEl.placeholder = cfg.placeholder;
            forgotIdEl.inputMode = cfg.input_mode;
            forgotIdEl.maxLength = cfg.maxlength;
            forgotIdLabel.textContent = cfg.input_label;

            if (clearValue) {
                forgotIdEl.value = '';
                setFieldError(forgotIdEl, forgotErr, null);
            }
        };

        roleEl.addEventListener('change', () => {
            applyRole(roleEl.value, { clearValue: true, focus: true });
        });

        // Terapkan definisi role awal (tanpa menghapus nilai old() dari server).
        applyRole(roleEl.value, { clearValue: false });

        /* ---------- Lockout / countdown ---------- */
        const lockoutBanner   = document.getElementById('lockout-banner');
        const lockoutTimeEl   = document.querySelector('[data-lockout-time]');
        const lockoutBar      = document.querySelector('[data-lockout-bar]');
        const initialLockData = document.getElementById('initial-lock');

        const lock = { active: false, total: 0, remaining: 0, timerId: null };

        const formatLockoutTime = (seconds) => {
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            const mm = String(m).padStart(2, '0');
            const ss = String(s).padStart(2, '0');
            return h > 0 ? `${h}:${mm}:${ss}` : `${mm}:${ss}`;
        };

        const renderLockout = () => {
            lockoutTimeEl.textContent = formatLockoutTime(Math.max(0, Math.ceil(lock.remaining)));
            const pct = lock.total > 0 ? Math.min(100, Math.max(0, ((lock.total - lock.remaining) / lock.total) * 100)) : 0;
            lockoutBar.style.width = `${pct}%`;
        };

        const setLocked = (remaining) => {
            lock.active = true;
            lock.total = Math.max(1, Math.round(Number(remaining) || 1));
            lock.remaining = lock.total;
            submitBtn.disabled = true;

            if (lock.timerId) clearInterval(lock.timerId);

            lockoutBanner.classList.remove('hidden');
            renderLockout();

            lock.timerId = setInterval(() => {
                lock.remaining -= 1;
                if (lock.remaining <= 0) {
                    clearInterval(lock.timerId);
                    lock.timerId = null;
                    lock.active = false;
                    submitBtn.disabled = false;
                    lockoutBanner.classList.add('hidden');
                    lockoutBar.style.width = '0%';
                } else {
                    renderLockout();
                }
            }, 1000);
        };

        const csrfToken = () => {
            const input = form.querySelector('input[name="_token"]');
            return input ? input.value : '';
        };

        // Jika server masih mencatat lockout (mis. usai refresh halaman), langsung aktifkan countdown.
        try {
            const initialLock = JSON.parse(initialLockData.dataset.lock || 'null');
            if (initialLock && initialLock.retry_after) {
                setLocked(initialLock.retry_after);
            }
        } catch (e) {
            // abaikan data lockout awal yang tidak valid
        }

        const openModal = (modal) => {
            modal.removeAttribute('inert');
            modal.setAttribute('aria-hidden', 'false');
            requestAnimationFrame(() => { modal.dataset.open = 'true'; });
        };

        const closeModal = (modal) => {
            modal.dataset.open = 'false';
            modal.setAttribute('aria-hidden', 'true');
            const finish = () => modal.setAttribute('inert', '');
            modal.addEventListener('transitionend', finish, { once: true });
            setTimeout(finish, 400);
        };

        /* ---------- Validasi form login ---------- */
        const validateIdentifier = () => {
            const cfg = roleConfig();
            const value = identifierEl.value.trim();

            if (!value) {
                setFieldError(identifierEl, identifierErr, `${cfg.input_label} wajib diisi.`);
                return false;
            }
            if (cfg.field === 'email' && !isValidEmail(value)) {
                setFieldError(identifierEl, identifierErr, 'Masukkan alamat email yang valid.');
                return false;
            }
            if (cfg.field !== 'email' && !isValidCode(value)) {
                setFieldError(
                    identifierEl,
                    identifierErr,
                    `${cfg.input_label} hanya boleh berisi huruf, angka, tanda hubung, atau garis miring.`
                );
                return false;
            }
            setFieldError(identifierEl, identifierErr, null);
            return true;
        };

        const validatePassword = () => {
            const value = passwordEl.value;

            if (!value) {
                setFieldError(passwordEl, passwordErr, 'Password wajib diisi.');
                return false;
            }
            if (value.length < 6) {
                setFieldError(passwordEl, passwordErr, 'Password minimal 6 karakter.');
                return false;
            }
            setFieldError(passwordEl, passwordErr, null);
            return true;
        };

        const validateLoginForm = () => {
            const identifierValid = validateIdentifier();
            const passwordValid = validatePassword();

            if (!identifierValid) {
                identifierEl.focus();
            } else if (!passwordValid) {
                passwordEl.focus();
            }

            return identifierValid && passwordValid;
        };

        identifierEl.addEventListener('input', () => {
            if (!identifierErr.classList.contains('hidden')) validateIdentifier();
        });
        passwordEl.addEventListener('input', () => {
            if (!passwordErr.classList.contains('hidden')) validatePassword();
        });

        // Tampilkan error dari server (validasi gagal / akun terkunci).
        const applyServerErrors = (errors = {}) => {
            const cfg = roleConfig();
            const pick = (key) => (Array.isArray(errors[key]) ? errors[key][0] : errors[key]);

            if (pick('role')) {
                setFieldError(roleEl, roleErr, pick('role'));
            }

            if (pick(cfg.field)) {
                setFieldError(identifierEl, identifierErr, pick(cfg.field));
            }

            if (pick('password')) {
                setFieldError(passwordEl, passwordErr, pick('password'));
            }
        };

        if (SERVER_ERRORS.role || SERVER_ERRORS.identifier || SERVER_ERRORS.password) {
            applyServerErrors({
                role: SERVER_ERRORS.role,
                [roleConfig().field]: SERVER_ERRORS.identifier,
                password: SERVER_ERRORS.password,
            });
        }

        const submitLogin = async () => {
            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new FormData(form),
                });

                const data = await res.json().catch(() => null);

                closeModal(loading);

                // Akun terkunci setelah percobaan gagal / masih dalam masa lockout ->
                // aktifkan countdown dan nonaktifkan tombol login.
                if (res.status === 429 || data?.data?.retry_after) {
                    applyServerErrors(data?.errors);
                    setLocked(data?.data?.retry_after || 30);
                    return;
                }

                if (!data || data.status !== 'success') {
                    applyServerErrors(data?.errors);
                    Swal.fire({
                        icon: 'error',
                        title: 'Login Gagal',
                        text: data?.message || 'Terjadi kesalahan. Silakan coba lagi.',
                        confirmButtonText: 'Coba Lagi',
                    });
                    return;
                }

                // Simpan token JWT per role agar logout satu role tidak
                // menghapus kredensial lokal role lain di browser yang sama.
                localStorage.setItem(`jwt_token_${data.data.role}`, data.data.token);
                localStorage.setItem('jwt_token', data.data.token);

                const isSetupPassword = data.data.must_change_password;

                await Swal.fire({
                    icon: 'success',
                    title: 'Login Berhasil!',
                    text: isSetupPassword
                        ? 'Silakan buat password baru untuk keamanan akunmu.'
                        : 'Selamat datang kembali di SASKANSA.',
showConfirmButton: false,
                    timer: 1800,
                    timerProgressBar: true,
                    allowOutsideClick: false,
                    });

                window.location.href = data.data.redirect;
            } catch (e) {
                closeModal(loading);

                Swal.fire({
                    icon: 'error',
                    title: 'Login Gagal',
                    text: 'Tidak dapat terhubung ke server. Silakan coba lagi.',
                    confirmButtonText: 'Coba Lagi',
                });
            }
        };

        /* ---------- Konfirmasi submit login ---------- */
        form.addEventListener('submit', (e) => {
            e.preventDefault();

            // Selama masa lockout (tombol nonaktif), Enter tidak boleh memicu login.
            if (lock.active) {
                return;
            }

            if (!validateLoginForm()) {
                return;
            }

            resetNavSelection();
            openModal(confirmModal);
        });

        /* ---------- Navigasi keyboard pada popup konfirmasi ---------- */
        const navButtons = [confirmNo, confirmYes];
        let navIndex = 1;

        const setNavIndex = (index, moveFocus = true) => {
            navIndex = index;
            navButtons.forEach((btn, i) => {
                btn.classList.toggle('auth-key-selected', i === index);
            });
            if (moveFocus) {
                navButtons[index].focus();
            }
        };

        const resetNavSelection = () => setNavIndex(1);

        document.addEventListener('keydown', (e) => {
            if (confirmModal.dataset.open !== 'true') {
                return;
            }

            if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) {
                e.preventDefault();
                setNavIndex(navIndex === 0 ? 1 : 0);
                return;
            }

            if (e.key === 'Enter') {
                e.preventDefault();
                navButtons[navIndex].click();
                return;
            }

            if (e.key === 'Escape') {
                e.preventDefault();
                closeModal(confirmModal);
                passwordEl.focus();
            }
        });

        confirmNo.addEventListener('click', () => {
            closeModal(confirmModal);
            passwordEl.focus();
        });

        confirmYes.addEventListener('click', () => {
            closeModal(confirmModal);

            submitBtn.disabled = true;
            loadingText.textContent = 'Memverifikasi kredensial...';
            openModal(loading);

            setTimeout(() => {
                submitLogin().finally(() => {
                    if (!lock.active) {
                        submitBtn.disabled = false;
                    }
                });
            }, 350);
        });

        /* ---------- Modal Lupa Password ---------- */
        forgotBtn.addEventListener('click', () => {
            const currentLoginRole = roleEl.value;
            const targetRole = (currentLoginRole === 'siswa') ? 'siswa' : 'guru';
            applyForgotRole(targetRole, { clearValue: true });
            openModal(forgotModal);
            setTimeout(() => forgotIdEl.focus(), 200);
        });

        // Tutup semua modal terkait lupa password (tombol X / "Mengerti")
        document.querySelectorAll('#forgot-password-modal [data-modal-close], #forgot-password-success-modal [data-modal-close]')
            .forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    closeModal(e.target.closest('.auth-modal'));
                });
            });

        [forgotModal, forgotSuccess].forEach((modal) => {
            modal.querySelector('[data-modal-backdrop]').addEventListener('click', () => {
                closeModal(modal);
            });
        });

        forgotRoleEl.addEventListener('change', () => {
            applyForgotRole(forgotRoleEl.value, { clearValue: true });
            forgotIdEl.focus();
        });

        forgotIdEl.addEventListener('input', () => {
            if (!forgotErr.classList.contains('hidden')) validateForgotForm();
        });

        const validateForgotForm = () => {
            const cfg = ROLES[forgotRoleEl.value] || ROLES.guru;
            const value = forgotIdEl.value.trim();

            if (!value) {
                setFieldError(forgotIdEl, forgotErr, `${cfg.input_label} wajib diisi.`);
                return false;
            }
            if (!isValidCode(value)) {
                setFieldError(
                    forgotIdEl,
                    forgotErr,
                    `${cfg.input_label} hanya boleh berisi huruf, angka, tanda hubung, atau garis miring.`
                );
                return false;
            }

            setFieldError(forgotIdEl, forgotErr, null);
            return true;
        };

        forgotForm.addEventListener('submit', (e) => {
            e.preventDefault();

            if (!validateForgotForm()) {
                return;
            }

            const role = forgotRoleEl.value;
            const field = ROLES[role]?.field || 'identifier';
            const payload = {
                role: role,
                [field]: forgotIdEl.value.trim(),
            };

            closeModal(forgotModal);

            loadingText.textContent = 'Mengirim pengajuan...';
            openModal(loading);

            setTimeout(() => {
                sendForgotPassword(payload).finally(() => {
                    closeModal(loading);
                });
            }, 350);
        });

        const sendForgotPassword = async (payload) => {
            const field = ROLES[payload.role]?.field;

            try {
                const res = await fetch(FORGOT_ENDPOINT, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                });

                const data = await res.json().catch(() => null);

                if (!res.ok || !data || data.status !== 'success') {
                    if (data?.errors) {
                        const errMsg = (field && data.errors[field]?.[0])
                            || Object.values(data.errors)[0]?.[0]
                            || data.message;
                        setFieldError(forgotIdEl, forgotErr, errMsg);
                    }

                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Mengirim',
                        text: data?.message || 'Terjadi kesalahan. Silakan coba lagi.',
                        confirmButtonText: 'Coba Lagi',
                    });
                    openModal(forgotModal);
                    return;
                }

                forgotSuccessText.textContent = data.message;
                openModal(forgotSuccess);
            } catch (e) {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Mengirim',
                    text: 'Tidak dapat terhubung ke server. Silakan coba lagi.',
                    confirmButtonText: 'Coba Lagi',
                });
                openModal(forgotModal);
            }
        };
    });
</script>

@if (session('logged_out'))
    <script>
        try {
            const role = @json(session('logged_out_role'));

            if (role) {
                const roleKey = 'jwt_token_' + role;
                const roleToken = localStorage.getItem(roleKey);

                if (roleToken !== null && roleToken === localStorage.getItem('jwt_token')) {
                    localStorage.removeItem('jwt_token');
                }

                localStorage.removeItem(roleKey);
                sessionStorage.removeItem(roleKey);
            } else {
                localStorage.removeItem('jwt_token');
                sessionStorage.removeItem('jwt_token');
            }
        } catch (e) {
        }
    </script>
@endif

@endsection
