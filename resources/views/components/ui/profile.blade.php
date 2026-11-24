@props([
    'user' => null,
    'fields' => [],
    'password' => '',
    'editModal' => false,
])

{{--
    Dipakai: <x-ui.profile :user="$user" :fields="$fields" />

    Isi $fields (satu item per kolom di kartu "Informasi Profil"):
      name    nama input yang dikirim ke server (kosongkan bila kolom tidak boleh diubah)
      label   label di atas kolom
      value   isi kolom
      locked  true = kolom terkunci (abu-abu + ikon gembok saat mode edit)
--}}

@php
    $user        = $user ?? auth()->user();
    $aktif       = (bool) ($user->is_active ?? true);
    $updateUrl   = Route::has('profile.update') ? route('profile.update') : '';
    $canEdit     = $updateUrl !== '';
    $editAsModal = $canEdit && (bool) $editModal;
    $password    = (string) $password;
    $canShowPw   = $password !== '';

    // text-base di HP mencegah auto-zoom iOS Safari; sm:text-sm untuk layar >= 640px
    $inputClass = 'w-full rounded-lg border px-3 py-2.5 text-base outline-none transition placeholder:text-slate-300 sm:py-2 sm:text-sm '
        . 'focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20 '
        . 'read-only:focus:border-slate-200 read-only:focus:ring-0';

    $modalInput = 'h-11 w-full rounded-lg border border-slate-300 bg-white pl-10 pr-11 text-base text-slate-800 outline-none transition '
        . 'placeholder:text-sm placeholder:text-slate-400 focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20 '
        . 'sm:h-10 sm:pr-10 sm:text-sm sm:placeholder:text-xs';
@endphp

<div id="profile-page">

    <div class="mb-4">
        <h2 id="profile-heading" class="text-xl font-bold tracking-tight text-[#0F3D73] sm:text-2xl">Profil</h2>
        <p class="mt-0.5 text-xs text-slate-400 sm:text-sm">Kelola informasi profil dan akun anda</p>
    </div>

    <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">

        {{-- ============ Informasi Profil ============ --}}
        <section class="overflow-hidden rounded-2xl bg-white shadow-md shadow-slate-200/60 ring-1 ring-slate-100">
            <header class="flex items-center gap-3 border-b border-blue-100 bg-blue-50 px-4 py-3 sm:px-5">
                <x-lucide-user-round class="h-5 w-5 shrink-0 text-primary-60" />
                <h3 class="text-base text-[#0F3D73]">Informasi Profil</h3>
            </header>

            <form id="profile-form" class="space-y-3.5 p-4 sm:p-5" autocomplete="off" novalidate>
                @foreach ($fields as $field)
                    @php
                        $locked = $field['locked'] ?? false;
                        $id     = 'profile-' . \Illuminate\Support\Str::slug($field['label']);
                    @endphp

                    <div>
                        <label for="{{ $id }}" class="mb-1 block text-xs text-slate-600">{{ $field['label'] }}</label>
                        <div class="relative">
                            <input
                                type="text"
                                id="{{ $id }}"
                                @unless ($locked) name="{{ $field['name'] }}" @endunless
                                value="{{ $field['value'] ?? '' }}"
                                placeholder="-"
                                readonly
                                {{ $locked ? 'data-locked' : 'data-editable' }}
                                class="{{ $inputClass }} border-slate-200 bg-white text-slate-800 {{ $locked ? 'pr-10' : '' }}"
                            >
                            @if ($locked)
                                <x-lucide-lock class="profile-lock pointer-events-none absolute right-3 top-1/2 hidden h-4 w-4 -translate-y-1/2 text-slate-400" />
                            @endif
                        </div>
                    </div>
                @endforeach

                <div>
                    <p class="mb-1 text-xs text-slate-600">Status Akun</p>
                    <span @class([
                        'inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium',
                        'border-blue-100 bg-blue-50 text-primary-60' => $aktif,
                        'border-slate-200 bg-slate-100 text-slate-500' => ! $aktif,
                    ])>
                        <span @class(['h-2 w-2 rounded-full', 'bg-primary-60' => $aktif, 'bg-slate-400' => ! $aktif])></span>
                        {{ $aktif ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>

                @if ($canEdit)
                    <div id="profile-view-actions" class="flex justify-end pt-4">
                        <button
                            type="button"
                            id="profile-edit-btn"
                            @if ($editAsModal) data-profile-edit-modal-trigger @endif
                            class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-primary-60 bg-white px-4 py-2.5 text-xs font-semibold text-primary-60 transition hover:bg-blue-50 sm:w-auto sm:py-2"
                        >
                            <x-lucide-pencil class="h-3.5 w-3.5" />
                            Edit Profil
                        </button>
                    </div>

                    @unless ($editAsModal)
                        <div id="profile-edit-actions" class="hidden items-center justify-end gap-3 pt-4">
                            <button
                                type="button"
                                id="profile-cancel-btn"
                                class="inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-lg border border-blue-300 bg-white px-4 text-xs font-medium text-primary-60 transition hover:bg-blue-50 sm:h-9 sm:flex-none"
                            >
                                <x-lucide-x class="h-4 w-4" />
                                Batal
                            </button>
                            <button
                                type="submit"
                                id="profile-save-btn"
                                class="inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-lg bg-primary-60 px-4 text-xs font-medium text-white shadow-md shadow-primary-60/25 transition hover:bg-primary-70 sm:h-9 sm:flex-none"
                            >
                                <x-lucide-save class="h-4 w-4" />
                                Simpan
                            </button>
                        </div>
                    @endunless
                @else
                    <div class="flex items-start gap-2.5 rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-xs text-primary-70">
                        <x-lucide-info class="mt-0.5 h-4 w-4 shrink-0 fill-primary-60 text-white" />
                        Perubahan data profil dilakukan oleh administrator sekolah.
                    </div>
                @endif
            </form>
        </section>

        {{-- ============ Keamanan Akun ============ --}}
        <section class="overflow-hidden rounded-2xl bg-white shadow-md shadow-slate-200/60 ring-1 ring-slate-100">
            <header class="flex items-center gap-3 border-b border-blue-100 bg-blue-50 px-4 py-3 sm:px-5">
                <x-lucide-shield-check class="h-5 w-5 shrink-0 text-primary-60" />
                <h3 class="text-base text-[#0F3D73]">Keamanan Akun</h3>
            </header>

            <div class="space-y-3 p-4 sm:p-5">
                <div>
                    <p class="mb-1.5 text-xs font-medium text-primary-70">Password</p>
                    <div class="relative">
                        <x-lucide-lock class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-primary-70" />
                        <input
                            type="password"
                            id="account-password"
                            value="{{ $password }}"
                            placeholder="{{ $canShowPw ? '' : '••••••••••••' }}"
                            readonly
                            tabindex="-1"
                            autocomplete="off"
                            aria-label="Password"
                            class="h-11 w-full rounded-lg border border-slate-300 bg-white pl-10 pr-11 text-base text-slate-800 outline-none sm:h-10 sm:pr-10 sm:text-sm"
                        >
                        <button
                            type="button"
                            data-toggle-account-password
                            aria-label="Tampilkan password"
                            @if (! $canShowPw) disabled @endif
                            class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-primary-60 transition hover:text-primary-70 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <x-lucide-eye class="icon-show h-4 w-4" />
                            <x-lucide-eye-off class="icon-hide hidden h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div class="flex items-start gap-2.5 rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-xs text-primary-70">
                    <x-lucide-info class="mt-0.5 h-4 w-4 shrink-0 fill-primary-60 text-white" />
                    Untuk mengubah password, silakan klik tombol di bawah ini.
                </div>

                <button
                    type="button"
                    id="open-password-modal"
                    class="flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-primary-60 bg-white text-xs font-semibold text-primary-70 transition hover:bg-blue-50 sm:h-9"
                >
                    <x-lucide-lock class="h-4 w-4" />
                    Ubah Password
                </button>
            </div>
        </section>
    </div>
</div>

{{-- ============ Popup Konfirmasi Simpan Password ============ --}}
<div
    id="password-confirm-modal"
    data-open="false"
    aria-hidden="true"
    inert
    class="pwd-confirm fixed inset-0 z-70 flex items-center justify-center p-4"
>
    <div data-modal-backdrop class="pwd-confirm-backdrop absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>

    <div
        data-modal-panel
        role="dialog"
        aria-modal="true"
        class="pwd-confirm-panel relative z-10 max-h-full w-full max-w-sm overflow-y-auto rounded-2xl bg-white p-5 text-center shadow-2xl sm:p-6"
    >
        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-600">
            <x-lucide-shield-check class="h-5 w-5" />
        </div>

        <h3 class="text-base font-semibold text-slate-900">Yakin ingin menyimpan password?</h3>
        <p class="mt-1.5 text-sm text-slate-500">
            Password baru akan menggantikan password lama akun Anda.
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
        {{-- Petunjuk keyboard hanya relevan di layar besar --}}
        <p class="mt-3 hidden text-center text-[11px] text-slate-400 sm:block">
            Gunakan <kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">←</kbd><kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">→</kbd> untuk memilih, <kbd class="rounded border border-slate-200 bg-slate-50 px-1 font-mono text-[10px] text-slate-500">Enter</kbd> untuk konfirmasi.
        </p>
    </div>
</div>

<style>
    .pwd-confirm {
        opacity: 0;
        pointer-events: none;
        transition: opacity .3s ease;
    }

    .pwd-confirm[data-open="true"] {
        opacity: 1;
        pointer-events: auto;
    }

    .pwd-confirm-backdrop {
        opacity: 0;
        transition: opacity .3s ease;
    }

    .pwd-confirm[data-open="true"] .pwd-confirm-backdrop {
        opacity: 1;
    }

    .pwd-confirm-panel {
        opacity: 0;
        transform: translateY(20px) scale(.97);
        transition: opacity .3s cubic-bezier(.22,1,.36,1), transform .3s cubic-bezier(.22,1,.36,1);
    }

    .pwd-confirm[data-open="true"] .pwd-confirm-panel {
        opacity: 1;
        transform: translateY(0) scale(1);
    }

    .pwd-confirm [data-key-nav].pwd-key-selected {
        outline: 2px solid #2563eb;
        outline-offset: 2px;
    }

    @media (prefers-reduced-motion: reduce) {
        .pwd-confirm, .pwd-confirm-backdrop, .pwd-confirm-panel,
        .pwd-modal, .pwd-modal-panel {
            transition: none;
        }
    }

    .pwd-modal {
        opacity: 0;
        pointer-events: none;
        transition: opacity .3s ease;
    }

    .pwd-modal[data-open="true"] {
        opacity: 1;
        pointer-events: auto;
    }

    .pwd-modal-panel {
        opacity: 0;
        transform: translateY(20px) scale(.97);
        transition: opacity .3s cubic-bezier(.22,1,.36,1), transform .3s cubic-bezier(.22,1,.36,1);
    }

    .pwd-modal[data-open="true"] .pwd-modal-panel {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
</style>

@if ($editAsModal)
    <div
        id="profile-edit-modal"
        data-open="false"
        aria-hidden="true"
        inert
        class="pwd-modal fixed inset-0 z-60 flex overflow-y-auto overscroll-contain bg-slate-900/50 p-3 backdrop-blur-sm sm:p-4 lg:left-64"
        role="dialog"
        aria-modal="true"
        aria-labelledby="profile-edit-modal-title"
    >
        <div data-profile-edit-backdrop class="absolute inset-0"></div>
        <div class="pwd-modal-panel relative z-10 m-auto flex max-h-[calc(100dvh-3rem)] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-[#FAFAFA] shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                <div class="min-w-0">
                    <h3 id="profile-edit-modal-title" class="text-base font-semibold text-slate-900">Edit Profil</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Perbarui informasi profil akun Anda.</p>
                </div>
                <button
                    type="button"
                    data-profile-edit-close
                    aria-label="Tutup"
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 active:scale-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60"
                >
                    <x-lucide-x class="h-4.5 w-4.5" />
                </button>
            </div>

            <form id="profile-edit-modal-form" class="flex min-h-0 flex-1 flex-col" autocomplete="off" novalidate>
                <div class="overflow-y-auto px-5 py-4">
                    <div class="space-y-3.5">
                        @foreach ($fields as $field)
                            @php
                                $locked = $field['locked'] ?? false;
                                $mid = 'profile-modal-' . \Illuminate\Support\Str::slug($field['label']);
                                $modalFieldClass = 'w-full rounded-xl border px-3 py-2.5 text-sm outline-none transition placeholder:text-slate-300 focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30 '
                                    . ($locked ? 'cursor-not-allowed border-slate-200 bg-slate-50 pr-10 text-slate-500' : 'border-slate-200 bg-white text-slate-800');
                            @endphp
                            <div>
                                <label for="{{ $mid }}" class="mb-1 block text-xs font-medium text-slate-600">{{ $field['label'] }}</label>
                                <div class="relative">
                                    <input
                                        type="text"
                                        id="{{ $mid }}"
                                        @unless ($locked) name="{{ $field['name'] }}" @endunless
                                        value="{{ $field['value'] ?? '' }}"
                                        placeholder="-"
                                        @if ($locked) readonly disabled tabindex="-1" aria-readonly="true" @endif
                                        class="{{ $modalFieldClass }}"
                                    >
                                    @if ($locked)
                                        <x-lucide-lock class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        <div>
                            <p class="mb-1 text-xs font-medium text-slate-600">Status Akun</p>
                            <span @class([
                                'inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium',
                                'border-blue-100 bg-blue-50 text-primary-60' => $aktif,
                                'border-slate-200 bg-slate-100 text-slate-500' => ! $aktif,
                            ])>
                                <span @class(['h-2 w-2 rounded-full', 'bg-primary-60' => $aktif, 'bg-slate-400' => ! $aktif])></span>
                                {{ $aktif ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-white px-5 py-3.5">
                    <button
                        type="button"
                        data-profile-edit-close
                        data-profile-edit-batal
                        class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        id="profile-edit-modal-save"
                        class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-primary-60 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-70 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:active:scale-100"
                    >
                        <x-lucide-save class="h-4 w-4" />
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
{{-- ============ Popup Ubah Password ============ --}}
{{-- lg:left-64 supaya sidebar tidak ikut gelap, sama seperti desain --}}
{{-- Container pakai flex biasa + panel m-auto agar tidak terpotong saat layar pendek --}}
<div
    id="password-modal"
    data-open="false"
    aria-hidden="true"
    inert
    class="pwd-modal fixed inset-0 z-60 flex overflow-y-auto overscroll-contain bg-[#0F3D73]/60 p-3 sm:p-4 lg:left-64"
    role="dialog"
    aria-modal="true"
    aria-labelledby="password-modal-title"
>
    <div class="pwd-modal-panel relative m-auto w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl sm:p-6">
        <button
            type="button"
            data-close-modal
            aria-label="Tutup"
            class="absolute right-3 top-3 rounded-lg p-1.5 text-primary-60 transition hover:bg-blue-50 sm:right-4 sm:top-4"
        >
            <x-lucide-x class="h-5 w-5" />
        </button>

        <h3 id="password-modal-title" class="pr-8 text-lg font-bold text-primary-70 sm:text-xl">Ubah Password</h3>
        <p class="mt-0.5 text-xs text-primary-60">Masukkan password Anda.</p>

        <form id="password-form" class="mt-4" autocomplete="off" novalidate>
            <div>
                <label for="modal-current-password" class="mb-1 block text-xs font-medium text-primary-70">Password saat ini</label>
                <div class="relative">
                    <x-lucide-lock class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-primary-70" />
                    <input type="password" id="modal-current-password" name="current_password" autocomplete="current-password" placeholder="Masukkan password saat ini" class="{{ $modalInput }}">
                    <button type="button" data-toggle-password data-target="modal-current-password" aria-label="Tampilkan password" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-primary-60">
                        <x-lucide-eye class="icon-show h-5 w-5" />
                        <x-lucide-eye-off class="icon-hide hidden h-5 w-5" />
                    </button>
                </div>
            </div>

            <div class="mt-3">
                <label for="modal-password" class="mb-1 block text-xs font-medium text-primary-70">Password Baru</label>
                <div class="relative">
                    <x-lucide-lock class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-primary-70" />
                    <input type="password" id="modal-password" name="password" autocomplete="new-password" placeholder="Masukkan password" class="{{ $modalInput }}">
                    <button type="button" data-toggle-password data-target="modal-password" aria-label="Tampilkan password" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-primary-60">
                        <x-lucide-eye class="icon-show h-5 w-5" />
                        <x-lucide-eye-off class="icon-hide hidden h-5 w-5" />
                    </button>
                </div>
            </div>

            <div class="mt-3">
                <label for="modal-password-confirmation" class="mb-1 block text-xs font-medium text-primary-70">Konfirmasi Password</label>
                <div class="relative">
                    <x-lucide-lock class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-primary-70" />
                    <input type="password" id="modal-password-confirmation" name="password_confirmation" autocomplete="new-password" placeholder="Masukkan kembali password" class="{{ $modalInput }}">
                    <button type="button" data-toggle-password data-target="modal-password-confirmation" aria-label="Tampilkan password" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-primary-60">
                        <x-lucide-eye class="icon-show h-5 w-5" />
                        <x-lucide-eye-off class="icon-hide hidden h-5 w-5" />
                    </button>
                </div>
            </div>

            <div class="mt-3 flex items-start gap-2.5 rounded-lg bg-blue-50 px-3 py-2 text-xs text-primary-70">
                <x-lucide-info class="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary-60" />
                Password baru minimal 8 karakter dan harus mengandung huruf besar dan angka.
            </div>

            <div class="mt-4 flex items-center justify-end gap-3">
                <button
                    type="button"
                    data-close-modal
                    class="inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-lg border border-blue-300 bg-white px-4 text-xs font-medium text-primary-60 transition hover:bg-blue-50 sm:h-9 sm:flex-none"
                >
                    <x-lucide-x class="h-4 w-4" />
                    Batal
                </button>
                <button
                    type="submit"
                    id="password-save-btn"
                    class="inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-lg bg-primary-60 px-4 text-xs font-medium text-white shadow-md shadow-primary-60/25 transition hover:bg-primary-70 sm:h-9 sm:flex-none"
                >
                    <x-lucide-save class="h-4 w-4" />
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const $ = (id) => document.getElementById(id);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const updateUrl = @json($updateUrl);
            const passwordUrl = @json(route('profile.password', ['role' => $user->role]));

            const setBusy = (btn, busy) => {
                btn.disabled = busy;
                btn.classList.toggle('opacity-60', busy);
            };

            const errorText = (data) =>
                Object.values(data?.errors ?? {})[0]?.[0] ?? data?.message ?? 'Terjadi kesalahan.';

            /* ---------- Mode lihat / edit ---------- */
            const form = $('profile-form');
            const heading = $('profile-heading');
            const viewActions = $('profile-view-actions');
            const editActions = $('profile-edit-actions');
            const editable = form?.querySelectorAll('[data-editable]') ?? [];
            const locked = form?.querySelectorAll('[data-locked]') ?? [];
            const editAsModal = {{ $editAsModal ? 'true' : 'false' }};
            const canEdit = updateUrl !== '' && form && viewActions && (!editAsModal ? !!editActions : true);

            const lockedOn = ['bg-slate-100', 'border-transparent', 'text-slate-500', 'cursor-not-allowed'];
            const lockedOff = ['bg-white', 'border-slate-200', 'text-slate-800'];

            const setEditing = (on) => {
                if (!canEdit) return;

                heading.textContent = on ? 'Edit Profil' : 'Profil';

                editable.forEach((el) => { el.readOnly = !on; });

                locked.forEach((el) => {
                    el.classList.remove(...(on ? lockedOff : lockedOn));
                    el.classList.add(...(on ? lockedOn : lockedOff));
                    el.parentElement.querySelector('.profile-lock')?.classList.toggle('hidden', !on);
                });

                viewActions.classList.toggle('hidden', on);
                editActions.classList.toggle('hidden', !on);
                editActions.classList.toggle('flex', on);

                if (on) editable[0]?.focus();
            };

            $('profile-edit-btn')?.addEventListener('click', () => setEditing(true));

            $('profile-cancel-btn')?.addEventListener('click', () => {
                form?.reset();
                setEditing(false);
            });

            if (!editAsModal) form?.addEventListener('submit', async (e) => {
                e.preventDefault();

                if (!canEdit) return;

                const btn = $('profile-save-btn');
                setBusy(btn, true);

                try {
                    const body = new FormData(form);
                    body.append('_method', 'PUT');

                    const res = await fetch(updateUrl, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body,
                    });
                    const data = await res.json().catch(() => ({}));

                    if (res.ok && data.status === 'success') {
                        editable.forEach((el) => { el.defaultValue = el.value; });
                        setEditing(false);
                        Swal.fire({ icon: 'success', title: 'Berhasil', text: data.message || 'Profil berhasil diperbarui.' });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: errorText(data) });
                    }
                } catch (err) {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: 'Terjadi kesalahan jaringan.' });
                } finally {
                    setBusy(btn, false);
                }
            });

            /* ---------- Edit profil (modal, Role Guru) ---------- */
            if (canEdit && editAsModal) {
                const editModal = $('profile-edit-modal');
                const editModalForm = $('profile-edit-modal-form');
                const editSave = $('profile-edit-modal-save');

                const openEditModal = () => {
                    if (!editModal) return;
                    editModal.removeAttribute('inert');
                    editModal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('overflow-hidden');
                    requestAnimationFrame(() => { editModal.dataset.open = 'true'; });
                    (editModal.querySelector('input:not([disabled]):not([type="hidden"]), select, textarea'))?.focus();
                };

                const closeEditModal = () => {
                    if (!editModal) return;
                    editModal.dataset.open = 'false';
                    editModal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('overflow-hidden');
                    const finish = () => editModal.setAttribute('inert', '');
                    editModal.addEventListener('transitionend', finish, { once: true });
                    setTimeout(finish, 400);
                };

                document.querySelector('[data-profile-edit-modal-trigger]')?.addEventListener('click', openEditModal);
                editModal?.querySelectorAll('[data-profile-edit-close]').forEach((btn) => btn.addEventListener('click', closeEditModal));
                editModal?.querySelector('[data-profile-edit-backdrop]')?.addEventListener('click', closeEditModal);
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && editModal?.dataset.open === 'true' && pwdConfirm?.dataset.open !== 'true') {
                        closeEditModal();
                    }
                });

                editModalForm?.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    if (!editSave) return;
                    setBusy(editSave, true);

                    try {
                        const body = new FormData(editModalForm);
                        body.append('_method', 'PUT');

                        const res = await fetch(updateUrl, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                            body,
                        });
                        const data = await res.json().catch(() => ({}));

                        if (res.ok && data.status === 'success') {
                            closeEditModal();
                            await Swal.fire({ icon: 'success', title: 'Berhasil', text: data.message || 'Profil berhasil diperbarui.' });
                            window.location.reload();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Gagal', text: errorText(data) });
                        }
                    } catch (err) {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: 'Terjadi kesalahan jaringan.' });
                    } finally {
                        setBusy(editSave, false);
                    }
                });
            }
            /* ---------- Popup ubah password ---------- */
            const modal = $('password-modal');
            const pwForm = $('password-form');
            const toggles = modal.querySelectorAll('[data-toggle-password]');

            const openModal = () => {
                modal.removeAttribute('inert');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden'); // kunci scroll halaman di belakang popup
                requestAnimationFrame(() => { modal.dataset.open = 'true'; });
                $('modal-current-password').focus();
            };

            const closeModal = () => {
                modal.dataset.open = 'false';
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
                const finish = () => modal.setAttribute('inert', '');
                modal.addEventListener('transitionend', finish, { once: true });
                setTimeout(finish, 400);
                toggles.forEach((btn) => {
                    if ($(btn.dataset.target).type === 'text') btn.click();
                });
                pwForm.reset();
            };

            $('open-password-modal').addEventListener('click', openModal);
            modal.querySelectorAll('[data-close-modal]').forEach((btn) => btn.addEventListener('click', closeModal));
            modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
            document.addEventListener('keydown', (e) => {
                // Abaikan Escape bila popup konfirmasi sedang terbuka (biar hanya konfirmasi yang tertutup)
                if (e.key === 'Escape' && modal.dataset.open === 'true' && pwdConfirm.dataset.open !== 'true') {
                    closeModal();
                }
            });

            toggles.forEach((btn) => {
                btn.addEventListener('click', () => {
                    const input = $(btn.dataset.target);
                    const show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    btn.querySelector('.icon-show').classList.toggle('hidden', show);
                    btn.querySelector('.icon-hide').classList.toggle('hidden', !show);
                    btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
                });
            });

            const accountToggle = document.querySelector('[data-toggle-account-password]');
            accountToggle?.addEventListener('click', () => {
                const input = $('account-password');
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                accountToggle.querySelector('.icon-show').classList.toggle('hidden', show);
                accountToggle.querySelector('.icon-hide').classList.toggle('hidden', !show);
                accountToggle.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
            });

            pwForm.addEventListener('submit', (e) => {
                e.preventDefault();

                const current = $('modal-current-password').value;

                if (!current) {
                    Swal.fire({ icon: 'warning', title: 'Password saat ini wajib diisi', text: 'Masukkan password saat ini terlebih dahulu.' });
                    return;
                }

                const pw = $('modal-password').value;
                const confirmation = $('modal-password-confirmation').value;

                if (pw.length < 8) {
                    Swal.fire({ icon: 'warning', title: 'Password terlalu pendek', text: 'Password minimal 8 karakter.' });
                    return;
                }
                if (pw !== confirmation) {
                    Swal.fire({ icon: 'warning', title: 'Konfirmasi tidak cocok', text: 'Konfirmasi password harus sama dengan password.' });
                    return;
                }

                openPwdConfirm();
            });

            /* ---------- Popup konfirmasi simpan password ---------- */
            const pwdConfirm = $('password-confirm-modal');
            const pwdConfirmYes = pwdConfirm.querySelector('[data-confirm-yes]');
            const pwdConfirmNo = pwdConfirm.querySelector('[data-confirm-no]');
            const pwdNavBtns = [pwdConfirmNo, pwdConfirmYes];
            let pwdNavIndex = 1;

            const setPwdNavIndex = (index, moveFocus = true) => {
                pwdNavIndex = index;
                pwdNavBtns.forEach((btn, i) => {
                    btn.classList.toggle('pwd-key-selected', i === index);
                });
                if (moveFocus) pwdNavBtns[index].focus();
            };

            const openPwdConfirm = () => {
                pwdConfirm.removeAttribute('inert');
                pwdConfirm.setAttribute('aria-hidden', 'false');
                requestAnimationFrame(() => { pwdConfirm.dataset.open = 'true'; });
                setPwdNavIndex(1);
            };

            const closePwdConfirm = () => {
                pwdConfirm.dataset.open = 'false';
                pwdConfirm.setAttribute('aria-hidden', 'true');
                const finish = () => pwdConfirm.setAttribute('inert', '');
                pwdConfirm.addEventListener('transitionend', finish, { once: true });
                setTimeout(finish, 400);
            };

            document.addEventListener('keydown', (e) => {
                if (pwdConfirm.dataset.open !== 'true') return;

                if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) {
                    e.preventDefault();
                    setPwdNavIndex(pwdNavIndex === 0 ? 1 : 0);
                    return;
                }

                if (e.key === 'Enter') {
                    e.preventDefault();
                    pwdNavBtns[pwdNavIndex].click();
                    return;
                }

                if (e.key === 'Escape') {
                    e.preventDefault();
                    closePwdConfirm();
                }
            });

            const showLoading = (text) => {
                const loading = document.getElementById('site-loading');
                if (!loading) return null;
                const lt = loading.querySelector('[data-loading-text]');
                if (lt) lt.textContent = text;
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

            pwdConfirmYes.addEventListener('click', () => {
                closePwdConfirm();

                const body = new FormData(pwForm);

                closeModal();

                const loading = showLoading('Menyimpan password...');

                setTimeout(async () => {
                    try {
                        const res = await fetch(passwordUrl, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                            body,
                        });
                        const data = await res.json().catch(() => ({}));

                        hideLoading(loading);

                        if (res.ok && data.status === 'success') {
                            pwForm.reset();
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil',
                                text: data.message || 'Password berhasil diperbarui.',
                                showConfirmButton: false,
                                timer: 1800,
                                timerProgressBar: true,
                                allowOutsideClick: false,
                            });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Gagal', text: errorText(data) });
                        }
                    } catch (err) {
                        hideLoading(loading);
                        Swal.fire({ icon: 'error', title: 'Gagal', text: 'Terjadi kesalahan jaringan.' });
                    }
                }, 800);
            });

            pwdConfirmNo.addEventListener('click', closePwdConfirm);
            pwdConfirm.querySelector('[data-modal-backdrop]').addEventListener('click', closePwdConfirm);
        });
    </script>
@endpush