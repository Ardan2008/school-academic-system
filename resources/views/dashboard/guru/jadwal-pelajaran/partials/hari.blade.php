@php
    $bisaKelola = $bisaKelola ?? true;
    $hariTerpilih = in_array($hariAwal, $hariAktif, true) ? $hariAwal : ($hariIni && in_array($hariIni, $hariAktif, true) ? $hariIni : ($hariAktif[0] ?? 'Senin'));
    $namaJurusanPenuh = [
        'RPL' => 'Rekayasa Perangkat Lunak',
        'TKJ' => 'Teknik Komputer Jaringan',
    ];
@endphp

<section data-view-panel="hari" class="jg-hide-mobile" hidden>
    <div class="jg-card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--jg-line)] px-4 py-4 sm:px-5">
            <div>
                <h2 class="text-sm font-bold jg-title">Timeline Hari</h2>
                <p class="mt-0.5 text-xs jg-muted">Pilih hari untuk melihat urutan sesi dari pagi hingga sore.</p>
            </div>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($hariAktif as $namaHari)
                    <button type="button"
                            class="jg-btn jg-btn-outline min-h-9 px-3! text-xs!"
                            data-day-tab="{{ $namaHari }}"
                            aria-pressed="{{ $namaHari === $hariTerpilih ? 'true' : 'false' }}">
                        {{ $namaHari }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="p-4 sm:p-5">
            @foreach ($hariAktif as $namaHari)
                <div data-timeline="{{ $namaHari }}" @if ($namaHari !== $hariTerpilih) hidden @endif>
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-xs font-bold jg-title">{{ $namaHari }}, {{ $tanggalPekan[$namaHari] ?? '' }}</p>
                        <p class="text-xs jg-muted">{{ count($perHariTampil[$namaHari] ?? []) }} jadwal</p>
                    </div>

                    @if (empty($perHariTampil[$namaHari]))
                        <p class="rounded-xl border border-dashed border-[var(--jg-line)] px-4 py-8 text-center text-xs jg-muted">
                            Tidak ada jadwal pada {{ $namaHari }}.
                        </p>
                    @else
                        <div class="jg-timeline">
                            @foreach ($perHariTampil[$namaHari] as $itemHari)
                                @php
                                    $sedangBerjalan = $namaHari === $hariIni
                                        && $nowLabel >= $itemHari['mulai']
                                        && $nowLabel <= $itemHari['selesai'];
                                    $namaLengkap = $namaJurusanPenuh[$itemHari['jurusan_singkat']] ?? $itemHari['jurusan'];
                                @endphp
                                <div class="jg-timeline-item" data-live-item data-id="{{ $itemHari['id'] }}" data-hari="{{ $namaHari }}"
                                     data-mulai="{{ $itemHari['mulai'] }}" data-selesai="{{ $itemHari['selesai'] }}"
                                     data-live="{{ $sedangBerjalan ? 'true' : 'false' }}">
                                    <div class="grid grid-cols-[72px_minmax(0,1fr)] gap-3">
                                        <div class="pt-1 text-right">
                                            <p class="text-xs font-bold tabular-nums jg-title">{{ $itemHari['mulai'] }}</p>
                                            <p class="text-xs tabular-nums jg-muted">{{ $itemHari['selesai'] }}</p>
                                        </div>
                                        <div class="rounded-[14px] border border-[var(--jg-line)] bg-[var(--surface)] p-3">
                                            <div class="flex flex-wrap items-start justify-between gap-2">
                                                <div class="min-w-0">
                                                    <p class="text-xs font-bold jg-title">
                                                        {{ $itemHari['kelas'] }} · {{ $namaLengkap !== '' ? $namaLengkap : 'Semua Jurusan' }}
                                                    </p>
                                                    <p class="mt-0.5 text-xs jg-muted">
                                                        {{ $itemHari['mapel'] }} · {{ $itemHari['ruang'] !== '' ? $itemHari['ruang'] : 'Ruang —' }}
                                                    </p>
                                                </div>
                                                <div class="flex shrink-0 items-center gap-1.5">
                                                    @if ($itemHari['perlu_disesuaikan'] || ($itemHari['di_luar_sesi'] ?? false))
                                                        <x-lucide-triangle-alert class="h-4 w-4 text-[#B78100]" role="img" aria-label="Jam belum sesuai sesi terbaru atau di luar sesi" />
                                                    @endif
                                                    <span class="jg-status {{ $itemHari['status'] === 'bentrok' ? 'jg-status-bentrok' : ($itemHari['status'] === 'perlu_disesuaikan' ? 'jg-status-perlu' : 'jg-status-sesuai') }}">
                                                        {{ $itemHari['status_label'] }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="mt-2 flex items-center justify-between gap-2 border-t border-[var(--jg-line)] pt-2">
                                                <span class="text-xs jg-muted">{{ $itemHari['sesi_label'] }} · {{ $itemHari['label'] }}</span>
                                                @if ($bisaKelola && ($itemHari['perlu_disesuaikan'] || $itemHari['bentrok']))
                                                    <button type="button" class="text-xs font-bold text-[var(--jg-blue)] hover:underline" data-buka-penyesuaian>
                                                        Sesuaikan
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
