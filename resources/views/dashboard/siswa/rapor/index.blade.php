@extends('layouts.dashboard')

@section('title', 'Rapor')
@section('subtitle', 'Dashboard Siswa')

@section('content')
    <div id="rapor-page">

        {{-- Judul halaman + identitas rombel siswa --}}
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0 print:hidden">
                <h2 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Rapor</h2>
                <p class="mt-1 text-sm text-slate-500">Hasil belajar Anda pada periode yang dipilih.</p>
            </div>

            <div class="flex items-center gap-2 print:hidden">
                @if (filled($siswa->kelas))
                    <span class="inline-flex w-fit items-center gap-2 rounded-xl border border-blue-100 bg-blue-50 px-3.5 py-2 text-xs font-medium text-primary-70">
                        <x-lucide-school class="h-4 w-4 shrink-0" />
                        <span class="truncate" title="Kelas {{ $siswa->kelas }}@if (filled($siswa->jurusan)) · {{ $siswa->jurusan }}@endif">
                            Kelas {{ $siswa->kelas }}@if (filled($siswa->jurusan)) · {{ $siswa->jurusan }}@endif
                        </span>
                    </span>
                @endif

                @if ($nilai->isNotEmpty())
                    <button type="button" onclick="window.print()"
                            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                        <x-lucide-printer class="h-4 w-4" />
                        Cetak
                    </button>
                @endif
            </div>
        </div>

        {{-- Kop cetak: tersembunyi di layar, tampil saat dicetak. --}}
        <div class="mb-4 hidden print:block">
            <div class="flex flex-wrap items-end justify-between gap-2 border-b border-slate-300 pb-2">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Rapor Hasil Belajar</h2>
                    <p class="text-xs text-slate-500">SASKANSA — Sistem Akademik Sekolah</p>
                </div>
                <p class="text-xs font-medium text-slate-600">{{ $periodeLabel }}</p>
            </div>
        </div>

        {{-- Identitas siswa + filter periode --}}
        <div class="mb-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-md shadow-slate-200/60 sm:p-6">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-slate-900">Identitas Siswa</h3>
                    <dl class="mt-3 grid grid-cols-2 gap-x-8 gap-y-2 text-sm">
                        <dt class="text-slate-400">Nama</dt>
                        <dd class="truncate font-medium text-slate-800" title="{{ $siswa->name }}">{{ $siswa->name }}</dd>
                        <dt class="text-slate-400">NIS</dt>
                        <dd class="font-medium text-slate-800">{{ $siswa->nis ?? '—' }}</dd>
                        <dt class="text-slate-400">Kelas</dt>
                        <dd class="font-medium text-slate-800">{{ $siswa->kelas ?? '—' }}{{ filled($siswa->jurusan) ? ' · '.$siswa->jurusan : '' }}</dd>
                    </dl>
                </div>

                <form method="GET" class="flex flex-wrap items-end gap-3 print:hidden">
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-slate-500">Tahun Ajaran</span>
                        <select name="tahun_ajaran" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 transition focus:border-primary-40 focus:outline-none focus:ring-2 focus:ring-primary-60/20">
                            @foreach ($tahunAjarans as $tahun)
                                <option value="{{ $tahun }}" @selected($periode['tahun_ajaran'] === $tahun)>{{ $tahun }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-slate-500">Semester</span>
                        <select name="semester" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 transition focus:border-primary-40 focus:outline-none focus:ring-2 focus:ring-primary-60/20">
                            @foreach ($semesters as $semester)
                                <option value="{{ $semester }}" @selected($periode['semester'] === $semester)>{{ $semester }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary-60 px-4 py-2 text-sm font-medium text-white shadow-sm shadow-primary-60/25 transition hover:bg-primary-70 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60">
                        <x-lucide-filter class="h-4 w-4" />
                        Tampilkan
                    </button>
                </form>
            </div>
        </div>

        @if ($nilai->isEmpty())
            {{-- Belum ada nilai pada periode yang dipilih. --}}
            <div class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-white/70 px-6 py-16 text-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-10 text-primary-70">
                    <x-lucide-award class="h-7 w-7" />
                </span>
                <h3 class="mt-4 text-base font-semibold text-slate-900">Rapor Belum Tersedia</h3>
                <p class="mt-1.5 max-w-md text-sm leading-relaxed text-slate-500">
                    Belum ada nilai tercatat untuk {{ $periodeLabel }}. Coba pilih tahun ajaran atau semester lain di atas.
                </p>
            </div>
        @else
            {{-- Kartu ringkasan periode --}}
            @php
                $statTone = [
                    'jumlah_mapel' => ['bg-violet-100 text-violet-600', '#7C3AED'],
                    'rata_rata' => ['bg-blue-100 text-primary-60', '#1E86FF'],
                    'tuntas' => ['bg-emerald-100 text-emerald-600', '#059669'],
                    'belum_tuntas' => ['bg-rose-100 text-rose-600', '#E11D48'],
                ];
                $stats = [
                    ['key' => 'jumlah_mapel', 'label' => 'Mata Pelajaran', 'icon' => 'lucide-book-open', 'value' => $ringkasan['jumlah_mapel'], 'catatan' => 'Dinilai pada periode ini'],
                    ['key' => 'rata_rata', 'label' => 'Rata-Rata Nilai', 'icon' => 'lucide-trending-up', 'value' => $ringkasan['rata_rata'] !== null ? number_format($ringkasan['rata_rata'], 2, ',', '.') : null, 'catatan' => $periodeLabel],
                    ['key' => 'tuntas', 'label' => 'Tuntas', 'icon' => 'lucide-circle-check', 'value' => $ringkasan['tuntas'], 'catatan' => $ringkasan['persen_tuntas'] !== null ? $ringkasan['persen_tuntas'].'% ketuntasan' : null],
                    ['key' => 'belum_tuntas', 'label' => 'Belum Tuntas', 'icon' => 'lucide-alert-circle', 'value' => $ringkasan['belum_tuntas'], 'catatan' => 'Di bawah KKM'],
                ];
            @endphp

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-4 print:hidden">
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

            {{-- Daftar nilai periode terpilih --}}
            <div class="mt-5 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60 sm:mt-6">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3.5 sm:px-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-60 text-white">
                            <x-lucide-award class="h-4.5 w-4.5" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-slate-900">Daftar Nilai</h3>
                            <p class="text-xs text-slate-500">{{ $ringkasan['jumlah_mapel'] }} mata pelajaran · {{ $ringkasan['tuntas'] }} tuntas</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 ring-1 ring-slate-100">
                        <x-lucide-calendar-days class="h-3.5 w-3.5 text-slate-400" />
                        {{ $periodeLabel }}
                    </span>
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
                            @foreach ($nilai as $i => $baris)
                                <tr class="transition hover:bg-blue-50/40">
                                    <td class="px-4 py-3.5 text-center text-xs text-slate-500">{{ $i + 1 }}</td>
                                    <td class="px-4 py-3.5 font-medium text-slate-900">{{ $baris->mataPelajaran?->nama ?? '—' }}</td>
                                    <td class="px-4 py-3.5 text-center tabular-nums text-slate-600">{{ $baris->kkm }}</td>
                                    <td class="px-4 py-3.5 text-center text-base font-bold tabular-nums text-slate-900">{{ $baris->nilai }}</td>
                                    <td class="px-4 py-3.5">
                                        @if ($baris->tuntas())
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
                                <td colspan="3" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Rata-Rata</td>
                                <td colspan="2" class="px-4 py-3 text-left text-base font-bold tabular-nums text-slate-900">{{ number_format($ringkasan['rata_rata'], 2, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- Kartu (di bawah md) --}}
                <div class="divide-y divide-slate-100 md:hidden">
                    @foreach ($nilai as $baris)
                        <div class="flex items-start justify-between gap-3 px-4 py-3.5">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-slate-900" title="{{ $baris->mataPelajaran?->nama ?? '—' }}">{{ $baris->mataPelajaran?->nama ?? '—' }}</p>
                                <span @class([
                                    'mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium',
                                    'bg-emerald-50 text-emerald-600' => $baris->tuntas(),
                                    'bg-rose-50 text-rose-600' => ! $baris->tuntas(),
                                ])>
                                    <span @class([
                                        'h-1.5 w-1.5 rounded-full',
                                        'bg-emerald-500' => $baris->tuntas(),
                                        'bg-rose-500' => ! $baris->tuntas(),
                                    ])></span>
                                    {{ $baris->tuntas() ? 'Tuntas' : 'Belum Tuntas' }}
                                </span>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-lg font-bold tabular-nums text-slate-900">{{ $baris->nilai }}</p>
                                <p class="text-[11px] text-slate-400">KKM {{ $baris->kkm }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        @page {
            margin: 10mm;
        }

        @media print {
            /* Chrome & Firefox tidak otomatis mencetak latar kartu dan badge. */
            #rapor-page, #rapor-page * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /* Kerangka dashboard disembunyikan agar tidak menyisakan celah di kertas. */
            #app-sidebar,
            #sidebar-backdrop,
            header,
            #logout-modal,
            #site-loading {
                display: none !important;
            }

            main {
                max-width: none;
                padding: 0;
            }
        }
    </style>
@endpush
