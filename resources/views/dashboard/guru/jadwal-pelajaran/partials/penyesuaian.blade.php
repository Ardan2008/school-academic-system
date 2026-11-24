@php
    $daftarSaran = collect($saran)->values();
    $bentrokGrup = collect($bentrokGroups)->values();
    $riwayat = collect($analysis['riwayat'] ?? [])->take(3)->values();
@endphp

<div id="penyesuaian-overlay"
     class="jg-overlay"
     data-open="false"
     data-adjust-overlay
     aria-hidden="true"
     inert>
    <div class="jg-panel jg-panel-adjust flex flex-col" role="dialog" aria-modal="true" aria-labelledby="penyesuaian-title">
        <div class="flex items-start justify-between gap-3 border-b border-[var(--jg-line)] px-5 py-4">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" style="background:#FFF1D6; color:#7A4300;">
                    <x-lucide-clock class="h-5 w-5" aria-hidden="true" />
                </span>
                <div>
                    <h2 id="penyesuaian-title" class="text-base font-bold jg-title">Sesuaikan dengan jam belajar terbaru</h2>
                    <p class="mt-1 text-xs leading-relaxed jg-muted">
                        {{ $daftarSaran->count() }} jadwal masih memakai jam lama. Periksa saran di bawah, lalu terapkan sekaligus dalam satu transaksi.
                    </p>
                </div>
            </div>
            <button type="button" class="jg-icon-btn" data-tutup-penyesuaian aria-label="Tutup panel penyesuaian">
                <x-lucide-x class="h-4 w-4" aria-hidden="true" />
            </button>
        </div>

        <div class="flex-1 space-y-5 px-5 py-4">
            <section>
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-xs font-bold jg-title">Saran otomatis · {{ $daftarSaran->count() }} jadwal</h3>
                    <label class="flex items-center gap-1.5 text-xs font-semibold jg-muted">
                        <input type="checkbox" data-saran-semua @checked($daftarSaran->isNotEmpty())>
                        Pilih semua
                    </label>
                </div>

                @if ($daftarSaran->isEmpty())
                    <p class="mt-3 rounded-xl border border-dashed border-[var(--jg-line)] px-3 py-5 text-center text-xs jg-muted">
                        Tidak ada jadwal yang memakai jam lama.
                    </p>
                @else
                    <div class="mt-3 space-y-2 rounded-xl border border-[var(--jg-line)] p-2.5">
                        @foreach ($daftarSaran as $index => $saranItem)
                            <label class="flex items-start gap-2.5 rounded-lg px-2 py-2 transition hover:bg-[var(--jg-surface-soft)]"
                                   data-saran-baris
                                   data-saran-extra="{{ $index >= 5 ? '1' : '0' }}"
                                   @if ($index >= 5) hidden @endif>
                                <input type="checkbox" class="mt-0.5 h-4 w-4 accent-[#1A6BDB]" data-saran-check
                                       data-id="{{ $saranItem['id'] }}"
                                       data-sesi-id="{{ $saranItem['sesi_id'] }}"
                                       data-hari="{{ $saranItem['hari'] }}"
                                       checked>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-xs font-bold jg-title">{{ $saranItem['hari'] }} · {{ $saranItem['kelas'] }}</span>
                                    <span class="block text-xs jg-muted">{{ $saranItem['sesi_label'] }} · {{ $saranItem['mapel'] }}</span>
                                    <span class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                                        <span class="tabular-nums whitespace-nowrap text-[#8A98AC] line-through">{{ $saranItem['lama'] }}</span>
                                        <x-lucide-arrow-right class="h-3 w-3 text-[#8A98AC]" aria-hidden="true" />
                                        <span class="tabular-nums whitespace-nowrap font-bold text-[#0F6B3C]">{{ $saranItem['baru'] }}</span>
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    @if ($daftarSaran->count() > 5)
                        <button type="button" class="mt-2 text-xs font-bold text-[var(--jg-blue)] hover:underline" data-saran-lain>
                            Tampilkan {{ $daftarSaran->count() - 5 }} jadwal lainnya
                        </button>
                    @endif
                @endif
            </section>

            <section>
                <h3 class="text-xs font-bold jg-title">Bentrok perlu keputusan · {{ $bentrokGrup->count() }} jadwal</h3>

                @if ($bentrokGrup->isEmpty())
                    <p class="mt-3 rounded-xl px-3 py-3 text-xs" style="background:#E0F6EA; color:#0F6B3C;">
                        Tidak ada bentrok pada jadwal Anda.
                    </p>
                @else
                    <div class="mt-3 space-y-3">
                        @foreach ($bentrokGrup as $grup)
                            <div class="rounded-xl border border-[#F3B7B1] p-3" style="background:#FDECEC; color:#A3261C;" data-bentrok-grup="{{ $grup['id'] }}">
                                <p class="text-xs font-bold leading-relaxed">
                                    Satu guru tidak bisa mengajar beberapa kelas di {{ $grup['hari'] }}, {{ $grup['sesi_label'] }} ({{ $grup['jam'] }}).
                                    Tentukan satu kelas yang tetap, lalu pindahkan sisanya.
                                </p>

                                <div class="mt-3 space-y-2">
                                    <div class="flex items-center justify-between gap-2 rounded-lg bg-[var(--surface)] px-3 py-2">
                                        <span class="text-xs font-bold text-[#10294B]">{{ $grup['keep_label'] }}</span>
                                        <span class="rounded-full px-2 py-0.5 text-xs font-bold" style="background:#E0F6EA; color:#0F6B3C;">
                                            Tetap di {{ $grup['sesi_label'] }}
                                        </span>
                                    </div>

                                    @foreach ($grup['alternatif'] as $alternatif)
                                        <div class="rounded-lg bg-[var(--surface)] px-3 py-2">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <span class="text-xs font-bold text-[#10294B]">{{ $alternatif['kelas'] }}</span>
                                                <label class="sr-only" for="pilih-sesi-{{ $alternatif['id'] }}">Pilih sesi untuk {{ $alternatif['kelas'] }}</label>
                                                <select id="pilih-sesi-{{ $alternatif['id'] }}" class="jg-select h-10! min-w-[220px]! text-xs!" data-bentrok-pilih data-id="{{ $alternatif['id'] }}"
                                                        @if ($alternatif['sesi'] === []) disabled @endif>
                                                    <option value="">{{ $alternatif['sesi'] === [] ? 'Tidak ada sesi kosong' : 'Pilih sesi pengganti' }}</option>
                                                    @foreach ($alternatif['sesi'] as $sesiKosong)
                                                        <option value="{{ $sesiKosong['id'] }}">
                                                            {{ $sesiKosong['label'] }} · {{ $sesiKosong['waktu'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            @if ($alternatif['sesi'] !== [])
                                                <p class="mt-1 flex items-center gap-1 text-xs font-semibold text-[#0F6B3C]">
                                                    <x-lucide-check class="h-3 w-3" aria-hidden="true" />
                                                    Guru dan ruang kosong
                                                </p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            @if ($riwayat->isNotEmpty())
                <section>
                    <h3 class="text-xs font-bold jg-title">Riwayat perubahan</h3>
                    <div class="mt-2 space-y-1.5">
                        @foreach ($riwayat as $itemRiwayat)
                            <div class="flex items-center justify-between gap-2 rounded-lg border border-[var(--jg-line)] px-3 py-2">
                                <p class="text-xs jg-muted">
                                    <span class="font-bold jg-title">{{ $itemRiwayat['aksi'] === 'batalkan' ? 'Pembatalan' : 'Penyesuaian' }}</span>
                                    · {{ $itemRiwayat['jumlah'] }} jadwal · <span class="tabular-nums whitespace-nowrap">{{ $itemRiwayat['waktu'] }}</span>
                                </p>
                                @if ($itemRiwayat['bisa_dibatalkan'])
                                    <button type="button" class="text-xs font-bold text-[var(--jg-blue)] hover:underline"
                                            data-undo-batch="{{ $itemRiwayat['batch'] }}">
                                        Batalkan
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <div class="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 border-t border-[var(--jg-line)] bg-[var(--surface)] px-5 py-3">
            <div>
                <p class="text-xs font-bold jg-title"><span data-jumlah-perubahan>0</span> perubahan dipilih</p>
                <p class="text-xs jg-muted">Bisa dibatalkan dari riwayat perubahan.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="jg-btn jg-btn-ghost" data-tutup-penyesuaian>Batal</button>
                <button type="button" class="jg-btn jg-btn-primary" data-terapkan>
                    <span data-terapkan-label>Terapkan 0 perubahan</span>
                </button>
            </div>
        </div>
    </div>
</div>
