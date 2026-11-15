@extends('layouts.dashboard')

@section('title', 'Jadwal Mengajar')
@section('subtitle', 'Dashboard Guru')

@section('content')
    <div id="jadwal-guru-page">
        @php
            $statTone = [
                'total' => ['bg-blue-100 text-primary-60', '#1E86FF'],
                'kelas' => ['bg-emerald-100 text-emerald-600', '#059669'],
                'mapel' => ['bg-violet-100 text-violet-600', '#7C3AED'],
                'hari_ini' => ['bg-amber-100 text-amber-600', '#D97706'],
            ];
        @endphp

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-4">
            @foreach ($stats as $stat)
                @php
                    $statPill = $statTone[$stat['key']][0] ?? 'bg-blue-100 text-primary-60';
                    $statAccent = $statTone[$stat['key']][1] ?? '#1E86FF';
                @endphp
                <x-ui.stat-card
                    :label="$stat['label']"
                    :value="$stat['value']"
                    :icon="$stat['icon']"
                    :tone="$statPill"
                    :accent="$statAccent"
                    :stat-key="$stat['key']"
                    layout="stacked"
                />
            @endforeach
        </div>

        <form method="GET" action="{{ route('dashboard.guru.jadwal-pelajaran.index') }}"
              class="mt-5 flex flex-col gap-2 rounded-2xl border border-slate-100 bg-white p-3 shadow-sm sm:flex-row sm:items-center sm:p-4">
            <label for="filter-hari" class="text-xs font-medium text-slate-600">Tampilkan hari</label>
            <select id="filter-hari" name="hari"
                    class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">
                <option value="">Semua Hari</option>
                @foreach (\App\Models\JadwalPelajaran::HARI as $namaHari)
                    <option value="{{ $namaHari }}" @selected($hari === $namaHari)>{{ $namaHari }}</option>
                @endforeach
            </select>

            <button type="submit"
                    class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-primary-60 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-70">
                <x-lucide-search class="h-4 w-4" />
                Tampilkan
            </button>

            @if ($hari !== null)
                <a href="{{ route('dashboard.guru.jadwal-pelajaran.index') }}"
                   class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                    <x-lucide-rotate-ccw class="h-4 w-4" />
                    Reset
                </a>
            @endif
        </form>

        @if ($jadwal->isEmpty())
            <div class="mt-5 flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-white/70 px-6 py-16 text-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-10 text-primary-70">
                    <x-lucide-calendar-days class="h-7 w-7" />
                </span>
                <h3 class="mt-4 text-base font-semibold text-slate-900">Belum Ada Jadwal Mengajar</h3>
                <p class="mt-1.5 max-w-md text-sm leading-relaxed text-slate-500">
                    @if ($hari !== null)
                        Tidak ada jadwal pada hari {{ $hari }}.
                    @else
                        Jadwal mengajar belum diatur oleh administrator. Hubungi admin untuk melengkapi jadwal Anda.
                    @endif
                </p>
            </div>
        @else
            <div class="mt-5 space-y-4">
                @foreach ($perHari as $namaHari => $items)
                    @continue($hari !== null && $hari !== $namaHari)

                    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3.5 sm:px-5">
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-10 text-primary-70">
                                    <x-lucide-calendar-days class="h-4 w-4" />
                                </span>
                                <h2 class="text-sm font-semibold text-slate-900">{{ $namaHari }}</h2>
                                @if ($hariIni === $namaHari)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Hari ini</span>
                                @endif
                            </div>
                            <span class="text-xs text-slate-500">{{ $items->count() }} jadwal</span>
                        </div>

                        @if ($items->isEmpty())
                            <p class="px-4 py-8 text-center text-sm text-slate-500 sm:px-5">Tidak ada jadwal pada hari ini.</p>
                        @else
                            <div class="hidden overflow-x-auto md:block">
                                <table class="w-full text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500">
                                            <th class="w-14 px-4 py-3 font-medium">No</th>
                                            <th class="w-40 px-4 py-3 font-medium">Jam</th>
                                            <th class="px-4 py-3 font-medium">Mata Pelajaran</th>
                                            <th class="px-4 py-3 font-medium">Kelas</th>
                                            <th class="w-36 px-4 py-3 font-medium">Ruang</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-slate-800">
                                        @foreach ($items as $item)
                                            <tr class="transition hover:bg-blue-50/40">
                                                <td class="px-4 py-3.5 text-center text-xs text-slate-500">{{ $loop->iteration }}</td>
                                                <td class="px-4 py-3.5 tabular-nums font-medium text-slate-700">{{ $item->jamLabel() }}</td>
                                                <td class="px-4 py-3.5">
                                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-10 px-2.5 py-0.5 text-xs font-medium text-primary-70">
                                                        {{ $item->mataPelajaran?->nama ?? '—' }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3.5 text-slate-600">
                                                    {{ $item->kelas }}@if (filled($item->jurusan)) · {{ $item->jurusan }}@endif
                                                </td>
                                                <td class="px-4 py-3.5 text-slate-600">{{ filled($item->ruang) ? $item->ruang : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="divide-y divide-slate-100 md:hidden">
                                @foreach ($items as $item)
                                    <div class="px-4 py-3.5">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="font-medium text-slate-900">{{ $item->mataPelajaran?->nama ?? '—' }}</p>
                                                <p class="mt-0.5 text-xs text-slate-500">
                                                    {{ $item->kelas }}@if (filled($item->jurusan)) · {{ $item->jurusan }}@endif
                                                </p>
                                            </div>
                                            <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium tabular-nums text-slate-600">
                                                {{ $item->jamLabel() }}
                                            </span>
                                        </div>
                                        @if (filled($item->ruang))
                                            <p class="mt-1 text-xs text-slate-500">Ruang {{ $item->ruang }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
