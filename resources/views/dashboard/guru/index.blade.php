@extends('layouts.dashboard')

@section('title', 'Dashboard Guru')
@section('subtitle', 'Ringkasan kelas yang Anda ampu')

@php
    // Angka dihitung DashboardController via StatistikNilai; data selalu dibatasi
    // ke mata pelajaran yang diampu akun guru ini.
    $totalKelas = $totalKelas ?? 0;
    $totalSiswa = $totalSiswa ?? 0;
    $siswaAktif = $siswaAktif ?? 0;
    $mapelDiampu = $mapelDiampu ?? collect();
    $kelasDiampu = $kelasDiampu ?? collect();
    $perKelas = $perKelas ?? collect();
    $kelasTertinggi = $kelasTertinggi ?? collect();
    $perluPerhatian = $perluPerhatian ?? collect();
    $perkembangan = $perkembangan ?? ['label' => [], 'rata_rata' => []];
    $ringkasan = $ringkasan ?? [
        'rata_rata' => null,
        'jumlah_nilai' => 0,
        'jumlah_siswa' => 0,
        'tuntas' => 0,
        'belum_tuntas' => 0,
        'persen_tuntas' => null,
    ];

    $daftarSiswa = $siswaPaginasi ?? ($siswa ?? collect());

    $adaMapel = $mapelDiampu->isNotEmpty();
    $adaNilai = ($ringkasan['jumlah_nilai'] ?? 0) > 0;
    $periodeLabel = \App\Support\Periode::label($periode['tahun_ajaran'], $periode['semester']);
    $namaMapel = $mapelDiampu->pluck('nama')->filter()->unique()->implode(', ');

    $kartu = [
        [
            'label' => 'Total Siswa',
            'value' => $totalSiswa,
            'icon' => 'lucide-graduation-cap',
            'tone' => 'bg-emerald-100 text-emerald-600',
            'accent' => '#059669',
            'decimals' => 0,
            'catatan' => $totalKelas > 0 ? $siswaAktif.' siswa aktif' : 'Belum ada siswa',
        ],
        [
            'label' => 'Kelas Diampu',
            'value' => $totalKelas,
            'icon' => 'lucide-school',
            'tone' => 'bg-blue-100 text-primary-60',
            'accent' => '#1E86FF',
            'decimals' => 0,
            'catatan' => $totalKelas > 0 ? 'Kelas yang Anda ajar' : 'Belum ada kelas',
        ],
        [
            'label' => 'Mata Pelajaran',
            'value' => $mapelDiampu->count(),
            'icon' => 'lucide-book-open',
            'tone' => 'bg-amber-100 text-amber-600',
            'accent' => '#D97706',
            'decimals' => 0,
            'catatan' => $namaMapel !== '' ? $namaMapel : 'Belum ditugaskan',
        ],
        [
            'label' => 'Rata-Rata Nilai',
            'value' => $ringkasan['rata_rata'],
            'icon' => 'lucide-chart-no-axes-column',
            'tone' => 'bg-violet-100 text-violet-600',
            'accent' => '#7C3AED',
            'decimals' => 1,
            'catatan' => $adaNilai
                ? (int) ($ringkasan['persen_tuntas'] ?? 0).'% tuntas · '.$ringkasan['jumlah_nilai'].' nilai'
                : 'Belum ada nilai',
        ],
    ];

    // Warna batang mengikuti mutu nilai, senada dengan grafik lain.
    $toneNilai = function (float $nilai): array {
        if ($nilai >= 90) {
            return ['bar' => 'bg-emerald-500'];
        }

        if ($nilai >= 80) {
            return ['bar' => 'bg-primary-60'];
        }

        if ($nilai >= 75) {
            return ['bar' => 'bg-amber-500'];
        }

        return ['bar' => 'bg-rose-500'];
    };
@endphp

@section('content')
    <div id="dashboard-guru" data-dashboard class="space-y-5 sm:space-y-6">
        <x-dashboard.animations />

        @if (! $adaMapel)
            <div data-reveal style="--d: 0s" class="rounded-2xl border border-amber-200/70 bg-amber-50/60 p-5 shadow-sm sm:p-6">
                <div class="flex items-start gap-3.5">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                        <x-lucide-alert-circle class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-amber-900">Mata Pelajaran Belum Ditugaskan</h3>
                        <p class="mt-1 max-w-2xl text-sm leading-relaxed text-amber-700">
                            Akun Anda belum dihubungkan ke mata pelajaran, jadi belum ada ringkasan akademik yang
                            bisa ditampilkan. Hubungi administrator untuk penugasan mata pelajaran.
                        </p>
                    </div>
                </div>
            </div>
        @else
            {{-- Lingkup data: mata pelajaran yang diampu + periode berjalan --}}
            <div data-reveal style="--d: 0s" class="flex flex-wrap items-center gap-2">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Mata Pelajaran Diampu</span>
                @foreach ($mapelDiampu as $mapel)
                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-medium text-primary-70 ring-1 ring-blue-100">
                        <x-lucide-book-open class="h-3.5 w-3.5" />
                        {{ $mapel->nama }}
                    </span>
                @endforeach
                <span class="ml-auto inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                    <x-lucide-calendar-days class="h-3.5 w-3.5" />
                    {{ $periodeLabel }}
                </span>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-4">
                @foreach ($kartu as $item)
                    <div data-reveal style="--d: {{ .05 + $loop->index * .05 }}s">
                        <x-ui.stat-card
                            :label="$item['label']"
                            :value="$item['value']"
                            :icon="$item['icon']"
                            :tone="$item['tone']"
                            :accent="$item['accent']"
                            :decimals="$item['decimals']"
                            :catatan="$item['catatan']"
                            :count="true"
                        />
                    </div>
                @endforeach
            </div>

            {{-- Baris 1: grafik perkembangan (2/3) + peringkat kelas (1/3) --}}
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                <div data-reveal style="--d: .25s" class="flex flex-col rounded-2xl border border-slate-100 bg-white p-4 shadow-md shadow-slate-200/60 sm:p-5 lg:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-slate-900">Perkembangan Rata-Rata Nilai</h3>
                            <p class="mt-0.5 text-xs text-slate-500">Rata-rata seluruh nilai Anda pada tiap periode.</p>
                        </div>
                        <a href="{{ route('dashboard.guru.rekap-nilai.index') }}"
                           class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap text-xs font-medium text-primary-70 transition hover:gap-1.5 hover:underline">
                            Lihat Rekap <x-lucide-arrow-right class="h-3.5 w-3.5 transition-transform" />
                        </a>
                    </div>

                    <div class="mt-4 flex-1">
                        <x-dashboard.bar-chart
                            id="guru-perkembangan"
                            :bars="collect($perkembangan['label'])->map(fn ($label, $i) => [
                                'label' => $label,
                                'value' => $perkembangan['rata_rata'][$i] ?? null,
                            ])->all()"
                            rank-noun="periode"
                            value-label="Rata-rata nilai"
                            bar-width="fill"
                            empty-text="Belum ada nilai untuk mata pelajaran Anda."
                        />
                    </div>
                </div>

                <div data-region="kelas-tertinggi" data-reveal style="--d: .3s" class="min-w-0 lg:col-span-1">
                    <div class="flex h-full min-w-0 flex-col rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60">
                        <div class="flex items-center justify-between gap-3 p-4 sm:p-5">
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-slate-900">Nilai Kelas Tertinggi</h3>
                                <p class="mt-0.5 text-xs text-slate-500">Peringkat kelas {{ $periodeLabel }}.</p>
                            </div>
                            @if ($kelasTertinggi->total() > 0)
                                <a href="{{ route('dashboard.guru.rekap-nilai.index') }}"
                                   class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap text-xs font-medium text-primary-70 transition hover:gap-1.5 hover:underline">
                                    Lihat Rekap <x-lucide-arrow-right class="h-3.5 w-3.5 transition-transform" />
                                </a>
                            @endif
                        </div>

                        <div class="flex-1 overflow-x-auto border-t border-slate-100">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                        <th class="px-4 py-3.5 text-center font-semibold">No</th>
                                        <th class="px-4 py-3.5 font-semibold">Kelas</th>
                                        <th class="px-4 py-3.5 font-semibold">Jurusan</th>
                                        <th class="px-4 py-3.5 font-semibold">Nilai</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-800">
                                    @forelse ($kelasTertinggi as $i => $row)
                                        @php $nilai = (float) $row['nilai']; @endphp
                                        <tr class="transition hover:bg-slate-50/70">
                                            <td class="px-4 py-3 text-center tabular-nums text-slate-500">{{ $kelasTertinggi->firstItem() + $i }}</td>
                                            <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-900">{{ $row['kelas'] }}</td>
                                            <td class="px-4 py-3 leading-relaxed text-slate-600">{{ $row['jurusan'] ?: '—' }}</td>
                                            <td class="px-4 py-3 text-slate-800">
                                                <div class="flex flex-col items-start gap-1.5">
                                                    <span class="font-semibold tabular-nums">{{ $row['nilai'] }}</span>
                                                    <div class="h-1 w-16 overflow-hidden rounded-full bg-slate-100">
                                                        <div class="h-full rounded-full {{ $nilai >= 90 ? 'bg-emerald-500' : ($nilai >= 85 ? 'bg-primary-60' : ($nilai >= 75 ? 'bg-amber-500' : 'bg-rose-500')) }}" style="width: {{ max(0, min(100, $nilai)) }}%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-6 py-14 text-center text-sm text-slate-500">Belum ada nilai pada periode ini.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <x-ui.table-footer :paginator="$kelasTertinggi" label="kelas" />
                    </div>
                </div>
            </div>

            {{-- Baris 2: rekap per kelas (2/3) + siswa perlu perhatian (1/3) --}}
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                <div data-region="kelas" data-reveal style="--d: .35s" class="flex flex-col rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60 lg:col-span-2">
                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-slate-900">Rekap per Kelas</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ $periodeLabel }} · {{ $perKelas->total() }} kelas pada rentang ini.
                            </p>
                        </div>
                        <a href="{{ route('dashboard.guru.laporan.index') }}"
                           class="inline-flex shrink-0 items-center gap-1.5 rounded-xl border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-blue-600 transition hover:border-blue-300 hover:bg-blue-50">
                            <x-lucide-clipboard-list class="h-3.5 w-3.5" />
                            Lihat Laporan
                        </a>
                    </div>

                    <div class="flex-1 overflow-x-auto border-t border-slate-100">
                        <table class="w-full min-w-[640px] text-left text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                    <th class="px-4 py-3.5 text-center font-semibold">No</th>
                                    <th class="px-4 py-3.5 font-semibold">Kelas</th>
                                    <th class="px-4 py-3.5 font-semibold">Jurusan</th>
                                    <th class="px-4 py-3.5 text-center font-semibold">Siswa</th>
                                    <th class="px-4 py-3.5 text-center font-semibold">Nilai</th>
                                    <th class="px-4 py-3.5 text-center font-semibold">Rata-Rata</th>
                                    <th class="px-4 py-3.5 text-center font-semibold">Tuntas</th>
                                    <th class="px-4 py-3.5 text-center font-semibold">Belum</th>
                                    <th class="px-4 py-3.5 text-center font-semibold">% Tuntas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-800">
                                @forelse ($perKelas as $row)
                                    <tr class="transition hover:bg-slate-50/70">
                                        <td class="px-4 py-3 text-center text-slate-500">{{ $perKelas->firstItem() + $loop->index }}</td>
                                        <td class="px-4 py-3 font-medium text-slate-900">{{ $row['kode'] }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ $row['nama'] ?: '—' }}</td>
                                        <td class="px-4 py-3 text-center text-slate-600">{{ $row['jumlah_siswa'] }}</td>
                                        <td class="px-4 py-3 text-center text-slate-600">{{ $row['jumlah_nilai'] }}</td>
                                        <td class="px-4 py-3 text-center font-semibold tabular-nums text-slate-800">{{ $row['rata_rata'] }}</td>
                                        <td class="px-4 py-3 text-center tabular-nums text-emerald-600">{{ $row['tuntas'] }}</td>
                                        <td class="px-4 py-3 text-center tabular-nums text-rose-600">{{ $row['belum_tuntas'] }}</td>
                                        <td class="px-4 py-3 text-center tabular-nums text-slate-600">{{ $row['persen_tuntas'] }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-6 py-14 text-center text-sm text-slate-500">Belum ada nilai pada periode ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <x-ui.table-footer :paginator="$perKelas" label="kelas" />
                </div>

                <div data-region="perhatian" data-reveal style="--d: .4s" class="flex flex-col rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60">
                    <div class="flex items-center justify-between gap-3 p-4 sm:p-5">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-slate-900">Perlu Perhatian</h3>
                            <p class="mt-0.5 text-xs text-slate-500">Nilai belum tuntas, rata-rata terendah.</p>
                        </div>
                        <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-600">
                            <x-lucide-triangle-alert class="h-3.5 w-3.5" />
                            {{ $perluPerhatian->total() }}
                        </span>
                    </div>

                    <div class="flex-1 divide-y divide-slate-100 border-t border-slate-100">
                        @forelse ($perluPerhatian as $row)
                            <div class="flex items-center gap-3 p-4">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-rose-50 text-xs font-bold text-rose-600">
                                    {{ strtoupper(substr($row['nama'], 0, 1)) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-800" title="{{ $row['nama'] }}">{{ $row['nama'] }}</p>
                                    <p class="truncate text-[11px] text-slate-400" title="{{ $row['kelas'] }} · {{ $row['belum_tuntas'] }} nilai belum tuntas">{{ $row['kelas'] }} · {{ $row['belum_tuntas'] }} nilai belum tuntas</p>
                                </div>
                                <span class="shrink-0 text-sm font-bold tabular-nums text-rose-600">{{ $row['rata_rata'] ?? '—' }}</span>
                            </div>
                        @empty
                            <p class="px-4 py-14 text-center text-sm text-slate-400">Semua siswa sudah mencapai KKM.</p>
                        @endforelse
                    </div>

                    <x-ui.table-footer :paginator="$perluPerhatian" label="siswa" />
                </div>
            </div>

            <div data-region="siswa" data-reveal style="--d: .45s" class="rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60">
                <div class="flex items-center justify-between gap-3 p-4 sm:p-5">
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-slate-900">Data Siswa</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Siswa pada kelas yang Anda ampu.</p>
                    </div>
                    <a href="{{ route('dashboard.guru.data-siswa.index') }}"
                       class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap text-xs font-medium text-primary-70 transition hover:gap-1.5 hover:underline">
                        Lihat Semua <x-lucide-arrow-right class="h-3.5 w-3.5 transition-transform" />
                    </a>
                </div>

                <div class="overflow-x-auto border-t border-slate-100">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-4 py-3.5 text-center font-semibold">No</th>
                                <th class="px-4 py-3.5 font-semibold">NIS</th>
                                <th class="px-4 py-3.5 font-semibold">Nama</th>
                                <th class="px-4 py-3.5 font-semibold">Kelas</th>
                                <th class="px-4 py-3.5 font-semibold">Jurusan</th>
                                <th class="px-4 py-3.5 text-center font-semibold">Status</th>
                                <th class="px-4 py-3.5 text-center font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-800">
                            @forelse ($daftarSiswa as $i => $murid)
                                <tr class="transition hover:bg-slate-50/70">
                                    <td class="px-4 py-3 text-center text-slate-500">{{ $daftarSiswa->firstItem() + $i }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $murid->nis ?: '-' }}</td>
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $murid->name }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $murid->kelas }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $murid->jurusan ?: '—' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span @class([
                                            'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                                            'bg-emerald-50 text-emerald-600' => $murid->statusLabel() === \App\Models\User::STATUS_AKTIF,
                                            'bg-rose-50 text-rose-600' => $murid->statusLabel() === \App\Models\User::STATUS_NONAKTIF,
                                            'bg-sky-50 text-sky-600' => $murid->statusLabel() === \App\Models\User::STATUS_LULUS,
                                        ])>
                                            {{ $murid->statusLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <a href="{{ route('dashboard.guru.input-nilai.index') }}"
                                           class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-primary-30 hover:text-primary-70"
                                           title="Input nilai">
                                            <x-lucide-square-pen class="h-4 w-4" />
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-14 text-center text-sm text-slate-500">
                                        Belum ada siswa pada kelas yang Anda ampu.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($daftarSiswa->isNotEmpty())
                    <x-ui.table-footer :paginator="$daftarSiswa" label="siswa" />
                @endif
            </div>
        @endif
    </div>
@endsection
