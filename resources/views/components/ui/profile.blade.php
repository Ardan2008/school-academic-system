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
    $photoUrl    = method_exists($user, 'profilePhotoUrl') ? $user->profilePhotoUrl() : null;
    $initial     = strtoupper(mb_substr(trim((string) ($user->name ?? '?')), 0, 1) ?: '?');
    $photoUpdateUrl = Route::has('profile.photo.update') ? route('profile.photo.update', ['role' => $user->role]) : '';
    $photoDeleteUrl = Route::has('profile.photo.delete') ? route('profile.photo.delete', ['role' => $user->role]) : '';

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
                <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Foto Profil</p>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-full ring-2 ring-white shadow-md sm:h-24 sm:w-24" data-profile-avatar>
                            <img id="profile-photo-preview" src="{{ $photoUrl ?: 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=' }}" alt="" aria-hidden="true" onerror="window.saskansaAvatarFallback?.(this)" class="{{ $photoUrl ? '' : 'hidden' }} h-full w-full object-cover" data-profile-avatar-img>
                            <div id="profile-photo-initial" class="{{ $photoUrl ? 'hidden' : '' }} flex h-full w-full items-center justify-center bg-primary-60 text-2xl font-bold text-white" data-profile-avatar-initial>{{ $initial }}</div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap gap-2">
                                <label
                                    for="profile-photo-input"
                                    tabindex="0"
                                    role="button"
                                    class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-primary-60 bg-white px-3 py-2 text-xs font-semibold text-primary-60 transition hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60"
                                >
                                    <x-lucide-upload class="h-4 w-4" />
                                    Upload Foto
                                </label>
                                <input id="profile-photo-input" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="hidden">
                                <button type="button" id="profile-photo-delete" class="{{ $photoUrl ? '' : 'hidden' }} inline-flex items-center gap-2 rounded-lg border border-red-200 bg-white px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-50">
                                    <x-lucide-trash-2 class="h-4 w-4" />
                                    Hapus Foto
                                </button>
                            </div>
                            <p class="mt-2 text-[11px] leading-relaxed text-slate-500">Format JPG, JPEG, PNG, atau WEBP. Maksimal 2 MB. Gunakan crop lingkaran sebelum menyimpan.</p>
                            <p id="profile-photo-error" class="mt-2 hidden rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs text-red-600"></p>
                        </div>
                    </div>
                </div>

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

{{-- ============ Popup Crop Foto Profil ============ --}}
<div
    id="photo-crop-modal"
    data-open="false"
    aria-hidden="true"
    inert
    class="photo-crop-modal fixed inset-0 z-[11000] flex items-center justify-center p-4 lg:left-64"
>
    <div data-photo-crop-backdrop class="photo-crop-backdrop absolute inset-0 bg-slate-900/55 backdrop-blur-[2px]"></div>
    <div
        data-photo-crop-panel
        role="dialog"
        aria-modal="true"
        aria-labelledby="photo-crop-title"
        class="photo-crop-panel relative z-10 w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl"
    >
        <div class="flex items-center justify-between border-b border-blue-100 bg-blue-50 px-5 py-3">
            <div>
                <h3 id="photo-crop-title" class="text-base font-semibold text-primary-70">Atur Foto Profil</h3>
                <p class="mt-0.5 text-xs text-slate-500">Geser dan ubah ukuran foto di dalam lingkaran.</p>
            </div>
            <button type="button" data-photo-crop-close aria-label="Tutup" class="rounded-lg p-1.5 text-primary-60 transition hover:bg-blue-100">
                <x-lucide-x class="h-5 w-5" />
            </button>
        </div>

        <div class="p-5">
            <div class="relative mx-auto aspect-square w-64 max-w-full overflow-hidden rounded-xl bg-slate-900">
                <canvas id="photo-crop-canvas" class="h-full w-full touch-none"></canvas>
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <div data-crop-circle class="h-3/4 w-3/4 rounded-full border-2 border-white/90 shadow-[0_0_0_9999px_rgba(15,23,42,0.55)]"></div>
                </div>
            </div>

            <div class="mt-4">
                <label for="photo-crop-zoom" class="mb-1 flex items-center justify-between text-xs font-medium text-primary-70">
                    <span>Ukuran</span>
                    <span id="photo-crop-zoom-value">100%</span>
                </label>
                <input id="photo-crop-zoom" type="range" min="100" max="300" value="100" step="1" class="w-full accent-primary-60">
            </div>

            <div class="mt-5 flex gap-3">
                <button type="button" data-photo-crop-close class="inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-lg border border-blue-300 bg-white px-4 text-xs font-semibold text-primary-60 transition hover:bg-blue-50">
                    <x-lucide-x class="h-4 w-4" />
                    Batal
                </button>
                <button type="button" id="photo-crop-save" class="inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-lg bg-primary-60 px-4 text-xs font-semibold text-white shadow-md shadow-primary-60/25 transition hover:bg-primary-70">
                    <x-lucide-save class="h-4 w-4" />
                    Simpan Foto
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ============ Popup Konfirmasi Simpan Password ============ --}}
<div
    id="password-confirm-modal"
    data-open="false"
    aria-hidden="true"
    inert
    class="pwd-confirm fixed inset-0 z-[10000] flex items-center justify-center p-4 lg:left-64"
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
    .photo-crop-modal {
        opacity: 0;
        pointer-events: none;
        transition: opacity .3s ease;
    }

    .photo-crop-modal[data-open="true"] {
        opacity: 1;
        pointer-events: auto;
    }

    .photo-crop-backdrop {
        opacity: 0;
        transition: opacity .3s ease;
    }

    .photo-crop-modal[data-open="true"] .photo-crop-backdrop {
        opacity: 1;
    }

    .photo-crop-panel {
        opacity: 0;
        transform: translateY(20px) scale(.97);
        transition: opacity .3s cubic-bezier(.22,1,.36,1), transform .3s cubic-bezier(.22,1,.36,1);
    }

    .photo-crop-modal[data-open="true"] .photo-crop-panel {
        opacity: 1;
        transform: translateY(0) scale(1);
    }

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
        .photo-crop-modal, .photo-crop-backdrop, .photo-crop-panel,
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
    class="pwd-modal fixed inset-0 z-[9000] flex overflow-y-auto overscroll-contain bg-[#0F3D73]/60 p-3 sm:p-4 lg:left-64"
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
            const photoUpdateUrl = @json($photoUpdateUrl);
            const photoDeleteUrl = @json($photoDeleteUrl);

            const setBusy = (btn, busy) => {
                btn.disabled = busy;
                btn.classList.toggle('opacity-60', busy);
            };

            const errorText = (data) =>
                Object.values(data?.errors ?? {})[0]?.[0] ?? data?.message ?? 'Terjadi kesalahan.';

            /* ---------- Foto profil ---------- */
            const photoInput = $('profile-photo-input');
            const photoPreview = $('profile-photo-preview');
            const photoInitial = $('profile-photo-initial');
            const photoDelete = $('profile-photo-delete');
            const photoError = $('profile-photo-error');
            const cropModal = $('photo-crop-modal');
            const cropCanvas = $('photo-crop-canvas');
            const cropZoom = $('photo-crop-zoom');
            const cropZoomValue = $('photo-crop-zoom-value');
            const cropSave = $('photo-crop-save');
            const cropCircle = cropModal?.querySelector('[data-crop-circle]');
            const ALLOWED_PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
            const MAX_PHOTO_BYTES = 2 * 1024 * 1024;
            const cropState = { image: null, mime: 'image/jpeg', minScale: 1, scale: 1, x: 0, y: 0, dragging: false, lastX: 0, lastY: 0 };
            let cropInertTimer = null;
            let cropSaving = false;

            const showPhotoError = (message) => {
                if (!photoError) return;
                photoError.textContent = message;
                photoError.classList.remove('hidden');
            };

            const clearPhotoError = () => photoError?.classList.add('hidden');
            const reportPhotoError = (message, title = 'Foto Tidak Valid') => {
                showPhotoError(message);
                window.saskansaToast?.error?.(message, title);
            };

            const cropSize = () => cropCircle?.getBoundingClientRect().width || Math.min(cropCanvas?.clientWidth || 256, cropCanvas?.clientHeight || 256) * 0.75;

            const clearCropInertTimer = () => {
                if (cropInertTimer) {
                    clearTimeout(cropInertTimer);
                    cropInertTimer = null;
                }
            };

            const setCropBusy = (busy) => {
                cropSaving = busy;
                setBusy(cropSave, busy);
                if (photoDelete) setBusy(photoDelete, busy);
                if (photoInput) {
                    photoInput.disabled = busy;
                    photoInput.classList.toggle('pointer-events-none', busy);
                }
                const uploadLabel = document.querySelector('label[for="profile-photo-input"]');
                uploadLabel?.classList.toggle('pointer-events-none', busy);
                uploadLabel?.classList.toggle('opacity-60', busy);
            };

            const resetCropState = () => {
                cropState.image = null;
                cropState.dragging = false;
                cropState.x = 0;
                cropState.y = 0;
                cropState.scale = 1;
                cropState.minScale = 1;
            };

            const openCropModal = () => {
                if (!cropModal) return;
                clearCropInertTimer();
                cropModal.removeAttribute('inert');
                cropModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
                requestAnimationFrame(() => {
                    cropModal.dataset.open = 'true';
                    drawCrop();
                    cropSave?.focus();
                });
            };

            const closeCropModal = ({ keepError = false } = {}) => {
                if (!cropModal) return;
                cropModal.dataset.open = 'false';
                cropModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
                clearCropInertTimer();
                const finish = () => {
                    if (cropModal.dataset.open !== 'true') cropModal.setAttribute('inert', '');
                };
                cropModal.addEventListener('transitionend', finish, { once: true });
                cropInertTimer = setTimeout(finish, 400);
                if (!keepError) clearPhotoError();
                cropSave?.blur();
            };

            const clampOffsets = () => {
                if (!cropState.image || !cropCanvas) return;
                const size = cropSize();
                const iw = cropState.image.naturalWidth * cropState.scale;
                const ih = cropState.image.naturalHeight * cropState.scale;
                const maxX = Math.max(0, (iw - size) / 2);
                const maxY = Math.max(0, (ih - size) / 2);
                cropState.x = Math.min(maxX, Math.max(-maxX, cropState.x));
                cropState.y = Math.min(maxY, Math.max(-maxY, cropState.y));
            };

            const drawCrop = () => {
                if (!cropCanvas || !cropState.image) return;
                const ctx = cropCanvas.getContext('2d');
                if (!ctx) return;
                const ratio = window.devicePixelRatio || 1;
                const w = cropCanvas.clientWidth;
                const h = cropCanvas.clientHeight;
                if (cropCanvas.width !== Math.round(w * ratio) || cropCanvas.height !== Math.round(h * ratio)) {
                    cropCanvas.width = Math.round(w * ratio);
                    cropCanvas.height = Math.round(h * ratio);
                }
                ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                ctx.clearRect(0, 0, w, h);
                ctx.fillStyle = '#0f172a';
                ctx.fillRect(0, 0, w, h);
                const iw = cropState.image.naturalWidth * cropState.scale;
                const ih = cropState.image.naturalHeight * cropState.scale;
                ctx.drawImage(cropState.image, w / 2 + cropState.x - iw / 2, h / 2 + cropState.y - ih / 2, iw, ih);
            };

            const renderCroppedFile = async () => {
                if (!cropCanvas || !cropState.image) return null;
                const size = 512;
                const output = document.createElement('canvas');
                output.width = size;
                output.height = size;
                const ctx = output.getContext('2d');
                const mime = ALLOWED_PHOTO_TYPES.includes(cropState.mime) ? cropState.mime : 'image/jpeg';
                ctx.fillStyle = mime === 'image/jpeg' ? '#ffffff' : 'rgba(0,0,0,0)';
                ctx.fillRect(0, 0, size, size);
                const base = cropSize();
                const scaleFactor = size / base;
                const iw = cropState.image.naturalWidth * cropState.scale * scaleFactor;
                const ih = cropState.image.naturalHeight * cropState.scale * scaleFactor;
                const cx = size / 2 + cropState.x * scaleFactor;
                const cy = size / 2 + cropState.y * scaleFactor;
                ctx.drawImage(cropState.image, cx - iw / 2, cy - ih / 2, iw, ih);
                const blob = await new Promise((resolve) => output.toBlob(resolve, mime, 0.92));
                if (!blob) return null;
                const ext = mime === 'image/png' ? 'png' : (mime === 'image/webp' ? 'webp' : 'jpg');
                return new File([blob], `foto-profil.${ext}`, { type: mime });
            };

            const PLACEHOLDER_IMG = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
            window.saskansaAvatarFallback = (img) => {
                if (!(img instanceof HTMLImageElement)) return;
                img.onerror = null;
                img.src = PLACEHOLDER_IMG;
                img.classList.add('hidden');
                const wrap = img.closest('[data-profile-avatar]') || img.parentElement;
                wrap?.querySelector('[data-profile-avatar-initial]')?.classList.remove('hidden');
            };

            const syncAvatars = (url) => {
                const cacheBust = url ? `${url}${url.includes('?') ? '&' : '?'}v=${Date.now()}` : '';
                document.querySelectorAll('[data-profile-avatar-img]').forEach((img) => {
                    if (url) {
                        img.onerror = () => window.saskansaAvatarFallback?.(img);
                        img.src = cacheBust;
                        img.classList.remove('hidden');
                    } else {
                        img.onerror = null;
                        img.src = PLACEHOLDER_IMG;
                        img.classList.add('hidden');
                    }
                });
                document.querySelectorAll('[data-profile-avatar-initial]').forEach((el) => {
                    el.classList.toggle('hidden', !!url);
                });
            };

            photoInput?.addEventListener('change', () => {
                if (cropSaving) return;
                clearPhotoError();
                const file = photoInput.files?.[0];
                if (!file) return;
                const mime = (file.type || '').toLowerCase();
                const ext = (file.name.split('.').pop() || '').toLowerCase();
                const extAllowed = ['jpg', 'jpeg', 'png', 'webp'].includes(ext);
                if ((mime && !ALLOWED_PHOTO_TYPES.includes(mime)) || (!mime && !extAllowed)) {
                    reportPhotoError('Format foto harus JPG, JPEG, PNG, atau WEBP.');
                    photoInput.value = '';
                    return;
                }
                if (file.size > MAX_PHOTO_BYTES) {
                    reportPhotoError('Ukuran foto maksimal 2 MB.');
                    photoInput.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = () => {
                    const image = new Image();
                    image.onload = () => {
                        cropState.image = image;
                        cropState.mime = ALLOWED_PHOTO_TYPES.includes(mime) ? mime : (ext === 'png' ? 'image/png' : (ext === 'webp' ? 'image/webp' : 'image/jpeg'));
                        cropState.x = 0;
                        cropState.y = 0;
                        const base = cropSize();
                        cropState.minScale = Math.max(base / image.naturalWidth, base / image.naturalHeight);
                        cropState.scale = cropState.minScale;
                        const minPct = Math.max(1, Math.round(cropState.minScale * 100));
                        if (cropZoom) {
                            cropZoom.min = String(minPct);
                            cropZoom.max = String(Math.max(minPct + 1, minPct * 3));
                            cropZoom.value = String(minPct);
                        }
                        if (cropZoomValue) cropZoomValue.textContent = `${minPct}%`;
                        clampOffsets();
                        drawCrop();
                        openCropModal();
                    };
                    image.onerror = () => {
                        reportPhotoError('File foto tidak dapat dibaca. Silakan pilih foto lain.');
                        photoInput.value = '';
                    };
                    image.src = String(reader.result || '');
                };
                reader.onerror = () => reportPhotoError('File foto tidak dapat dibaca. Silakan pilih foto lain.');
                reader.readAsDataURL(file);
            });

            cropZoom?.addEventListener('input', () => {
                cropState.scale = Number(cropZoom.value) / 100;
                if (cropZoomValue) cropZoomValue.textContent = `${cropZoom.value}%`;
                clampOffsets();
                drawCrop();
            });

            cropCanvas?.addEventListener('pointerdown', (e) => {
                if (!e.isPrimary || e.button !== 0) return;
                cropState.dragging = true;
                cropState.lastX = e.clientX;
                cropState.lastY = e.clientY;
                cropCanvas.setPointerCapture?.(e.pointerId);
            });
            cropCanvas?.addEventListener('pointermove', (e) => {
                if (!cropState.dragging) return;
                cropState.x += e.clientX - cropState.lastX;
                cropState.y += e.clientY - cropState.lastY;
                cropState.lastX = e.clientX;
                cropState.lastY = e.clientY;
                clampOffsets();
                drawCrop();
            });
            const stopCropDrag = () => { cropState.dragging = false; };
            cropCanvas?.addEventListener('pointerup', stopCropDrag);
            cropCanvas?.addEventListener('pointercancel', stopCropDrag);
            cropCanvas?.addEventListener('lostpointercapture', stopCropDrag);
            window.addEventListener('pointerup', stopCropDrag);
            window.addEventListener('resize', () => { clampOffsets(); drawCrop(); });

            cropModal?.querySelectorAll('[data-photo-crop-close]').forEach((btn) => btn.addEventListener('click', () => {
                closeCropModal();
                photoInput.value = '';
                resetCropState();
            }));
            cropModal?.querySelector('[data-photo-crop-backdrop]')?.addEventListener('click', () => {
                closeCropModal();
                photoInput.value = '';
                resetCropState();
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && cropModal?.dataset.open === 'true' && !cropSaving) {
                    e.preventDefault();
                    closeCropModal();
                    photoInput.value = '';
                    resetCropState();
                }
            });

            cropSave?.addEventListener('click', async () => {
                clearPhotoError();
                if (!photoUpdateUrl || !cropState.image || cropSaving) return;
                setCropBusy(true);
                window.saskansaLoading?.show('Menyimpan foto profil...');
                try {
                    const file = await renderCroppedFile();
                    if (!file) throw new Error('Gagal memproses foto.');
                    const body = new FormData();
                    body.append('photo', file);
                    const res = await fetch(photoUpdateUrl, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body,
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || data.status !== 'success') {
                        reportPhotoError(errorText(data), 'Gagal Menyimpan Foto');
                        return;
                    }
                    const url = data.data?.photo_url ?? '';
                    syncAvatars(url);
                    if (photoDelete) photoDelete.classList.toggle('hidden', !url);
                    closeCropModal();
                    photoInput.value = '';
                    resetCropState();
                    window.saskansaToast?.success?.(data.message || 'Foto profil berhasil diperbarui.', 'Berhasil');
                } catch (err) {
                    reportPhotoError(err instanceof Error ? err.message : 'Terjadi kesalahan jaringan.', 'Gagal Menyimpan Foto');
                } finally {
                    window.saskansaLoading?.hide();
                    setCropBusy(false);
                }
            });

            photoDelete?.addEventListener('click', async () => {
                if (!photoDeleteUrl || cropSaving) return;
                const confirm = window.saskansaUi?.confirm
                    ? await window.saskansaUi.confirm({
                        title: 'Hapus Foto Profil',
                        text: 'Foto profil akan dihapus dan diganti dengan inisial. Lanjutkan?',
                    })
                    : window.confirm('Hapus foto profil?');
                if (!confirm) return;
                clearPhotoError();
                setCropBusy(true);
                window.saskansaLoading?.show('Menghapus foto profil...');
                try {
                    const res = await fetch(photoDeleteUrl, {
                        method: 'DELETE',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || data.status !== 'success') {
                        window.saskansaToast?.error?.(errorText(data), 'Gagal Menghapus Foto');
                        return;
                    }
                    syncAvatars(null);
                    photoDelete.classList.add('hidden');
                    window.saskansaToast?.success?.(data.message || 'Foto profil berhasil dihapus.', 'Berhasil');
                } catch (err) {
                    window.saskansaToast?.error?.('Terjadi kesalahan jaringan.', 'Gagal Menghapus Foto');
                } finally {
                    window.saskansaLoading?.hide();
                    setCropBusy(false);
                }
            });

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
                if (editActions) {
                    editActions.classList.toggle('hidden', !on);
                    editActions.classList.toggle('flex', on);
                }

                if (on) editable[0]?.focus();
            };

            if (!editAsModal) $('profile-edit-btn')?.addEventListener('click', () => setEditing(true));

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