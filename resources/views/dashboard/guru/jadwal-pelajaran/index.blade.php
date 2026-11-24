@extends('layouts.dashboard')

@section('title', 'Jadwal Mengajar')
@section('subtitle', 'Dashboard Guru')

@section('content')
    <x-dashboard.animations />

    @php
        $periodeLabel = \App\Support\Periode::label($periode['tahun_ajaran'], $periode['semester']);
        $bisaTambah = $mapel->isNotEmpty() && $rombelOptions->isNotEmpty();
        $bukaModalAwal = $bukaModal || $errors->any();
        $itemsTampil = $hari !== null
            ? array_values(array_filter($items, fn (array $item) => $item['hari'] === $hari))
            : $items;
        $perHariTampil = collect($hariAktif)
            ->mapWithKeys(fn (string $nama) => [
                $nama => collect($itemsTampil)->where('hari', $nama)->values()->all(),
            ])
            ->all();
        $saranTampil = $hari !== null
            ? array_values(array_filter($saran, fn (array $item) => $item['hari'] === $hari))
            : $saran;
        $bentrokTampil = $hari !== null
            ? array_values(array_filter($bentrokGroups, fn (array $grup) => $grup['hari'] === $hari))
            : $bentrokGroups;
        $jsonItems = json_encode($itemsTampil, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
        $jsonBentrok = json_encode($bentrokTampil, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $jsonSaran = json_encode($saranTampil, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $statsTampil = collect($stats)->map(function (array $stat) use ($itemsTampil, $hariIni): array {
            $stat['value'] = match ($stat['key']) {
                'total' => count($itemsTampil),
                'kelas' => collect($itemsTampil)->pluck('rombel_key')->filter()->unique()->count(),
                'mapel' => collect($itemsTampil)->pluck('mapel_id')->filter()->unique()->count(),
                'hari_ini' => collect($itemsTampil)->where('hari', $hariIni)->count(),
                default => $stat['value'],
            };

            return $stat;
        })->all();
        $adaMasalah = count($saranTampil) > 0 || count($bentrokTampil) > 0 || count($catatanPerhatian) > 0;
    @endphp

    <div id="jadwal-guru-page"
         class="jadwal-guru-page"
         data-jadwal-page
         data-role="guru"
         data-dashboard
         data-buka-modal="0"
         data-mode-awal="{{ $formJadwal ? 'edit' : 'tambah' }}"
         data-jam-lama="{{ $formJadwal ? substr((string) $formJadwal->jam_mulai, 0, 5).'–'.substr((string) $formJadwal->jam_selesai, 0, 5) : '' }}"
         data-mapel-awal-id="{{ $mapelForm?->id ?? '' }}"
         data-mapel-awal-nama="{{ $mapelForm?->nama ?? '' }}"
         data-jam-masuk="{{ $jamOperasional['mulai'] }}"
         data-jam-selesai="{{ $jamOperasional['selesai'] }}"
         data-items="{{ $jsonItems }}"
         data-semua-items="{{ $jsonItems }}"
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
            <div data-flash-toast class="hidden">{{ session('success') }}</div>
        @endif

        {{-- B. Baris kontrol --}}
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
                         data-export-url="{{ route($routeNames['export'], $hari ? ['hari' => $hari] : []) }}"

                        title="Unduh jadwal mengajar periode berjalan dalam format Excel"
                        class="jg-ekspor relative inline-flex h-10 shrink-0 items-center justify-center gap-2 overflow-hidden whitespace-nowrap rounded-lg border border-blue-300 bg-white px-4 text-xs font-medium text-primary-60 transition hover:bg-blue-50 sm:h-9 sm:text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60">
                    <span data-ekspor-kilau class="jg-ekspor-kilau" aria-hidden="true"></span>
                    <x-lucide-download data-ekspor-ikon="idle" class="jg-ekspor-ikon h-4 w-4" aria-hidden="true" />
                    <svg data-ekspor-ikon="muat" class="jg-ekspor-ikon h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                        <path d="M21 12a9 9 0 1 1-6.219-8.56" />
                    </svg>
                    <x-lucide-check data-ekspor-ikon="sukses" class="jg-ekspor-ikon h-4 w-4" aria-hidden="true" />
                    <span data-ekspor-label>Export Excel</span>
                </button>
                @if ($bisaKelola)
                    <button type="button" class="jg-btn jg-btn-primary" data-tambah-jadwal-trigger @disabled(! $bisaTambah)>
                        <x-lucide-plus class="h-4 w-4" aria-hidden="true" />
                        Tambah Jadwal
                    </button>
                @endif
            </div>
        </div>

        @if ($bisaKelola && ! $bisaTambah)
            <p class="mt-2 text-xs text-[#7A4300]">
                Penugasan mata pelajaran dan rombel belum tersedia, sehingga jadwal belum dapat ditambahkan.
            </p>
        @endif

        {{-- C. Statistik --}}
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

        {{-- D. Banner tindakan --}}
        @if ($adaMasalah)
            <div class="jg-banner mt-4 px-4 py-3.5 sm:px-5" role="status">
                <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" style="background:#FFF1D6; color:#7A4300;">
                            <x-lucide-triangle-alert class="h-4 w-4" aria-hidden="true" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold jg-title">
                                <span data-banner-saran>{{ count($saranTampil) }}</span> jadwal perlu disesuaikan dengan jam belajar terbaru
                            </p>
                            <p class="mt-0.5 truncate text-xs jg-muted">
                                Ditemukan <span data-banner-bentrok>{{ count($bentrokTampil) }}</span> kelompok bentrok yang perlu diputuskan sesi penggantinya.
                            </p>
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        @if ($bisaKelola)
                            <button type="button" class="jg-btn jg-btn-primary min-h-10" data-buka-penyesuaian>
                                <x-lucide-settings-2 class="h-4 w-4" aria-hidden="true" />
                                Lihat detail
                            </button>
                        @endif
                        <button type="button" class="jg-btn jg-btn-outline min-h-10" data-lihat-bentrok>
                            <x-lucide-alert-triangle class="h-4 w-4" aria-hidden="true" />
                            Lihat bentrok
                        </button>
                    </div>
                </div>

                @if (count($catatanPerhatian) > 0)
                    <ul class="sr-only" data-banner-detail>
                        @foreach ($catatanPerhatian as $catatan)
                            <li>{{ $catatan }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        {{-- E. Dua kolom --}}
        @if (empty($items))
            <div class="jg-card mt-5 flex flex-col items-center justify-center px-6 py-14 text-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl" style="background:#E8F1FF; color:#1A6BDB;">
                    <x-lucide-calendar-days class="h-7 w-7" aria-hidden="true" />
                </span>
                <h2 class="mt-4 text-base font-bold jg-title">Belum Ada Jadwal Mengajar</h2>
                <p class="mt-1.5 max-w-md text-xs leading-relaxed jg-muted">
                    {{ $bisaKelola ? 'Belum ada jadwal pada periode ini. Tambahkan jadwal pertama untuk mulai mengisi minggu pembelajaran.' : 'Belum ada jadwal mengajar pada periode ini.' }}
                </p>
                @if ($bisaKelola)
                    <button type="button" class="jg-btn jg-btn-primary mt-4" data-tambah-jadwal-trigger @disabled(! $bisaTambah)>
                        <x-lucide-plus class="h-4 w-4" aria-hidden="true" />
                        Tambah Jadwal
                    </button>
                @endif
            </div>
        @else
            <div class="jg-content-shell mt-5">
                <div class="jg-main-column min-w-0 space-y-4">
                    @include('dashboard.guru.jadwal-pelajaran.partials.grid', [
                        'items' => $itemsTampil,
                        'perHariTampil' => $perHariTampil,
                    ])
                    @include('dashboard.guru.jadwal-pelajaran.partials.hari', [
                        'items' => $itemsTampil,
                        'perHariTampil' => $perHariTampil,
                        'hariAwal' => $hari ?? '',
                    ])
                    @include('dashboard.guru.jadwal-pelajaran.partials.daftar', [
                        'items' => $itemsTampil,
                        'perHariTampil' => $perHariTampil,
                        'hariAwal' => $hari ?? '',
                    ])
                    @include('dashboard.guru.jadwal-pelajaran.partials.mobile', [
                        'items' => $itemsTampil,
                        'perHariTampil' => $perHariTampil,
                        'hariAwal' => $hari ?? '',
                    ])
                </div>

                <aside class="jg-side-column min-w-0">
                    @include('dashboard.guru.jadwal-pelajaran.partials.panel-kanan', ['items' => $itemsTampil])
                </aside>
            </div>
        @endif

        @if ($bisaKelola)
            @include('dashboard.guru.jadwal-pelajaran.partials.penyesuaian', [
                'saran' => $saranTampil,
                'bentrokGroups' => $bentrokTampil,
            ])
            @include('dashboard.guru.jadwal-pelajaran.partials.form', ['items' => $itemsTampil])
        @endif
    </div>
@endsection
