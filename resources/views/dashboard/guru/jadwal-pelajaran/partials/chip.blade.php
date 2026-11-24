@php
    $bisaKelola = $bisaKelola ?? true;
    $warnaBg = $item['warna_bg'];
    $warnaTeks = $item['warna_teks'];
    $namaKelas = trim($item['kelas'].($item['jurusan_singkat'] !== '' ? ' '.$item['jurusan_singkat'] : ''));
    $perluPeringatan = $item['perlu_disesuaikan'] || ($item['di_luar_sesi'] ?? false);
    $statusFilter = ($item['di_luar_sesi'] ?? false) && $item['status'] === 'sesuai'
        ? 'luar'
        : $item['status'];
@endphp

<button type="button"
        class="jg-chip"
        style="--chip-bg: {{ $warnaBg }}; --chip-text: {{ $warnaTeks }};"
        data-jadwal-chip
        @if ($bisaKelola) data-edit-jadwal-trigger @endif
        data-id="{{ $item['id'] }}"
        data-hari="{{ $item['hari'] }}"
        data-mulai="{{ $item['mulai'] }}"
        data-selesai="{{ $item['selesai'] }}"
        data-status="{{ $statusFilter }}"
        data-kelas-key="{{ $item['rombel_key'] }}"
        data-sesi-id="{{ $item['sesi_id'] }}"
        data-luar-sesi="{{ ($item['di_luar_sesi'] ?? false) ? '1' : '0' }}"
        data-live-item
        title="{{ $item['mapel'].' · '.$namaKelas.' · '.$item['label'].' · '.$item['ruang'] }}">
    <span class="jg-chip-title">{{ $namaKelas }}</span>
    @if (($mode ?? 'guru') === 'admin')
        <span class="jg-chip-sub truncate">{{ $item['guru'] ?? '-' }}</span>
    @endif
    @if ($item['bentrok'] ?? false)
        <x-lucide-triangle-alert class="jg-chip-warning" style="color:#A3261C;" role="img" aria-label="Jadwal mengalami bentrok" />
    @endif
    @if ($perluPeringatan)
        <x-lucide-triangle-alert class="jg-chip-warning" role="img" aria-label="Jam belum sesuai sesi terbaru" />
    @endif
    <span class="jg-chip-sub">{{ $item['ruang'] !== '' ? $item['ruang'] : 'Ruang —' }}</span>
    <span data-live-badge class="jg-badge-live hidden">Berlangsung</span>
</button>
