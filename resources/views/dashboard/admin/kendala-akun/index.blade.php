@extends('layouts.dashboard')

@section('title', 'Kendala Akun')
@section('subtitle', 'Dashboard Admin')

@section('content')
    @if (session('success'))
        <div class="mb-5 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <x-lucide-badge-check class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
            <p class="text-sm font-medium text-emerald-800">{{ session('success') }}</p>
        </div>
    @endif

    @php
        $totalSemua = array_sum($counts);
    @endphp

    {{-- Kartu Statistik --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">

        <a href="{{ route('dashboard.admin.kendala-akun.index', ['status' => \App\Models\ItHelpRequest::STATUS_BARU]) }}"
           @class([
               'group relative flex flex-col justify-between overflow-hidden rounded-2xl border p-4 transition-all duration-200',
               'border-blue-300 bg-blue-50/50 shadow-md shadow-blue-500/10' => $status === \App\Models\ItHelpRequest::STATUS_BARU,
               'border-slate-100 bg-white hover:border-blue-200 hover:shadow-sm' => $status !== \App\Models\ItHelpRequest::STATUS_BARU,
           ])>
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Baru</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-100 text-blue-600 transition group-hover:scale-105">
                    <x-lucide-inbox class="h-4 w-4" />
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold tracking-tight text-slate-900">{{ $counts[\App\Models\ItHelpRequest::STATUS_BARU] ?? 0 }}</span>
                <span class="mt-0.5 block text-xs text-slate-400">Belum ditindaklanjuti</span>
            </div>
        </a>

        <a href="{{ route('dashboard.admin.kendala-akun.index', ['status' => \App\Models\ItHelpRequest::STATUS_DIPROSES]) }}"
           @class([
               'group relative flex flex-col justify-between overflow-hidden rounded-2xl border p-4 transition-all duration-200',
               'border-amber-300 bg-amber-50/50 shadow-md shadow-amber-500/10' => $status === \App\Models\ItHelpRequest::STATUS_DIPROSES,
               'border-slate-100 bg-white hover:border-amber-200 hover:shadow-sm' => $status !== \App\Models\ItHelpRequest::STATUS_DIPROSES,
           ])>
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Diproses</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-100 text-amber-600 transition group-hover:scale-105">
                    <x-lucide-clock class="h-4 w-4" />
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold tracking-tight text-slate-900">{{ $counts[\App\Models\ItHelpRequest::STATUS_DIPROSES] ?? 0 }}</span>
                <span class="mt-0.5 block text-xs text-slate-400">Sedang ditangani</span>
            </div>
        </a>

        <a href="{{ route('dashboard.admin.kendala-akun.index', ['status' => \App\Models\ItHelpRequest::STATUS_SUDAH_DIBALAS]) }}"
           @class([
               'group relative flex flex-col justify-between overflow-hidden rounded-2xl border p-4 transition-all duration-200',
               'border-emerald-300 bg-emerald-50/50 shadow-md shadow-emerald-500/10' => $status === \App\Models\ItHelpRequest::STATUS_SUDAH_DIBALAS,
               'border-slate-100 bg-white hover:border-emerald-200 hover:shadow-sm' => $status !== \App\Models\ItHelpRequest::STATUS_SUDAH_DIBALAS,
           ])>
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Sudah Dibalas</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 transition group-hover:scale-105">
                    <x-lucide-reply class="h-4 w-4" />
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold tracking-tight text-slate-900">{{ $counts[\App\Models\ItHelpRequest::STATUS_SUDAH_DIBALAS] ?? 0 }}</span>
                <span class="mt-0.5 block text-xs text-slate-400">Balasan terkirim</span>
            </div>
        </a>

        <div class="flex flex-col justify-between rounded-2xl border border-slate-100 bg-white p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                    <x-lucide-life-buoy class="h-4 w-4" />
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold tracking-tight text-slate-900">{{ $totalSemua }}</span>
                <span class="mt-0.5 block text-xs text-slate-400">Semua pengajuan</span>
            </div>
        </div>
    </div>

    {{-- Panel Utama --}}
    <div class="panel-card overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">

        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <div class="flex min-w-0 items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                    <x-lucide-life-buoy class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold text-slate-900 sm:text-base">Kendala Akun &middot; Hubungi Tim IT</h2>
                    <p class="mt-0.5 text-xs text-slate-400">
                        Pengajuan dari form "Hubungi Tim IT" di halaman beranda
                    </p>
                </div>
            </div>
        </div>

        {{-- Form Pencarian & Filter --}}
        <form method="GET" action="{{ route('dashboard.admin.kendala-akun.index') }}"
              class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/40 p-4 sm:flex-row sm:items-center sm:gap-2.5 sm:p-5">

            <div class="relative flex-1">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                    <x-lucide-search class="h-4 w-4" />
                </span>
                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Cari nama, NIP/NIS, email, atau isi kendala…"
                    class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-9 pr-4 text-sm text-slate-800 placeholder-slate-400 shadow-xs transition focus:border-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-50/30"
                />
            </div>

            <select name="role"
                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-xs transition focus:border-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-50/30 sm:w-36">
                <option value="">Semua Role</option>
                <option value="guru" @selected($role === 'guru')>Guru</option>
                <option value="siswa" @selected($role === 'siswa')>Siswa</option>
            </select>

            <select name="status"
                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-xs transition focus:border-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-50/30 sm:w-44">
                <option value="">Semua Status</option>
                <option value="{{ \App\Models\ItHelpRequest::STATUS_BARU }}"
                        @selected($status === \App\Models\ItHelpRequest::STATUS_BARU)>Baru</option>
                <option value="{{ \App\Models\ItHelpRequest::STATUS_DIPROSES }}"
                        @selected($status === \App\Models\ItHelpRequest::STATUS_DIPROSES)>Diproses</option>
                <option value="{{ \App\Models\ItHelpRequest::STATUS_SUDAH_DIBALAS }}"
                        @selected($status === \App\Models\ItHelpRequest::STATUS_SUDAH_DIBALAS)>Sudah Dibalas</option>
                <option value="{{ \App\Models\ItHelpRequest::STATUS_SELESAI }}"
                        @selected($status === \App\Models\ItHelpRequest::STATUS_SELESAI)>Selesai</option>
            </select>

            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-primary-60 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-70">
                <x-lucide-search class="h-4 w-4" />
                Cari
            </button>

            @if ($search !== '' || $status !== '' || $role !== '')
                <a href="{{ route('dashboard.admin.kendala-akun.index') }}"
                   title="Reset semua filter"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-600 shadow-xs transition hover:border-slate-300 hover:bg-slate-50">
                    <x-lucide-rotate-ccw class="h-4 w-4" />
                    Reset
                </a>
            @endif
        </form>

            @if ($requests->isEmpty())
            <div class="flex flex-col items-center gap-3 py-16 text-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                    <x-lucide-life-buoy class="h-7 w-7" />
                </span>
                <p class="text-sm font-medium text-slate-500">
                    @if ($search !== '' || $status !== '' || $role !== '')
                        Tidak ada pengajuan yang cocok dengan filter saat ini.
                    @else
                        Belum ada pengajuan Kendala Akun.
                    @endif
                </p>
                @if ($search !== '' || $status !== '' || $role !== '')
                    <a href="{{ route('dashboard.admin.kendala-akun.index') }}"
                       class="text-xs text-primary-60 underline underline-offset-2 hover:text-primary-70">
                        Hapus semua filter
                    </a>
                @endif
            </div>
        @else
            {{-- Desktop: tabel --}}
            <div class="hidden overflow-x-auto sm:block">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/60 text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="px-5 py-3.5">Pengaju</th>
                            <th class="px-5 py-3.5">Role</th>
                            <th class="px-5 py-3.5">NIP / NIS</th>
                            <th class="px-5 py-3.5">Email</th>
                            <th class="px-5 py-3.5">Waktu Pengajuan</th>
                            <th class="px-5 py-3.5">Status</th>
                            <th class="px-5 py-3.5 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($requests as $item)
                            @php
                                $roleBadge = [
                                    'guru'  => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                    'siswa' => 'bg-amber-50 text-amber-700 ring-amber-200',
                                ][$item->role] ?? 'bg-slate-100 text-slate-600 ring-slate-200';

                                $statusBadge = [
                                    \App\Models\ItHelpRequest::STATUS_BARU          => 'bg-blue-50 text-blue-700 ring-blue-200',
                                    \App\Models\ItHelpRequest::STATUS_DIPROSES      => 'bg-amber-50 text-amber-700 ring-amber-200',
                                    \App\Models\ItHelpRequest::STATUS_SUDAH_DIBALAS => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                    \App\Models\ItHelpRequest::STATUS_SELESAI       => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                ][$item->status] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
                            @endphp
                            <tr class="group transition hover:bg-slate-50/60">
                                <td class="px-5 py-3.5">
                                    <p class="font-medium text-slate-800">{{ $item->user?->name ?? 'Tidak terdaftar' }}</p>
                                    @if (! $item->user)
                                        <p class="text-xs text-slate-400">Identitas tidak cocok</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $roleBadge }}">
                                        {{ ucfirst($item->role) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 font-mono text-sm text-slate-700">{{ $item->identifier }}</td>
                                <td class="px-5 py-3.5 max-w-[180px] truncate text-slate-700" title="{{ $item->email }}">{{ $item->email }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-slate-500">
                                    {{ \App\Support\Waktu::lengkap($item->created_at) ?? '-' }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusBadge }}">
                                        {{ $item->statusLabel() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <a href="{{ route('dashboard.admin.kendala-akun.show', $item) }}"
                                       class="inline-flex items-center gap-1 rounded-lg bg-primary-60 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-70">
                                        <x-lucide-eye class="h-3.5 w-3.5" />
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile: kartu --}}
            <div class="divide-y divide-slate-100 sm:hidden">
                @foreach ($requests as $item)
                    @php
                        $roleBadge = [
                            'guru'  => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                            'siswa' => 'bg-amber-50 text-amber-700 ring-amber-200',
                        ][$item->role] ?? 'bg-slate-100 text-slate-600 ring-slate-200';

                        $statusBadge = [
                            \App\Models\ItHelpRequest::STATUS_BARU          => 'bg-blue-50 text-blue-700 ring-blue-200',
                            \App\Models\ItHelpRequest::STATUS_DIPROSES      => 'bg-amber-50 text-amber-700 ring-amber-200',
                            \App\Models\ItHelpRequest::STATUS_SUDAH_DIBALAS => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                            \App\Models\ItHelpRequest::STATUS_SELESAI       => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                        ][$item->status] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
                    @endphp
                    <a href="{{ route('dashboard.admin.kendala-akun.show', $item) }}"
                       class="flex flex-col gap-2 p-4 transition hover:bg-slate-50/60">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-slate-800">{{ $item->user?->name ?? 'Tidak terdaftar' }}</p>
                                <p class="mt-0.5 font-mono text-xs text-slate-500">{{ $item->identifier }}</p>
                            </div>
                            <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusBadge }}">
                                {{ $item->statusLabel() }}
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $roleBadge }}">
                                {{ ucfirst($item->role) }}
                            </span>
                            <span class="text-xs text-slate-400">{{ $item->email }}</span>
                        </div>
                        <p class="truncate text-xs text-slate-400">{{ \App\Support\Waktu::lengkap($item->created_at) ?? '-' }}</p>
                    </a>
                @endforeach
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-400">
                    Menampilkan {{ $requests->firstItem() }}–{{ $requests->lastItem() }}
                    dari {{ $requests->total() }} pengajuan
                </p>
                @if ($requests->hasPages())
                    <div class="flex flex-wrap items-center gap-1">
                        @if ($requests->onFirstPage())
                            <span class="inline-flex h-8 w-8 cursor-not-allowed items-center justify-center rounded-lg border border-slate-100 bg-white text-slate-300">
                                <x-lucide-chevron-left class="h-4 w-4" />
                            </span>
                        @else
                            <a href="{{ $requests->previousPageUrl() }}"
                               class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-slate-300 hover:bg-slate-50">
                                <x-lucide-chevron-left class="h-4 w-4" />
                            </a>
                        @endif

                        @foreach ($requests->getUrlRange(max(1, $requests->currentPage() - 2), min($requests->lastPage(), $requests->currentPage() + 2)) as $page => $url)
                            @if ($page === $requests->currentPage())
                                <span class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg bg-primary-60 px-2 text-sm font-semibold text-white">
                                    {{ $page }}
                                </span>
                            @else
                                <a href="{{ $url }}"
                                   class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-2 text-sm text-slate-600 transition hover:border-slate-300 hover:bg-slate-50">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach

                        @if ($requests->hasMorePages())
                            <a href="{{ $requests->nextPageUrl() }}"
                               class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-slate-300 hover:bg-slate-50">
                                <x-lucide-chevron-right class="h-4 w-4" />
                            </a>
                        @else
                            <span class="inline-flex h-8 w-8 cursor-not-allowed items-center justify-center rounded-lg border border-slate-100 bg-white text-slate-300">
                                <x-lucide-chevron-right class="h-4 w-4" />
                            </span>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection

