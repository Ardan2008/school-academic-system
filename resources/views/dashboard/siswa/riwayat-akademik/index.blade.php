@extends('layouts.dashboard')

@section('title', 'Riwayat Akademik')
@section('subtitle', 'Dashboard Siswa')

@section('content')
    <div id="riwayat-akademik-page">

        {{-- Judul halaman + identitas rombel siswa --}}
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            @if (filled($siswa->kelas))
                <span class="inline-flex max-w-full items-center gap-2 rounded-xl border border-blue-100 bg-blue-50 px-3.5 py-2 text-xs font-medium text-primary-70">
                    <x-lucide-school class="h-4 w-4 shrink-0" />
                    <span class="truncate" title="Kelas {{ $siswa->kelas }}@if (filled($siswa->jurusan)) · {{ $siswa->jurusan }}@endif">
                        Kelas {{ $siswa->kelas }}@if (filled($siswa->jurusan)) · {{ $siswa->jurusan }}@endif
                    </span>
                </span>
            @endif
        </div>

        @if ($riwayat->isEmpty())
            {{-- Belum ada nilai sama sekali untuk akun ini. --}}
            <div class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-white/70 px-6 py-16 text-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-10 text-primary-70">
                    <x-lucide-history class="h-7 w-7" />
                </span>
                <h3 class="mt-4 text-base font-semibold text-slate-900">Riwayat Akademik Belum Tersedia</h3>
                <p class="mt-1.5 max-w-md text-sm leading-relaxed text-slate-500">
                    Belum ada nilai yang tercatat untuk akun Anda. Riwayat akan muncul di sini setelah guru memasukkan nilai.
                </p>
            </div>
        @else
            {{-- Kartu ringkasan seluruh periode --}}
            @php
                $statTone = [
                    'total_periode' => ['bg-blue-100 text-primary-60', '#1E86FF'],
                    'rata_rata' => ['bg-emerald-100 text-emerald-600', '#059669'],
                    'total_mapel' => ['bg-violet-100 text-violet-600', '#7C3AED'],
                    'persen_tuntas' => ['bg-amber-100 text-amber-600', '#D97706'],
                ];
                $stats = [
                    ['key' => 'total_periode', 'label' => 'Periode Tercatat', 'icon' => 'lucide-history', 'value' => $ringkasan['total_periode'], 'catatan' => null],
                    ['key' => 'rata_rata', 'label' => 'Rata-Rata Keseluruhan', 'icon' => 'lucide-trending-up', 'value' => $ringkasan['rata_rata'] !== null ? number_format($ringkasan['rata_rata'], 1, ',', '.') : null, 'catatan' => $ringkasan['total_nilai'].' nilai tercatat'],
                    ['key' => 'total_mapel', 'label' => 'Mata Pelajaran', 'icon' => 'lucide-book-open', 'value' => $ringkasan['total_mapel'], 'catatan' => 'Sepanjang riwayat'],
                    ['key' => 'persen_tuntas', 'label' => 'Ketuntasan', 'icon' => 'lucide-circle-check', 'value' => $ringkasan['persen_tuntas'] !== null ? $ringkasan['persen_tuntas'].'%' : null, 'catatan' => $ringkasan['tuntas'].' dari '.$ringkasan['total_nilai'].' nilai tuntas'],
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
                        :catatan="$stat['catatan']"
                        :stat-key="$stat['key']"
                        layout="stacked"
                    />
                @endforeach
            </div>

            {{-- Grafik perkembangan rata-rata per periode --}}
            <div class="mt-5 rounded-2xl border border-slate-100 bg-white p-4 shadow-md shadow-slate-200/60 sm:mt-6 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-slate-900">Perkembangan Rata-Rata Nilai</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Rata-rata semua mata pelajaran Anda pada tiap periode.</p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                        <x-lucide-history class="h-3.5 w-3.5" />
                        {{ $ringkasan['total_periode'] }} periode
                    </span>
                </div>

                <div class="mt-4">
                    <x-dashboard.bar-chart
                        id="riwayat-perkembangan"
                        :bars="$grafik['bars']"
                        :min="$grafik['min']"
                        :y-ticks="$grafik['ticks']"
                        rank-noun="periode"
                        value-label="Rata-rata nilai"
                        bar-width="fill"
                        empty-text="Belum ada nilai untuk ditampilkan."
                    />
                </div>
            </div>

            {{-- Filter periode (server-side lewat query string) --}}
            @if (count($periodeTersedia) > 1)
                <div class="mt-5 flex flex-wrap items-center gap-2 sm:mt-6">
                    <span class="mr-1 hidden text-xs font-semibold uppercase tracking-wide text-slate-400 sm:inline">Periode</span>

                    <a href="{{ route('dashboard.siswa.riwayat-akademik.index') }}"
                       @class([
                            'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60',
                            'bg-primary-60 text-white shadow-sm shadow-primary-60/25' => $periodeDipilih === null,
                            'border border-slate-200 bg-white text-slate-600 hover:border-primary-60 hover:bg-blue-50 hover:text-primary-70' => $periodeDipilih !== null,
                       ])>
                        Semua
                    </a>

                    @foreach ($periodeTersedia as $label)
                        <a href="{{ route('dashboard.siswa.riwayat-akademik.index', ['periode' => $label]) }}"
                           @class([
                                'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60',
                                'bg-primary-60 text-white shadow-sm shadow-primary-60/25' => $periodeDipilih === $label,
                                'border border-slate-200 bg-white text-slate-600 hover:border-primary-60 hover:bg-blue-50 hover:text-primary-70' => $periodeDipilih !== $label,
                           ])>
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Daftar nilai per periode (terbaru lebih dulu) --}}
            <div class="mt-5 space-y-5 sm:mt-6">
                @foreach ($riwayat as $periode)
                    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60">

                        {{-- Kepala periode --}}
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3.5 sm:px-5">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-60 text-white">
                                    <x-lucide-calendar-days class="h-4.5 w-4.5" />
                                </span>
                                <div class="min-w-0">
                                    <h3 class="truncate text-sm font-semibold text-slate-900" title="{{ $periode['label'] }}">{{ $periode['label'] }}</h3>
                                    <p class="text-xs text-slate-500">{{ $periode['jumlah_mapel'] }} mata pelajaran · {{ $periode['tuntas'] }} tuntas</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                @if ($periode['selisih'] !== null)
                                    @php
                                        $naik = $periode['selisih'] > 0;
                                        $turun = $periode['selisih'] < 0;
                                    @endphp
                                    <span @class([
                                        'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-emerald-50 text-emerald-600' => $naik,
                                        'bg-rose-50 text-rose-600' => $turun,
                                        'bg-slate-100 text-slate-500' => ! $naik && ! $turun,
                                    ])>
                                        @if ($naik)
                                            <x-lucide-trending-up class="h-3.5 w-3.5" />
                                        @elseif ($turun)
                                            <x-lucide-trending-down class="h-3.5 w-3.5" />
                                        @else
                                            <x-lucide-minus class="h-3.5 w-3.5" />
                                        @endif
                                        {{ number_format(abs($periode['selisih']), 1, ',', '.') }}
                                    </span>
                                @endif

                                <span class="inline-flex items-center gap-2 rounded-lg bg-white px-3 py-1.5 text-xs font-medium text-slate-600 ring-1 ring-slate-100">
                                    Rata-rata
                                    <span class="text-sm font-bold tabular-nums text-slate-900">{{ number_format($periode['rata_rata'], 1, ',', '.') }}</span>
                                </span>
                            </div>
                        </div>

                        {{-- Tabel (md ke atas) --}}
                        <div class="hidden overflow-x-auto md:block">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                        <th class="w-14 px-4 py-3.5 text-center font-medium">No</th>
                                        <th class="px-4 py-3.5 font-medium">Mata Pelajaran</th>
                                        <th class="w-24 px-4 py-3.5 text-center font-medium">KKM</th>
                                        <th class="w-28 px-4 py-3.5 text-center font-medium">Nilai</th>
                                        <th class="w-40 px-4 py-3.5 font-medium">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-800">
                                    @foreach ($periode['nilai'] as $i => $baris)
                                        <tr class="transition hover:bg-blue-50/40">
                                            <td class="px-4 py-3.5 text-center text-xs text-slate-500">{{ $i + 1 }}</td>
                                            <td class="px-4 py-3.5 font-medium text-slate-900">{{ $baris['nama'] }}</td>
                                            <td class="px-4 py-3.5 text-center tabular-nums text-slate-600">{{ $baris['kkm'] }}</td>
                                            <td class="px-4 py-3.5 text-center text-base font-bold tabular-nums text-slate-900">{{ $baris['nilai'] }}</td>
                                            <td class="px-4 py-3.5">
                                                @if ($baris['tuntas'])
                                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-600">
                                                        <x-lucide-circle-check class="h-3.5 w-3.5" />
                                                        Tuntas
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-medium text-rose-600">
                                                        <x-lucide-alert-circle class="h-3.5 w-3.5" />
                                                        Belum Tuntas
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="border-t border-slate-100 bg-slate-50/60">
                                        <td colspan="3" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Rata-Rata Periode</td>
                                        <td colspan="2" class="px-4 py-3 text-left text-base font-bold tabular-nums text-slate-900">{{ number_format($periode['rata_rata'], 1, ',', '.') }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {{-- Kartu (di bawah md) --}}
                        <div class="divide-y divide-slate-100 md:hidden">
                            @foreach ($periode['nilai'] as $baris)
                                <div class="flex items-start justify-between gap-3 px-4 py-3.5">
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-slate-900" title="{{ $baris['nama'] }}">{{ $baris['nama'] }}</p>
                                        <span @class([
                                            'mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium',
                                            'bg-emerald-50 text-emerald-600' => $baris['tuntas'],
                                            'bg-rose-50 text-rose-600' => ! $baris['tuntas'],
                                        ])>
                                            <span @class([
                                                'h-1.5 w-1.5 rounded-full',
                                                'bg-emerald-500' => $baris['tuntas'],
                                                'bg-rose-500' => ! $baris['tuntas'],
                                            ])></span>
                                            {{ $baris['tuntas'] ? 'Tuntas' : 'Belum Tuntas' }}
                                        </span>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p class="text-lg font-bold tabular-nums text-slate-900">{{ $baris['nilai'] }}</p>
                                        <p class="text-[11px] text-slate-400">KKM {{ $baris['kkm'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
