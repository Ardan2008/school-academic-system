@extends('layouts.dashboard')

@section('title', 'Rekap Nilai')
@section('subtitle', 'Dashboard Guru')

@section('content')
    @php
        $jumlahKolomMapel = $mapelDiampu->count();
        // No, NIS, Nama, Kelas, Jurusan + kolom tiap mapel + rata-rata, nilai tercatat, tuntas, belum tuntas.
        $kolomSiswa = 5 + $jumlahKolomMapel + 4;

        $filterLabel = 'mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400';
        $filterSelect = 'h-10 w-full appearance-none rounded-lg border border-slate-300 bg-white pl-3 pr-9 text-sm text-slate-800 outline-none transition focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20';

        $kartu = [
            ['label' => 'Nilai Tercatat', 'value' => $ringkasan['jumlah_nilai'], 'icon' => 'lucide-clipboard-list', 'tone' => 'bg-blue-100 text-primary-60', 'accent' => '#1E86FF', 'decimals' => 0],
            ['label' => 'Rata-Rata', 'value' => $ringkasan['rata_rata'], 'icon' => 'lucide-chart-no-axes-column', 'tone' => 'bg-sky-100 text-sky-600', 'accent' => '#0284C7', 'decimals' => 1],
            ['label' => 'Tuntas', 'value' => $ringkasan['tuntas'], 'icon' => 'lucide-circle-check-big', 'tone' => 'bg-emerald-100 text-emerald-600', 'accent' => '#059669', 'decimals' => 0],
            ['label' => 'Belum Tuntas', 'value' => $ringkasan['belum_tuntas'], 'icon' => 'lucide-triangle-alert', 'tone' => 'bg-rose-100 text-rose-600', 'accent' => '#E11D48', 'decimals' => 0],
        ];
    @endphp

    <div id="rekap-nilai-page" data-dashboard class="space-y-5 sm:space-y-6">
        <x-dashboard.animations />

        @if ($mapelDiampu->isEmpty())
            <div data-reveal style="--d: 0s" class="rounded-2xl border border-amber-200/70 bg-amber-50/60 p-5 shadow-sm sm:p-6">
                <div class="flex items-start gap-3.5">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                        <x-lucide-alert-circle class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-amber-900">Mata Pelajaran Belum Ditugaskan</h3>
                        <p class="mt-1 max-w-2xl text-sm leading-relaxed text-amber-700">
                            Akun Anda belum dihubungkan ke mata pelajaran, jadi belum ada nilai yang bisa direkap.
                            Hubungi administrator untuk penugasan mata pelajaran.
                        </p>
                    </div>
                </div>
            </div>
        @else
            <div data-reveal style="--d: 0s" class="flex flex-wrap items-center gap-2">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Mata Pelajaran Diampu</span>
                @foreach ($mapelDiampu as $mapel)
                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-medium text-primary-70 ring-1 ring-blue-100">
                        <x-lucide-book-open class="h-3.5 w-3.5" />
                        {{ $mapel->nama }}
                    </span>
                @endforeach
            </div>

            <form method="GET" action="{{ route('dashboard.guru.rekap-nilai.index') }}"
                  data-reveal style="--d: .05s"
                  class="rounded-2xl border border-slate-100 bg-white p-4 shadow-md shadow-slate-200/60 sm:p-5">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <label class="block">
                        <span class="{{ $filterLabel }}">Tahun Ajaran</span>
                        <div class="relative">
                            <select name="tahun_ajaran" class="{{ $filterSelect }}">
                                @foreach ($tahunAjarans as $tahun)
                                    <option value="{{ $tahun }}" @selected($filter['tahun_ajaran'] === $tahun)>{{ $tahun }}</option>
                                @endforeach
                            </select>
                            <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                        </div>
                    </label>

                    <label class="block">
                        <span class="{{ $filterLabel }}">Semester</span>
                        <div class="relative">
                            <select name="semester" class="{{ $filterSelect }}">
                                @foreach ($semesters as $semester)
                                    <option value="{{ $semester }}" @selected($filter['semester'] === $semester)>{{ $semester }}</option>
                                @endforeach
                            </select>
                            <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                        </div>
                    </label>

                    <label class="block">
                        <span class="{{ $filterLabel }}">Kelas</span>
                        <div class="relative">
                            <select name="kelas" class="{{ $filterSelect }}">
                                <option value="">Semua Kelas</option>
                                @foreach ($kelasOptions as $kelas)
                                    <option value="{{ $kelas['kelas'] }}" @selected($filter['kelas'] === $kelas['kelas'])>
                                        {{ $kelas['kelas'] }}{{ $kelas['jurusan'] ? ' · '.$kelas['jurusan'] : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                        </div>
                    </label>

                    <label class="block">
                        <span class="{{ $filterLabel }}">Pencarian</span>
                        <div class="relative">
                            <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input type="search" name="cari" value="{{ $filter['cari'] }}" maxlength="100"
                                placeholder="Nama atau NIS siswa"
                                class="h-10 w-full rounded-lg border border-slate-300 bg-white pl-10 pr-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20">
                        </div>
                    </label>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                    <p class="text-[11px] text-slate-400">Terapkan filter untuk memperbarui seluruh tabel di bawah.</p>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('dashboard.guru.rekap-nilai.index') }}"
                           class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                            <x-lucide-rotate-ccw class="h-3.5 w-3.5" />
                            Reset
                        </a>
                        <button type="submit"
                                class="inline-flex h-9 items-center gap-2 rounded-lg bg-primary-60 px-4 text-xs font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-primary-70 active:scale-[.98]">
                            <x-lucide-filter class="h-3.5 w-3.5" />
                            Terapkan
                        </button>
                    </div>
                </div>
            </form>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-4">
                @foreach ($kartu as $item)
                    <div data-reveal style="--d: {{ .1 + $loop->index * .05 }}s">
                        <x-ui.stat-card
                            :label="$item['label']"
                            :value="$item['value']"
                            :icon="$item['icon']"
                            :tone="$item['tone']"
                            :accent="$item['accent']"
                            :decimals="$item['decimals']"
                            :count="true"
                        />
                    </div>
                @endforeach
            </div>

            @php $adaPerkembangan = count($perkembangan['label']) > 0; @endphp

            {{-- Baris 1: grafik perkembangan (2/3) + rekap per mata pelajaran (1/3). --}}
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                @if ($adaPerkembangan)
                    <div data-reveal style="--d: .3s" class="flex flex-col rounded-2xl border border-slate-100 bg-white p-4 shadow-md shadow-slate-200/60 sm:p-5 lg:col-span-2">
                        <h3 class="text-sm font-semibold text-slate-900">Perkembangan Rata-Rata Nilai</h3>
                        <x-dashboard.bar-chart
                            id="rekap-perkembangan"
                            :bars="collect($perkembangan['label'])->map(fn ($label, $i) => [
                                'label' => $label,
                                'value' => $perkembangan['rata_rata'][$i],
                            ])->all()"
                            rank-noun="periode"
                            value-label="Rata-rata nilai"
                            bar-width="fill"
                            empty-text="Belum ada nilai pada periode ini."
                        />
                    </div>
                @endif

                <div data-region="mapel" data-reveal style="--d: .35s"
                     class="flex flex-col rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60 {{ $adaPerkembangan ? 'lg:col-span-1' : 'lg:col-span-3' }}">
                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-slate-900">Rekap per Mata Pelajaran</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                @if ($perMapel->total() > 0)
                                    Menampilkan {{ $perMapel->firstItem() }}–{{ $perMapel->lastItem() }} dari {{ $perMapel->total() }} mata pelajaran.
                                @else
                                    Rekap nilai tiap mata pelajaran pada periode ini.
                                @endif
                            </p>
                        </div>
                        <a href="{{ route('dashboard.guru.rekap-nilai.export-mata-pelajaran', $filter) }}"
                           class="inline-flex shrink-0 items-center gap-1.5 rounded-xl border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-blue-600 transition hover:border-blue-300 hover:bg-blue-50">
                            <x-lucide-download class="h-3.5 w-3.5" />
                            Ekspor XLSX
                        </a>
                    </div>
                    <div class="flex-1 divide-y divide-slate-100 border-t border-slate-100">
                        @forelse ($perMapel as $row)
                            <div class="p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900" title="{{ $row['nama'] }}">{{ $row['nama'] }}</p>
                                        <p class="mt-0.5 text-[11px] text-slate-400">
                                            {{ $row['kode'] ?: 'Tanpa kode' }} · {{ $row['jumlah_siswa'] }} siswa · {{ $row['jumlah_nilai'] }} nilai
                                        </p>
                                    </div>
                                    <p class="shrink-0 text-lg font-bold tabular-nums text-slate-900">{{ $row['rata_rata'] }}</p>
                                </div>
                                <div class="mt-2 flex items-center gap-2 text-[11px]">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-600">
                                        Tuntas {{ $row['tuntas'] }}
                                    </span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 font-semibold text-rose-600">
                                        Belum {{ $row['belum_tuntas'] }}
                                    </span>
                                    <span class="ml-auto font-medium text-slate-500">{{ $row['persen_tuntas'] }}% tuntas</span>
                                </div>
                                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-primary-60" style="width: {{ $row['persen_tuntas'] }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="px-4 py-12 text-center text-sm text-slate-500">Belum ada nilai pada periode ini.</p>
                        @endforelse
                    </div>

                    <x-ui.table-footer :paginator="$perMapel" label="mata pelajaran" :ringkasan="$ringkasanMapel ?? null" />
                </div>
            </div>

            {{-- Baris 2: rekap per kelas + rekap per siswa. --}}
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <div data-region="kelas" data-reveal style="--d: .4s" class="flex flex-col rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60">
                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-slate-900">Rekap per Kelas</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                @if ($perKelas->total() > 0)
                                    Menampilkan {{ $perKelas->firstItem() }}–{{ $perKelas->lastItem() }} dari {{ $perKelas->total() }} kelas.
                                @else
                                    Rekap nilai tiap kelas pada periode ini.
                                @endif
                            </p>
                        </div>
                        <a href="{{ route('dashboard.guru.rekap-nilai.export-kelas', $filter) }}"
                           class="inline-flex shrink-0 items-center gap-1.5 rounded-xl border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-blue-600 transition hover:border-blue-300 hover:bg-blue-50">
                            <x-lucide-download class="h-3.5 w-3.5" />
                            Ekspor XLSX
                        </a>
                    </div>
                    <div class="flex-1 overflow-x-auto border-t border-slate-100">
                        <table class="w-full min-w-[640px] text-left text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                    <th class="px-4 py-4 text-center font-semibold">No</th>
                                    <th class="px-4 py-4 font-semibold">Kelas</th>
                                    <th class="px-4 py-4 font-semibold">Jurusan</th>
                                    <th class="px-4 py-4 font-semibold">Siswa</th>
                                    <th class="px-4 py-4 font-semibold">Nilai</th>
                                    <th class="px-4 py-4 font-semibold">Rata-Rata</th>
                                    <th class="px-4 py-4 font-semibold">Tuntas</th>
                                    <th class="px-4 py-4 font-semibold">Belum</th>
                                    <th class="px-4 py-4 font-semibold">% Tuntas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-800">
                                @forelse ($perKelas as $row)
                                    <tr class="transition hover:bg-slate-50/70">
                                        <td class="px-4 py-3.5 text-center text-slate-500">{{ $perKelas->firstItem() + $loop->index }}</td>
                                        <td class="px-4 py-3.5 font-medium text-slate-900">{{ $row['kode'] }}</td>
                                        <td class="px-4 py-3.5 text-slate-600">{{ $row['nama'] }}</td>
                                        <td class="px-4 py-3.5 text-slate-600">{{ $row['jumlah_siswa'] }}</td>
                                        <td class="px-4 py-3.5 text-slate-600">{{ $row['jumlah_nilai'] }}</td>
                                        <td class="px-4 py-3.5 font-semibold text-slate-800">{{ $row['rata_rata'] }}</td>
                                        <td class="px-4 py-3.5 text-emerald-600">{{ $row['tuntas'] }}</td>
                                        <td class="px-4 py-3.5 text-rose-600">{{ $row['belum_tuntas'] }}</td>
                                        <td class="px-4 py-3.5 text-slate-600">{{ $row['persen_tuntas'] }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-6 py-16 text-center text-sm text-slate-500">Belum ada nilai pada periode ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <x-ui.table-footer :paginator="$perKelas" label="kelas" :ringkasan="$ringkasanKelas ?? null" />
                </div>

                <div data-region="siswa" data-reveal style="--d: .45s" class="flex flex-col rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60">
                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-slate-900">Rekap per Siswa</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                @if ($perSiswa->total() > 0)
                                    Menampilkan {{ $perSiswa->firstItem() }}–{{ $perSiswa->lastItem() }} dari {{ $perSiswa->total() }} siswa.
                                @else
                                    Daftar nilai tiap siswa pada periode ini.
                                @endif
                            </p>
                        </div>
                        <a href="{{ route('dashboard.guru.rekap-nilai.export-siswa', $filter) }}"
                           class="inline-flex shrink-0 items-center gap-1.5 rounded-xl border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-blue-600 transition hover:border-blue-300 hover:bg-blue-50">
                            <x-lucide-download class="h-3.5 w-3.5" />
                            Ekspor XLSX
                        </a>
                    </div>

                    <div class="flex-1 overflow-x-auto border-t border-slate-100">
                        <table class="w-full min-w-[720px] text-left text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                    <th class="px-4 py-4 text-center font-semibold">No</th>
                                    <th class="px-4 py-4 font-semibold">NIS</th>
                                    <th class="px-4 py-4 font-semibold">Nama</th>
                                    <th class="px-4 py-4 font-semibold">Kelas</th>
                                    <th class="px-4 py-4 font-semibold">Jurusan</th>
                                    @foreach ($mapelDiampu as $mapel)
                                        <th class="px-4 py-4 text-center font-semibold">{{ $mapel->nama }}</th>
                                    @endforeach
                                    <th class="px-4 py-4 text-center font-semibold">Rata-Rata</th>
                                    <th class="px-4 py-4 text-center font-semibold">Nilai</th>
                                    <th class="px-4 py-4 text-center font-semibold">Tuntas</th>
                                    <th class="px-4 py-4 text-center font-semibold">Belum</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-800">
                                @forelse ($perSiswa as $row)
                                    <tr class="transition hover:bg-slate-50/70">
                                        <td class="px-4 py-3.5 text-center text-slate-500">{{ $perSiswa->firstItem() + $loop->index }}</td>
                                        <td class="px-4 py-3.5 font-mono text-xs text-slate-500">{{ $row['nis'] ?: '-' }}</td>
                                        <td class="px-4 py-3.5 font-medium text-slate-900">{{ $row['nama'] }}</td>
                                        <td class="px-4 py-3.5 text-slate-600">{{ $row['kelas'] }}</td>
                                        <td class="px-4 py-3.5 text-slate-600">{{ $row['jurusan'] ?: '—' }}</td>
                                        @foreach ($mapelDiampu as $mapel)
                                            @php $nilaiSiswa = $row['nilai'][(int) $mapel->id] ?? null; @endphp
                                            <td class="px-4 py-3.5 text-center tabular-nums">
                                                @if ($nilaiSiswa !== null)
                                                    <span class="font-semibold {{ $nilaiSiswa->tuntas() ? 'text-emerald-600' : 'text-rose-600' }}">
                                                        {{ $nilaiSiswa->nilai }}
                                                    </span>
                                                @else
                                                    <span class="text-slate-300">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="px-4 py-3.5 text-center font-semibold tabular-nums text-slate-800">{{ $row['rata_rata'] ?? '—' }}</td>
                                        <td class="px-4 py-3.5 text-center tabular-nums text-slate-600">{{ $row['jumlah_nilai'] }}</td>
                                        <td class="px-4 py-3.5 text-center tabular-nums text-emerald-600">{{ $row['tuntas'] }}</td>
                                        <td class="px-4 py-3.5 text-center tabular-nums text-rose-600">{{ $row['belum_tuntas'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $kolomSiswa }}" class="px-6 py-16 text-center text-sm text-slate-500">
                                            @if ($filter['cari'] !== '')
                                                Tidak ada siswa yang cocok dengan pencarian "{{ $filter['cari'] }}".
                                            @else
                                                Belum ada nilai pada periode ini.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <x-ui.table-footer :paginator="$perSiswa" label="siswa" />
                </div>
            </div>
        @endif
    </div>
@endsection
