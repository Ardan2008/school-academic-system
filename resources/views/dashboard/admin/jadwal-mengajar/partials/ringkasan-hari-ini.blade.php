@php
    $ringkasanKompak = $kompak ?? false;
    $ringkasanHari = in_array($hari ?? '', $hariAktif, true) ? $hari : $hariIni;
    $ringkasanItems = collect($itemsTampil ?? $items)
        ->where('hari', $ringkasanHari)
        ->sortBy('mulai')
        ->values();
    $ringkasanTotal = $ringkasanItems->count();
    $ringkasanKelas = $ringkasanItems->pluck('rombel_key')->filter()->unique()->count();
    $ringkasanRuang = $ringkasanItems
        ->map(fn (array $item) => trim((string) ($item['ruang'] ?? '')))
        ->filter()
        ->unique()
        ->count();

    $posisiHari = array_search($ringkasanHari, \App\Models\JadwalPelajaran::HARI, true);
    $tanggalRingkasan = \Illuminate\Support\Carbon::parse($pekanStartIso)
        ->addDays(max(0, (int) $posisiHari))
        ->locale('id')
        ->translatedFormat('l, d F Y');

    $distribusi = $ringkasanItems
        ->map(fn (array $item) => substr((string) ($item['mulai'] ?? ''), 0, 5))
        ->filter()
        ->countBy()
        ->all();
    $labelSesi = collect($sesiGrid)
        ->map(fn ($sesi) => substr((string) $sesi->jam_mulai, 0, 5))
        ->filter()
        ->unique()
        ->values()
        ->all();
    foreach (array_keys($distribusi) as $jamRingkasan) {
        if (! in_array($jamRingkasan, $labelSesi, true)) {
            $labelSesi[] = $jamRingkasan;
        }
    }
    sort($labelSesi);
    $maksSesi = max(1, ...array_values($distribusi ?: [0]));

    $adaBentrok = $ringkasanItems->contains(fn (array $item) => ($item['bentrok'] ?? false) || (($item['status'] ?? '') === 'bentrok'));
    $adaPeringatan = $ringkasanItems->contains(fn (array $item) => ($item['perlu_disesuaikan'] ?? false) || ($item['di_luar_sesi'] ?? false))
        || collect($saran)->contains(fn (array $item) => ($item['hari'] ?? null) === $ringkasanHari);

    if ($ringkasanTotal === 0) {
        $statusKunci = 'kosong';
        $statusTeks = 'Belum ada jadwal';
    } elseif ($adaBentrok) {
        $statusKunci = 'bentrok';
        $statusTeks = 'Jadwal bentrok';
    } elseif ($adaPeringatan) {
        $statusKunci = 'peringatan';
        $statusTeks = 'Peringatan jadwal';
    } else {
        $statusKunci = 'aman';
        $statusTeks = 'Jadwal tidak bentrok';
    }
@endphp

<section class="jg-card jg-ringkasan p-4 sm:p-5 {{ $ringkasanKompak ? 'mt-4' : '' }}"
         data-ringkasan-hari-ini
         data-reveal>
    <div class="flex min-w-0 items-start justify-between gap-3">
        <div class="flex min-w-0 items-start gap-2.5">
            <span class="jg-ringkasan-ikon shrink-0" aria-hidden="true">
                <x-lucide-calendar-days class="h-4 w-4" />
            </span>
            <div class="min-w-0">
                <h3 class="truncate text-sm font-bold jg-title">Ringkasan Jadwal Hari Ini</h3>
                <p class="jg-ringkasan-tanggal mt-0.5 text-xs jg-muted" data-ringkasan-tanggal>{{ $tanggalRingkasan }}</p>
            </div>
        </div>
        <span class="jg-ringkasan-badge shrink-0" data-ringkasan-badge>{{ $ringkasanTotal }} Sesi</span>
    </div>

    <div class="mt-4 grid grid-cols-3 gap-2">
        <div class="jg-ringkasan-utama">
            <p class="jg-ringkasan-utama-label">Total Sesi</p>
            <p class="jg-ringkasan-utama-nilai" data-ringkasan-total>{{ $ringkasanTotal }}</p>
        </div>
        <div class="jg-ringkasan-stat">
            <span class="jg-ringkasan-stat-ikon" aria-hidden="true">
                <x-lucide-school class="h-3.5 w-3.5" />
            </span>
            <p class="jg-ringkasan-stat-label">Kelas Terjadwal</p>
            <p class="jg-ringkasan-stat-nilai" data-ringkasan-kelas>{{ $ringkasanKelas }}</p>
        </div>
        <div class="jg-ringkasan-stat">
            <span class="jg-ringkasan-stat-ikon" aria-hidden="true">
                <x-lucide-map-pin class="h-3.5 w-3.5" />
            </span>
            <p class="jg-ringkasan-stat-label">Ruang Digunakan</p>
            <p class="jg-ringkasan-stat-nilai" data-ringkasan-ruang>{{ $ringkasanRuang }}</p>
        </div>
    </div>

    <div class="jg-ringkasan-status mt-4" data-ringkasan-status data-status="{{ $statusKunci }}">
        <span class="jg-ringkasan-status-ikon" aria-hidden="true">
            <x-lucide-check class="jg-ringkasan-status-aman h-4 w-4" data-ringkasan-status-ikon-aman />
            <x-lucide-triangle-alert class="jg-ringkasan-status-peringatan h-4 w-4" data-ringkasan-status-ikon-peringatan />
            <x-lucide-clock class="jg-ringkasan-status-kosong h-4 w-4" data-ringkasan-status-ikon-kosong />
        </span>
        <p class="min-w-0 text-xs font-bold" data-ringkasan-status-text>{{ $statusTeks }}</p>
    </div>

    @if ($ringkasanTotal === 0)
        <div class="jg-ringkasan-kosong mt-4" data-ringkasan-kosong>
            <x-lucide-calendar-days class="h-5 w-5" aria-hidden="true" />
            <p class="mt-2 text-xs font-bold jg-title">Belum ada jadwal pada hari ini</p>
            <p class="mt-1 text-[11px] leading-relaxed jg-muted">Pilih hari lain atau tambahkan jadwal untuk melihat distribusi sesi.</p>
        </div>
        <div class="mt-4" data-ringkasan-chart hidden></div>
    @else
        <div class="mt-4" data-ringkasan-chart>
            <div class="mb-2 flex items-center justify-between gap-2">
                <p class="jg-eyebrow">Distribusi Sesi per Jam</p>
                <p class="text-[11px] jg-muted" data-ringkasan-maks>Maks. {{ $maksSesi }} sesi</p>
            </div>
            @foreach ($labelSesi as $jam)
                @php($jumlahSesi = $distribusi[$jam] ?? 0)
                <div class="jg-ringkasan-baris"
                     data-ringkasan-bar
                     data-waktu="{{ $jam }}"
                     title="{{ $jam }} · {{ $jumlahSesi }} sesi">
                    <span class="jg-ringkasan-waktu" data-ringkasan-waktu>{{ $jam }}</span>
                    <span class="jg-ringkasan-track" aria-hidden="true">
                        <span class="jg-ringkasan-fill"
                              data-ringkasan-fill
                              style="width: {{ $jumlahSesi > 0 ? round($jumlahSesi / $maksSesi * 100) : 0 }}%"></span>
                    </span>
                    <span class="jg-ringkasan-jumlah" data-ringkasan-jumlah>{{ $jumlahSesi }}</span>
                </div>
            @endforeach
        </div>
        <div class="jg-ringkasan-kosong mt-4" data-ringkasan-kosong hidden>
            <x-lucide-calendar-days class="h-5 w-5" aria-hidden="true" />
            <p class="mt-2 text-xs font-bold jg-title">Belum ada jadwal pada hari ini</p>
            <p class="mt-1 text-[11px] leading-relaxed jg-muted">Pilih hari lain atau tambahkan jadwal untuk melihat distribusi sesi.</p>
        </div>
    @endif

    <p class="jg-ringkasan-error" data-ringkasan-error hidden>Data ringkasan tidak dapat diperbarui.</p>
</section>
