@extends('layouts.dashboard')

@section('title', 'Jadwal Pelajaran')
@section('subtitle', 'Dashboard Siswa')

@section('content')
    <x-dashboard.animations />

    <div id="jadwal-page" data-dashboard>
        @php
            $catatanPerhatian = $jadwalPerluPerhatian
                ->flatMap(fn ($baris) => $baris->infoWaktu($konfigurasiJam)['catatan'])
                ->filter()
                ->unique()
                ->values()
                ->take(4);
        @endphp

        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                @if (filled($siswa->kelas))
                    <span class="inline-flex max-w-full items-center gap-2 rounded-xl border border-blue-100 bg-blue-50 px-3.5 py-2 text-xs font-medium text-primary-70">
                        <x-lucide-school class="h-4 w-4 shrink-0" />
                        <span class="truncate" title="Kelas {{ $siswa->kelas }}@if (filled($siswa->jurusan)) · {{ $siswa->jurusan }}@endif">
                            Kelas {{ $siswa->kelas }}@if (filled($siswa->jurusan)) · {{ $siswa->jurusan }}@endif
                        </span>
                    </span>
                @endif

                <span class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-medium text-slate-600">
                    <x-lucide-calendar-days class="h-4 w-4 shrink-0 text-slate-400" />
                    {{ \App\Support\Periode::label($periode['tahun_ajaran'], $periode['semester']) }}
                </span>
            </div>
        </div>

        @if ($catatanPerhatian->isNotEmpty())
            <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-relaxed text-amber-800">
                <p class="font-semibold">
                    Ada {{ $jadwalPerluPerhatian->count() }} jadwal yang mengikuti konfigurasi jam belajar terbaru.
                </p>
                <ul class="mt-1.5 list-disc space-y-0.5 pl-4">
                    @foreach ($catatanPerhatian as $catatan)
                        <li>{{ $catatan }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (blank($siswa->kelas))
            {{-- Siswa belum punya kelas: jadwal tidak bisa ditentukan. --}}
            <div class="rounded-2xl border border-amber-200/70 bg-amber-50/60 p-4 shadow-sm sm:p-5">
                <div class="flex items-start gap-3">
                    <x-lucide-alert-circle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                    <div>
                        <h3 class="text-sm font-semibold text-amber-900">Data Kelas Belum Diatur</h3>
                        <p class="mt-0.5 text-xs leading-relaxed text-amber-700">
                            Akun Anda belum memiliki data kelas, sehingga jadwal pelajaran belum dapat ditampilkan.
                            Silakan hubungi administrator sekolah untuk melengkapi data kelas Anda.
                        </p>
                    </div>
                </div>
            </div>
        @elseif ($ringkasan['total_jam'] === 0)
            {{-- Sudah punya kelas, tetapi admin/guru belum mengisi jadwal rombel ini. --}}
            <div class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-white/70 px-6 py-16 text-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-10 text-primary-70">
                    <x-lucide-calendar-days class="h-7 w-7" />
                </span>
                <h3 class="mt-4 text-base font-semibold text-slate-900">Jadwal Pelajaran Belum Tersedia</h3>
                <p class="mt-1.5 max-w-md text-sm leading-relaxed text-slate-500">
                    Jadwal untuk kelas Anda belum diatur. Jadwal akan muncul di sini setelah ditetapkan oleh guru atau administrator sekolah.
                </p>
            </div>
        @else
            @php
                $statTone = [
                    'total_jam' => ['bg-blue-100 text-primary-60', '#1E86FF'],
                    'total_mapel' => ['bg-violet-100 text-violet-600', '#7C3AED'],
                    'hari_aktif' => ['bg-emerald-100 text-emerald-600', '#059669'],
                    'jam_belajar' => ['bg-amber-100 text-amber-600', '#D97706'],
                ];
                $stats = [
                    ['key' => 'total_jam', 'label' => 'Total Jam Pelajaran', 'icon' => 'lucide-clock', 'value' => $ringkasan['total_jam']],
                    ['key' => 'total_mapel', 'label' => 'Mata Pelajaran', 'icon' => 'lucide-book-open', 'value' => $ringkasan['total_mapel']],
                    ['key' => 'hari_aktif', 'label' => 'Hari Belajar', 'icon' => 'lucide-calendar-days', 'value' => $ringkasan['hari_aktif']],
                    ['key' => 'jam_belajar', 'label' => 'Jam Belajar', 'icon' => 'lucide-timer', 'value' => $ringkasan['jam_belajar'] ?? '—'],
                ];
            @endphp

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-4" data-reveal>
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
                        :value-class="$stat['key'] === 'jam_belajar'
                            ? 'text-base leading-tight tracking-tight whitespace-nowrap sm:text-lg xl:text-lg'
                            : ''"
                        layout="stacked"
                    />
                @endforeach
            </div>

            {{-- Filter hari (server-side lewat query string) --}}
            <div class="mt-5 flex flex-wrap items-center gap-2 sm:mt-6">
                <span class="mr-1 hidden text-xs font-semibold uppercase tracking-wide text-slate-400 sm:inline">Hari</span>

                <a href="{{ route('dashboard.siswa.jadwal-pelajaran.index') }}"
                   @class([
                        'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60',
                        'bg-primary-60 text-white shadow-sm shadow-primary-60/25' => $hariDipilih === null,
                        'border border-slate-200 bg-white text-slate-600 hover:border-primary-60 hover:bg-blue-50 hover:text-primary-70' => $hariDipilih !== null,
                   ])>
                    Semua
                </a>

                @foreach ($hariTersedia as $namaHari)
                    <a href="{{ route('dashboard.siswa.jadwal-pelajaran.index', ['hari' => $namaHari]) }}"
                       @class([
                            'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60',
                            'bg-primary-60 text-white shadow-sm shadow-primary-60/25' => $hariDipilih === $namaHari,
                            'border border-slate-200 bg-white text-slate-600 hover:border-primary-60 hover:bg-blue-50 hover:text-primary-70' => $hariDipilih !== $namaHari,
                       ])>
                        {{ $namaHari }}
                        @if ($namaHari === $hariIni)
                            <span @class([
                                'h-1.5 w-1.5 rounded-full',
                                'bg-white' => $hariDipilih === $namaHari,
                                'bg-emerald-500' => $hariDipilih !== $namaHari,
                            ])></span>
                        @endif
                    </a>
                @endforeach
            </div>

            <div class="mt-5 space-y-5 sm:mt-6">
                @forelse ($jadwal as $namaHari => $items)
                    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60" data-reveal>

                        {{-- Kepala hari --}}
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3.5 sm:px-5">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-60 text-white">
                                    <x-lucide-calendar-days class="h-4.5 w-4.5" />
                                </span>
                                <div class="min-w-0">
                                    <h3 class="truncate text-sm font-semibold text-slate-900" title="{{ $namaHari }}">{{ $namaHari }}</h3>
                                    <p class="text-xs text-slate-500">{{ $items->count() }} jam pelajaran</p>
                                </div>
                            </div>

                            @if ($namaHari === $hariIni)
                                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    Hari Ini
                                </span>
                            @endif
                        </div>

                        {{-- Tabel (md ke atas) --}}
                        <div class="hidden overflow-x-auto md:block">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                        <th class="w-16 px-4 py-3.5 text-center font-medium">No</th>
                                        <th class="w-40 px-4 py-3.5 font-medium">Jam</th>
                                        <th class="px-4 py-3.5 font-medium">Mata Pelajaran</th>
                                        <th class="px-4 py-3.5 font-medium">Guru Pengampu</th>
                                        <th class="w-28 px-4 py-3.5 font-medium">Ruang</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-800">
                                    @foreach ($items as $i => $baris)
                                        <tr class="transition hover:bg-blue-50/40">
                                            <td class="px-4 py-3.5 text-center text-xs text-slate-500">{{ $i + 1 }}</td>
                                            @php($waktu = $baris->infoWaktu($konfigurasiJam))
                                            <td class="px-4 py-3.5">
                                                <span @class([
                                                    'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium tabular-nums',
                                                    'bg-primary-10 text-primary-70' => $waktu['catatan'] === [],
                                                    'bg-amber-50 text-amber-700' => $waktu['catatan'] !== [],
                                                ]) title="{{ implode(' ', $waktu['catatan']) }}">
                                                    <x-lucide-clock class="h-3.5 w-3.5" />
                                                    {{ $waktu['label'] }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3.5 font-medium text-slate-900">
                                                {{ $baris->mataPelajaran?->nama ?? '—' }}
                                            </td>
                                            <td class="px-4 py-3.5 text-slate-600">
                                                {{ $baris->guru?->name ?? '—' }}
                                            </td>
                                            <td class="px-4 py-3.5">
                                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                                    <x-lucide-map-pin class="h-3.5 w-3.5 text-slate-400" />
                                                    {{ $baris->ruang ?? '—' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Kartu (di bawah md) --}}
                        <div class="divide-y divide-slate-100 md:hidden">
                            @foreach ($items as $baris)
                                <div class="flex items-start justify-between gap-3 px-4 py-3.5">
                                    <div class="min-w-0">
                                        @php($waktu = $baris->infoWaktu($konfigurasiJam))
                                        <p @class([
                                            'inline-flex items-center gap-1.5 text-xs font-medium tabular-nums',
                                            'text-primary-70' => $waktu['catatan'] === [],
                                            'text-amber-700' => $waktu['catatan'] !== [],
                                        ]) title="{{ implode(' ', $waktu['catatan']) }}">
                                            <x-lucide-clock class="h-3.5 w-3.5" />
                                            {{ $waktu['label'] }}
                                        </p>
                                        <p class="mt-1 truncate font-medium text-slate-900" title="{{ $baris->mataPelajaran?->nama ?? '—' }}">
                                            {{ $baris->mataPelajaran?->nama ?? '—' }}
                                        </p>
                                        <p class="mt-0.5 flex items-center gap-1.5 truncate text-xs text-slate-500" title="{{ $baris->guru?->name ?? 'Belum ada guru pengampu' }}">
                                            <x-lucide-user-round class="h-3.5 w-3.5 shrink-0 text-slate-400" />
                                            {{ $baris->guru?->name ?? 'Belum ada guru pengampu' }}
                                        </p>
                                    </div>
                                    <span class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">
                                        <x-lucide-map-pin class="h-3.5 w-3.5 text-slate-400" />
                                        {{ $baris->ruang ?? '—' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-white/70 px-6 py-14 text-center">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-10 text-primary-70">
                            <x-lucide-calendar-days class="h-6 w-6" />
                        </span>
                        <h3 class="mt-4 text-sm font-semibold text-slate-900">Tidak Ada Jadwal</h3>
                        <p class="mt-1.5 max-w-md text-sm text-slate-500">
                            Tidak ada jadwal pelajaran pada hari yang dipilih.
                        </p>
                        <a href="{{ route('dashboard.siswa.jadwal-pelajaran.index') }}"
                           class="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-primary-60 transition hover:bg-slate-50">
                            <x-lucide-rotate-ccw class="h-3.5 w-3.5" />
                            Tampilkan Semua Hari
                        </a>
                    </div>
                @endforelse
            </div>
        @endif
    </div>
@endsection
