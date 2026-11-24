@php
    $bisaKelola = $bisaKelola ?? true;
    $transaction = \App\Models\SesiJamBelajar::JENIS_PELAJARAN;
    $cocokSesi = function (array $item, $sesi, string $mulaiSesi, string $selesaiSesi): bool {
        if (($item['di_luar_sesi'] ?? false)) {
            return false;
        }

        if ($item['sesi_id'] !== null && (int) $item['sesi_id'] === (int) $sesi->id) {
            return true;
        }

        return $item['sesi_id'] === null
            && $item['mulai'] >= $mulaiSesi
            && $item['selesai'] <= $selesaiSesi;
    };
    $itemsLuar = collect($hariAktif)
        ->mapWithKeys(fn (string $nama) => [
            $nama => collect($perHariTampil[$nama] ?? [])
                ->filter(fn (array $item) => ($item['di_luar_sesi'] ?? false))
                ->values(),
        ]);
    $adaItemsLuar = $itemsLuar->contains(fn ($kumpulan) => $kumpulan->isNotEmpty());
    $teksCariAdmin = fn (array $itemCari): string => strtolower(implode(' ', [
        (string) ($itemCari['guru'] ?? ''),
        (string) ($itemCari['mapel'] ?? ''),
        (string) ($itemCari['kelas'] ?? ''),
        (string) ($itemCari['jurusan'] ?? ''),
        (string) ($itemCari['jurusan_singkat'] ?? ''),
        (string) ($itemCari['ruang'] ?? ''),
        str_replace('|', ' ', (string) ($itemCari['rombel_key'] ?? '')),
        trim((string) ($itemCari['kelas'] ?? '').' '.(string) ($itemCari['jurusan_singkat'] ?? '')),
        trim((string) ($itemCari['jurusan_singkat'] ?? '').' '.(string) ($itemCari['kelas'] ?? '')),
        trim((string) ($itemCari['kelas'] ?? '').' '.(string) ($itemCari['jurusan'] ?? '')),
    ]));
@endphp

<section data-view-panel="minggu">
    <div class="jg-card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-[var(--jg-line)] px-4 py-4 sm:px-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <button type="button" class="jg-icon-btn" data-week-prev aria-label="Pekan sebelumnya">
                        <x-lucide-chevron-left class="h-4 w-4" />
                    </button>
                    <div class="min-w-[168px] max-w-[280px] text-center">
                        <p class="truncate text-sm font-semibold jg-title" data-week-label>{{ $pekanLabel }}</p>
                        <p class="text-xs jg-muted">Pekan pembelajaran</p>
                    </div>
                    <button type="button" class="jg-icon-btn" data-week-next aria-label="Pekan berikutnya">
                        <x-lucide-chevron-right class="h-4 w-4" />
                    </button>
                    <button type="button" class="jg-btn jg-btn-outline min-h-9! px-3! text-xs!" data-week-today>Hari ini</button>
                </div>

                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <label class="sr-only" for="filter-kelas-select">Filter kelas</label>
                    <select id="filter-kelas-select" class="jg-select jg-compact-select jg-filter-select" data-filter-kelas>
                        <option value="">Semua kelas</option>
                        @foreach ($kelas as $opsiKelas)
                            <option value="{{ $opsiKelas['jurusan'].'|'.$opsiKelas['kelas'] }}">
                                {{ $opsiKelas['kelas'] }}{{ $opsiKelas['jurusan'] !== '' ? ' · '.$opsiKelas['jurusan'] : '' }}
                            </option>
                        @endforeach
                    </select>

                    <label class="sr-only" for="filter-status-select">Filter status</label>
                    <select id="filter-status-select" class="jg-select jg-compact-select jg-filter-select" data-filter-status>
                        <option value="">Semua status</option>
                        <option value="sesuai">Sesuai</option>
                        <option value="perlu_disesuaikan">Perlu disesuaikan</option>
                        <option value="bentrok">Bentrok</option>
                        <option value="luar">Di luar sesi</option>
                    </select>

                    <label class="jg-toggle">
                        <input type="checkbox" data-toggle-empty-session>
                        Tampilkan sesi kosong
                    </label>
                </div>
            </div>
        </div>

        <div class="jg-grid-scroll">
            <div class="jg-grid" style="--jg-hari: {{ count($hariAktif) }};" role="table" aria-label="Jadwal mingguan">
                <div class="jg-grid-head jg-eyebrow" role="columnheader">Sesi</div>
                @foreach ($hariAktif as $namaHari)
                    @php
                        $jumlahHari = count($perHariTampil[$namaHari] ?? []);
                    @endphp
                    <div class="jg-grid-head jg-day-head {{ $namaHari === $hariIni ? 'jg-grid-head-today' : '' }}"
                         role="columnheader"
                         data-day-head="{{ $namaHari }}"
                         title="{{ $namaHari }}, {{ $tanggalPekan[$namaHari] ?? '' }} · {{ $jumlahHari }} jadwal">
                        <div class="jg-day-headline">
                            <span class="jg-day-name">{{ $namaHari }}</span>
                            @if ($namaHari === $hariIni)
                                <span class="jg-today-badge" data-today-badge>Hari ini</span>
                            @endif
                        </div>
                        <p class="jg-day-date-line jg-muted">
                            <span data-day-date="{{ $namaHari }}">{{ $tanggalPekan[$namaHari] ?? '' }}</span>
                            <span class="jg-day-count-separator">·</span>
                            <span class="jg-day-count" data-day-count="{{ $namaHari }}">{{ $jumlahHari }}</span>
                            <span class="jg-day-count">jadwal</span>
                        </p>
                    </div>
                @endforeach

                @foreach ($sesiGrid as $sesi)
                    @php
                        $mulaiSesi = substr((string) $sesi->jam_mulai, 0, 5);
                        $selesaiSesi = substr((string) $sesi->jam_selesai, 0, 5);
                    @endphp

                    @if ($sesi->jenis !== $transaction)
                        <div class="jg-break" role="row">
                            {{ $sesi->nama }} · {{ $mulaiSesi }}–{{ $selesaiSesi }}
                        </div>
                    @else
                        @php
                            $nomorSesi = $sesi->nomor_pelajaran ?: $sesi->urutan;
                            $adaIsiSesi = collect($hariAktif)->contains(function (string $namaHari) use ($perHariTampil, $cocokSesi, $sesi, $mulaiSesi, $selesaiSesi): bool {
                                return collect($perHariTampil[$namaHari] ?? [])->contains(fn (array $item) => $cocokSesi($item, $sesi, $mulaiSesi, $selesaiSesi));
                            });
                            $memuatSekarang = $nowLabel >= $mulaiSesi && $nowLabel <= $selesaiSesi;
                            $tampilkanSesiAwal = $adaIsiSesi || $memuatSekarang;
                        @endphp
                        <div class="jg-grid-head jg-session-head"
                             role="rowheader"
                             data-sesi-baris="{{ $sesi->id }}"
                             @if (! $tampilkanSesiAwal) hidden @endif>
                            <p class="text-xs font-bold jg-title">Sesi {{ $nomorSesi }}</p>
                            <p class="text-xs tabular-nums jg-muted">{{ $mulaiSesi }}–{{ $selesaiSesi }}</p>
                        </div>

                        @foreach ($hariAktif as $namaHari)
                            @php
                                $cellItems = collect($perHariTampil[$namaHari] ?? [])
                                    ->filter(fn (array $item) => $cocokSesi($item, $sesi, $mulaiSesi, $selesaiSesi))
                                    ->values();
                                $grupBentrok = collect($bentrokGroups)->first(function (array $grup) use ($namaHari, $cellItems): bool {
                                    return $grup['hari'] === $namaHari
                                        && collect($grup['items'])->contains(
                                            fn (array $item) => $cellItems->pluck('id')->contains($item['id'])
                                        );
                                });
                            @endphp
                            <div class="jg-grid-cell {{ $namaHari === $hariIni ? 'jg-grid-cell-today' : '' }}"
                                 role="cell"
                                 data-grid-cell
                                 data-sesi-baris="{{ $sesi->id }}"
                                 data-cell-jenis="sesi"
                                 data-cell-hari="{{ $namaHari }}"
                                 data-cell-mulai="{{ $mulaiSesi }}"
                                 data-cell-selesai="{{ $selesaiSesi }}"
                                 @if (! $tampilkanSesiAwal) hidden @endif>
                                @if ($cellItems->count() > 1)
                                    @php
                                        $teksCariKonflik = ($mode ?? 'guru') === 'admin'
                                            ? $cellItems->map($teksCariAdmin)->implode(' ')
                                            : '';
                                    @endphp
                                    <div class="jg-conflict-box"
                                         data-conflict-box
                                         data-status="bentrok"
                                         data-jumlah="{{ $cellItems->count() }}"
                                         data-hari="{{ $namaHari }}"
                                         data-kelas-key="*"
                                         data-conflict-hari="{{ $namaHari }}"
                                         data-search="{{ $teksCariKonflik }}">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="text-xs font-bold">Bentrok · {{ $cellItems->count() }} kelas</span>
                                            <x-lucide-alert-triangle class="h-3.5 w-3.5" aria-hidden="true" />
                                        </div>
                                        <div class="mt-1.5 flex flex-wrap gap-1">
                                            @foreach ($cellItems as $itemKonflik)
                                                <span class="jg-conflict-tag">
                                                    {{ $itemKonflik['kelas'] }}{{ $itemKonflik['jurusan_singkat'] !== '' ? ' '.$itemKonflik['jurusan_singkat'] : '' }}
                                                </span>
                                            @endforeach
                                        </div>
                                        @if ($bisaKelola)
                                            <button type="button"
                                                    class="mt-2 inline-flex min-h-7 items-center rounded-lg bg-[var(--surface)] px-2 text-xs font-bold text-[#A3261C] transition hover:bg-[#fff5f4]"
                                                    data-buka-penyesuaian
                                                    data-fokus-bentrok="{{ $grupBentrok['id'] ?? '' }}">
                                                Atur ulang
                                            </button>
                                        @endif
                                    </div>
                                @else
                                    @foreach ($cellItems as $itemGrid)
                                        @include('dashboard.guru.jadwal-pelajaran.partials.chip', ['item' => $itemGrid])
                                    @endforeach
                                @endif
                            </div>
                        @endforeach
                    @endif
                @endforeach

                <div class="jg-grid-head jg-outside-head" role="rowheader" data-luar-row @if (! $adaItemsLuar) hidden @endif>
                    Di luar jam sesi
                </div>
                @foreach ($hariAktif as $namaHari)
                    @php
                        $luarHari = $itemsLuar[$namaHari] ?? collect();
                    @endphp
                    <div class="jg-grid-cell jg-outside-cell {{ $namaHari === $hariIni ? 'jg-grid-cell-today' : '' }}"
                         role="cell"
                         data-grid-cell
                         data-cell-jenis="luar"
                         data-cell-hari="{{ $namaHari }}"
                         data-luar-row
                         @if ($luarHari->isEmpty()) hidden @endif>
                        @if ($luarHari->count() > 1)
                            @php
                                    $teksCariLuar = ($mode ?? 'guru') === 'admin'
                                        ? $luarHari->map($teksCariAdmin)->implode(' ')
                                        : '';

                            @endphp
                            <div class="jg-conflict-box"
                                 data-conflict-box
                                 data-status="luar"
                                 data-jumlah="{{ $luarHari->count() }}"
                                 data-hari="{{ $namaHari }}"
                                 data-kelas-key="*"
                                 data-search="{{ $teksCariLuar }}">
                                <div class="flex items-center justify-between gap-1">
                                    <span class="text-xs font-bold">{{ $luarHari->count() }} jadwal di luar sesi</span>
                                    <x-lucide-alert-triangle class="h-3.5 w-3.5" aria-hidden="true" />
                                </div>
                                <div class="mt-1.5 flex flex-wrap gap-1">
                                    @foreach ($luarHari as $itemLuar)
                                        <span class="jg-conflict-tag">{{ $itemLuar['kelas'] }} · {{ $itemLuar['label'] }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            @foreach ($luarHari as $itemLuar)
                                @include('dashboard.guru.jadwal-pelajaran.partials.chip', ['item' => $itemLuar])
                            @endforeach
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="jg-legend jg-muted">
            <span class="font-semibold jg-title">Legenda</span>
            <span class="jg-legend-item">
                <span class="jg-legend-mark rounded-full" style="background:#E8F1FF; border:1px solid #1B4F9E;"></span>
                RPL = Rekayasa Perangkat Lunak
            </span>
            <span class="jg-legend-item">
                <span class="jg-legend-mark rounded-full" style="background:#DCF4EE; border:1px solid #0B6554;"></span>
                TKJ = Teknik Komputer Jaringan
            </span>
            <span class="jg-legend-item">
                <span class="jg-legend-mark inline-flex items-center justify-center text-[#B78100]">
                    <x-lucide-triangle-alert class="h-3 w-3" aria-hidden="true" />
                </span>
                Jam belum sesuai sesi terbaru / di luar sesi
            </span>
            <span class="jg-legend-item">
                <span class="jg-legend-mark rounded-full bg-[#A3261C]"></span>
                Bentrok
            </span>
        </div>
    </div>
</section>
