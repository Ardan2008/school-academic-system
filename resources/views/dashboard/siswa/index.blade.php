@extends('layouts.dashboard')

@section('title', 'Dashboard Siswa')
@section('subtitle', 'Ringkasan kegiatan akademik Anda')

@php
    $user = auth()->user();

    $ringkasan = $ringkasan ?? [];
    $perkembangan = $perkembangan ?? ['label' => [], 'rata_rata' => [], 'jumlah' => []];

    $rataNilai = $ringkasan['rata_rata'] ?? null;
    $selisihSemesterLalu = $ringkasan['selisih'] ?? null;
    $jumlahMapel = $ringkasan['jumlah_mapel'] ?? 0;
    $mapelTuntas = $ringkasan['tuntas'] ?? 0;
    $mapelBelumTuntas = $ringkasan['belum_tuntas'] ?? 0;

    $tugasBelumDikumpulkan = $tugasBelumDikumpulkan ?? collect();
    $jadwalHariIni = $jadwalHariIni ?? collect();
    $aktivitasTerbaru = $aktivitasTerbaru ?? collect();
    $notifikasi = $notifikasi ?? collect();

    $jumlahTugasBelum = $tugasBelumDikumpulkan->count();

    $batasMendekati = now()->addDays(7)->startOfDay();
    $tugasMendekatiDeadline = $tugasBelumDikumpulkan->filter(fn ($tugas) => $tugas->deadline !== null
        && ! $tugas->sudahLewatDeadline()
        && $tugas->deadline->copy()->startOfDay()->lessThanOrEqualTo($batasMendekati))->count();

    $deadlineTerdekat = optional($tugasBelumDikumpulkan->first(fn ($tugas) => $tugas->deadline !== null))->labelDeadline() ?? 'Tidak ada';

    $periodeLabel = \App\Support\Periode::label($periode['tahun_ajaran'], $periode['semester']);

    $kartu = [
        [
            'label' => 'Rata-Rata Nilai',
            'value' => $rataNilai === null ? null : number_format($rataNilai, 2, '.', ''),
            'icon' => 'lucide-graduation-cap',
            'tone' => 'bg-blue-100 text-primary-60',
            'accent' => '#1E86FF',
            'decimals' => 2,
            'catatan' => $rataNilai === null
                ? 'Belum ada nilai'
                : ($selisihSemesterLalu !== null
                    ? (($selisihSemesterLalu >= 0 ? '+' : '').number_format($selisihSemesterLalu, 1).' dari semester lalu')
                    : $jumlahMapel.' mata pelajaran'),
        ],
        [
            'label' => 'Mapel Tuntas',
            'value' => $mapelTuntas,
            'icon' => 'lucide-circle-check',
            'tone' => 'bg-emerald-100 text-emerald-600',
            'accent' => '#059669',
            'decimals' => 0,
            'catatan' => $mapelBelumTuntas > 0 ? $mapelBelumTuntas.' belum tuntas' : 'Semua tuntas',
        ],
        [
            'label' => 'Tugas Belum',
            'value' => $jumlahTugasBelum,
            'icon' => 'lucide-clipboard-list',
            'tone' => 'bg-amber-100 text-amber-600',
            'accent' => '#D97706',
            'decimals' => 0,
            // Dipersingkat agar tidak terpotong di kartu
            'catatan' => $jumlahTugasBelum > 0
                ? ($tugasMendekatiDeadline > 0
                    ? $tugasMendekatiDeadline.' mendekati deadline'
                    : $deadlineTerdekat)
                : 'Semua tugas terkumpul',
        ],
        [
            'label' => 'Jadwal Hari Ini',
            'value' => $jadwalHariIni->count(),
            'icon' => 'lucide-calendar-days',
            'tone' => 'bg-violet-100 text-violet-600',
            'accent' => '#7C3AED',
            'decimals' => 0,
            'catatan' => $jadwalHariIni->isNotEmpty() ? 'Pelajaran hari ini' : 'Tidak ada jadwal',
        ],
    ];
@endphp

@section('content')
    <div data-dashboard class="space-y-6 sm:space-y-8">
        <x-dashboard.animations />

        {{-- Lingkup data: kelas + periode berjalan --}}
        <div data-reveal style="--d: 0s" class="flex flex-wrap items-center gap-2.5">
            <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Kelas</span>
            <span class="inline-flex max-w-full items-center gap-2 rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-medium text-primary-70 ring-1 ring-blue-100">
                <x-lucide-school class="h-3.5 w-3.5 shrink-0" />
                <span class="truncate">{{ $user->kelas ?? '—' }}{{ $user->jurusan ? ' · '.$user->jurusan : '' }}</span>
            </span>
            <span class="ml-auto inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600">
                <x-lucide-calendar-days class="h-3.5 w-3.5" />
                {{ $periodeLabel }}
            </span>
        </div>

        {{-- Kartu ringkasan --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-5 xl:grid-cols-4">
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

        {{-- Baris 1: grafik perkembangan (2/3) + jadwal hari ini (1/3) --}}
        {{-- Layout 3 kolom baru aktif di xl, karena di lg lebar konten sudah berkurang oleh sidebar --}}
        <div class="grid grid-cols-1 gap-5 sm:gap-6 xl:grid-cols-3">
            <div data-reveal style="--d: .25s" class="min-w-0 xl:col-span-2">
                <div class="flex h-full min-w-0 flex-col rounded-2xl border border-slate-100 bg-white p-5 shadow-md shadow-slate-200/60 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-slate-900">Perkembangan Nilai</h3>
                            <p class="mt-1 text-xs text-slate-500">Rata-rata nilai Anda pada tiap periode.</p>
                        </div>
                        @if (Route::has('dashboard.siswa.riwayat-akademik.index'))
                            <a href="{{ route('dashboard.siswa.riwayat-akademik.index') }}"
                               class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap text-xs font-medium text-primary-70 transition hover:gap-1.5 hover:underline">
                                Lihat Riwayat <x-lucide-arrow-right class="h-3.5 w-3.5 transition-transform" />
                            </a>
                        @endif
                    </div>

                    <div class="mt-5 flex-1">
                        <x-dashboard.bar-chart
                            id="siswa-perkembangan"
                            :bars="collect($perkembangan['label'])->map(fn ($label, $i) => [
                                'label' => $label,
                                'value' => $perkembangan['rata_rata'][$i] ?? null,
                            ])->all()"
                            rank-noun="periode"
                            value-label="Rata-rata nilai"
                            bar-width="fill"
                            empty-text="Belum ada nilai."
                        />
                    </div>
                </div>
            </div>

            <div data-region="jadwal" data-reveal style="--d: .3s" class="min-w-0">
                <div class="flex h-full min-w-0 flex-col rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60">
                    <div class="flex items-center justify-between gap-3 p-5 sm:p-6">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-slate-900">Jadwal Hari Ini</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ $jadwalHariIni->count() }} pelajaran terjadwal.</p>
                        </div>
                        @if (Route::has('dashboard.siswa.jadwal-pelajaran.index'))
                            <a href="{{ route('dashboard.siswa.jadwal-pelajaran.index') }}"
                               class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap text-xs font-medium text-primary-70 transition hover:gap-1.5 hover:underline">
                                Lihat Semua <x-lucide-arrow-right class="h-3.5 w-3.5 transition-transform" />
                            </a>
                        @endif
                    </div>

                    {{-- Daftar baris (bukan tabel) agar muat di kolom 1/3 tanpa scroll horizontal --}}
                    <ul class="flex-1 divide-y divide-slate-100 border-t border-slate-100">
                        @forelse ($jadwalHariIni as $i => $jadwal)
                            <li class="flex items-center gap-3.5 px-5 py-4 transition hover:bg-slate-50/70 sm:px-6">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs font-semibold text-slate-500">
                                    {{ $i + 1 }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium leading-snug text-slate-900">{{ $jadwal->mataPelajaran?->nama ?? '-' }}</p>
                                    <p class="mt-1 whitespace-nowrap text-xs text-slate-500">{{ $jadwal->jamLabel() }}</p>
                                </div>
                                <span class="inline-flex shrink-0 whitespace-nowrap rounded-full bg-primary-10 px-3 py-1 text-xs font-medium text-primary-70">
                                    {{ $jadwal->ruang ?? '-' }}
                                </span>
                            </li>
                        @empty
                            <li class="px-6 py-14 text-center text-sm text-slate-500">Tidak ada jadwal hari ini.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        {{-- Baris 2: tugas belum dikumpulkan (2/3) + notifikasi (1/3) --}}
        <div class="grid grid-cols-1 gap-5 sm:gap-6 xl:grid-cols-3">
            <div data-region="tugas" data-reveal style="--d: .35s" class="min-w-0 xl:col-span-2">
                <div class="flex h-full min-w-0 flex-col rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60">
                    <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-slate-900">Tugas Belum Dikumpulkan</h3>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $jumlahTugasBelum }} tugas menunggu · {{ $tugasMendekatiDeadline }} mendekati deadline.
                            </p>
                        </div>
                        @if (Route::has('dashboard.siswa.tugas-materi.index'))
                            <a href="{{ route('dashboard.siswa.tugas-materi.index') }}"
                               class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap text-xs font-medium text-primary-70 transition hover:gap-1.5 hover:underline">
                                Lihat Semua <x-lucide-arrow-right class="h-3.5 w-3.5 transition-transform" />
                            </a>
                        @endif
                    </div>

                    <div class="flex-1 overflow-x-auto border-t border-slate-100 md:hidden">
                        <div class="divide-y divide-slate-100">
                            @forelse ($tugasPaginasi ?? $tugasBelumDikumpulkan as $tugas)
                                @php $terlambat = $tugas->sudahLewatDeadline(); @endphp
                                <div class="flex flex-col gap-3 p-4 text-sm sm:p-5">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1 space-y-1.5">
                                            <p class="truncate text-sm font-medium text-slate-900" title="{{ $tugas->judul }}">{{ $tugas->judul }}</p>
                                            <p class="truncate text-xs text-slate-500" title="{{ $tugas->mataPelajaran?->nama ?? '-' }}">
                                                <span class="font-medium text-slate-600">Mata Pelajaran:</span> {{ $tugas->mataPelajaran?->nama ?? '-' }}
                                            </p>
                                            <p class="truncate text-xs text-slate-500" title="{{ $tugas->guru?->name ?? '-' }}">
                                                <span class="font-medium text-slate-600">Guru:</span> {{ $tugas->guru?->name ?? '-' }}
                                            </p>
                                        </div>
                                        <span @class([
                                            'inline-flex shrink-0 self-start whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium',
                                            'bg-rose-50 text-rose-600' => $terlambat,
                                            'bg-amber-50 text-amber-600' => ! $terlambat,
                                        ])>
                                            {{ $terlambat ? 'Terlambat' : 'Menunggu' }}
                                        </span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                        <span class="inline-flex items-center gap-1.5">
                                            <x-lucide-calendar-clock class="h-3.5 w-3.5" />
                                            Deadline: {{ $tugas->labelDeadline() }}
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <p class="px-4 py-10 text-center text-sm text-slate-500">Semua tugas sudah dikumpulkan.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="flex-1 overflow-x-auto border-t border-slate-100 hidden md:block">
                        <table class="w-full min-w-[720px] divide-y divide-slate-100">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                    <th class="whitespace-nowrap px-4 py-3 text-center font-semibold lg:px-5 lg:py-4">No</th>
                                    <th class="whitespace-nowrap px-4 py-3 font-semibold lg:px-5 lg:py-4">Mata Pelajaran</th>
                                    <th class="px-4 py-3 font-semibold lg:px-5 lg:py-4">Tugas</th>
                                    <th class="whitespace-nowrap px-4 py-3 font-semibold lg:px-5 lg:py-4">Guru</th>
                                    <th class="whitespace-nowrap px-4 py-3 font-semibold lg:px-5 lg:py-4">Deadline</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-center font-semibold lg:px-5 lg:py-4">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-800">
                                @forelse ($tugasPaginasi ?? $tugasBelumDikumpulkan as $i => $tugas)
                                    <tr class="transition hover:bg-slate-50/70">
                                        <td class="px-4 py-3 text-center text-sm text-slate-500 lg:px-5 lg:py-4">{{ ($tugasPaginasi?->firstItem() ?? 1) + $i }}</td>
                                        <td class="px-4 py-3 text-sm font-medium text-slate-900 lg:px-5 lg:py-4">{{ $tugas->mataPelajaran?->nama ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-600 lg:px-5 lg:py-4">
                                            <div class="max-w-xs truncate" title="{{ $tugas->judul }}">{{ $tugas->judul }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-600 lg:px-5 lg:py-4">
                                            <div class="max-w-[160px] truncate" title="{{ $tugas->guru?->name ?? '-' }}">{{ $tugas->guru?->name ?? '-' }}</div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600 lg:px-5 lg:py-4">{{ $tugas->labelDeadline() }}</td>
                                        <td class="px-4 py-3 text-center lg:px-5 lg:py-4">
                                            @php $terlambat = $tugas->sudahLewatDeadline(); @endphp
                                            <span @class([
                                                'inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium',
                                                'bg-rose-50 text-rose-600' => $terlambat,
                                                'bg-amber-50 text-amber-600' => ! $terlambat,
                                            ])>
                                                {{ $terlambat ? 'Terlambat' : 'Menunggu' }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-14 text-center text-sm text-slate-500">Semua tugas sudah dikumpulkan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if (($tugasPaginasi ?? null) && $tugasPaginasi->total() > 0)
                        <x-ui.table-footer :paginator="$tugasPaginasi" label="tugas" />
                    @endif
                </div>
            </div>

            <div data-region="notifikasi" data-reveal style="--d: .4s" class="min-w-0">
                <div class="flex h-full min-w-0 flex-col rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60">
                    <div class="flex items-center justify-between gap-3 p-5 sm:p-6">
                        <h3 class="text-sm font-semibold text-slate-900">Notifikasi Terbaru</h3>
                    </div>

                    <div class="flex-1 divide-y divide-slate-100 border-t border-slate-100">
                        @forelse ($notifikasi as $notif)
                            <div class="flex items-start gap-3.5 px-5 py-4 sm:px-6">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-10 text-primary-70">
                                    <x-dynamic-component :component="'lucide-'.$notif['icon']" class="h-4 w-4" />
                                </span>
                                <div class="min-w-0 space-y-1">
                                    <p class="text-sm font-semibold leading-snug text-slate-800">{{ $notif['judul'] }}</p>
                                    <x-ui.expandable-text :text="$notif['detail']" :lines="2" class="text-xs text-slate-500" />
                                    <p class="text-xs text-slate-400">{{ \App\Support\Waktu::jamRelatif($notif['waktu'] ?? null) }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="px-5 py-14 text-center text-sm text-slate-400">Belum ada notifikasi baru.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Baris 3: aktivitas terbaru --}}
        <div data-region="aktivitas" data-reveal style="--d: .45s" class="rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60">
            <div class="flex items-center justify-between gap-3 p-5 sm:p-6">
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-slate-900">Aktivitas Terbaru</h3>
                    <p class="mt-1 text-xs text-slate-500">Pengumpulan tugas terakhir Anda.</p>
                </div>
                @if (Route::has('dashboard.siswa.riwayat-aktivitas.index'))
                    <a href="{{ route('dashboard.siswa.riwayat-aktivitas.index') }}"
                       class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap text-xs font-medium text-primary-70 transition hover:gap-1.5 hover:underline">
                        Lihat Semua <x-lucide-arrow-right class="h-3.5 w-3.5 transition-transform" />
                    </a>
                @endif
            </div>

            <div class="overflow-x-auto border-t border-slate-100 md:hidden">
                <div class="divide-y divide-slate-100">
                    @forelse ($aktivitasPaginasi ?? $aktivitasTerbaru as $act)
                        @php $tugas = $act->tugas; @endphp
                        <div class="flex flex-col gap-2.5 p-4 text-sm sm:p-5">
                            <div class="min-w-0 space-y-1.5">
                                <p class="truncate text-sm font-medium text-slate-900" title="{{ $tugas?->judul ?? '-' }}">{{ $tugas?->judul ?? '-' }}</p>
                                <p class="truncate text-xs text-slate-500" title="{{ $tugas?->mataPelajaran?->nama ?? '-' }}">
                                    <span class="font-medium text-slate-600">Mata Pelajaran:</span> {{ $tugas?->mataPelajaran?->nama ?? '-' }}
                                </p>
                                <p class="truncate text-xs text-slate-500" title="{{ $tugas?->guru?->name ?? '-' }}">
                                    <span class="font-medium text-slate-600">Guru Pengampu:</span> {{ $tugas?->guru?->name ?? '-' }}
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                <span class="inline-flex items-center gap-1.5">
                                    <x-lucide-clock class="h-3.5 w-3.5" />
                                    {{ \App\Support\Waktu::bulanJam($act->dikumpulkan_at) ?? '-' }}
                                </span>
                                @if ($tugas?->kelas)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-10 px-2.5 py-1 font-medium text-primary-70">
                                        <x-lucide-school class="h-3.5 w-3.5" />
                                        {{ $tugas->kelas }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="px-4 py-10 text-center text-sm text-slate-500">Belum ada aktivitas pengumpulan tugas.</p>
                    @endforelse
                </div>
            </div>

            <div class="overflow-x-auto border-t border-slate-100 hidden md:block">
                <table class="w-full min-w-[720px] divide-y divide-slate-100">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <th class="whitespace-nowrap px-4 py-3 text-center font-semibold lg:px-5 lg:py-4">No</th>
                            <th class="whitespace-nowrap px-4 py-3 font-semibold lg:px-5 lg:py-4">Waktu</th>
                            <th class="whitespace-nowrap px-4 py-3 font-semibold lg:px-5 lg:py-4">Mata Pelajaran</th>
                            <th class="px-4 py-3 font-semibold lg:px-5 lg:py-4">Tugas</th>
                            <th class="whitespace-nowrap px-4 py-3 font-semibold lg:px-5 lg:py-4">Guru Pengampu</th>
                            <th class="whitespace-nowrap px-4 py-3 font-semibold lg:px-5 lg:py-4">Kelas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-800">
                        @forelse ($aktivitasPaginasi ?? $aktivitasTerbaru as $i => $act)
                            @php $tugas = $act->tugas; @endphp
                            <tr class="transition hover:bg-slate-50/70">
                                <td class="px-4 py-3 text-center text-sm text-slate-500 lg:px-5 lg:py-4">{{ ($aktivitasPaginasi?->firstItem() ?? 1) + $i }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600 lg:px-5 lg:py-4">{{ \App\Support\Waktu::bulanJam($act->dikumpulkan_at) ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-slate-900 lg:px-5 lg:py-4">{{ $tugas?->mataPelajaran?->nama ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 lg:px-5 lg:py-4">
                                    <div class="max-w-xs truncate" title="{{ $tugas?->judul ?? '-' }}">{{ $tugas?->judul ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 lg:px-5 lg:py-4">
                                    <div class="max-w-[160px] truncate" title="{{ $tugas?->guru?->name ?? '-' }}">{{ $tugas?->guru?->name ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3 lg:px-5 lg:py-4">
                                    <span class="inline-flex whitespace-nowrap rounded-full bg-primary-10 px-2.5 py-1 text-xs font-medium text-primary-70">
                                        {{ $tugas?->kelas ?? '-' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-14 text-center text-sm text-slate-500">Belum ada aktivitas pengumpulan tugas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (($aktivitasPaginasi ?? null) && $aktivitasPaginasi->total() > 0)
                <x-ui.table-footer :paginator="$aktivitasPaginasi" label="aktivitas" />
            @endif
        </div>
    </div>
@endsection