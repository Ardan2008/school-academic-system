@extends('layouts.dashboard')

@section('title', 'Pengajuan Reset Password')
@section('subtitle', 'Dashboard Admin')

@section('content')
    @if (session('success'))
        <div class="mb-5 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <x-lucide-badge-check class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
            <p class="text-sm font-medium text-emerald-800">{{ session('success') }}</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5 flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4">
            <x-lucide-triangle-alert class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
            <p class="text-sm font-medium text-red-800">{{ $errors->first() }}</p>
        </div>
    @endif

    {{-- Kartu Statistik --}}
    @php
        $totalPengajuan = array_sum($counts);
    @endphp
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
        <a href="{{ route('dashboard.admin.pengajuan-reset-password.index', ['status' => 'pending']) }}"
           @class([
               'group relative flex flex-col justify-between overflow-hidden rounded-2xl border p-4 transition-all duration-200',
               'border-amber-300 bg-amber-50/50 shadow-md shadow-amber-500/10' => $status === 'pending',
               'border-slate-100 bg-white hover:border-amber-200 hover:shadow-sm' => $status !== 'pending',
           ])>
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Menunggu</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-100 text-amber-600 transition group-hover:scale-105">
                    <x-lucide-clock class="h-4 w-4" />
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold tracking-tight text-slate-900">{{ $counts['pending'] ?? 0 }}</span>
                <span class="mt-0.5 block text-xs text-slate-400">Perlu tindakan</span>
            </div>
        </a>

        <a href="{{ route('dashboard.admin.pengajuan-reset-password.index', ['status' => 'approved']) }}"
           @class([
               'group relative flex flex-col justify-between overflow-hidden rounded-2xl border p-4 transition-all duration-200',
               'border-emerald-300 bg-emerald-50/50 shadow-md shadow-emerald-500/10' => $status === 'approved',
               'border-slate-100 bg-white hover:border-emerald-200 hover:shadow-sm' => $status !== 'approved',
           ])>
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Disetujui</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 transition group-hover:scale-105">
                    <x-lucide-check-circle-2 class="h-4 w-4" />
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold tracking-tight text-slate-900">{{ $counts['approved'] ?? 0 }}</span>
                <span class="mt-0.5 block text-xs text-slate-400">Tautan dikirim</span>
            </div>
        </a>

        <a href="{{ route('dashboard.admin.pengajuan-reset-password.index', ['status' => 'rejected']) }}"
           @class([
               'group relative flex flex-col justify-between overflow-hidden rounded-2xl border p-4 transition-all duration-200',
               'border-red-300 bg-red-50/50 shadow-md shadow-red-500/10' => $status === 'rejected',
               'border-slate-100 bg-white hover:border-red-200 hover:shadow-sm' => $status !== 'rejected',
           ])>
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Ditolak</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-red-100 text-red-600 transition group-hover:scale-105">
                    <x-lucide-x-circle class="h-4 w-4" />
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold tracking-tight text-slate-900">{{ $counts['rejected'] ?? 0 }}</span>
                <span class="mt-0.5 block text-xs text-slate-400">Tercatat alasan</span>
            </div>
        </a>

        <div class="flex flex-col justify-between rounded-2xl border border-slate-100 bg-white p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Pengajuan</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary-10 text-primary-70">
                    <x-lucide-key-round class="h-4 w-4" />
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold tracking-tight text-slate-900">{{ $totalPengajuan }}</span>
                <span class="mt-0.5 block text-xs text-slate-400">Seluruh riwayat</span>
            </div>
        </div>
    </div>

    {{-- Filter & Tab Bar --}}
    <div class="mb-5 flex flex-col gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">

            {{-- Tab Status Pills --}}
            <div class="flex flex-wrap items-center gap-2">
                @foreach ([
                    'pending' => 'Menunggu',
                    'approved' => 'Disetujui',
                    'rejected' => 'Ditolak',
                ] as $key => $label)
                    <a
                        href="{{ route('dashboard.admin.pengajuan-reset-password.index', array_filter(['status' => $key, 'search' => $search, 'role' => $role])) }}"
                        @class([
                            'inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold transition',
                            'bg-primary-60 text-white shadow-md shadow-primary-60/25' => $status === $key,
                            'border border-slate-200 bg-white text-slate-600 hover:border-primary-20 hover:text-primary-70' => $status !== $key,
                        ])
                    >
                        {{ $label }}
                        <span @class([
                            'inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-[11px]',
                            'bg-white/25 text-white' => $status === $key,
                            'bg-slate-100 text-slate-600' => $status !== $key,
                        ])>{{ $counts[$key] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>

            {{-- Form Pencarian dan Filter Role --}}
            <form method="GET" action="{{ route('dashboard.admin.pengajuan-reset-password.index') }}"
                  class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center lg:max-w-xl lg:justify-end">
                <input type="hidden" name="status" value="{{ $status }}">

                <div class="relative flex-1">
                    <x-lucide-search class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Cari nama, NIP, NIS, email, atau IP..."
                        class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-9 pr-4 text-sm text-slate-800 placeholder-slate-400 outline-none transition focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20"
                    >
                </div>

                <div class="flex items-center gap-2">
                    <select
                        name="role"
                        onchange="this.form.submit()"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-primary-60"
                    >
                        <option value="">Semua Role</option>
                        <option value="guru" @selected($role === 'guru')>Guru</option>
                        <option value="siswa" @selected($role === 'siswa')>Siswa</option>
                    </select>

                    <button
                        type="submit"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-primary-60 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-70"
                    >
                        <x-lucide-search class="h-3.5 w-3.5" />
                        <span>Cari</span>
                    </button>

                    @if ($search !== '' || $role !== '')
                        <a
                            href="{{ route('dashboard.admin.pengajuan-reset-password.index', ['status' => $status]) }}"
                            title="Reset filter"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white p-2 text-slate-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600"
                        >
                            <x-lucide-rotate-ccw class="h-4 w-4" />
                        </a>
                    @endif
                </div>
            </form>

        </div>
    </div>

    {{-- Tabel & Kartu Pengajuan --}}
    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
        {{-- Desktop table --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/70 text-xs uppercase tracking-wider text-slate-400">
                        <th class="px-5 py-3.5 text-left align-top font-semibold">Pemohon</th>
                        <th class="px-5 py-3.5 text-left align-top font-semibold whitespace-nowrap">Role</th>
                        <th class="px-5 py-3.5 text-left align-top font-semibold whitespace-nowrap">Identitas</th>
                        <th class="px-5 py-3.5 text-left align-top font-semibold">Email</th>
                        <th class="px-5 py-3.5 text-left align-top font-semibold whitespace-nowrap">Diajukan</th>
                        <th class="px-5 py-3.5 text-left align-top font-semibold">Status &amp; Info</th>
                        <th class="px-5 py-3.5 text-right align-top font-semibold whitespace-nowrap">
                            @if ($status === 'pending')
                                Aksi
                            @elseif ($status === 'approved')
                                Diverifikasi Oleh
                            @else
                                Ditolak Oleh
                            @endif
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($requests as $item)
                        @php
                            $roleBadge = [
                                'guru' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                'siswa' => 'bg-amber-50 text-amber-700 ring-amber-200',
                            ][$item->role] ?? 'bg-slate-100 text-slate-600 ring-slate-200';

                            $statusBadge = [
                                'pending' => 'bg-amber-50 text-amber-700 ring-amber-200',
                                'approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                'rejected' => 'bg-red-50 text-red-700 ring-red-200',
                            ][$item->status] ?? 'bg-slate-100 text-slate-600 ring-slate-200';

                            $tokenState = $tokenStates[$item->user_id] ?? null;
                        @endphp

                        <tr class="transition hover:bg-slate-50/60">
                            {{-- Pemohon --}}
                            <td class="px-5 py-4 align-top">
                                <div class="flex items-center gap-3">
                                    <span @class([
                                        'flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                        'bg-emerald-100 text-emerald-700' => $item->role === 'guru',
                                        'bg-amber-100 text-amber-700' => $item->role === 'siswa',
                                    ])>
                                        {{ strtoupper(substr($item->user?->name ?? '?', 0, 1)) }}
                                    </span>
                                    <div class="min-w-0">
                                        <span class="block max-w-[170px] truncate font-semibold text-slate-800" title="{{ $item->user?->name ?? 'Akun dihapus' }}">
                                            {{ $item->user?->name ?? 'Akun dihapus' }}
                                        </span>
                                        @if ($item->ip_address)
                                            <span class="mt-0.5 inline-flex items-center gap-1 font-mono text-[11px] text-slate-400">
                                                <x-lucide-globe class="h-3 w-3" />
                                                {{ $item->ip_address }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Role --}}
                            <td class="px-5 py-4 align-top">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize ring-1 {{ $roleBadge }}">
                                    {{ $item->role }}
                                </span>
                            </td>

                            {{-- Identitas --}}
                            <td class="whitespace-nowrap px-5 py-4 align-top">
                                <span class="text-[11px] font-medium uppercase text-slate-400">
                                    {{ \App\Support\LoginIdentifier::label($item->role) }}
                                </span>
                                <span class="mt-0.5 block font-mono text-sm font-medium text-slate-700">
                                    {{ $item->identifier }}
                                </span>
                            </td>

                            {{-- Email --}}
                            <td class="px-5 py-4 align-top">
                                @if ($item->user?->email)
                                    <div class="flex items-center gap-1.5 text-slate-600">
                                        <x-lucide-mail class="h-3.5 w-3.5 shrink-0 text-slate-400" />
                                        <span class="block max-w-[200px] truncate" title="{{ $item->user->email }}">{{ $item->user->email }}</span>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">Tidak ada email</span>
                                @endif
                            </td>

                            {{-- Diajukan --}}
                            <td class="whitespace-nowrap px-5 py-4 align-top">
                                <span class="block text-xs font-medium text-slate-700">
                                    {{ \App\Support\Waktu::tanggal($item->created_at) }}
                                </span>
                                <span class="mt-0.5 block font-mono text-[11px] text-slate-400">
                                    {{ \App\Support\Waktu::jamRelatif($item->created_at) }}
                                </span>
                            </td>

                            {{-- Status & Info --}}
                            <td class="px-5 py-4 align-top">
                                <div class="flex flex-col items-start gap-1">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $statusBadge }}">
                                        @if ($item->isPending())
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                        @elseif ($item->status === 'approved')
                                            <x-lucide-check class="h-3 w-3" />
                                        @else
                                            <x-lucide-x class="h-3 w-3" />
                                        @endif
                                        {{ $item->statusLabel() }}
                                    </span>

                                    {{-- State token reset: hanya di tab Disetujui --}}
                                    @if ($status === 'approved' && $tokenState)
                                        @php
                                            $tBadge = [
                                                'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                                'used' => 'bg-blue-50 text-blue-700 ring-blue-200',
                                                'expired' => 'bg-slate-100 text-slate-600 ring-slate-200',
                                            ][$tokenState] ?? 'bg-slate-100 text-slate-600 ring-slate-200';

                                            $tLabel = [
                                                'active' => 'Tautan Aktif (Menunggu Reset)',
                                                'used' => 'Password Berhasil Direset',
                                                'expired' => 'Tautan Kedaluwarsa',
                                            ][$tokenState] ?? ucfirst($tokenState);
                                        @endphp
                                        <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-medium ring-1 {{ $tBadge }}">
                                            @if ($tokenState === 'active')
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            @elseif ($tokenState === 'used')
                                                <x-lucide-check-circle-2 class="h-3 w-3" />
                                            @else
                                                <x-lucide-clock class="h-3 w-3" />
                                            @endif
                                            {{ $tLabel }}
                                        </span>
                                    @endif

                                    {{-- Info Email Terkirim --}}
                                    @if ($item->email_sent_at)
                                        <span class="inline-flex items-center gap-1 text-[11px] text-slate-400">
                                            <x-lucide-send class="h-2.5 w-2.5" />
                                             Email {{ \App\Support\Waktu::bulanJam($item->email_sent_at) }}
                                        </span>
                                    @endif

                                    {{-- Info Alasan Penolakan --}}
                                    @if ($item->rejection_reason)
                                        <div class="mt-1 max-w-xs rounded-lg bg-red-50 p-2 text-xs text-red-700 ring-1 ring-red-200/60">
                                            <span class="font-semibold block text-[10px] uppercase text-red-500">Alasan:</span>
                                            {{ $item->rejection_reason }}
                                        </div>
                                    @endif
                                </div>
                            </td>

                            {{-- Aksi / Diverifikasi Oleh --}}
                            <td class="px-5 py-4 align-top">
                                @if ($item->isPending())
                                    <div class="flex items-center justify-end gap-2">
                                        <form method="POST" action="{{ route('dashboard.admin.pengajuan-reset-password.tolak', $item) }}">
                                            @csrf
                                            <button
                                                type="button"
                                                data-reject="{{ $item->id }}"
                                                data-name="{{ $item->user?->name }}"
                                                data-identifier="{{ $item->identifier }}"
                                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-red-300 hover:bg-red-50 hover:text-red-700"
                                            >
                                                <x-lucide-x class="h-3.5 w-3.5 text-red-500" />
                                                Tolak
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('dashboard.admin.pengajuan-reset-password.setujui', $item) }}">
                                            @csrf
                                            <button
                                                type="submit"
                                                data-approve="{{ $item->id }}"
                                                data-name="{{ $item->user?->name }}"
                                                data-email="{{ $item->user?->email }}"
                                                class="inline-flex items-center gap-1.5 rounded-xl bg-primary-60 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-70"
                                            >
                                                <x-lucide-check class="h-3.5 w-3.5" />
                                                Setujui
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <div class="text-right">
                                        @if ($item->reviewer)
                                            <div class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                                                <x-lucide-shield-check class="h-3.5 w-3.5 text-primary-60" />
                                                <span>{{ $item->reviewer->name }}</span>
                                            </div>
                                            <span class="block text-[11px] text-slate-400">
                                                {{ \App\Support\Waktu::lengkap($item->reviewed_at) }}
                                            </span>
                                        @else
                                            <span class="text-xs text-slate-400">-</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-16 text-center">
                                @if ($search !== '' || $role !== '')
                                    <x-lucide-search-x class="mx-auto h-9 w-9 text-slate-300" />
                                    <p class="mt-3 text-sm font-semibold text-slate-600">Tidak ada pengajuan yang cocok</p>
                                    <p class="mt-1 text-xs text-slate-400">
                                        Coba ubah kata kunci pencarian atau ganti filter role.
                                    </p>
                                    <div class="mt-4">
                                        <a
                                            href="{{ route('dashboard.admin.pengajuan-reset-password.index', ['status' => $status]) }}"
                                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
                                        >
                                            <x-lucide-rotate-ccw class="h-3.5 w-3.5" />
                                            Reset Filter
                                        </a>
                                    </div>
                                @else
                                    <x-lucide-inbox class="mx-auto h-9 w-9 text-slate-300" />
                                    <p class="mt-3 text-sm font-semibold text-slate-600">Tidak ada pengajuan {{ strtolower($statusLabel ?? '') }}</p>
                                    <p class="mt-1 text-xs text-slate-400">
                                        @if ($status === 'pending')
                                            Saat ini belum ada pengajuan reset password yang menunggu persetujuan.
                                        @elseif ($status === 'approved')
                                            Belum ada pengajuan yang disetujui.
                                        @else
                                            Belum ada pengajuan yang ditolak.
                                        @endif
                                    </p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="md:hidden divide-y divide-slate-100">
            @forelse ($requests as $item)
                @php
                    $roleBadge = [
                        'guru' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                        'siswa' => 'bg-amber-50 text-amber-700 ring-amber-200',
                    ][$item->role] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
                    $statusColors = [
                        'pending' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'ring' => 'ring-amber-200'],
                        'approved' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'ring' => 'ring-emerald-200'],
                        'rejected' => ['bg' => 'bg-red-50', 'text' => 'text-red-700', 'ring' => 'ring-red-200'],
                    ][$item->status] ?? ['bg' => 'bg-slate-50', 'text' => 'text-slate-600', 'ring' => 'ring-slate-200'];
                    $statusText = [
                        'pending' => 'Menunggu',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    ][$item->status] ?? strtoupper($item->status);
                    $tanggal = $item->created_at ? $item->created_at->translatedFormat('d M Y, H:i') : '-';
                    $identitas = $item->role === 'guru' ? ($item->nip ?? '-') : ($item->nis ?? '-');
                    $requesterName = $item->user?->name ?? $item->nama ?? $item->email ?? '-';
                @endphp
                <div class="p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-800">{{ $requesterName }}</p>
                            <p class="mt-0.5 text-xs text-slate-400">Diajukan {{ $tanggal }}</p>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 {{ $statusColors['bg'] }} {{ $statusColors['text'] }} {{ $statusColors['ring'] }}">
                            {{ $statusText }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="min-w-0">
                            <p class="text-xs text-slate-400">Role</p>
                            <span class="mt-0.5 inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 {{ $roleBadge }}">{{ ucfirst($item->role) }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-slate-400">Identitas</p>
                            <p class="mt-0.5 font-medium text-slate-700 truncate">{{ $identitas }}</p>
                        </div>
                        <div class="col-span-2 min-w-0">
                            <p class="text-xs text-slate-400">Email</p>
                            <p class="mt-0.5 font-medium text-slate-700 truncate" title="{{ $item->email }}">{{ $item->email }}</p>
                        </div>
                        @if ($status === 'rejected' && $item->rejection_reason)
                            <div class="col-span-2 min-w-0">
                                <p class="text-[10px] font-semibold uppercase text-red-500">Alasan:</p>
                                <p class="mt-0.5 text-sm text-slate-600">{{ $item->rejection_reason }}</p>
                            </div>
                        @endif
                    </div>
                    @if ($status === 'pending')
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('dashboard.admin.pengajuan-reset-password.setujui', $item) }}" class="flex-1">
                                @csrf
                                <button type="submit"
                                        data-approve
                                        data-name="{{ $requesterName }}"
                                        data-email="{{ $item->email }}"
                                        class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                                    <x-lucide-check class="h-4 w-4" />
                                    Setujui
                                </button>
                            </form>
                            <form method="POST" action="{{ route('dashboard.admin.pengajuan-reset-password.tolak', $item) }}" class="flex-1">
                                @csrf
                                <button type="button"
                                        data-reject
                                        data-name="{{ $requesterName }}"
                                        data-identifier="{{ $identitas }}"
                                        class="flex w-full items-center justify-center rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                                    Tolak
                                </button>
                            </form>
                        </div>
                    @elseif ($status === 'approved')
                        <div class="text-xs text-slate-500">
                            @if ($item->approvedBy)
                                Disetujui oleh: <span class="font-medium text-slate-700">{{ $item->approvedBy->name }}</span>
                            @endif
                            @if ($item->approved_at)
                                <span class="block mt-0.5">({{ $item->approved_at->translatedFormat('d M Y, H:i') }})</span>
                            @endif
                        </div>
                    @else
                        <div class="text-xs text-slate-500">
                            @if ($item->rejectedBy)
                                Ditolak oleh: <span class="font-medium text-slate-700">{{ $item->rejectedBy->name }}</span>
                            @endif
                            @if ($item->rejected_at)
                                <span class="block mt-0.5">({{ $item->rejected_at->translatedFormat('d M Y, H:i') }})</span>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="p-6 text-center text-sm text-slate-500">Tidak ada pengajuan untuk filter saat ini.</div>
            @endforelse
        </div>

        <div class="border-t border-slate-100 px-5 py-3">
            {{ $requests->links() }}
        </div>
    </div>

    {{-- Overlay proses --}}
    <div
        id="proses-overlay"
        data-open="false"
        aria-hidden="true"
        inert
        class="proses-overlay fixed inset-0 z-[80] flex flex-col items-center justify-center gap-3 bg-slate-900/25 px-6 text-center backdrop-blur-[2px]"
    >
        <div class="flex flex-col items-center gap-3 rounded-2xl border border-white/70 bg-white/85 px-8 py-6 shadow-xl">
            <div class="relative flex h-12 w-12 items-center justify-center">
                <span
                    aria-hidden="true"
                    class="absolute inset-0 animate-spin rounded-full border-[3px] border-blue-100 border-t-blue-600"
                ></span>
                <x-lucide-graduation-cap class="h-5 w-5 text-blue-600" />
            </div>
            <p data-proses-text class="text-sm font-bold tracking-wide text-blue-700">Memproses...</p>
            <p class="text-xs font-medium text-slate-500">Mohon tunggu sebentar, jangan tutup halaman ini.</p>
        </div>
    </div>

    <style>
        .proses-overlay {
            opacity: 0;
            pointer-events: none;
            transition: opacity .25s ease;
        }

        .proses-overlay[data-open="true"] {
            opacity: 1;
            pointer-events: auto;
        }

        @media (prefers-reduced-motion: reduce) {
            .proses-overlay {
                transition: none;
            }
        }
    </style>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        const overlay = document.getElementById('proses-overlay');
        const overlayText = overlay?.querySelector('[data-proses-text]');

        const showProses = (text) => {
            if (!overlay) return;
            if (text && overlayText) overlayText.textContent = text;
            overlay.removeAttribute('inert');
            overlay.setAttribute('aria-hidden', 'false');
            requestAnimationFrame(() => { overlay.dataset.open = 'true'; });
        };

        const hideProses = () => {
            if (!overlay) return;
            overlay.dataset.open = 'false';
            overlay.setAttribute('aria-hidden', 'true');
            const finish = () => overlay.setAttribute('inert', '');
            overlay.addEventListener('transitionend', finish, { once: true });
            setTimeout(finish, 300);
        };

        document.querySelectorAll('[data-approve]').forEach((button) => {
            button.addEventListener('click', async (e) => {
                e.preventDefault();

                const name = button.dataset.name || 'pengguna';
                const email = button.dataset.email || 'email akun';

                const { isConfirmed } = await Swal.fire({
                    icon: 'question',
                    title: 'Setujui Pengajuan?',
                    html: `Tautan atur ulang password berlaku <strong>30 menit</strong> akan dikirim ke email <strong>${email}</strong> milik <strong>${name}</strong>.<br><br><span class="text-xs text-slate-500">Password tidak akan diubah oleh admin; pemohon menetapkan sendiri password barunya.</span>`,
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Setujui & Kirim',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#2563eb',
                });

                if (!isConfirmed) return;

                button.disabled = true;
                showProses('Memproses pengajuan...');

                try {
                    const res = await fetch(button.form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    const data = await res.json().catch(() => null);

                    if (!res.ok || data?.status !== 'success') {
                        throw new Error(data?.message || 'Terjadi kesalahan.');
                    }

                    hideProses();

                    await Swal.fire({
                        icon: 'success',
                        title: 'Pengajuan Disetujui',
                        text: data.message,
                        showConfirmButton: false,
                        timer: 1600,
                        timerProgressBar: true,
                    });

                    window.location.reload();
                } catch (err) {
                    hideProses();
                    button.disabled = false;
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Menyetujui',
                        text: err.message,
                        confirmButtonText: 'Mengerti',
                    });
                }
            });
        });

        document.querySelectorAll('[data-reject]').forEach((button) => {
            button.addEventListener('click', async (e) => {
                e.preventDefault();

                const name = button.dataset.name || 'pemohon';
                const id = button.dataset.identifier || '';

                const { value: reason } = await Swal.fire({
                    icon: 'warning',
                    title: 'Tolak Pengajuan',
                    html: `Alasan penolakan wajib diisi agar tercatat di riwayat sistem.<br><span class="text-xs font-semibold text-slate-500">Pemohon: ${name} (${id})</span>`,
                    input: 'textarea',
                    inputPlaceholder: 'Contoh: Data NIP/NIS tidak sesuai dengan data identitas fisik, silakan hubungi tata usaha.',
                    inputAttributes: {
                        'rows': 3,
                        'maxlength': 255,
                    },
                    inputValidator: (value) => {
                        if (!value || value.trim().length < 5) {
                            return 'Alasan penolakan minimal 5 karakter.';
                        }
                        if (value.trim().length > 255) {
                            return 'Alasan penolakan maksimal 255 karakter.';
                        }
                    },
                    showCancelButton: true,
                    confirmButtonText: 'Tolak Pengajuan',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#dc2626',
                });

                if (!reason) return;

                button.disabled = true;
                showProses('Menolak pengajuan...');

                try {
                    const res = await fetch(button.form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ rejection_reason: reason.trim() }),
                    });

                    const data = await res.json().catch(() => null);

                    if (!res.ok || data?.status !== 'success') {
                        throw new Error(data?.message || 'Terjadi kesalahan.');
                    }

                    hideProses();

                    await Swal.fire({
                        icon: 'success',
                        title: 'Pengajuan Ditolak',
                        text: data.message,
                        showConfirmButton: false,
                        timer: 1600,
                        timerProgressBar: true,
                    });

                    window.location.reload();
                } catch (err) {
                    hideProses();
                    button.disabled = false;
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Menolak',
                        text: err.message,
                        confirmButtonText: 'Mengerti',
                    });
                }
            });
        });
    });
</script>
@endpush
