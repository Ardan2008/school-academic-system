@extends('layouts.auth')

@section('title', 'Atur Ulang Password - SASKANSA')

@section('content')

<div class="h-dvh w-full overflow-hidden bg-slate-50 text-slate-900">

    <div class="flex h-full items-center justify-center px-4 py-6 sm:px-6">

        <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-xl ring-1 ring-slate-100 sm:p-8">

            <a
                href="{{ route('login') }}"
                class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-400 transition hover:text-blue-600"
            >
                <x-lucide-arrow-left class="h-4 w-4" />
                Kembali ke halaman login
            </a>

            @if ($tokenRecord)
                <div class="mt-4 text-center">
                    <div class="mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                        <x-lucide-key-round class="h-6 w-6" />
                    </div>

                    <h1 class="text-lg font-bold tracking-tight text-slate-900">Buat Password Baru</h1>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-500">
                        Tautan ini berlaku sampai
                        <span class="font-semibold text-slate-700">{{ \App\Support\Waktu::lengkap($tokenRecord->expires_at) }}</span>
                        dan hanya bisa dipakai satu kali.
                    </p>
                </div>

                <form
                    id="reset-form"
                    action="{{ route('password.reset.store', ['token' => $token]) }}"
                    method="POST"
                    class="mt-6 space-y-4"
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

                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Minimal 8 karakter"
                                required
                                autofocus
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

                        <p data-password-error class="mt-2 hidden text-xs text-red-500"></p>
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
                                class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-4
                                       text-base text-slate-900 outline-none transition
                                       placeholder:text-slate-400
                                       focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10
                                       sm:text-sm"
                            >
                        </div>

                        <p data-confirm-error class="mt-2 hidden text-xs text-red-500"></p>
                    </div>

                    <ul class="space-y-1 rounded-xl bg-slate-50 p-3.5 text-xs text-slate-500">
                        <li data-rule="length">Minimal {{ \App\Support\PasswordPolicy::MIN_LENGTH }} karakter.</li>
                        <li data-rule="uppercase">Mengandung huruf besar (A-Z).</li>
                        <li data-rule="number">Mengandung angka (0-9).</li>
                    </ul>

                    <button
                        type="submit"
                        id="submit-btn"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-blue-700 px-4 py-3
                               text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition
                               hover:bg-blue-800 focus-visible:outline-2
                               focus-visible:outline-offset-2 focus-visible:outline-blue-700
                               active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-70"
                    >
                        <x-lucide-badge-check class="h-4 w-4" />
                        Simpan Password Baru
                    </button>
                </form>
            @else
                <div class="mt-4 text-center">
                    <div class="mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-red-50 text-red-600">
                        <x-lucide-clock class="h-6 w-6" />
                    </div>

                    <h1 class="text-lg font-bold tracking-tight text-slate-900">Tautan Tidak Berlaku</h1>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-500">
                        Tautan atur ulang password ini sudah dipakai, kedaluwarsa, atau tidak dikenal.
                        Silakan ajukan pengajuan baru dari halaman login.
                    </p>

                    <a
                        href="{{ route('login') }}"
                        class="mt-6 flex w-full items-center justify-center gap-2 rounded-2xl bg-blue-700 px-4 py-3
                               text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition
                               hover:bg-blue-800 focus-visible:outline-2
                               focus-visible:outline-offset-2 focus-visible:outline-blue-700
                               active:scale-[0.98]"
                    >
                        <x-lucide-log-in class="h-4 w-4" />
                        Kembali ke Login
                    </a>
                </div>
            @endif

        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('reset-form');

        if (!form) {
            return;
        }

        const passwordEl = document.getElementById('password');
        const confirmEl  = document.getElementById('password_confirmation');
        const passwordErr = form.querySelector('[data-password-error]');
        const confirmErr  = form.querySelector('[data-confirm-error]');
        const visBtn      = document.getElementById('toggle-password');
        const submitBtn   = document.getElementById('submit-btn');
        const csrfToken   = form.querySelector('input[name="_token"]')?.value ?? '';

        const minLength = {{ \App\Support\PasswordPolicy::MIN_LENGTH }};

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

        visBtn.addEventListener('click', () => {
            const show = passwordEl.type === 'password';
            const target = show ? 'text' : 'password';
            passwordEl.type = target;
            confirmEl.type = target;
            visBtn.querySelector('.toggle-eye-show').classList.toggle('hidden', show);
            visBtn.querySelector('.toggle-eye-hide').classList.toggle('hidden', !show);
            visBtn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
        });

        const refreshRules = () => {
            const value = passwordEl.value;
            form.querySelector('[data-rule="length"]').classList.toggle('text-emerald-600', value.length >= minLength);
            form.querySelector('[data-rule="uppercase"]').classList.toggle('text-emerald-600', /[A-Z]/.test(value));
            form.querySelector('[data-rule="number"]').classList.toggle('text-emerald-600', /[0-9]/.test(value));
        };

        const validate = () => {
            const value = passwordEl.value;
            let firstInvalid = null;

            if (!value) {
                setFieldError(passwordEl, passwordErr, 'Password baru wajib diisi.');
                firstInvalid = passwordEl;
            } else if (value.length < minLength) {
                setFieldError(passwordEl, passwordErr, `Password minimal terdiri dari ${minLength} karakter.`);
                firstInvalid = passwordEl;
            } else if (!/[A-Z]/.test(value) || !/[0-9]/.test(value)) {
                setFieldError(passwordEl, passwordErr, 'Password harus mengandung huruf besar dan angka.');
                firstInvalid = passwordEl;
            } else {
                setFieldError(passwordEl, passwordErr, null);
            }

            if (!confirmEl.value) {
                setFieldError(confirmEl, confirmErr, 'Konfirmasi password wajib diisi.');
                firstInvalid = firstInvalid ?? confirmEl;
            } else if (confirmEl.value !== value) {
                setFieldError(confirmEl, confirmErr, 'Konfirmasi password tidak cocok.');
                firstInvalid = firstInvalid ?? confirmEl;
            } else {
                setFieldError(confirmEl, confirmErr, null);
            }

            return firstInvalid === null;
        };

        passwordEl.addEventListener('input', () => {
            refreshRules();
            if (!passwordErr.classList.contains('hidden')) validate();
        });
        confirmEl.addEventListener('input', () => {
            if (!confirmErr.classList.contains('hidden')) validate();
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (!validate()) {
                return;
            }

            submitBtn.disabled = true;
            const originalHtml = submitBtn.innerHTML;
            submitBtn.innerHTML = 'Menyimpan...';

            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        password: passwordEl.value,
                        password_confirmation: confirmEl.value,
                    }),
                });

                const data = await res.json().catch(() => null);

                if (!res.ok || !data || data.status !== 'success') {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;

                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Menyimpan',
                        text: data?.message || 'Terjadi kesalahan. Silakan coba lagi.',
                        confirmButtonText: 'Coba Lagi',
                    });

                    return;
                }

                await Swal.fire({
                    icon: 'success',
                    title: 'Password Diganti',
                    text: data.message,
                    showConfirmButton: false,
                    timer: 1800,
                    timerProgressBar: true,
                    allowOutsideClick: false,
                });

                window.location.href = data.data.redirect;
            } catch (e) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;

                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Menyimpan',
                    text: 'Tidak dapat terhubung ke server. Silakan coba lagi.',
                    confirmButtonText: 'Coba Lagi',
                });
            }
        });
    });
</script>

@endsection
