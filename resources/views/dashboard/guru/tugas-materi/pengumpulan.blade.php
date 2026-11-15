@extends('layouts.dashboard')

@section('title', 'Pengumpulan Tugas')
@section('subtitle', 'Dashboard Guru')

@section('content')
    <div id="pengumpulan-tugas-page">
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

        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('dashboard.guru.tugas-materi.index', ['tab' => 'tugas']) }}"
               class="inline-flex w-fit items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                <x-lucide-arrow-left class="h-4 w-4" />
                Kembali ke Tugas &amp; Materi
            </a>

            <span class="inline-flex w-fit items-center gap-2 rounded-xl border border-blue-100 bg-blue-50 px-3.5 py-2 text-xs font-medium text-primary-70">
                <x-lucide-school class="h-4 w-4 shrink-0" />
                Kelas {{ $tugas->kelas }}@if (filled($tugas->jurusan)) · {{ $tugas->jurusan }}@endif
            </span>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-primary-60">{{ $tugas->mataPelajaran?->nama ?? 'Mata pelajaran' }}</p>
                    <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $tugas->judul }}</h2>
                    @if (filled($tugas->deskripsi))
                        <p class="mt-1 max-w-2xl text-sm leading-relaxed text-slate-500">{{ $tugas->deskripsi }}</p>
                    @endif
                </div>

                <div class="shrink-0 rounded-xl bg-slate-50 px-3.5 py-2.5 text-xs text-slate-600">
                    <p class="font-medium text-slate-700">Tenggat</p>
                    <p class="mt-0.5">{{ $tugas->deadline ? \App\Support\Waktu::tanggalJam($tugas->deadline) : 'Tanpa tenggat' }}</p>
                    <p class="mt-1 text-slate-400">{{ $tugas->labelDeadline() }}</p>
                </div>
            </div>
        </div>

        <div class="mt-4 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60">
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <th class="w-14 px-4 py-3.5 text-center font-medium">No</th>
                            <th class="w-28 px-4 py-3.5 font-medium">NIS</th>
                            <th class="px-4 py-3.5 font-medium">Nama Siswa</th>
                            <th class="w-32 px-4 py-3.5 font-medium">Status Siswa</th>
                            <th class="w-44 px-4 py-3.5 font-medium">Dikumpulkan</th>
                            <th class="w-32 px-4 py-3.5 font-medium">Status</th>
                            <th class="w-48 px-4 py-3.5 font-medium">Nilai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-800">
                        @forelse ($siswa as $murid)
                            @php
                                $kumpul = $pengumpulan->get($murid->id);
                            @endphp
                            <tr class="transition hover:bg-blue-50/40">
                                <td class="px-4 py-3.5 text-center text-xs text-slate-500">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3.5 tabular-nums text-slate-600">{{ $murid->nis ?? '—' }}</td>
                                <td class="px-4 py-3.5 font-medium text-slate-900">{{ $murid->name }}</td>
                                <td class="px-4 py-3.5">
                                    <span @class([
                                        'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        'bg-emerald-50 text-emerald-700' => $murid->statusLabel() === \App\Models\User::STATUS_AKTIF,
                                        'bg-amber-50 text-amber-700' => $murid->statusLabel() === \App\Models\User::STATUS_LULUS,
                                        'bg-slate-100 text-slate-600' => $murid->statusLabel() === \App\Models\User::STATUS_NONAKTIF,
                                    ])>
                                        {{ $murid->statusLabel() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-slate-600">
                                    {{ $kumpul?->dikumpulkan_at ? \App\Support\Waktu::tanggalJam($kumpul->dikumpulkan_at) : '—' }}
                                </td>
                                <td class="px-4 py-3.5">
                                    @if ($kumpul === null)
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">Belum mengumpulkan</span>
                                    @elseif ($kumpul->status === \App\Models\PengumpulanTugas::STATUS_DINILAI)
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Dinilai</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-primary-70">Dikumpulkan</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    @if ($kumpul === null)
                                        <span class="text-xs text-slate-400">Menunggu pengumpulan</span>
                                    @else
                                        <form method="POST" action="{{ route('dashboard.guru.tugas-materi.pengumpulan.update', $kumpul) }}"
                                              class="flex items-center gap-2">
                                            @csrf
                                            @method('PUT')
                                            <input type="number" name="nilai" min="0" max="100" step="0.01"
                                                   value="{{ old('nilai', $kumpul->nilai) }}"
                                                   aria-label="Nilai {{ $murid->name }}"
                                                   class="h-9 w-24 rounded-lg border border-slate-200 bg-white px-2.5 text-sm text-slate-800 outline-none transition focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">
                                            <button type="submit"
                                                    class="inline-flex h-9 items-center justify-center gap-1 rounded-lg bg-primary-60 px-2.5 text-xs font-semibold text-white transition hover:bg-primary-70">
                                                <x-lucide-save class="h-3.5 w-3.5" />
                                                Simpan
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center">
                                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-10 text-primary-70">
                                        <x-lucide-users class="h-6 w-6" />
                                    </span>
                                    <h3 class="mt-3 text-sm font-semibold text-slate-900">Belum Ada Siswa</h3>
                                    <p class="mt-1 text-sm text-slate-500">Belum ada siswa pada rombel tugas ini.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-100 md:hidden">
                @forelse ($siswa as $murid)
                    @php
                        $kumpul = $pengumpulan->get($murid->id);
                    @endphp
                    <div class="px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-slate-900">{{ $murid->name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $murid->nis ?? '—' }} · {{ $murid->statusLabel() }}</p>
                            </div>
                            @if ($kumpul === null)
                                <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">Belum</span>
                            @elseif ($kumpul->status === \App\Models\PengumpulanTugas::STATUS_DINILAI)
                                <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Dinilai</span>
                            @else
                                <span class="shrink-0 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-primary-70">Dikumpulkan</span>
                            @endif
                        </div>

                        <p class="mt-2 text-xs text-slate-500">
                            Dikumpulkan: {{ $kumpul?->dikumpulkan_at ? \App\Support\Waktu::tanggalJam($kumpul->dikumpulkan_at) : '—' }}
                        </p>

                        @if ($kumpul !== null)
                            <form method="POST" action="{{ route('dashboard.guru.tugas-materi.pengumpulan.update', $kumpul) }}"
                                  class="mt-3 flex items-center gap-2">
                                @csrf
                                @method('PUT')
                                <input type="number" name="nilai" min="0" max="100" step="0.01"
                                       value="{{ old('nilai', $kumpul->nilai) }}"
                                       aria-label="Nilai {{ $murid->name }}"
                                       class="h-9 w-24 rounded-lg border border-slate-200 bg-white px-2.5 text-sm text-slate-800 outline-none transition focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">
                                <button type="submit"
                                        class="inline-flex h-9 items-center justify-center gap-1 rounded-lg bg-primary-60 px-2.5 text-xs font-semibold text-white transition hover:bg-primary-70">
                                    <x-lucide-save class="h-3.5 w-3.5" />
                                    Simpan Nilai
                                </button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="px-4 py-12 text-center">
                        <h3 class="text-sm font-semibold text-slate-900">Belum Ada Siswa</h3>
                        <p class="mt-1 text-sm text-slate-500">Belum ada siswa pada rombel tugas ini.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
