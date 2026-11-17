@extends('layouts.dashboard')

@section('title', 'Slip Password')
@section('subtitle', 'Dashboard Admin')

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h2 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Slip Password Akun</h2>
            <p class="mt-1 text-sm text-slate-500">Cara cepat membagikan kredensial login awal kepada guru dan siswa.</p>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <button type="button" id="slip-export" data-state="idle"
               data-export-url="{{ route('dashboard.admin.manajemen-akun.slips.export', request()->only(['role', 'jurusan', 'tingkat', 'kelas', 'search'])) }}"
               aria-live="polite"
               title="Unduh slip password yang sedang ditampilkan"
               class="no-print ekspor relative inline-flex h-10 items-center justify-center gap-2 overflow-hidden whitespace-nowrap rounded-lg border border-blue-300 bg-white px-4 text-xs font-medium text-primary-60 transition hover:bg-blue-50 sm:h-9 sm:text-sm">
                <span data-ekspor-kilau class="ekspor-kilau" aria-hidden="true"></span>
                <x-lucide-download data-ekspor-ikon="idle" class="ekspor-ikon h-4 w-4" />
                <svg data-ekspor-ikon="muat" class="ekspor-ikon h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                    <path d="M21 12a9 9 0 1 1-6.219-8.56" />
                </svg>
                <x-lucide-check data-ekspor-ikon="sukses" class="ekspor-ikon h-4 w-4" />
                <span data-ekspor-label>Export Excel</span>
            </button>
            <button type="button" onclick="window.print()"
                class="no-print inline-flex items-center gap-2 rounded-xl bg-primary-60 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-primary-60/25 transition hover:bg-primary-70">
                <x-lucide-printer class="h-4 w-4" />
                Cetak Slip
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($items as $item)
            @php
                $user = $item['user'];
            @endphp

            <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm print:break-inside-avoid">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-10 text-primary-70">
                        <x-lucide-key-round class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900" title="{{ $user->name }}">{{ $user->name }}</p>
                        <p class="truncate text-xs capitalize text-slate-500" title="{{ $user->role }}">{{ $user->role }}</p>
                    </div>
                    @if ($user->kelas)
                        <span class="ml-auto shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                            {{ $user->kelas }}
                        </span>
                    @endif
                </div>

                <div class="mt-5 space-y-2.5 border-t border-dashed border-slate-200 pt-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Email</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800">{{ $user->email }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Password Awal</p>
                        <p class="mt-0.5 font-mono text-sm font-medium text-slate-800">
                            {{ $item['initial_password'] ?: '— sudah diubah —' }}
                        </p>
                    </div>
                </div>
            </div>
        @empty
            <div class="sm:col-span-2 xl:col-span-3">
                <div class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-white/70 px-6 py-16 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-10 text-primary-70">
                        <x-lucide-search-x class="h-7 w-7" />
                    </span>

                    <h3 class="mt-4 text-base font-semibold text-slate-900">Tidak Ada Akun</h3>
                    <p class="mt-1.5 max-w-md text-sm leading-relaxed text-slate-500">Tidak ada akun guru atau siswa yang cocok dengan filter saat ini.</p>
                </div>
            </div>
        @endforelse
    </div>
@endsection

@push('scripts')
<style>
    /* ===== Tombol export: tiga keadaan (diam, mengunduh, berhasil) =====
       Gaya semuanya dipandu atribut data-state, jadi JS cukup mengubah satu atribut. */

    .ekspor-ikon { transition: opacity .18s ease, transform .18s ease; }

    /* Ikon yang tidak dipakai pada keadaan sekarang disembunyikan. */
    .ekspor[data-state="idle"] [data-ekspor-ikon]:not([data-ekspor-ikon="idle"]),
    .ekspor[data-state="muat"] [data-ekspor-ikon]:not([data-ekspor-ikon="muat"]),
    .ekspor[data-state="sukses"] [data-ekspor-ikon]:not([data-ekspor-ikon="sukses"]) {
        display: none;
    }

    /* Keadaan mengunduh: tombol mati agar tidak bisa diklik dua kali. */
    .ekspor[data-state="muat"] {
        cursor: progress;
        border-color: rgb(191 219 254);
        background-color: rgb(239 246 255);
        translate: none;
        box-shadow: none;
    }

    .ekspor[data-state="muat"]:hover { transform: none; }
    .ekspor[data-state="muat"] [data-ekspor-label] { opacity: .75; }

    /* Keadaan berhasil: hijau sejenak sebagai tanda file sudah masuk. */
    .ekspor[data-state="sukses"] {
        cursor: default;
        border-color: rgb(167 243 208);
        background-color: rgb(236 253 245);
        color: rgb(4 120 87);
        translate: none;
        box-shadow: 0 6px 14px -10px rgb(16 185 129);
    }

    .ekspor[data-state="sukses"] [data-ekspor-ikon="sukses"] {
        animation: ekspor-pop .45s cubic-bezier(.34, 1.56, .64, 1);
    }

    @keyframes ekspor-pop {
        0%   { transform: scale(.3) rotate(-25deg); opacity: 0; }
        60%  { transform: scale(1.18) rotate(6deg); opacity: 1; }
        100% { transform: scale(1) rotate(0); opacity: 1; }
    }

    /* Cahaya bergerak melintas tombol selama proses berjalan. */
    .ekspor-kilau {
        position: absolute;
        inset: 0;
        pointer-events: none;
        opacity: 0;
        background: linear-gradient(100deg,
                transparent 20%,
                rgb(255 255 255 / .85) 50%,
                transparent 80%);
        transform: translateX(-100%);
    }

    .ekspor[data-state="muat"] .ekspor-kilau {
        opacity: 1;
        animation: ekspor-kilau 1.1s ease-in-out infinite;
    }

    @keyframes ekspor-kilau {
        0%   { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }

    @media (prefers-reduced-motion: reduce) {
        .ekspor[data-state="muat"] .ekspor-kilau { animation: none; opacity: .5; }
        .ekspor[data-state="sukses"] [data-ekspor-ikon="sukses"] { animation: none; }
    }

    @media print {
        header, aside, #sidebar-backdrop, .no-print { display: none !important; }
        body { background: white; }
        .lg\:pl-64 { padding-left: 0 !important; }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const tombol = document.getElementById('slip-export');
        if (!tombol) return;

        const exportUrl = tombol.dataset.exportUrl;
        const label = tombol.querySelector('[data-ekspor-label]');
        const LABEL_AWAL = label ? label.textContent.trim() : 'Export Excel';
        const KEMBALI_SUKSES_MS = 2400;

        let exportState = 'idle';
        let exportTimer = null;

        const setExportState = (state, text) => {
            clearTimeout(exportTimer);
            exportState = state;
            tombol.dataset.state = state;
            tombol.disabled = state === 'muat';

            if (text && label) label.textContent = text;
        };

        const namaBerkas = (disposisi) => {
            const cocok = /filename\*?=(?:UTF-8''|")?([^";]+)/i.exec(disposisi || '');

            return cocok
                ? decodeURIComponent(cocok[1].replace(/"/g, '').trim())
                : 'Slip_Password_Akun.xlsx';
        };

        const unduhBlob = (blob, nama) => {
            const objectUrl = URL.createObjectURL(blob);
            const tautan = document.createElement('a');
            tautan.href = objectUrl;
            tautan.download = nama;
            document.body.appendChild(tautan);
            tautan.click();
            tautan.remove();
            window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1500);
        };

        tombol.addEventListener('click', async () => {
            if (exportState === 'muat') return;

            setExportState('muat', 'Menyiapkan…');

            try {
                const respons = await fetch(exportUrl, {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    },
                });

                const tipe = respons.headers.get('Content-Type') || '';

                if (!respons.ok || !tipe.includes('spreadsheet')) {
                    if (respons.status === 403) {
                        throw new Error('Anda tidak punya akses untuk mengunduh data ini.');
                    }

                    if (respons.status === 419) {
                        throw new Error('Sesi Anda sudah habis. Muat ulang halaman lalu coba lagi.');
                    }

                    throw new Error('File Excel gagal dibuat. Silakan coba lagi.');
                }

                const blob = await respons.blob();
                if (blob.size === 0) {
                    throw new Error('File Excel kosong. Silakan coba lagi.');
                }

                unduhBlob(blob, namaBerkas(respons.headers.get('Content-Disposition')));

                const total = Number(respons.headers.get('X-Total-Slip') || 0);

                setExportState('sukses', total > 0 ? `${total} akun diunduh` : 'Tidak ada akun');
                exportTimer = setTimeout(() => {
                    setExportState('idle', LABEL_AWAL);
                }, KEMBALI_SUKSES_MS);

                Swal.fire({
                    icon: 'success',
                    title: 'Export Berhasil',
                    text: 'Data slip password berhasil diunduh.',
                    showConfirmButton: false,
                    timer: 1800,
                    timerProgressBar: true,
                });
            } catch (galat) {
                setExportState('idle', LABEL_AWAL);
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Mengunduh',
                    text: galat instanceof Error ? galat.message : 'Tidak dapat terhubung ke server.',
                    confirmButtonText: 'Tutup',
                });
            }
        });
    });
</script>
@endpush