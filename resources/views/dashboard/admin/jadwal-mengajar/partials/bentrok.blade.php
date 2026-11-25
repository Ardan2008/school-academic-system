<div id="bentrok-overlay"
     class="jg-overlay"
     data-open="false"
     data-bentrok-overlay
     aria-hidden="true"
     inert>
    <div class="jg-panel jg-panel-adjust flex flex-col" role="dialog" aria-modal="true" aria-labelledby="bentrok-title">
        <div class="flex items-start justify-between gap-3 border-b border-[var(--jg-line)] px-5 py-4">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" style="background:#FDECEC; color:#A3261C;">
                    <x-lucide-alert-triangle class="h-5 w-5" aria-hidden="true" />
                </span>
                <div>
                    <h2 id="bentrok-title" class="text-base font-bold jg-title">Atur Ulang Bentrok</h2>
                    <p class="mt-1 text-xs leading-relaxed jg-muted" data-bentrok-progres>
                        Identifikasi bentrok → Detail konflik → Pilih jadwal → Pilih waktu → Pratinjau → Simpan
                    </p>
                </div>
            </div>
            <button type="button" class="jg-icon-btn" data-tutup-bentrok aria-label="Tutup panel atur ulang bentrok">
                <x-lucide-x class="h-4 w-4" aria-hidden="true" />
            </button>
        </div>

        <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
            <div class="rounded-xl px-3 py-6 text-center text-xs jg-muted" data-bentrok-muat hidden>
                Memuat detail konflik dari database...
            </div>

            <div class="rounded-xl border border-[#F3B7B1] px-3 py-6 text-center text-xs" style="background:#FDECEC; color:#A3261C;" data-bentrok-gagal hidden>
                <p data-bentrok-gagal-pesan>Detail konflik tidak dapat dimuat.</p>
                <button type="button" class="jg-btn jg-btn-outline mt-3 min-h-9!" data-bentrok-coba-lagi>Coba lagi</button>
            </div>

            <div class="space-y-4" data-bentrok-konten hidden>
                <section class="rounded-xl border border-[#F3B7B1] p-3.5" style="background:#FDECEC; color:#A3261C;">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-xs font-bold">Informasi Bentrok</h3>
                        <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-bold" data-bentrok-jenis>Bentrok</span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-3">
                        <div class="rounded-lg bg-white/80 px-2.5 py-2">
                            <p class="text-[11px] font-semibold opacity-70">Hari</p>
                            <p class="font-bold" data-bentrok-hari>-</p>
                        </div>
                        <div class="rounded-lg bg-white/80 px-2.5 py-2">
                            <p class="text-[11px] font-semibold opacity-70">Sesi</p>
                            <p class="font-bold" data-bentrok-sesi>-</p>
                        </div>
                        <div class="rounded-lg bg-white/80 px-2.5 py-2">
                            <p class="text-[11px] font-semibold opacity-70">Jam</p>
                            <p class="font-bold tabular-nums" data-bentrok-jam>-</p>
                        </div>
                    </div>
                    <div class="mt-3 space-y-2" data-bentrok-konflik></div>
                    <div class="mt-3 space-y-1.5" data-bentrok-daftar></div>
                </section>

                <section>
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-xs font-bold jg-title">Pilih Jadwal yang Akan Dipindahkan</h3>
                        <span class="text-[11px] font-semibold jg-muted" data-bentrok-jumlah-jadwal></span>
                    </div>
                    <div class="mt-2 space-y-2" data-bentrok-pilih-jadwal></div>
                </section>

                <section>
                    <h3 class="text-xs font-bold jg-title">Pilih Hari dan Sesi Pengganti</h3>
                    <p class="mt-1 text-[11px] jg-muted">Hanya waktu dengan guru, kelas, dan ruangan yang tersedia yang ditampilkan.</p>
                    <div class="mt-2 grid max-h-64 grid-cols-1 gap-2 overflow-y-auto pr-1 sm:grid-cols-2" data-bentrok-alternatif></div>
                    <div class="mt-2 rounded-xl border border-dashed border-[var(--jg-line)] px-3 py-5 text-center text-xs jg-muted" data-bentrok-kosong hidden>
                        Tidak ada hari dan sesi alternatif yang tersedia untuk jadwal ini. Ubah jadwal lain terlebih dahulu atau gunakan menu tambah/ubah jadwal.
                    </div>
                </section>

                <section class="rounded-xl border border-[var(--jg-line)] p-3.5" data-bentrok-pratinjau-wrap hidden>
                    <h3 class="text-xs font-bold jg-title">Pratinjau Sebelum Menyimpan</h3>
                    <div class="mt-3 space-y-2" data-bentrok-pratinjau></div>
                    <div class="mt-3 space-y-1.5" data-bentrok-pemeriksaan></div>
                </section>
            </div>
        </div>

        <div class="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 border-t border-[var(--jg-line)] bg-[var(--surface)] px-5 py-3">
            <p class="text-xs jg-muted" data-bentrok-ringkasan>Pilih jadwal dan waktu pengganti untuk melanjutkan.</p>
            <div class="flex items-center gap-2">
                <button type="button" class="jg-btn jg-btn-ghost" data-tutup-bentrok>Batal</button>
                <button type="button" class="jg-btn jg-btn-primary" data-bentrok-simpan disabled>
                    <x-lucide-check class="h-4 w-4" aria-hidden="true" />
                    <span data-bentrok-simpan-label>Simpan Perubahan</span>
                </button>
            </div>
        </div>
    </div>
</div>
