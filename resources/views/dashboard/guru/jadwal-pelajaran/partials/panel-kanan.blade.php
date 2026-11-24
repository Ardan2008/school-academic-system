@php
    $namaJurusanPenuh = [
        'RPL' => 'Rekayasa Perangkat Lunak',
        'TKJ' => 'Teknik Komputer Jaringan',
    ];
    $urutanHari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    $posisiHari = static fn (string $nama): int => (int) array_search($nama, $urutanHari, true);
    $itemsHariIni = collect($items)->where('hari', $hariIni)->sortBy('mulai')->values();
    $berjalan = $itemsHariIni->first(function (array $item) use ($nowLabel): bool {
        return $nowLabel >= $item['mulai'] && $nowLabel <= $item['selesai'];
    });
    $posisiHariIni = $posisiHari((string) $hariIni);
    $berikutnya = collect($items)
        ->filter(function (array $item) use ($posisiHari, $posisiHariIni, $nowLabel): bool {
            $posisi = $posisiHari((string) $item['hari']);

            return $posisi > $posisiHariIni
                || ($posisi === $posisiHariIni && (string) $item['mulai'] > $nowLabel);
        })
        ->sort(function (array $a, array $b) use ($posisiHari): int {
            return ($posisiHari((string) $a['hari']) <=> $posisiHari((string) $b['hari']))
                ?: strcmp((string) $a['mulai'], (string) $b['mulai']);
        })
        ->first();
    $selesaiHari = $itemsHariIni->isNotEmpty() && $berjalan === null
        && $itemsHariIni->every(fn (array $item): bool => (string) $item['mulai'] <= $nowLabel);
    $selesai = $selesaiHari && $berikutnya === null;
    $fokus = $berjalan ?? $berikutnya ?? ($selesaiHari ? $itemsHariIni->last() : null);
    $persen = 0;
    $sisaMenit = 0;
    $hariMenuju = 0;

    if ($fokus !== null) {
        $posisi = $posisiHari((string) $fokus['hari']);
        $hariMenuju = $posisi < $posisiHariIni ? $posisi + 7 - $posisiHariIni : $posisi - $posisiHariIni;
    }
    $menitMenuju = $fokus === null
        ? 0
        : max(0, ((int) ((strtotime($fokus['mulai']) - strtotime($nowLabel)) / 60)) + ($hariMenuju * 1440));

    if ($berjalan !== null) {
        $total = max(1, (int) ((strtotime($berjalan['selesai']) - strtotime($berjalan['mulai'])) / 60));
        $lewat = max(0, (int) ((strtotime($nowLabel) - strtotime($berjalan['mulai'])) / 60));
        $persen = (int) round(min(100, max(0, $lewat / $total * 100)));
        $sisaMenit = max(0, $total - $lewat);
    }

    if ($berjalan !== null) {
        $teksSisa = 'Sisa '.$sisaMenit.' menit';
    } elseif ($fokus !== null) {
        $jam = intdiv($menitMenuju, 60);
        $menit = $menitMenuju % 60;
        $teksSisa = $jam > 0
            ? 'Mulai dalam '.$jam.' jam '.$menit.' menit'
            : 'Mulai dalam '.$menit.' menit';
    } elseif ($selesai) {
        $teksSisa = 'Selesai hari ini';
    } else {
        $teksSisa = '—';
    }

    $stateTimeline = $berjalan !== null ? 'berjalan' : ($selesai ? 'selesai' : 'menunggu');
    $persenTimeline = $berjalan !== null ? $persen : ($selesai ? 100 : 0);
    $labelTimeline = $berjalan !== null
        ? 'Sedang berlangsung'
        : ($selesai ? 'Sesi telah berakhir' : 'Menunggu dimulai');

    $ruteTugas = route('dashboard.guru.tugas-materi.index', array_filter([
        'mapel_id' => $fokus['mapel_id'] ?? null,
        'rombel' => $fokus !== null ? $fokus['kelas'].'|'.$fokus['jurusan'] : null,
    ], fn ($nilai) => $nilai !== null && $nilai !== ''));
@endphp

<div class="jg-side-grid">
    @if (($mode ?? 'guru') === 'admin')
        @include('dashboard.admin.jadwal-mengajar.partials.ringkasan-hari-ini')
    @else
    <section class="jg-live-card p-4 sm:p-5" data-live-card>
        <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-2.5 py-1 text-xs font-bold" data-live-status>
            <span class="h-2 w-2 rounded-full" style="background:{{ $berjalan !== null ? '#6EE7A5' : ($fokus !== null ? '#FDE68A' : '#8A98AC') }}"></span>
            <span data-live-status-text>{{ $berjalan !== null ? 'Sedang berlangsung' : ($berikutnya !== null ? 'Berikutnya · '.$berikutnya['hari'] : ($selesai ? 'Selesai hari ini' : 'Tidak ada jadwal')) }}</span>
        </span>

        <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-white/70" data-live-sesi>
            {{ $fokus !== null ? $fokus['sesi_label'] : 'Belum ada jadwal' }}
        </p>
        <h3 class="jg-live-title mt-1 text-base font-bold" data-live-kelas>
            @if ($fokus !== null)
                {{ $fokus['kelas'] }} · {{ $namaJurusanPenuh[$fokus['jurusan_singkat']] ?? ($fokus['jurusan'] !== '' ? $fokus['jurusan'] : 'Semua Jurusan') }}
            @else
                Tidak ada pelajaran hari ini
            @endif
        </h3>
        <p class="mt-1 text-xs text-white/80" data-live-mapel>
            {{ $fokus !== null ? $fokus['mapel'].' · Ruang '.($fokus['ruang'] !== '' ? $fokus['ruang'] : '—') : 'Nikmati waktu kosong Anda.' }}
        </p>

        <div class="mt-4 flex items-center justify-between gap-2 text-xs text-white/80">
            <span class="shrink-0 tabular-nums" data-live-start>{{ $fokus['mulai'] ?? '--:--' }}</span>
            <span class="truncate font-bold text-white" data-live-sisa>{{ $teksSisa }}</span>
            <span class="shrink-0 tabular-nums" data-live-end>{{ $fokus['selesai'] ?? '--:--' }}</span>
        </div>

        @if (($mode ?? 'guru') !== 'admin')
            <div class="mt-4 grid grid-cols-2 gap-2">
                <a href="{{ $ruteTugas }}"
                   class="jg-live-action inline-flex min-h-10 items-center justify-center rounded-xl bg-[var(--surface)] px-3 text-xs font-bold text-[#1556B8] transition hover:bg-[#E8F1FF]">
                    Materi & tugas
                </a>
                <a href="{{ route('dashboard.guru.input-nilai.index') }}"
                   class="jg-live-action inline-flex min-h-10 items-center justify-center rounded-xl border border-white/60 px-3 text-xs font-bold text-white transition hover:bg-white/10">
                    Input nilai
                </a>
            </div>
        @endif

        @if ($fokus !== null)
            <div class="jg-mini-timeline mt-4 border-t border-white/15 pt-4"
                 data-mini-timeline
                 data-state="{{ $stateTimeline }}">
                <div class="flex items-center justify-between gap-2 text-xs font-bold text-white/70">
                    <span>Progres sesi</span>
                    <span data-timeline-label>{{ $labelTimeline }}</span>
                </div>
                <div class="jg-mini-timeline-track mt-2" aria-hidden="true">
                    <span class="jg-mini-timeline-fill" data-timeline-fill style="width: {{ $persenTimeline }}%"></span>
                    <span class="jg-mini-timeline-dot jg-mini-timeline-dot-start"></span>
                    <span class="jg-mini-timeline-dot jg-mini-timeline-dot-now" data-timeline-dot style="left: {{ $persenTimeline }}%"></span>
                    <span class="jg-mini-timeline-dot jg-mini-timeline-dot-end"></span>
                </div>
                <div class="mt-2 grid grid-cols-3 gap-2 text-xs">
                    <div class="min-w-0">
                        <p class="text-white/60">Sekarang</p>
                        <p class="font-bold tabular-nums" data-timeline-now>{{ $nowLabel }}</p>
                    </div>
                    <div class="min-w-0 text-center">
                        <p class="text-white/60">Mulai</p>
                        <p class="font-bold tabular-nums" data-timeline-start>{{ $fokus['mulai'] }}</p>
                    </div>
                    <div class="min-w-0 text-right">
                        <p class="text-white/60">Selesai</p>
                        <p class="font-bold tabular-nums" data-timeline-end>{{ $fokus['selesai'] }}</p>
                    </div>
                </div>
            </div>
        @endif
    </section>
    @endif

    <section class="jg-card p-4 sm:p-5">
        <div class="flex min-w-0 items-start justify-between gap-2">
            <div class="min-w-0">
                <h3 class="text-sm font-bold jg-title">Agenda hari ini</h3>
                <p class="text-xs jg-muted">{{ $hariIni ?? 'Libur' }} · {{ $tanggalPekan[$hariIni] ?? '' }}</p>
            </div>
            <span class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold" style="background:#E8F1FF; color:#1B4F9E;">
                {{ $itemsHariIni->count() }} sesi
            </span>
        </div>

        @if ($itemsHariIni->isEmpty())
            <p class="mt-4 rounded-xl border border-dashed border-[var(--jg-line)] px-3 py-6 text-center text-xs jg-muted">
                Belum ada agenda untuk hari ini.
            </p>
        @else
            <div class="jg-agenda-list mt-3" data-agenda-list data-many="{{ $itemsHariIni->count() >= 7 ? '1' : '0' }}">
                @foreach ($itemsHariIni as $itemAgenda)
                    @php
                        $live = $nowLabel >= $itemAgenda['mulai'] && $nowLabel <= $itemAgenda['selesai'];
                        $namaKelas = trim($itemAgenda['kelas'].($itemAgenda['jurusan_singkat'] !== '' ? ' '.$itemAgenda['jurusan_singkat'] : ''));
                        $keterangan = $itemAgenda['mapel'].' · '.($itemAgenda['ruang'] !== '' ? $itemAgenda['ruang'] : 'ruang —');
                    @endphp
                    <div class="jg-agenda-item"
                         data-live-item
                         data-id="{{ $itemAgenda['id'] }}"
                         data-hari="{{ $itemAgenda['hari'] }}"
                         data-mulai="{{ $itemAgenda['mulai'] }}"
                         data-selesai="{{ $itemAgenda['selesai'] }}">
                        <div class="jg-agenda-time">
                            <p class="text-xs font-bold tabular-nums jg-title">{{ $itemAgenda['mulai'] }}</p>
                            <p class="text-xs tabular-nums jg-muted">{{ $itemAgenda['selesai'] }}</p>
                        </div>
                        <div class="jg-agenda-main">
                            <p class="jg-agenda-kelas font-bold jg-title" title="{{ $namaKelas }}">{{ $namaKelas }}</p>
                            <p class="jg-agenda-mapel jg-muted" title="{{ $keterangan }}">{{ $keterangan }}</p>
                        </div>
                        <div class="jg-agenda-mark">
                            @if ($itemAgenda['bentrok'] ?? false)
                                <x-lucide-triangle-alert class="h-4 w-4 text-[#A3261C]" role="img" aria-label="Jadwal mengalami bentrok" />
                            @endif
                            @if ($itemAgenda['perlu_disesuaikan'] || ($itemAgenda['di_luar_sesi'] ?? false))
                                <x-lucide-triangle-alert class="h-4 w-4 text-[#B78100]" role="img" aria-label="Jam belum sesuai sesi terbaru" />
                            @endif
                            <span class="jg-agenda-live-dot" data-live-badge title="Berlangsung" @if (! $live) hidden @endif></span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
