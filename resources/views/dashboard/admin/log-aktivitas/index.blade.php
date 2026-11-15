@extends('layouts.dashboard')

@section('title', 'Log Aktivitas')
@section('subtitle', 'Dashboard Admin')

@section('content')
    @php
        $roleBadge = [
            'admin' => 'bg-blue-50 text-blue-700 ring-blue-200',
            'guru' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'siswa' => 'bg-amber-50 text-amber-700 ring-amber-200',
        ];

        $tipeBadge = [
            \App\Models\ActivityLog::TIPE_AUTH => 'bg-slate-100 text-slate-700 ring-slate-200',
            \App\Models\ActivityLog::TIPE_PASSWORD => 'bg-violet-50 text-violet-700 ring-violet-200',
            \App\Models\ActivityLog::TIPE_DATA_GURU => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            \App\Models\ActivityLog::TIPE_DATA_SISWA => 'bg-amber-50 text-amber-700 ring-amber-200',
            \App\Models\ActivityLog::TIPE_DATA_KELAS => 'bg-sky-50 text-sky-700 ring-sky-200',
            \App\Models\ActivityLog::TIPE_DATA_JURUSAN => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
            \App\Models\ActivityLog::TIPE_DATA_MAPEL => 'bg-rose-50 text-rose-700 ring-rose-200',
            \App\Models\ActivityLog::TIPE_MANAJEMEN_AKUN => 'bg-cyan-50 text-cyan-700 ring-cyan-200',
            \App\Models\ActivityLog::TIPE_PENGAJUAN_RESET_PASSWORD => 'bg-orange-50 text-orange-700 ring-orange-200',
            \App\Models\ActivityLog::TIPE_KENDALA_AKUN => 'bg-teal-50 text-teal-700 ring-teal-200',
            \App\Models\ActivityLog::TIPE_BACKUP_DATABASE => 'bg-slate-100 text-slate-700 ring-slate-200',
            \App\Models\ActivityLog::TIPE_INPUT_NILAI => 'bg-lime-50 text-lime-700 ring-lime-200',
        ];

        $adaFilter = $search !== '' || $role !== '' || $tipe !== '' || $dari !== null || $sampai !== null;
    @endphp

    <div class="panel-card overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <div class="flex min-w-0 items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                    <x-lucide-history class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold text-slate-900 sm:text-base">Log Aktivitas</h2>
                    <p class="mt-0.5 text-xs text-slate-400">
                        Riwayat aktivitas Admin, Guru, dan Siswa &middot; diurutkan dari yang terbaru
                    </p>
                </div>
            </div>

            <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600">
                <x-lucide-lock class="h-3.5 w-3.5" />
                Riwayat tidak dapat diubah
            </span>
        </div>

        <form method="GET" action="{{ route('dashboard.admin.log-aktivitas.index') }}"
              class="border-b border-slate-100 bg-slate-50/40 p-4 sm:p-5">

            <div class="grid grid-cols-1 gap-x-3 gap-y-4 sm:grid-cols-2 xl:grid-cols-12">
                <div class="sm:col-span-2 xl:col-span-4">
                    <label for="search" class="mb-1.5 block text-xs font-medium text-slate-500">Cari Aktivitas</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3.5 top-1/2 flex -translate-y-1/2 text-slate-400">
                            <x-lucide-search class="h-4 w-4" />
                        </span>
                        <input
                            id="search"
                            type="search"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Cari nama pengguna atau aktivitas…"
                            class="h-10 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-3.5 text-sm text-slate-800 placeholder:text-slate-400 shadow-xs transition focus:border-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-50/30"
                        />
                    </div>
                </div>

                <div class="xl:col-span-2">
                    <label for="role" class="mb-1.5 block text-xs font-medium text-slate-500">Role</label>
                    <div class="relative">
                        <select id="role" name="role"
                                class="h-10 w-full appearance-none rounded-xl border border-slate-200 bg-white pl-3 pr-9 text-sm text-slate-700 shadow-xs transition focus:border-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-50/30">
                            <option value="">Semua Role</option>
                            <option value="admin" @selected($role === 'admin')>Admin</option>
                            <option value="guru" @selected($role === 'guru')>Guru</option>
                            <option value="siswa" @selected($role === 'siswa')>Siswa</option>
                        </select>
                        <x-lucide-chevron-down class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    </div>
                </div>

                <div class="xl:col-span-3">
                    <label for="tipe" class="mb-1.5 block text-xs font-medium text-slate-500">Jenis Aktivitas</label>
                    <div class="relative">
                        <select id="tipe" name="tipe"
                                class="h-10 w-full appearance-none rounded-xl border border-slate-200 bg-white pl-3 pr-9 text-sm text-slate-700 shadow-xs transition focus:border-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-50/30">
                            <option value="">Semua Jenis Aktivitas</option>
                            @foreach ($tipeOptions as $nilai => $label)
                                <option value="{{ $nilai }}" @selected($tipe === $nilai)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-lucide-chevron-down class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    </div>
                </div>

                <div class="sm:col-span-2 xl:col-span-3">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <div class="min-w-0">
                            <label for="dari" class="mb-1.5 block text-xs font-medium text-slate-500">Dari Tanggal</label>
                            <input id="dari" type="date" name="dari" value="{{ $dari }}"
                                   placeholder="Pilih tanggal mulai"
                                   title="Dari Tanggal"
                                   @if ($tanggalError !== null) aria-invalid="true" aria-describedby="tanggal-error" @endif
                                   class="h-10 w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-xs transition focus:border-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-50/30" />
                        </div>
                        <div class="min-w-0">
                            <label for="sampai" class="mb-1.5 block text-xs font-medium text-slate-500">Sampai Tanggal</label>
                            <input id="sampai" type="date" name="sampai" value="{{ $sampai }}"
                                   placeholder="Pilih tanggal akhir"
                                   title="Sampai Tanggal"
                                   @if ($dari !== null) min="{{ $dari }}" @endif
                                   @if ($tanggalError !== null) aria-invalid="true" aria-describedby="tanggal-error" @endif
                                   class="h-10 w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-xs transition focus:border-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-50/30" />
                        </div>
                    </div>
                </div>
            </div>

            @if ($tanggalError !== null)
                <div class="mt-3">
                    <p id="tanggal-error" role="alert" class="flex items-start gap-1.5 text-xs font-medium text-red-600">
                        <x-lucide-triangle-alert class="mt-px h-3.5 w-3.5 shrink-0" />
                        <span>{{ $tanggalError }}</span>
                    </p>
                </div>
            @endif

            <div class="mt-4 flex flex-col gap-3 border-t border-slate-200/70 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit"
                            class="inline-flex h-10 items-center gap-2 rounded-xl bg-primary-60 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-70 focus:outline-none focus:ring-2 focus:ring-primary-50/30 active:scale-[.98]">
                        <x-lucide-filter class="h-4 w-4" />
                        Terapkan Filter
                    </button>

                    @if ($dari !== null || $sampai !== null)
                        <a href="{{ $tanggalResetUrl }}"
                           title="Hapus filter tanggal"
                           class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-medium text-slate-600 shadow-xs transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-200 active:scale-[.98]">
                            <x-lucide-calendar-x class="h-4 w-4" />
                            Reset Tanggal
                        </a>
                    @endif

                    @if ($adaFilter)
                        <a href="{{ route('dashboard.admin.log-aktivitas.index') }}"
                           title="Reset semua filter"
                           class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-medium text-slate-600 shadow-xs transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-200 active:scale-[.98]">
                            <x-lucide-rotate-ccw class="h-4 w-4" />
                            Reset Filter
                        </a>
                    @endif
                </div>

                <span class="inline-flex items-center gap-1.5 text-xs text-slate-400">
                    <x-lucide-info class="h-3.5 w-3.5 shrink-0" />
                    Maksimal 10 data per halaman
                </span>
            </div>
        </form>

        @if ($logs->isEmpty())
            <div class="flex flex-col items-center gap-3 py-16 text-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                    <x-lucide-history class="h-7 w-7" />
                </span>
                <p class="text-sm font-medium text-slate-500">
                    @if ($adaFilter)
                        Tidak ada aktivitas yang cocok dengan filter saat ini.
                    @else
                        Belum ada aktivitas tercatat.
                    @endif
                </p>
                @if ($adaFilter)
                    <a href="{{ route('dashboard.admin.log-aktivitas.index') }}"
                       class="text-xs text-primary-60 underline underline-offset-2 hover:text-primary-70">
                        Hapus semua filter
                    </a>
                @endif
            </div>
        @else
            <div class="hidden overflow-x-auto sm:block">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/60 text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="px-5 py-3.5">Nama Pengguna</th>
                            <th class="px-5 py-3.5">Role</th>
                            <th class="px-5 py-3.5">Jenis Aktivitas</th>
                            <th class="px-5 py-3.5">Deskripsi</th>
                            <th class="px-5 py-3.5">Waktu Kejadian</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($logs as $log)
                            <tr class="transition hover:bg-slate-50/60">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-bold text-primary-70">
                                            {{ strtoupper(substr($log->user_name ?? '?', 0, 1)) }}
                                        </span>
                                        <span class="font-medium text-slate-800">{{ $log->user_name ?? 'Pengguna terhapus' }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $roleBadge[$log->role] ?? 'bg-slate-100 text-slate-600 ring-slate-200' }}">
                                        {{ $log->roleLabel() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $tipeBadge[$log->activity_type] ?? 'bg-slate-100 text-slate-600 ring-slate-200' }}">
                                        {{ $log->label() }}
                                    </span>
                                </td>
                                <td class="max-w-md px-5 py-3.5 leading-relaxed text-slate-600">
                                    {{ $log->description }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-slate-500">
                                    {{ \App\Support\Waktu::lengkap($log->created_at) ?? '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-100 sm:hidden">
                @foreach ($logs as $log)
                    <div class="flex flex-col gap-2 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-2.5">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-bold text-primary-70">
                                    {{ strtoupper(substr($log->user_name ?? '?', 0, 1)) }}
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-slate-800">{{ $log->user_name ?? 'Pengguna terhapus' }}</p>
                                    <p class="text-xs text-slate-400">{{ \App\Support\Waktu::lengkap($log->created_at) ?? '-' }}</p>
                                </div>
                            </div>
                            <span class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $roleBadge[$log->role] ?? 'bg-slate-100 text-slate-600 ring-slate-200' }}">
                                {{ $log->roleLabel() }}
                            </span>
                        </div>
                        <div>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $tipeBadge[$log->activity_type] ?? 'bg-slate-100 text-slate-600 ring-slate-200' }}">
                                {{ $log->label() }}
                            </span>
                        </div>
                        <p class="text-sm leading-relaxed text-slate-600">{{ $log->description }}</p>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-slate-100 px-5 py-4">
                <x-ui.server-pagination :paginator="$logs" label="aktivitas" />
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dari = document.getElementById('dari');
    const sampai = document.getElementById('sampai');

    if (!dari || !sampai) {
        return;
    }

    const perbaruiBatasTanggal = () => {
        if (dari.value !== '') {
            sampai.min = dari.value;
        } else {
            sampai.removeAttribute('min');
        }
    };

    dari.addEventListener('change', perbaruiBatasTanggal);
    dari.addEventListener('input', perbaruiBatasTanggal);
    perbaruiBatasTanggal();
});
</script>
@endpush
