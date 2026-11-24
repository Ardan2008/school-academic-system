@php
    $bisaKelola = $bisaKelola ?? true;
    $hariTerpilihMobile = in_array($hariAwal, $hariAktif, true) ? $hariAwal : ($hariIni && in_array($hariIni, $hariAktif, true) ? $hariIni : ($hariAktif[0] ?? 'Senin'));
    $itemsMobile = collect($perHariTampil[$hariTerpilihMobile] ?? [])->sortBy('mulai')->values();
    $itemsAgendaMobile = collect($perHariTampil[$hariIni] ?? [])->sortBy('mulai')->values();
    $perluMobile = $itemsMobile->where('perlu_disesuaikan', true)->count();
    $urutanHariMobile = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    $posisiHariMobile = static fn (string $nama): int => (int) array_search($nama, $urutanHariMobile, true);
    $posisiHariIniMobile = $posisiHariMobile((string) $hariIni);
    $itemHariIniMobile = collect($items)->where('hari', $hariIni)->sortBy('mulai')->values();
    $berjalanMobile = $itemHariIniMobile->first(fn (array $item) => $nowLabel >= $item['mulai'] && $nowLabel <= $item['selesai']);
    $berikutnyaMobile = $berjalanMobile !== null ? null : collect($items)
        ->filter(function (array $item) use ($posisiHariMobile, $posisiHariIniMobile, $nowLabel): bool {
            $posisi = $posisiHariMobile((string) $item['hari']);

            return $posisi > $posisiHariIniMobile
                || ($posisi === $posisiHariIniMobile && (string) $item['mulai'] > $nowLabel);
        })
        ->sort(function (array $a, array $b) use ($posisiHariMobile): int {
            return ($posisiHariMobile((string) $a['hari']) <=> $posisiHariMobile((string) $b['hari']))
                ?: strcmp((string) $a['mulai'], (string) $b['mulai']);
        })
        ->first();
    $namaJurusanPenuh = [
        'RPL' => 'Rekayasa Perangkat Lunak',
        'TKJ' => 'Teknik Komputer Jaringan',
    ];
    $persenMobile = 0;

    if ($berjalanMobile !== null) {
        $total = max(1, (int) ((strtotime($berjalanMobile['selesai']) - strtotime($berjalanMobile['mulai'])) / 60));
        $lewat = max(0, (int) ((strtotime($nowLabel) - strtotime($berjalanMobile['mulai'])) / 60));
        $persenMobile = (int) round(min(100, max(0, $lewat / $total * 100)));
    }

    $selesaiHariMobile = $itemHariIniMobile->isNotEmpty() && $berjalanMobile === null
        && $itemHariIniMobile->every(fn (array $item): bool => (string) $item['mulai'] <= $nowLabel);
    $selesaiMobile = $selesaiHariMobile && $berikutnyaMobile === null;
    $fokusMobile = $berjalanMobile ?? $berikutnyaMobile ?? ($selesaiHariMobile ? $itemHariIniMobile->last() : null);
    $hariMenujuMobile = 0;

    if ($fokusMobile !== null) {
        $posisi = $posisiHariMobile((string) $fokusMobile['hari']);
        $hariMenujuMobile = $posisi < $posisiHariIniMobile
            ? $posisi + 7 - $posisiHariIniMobile
            : $posisi - $posisiHariIniMobile;
    }
    $menitMenujuMobile = $fokusMobile === null
        ? 0
        : max(0, ((int) ((strtotime($fokusMobile['mulai']) - strtotime($nowLabel)) / 60)) + ($hariMenujuMobile * 1440));

    if ($berjalanMobile !== null) {
        $teksSisaMobile = 'Sisa '.max(0, (int) ((strtotime($berjalanMobile['selesai']) - strtotime($nowLabel)) / 60)).' menit';
    } elseif ($fokusMobile !== null) {
        $jamMobile = intdiv($menitMenujuMobile, 60);
        $menitMobile = $menitMenujuMobile % 60;
        $teksSisaMobile = $jamMobile > 0
            ? 'Mulai dalam '.$jamMobile.' jam '.$menitMobile.' menit'
            : 'Mulai dalam '.$menitMobile.' menit';
    } elseif ($selesaiMobile) {
        $teksSisaMobile = 'Selesai hari ini';
    } else {
        $teksSisaMobile = '—';
    }

    $stateTimelineMobile = $berjalanMobile !== null ? 'berjalan' : ($selesaiMobile ? 'selesai' : 'menunggu');
    $persenTimelineMobile = $berjalanMobile !== null ? $persenMobile : ($selesaiMobile ? 100 : 0);
    $labelTimelineMobile = $berjalanMobile !== null
        ? 'Sedang berlangsung'
        : ($selesaiMobile ? 'Sesi telah berakhir' : 'Menunggu dimulai');

    $ruteTugasMobile = ($mode ?? 'guru') === 'admin'
        ? route('dashboard.admin.jadwal-mengajar.index')
        : route('dashboard.guru.tugas-materi.index', array_filter([
            'mapel_id' => $fokusMobile['mapel_id'] ?? null,
            'rombel' => $fokusMobile !== null ? $fokusMobile['kelas'].'|'.$fokusMobile['jurusan'] : null,
        ], fn ($nilai) => $nilai !== null && $nilai !== ''));
@endphp

<section data-view-panel="hari" class="jg-mobile-only" hidden>
    <div class="grid gap-1.5" style="grid-template-columns: repeat({{ count($hariAktif) }}, minmax(0, 1fr));">
        @foreach ($hariAktif as $namaHari)
            @php
                $adaMasalahHari = collect($perHariTampil[$namaHari] ?? [])
                    ->contains(fn (array $item) => ($item['perlu_disesuaikan'] ?? false)
                        || ($item['bentrok'] ?? false)
                        || ($item['di_luar_sesi'] ?? false));
            @endphp
            <button type="button" class="jg-mobile-day" data-mobile-day="{{ $namaHari }}"
                    aria-pressed="{{ $namaHari === $hariTerpilihMobile ? 'true' : 'false' }}">
                <span class="block text-xs font-bold">{{ $namaHari }}</span>
                <span class="mt-0.5 block text-xs opacity-80">{{ $tanggalPekan[$namaHari] ?? '' }}</span>
                <span class="mx-auto mt-1 block h-1.5 w-1.5 rounded-full {{ $adaMasalahHari ? 'bg-[#EAA72C]' : 'jg-mobile-dot' }}"></span>
            </button>
        @endforeach
    </div>

    <div class="mt-4 flex items-center justify-between gap-2">
        <p class="text-xs font-bold jg-title">
            <span data-mobile-count>{{ $itemsMobile->count() }}</span> jadwal hari ini
        </p>
        @if ($bisaKelola && $perluMobile > 0)
            <button type="button" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold" style="background:#FFF1D6; color:#7A4300;" data-buka-penyesuaian>
                <x-lucide-triangle-alert class="h-3 w-3" aria-hidden="true" />
                <span data-mobile-perlu>{{ $perluMobile }}</span> perlu disesuaikan
            </button>
        @else
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold" style="background:#E0F6EA; color:#0F6B3C;">
                <x-lucide-check class="h-3 w-3" aria-hidden="true" />
                Semua sesuai
            </span>
        @endif
    </div>

    @if ($bisaKelola)
        <button type="button" class="jg-btn jg-btn-primary mt-3 w-full" data-tambah-jadwal-trigger @disabled(!$bisaTambah)>
            <x-lucide-plus class="h-4 w-4" aria-hidden="true" />
            Tambah Jadwal
        </button>
    @endif

    @if (($mode ?? 'guru') === 'admin')
        @include('dashboard.admin.jadwal-mengajar.partials.ringkasan-hari-ini', ['kompak' => true])
    @else
    <section class="jg-live-card mt-4 p-4" data-live-card>
        <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-2.5 py-1 text-xs font-bold" data-live-status>
            <span class="h-2 w-2 rounded-full" style="background:{{ $berjalanMobile !== null ? '#6EE7A5' : ($fokusMobile !== null ? '#FDE68A' : '#8A98AC') }}"></span>
            <span data-live-status-text>{{ $berjalanMobile !== null ? 'Sedang berlangsung' : ($berikutnyaMobile !== null ? 'Berikutnya · '.$berikutnyaMobile['hari'] : ($selesaiMobile ? 'Selesai hari ini' : 'Tidak ada jadwal')) }}</span>
        </span>
        <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-white/70" data-live-sesi>
            {{ $fokusMobile['sesi_label'] ?? 'Belum ada jadwal' }}
        </p>
        <h3 class="jg-live-title mt-1 text-base font-bold" data-live-kelas>
            @if ($fokusMobile !== null)
                {{ $fokusMobile['kelas'] }} · {{ $namaJurusanPenuh[$fokusMobile['jurusan_singkat']] ?? ($fokusMobile['jurusan'] !== '' ? $fokusMobile['jurusan'] : 'Semua Jurusan') }}
            @else
                Tidak ada pelajaran hari ini
            @endif
        </h3>
        <p class="mt-1 text-xs text-white/80" data-live-mapel>
            {{ $fokusMobile !== null ? $fokusMobile['mapel'].' · Ruang '.($fokusMobile['ruang'] !== '' ? $fokusMobile['ruang'] : '—') : 'Nikmati waktu kosong Anda.' }}
        </p>
        <div class="mt-3 flex items-center justify-between gap-2 text-xs text-white/80">
            <span class="shrink-0 tabular-nums" data-live-start>{{ $fokusMobile['mulai'] ?? '--:--' }}</span>
            <span class="truncate font-bold text-white" data-live-sisa>{{ $teksSisaMobile }}</span>
            <span class="shrink-0 tabular-nums" data-live-end>{{ $fokusMobile['selesai'] ?? '--:--' }}</span>
        </div>
        @if (($mode ?? 'guru') !== 'admin')
            <div class="mt-4 grid grid-cols-2 gap-2">
                <a href="{{ $ruteTugasMobile }}" class="jg-live-action inline-flex min-h-11 items-center justify-center rounded-xl bg-[var(--surface)] px-3 text-xs font-bold text-[#1556B8]">Materi & tugas</a>
                <a href="{{ route('dashboard.guru.input-nilai.index') }}" class="jg-live-action inline-flex min-h-11 items-center justify-center rounded-xl border border-white/60 px-3 text-xs font-bold text-white">Input nilai</a>
            </div>
        @endif

        @if ($fokusMobile !== null)
            <div class="jg-mini-timeline mt-4 border-t border-white/15 pt-4"
                 data-mini-timeline
                 data-state="{{ $stateTimelineMobile }}">
                <div class="flex items-center justify-between gap-2 text-xs font-bold text-white/70">
                    <span>Progres sesi</span>
                    <span data-timeline-label>{{ $labelTimelineMobile }}</span>
                </div>
                <div class="jg-mini-timeline-track mt-2" aria-hidden="true">
                    <span class="jg-mini-timeline-fill" data-timeline-fill style="width: {{ $persenTimelineMobile }}%"></span>
                    <span class="jg-mini-timeline-dot jg-mini-timeline-dot-start"></span>
                    <span class="jg-mini-timeline-dot jg-mini-timeline-dot-now" data-timeline-dot style="left: {{ $persenTimelineMobile }}%"></span>
                    <span class="jg-mini-timeline-dot jg-mini-timeline-dot-end"></span>
                </div>
                <div class="mt-2 grid grid-cols-3 gap-2 text-xs">
                    <div class="min-w-0">
                        <p class="text-white/60">Sekarang</p>
                        <p class="font-bold tabular-nums" data-timeline-now>{{ $nowLabel }}</p>
                    </div>
                    <div class="min-w-0 text-center">
                        <p class="text-white/60">Mulai</p>
                        <p class="font-bold tabular-nums" data-timeline-start>{{ $fokusMobile['mulai'] }}</p>
                    </div>
                    <div class="min-w-0 text-right">
                        <p class="text-white/60">Selesai</p>
                        <p class="font-bold tabular-nums" data-timeline-end>{{ $fokusMobile['selesai'] }}</p>
                    </div>
                </div>
            </div>
        @endif
    </section>
    @endif

    <section class="jg-card mt-4 p-4">
        <div class="flex min-w-0 items-start justify-between gap-2">
            <div class="min-w-0">
                <h3 class="text-sm font-bold jg-title">Agenda hari ini</h3>
                <p class="text-xs jg-muted">{{ $hariIni ?? 'Libur' }} · {{ $tanggalPekan[$hariIni] ?? '' }}</p>
            </div>
            <span class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold" style="background:#E8F1FF; color:#1B4F9E;">
                {{ $itemsAgendaMobile->count() }} sesi
            </span>
        </div>

        @if ($itemsAgendaMobile->isEmpty())
            <p class="mt-4 rounded-xl border border-dashed border-[var(--jg-line)] px-3 py-6 text-center text-xs jg-muted">
                Belum ada agenda untuk hari ini.
            </p>
        @else
            <div class="jg-agenda-list mt-3" data-agenda-list data-many="{{ $itemsAgendaMobile->count() >= 7 ? '1' : '0' }}">
                @foreach ($itemsAgendaMobile as $itemAgendaMobile)
                    @php
                        $liveMobile = $nowLabel >= $itemAgendaMobile['mulai'] && $nowLabel <= $itemAgendaMobile['selesai'];
                        $namaKelasMobile = trim($itemAgendaMobile['kelas'].($itemAgendaMobile['jurusan_singkat'] !== '' ? ' '.$itemAgendaMobile['jurusan_singkat'] : ''));
                        $keteranganMobile = $itemAgendaMobile['mapel'].' · '.($itemAgendaMobile['ruang'] !== '' ? $itemAgendaMobile['ruang'] : 'ruang —');
                    @endphp
                    <div class="jg-agenda-item"
                         data-live-item
                         data-id="{{ $itemAgendaMobile['id'] }}"
                         data-hari="{{ $itemAgendaMobile['hari'] }}"
                         data-mulai="{{ $itemAgendaMobile['mulai'] }}"
                         data-selesai="{{ $itemAgendaMobile['selesai'] }}">
                        <div class="jg-agenda-time">
                            <p class="text-xs font-bold tabular-nums jg-title">{{ $itemAgendaMobile['mulai'] }}</p>
                            <p class="text-xs tabular-nums jg-muted">{{ $itemAgendaMobile['selesai'] }}</p>
                        </div>
                        <div class="jg-agenda-main">
                            <p class="jg-agenda-kelas font-bold jg-title" title="{{ $namaKelasMobile }}">{{ $namaKelasMobile }}</p>
                            <p class="jg-agenda-mapel jg-muted" title="{{ $keteranganMobile }}">{{ $keteranganMobile }}</p>
                        </div>
                        <div class="jg-agenda-mark">
                            @if ($itemAgendaMobile['bentrok'] ?? false)
                                <x-lucide-triangle-alert class="h-4 w-4 text-[#A3261C]" role="img" aria-label="Jadwal mengalami bentrok" />
                            @endif
                            @if ($itemAgendaMobile['perlu_disesuaikan'] || ($itemAgendaMobile['di_luar_sesi'] ?? false))
                                <x-lucide-triangle-alert class="h-4 w-4 text-[#B78100]" role="img" aria-label="Jam belum sesuai sesi terbaru" />
                            @endif
                            <span class="jg-agenda-live-dot" data-live-badge title="Berlangsung" @if (! $liveMobile) hidden @endif></span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <div class="mt-5">
        <p class="text-xs font-bold uppercase tracking-[0.12em] jg-muted">Berikutnya</p>
        <div class="mt-2 space-y-2" data-mobile-next>
            @foreach ($hariAktif as $namaHariDaftar)
                @php($ditampilkan = 0)
                @foreach (collect($perHariTampil[$namaHariDaftar] ?? [])->sortBy('mulai')->values() as $itemMobile)
                    @continue($berjalanMobile !== null && $namaHariDaftar === ($berjalanMobile['hari'] ?? null) && (int) $itemMobile['id'] === (int) ($berjalanMobile['id'] ?? 0))
                    @continue($ditampilkan >= 5)
                    @php($ditampilkan++)
                    <div class="grid grid-cols-[56px_minmax(0,1fr)] items-stretch gap-2" data-mobile-item
                         data-id="{{ $itemMobile['id'] }}"
                         data-hari="{{ $namaHariDaftar }}"
                         @if ($namaHariDaftar !== $hariTerpilihMobile) hidden @endif>
                        <div class="pt-2 text-right">
                            <p class="text-xs font-bold tabular-nums jg-title">{{ $itemMobile['mulai'] }}</p>
                            <p class="text-xs tabular-nums jg-muted">{{ $itemMobile['selesai'] }}</p>
                        </div>
                        <div class="rounded-xl px-3 py-2.5" style="background:{{ $itemMobile['warna_bg'] }}; color:{{ $itemMobile['warna_teks'] }};">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-xs font-bold">
                                    {{ $itemMobile['kelas'] }}{{ $itemMobile['jurusan_singkat'] !== '' ? ' '.$itemMobile['jurusan_singkat'] : '' }}
                                </p>
                                <span class="flex shrink-0 items-center gap-1">
                                    @if ($itemMobile['bentrok'] ?? false)
                                        <x-lucide-triangle-alert class="h-3.5 w-3.5 text-[#A3261C]" role="img" aria-label="Jadwal mengalami bentrok" />
                                    @endif
                                    @if ($itemMobile['perlu_disesuaikan'] || ($itemMobile['di_luar_sesi'] ?? false))
                                        <x-lucide-triangle-alert class="h-3.5 w-3.5 shrink-0" role="img" aria-label="Jam belum sesuai sesi terbaru" />
                                    @endif
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs opacity-85">{{ $itemMobile['mapel'] }} · {{ $itemMobile['ruang'] !== '' ? $itemMobile['ruang'] : 'ruang —' }}</p>
                        </div>
                    </div>
                @endforeach

                @if ($ditampilkan === 0)
                    <p class="rounded-xl border border-dashed border-[var(--jg-line)] px-3 py-5 text-center text-xs jg-muted"
                       data-mobile-empty data-hari="{{ $namaHariDaftar }}"
                       @if ($namaHariDaftar !== $hariTerpilihMobile) hidden @endif>
                        Tidak ada jadwal berikutnya.
                    </p>
                @endif
            @endforeach
        </div>
    </div>
</section>
