@extends('layouts.dashboard')

@section('title', 'Laporan Akademik')
@section('subtitle', 'Dashboard Admin')

@section('content')
@php
    // Filter aktif ikut dibawa ke link export & tombol cetak supaya file yang
    // diunduh persis sama dengan yang terlihat di layar.
    $filterAktif = array_filter([
        'semester' => $filter['semester'],
        'tahun_ajaran' => $filter['tahun_ajaran'],
        'jurusan' => $filter['jurusan'],
        'kelas' => $filter['kelas'],
        'mata_pelajaran' => $filter['mata_pelajaran'],
    ], fn ($nilai) => $nilai !== '');

    $filterDipakai = $filter['jurusan'] !== ''
        || $filter['kelas'] !== ''
        || $filter['mata_pelajaran'] !== ''
        || $filter['semester'] !== \App\Support\Periode::semesterSekarang()
        || $filter['tahun_ajaran'] !== \App\Support\Periode::tahunAjaranSekarang();

    $angka = fn ($nilai) => $nilai === null ? '--' : str_replace('.', ',', (string) $nilai);
@endphp

<div id="laporan-akademik" class="space-y-6 pb-12">

    {{-- Kop cetak: tanpa ini, kertas laporan hanya jadi lembar angka tanpa judul dan periode. --}}
    <div class="cetak-panel hidden border-b border-slate-200 pb-3 print:block">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="text-base font-bold text-slate-900">Laporan Akademik</h2>
                <p class="text-xs text-slate-500">Saskansa · Dashboard Administrator</p>
            </div>
            <p class="text-xs font-medium text-slate-600">
                {{ \App\Support\Periode::semesterLabel($filter['semester']) }}
                {{ \App\Support\Periode::tahunAjaranLabel($filter['tahun_ajaran']) }}
            </p>
        </div>
    </div>

    {{-- Filter Utama --}}
    <form method="GET" action="{{ route('dashboard.admin.laporan-akademik.index') }}"
          class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:shadow-md">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">

            <div>
                <label for="filter-semester" class="mb-1.5 block text-xs font-medium text-slate-500">Semester</label>
                <div class="relative">
                    <select id="filter-semester" name="semester"
                            class="w-full appearance-none rounded-xl border border-slate-200 bg-white py-2.5 pl-3.5 pr-8 text-xs font-medium text-slate-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                        @foreach ($semesters as $s)
                            <option value="{{ $s }}" @selected($filter['semester'] === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                    <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                </div>
            </div>

            <div>
                <label for="filter-tahun" class="mb-1.5 block text-xs font-medium text-slate-500">Tahun Ajaran</label>
                <div class="relative">
                    <select id="filter-tahun" name="tahun_ajaran"
                            class="w-full appearance-none rounded-xl border border-slate-200 bg-white py-2.5 pl-3.5 pr-8 text-xs font-medium text-slate-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                        @foreach ($tahunAjarans as $ta)
                            <option value="{{ $ta }}" @selected($filter['tahun_ajaran'] === $ta)>{{ $ta }}</option>
                        @endforeach
                    </select>
                    <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                </div>
            </div>

            {{-- Kelas memakai format yang sama dengan Data Kelas/Siswa, jadi "12-1" tidak tertukar dengan "12-11". --}}
            <div>
                <label for="filter-kelas" class="mb-1.5 block text-xs font-medium text-slate-500">Kelas</label>
                <div class="relative">
                    <select id="filter-kelas" name="kelas"
                            class="w-full appearance-none rounded-xl border border-slate-200 bg-white py-2.5 pl-3.5 pr-8 text-xs font-medium text-slate-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                        <option value="">Semua Kelas</option>
                        @foreach ($kelasOptions as $k)
                            <option value="{{ $k }}" @selected($filter['kelas'] === $k)>{{ $k }}</option>
                        @endforeach
                    </select>
                    <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                </div>
            </div>

            <div>
                <label for="filter-jurusan" class="mb-1.5 block text-xs font-medium text-slate-500">Jurusan</label>
                <div class="relative">
                    <select id="filter-jurusan" name="jurusan"
                            class="w-full appearance-none rounded-xl border border-slate-200 bg-white py-2.5 pl-3.5 pr-8 text-xs font-medium text-slate-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                        <option value="">Semua Jurusan</option>
                        @foreach ($jurusanOptions as $j)
                            <option value="{{ $j }}" @selected($filter['jurusan'] === $j)>{{ $j }}</option>
                        @endforeach
                    </select>
                    <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                </div>
            </div>

            <div>
                <label for="filter-mapel" class="mb-1.5 block text-xs font-medium text-slate-500">Mata Pelajaran</label>
                <div class="relative">
                    <select id="filter-mapel" name="mata_pelajaran"
                            class="w-full appearance-none rounded-xl border border-slate-200 bg-white py-2.5 pl-3.5 pr-8 text-xs font-medium text-slate-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                        <option value="">Semua Mata Pelajaran</option>
                        @foreach ($mapelOptions as $m)
                            <option value="{{ $m }}" @selected($filter['mata_pelajaran'] === $m)>{{ $m }}</option>
                        @endforeach
                    </select>
                    <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                </div>
            </div>

        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            @if ($filterDipakai)
                <a href="{{ route('dashboard.admin.laporan-akademik.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                    <x-lucide-x class="h-3.5 w-3.5" />
                    Reset Filter
                </a>
            @else
                <span></span>
            @endif

            <div class="flex items-center gap-2">
                <button type="button" onclick="window.print()"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
                    <x-lucide-printer class="h-3.5 w-3.5" />
                    Cetak
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-xs font-semibold text-white shadow-md shadow-blue-500/25 transition hover:bg-blue-700 active:scale-[0.98]">
                    <x-lucide-filter class="h-3.5 w-3.5" />
                    Terapkan Filter
                </button>
            </div>
        </div>
    </form>

    {{-- Rentang laporan yang sedang dibaca --}}
    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
        <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1 font-semibold text-slate-600">
            {{ \App\Support\Periode::semesterLabel($filter['semester']) }}
        </span>
        <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1 font-semibold text-slate-600">
            {{ \App\Support\Periode::tahunAjaranLabel($filter['tahun_ajaran']) }}
        </span>
        @if ($filter['kelas'] !== '')
            <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-2.5 py-1 font-semibold text-blue-700">Kelas {{ $filter['kelas'] }}</span>
        @endif
        @if ($filter['jurusan'] !== '')
            <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-2.5 py-1 font-semibold text-blue-700">{{ $filter['jurusan'] }}</span>
        @endif
        @if ($filter['mata_pelajaran'] !== '')
            <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-2.5 py-1 font-semibold text-blue-700">{{ $filter['mata_pelajaran'] }}</span>
        @endif
    </div>

    @if (! $adaNilai)
        {{-- Kosong tidak pernah diisi angka perkiraan; laporan hanya menampilkan nilai yang tercatat. --}}
        <div class="cetak-panel rounded-2xl border border-amber-200 bg-amber-50/70 p-6 shadow-sm">
            <div class="flex items-start gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-600">
                    <x-lucide-alert-triangle class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <h3 class="text-sm font-bold text-amber-900">Belum ada nilai pada rentang ini</h3>
                    <p class="mt-1.5 text-xs leading-relaxed text-amber-800">
                        Laporan ini dihitung dari tabel nilai, jadi tidak ada angka yang bisa
                        ditampilkan selama belum ada nilai yang tercatat untuk
                        {{ \App\Support\Periode::semesterLabel($filter['semester']) }}
                        {{ $filter['tahun_ajaran'] }}
                        @if ($filter['kelas'] !== ''), kelas {{ $filter['kelas'] }}@endif
                        @if ($filter['jurusan'] !== ''), {{ $filter['jurusan'] }}@endif
                        @if ($filter['mata_pelajaran'] !== ''), {{ $filter['mata_pelajaran'] }}@endif.
                    </p>
                    @if ($jumlahSiswaTerfilter > 0)
                        <p class="mt-2 text-xs font-medium text-amber-900">
                            Ada {{ $jumlahSiswaTerfilter }} siswa pada lingkup kelas dan jurusan ini,
                            jadi yang belum ada adalah nilainya, bukan datanya.
                        </p>
                    @endif
                    <p class="mt-3 text-xs leading-relaxed text-amber-700">
                        Angka yang tampil di laporan ini hanya yang benar-benar tercatat, jadi selama
                        rentang ini kosong angka perkiraan tetap tidak ditampilkan agar tidak
                        disalahartikan sebagai hasil belajar siswa.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- 4 Kartu Ringkasan --}}
    @php
        $kasusTuntas = number_format($ringkasan['tuntas'], 0, ',', '.').' tuntas dari '
            .number_format($ringkasan['jumlah_nilai'], 0, ',', '.').' nilai'
            .($ringkasan['rata_kkm'] !== null ? ' · KKM rata-rata '.$angka($ringkasan['rata_kkm']) : '');
    @endphp
    <div class="cetak-grid-2 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4 sm:gap-5">

        <x-ui.stat-card
            layout="aside"
            label="Rata-Rata Nilai"
            :value="$angka($ringkasan['rata_rata'])"
            icon="lucide-bar-chart-2"
            tone="bg-blue-100 text-primary-60"
            :catatan="'dari '.number_format($ringkasan['jumlah_nilai'], 0, ',', '.').' nilai tercatat'"
            class="cetak-panel"
        />

        <x-ui.stat-card
            layout="aside"
            label="Ketuntasan (KKM)"
            :value="$angka($ringkasan['persen_tuntas']).($ringkasan['persen_tuntas'] === null ? '' : '%')"
            icon="lucide-badge-check"
            tone="bg-blue-100 text-primary-60"
            :catatan="$kasusTuntas"
            class="cetak-panel"
        />

        <x-ui.stat-card
            layout="aside"
            label="Siswa Dinilai"
            :value="number_format($ringkasan['jumlah_siswa'], 0, ',', '.')"
            icon="lucide-users"
            tone="bg-blue-100 text-primary-60"
            catatan="siswa yang punya nilai pada rentang ini"
            class="cetak-panel"
        />

        <x-ui.stat-card
            layout="aside"
            label="Kelas Tercatat"
            :value="number_format($totalKelas, 0, ',', '.')"
            icon="lucide-book-open"
            tone="bg-blue-100 text-primary-60"
            :catatan="'dari '.number_format($totalMapel, 0, ',', '.').' mata pelajaran'"
            class="cetak-panel"
        />

    </div>

    @if ($adaNilai)
        {{-- Grafik --}}
        <div class="cetak-grid-1 grid grid-cols-1 gap-5 lg:grid-cols-2">

            <div class="cetak-panel flex flex-col justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:shadow-md">
                <div class="cetak-judul flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-800 sm:text-base">Rata-Rata Nilai per Kelas</h3>
                    <a href="{{ route('dashboard.admin.laporan-akademik.export-kelas', $filterAktif) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-blue-600 transition hover:border-blue-300 hover:bg-blue-50">
                        <x-lucide-download class="h-3.5 w-3.5" />
                        Export Excel
                    </a>
                </div>

                <div class="mt-4 h-64 w-full">
                    <canvas id="chartNilaiKelas"></canvas>
                </div>
            </div>

            {{-- Perkembangan sengaja lintas periode: ikut periode terpilih, garisnya cuma satu titik dan tidak menunjukkan perkembangan. --}}
            <div class="cetak-panel flex flex-col justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:shadow-md">
                <div class="cetak-judul flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-800 sm:text-base">Perkembangan Rata-Rata per Semester</h3>
                    <span class="rounded-lg bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">
                        Lintas tahun ajaran
                    </span>
                </div>

                @if ($perkembanganLabel === [])
                    <div class="mt-4 flex h-64 items-center justify-center rounded-xl bg-slate-50 text-xs text-slate-400">
                        Belum ada nilai yang bisa dibandingkan antar periode.
                    </div>
                @else
                    <div class="mt-4 h-64 w-full">
                        <canvas id="chartPerkembangan"></canvas>
                    </div>
                @endif
            </div>

        </div>

        {{-- Kedua tabel rekap mengikuti filter yang sama dengan kartu ringkasan; data-region dipakai test agar assert angka tidak menabrak angka lain. --}}
        <div data-region="rekap" class="space-y-5">
        {{-- Rekap per Kelas --}}
        <div class="cetak-panel flex flex-col rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60">
            <div class="cetak-judul flex flex-wrap items-center justify-between gap-3 p-4 sm:p-5">
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-slate-900">Rekap Nilai per Kelas</h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Rata-rata dihitung dari seluruh nilai di kelas itu, bukan dari rata-rata antar kelas.
                    </p>
                </div>
                <a href="{{ route('dashboard.admin.laporan-akademik.export-kelas', $filterAktif) }}"
                   class="inline-flex shrink-0 items-center gap-1.5 rounded-xl border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-blue-600 transition hover:border-blue-300 hover:bg-blue-50">
                    <x-lucide-download class="h-3.5 w-3.5" />
                    Export Excel
                </a>
            </div>

            <div class="cetak-tabel flex-1 overflow-x-auto border-t border-slate-100">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <th class="cetak-nowrap px-4 py-3.5 text-center font-semibold">No</th>
                            <th class="px-4 py-3.5 font-semibold">Kelas</th>
                            <th class="px-4 py-3.5 font-semibold">Jurusan</th>
                            <th class="px-4 py-3.5 text-center font-semibold">Siswa</th>
                            <th class="px-4 py-3.5 text-center font-semibold">Nilai Tercatat</th>
                            <th class="px-4 py-3.5 text-center font-semibold">Rata-Rata</th>
                            <th class="px-4 py-3.5 text-center font-semibold">Tuntas</th>
                            <th class="px-4 py-3.5 text-center font-semibold">Belum Tuntas</th>
                            <th class="cetak-nowrap px-4 py-3.5 text-center font-semibold">Persentase Tuntas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-800">
                        @foreach ($perKelas as $row)
                            <tr class="transition hover:bg-slate-50/70">
                                <td class="px-4 py-3 text-center text-slate-500">{{ $row['no'] }}</td>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $row['kode'] }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row['nama'] }}</td>
                                <td class="px-4 py-3 text-center text-slate-600">{{ $row['jumlah_siswa'] }}</td>
                                <td class="px-4 py-3 text-center text-slate-600">{{ $row['jumlah_nilai'] }}</td>
                                <td class="px-4 py-3 text-center font-semibold tabular-nums text-slate-800">{{ $angka($row['rata_rata']) }}</td>
                                <td class="px-4 py-3 text-center font-semibold tabular-nums text-emerald-600">{{ $row['tuntas'] }}</td>
                                <td class="px-4 py-3 text-center tabular-nums text-rose-600">{{ $row['belum_tuntas'] }}</td>
                                <td class="px-4 py-3 text-center">
                                    @php $pct = $row['persen_tuntas']; @endphp
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-bold ring-1 {{ $pct >= 80 ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : ($pct >= 60 ? 'bg-amber-50 text-amber-700 ring-amber-200' : 'bg-red-50 text-red-600 ring-red-200') }}">
                                        {{ $pct }}%
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="cetak-footer rounded-b-2xl border-t border-slate-200 bg-slate-50 px-4 py-3">
                <div class="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2">
                    <div class="flex items-center justify-between gap-2 rounded-xl bg-white px-3 py-2 ring-1 ring-slate-200">
                        <span class="text-slate-500">Rata-rata tertinggi</span>
                        <span class="font-bold text-slate-800">
                            {{ $bestKelas ? $bestKelas['kode'].' · '.$bestKelas['nama'].' ('.$angka($bestKelas['rata_rata']).')' : '--' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2 rounded-xl bg-white px-3 py-2 ring-1 ring-slate-200">
                        <span class="text-slate-500">Rata-rata terendah</span>
                        <span class="font-bold text-slate-800">
                            {{ $worstKelas ? $worstKelas['kode'].' · '.$worstKelas['nama'].' ('.$angka($worstKelas['rata_rata']).')' : '--' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Rekap per Mata Pelajaran --}}
        <div class="cetak-panel flex flex-col rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60">
            <div class="cetak-judul flex flex-wrap items-center justify-between gap-3 p-4 sm:p-5">
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-slate-900">Rekap Nilai per Mata Pelajaran</h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Guru pengampu diambil dari akun guru yang mata Pelajarannya sama, bukan dari urutan nama.
                    </p>
                </div>
                <a href="{{ route('dashboard.admin.laporan-akademik.export-mata-pelajaran', $filterAktif) }}"
                   class="inline-flex shrink-0 items-center gap-1.5 rounded-xl border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-blue-600 transition hover:border-blue-300 hover:bg-blue-50">
                    <x-lucide-download class="h-3.5 w-3.5" />
                    Export Excel
                </a>
            </div>

            <div class="cetak-tabel flex-1 overflow-x-auto border-t border-slate-100">
                {{-- cetak-nowrap agar badge persentase tidak terbelah dua baris di kertas. --}}
                <table class="w-full min-w-[860px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <th class="cetak-nowrap px-4 py-3.5 text-center font-semibold">No</th>
                            <th class="cetak-nowrap px-4 py-3.5 font-semibold">Kode</th>
                            <th class="px-4 py-3.5 font-semibold">Mata Pelajaran</th>
                            <th class="px-4 py-3.5 font-semibold">Guru Pengampu</th>
                            <th class="cetak-nowrap px-4 py-3.5 text-center font-semibold">Siswa</th>
                            <th class="cetak-nowrap px-4 py-3.5 text-center font-semibold">Nilai Tercatat</th>
                            <th class="cetak-nowrap px-4 py-3.5 text-center font-semibold">Rata-Rata</th>
                            <th class="cetak-nowrap px-4 py-3.5 text-center font-semibold">Tuntas</th>
                            <th class="cetak-nowrap px-4 py-3.5 text-center font-semibold">Belum Tuntas</th>
                            <th class="cetak-nowrap px-4 py-3.5 text-center font-semibold">Persentase Tuntas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-800">
                        @foreach ($perMapel as $row)
                            <tr class="transition hover:bg-slate-50/70">
                                <td class="px-4 py-3 text-center text-slate-500">{{ $row['no'] }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $row['kode'] ?: '-' }}</td>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $row['nama'] }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row['guru'] }}</td>
                                <td class="px-4 py-3 text-center text-slate-600">{{ $row['jumlah_siswa'] }}</td>
                                <td class="px-4 py-3 text-center text-slate-600">{{ $row['jumlah_nilai'] }}</td>
                                <td class="px-4 py-3 text-center font-semibold tabular-nums text-slate-800">{{ $angka($row['rata_rata']) }}</td>
                                <td class="px-4 py-3 text-center font-semibold tabular-nums text-emerald-600">{{ $row['tuntas'] }}</td>
                                <td class="px-4 py-3 text-center tabular-nums text-rose-600">{{ $row['belum_tuntas'] }}</td>
                                <td class="px-4 py-3 text-center">
                                    @php $pct = $row['persen_tuntas']; @endphp
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-bold ring-1 {{ $pct >= 80 ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : ($pct >= 60 ? 'bg-amber-50 text-amber-700 ring-amber-200' : 'bg-red-50 text-red-600 ring-red-200') }}">
                                        {{ $pct }}%
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="cetak-footer rounded-b-2xl border-t border-slate-200 bg-slate-50 px-4 py-3">
                <div class="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2">
                    <div class="flex items-center justify-between gap-2 rounded-xl bg-white px-3 py-2 ring-1 ring-slate-200">
                        <span class="text-slate-500">Rata-rata tertinggi</span>
                        <span class="font-bold text-slate-800">
                            {{ $bestMapel ? $bestMapel['nama'].' ('.$angka($bestMapel['rata_rata']).')' : '--' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2 rounded-xl bg-white px-3 py-2 ring-1 ring-slate-200">
                        <span class="text-slate-500">Rata-rata terendah</span>
                        <span class="font-bold text-slate-800">
                            {{ $worstMapel ? $worstMapel['nama'].' ('.$angka($worstMapel['rata_rata']).')' : '--' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        </div>

        {{-- Ketuntasan --}}
        <div data-region="ketuntasan" class="cetak-panel rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:shadow-md">
            <div class="cetak-judul border-b border-slate-100 pb-3">
                <h3 class="text-sm font-bold text-slate-800 sm:text-base">Tingkat Ketuntasan (KKM)</h3>
                <p class="mt-0.5 text-[11px] text-slate-400">
                    Setiap nilai dibandingkan dengan KKM yang dipakai saat menilai, jadi ambang per kelas dan per
                    mata pelajaran ikut terhitung.
                </p>
            </div>

            <div class="mt-4 flex flex-col items-center justify-around gap-6 sm:flex-row">
                <div class="relative h-44 w-44">
                    <canvas id="chartKetuntasan"></canvas>
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                        <span class="text-[11px] font-semibold uppercase text-slate-400">Nilai Tercatat</span>
                        <span class="text-2xl font-bold text-slate-900">{{ number_format($ringkasan['jumlah_nilai'], 0, ',', '.') }}</span>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <span class="h-3 w-3 rounded-full bg-blue-600"></span>
                        <div>
                            <span class="block text-xs font-semibold text-slate-800">Tuntas (&ge; KKM)</span>
                            <span class="text-xs text-slate-500">
                                {{ number_format($ringkasan['tuntas'], 0, ',', '.') }} nilai
                                ({{ $angka($ringkasan['persen_tuntas']) }}%)
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="h-3 w-3 rounded-full bg-sky-200"></span>
                        <div>
                            <span class="block text-xs font-semibold text-slate-800">Belum Tuntas (&lt; KKM)</span>
                            <span class="text-xs text-slate-500">
                                {{ number_format($ringkasan['belum_tuntas'], 0, ',', '.') }} nilai
                                ({{ $ringkasan['persen_tuntas'] === null ? '--' : 100 - $ringkasan['persen_tuntas'] }}%)
                            </span>
                        </div>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-2.5 text-xs text-slate-600 ring-1 ring-slate-100">
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Rata-rata KKM</span>
                        @if ($ringkasan['rata_kkm'] === null)
                            <span class="font-semibold text-slate-400">Belum ada nilai</span>
                        @else
                            <span class="font-semibold text-blue-700">{{ $angka($ringkasan['rata_kkm']) }}</span>
                            <span class="text-slate-400">dari {{ number_format($ringkasan['jumlah_nilai'], 0, ',', '.') }} nilai tercatat</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>

{{-- Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const perKelas = @json($perKelas);
        const labelPerkembangan = @json($perkembanganLabel);
        const nilaiPerkembangan = @json($perkembanganNilai);
        const jumlahPerkembangan = @json($perkembanganJumlah);
        const ringkasan = @json($ringkasan);

        // Semua grafik dikumpulkan di satu daftar agar ukurannya bisa dihitung
        // ulang tepat sebelum dan sesudah dicetak.
        const grafik = [];

        // Label angka di atas batang/titik supaya rata-rata terbaca tanpa hover.
        const labelDiAtas = {
            id: 'labelDiAtas',
            afterDatasetsDraw(chart) {
                const ctx = chart.ctx;
                chart.data.datasets.forEach((dataset, i) => {
                    const meta = chart.getDatasetMeta(i);
                    if (meta.hidden) return;
                    meta.data.forEach((elemen, index) => {
                        const nilai = dataset.data[index];
                        if (nilai === null || nilai === undefined) return;
                        // Titik garis juga punya .x/.y; labelnya digeser lebih tinggi
                        // supaya tidak menabrak garisnya.
                        if (typeof elemen.x !== 'number' || typeof elemen.y !== 'number') return;
                        const y = elemen.type === 'bar' ? elemen.y - 6 : elemen.y - 10;
                        ctx.save();
                        ctx.fillStyle = '#64748b';
                        ctx.font = '600 11px sans-serif';
                        ctx.textAlign = 'center';
                        ctx.fillText(String(nilai).replace('.', ','), elemen.x, y);
                        ctx.restore();
                    });
                });
            }
        };

        // Sumbu nilai selalu 0-100 karena itu rentang nilai rapor; nilai di
        // bawah 60 pun sah jadi tidak dipatok lebih tinggi.
        const sumbuNilai = {
            min: 0,
            max: 100,
            ticks: { stepSize: 20, color: '#94a3b8', font: { size: 11 } },
            grid: { color: '#f1f5f9', borderDash: [4, 4] }
        };

        const sumbuKategori = {
            ticks: { color: '#64748b', font: { size: 11 } },
            grid: { display: false }
        };

        const ctxKelas = document.getElementById('chartNilaiKelas')?.getContext('2d');
        if (ctxKelas) {
            grafik.push(new Chart(ctxKelas, {
                type: 'bar',
                data: {
                    labels: perKelas.map(d => d.kode),
                    datasets: [{
                        label: 'Rata-Rata Nilai',
                        data: perKelas.map(d => d.rata_rata),
                        backgroundColor: '#2563EB',
                        borderRadius: 8,
                        barPercentage: 0.45,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            cornerRadius: 8,
                            callbacks: {
                                // Kelas dipakai bersama beberapa jurusan, jadi nama
                                // jurusan ikut ditampilkan saat hover.
                                title: items => {
                                    const row = perKelas[items[0].dataIndex];
                                    return row ? row.kode + ' - ' + row.nama : '';
                                }
                            }
                        }
                    },
                    scales: { y: sumbuNilai, x: sumbuKategori }
                },
                plugins: [labelDiAtas]
            }));
        }

        const ctxPerkembangan = document.getElementById('chartPerkembangan')?.getContext('2d');
        if (ctxPerkembangan) {
            grafik.push(new Chart(ctxPerkembangan, {
                type: 'line',
                data: {
                    labels: labelPerkembangan,
                    datasets: [{
                        label: 'Rata-Rata Nilai',
                        data: nilaiPerkembangan,
                        borderColor: '#10B981',
                        backgroundColor: '#10B981',
                        tension: 0.3,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        borderWidth: 2.2,
                        // Periode tanpa nilai dilewati, bukan dijatuhkan, supaya
                        // jarak antar titik tetap menunjukkan jarak waktu.
                        spanGaps: true,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            cornerRadius: 8,
                            callbacks: {
                                label: item => {
                                    const jumlah = jumlahPerkembangan[item.dataIndex];
                                    if (item.parsed.y === null) {
                                        return 'Tidak ada nilai tercatat';
                                    }
                                    return 'Rata-rata ' + String(item.parsed.y).replace('.', ',')
                                        + ' dari ' + jumlah + ' nilai';
                                }
                            }
                        }
                    },
                    scales: { y: sumbuNilai, x: sumbuKategori }
                },
                plugins: [labelDiAtas]
            }));
        }

        const ctxKetuntasan = document.getElementById('chartKetuntasan')?.getContext('2d');
        if (ctxKetuntasan) {
            grafik.push(new Chart(ctxKetuntasan, {
                type: 'doughnut',
                data: {
                    labels: ['Tuntas (>= KKM)', 'Belum Tuntas (< KKM)'],
                    datasets: [{
                        data: [ringkasan.tuntas, ringkasan.belum_tuntas],
                        backgroundColor: ['#2563EB', '#BAE6FD'],
                        borderWidth: 0,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '76%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            cornerRadius: 8,
                            callbacks: {
                                label: item => item.label + ': '
                                    + new Intl.NumberFormat('id-ID').format(item.parsed)
                                    + ' nilai'
                            }
                        }
                    }
                }
            }));
        }

        // Chart.js tidak menggambar ulang saat tata letak berubah ke cetak,
        // sehingga kanvas yang lebih lebar dari kolom cetak akan meluber di kertas.
        const gambarUlang = () => grafik.forEach(chart => chart.resize());

        // matchMedia('print') paling tepat karena aktif ketika tata letak cetak sudah berlaku.
        window.matchMedia('print').addEventListener('change', ({ matches }) => {
            if (matches) gambarUlang();
        });

        // afterprint mengembalikan ukuran layar, jadi grafik digambar ulang agar tidak tertinggal ukuran kertas.
        window.addEventListener('afterprint', gambarUlang);
    });
</script>

<style>
    @page {
        /* Margin kertas dipersempit supaya tepi tidak memakan tinggi halaman. */
        margin: 10mm;
    }

    @media print {
        /* Chrome & Firefox tidak otomatis mencetak latar kartu tanpa ini. */
        #laporan-akademik, #laporan-akademik * {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Kerangka dashboard memakai display:none, bukan visibility: visibility
           tetap menyisakan tinggi layout sehingga muncul celah besar di kertas. */
        #app-sidebar,
        #sidebar-backdrop,
        header,
        #logout-modal,
        #site-loading {
            display: none !important;
        }

        body {
            background: #fff !important;
        }
        /* Buang offset sidebar dan batas lebar konten dashboard. */
        body > div.min-h-screen {
            min-height: 0 !important;
            padding-left: 0 !important;
        }
        main {
            max-width: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        form, button, a {
            display: none !important;
        }

        /* Laporan ikut aliran halaman biasa; position absolut justru membuat
           perhitungan tinggi halaman tidak stabil. */
        #laporan-akademik {
            position: static !important;
            padding-bottom: 0 !important;
        }
        /* space-y-6 layar (1.5rem) terlalu lebar di kertas, menghasilkan celah antar kartu. */
        #laporan-akademik > * + * {
            margin-top: 0.55rem !important;
        }

        /* Kartu dan grafik tidak boleh terbelah oleh batas halaman. */
        .cetak-panel {
            break-inside: avoid;
            page-break-inside: avoid;
            padding: 0.7rem 0.85rem !important;
            box-shadow: none !important;
        }

        /* Judul selalu paling tinggi di panelnya. break-after: avoid
           mencegah browser meninggalkannya sendirian di bawah halaman.
           Padding layar (p-4 / sm:p-5) dibuang karena panel cetak sudah
           punya padding sendiri. */
        .cetak-judul {
            break-after: avoid;
            page-break-after: avoid;
            padding: 0 0 0.4rem !important;
        }

        /* Footer tabel di layar berlatar abu-abu dengan padding sendiri;
           di kertas cukup garis atas tanpa latar dan tanpa padding ganda. */
        .cetak-footer {
            padding: 0.4rem 0 0 !important;
            background: transparent !important;
            border-radius: 0 !important;
        }

        /* Ringkasan dan grafik disusun dua kolom supaya tingginya pendek
           dan tidak perlu dilempar ke halaman baru. */
        .cetak-grid-2,
        .cetak-grid-1 {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 0.5rem !important;
        }

        /* Rapatkan jarak umum di dalam laporan agar tidak boros kertas. */
        #laporan-akademik .mt-4 { margin-top: 0.45rem !important; }
        #laporan-akademik .mt-3 { margin-top: 0.35rem !important; }
        #laporan-akademik .mt-2 { margin-top: 0.25rem !important; }
        #laporan-akademik .mt-1\.5 { margin-top: 0.2rem !important; }
        #laporan-akademik .pt-3 { padding-top: 0.4rem !important; }
        #laporan-akademik .pb-3 { padding-bottom: 0.4rem !important; }
        #laporan-akademik .gap-5 { gap: 0.5rem !important; }
        #laporan-akademik .gap-6 { gap: 0.75rem !important; }
        #laporan-akademik .gap-3 { gap: 0.4rem !important; }
        #laporan-akademik .h-64 { height: 11rem !important; }
        #laporan-akademik .h-44 { height: 9rem !important; }
        /* Grid dua tabel rekap memakai space-y-5 di layar. */
        [data-region="rekap"] > * + * {
            margin-top: 0.55rem !important;
        }

        /* Tabel rekap boleh panjang. Baris dan judul kolomnya tetap utuh
           dan judul kolom diulang kalau tabelnya harus terbelah. */
        .cetak-tabel {
            overflow: visible !important;
            margin-top: 0.5rem !important;
            break-inside: auto;
            page-break-inside: auto;
        }
        .cetak-tabel table {
            width: 100% !important;
            min-width: 0 !important;
            font-size: 10px;
            /* Garis tabel dibuat eksplisit supaya tidak menghilang di kertas
               (divide-y berbasis warna dan sering tidak tercetak). */
            border-collapse: collapse;
        }
        .cetak-tabel thead {
            /* Judul kolom ikut diulang kalau tabelnya memang terpecah. */
            display: table-header-group;
        }
        .cetak-tabel tr, .cetak-tabel th, .cetak-tabel td {
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .cetak-tabel th {
            border: 1px solid #cbd5e1;
            background: #f1f5f9 !important;
            color: #334155 !important;
            font-weight: 700;
            text-align: left;
            vertical-align: middle;
            /* Judul kolom di layar huruf kapital kecil; di kertas dibiarkan
               seperti semula supaya tidak terlalu rapat. */
            text-transform: none;
            letter-spacing: normal;
            font-size: 10px;
        }
        .cetak-tabel td {
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .cetak-tabel th, .cetak-tabel td {
            padding: 0.3rem 0.4rem !important;
            line-height: 1.35;
            /* Teks panjang membungkus, bukan memaksa tabel melebar dan
               keluar dari area cetak. */
            white-space: normal;
            overflow-wrap: anywhere;
        }
        .cetak-nowrap {
            white-space: nowrap !important;
        }
        /* Kolom angka rata kanan / tengah tetap konsisten di kertas. */
        .cetak-tabel th.text-center, .cetak-tabel td.text-center {
            text-align: center !important;
        }
        .cetak-tabel th.text-right, .cetak-tabel td.text-right {
            text-align: right !important;
        }

        img, canvas, svg {
            max-width: 100%;
        }
    }
</style>
@endsection