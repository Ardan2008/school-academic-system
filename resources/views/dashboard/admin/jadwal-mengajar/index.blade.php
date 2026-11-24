@extends('layouts.dashboard')

@section('title', 'Jadwal Mengajar')
@section('subtitle', 'Dashboard Admin')

@section('content')
    <x-dashboard.animations />

    @php
        $periodeLabel = \App\Support\Periode::label($periode['tahun_ajaran'], $periode['semester']);
        $bisaTambah = $guruOptions->isNotEmpty()
            && collect($penugasanOptions)->contains(fn (array $item) => $item['pairs'] !== []);
        $itemsTampil = $items;
        $perHariTampil = collect($hariAktif)
            ->mapWithKeys(fn (string $nama) => [
                $nama => collect($itemsTampil)->where('hari', $nama)->values()->all(),
            ])
            ->all();
        $jsonItems = json_encode($itemsTampil, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $jsonSemuaItems = json_encode($analysis['items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $jsonHari = json_encode($hariAktif, JSON_UNESCAPED_UNICODE);
        $jsonSesi = json_encode(
            collect($sesiGrid)->map(fn ($sesi) => [
                'id' => (int) $sesi->id,
                'nama' => $sesi->nama,
                'jenis' => $sesi->jenis,
                'mulai' => substr((string) $sesi->jam_mulai, 0, 5),
                'selesai' => substr((string) $sesi->jam_selesai, 0, 5),
            ])->values()->all(),
            JSON_UNESCAPED_UNICODE
        );
        $jsonTanggal = json_encode($tanggalPekan, JSON_UNESCAPED_UNICODE);
        $jsonBentrok = json_encode($bentrokGroups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $jsonSaran = json_encode($saran, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $statsTampil = collect($stats)->map(function (array $stat) use ($itemsTampil, $hariIni): array {
            $stat['value'] = match ($stat['key']) {
                'total' => count($itemsTampil),
                'kelas' => collect($itemsTampil)->pluck('rombel_key')->filter()->unique()->count(),
                'mapel' => collect($itemsTampil)->pluck('mapel_id')->filter()->unique()->count(),
                'guru' => collect($itemsTampil)->pluck('guru_id')->filter()->unique()->count(),
                default => $stat['value'],
            };

            return $stat;
        })->all();
        $adaMasalah = count($saran) > 0 || count($bentrokGroups) > 0 || count($catatanPerhatian) > 0;
        $queryExport = array_filter([
            'tahun_ajaran' => $periode['tahun_ajaran'],
            'semester' => $periode['semester'],
            'guru_id' => $filters['guru_id'] > 0 ? $filters['guru_id'] : null,
            'rombel' => $filters['rombel'],
            'mapel_id' => $filters['mapel_id'] > 0 ? $filters['mapel_id'] : null,
            'ruang' => $filters['ruang'],
            'hari' => $hari,
            'status' => $filters['status'],
            'cari' => $filters['cari'],
        ], fn ($nilai) => $nilai !== null && $nilai !== '');
    @endphp

    <div id="jadwal-admin-page"
         class="jadwal-guru-page"
         data-jadwal-page
         data-role="admin"
         data-dashboard
         data-store-url="{{ route($routeNames['store']) }}"
         data-update-template="{{ route($routeNames['update'], '__ID__') }}"
         data-intent-url="{{ route($routeNames['intent']) }}"
         data-buka-modal="{{ ($bukaModal || $errors->any()) ? '1' : '0' }}"
         data-errors-any="{{ $errors->any() ? '1' : '0' }}"
         data-mode-awal="{{ $formJadwal ? 'edit' : 'tambah' }}"
         data-guru-awal-id="{{ $formJadwal?->guru_id ?? '' }}"
         data-jam-lama="{{ $formJadwal ? substr((string) $formJadwal->jam_mulai, 0, 5).'–'.substr((string) $formJadwal->jam_selesai, 0, 5) : '' }}"
         data-mapel-awal-id="{{ $mapelForm?->id ?? '' }}"
         data-mapel-awal-nama="{{ $mapelForm?->nama ?? '' }}"
         data-old-guru-id="{{ old('guru_id', '') }}"
         data-old-mapel-id="{{ old('mata_pelajaran_id', '') }}"
         data-old-rombel="{{ old('rombel', '') }}"
         data-jam-masuk="{{ $jamOperasional['mulai'] }}"
         data-jam-selesai="{{ $jamOperasional['selesai'] }}"
         data-items="{{ $jsonItems }}"
         data-semua-items="{{ $jsonSemuaItems }}"
         data-hari-aktif="{{ $jsonHari }}"
         data-sesi="{{ $jsonSesi }}"
         data-tanggal="{{ $jsonTanggal }}"
         data-bentrok="{{ $jsonBentrok }}"
         data-saran="{{ $jsonSaran }}"
         data-hari-ini="{{ $hariIni ?? '' }}"
         data-hari-awal="{{ $hari ?? '' }}"
         data-now="{{ $nowLabel }}"
         data-today-iso="{{ $todayIso }}"
         data-week-start="{{ $pekanStartIso }}">

        @if (session('success'))
            <div class="jg-toast" data-flash-toast role="status">
                <x-lucide-circle-check class="mr-2 inline h-4 w-4 text-[#0F6B3C]" aria-hidden="true" />
                {{ session('success') }}
            </div>
        @endif

        <div class="jg-controls flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="jg-eyebrow">Periode Pembelajaran</p>
                <button type="button" class="jg-btn jg-btn-outline mt-1.5" data-periode-button aria-label="Periode pembelajaran {{ $periodeLabel }}">
                    <x-lucide-calendar-days class="h-4 w-4 text-[var(--jg-blue)]" aria-hidden="true" />
                    {{ $periodeLabel }}
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="jg-segmented" role="group" aria-label="Pilihan tampilan jadwal">
                    <button type="button" class="jg-seg" data-view-pilih="minggu" aria-pressed="true">Minggu</button>
                    <button type="button" class="jg-seg" data-view-pilih="hari" aria-pressed="false">Hari</button>
                    <button type="button" class="jg-seg" data-view-pilih="daftar" aria-pressed="false">Daftar</button>
                </div>
                <button type="button"
                        id="jadwal-ekspor"
                        data-ekspor
                        data-state="idle"
                        aria-live="polite"
                        data-export-url="{{ route($routeNames['export'], $queryExport) }}"
                        title="Unduh seluruh jadwal mengajar sesuai filter dalam format Excel"
                        class="jg-ekspor relative inline-flex h-10 shrink-0 items-center justify-center gap-2 overflow-hidden whitespace-nowrap rounded-lg border border-blue-300 bg-white px-4 text-xs font-medium text-primary-60 transition hover:bg-blue-50 sm:h-9 sm:text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60">
                    <span data-ekspor-kilau class="jg-ekspor-kilau" aria-hidden="true"></span>
                    <x-lucide-download data-ekspor-ikon="idle" class="jg-ekspor-ikon h-4 w-4" aria-hidden="true" />
                    <svg data-ekspor-ikon="muat" class="jg-ekspor-ikon h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                        <path d="M21 12a9 9 0 1 1-6.219-8.56" />
                    </svg>
                    <x-lucide-check data-ekspor-ikon="sukses" class="jg-ekspor-ikon h-4 w-4" aria-hidden="true" />
                    <span data-ekspor-label>Export Excel</span>
                </button>
                <button type="button" class="jg-btn jg-btn-primary" data-tambah-jadwal-trigger @disabled(! $bisaTambah)>
                    <x-lucide-plus class="h-4 w-4" aria-hidden="true" />
                    Tambah Jadwal
                </button>
            </div>
        </div>

        <form method="GET" action="{{ route($routeNames['index']) }}" class="jg-card mt-4 p-4">
            <input type="hidden" name="tahun_ajaran" value="{{ $periode['tahun_ajaran'] }}">
            <input type="hidden" name="semester" value="{{ $periode['semester'] }}">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label for="filter-guru" class="mb-1.5 block text-xs font-bold jg-title">Guru</label>
                    <select id="filter-guru" name="guru_id" class="jg-select w-full">
                        <option value="">Semua guru</option>
                        @foreach ($guruOptions as $opsiGuru)
                            <option value="{{ $opsiGuru->id }}" @selected($filters['guru_id'] === (int) $opsiGuru->id)>{{ $opsiGuru->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter-mapel" class="mb-1.5 block text-xs font-bold jg-title">Mata pelajaran</label>
                    <select id="filter-mapel" name="mapel_id" class="jg-select w-full">
                        <option value="">Semua mata pelajaran</option>
                        @foreach ($mapelOptions as $opsiMapel)
                            <option value="{{ $opsiMapel->id }}" @selected($filters['mapel_id'] === (int) $opsiMapel->id)>{{ $opsiMapel->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter-rombel" class="mb-1.5 block text-xs font-bold jg-title">Rombel</label>
                    <select id="filter-rombel" name="rombel" class="jg-select w-full">
                        <option value="">Semua rombel</option>
                        @foreach ($rombelOptions as $opsiRombel)
                            <option value="{{ $opsiRombel['value'] }}" @selected($filters['rombel'] === $opsiRombel['value'])>{{ $opsiRombel['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter-hari" class="mb-1.5 block text-xs font-bold jg-title">Hari</label>
                    <select id="filter-hari" name="hari" class="jg-select w-full">
                        <option value="">Semua hari</option>
                        @foreach ($hariAktif as $namaHari)
                            <option value="{{ $namaHari }}" @selected($filters['hari'] === $namaHari)>{{ $namaHari }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter-ruang" class="mb-1.5 block text-xs font-bold jg-title">Ruang</label>
                    <input id="filter-ruang" type="text" name="ruang" value="{{ $filters['ruang'] }}" placeholder="Contoh: R.123" class="jg-input w-full">
                </div>
                <div>
                    <label for="filter-status" class="mb-1.5 block text-xs font-bold jg-title">Status</label>
                    <select id="filter-status" name="status" class="jg-select w-full">
                        <option value="">Semua status</option>
                        <option value="sesuai" @selected($filters['status'] === 'sesuai')>Sesuai</option>
                        <option value="perlu_disesuaikan" @selected($filters['status'] === 'perlu_disesuaikan')>Perlu disesuaikan</option>
                        <option value="bentrok" @selected($filters['status'] === 'bentrok')>Bentrok</option>
                        <option value="luar" @selected($filters['status'] === 'luar')>Di luar sesi</option>
                    </select>
                </div>
                <div class="sm:col-span-2 xl:col-span-2">
                    <label for="filter-cari" class="mb-1.5 block text-xs font-bold jg-title">Cari</label>
                    <div class="flex gap-2">
                        <input id="filter-cari" type="search" name="cari" value="{{ $filters['cari'] }}" placeholder="Cari mata pelajaran, guru, kelas, atau ruang..." class="jg-input w-full" data-cari="utama">
                        <button type="submit" class="jg-btn jg-btn-outline shrink-0" data-cari-terapkan>Terapkan</button>
                    </div>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <label for="filter-periode" class="text-xs font-bold jg-title">Periode lain</label>
                <select id="filter-periode" class="jg-select jg-compact-select" onchange="if (this.value) { const [a, s] = this.value.split('|'); document.querySelector('input[name=tahun_ajaran]').value = a; document.querySelector('input[name=semester]').value = s; this.form.submit(); }">
                    <option value="">Pilih periode</option>
                    @foreach ($periodeOptions as $opsiPeriode)
                        <option value="{{ $opsiPeriode['tahun_ajaran'] }}|{{ $opsiPeriode['semester'] }}"
                            @selected($opsiPeriode['tahun_ajaran'] === $periode['tahun_ajaran'] && $opsiPeriode['semester'] === $periode['semester'])>
                            {{ $opsiPeriode['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>

        <div class="mt-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
            @foreach ($statsTampil as $stat)
                <div class="jg-card p-4" data-reveal>
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="jg-eyebrow truncate">{{ $stat['label'] }}</p>
                            <p class="jg-stat-value mt-2" data-stat="{{ $stat['key'] }}" data-count="{{ $stat['value'] }}">{{ $stat['value'] }}</p>
                        </div>
                        <span class="jg-stat-icon shrink-0" style="background:{{ $stat['latar'] }}; color:{{ $stat['warna'] }};">
                            <x-dynamic-component :component="'lucide-'.$stat['icon']" class="h-5 w-5" aria-hidden="true" />
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($adaMasalah)
            <div class="jg-banner mt-4 px-4 py-3.5 sm:px-5" role="status">
                <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" style="background:#FFF1D6; color:#7A4300;">
                            <x-lucide-triangle-alert class="h-4 w-4" aria-hidden="true" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold jg-title">
                                <span>{{ count($saran) }}</span> jadwal perlu disesuaikan dengan jam belajar terbaru
                            </p>
                            <p class="mt-0.5 truncate text-xs jg-muted">
                                Ditemukan <span>{{ count($bentrokGroups) }}</span> kelompok bentrok pada filter ini.
                            </p>
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <button type="button" class="jg-btn jg-btn-primary min-h-10" data-buka-penyesuaian>
                            <x-lucide-settings-2 class="h-4 w-4" aria-hidden="true" />
                            Lihat detail
                        </button>
                        <button type="button" class="jg-btn jg-btn-outline min-h-10" data-lihat-bentrok>
                            <x-lucide-alert-triangle class="h-4 w-4" aria-hidden="true" />
                            Lihat bentrok
                        </button>
                    </div>
                </div>
            </div>
        @endif

        @if (($mode ?? 'guru') === 'admin')
            <div class="jg-card mt-5 flex flex-col items-center justify-center px-6 py-14 text-center jg-cari-kosong"
                 data-cari-kosong-global hidden>
                <x-lucide-search class="h-7 w-7" aria-hidden="true" />
                <h2 class="mt-4 text-base font-bold jg-title">Jadwal tidak ditemukan</h2>
                <p class="mt-1.5 max-w-md text-xs leading-relaxed jg-muted">
                    Tidak ada jadwal yang cocok dengan kata kunci pencarian. Coba gunakan kata kunci lain.
                </p>
            </div>
        @endif

        @if (empty($items))
            <div class="jg-card mt-5 flex flex-col items-center justify-center px-6 py-14 text-center" data-cari-konten>
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl" style="background:#E8F1FF; color:#1A6BDB;">
                    <x-lucide-calendar-days class="h-7 w-7" aria-hidden="true" />
                </span>
                <h2 class="mt-4 text-base font-bold jg-title">Tidak Ada Jadwal</h2>
                <p class="mt-1.5 max-w-md text-xs leading-relaxed jg-muted">
                    Tidak ada jadwal yang cocok dengan filter pada periode ini.
                </p>
                <button type="button" class="jg-btn jg-btn-primary mt-4" data-tambah-jadwal-trigger @disabled(! $bisaTambah)>
                    <x-lucide-plus class="h-4 w-4" aria-hidden="true" />
                    Tambah Jadwal
                </button>
            </div>
        @else
            <div class="jg-content-shell mt-5" data-cari-konten>
                <div class="jg-main-column min-w-0 space-y-4">
                    @include('dashboard.guru.jadwal-pelajaran.partials.grid', [
                        'items' => $itemsTampil,
                        'perHariTampil' => $perHariTampil,
                        'bisaKelola' => true,
                        'mode' => 'admin',
                    ])
                    @include('dashboard.guru.jadwal-pelajaran.partials.hari', [
                        'items' => $itemsTampil,
                        'perHariTampil' => $perHariTampil,
                        'hariAwal' => $hari ?? '',
                        'bisaKelola' => true,
                        'mode' => 'admin',
                    ])
                    @include('dashboard.guru.jadwal-pelajaran.partials.daftar', [
                        'items' => $itemsTampil,
                        'perHariTampil' => $perHariTampil,
                        'hariAwal' => $hari ?? '',
                        'bisaKelola' => true,
                        'mode' => 'admin',
                    ])
                    @include('dashboard.guru.jadwal-pelajaran.partials.mobile', [
                        'items' => $itemsTampil,
                        'perHariTampil' => $perHariTampil,
                        'hariAwal' => $hari ?? '',
                        'bisaKelola' => true,
                        'mode' => 'admin',
                    ])
                </div>

                <aside class="jg-side-column min-w-0">
                    @include('dashboard.guru.jadwal-pelajaran.partials.panel-kanan', [
                        'items' => $itemsTampil,
                        'mode' => 'admin',
                    ])
                </aside>
            </div>
        @endif

        @include('dashboard.guru.jadwal-pelajaran.partials.penyesuaian', [
            'saran' => $saran,
            'bentrokGroups' => $bentrokGroups,
            'bisaKelola' => true,
            'mode' => 'admin',
        ])
        @include('dashboard.admin.jadwal-mengajar.partials.form', ['items' => $itemsTampil])
    </div>
@endsection
