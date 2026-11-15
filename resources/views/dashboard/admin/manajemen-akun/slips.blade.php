@extends('layouts.dashboard')

@section('title', 'Slip Password')
@section('subtitle', 'Dashboard Admin')

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h2 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Slip Password Akun</h2>
            <p class="mt-1 text-sm text-slate-500">Cara cepat membagikan kredensial login awal kepada guru dan siswa.</p>
        </div>
        <div class="flex shrink-0 items-center gap-2">
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
    @media print {
        header, aside, #sidebar-backdrop, .no-print { display: none !important; }
        body { background: white; }
        .lg\:pl-64 { padding-left: 0 !important; }
    }
</style>
@endpush