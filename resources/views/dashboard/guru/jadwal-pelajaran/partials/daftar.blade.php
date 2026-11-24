@php
    $bisaKelola = $bisaKelola ?? true;
    $semuaItems = collect($items);
    $jumlahPerlu = $semuaItems->where('perlu_disesuaikan', true)->count();
    $jumlahBentrok = $semuaItems->where('bentrok', true)->count();
    $namaJurusanPenuh = [
        'RPL' => 'Rekayasa Perangkat Lunak',
        'TKJ' => 'Teknik Komputer Jaringan',
    ];
@endphp

<section data-view-panel="daftar" hidden>
    <div class="jg-card mb-4 px-4 py-4 sm:px-5">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="jg-filter-chip" data-list-filter="semua" aria-pressed="true"
                        style="background:#1A6BDB; color:#fff;">
                    Semua · {{ $semuaItems->count() }}
                </button>
                <button type="button" class="jg-filter-chip" data-list-filter="perlu" aria-pressed="false"
                        style="background:#FFF1D6; color:#7A4300;">
                    Perlu disesuaikan · {{ $jumlahPerlu }}
                </button>
                <button type="button" class="jg-filter-chip" data-list-filter="bentrok" aria-pressed="false"
                        style="background:#FDECEC; color:#A3261C;">
                    Bentrok · {{ $jumlahBentrok }}
                </button>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <label class="relative block">
                    <span class="sr-only">Cari jadwal</span>
                    <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#5B6B82]" aria-hidden="true" />
                    @if (($mode ?? 'guru') === 'admin')
                        <input type="search" class="jg-input jg-cari-daftar-admin w-full sm:w-56" value="{{ $filters['cari'] ?? '' }}" placeholder="Cari mata pelajaran, guru, kelas, atau ruang..." data-cari="daftar" data-list-search>
                    @else
                        <input type="search" class="jg-input w-full pl-9 sm:w-56" placeholder="Cari kelas atau ruang" data-list-search>
                    @endif
                </label>
                <label class="sr-only" for="daftar-hari">Filter hari</label>
                <select id="daftar-hari" class="jg-select" data-list-hari>
                    <option value="">Semua hari</option>
                    @foreach ($hariAktif as $namaHari)
                        <option value="{{ $namaHari }}" @selected($hariAwal === $namaHari)>{{ $namaHari }}</option>
                    @endforeach
                </select>
                <label class="sr-only" for="daftar-kelas">Filter kelas</label>
                <select id="daftar-kelas" class="jg-select" data-list-kelas>
                    <option value="">Semua kelas</option>
                    @foreach ($kelas as $opsiKelas)
                        <option value="{{ $opsiKelas['jurusan'].'|'.$opsiKelas['kelas'] }}">
                            {{ $opsiKelas['kelas'] }}{{ $opsiKelas['jurusan'] !== '' ? ' · '.$opsiKelas['jurusan'] : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="space-y-3">
        @foreach ($hariAktif as $namaHari)
            @php
                $itemsHari = collect($perHariTampil[$namaHari] ?? []);
                $perluHari = $itemsHari->where('perlu_disesuaikan', true)->count();
                $bentrokHari = $itemsHari->where('bentrok', true)->count();
                $jamAwal = $itemsHari->pluck('mulai')->filter()->sort()->first() ?? '—';
                $jamAkhir = $itemsHari->pluck('selesai')->filter()->sort()->last() ?? '—';
            @endphp
            <details class="jg-card overflow-hidden" data-day-group="{{ $namaHari }}" @if ($loop->first) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3.5 [&::-webkit-details-marker]:hidden sm:px-5">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" style="background:#E8F1FF; color:#1A6BDB;">
                            <x-lucide-calendar-days class="h-4 w-4" aria-hidden="true" />
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold jg-title">
                                {{ $namaHari }}
                                @if ($namaHari === $hariIni)
                                    <span class="ml-1 rounded-full bg-[var(--jg-soft)] px-2 py-0.5 text-xs font-bold text-[var(--jg-blue)]">Hari ini</span>
                                @endif
                            </p>
                            <p class="text-xs jg-muted">
                                {{ $tanggalPekan[$namaHari] ?? '' }} · {{ $itemsHari->count() }} jadwal · {{ $jamAwal }}–{{ $jamAkhir }}
                            </p>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        @if ($perluHari > 0)
                            <span class="hidden rounded-full px-2 py-1 text-xs font-semibold sm:inline-flex" style="background:#FFF1D6; color:#7A4300;">
                                {{ $perluHari }} perlu disesuaikan
                            </span>
                        @endif
                        @if ($bentrokHari > 0)
                            <span class="hidden rounded-full px-2 py-1 text-xs font-semibold sm:inline-flex" style="background:#FDECEC; color:#A3261C;">
                                {{ $bentrokHari }} bentrok
                            </span>
                        @endif
                        <x-lucide-chevron-down class="h-4 w-4 text-[#5B6B82] transition-transform" data-chevron aria-hidden="true" />
                    </div>
                </summary>

                @if ($itemsHari->isEmpty())
                    <p class="border-t border-[var(--jg-line)] px-5 py-8 text-center text-xs jg-muted">Tidak ada jadwal pada {{ $namaHari }}.</p>
                @else
                    <div class="overflow-x-auto border-t border-[var(--jg-line)]">
                        <table class="w-full min-w-[860px] text-left text-xs">
                            <thead>
                                <tr class="border-b border-[var(--jg-line)] bg-[var(--jg-surface-soft)] text-xs font-bold uppercase tracking-wide jg-muted">
                                    <th class="px-4 py-2.5 font-semibold">Sesi</th>
                                    <th class="px-4 py-2.5 font-semibold">Jam</th>
                                    <th class="px-4 py-2.5 font-semibold">Mata Pelajaran</th>
                                    <th class="px-4 py-2.5 font-semibold">Kelas</th>
                                    <th class="px-4 py-2.5 font-semibold">Ruang</th>
                                    <th class="px-4 py-2.5 font-semibold">Status</th>
                                    <th class="px-4 py-2.5 text-right font-semibold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--jg-line)]">
                                @foreach ($itemsHari as $itemList)
                                    @php
                                        $statusKelas = $itemList['status'] === 'bentrok'
                                            ? 'jg-status-bentrok'
                                            : ($itemList['status'] === 'perlu_disesuaikan' ? 'jg-status-perlu' : 'jg-status-sesuai');
                                        $statusFilter = $itemList['status'] === 'bentrok' ? 'bentrok'
                                            : ($itemList['perlu_disesuaikan'] ? 'perlu' : 'sesuai');
                                        $simpan = explode('–', $itemList['tersimpan']);
                                        $kelasLengkap = $itemList['kelas'].' · '.($namaJurusanPenuh[$itemList['jurusan_singkat']] ?? ($itemList['jurusan'] !== '' ? $itemList['jurusan'] : 'Semua Jurusan'));
                                        $teksCari = strtolower($kelasLengkap.' '.$itemList['ruang'].' '.$itemList['mapel'].' '.$itemList['kelas']);
                                    @endphp
                                    <tr class="jg-row"
                                        data-list-row
                                        data-id="{{ $itemList['id'] }}"
                                        data-hari="{{ $itemList['hari'] }}"
                                        data-status="{{ $statusFilter }}"
                                        data-kelas-key="{{ $itemList['rombel_key'] }}"
                                        data-search="{{ $teksCari }}"
                                        data-delete-item="jadwal:{{ $itemList['id'] }}">
                                        <td class="px-4 py-3">
                                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-xs font-bold" style="background:#E8F1FF; color:#1B4F9E;">
                                                {{ $itemList['sesi_nomor'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 tabular-nums font-semibold jg-title">
                                            {{ $itemList['label'] }}
                                            @if ($itemList['perlu_disesuaikan'])
                                                <span class="mt-0.5 block text-xs font-medium text-[#7A4300]">tersimpan {{ $itemList['tersimpan'] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" style="background:#E8F1FF; color:#1B4F9E;">
                                                {{ $itemList['mapel'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 font-medium jg-title">{{ $kelasLengkap }}</td>
                                        <td class="px-4 py-3 jg-muted">{{ $itemList['ruang'] !== '' ? $itemList['ruang'] : '—' }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-1.5">
                                                @if ($itemList['perlu_disesuaikan'] || ($itemList['di_luar_sesi'] ?? false))
                                                    <x-lucide-triangle-alert class="h-3.5 w-3.5 shrink-0 text-[#B78100]" role="img" aria-label="Jam belum sesuai sesi terbaru atau di luar sesi" />
                                                @endif
                                                <span class="jg-status {{ $statusKelas }}">{{ $itemList['status_label'] }}</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($bisaKelola && isset($routeNames['destroy']))
                                                <div class="flex items-center justify-end gap-1.5">
                                                    @if ($itemList['perlu_disesuaikan'] || $itemList['bentrok'])
                                                        <button type="button" class="jg-btn jg-btn-outline min-h-8 px-2.5! text-xs!" data-buka-penyesuaian>
                                                            Sesuaikan
                                                        </button>
                                                    @endif
                                                    <button type="button"
                                                            class="jg-icon-btn"
                                                            data-edit-jadwal-trigger
                                                            data-id="{{ $itemList['id'] }}"
                                                            data-guru-id="{{ $itemList['guru_id'] ?? '' }}"
                                                            data-hari="{{ $itemList['hari'] }}"
                                                            data-mapel="{{ $itemList['mapel_id'] }}"
                                                            data-mapel-nama="{{ $itemList['mapel'] }}"
                                                            data-rombel="{{ $itemList['kelas'] }}|{{ $itemList['jurusan'] }}"
                                                            data-sesi-id="{{ $itemList['sesi_id'] }}"
                                                            data-mulai="{{ $simpan[0] ?? '' }}"
                                                            data-selesai="{{ $simpan[1] ?? '' }}"
                                                            data-ruang="{{ $itemList['ruang'] }}"
                                                            aria-label="Ubah jadwal {{ $itemList['kelas'] }}"
                                                            title="Ubah jadwal">
                                                        <x-lucide-pencil class="h-4 w-4" aria-hidden="true" />
                                                    </button>
                                                    <div class="jg-more">
                                                        <button type="button" class="jg-icon-btn" data-more-toggle aria-expanded="false"
                                                                aria-label="Menu aksi jadwal {{ $itemList['id'] }}">
                                                            <x-lucide-ellipsis class="h-4 w-4" aria-hidden="true" />
                                                        </button>
                                                        <div class="jg-more-menu hidden" data-more-menu>
                                                            <button type="button" class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-xs font-medium text-[#10294B] transition hover:bg-[var(--jg-surface-soft)]"
                                                                    data-edit-jadwal-trigger
                                                                    data-id="{{ $itemList['id'] }}"
                                                                    data-guru-id="{{ $itemList['guru_id'] ?? '' }}"
                                                                    data-hari="{{ $itemList['hari'] }}"
                                                                    data-mapel="{{ $itemList['mapel_id'] }}"
                                                                    data-mapel-nama="{{ $itemList['mapel'] }}"
                                                                    data-rombel="{{ $itemList['kelas'] }}|{{ $itemList['jurusan'] }}"
                                                                    data-sesi-id="{{ $itemList['sesi_id'] }}"
                                                                    data-mulai="{{ $simpan[0] ?? '' }}"
                                                                    data-selesai="{{ $simpan[1] ?? '' }}"
                                                                    data-ruang="{{ $itemList['ruang'] }}">
                                                                <x-lucide-pencil class="h-3.5 w-3.5" aria-hidden="true" />
                                                                Ubah jadwal
                                                            </button>
                                                            <form method="POST"
                                                                  action="{{ route($routeNames['destroy'], $itemList['id']) }}"
                                                                  data-delete-form
                                                                  data-delete-kind="jadwal"
                                                                  data-delete-id="{{ $itemList['id'] }}"
                                                                  data-delete-name="{{ $itemList['kelas'] }}"
                                                                  data-delete-title="Hapus jadwal {{ $namaHari }} Sesi {{ $itemList['sesi_nomor'] }} kelas {{ $itemList['kelas'] }}?"
                                                                  data-delete-text="Jadwal {{ $itemList['kelas'] }} pada {{ $namaHari }} sesi {{ $itemList['sesi_nomor'] }} akan dihapus. Siswa terkait menerima notifikasi perubahan."
                                                                  data-delete-loading="Menghapus jadwal..."
                                                                  data-delete-counter-key="jadwal:{{ $namaHari }}">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-xs font-medium text-[#A3261C] transition hover:bg-[#FDECEC]">
                                                                    <x-lucide-trash-2 class="h-3.5 w-3.5" aria-hidden="true" />
                                                                    Hapus jadwal
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-xs jg-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </details>
        @endforeach
    </div>

    @if (($mode ?? 'guru') === 'admin')
        <div class="jg-cari-kosong mt-4" data-cari-kosong hidden>
            <x-lucide-search class="h-5 w-5" aria-hidden="true" />
            <p class="mt-2 text-sm font-bold jg-title">Jadwal tidak ditemukan</p>
            <p class="mt-1 text-xs leading-relaxed jg-muted">Tidak ada jadwal yang cocok dengan kata kunci pencarian. Coba gunakan kata kunci lain.</p>
        </div>
    @endif

    <p class="mt-3 text-xs jg-muted">
        Hari pertama dibuka secara default. Gunakan menu “⋯” untuk menghapus jadwal; penghapusan tetap meminta konfirmasi.
    </p>
</section>
