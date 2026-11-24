@php
    $oldHariModal = old('hari', $formJadwal?->hari ?? '');
    $oldRombelModal = old('rombel', $formJadwal ? $formJadwal->kelas.'|'.$formJadwal->jurusan : '');
    $oldSesiIdModal = old('sesi_id', $formJadwal?->sesi_id ?? '');
    $oldRuangModal = old('ruang', $formJadwal?->ruang ?? '');
    $jamTersimpanModal = $formJadwal
        ? substr((string) $formJadwal->jam_mulai, 0, 5).'–'.substr((string) $formJadwal->jam_selesai, 0, 5)
        : '';
    $sesiTerisi = collect($items)
        ->filter(fn (array $item) => $item['hari'] === $oldHariModal
            && (int) $item['id'] !== (int) ($formJadwal?->id ?? 0)
            && $item['sesi_id'] !== null)
        ->mapWithKeys(fn (array $item) => [(int) $item['sesi_id'] => $item['kelas'].($item['jurusan_singkat'] !== '' ? ' '.$item['jurusan_singkat'] : '')])
        ->all();
    $ruangTersedia = collect($items)->pluck('ruang')->filter()->unique()->sort()->values();
@endphp

<div id="jadwal-overlay"
     class="jg-overlay"
     data-open="false"
     data-form-overlay
     aria-hidden="true"
     inert>
    <div class="jg-panel jg-panel-form flex flex-col" role="dialog" aria-modal="true" aria-labelledby="jadwal-modal-title">
        <div class="flex items-start justify-between gap-3 border-b border-[var(--jg-line)] px-5 py-4">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" style="background:#E8F1FF; color:#1A6BDB;">
                    <x-lucide-calendar-plus class="h-5 w-5" aria-hidden="true" />
                </span>
                <div>
                    <h2 id="jadwal-modal-title" class="text-base font-bold jg-title">Tambah jadwal mengajar</h2>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        <span class="rounded-full px-2 py-0.5 text-xs font-bold" style="background:#E8F1FF; color:#1B4F9E;">
                            {{ $mapelForm?->nama ?? 'Mata Pelajaran' }}
                        </span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-bold" style="background:#EEF2F8; color:#10294B;">
                            {{ \App\Support\Periode::label($periode['tahun_ajaran'], $periode['semester']) }}
                        </span>
                    </div>
                </div>
            </div>
            <button type="button" class="jg-icon-btn" data-tutup-jadwal-modal aria-label="Tutup form jadwal">
                <x-lucide-x class="h-4 w-4" aria-hidden="true" />
            </button>
        </div>

        @if ($errors->any())
            <div class="mx-5 mt-4 rounded-xl border border-[#F3B7B1] px-4 py-3 text-xs leading-relaxed" style="background:#FDECEC; color:#A3261C;" role="alert">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="jadwal-form"
              method="POST"
              action="{{ $formJadwal ? route('dashboard.guru.jadwal-pelajaran.update', $formJadwal->id) : route('dashboard.guru.jadwal-pelajaran.store') }}"
              data-mode="{{ $formJadwal ? 'edit' : 'tambah' }}"
              data-jadwal-id="{{ $formJadwal?->id ?? '' }}"
              data-jam-lama="{{ $jamTersimpanModal }}"
              class="flex-1 space-y-4 px-5 py-4">
            @csrf
            @if ($formJadwal)
                @method('PUT')
            @endif

            <input type="hidden" name="tahun_ajaran" value="{{ $periode['tahun_ajaran'] }}">
            <input type="hidden" name="semester" value="{{ $periode['semester'] }}">

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="jadwal-rombel" class="mb-1.5 block text-xs font-bold jg-title">Kelas <span class="text-[#A3261C]">*</span></label>
                    <select id="jadwal-rombel" name="rombel" required class="jg-select w-full">
                        <option value="">Pilih kelas</option>
                        @foreach ($rombelOptions as $opsi)
                            <option value="{{ $opsi['value'] }}" data-mapel="{{ $opsi['mapel_id'] }}" @selected($oldRombelModal === $opsi['value'])>
                                {{ $opsi['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="jadwal-ruang" class="mb-1.5 block text-xs font-bold jg-title">Ruang <span class="text-[#A3261C]">*</span></label>
                    <input id="jadwal-ruang" type="text" name="ruang" maxlength="60" value="{{ $oldRuangModal }}" required
                           list="daftar-ruang-jadwal" class="jg-input w-full" placeholder="Contoh: R.123" autocomplete="off">
                    <datalist id="daftar-ruang-jadwal">
                        @foreach ($ruangTersedia as $ruangOpsional)
                            <option value="{{ $ruangOpsional }}"></option>
                        @endforeach
                    </datalist>
                    <p class="mt-1 text-xs jg-muted">Boleh memilih ruang yang sudah ada atau menulis nama ruang baru.</p>
                </div>
            </div>

            <div class="relative">
                <label for="jadwal-mapel-tampil" class="mb-1.5 block text-xs font-bold jg-title">Mata Pelajaran</label>
                <div class="relative">
                    <input id="jadwal-mapel-tampil" type="text" readonly aria-readonly="true" disabled tabindex="-1"
                           value="{{ $mapelForm?->nama ?? 'Belum ada mata pelajaran yang ditugaskan' }}"
                           class="h-11 w-full cursor-not-allowed select-none rounded-xl border border-[var(--jg-line)] bg-[var(--jg-surface-soft)] px-3 pr-9 text-[13px] text-[#5B6B82] outline-none disabled:cursor-not-allowed disabled:bg-[var(--jg-surface-soft)] disabled:text-[#5B6B82] disabled:opacity-100">
                    <x-lucide-lock class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A98AC]" aria-hidden="true" />
                </div>
                <input type="hidden" id="jadwal-mapel" name="mata_pelajaran_id" value="{{ $mapelForm?->id }}">
            </div>

            <div>
                <div class="mb-1.5 flex items-center justify-between gap-2">
                    <span class="text-xs font-bold jg-title">Hari <span class="text-[#A3261C]">*</span></span>
                </div>
                <input type="hidden" id="jadwal-hari" name="hari" value="{{ $oldHariModal }}">
                <div class="grid grid-cols-5 gap-1.5" data-hari-grid style="grid-template-columns: repeat({{ count($hariAktif) }}, minmax(0, 1fr));">
                    @foreach ($hariAktif as $namaHari)
                        <button type="button" class="jg-btn jg-btn-outline min-h-10 px-1! text-xs!" data-hari-pilih="{{ $namaHari }}"
                                aria-pressed="{{ $oldHariModal === $namaHari ? 'true' : 'false' }}">
                            {{ $namaHari }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div>
                <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
                    <span class="text-xs font-bold jg-title">Sesi <span class="text-[#A3261C]">*</span></span>
                    <span class="text-xs jg-muted">Pilih sesi berurutan untuk jam pelajaran ganda</span>
                </div>
                <input type="hidden" id="jadwal-sesi" name="sesi_id" value="{{ $oldSesiIdModal }}">
                <div class="jg-session-grid grid grid-cols-3 gap-2" data-sesi-grid>
                    @foreach ($sesiOptions as $opsiSesi)
                        @php
                            $terisiOleh = $sesiTerisi[(int) $opsiSesi['id']] ?? null;
                            $dipilih = (string) $oldSesiIdModal === (string) $opsiSesi['id'];
                        @endphp
                        <button type="button"
                                class="jg-session-pilih flex min-h-16 flex-col justify-between"
                                data-sesi-pilih
                                data-id="{{ $opsiSesi['id'] }}"
                                data-mulai="{{ $opsiSesi['mulai'] }}"
                                data-selesai="{{ $opsiSesi['selesai'] }}"
                                data-label="{{ $opsiSesi['label'] }}"
                                aria-pressed="{{ $dipilih ? 'true' : 'false' }}"
                                @disabled($terisiOleh !== null)>
                            <span class="flex items-start justify-between gap-1">
                                <span class="text-xs font-bold jg-title">Sesi {{ $opsiSesi['nomor'] ?: $loop->iteration }}</span>
                                @if ($dipilih)
                                    <x-lucide-check class="h-3.5 w-3.5 text-[var(--jg-blue)]" aria-hidden="true" />
                                @endif
                            </span>
                            <span class="mt-1 block whitespace-nowrap text-xs tabular-nums jg-muted">{{ $opsiSesi['mulai'] }}–{{ $opsiSesi['selesai'] }}</span>
                            <span class="mt-0.5 block text-xs font-semibold {{ $terisiOleh !== null ? 'text-[#8A98AC]' : 'text-[#0F6B3C]' }}" data-sesi-status>
                                {{ $terisiOleh !== null ? 'Terisi · '.$terisiOleh : ($dipilih ? 'Dipilih' : 'Kosong') }}
                            </span>
                        </button>
                    @endforeach
                </div>
                <p class="mt-1.5 text-xs jg-muted">Jam operasional sekolah: <span class="whitespace-nowrap tabular-nums">{{ $jamOperasional['mulai'] }}–{{ $jamOperasional['selesai'] }}</span></p>
            </div>

            <div class="rounded-xl border px-3 py-3" data-check-panel style="border-color:#E3E9F2; background:var(--jg-surface-soft);">
                <div class="flex items-start gap-2">
                    <x-lucide-shield-check class="mt-0.5 h-4 w-4 shrink-0 text-[#5B6B82]" data-check-icon aria-hidden="true" />
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold jg-title" data-check-judul>Pemeriksaan bentrok</p>
                        <p class="mt-0.5 text-xs jg-muted" data-check-pesan>
                            Pilih hari, kelas, ruang, dan sesi untuk memeriksa bentrok secara langsung.
                        </p>
                        <p class="mt-1 hidden text-xs font-semibold jg-title" data-check-ringkasan></p>
                        <ul class="mt-2 hidden space-y-1" data-check-detail></ul>
                    </div>
                </div>
            </div>
        </form>

        <div class="sticky bottom-0 flex flex-wrap items-center justify-between gap-2 border-t border-[var(--jg-line)] bg-[var(--surface)] px-5 py-3">
            <button type="button" class="jg-btn jg-btn-ghost" data-tutup-jadwal-modal>Batal</button>
            <div class="flex items-center gap-2">
                <button type="submit" form="jadwal-form" name="simpan_lagi" value="1" class="jg-btn jg-btn-outline" data-simpan-lagi>
                    Simpan & tambah lagi
                </button>
                <button type="submit" form="jadwal-form" id="jadwal-submit" class="jg-btn jg-btn-primary" data-simpan-utama>
                    <x-lucide-save class="h-4 w-4" aria-hidden="true" />
                    <span data-label-simpan>Simpan jadwal</span>
                </button>
            </div>
        </div>
    </div>
</div>
