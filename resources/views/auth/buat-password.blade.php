@extends('layouts.auth')

@section('title', 'Buat Password Baru - SASKANSA')

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
                    href="{{ route('login') }}"
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

                    {{-- ============ STATE: FORM ============ --}}
                    <div id="form-state" class="form-state">

                        {{-- Icon + Header --}}
                        <div class="mb-4 text-center">
                            <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center text-blue-600">
                                <x-lucide-shield-check class="h-8 w-8" />
                            </div>

                            <h1 class="text-lg font-bold tracking-tight text-slate-900">
                                Buat Password Baru
                            </h1>

                            <p class="mt-1 text-sm font-semibold text-blue-600">
                                Sistem Akademik Sekolah
                            </p>

                            <p class="mt-2 text-xs leading-relaxed text-slate-400">
                                Buat password baru untuk mengamankan akun kamu sebelum melanjutkan.
                            </p>
                        </div>

                        <form
                            id="password-form"
                            action="{{ route('password.setup.store') }}"
                            method="POST"
                            class="space-y-4"
                            novalidate
                        >

                            @csrf

                            <div>
                                <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-800">
                                    Password Baru
                                </label>

                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                        <x-lucide-lock class="h-4.5 w-4.5" />
                                    </span>

                                    {{-- text-base (16px) di HP agar Safari/iOS tidak zoom otomatis saat fokus --}}
                                    <input
                                        type="password"
                                        id="password"
                                        name="password"
                                        placeholder="Masukkan password baru"
                                        required
                                        autocomplete="new-password"
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

                                @error('password')
                                    <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                                <p data-password-error class="mt-2 hidden text-xs text-red-500">
                                    Password belum memenuhi semua kriteria di bawah.
                                </p>

                                <div id="password-checklist" class="mt-2 rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                                    <p class="mb-1.5 text-[10px] font-medium uppercase tracking-wide text-slate-400">
                                        Kriteria Password
                                    </p>
                                    <ul class="grid grid-cols-2 gap-x-3 gap-y-1">
                                        <li data-rule="length" class="flex items-center gap-1.5 text-xs text-slate-400 transition-colors">
                                            <span data-icon class="flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded-full border border-slate-300 text-transparent transition-colors">
                                                <x-lucide-check class="h-2.5 w-2.5" />
                                            </span>
                                            Min. 8 karakter
                                        </li>
                                        <li data-rule="upper" class="flex items-center gap-1.5 text-xs text-slate-400 transition-colors">
                                            <span data-icon class="flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded-full border border-slate-300 text-transparent transition-colors">
                                                <x-lucide-check class="h-2.5 w-2.5" />
                                            </span>
                                            Huruf besar
                                        </li>
                                        <li data-rule="number" class="flex items-center gap-1.5 text-xs text-slate-400 transition-colors">
                                            <span data-icon class="flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded-full border border-slate-300 text-transparent transition-colors">
                                                <x-lucide-check class="h-2.5 w-2.5" />
                                            </span>
                                            Ada angka
                                        </li>
                                        <li data-rule="match" class="flex items-center gap-1.5 text-xs text-slate-400 transition-colors">
                                            <span data-icon class="flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded-full border border-slate-300 text-transparent transition-colors">
                                                <x-lucide-check class="h-2.5 w-2.5" />
                                            </span>
                                            Konfirmasi cocok
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div>
                                <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-slate-800">
                                    Konfirmasi Password
                                </label>

                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                        <x-lucide-lock class="h-4.5 w-4.5" />
                                    </span>

                                    <input
                                        type="password"
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        placeholder="Ulangi password baru"
                                        required
                                        autocomplete="new-password"
                                        class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-10
                                               text-base text-slate-900 outline-none transition
                                               placeholder:text-slate-400
                                               focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10
                                               sm:text-sm"
                                    >
                                    <button
                                        type="button"
                                        id="toggle-password-confirmation"
                                        aria-label="Tampilkan konfirmasi password"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-primary-60"
                                    >
                                        <x-lucide-eye class="toggle-eye-show h-5 w-5" />
                                        <x-lucide-eye-off class="toggle-eye-hide hidden h-5 w-5" />
                                    </button>
                                </div>
                                <p data-confirm-error class="mt-2 hidden text-xs text-red-500">
                                    Konfirmasi password tidak cocok.
                                </p>
                            </div>

                            <button
                                type="submit"
                                id="submit-btn"
                                class="flex w-full items-center justify-center gap-2 rounded-2xl bg-blue-700 px-4 py-3
                                       text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition
                                       hover:bg-blue-800 focus-visible:outline-2
                                       focus-visible:outline-offset-2 focus-visible:outline-blue-700
                                       active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-70"
                            >
                                <x-lucide-shield-check class="h-4 w-4" />
                                Simpan Password
                            </button>

                        </form>

                        <p class="mt-3 text-center text-xs text-slate-400">
                            Pastikan password mudah kamu ingat dan tidak dibagikan kepada orang lain.
                        </p>

                    </div>

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
                            Satu langkah lagi - amankan password mu
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
                <x-lucide-shield-check class="h-5 w-5" />
            </div>

            <h3 class="text-base font-semibold text-slate-900">Yakin ingin menyimpan password ini?</h3>
            <p class="mt-1.5 text-sm text-slate-500">
                Pastikan kamu mengingat password baru ini dengan baik sebelum menyimpannya.
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
                    Ya, Simpan
                </button>
            </div>
            <p class="mt-3 text-center text-[11px] text-slate-400">
                Gunakan <kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">←</kbd><kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">→</kbd> untuk memilih, <kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">Enter</kbd> untuk konfirmasi.
            </p>
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
        const form         = document.getElementById('password-form');
        const passwordEl   = document.getElementById('password');
        const confirmEl    = document.getElementById('password_confirmation');
        const checklist    = document.getElementById('password-checklist');
        const passwordErr  = form.querySelector('[data-password-error]');
        const confirmErr   = form.querySelector('[data-confirm-error]');
        const confirmModal = document.getElementById('confirm-modal');
        const confirmYes   = confirmModal.querySelector('[data-confirm-yes]');
        const confirmNo    = confirmModal.querySelector('[data-confirm-no]');
        const loading      = document.getElementById('site-loading');
        const loadingText  = loading.querySelector('[data-loading-text]');
        const submitBtn    = document.getElementById('submit-btn');

        const csrfToken = () => {
            const input = form.querySelector('input[name="_token"]');
            return input ? input.value : '';
        };

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

        /* ---------- Tampilkan / sembunyikan password ---------- */
        [['toggle-password', passwordEl], ['toggle-password-confirmation', confirmEl]].forEach(([btnId, inputEl]) => {
            const btn = document.getElementById(btnId);
            btn.addEventListener('click', () => {
                const show = inputEl.type === 'password';
                inputEl.type = show ? 'text' : 'password';
                btn.querySelector('.toggle-eye-show').classList.toggle('hidden', show);
                btn.querySelector('.toggle-eye-hide').classList.toggle('hidden', !show);
                btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
            });
        });

        /* ---------- Checklist kriteria password ---------- */
        const rules = {
            length: (p) => p.length >= 8,
            upper: (p) => /[A-Z]/.test(p),
            number: (p) => /[0-9]/.test(p),
            match: (p, c) => p.length > 0 && p === c,
        };

        const updateChecklist = () => {
            const p = passwordEl.value;
            const c = confirmEl.value;

            Object.entries(rules).forEach(([key, test]) => {
                const li = checklist.querySelector(`[data-rule="${key}"]`);
                if (!li) return;

                const ok = key === 'match' ? test(p, c) : test(p);
                const icon = li.querySelector('[data-icon]');

                li.classList.toggle('text-emerald-600', ok);
                li.classList.toggle('text-slate-400', !ok);

                icon.classList.toggle('border-emerald-500', ok);
                icon.classList.toggle('bg-emerald-500', ok);
                icon.classList.toggle('text-white', ok);
                icon.classList.toggle('border-slate-300', !ok);
                icon.classList.toggle('text-transparent', !ok);
            });
        };

        const setFieldError = (input, errorEl, show) => {
            errorEl.classList.toggle('hidden', !show);
            input.classList.toggle('border-red-400', show);
            input.classList.toggle('ring-4', show);
            input.classList.toggle('ring-red-500/10', show);
        };

        passwordEl.addEventListener('input', () => {
            updateChecklist();

            const p = passwordEl.value;
            if (rules.length(p) && rules.upper(p) && rules.number(p)) {
                setFieldError(passwordEl, passwordErr, false);
            }
            if (rules.match(p, confirmEl.value)) {
                setFieldError(confirmEl, confirmErr, false);
            }
        });

        confirmEl.addEventListener('input', () => {
            updateChecklist();

            if (rules.match(passwordEl.value, confirmEl.value)) {
                setFieldError(confirmEl, confirmErr, false);
            }
        });

        /* ---------- Validasi form password ---------- */
        const validatePasswordForm = () => {
            const p = passwordEl.value;
            const c = confirmEl.value;

            const meetsRules = rules.length(p) && rules.upper(p) && rules.number(p);
            const matches = rules.match(p, c);

            setFieldError(passwordEl, passwordErr, !meetsRules);
            setFieldError(confirmEl, confirmErr, meetsRules && !matches);

            if (!meetsRules) {
                passwordEl.focus();
            } else if (!matches) {
                confirmEl.focus();
            }

            return meetsRules && matches;
        };

        /* ---------- Konfirmasi submit ---------- */
        form.addEventListener('submit', (e) => {
            e.preventDefault();

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            if (!validatePasswordForm()) {
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

        confirmNo.addEventListener('click', () => closeModal(confirmModal));

        confirmYes.addEventListener('click', () => {
            closeModal(confirmModal);

            submitBtn.disabled = true;
            loadingText.textContent = 'Menyimpan password...';
            openModal(loading);

            setTimeout(async () => {
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

                    if (!data || data.status !== 'success') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal Menyimpan Password',
                            text: data?.message || 'Terjadi kesalahan. Silakan coba lagi.',
confirmButtonText: 'Coba Lagi',
                            });
                        return;
                    }

                    await Swal.fire({
                        icon: 'success',
                        title: 'Password Berhasil Dibuat',
                        text: 'Password kamu berhasil diperbarui. Selamat datang di SASKANSA!',
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
                        title: 'Gagal Menyimpan Password',
                        text: 'Tidak dapat terhubung ke server. Silakan coba lagi.',
                        confirmButtonText: 'Coba Lagi',
                    });
                } finally {
                    submitBtn.disabled = false;
                }
            }, 350);
        });

        });
</script>

@endsection