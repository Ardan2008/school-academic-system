@extends('layouts.dashboard')

@section('title', 'Pengaturan Jam Belajar')
@section('subtitle', 'Dashboard Admin')

@section('content')
    <div id="jam-page">
        <div data-reveal style="--d: .06s" class="rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 p-4 sm:p-5">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-primary-60">
                        <x-lucide-clock class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Pengaturan Jam Belajar</h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Jam operasional dan sesi dipakai Guru saat menyusun jadwal mengajar.
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" id="jam-operasional-edit"
                            class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-xs font-medium text-slate-600 transition hover:border-primary-60 hover:bg-blue-50 hover:text-primary-70">
                        Atur Jam Operasional
                    </button>
                    <button type="button" id="jam-sesi-add"
                            class="inline-flex h-10 items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-primary-60 px-4 text-sm font-semibold text-white transition hover:bg-primary-70 disabled:cursor-not-allowed disabled:opacity-50">
                        <x-lucide-plus class="h-4 w-4" />
                        Tambah Sesi
                    </button>
                </div>
            </div>

            <div class="grid gap-4 p-4 sm:p-5 lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)]">
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Jam Operasional Sekolah</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-primary-70">
                        <span data-jam-masuk>{{ $jamBelajarData['konfigurasi']['jam_masuk'] }}</span>
                        <span class="text-slate-400">–</span>
                        <span data-jam-selesai>{{ $jamBelajarData['konfigurasi']['jam_selesai'] }}</span>
                    </p>
                    <p data-jam-rentang class="mt-1 text-xs text-slate-500">
                        Rentang waktu berlaku untuk seluruh sesi.
                    </p>
                    <p data-jam-keterangan class="mt-2 text-xs text-slate-500">
                        {{ $jamBelajarData['konfigurasi']['keterangan'] ?: 'Belum ada keterangan tambahan.' }}
                    </p>
                    <p data-jam-hari-aktif class="mt-1 text-xs text-slate-500">
                        Hari belajar: {{ implode(', ', $jamBelajarData['konfigurasi']['hari_aktif']) }}
                    </p>
                </div>

                <div class="min-w-0 overflow-x-auto rounded-xl border border-slate-100">
                    <table class="w-full min-w-[34rem] text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2.5 font-medium">No</th>
                                <th class="px-3 py-2.5 font-medium">Nama Sesi</th>
                                <th class="px-3 py-2.5 font-medium">Jenis</th>
                                <th class="px-3 py-2.5 font-medium">Waktu</th>
                                <th class="px-3 py-2.5 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="jam-sesi-body" class="divide-y divide-slate-100">
                            @forelse ($jamBelajarData['sesi'] as $sesi)
                                <tr data-sesi-row data-sesi='@json($sesi)'>
                                    <td class="px-3 py-2.5 tabular-nums text-slate-400">{{ $sesi['urutan'] }}</td>
                                    <td class="px-3 py-2.5 font-medium text-slate-700">
                                        {{ $sesi['nama'] }}
                                        @if ($sesi['jenis'] === 'pelajaran')
                                            <span class="ml-1 text-[11px] font-normal text-slate-400">Jam ke-{{ $sesi['nomor_pelajaran'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $sesi['jenis'] === 'istirahat' ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-primary-70' }}">
                                            {{ $sesi['jenis_label'] }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5 tabular-nums text-slate-600">{{ $sesi['waktu'] }}</td>
                                    <td class="px-3 py-2.5">
                                        <div class="inline-flex items-center justify-end gap-2">
                                            <button type="button" data-jam-sesi-edit
                                                    aria-label="Ubah {{ $sesi['nama'] }}" title="Ubah {{ $sesi['nama'] }}"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-blue-300 bg-white text-primary-60 transition hover:bg-blue-50 active:scale-90">
                                                <x-lucide-pencil class="h-4 w-4" />
                                            </button>
                                            <button type="button" data-jam-sesi-hapus
                                                    aria-label="Hapus {{ $sesi['nama'] }}" title="Hapus {{ $sesi['nama'] }}"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-red-300 bg-white text-red-500 transition hover:bg-red-50 active:scale-90">
                                                <x-lucide-trash-2 class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-8 text-center text-xs text-slate-400">Belum ada sesi jam belajar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <style>
            #jam-page.reveal-ready [data-reveal] {
                opacity: 0;
                transform: translateY(14px);
                filter: blur(2px);
                transition:
                    opacity .6s cubic-bezier(.22, 1, .36, 1) var(--d, 0s),
                    transform .6s cubic-bezier(.22, 1, .36, 1) var(--d, 0s),
                    filter .6s cubic-bezier(.22, 1, .36, 1) var(--d, 0s);
                will-change: opacity, transform;
            }

            #jam-page.reveal-ready [data-reveal].is-visible {
                opacity: 1;
                transform: none;
                filter: none;
            }

            @media (max-width: 639px) {
                #jam-page [data-reveal] { --d: 0s !important; }
            }

            .km {
                opacity: 0;
                pointer-events: none;
                transition: opacity .25s ease;
            }

            .km[data-open="true"] {
                opacity: 1;
                pointer-events: auto;
            }

            .km-backdrop {
                opacity: 0;
                transition: opacity .25s ease;
            }

            .km[data-open="true"] .km-backdrop {
                opacity: 1;
            }

            .km-panel {
                opacity: 0;
                transform: translateY(28px) scale(.97);
                transition:
                    opacity .32s cubic-bezier(.22, 1, .36, 1),
                    transform .32s cubic-bezier(.22, 1, .36, 1);
            }

            .km[data-open="true"] .km-panel {
                opacity: 1;
                transform: none;
            }

            .km-close {
                transition: transform .2s ease;
            }

            .km-close:hover { transform: scale(1.06); }
            .km-close:active { transform: scale(.94); }

            @media (prefers-reduced-motion: reduce) {
                #jam-page.reveal-ready [data-reveal] {
                    opacity: 1;
                    transform: none;
                    filter: none;
                    transition: none;
                }

                .km, .km-backdrop, .km-panel, .km-close { transition: none; }
                .km[data-open="true"] .km-panel { opacity: 1; transform: none; }
            }
        </style>

        <div id="jam-modal"
             data-open="false"
             aria-hidden="true"
             inert
             class="km fixed inset-0 z-60 flex items-end justify-center overflow-y-auto overscroll-contain bg-[#0F3D73]/60 p-0 sm:items-center sm:p-4 lg:left-64"
             role="dialog"
             aria-modal="true"
             aria-labelledby="jam-modal-title">
            <div data-jam-backdrop class="km-backdrop absolute inset-0"></div>

            <div class="km-panel relative z-10 flex max-h-[92dvh] w-full max-w-lg flex-col rounded-t-3xl bg-white shadow-2xl sm:max-h-[88dvh] sm:rounded-2xl">
                <header class="flex shrink-0 items-start gap-3 border-b border-slate-100 px-5 py-4">
                    <div class="min-w-0 flex-1">
                        <h3 id="jam-modal-title" data-jam-title class="text-lg font-bold text-primary-70">Atur Jam Operasional</h3>
                        <p data-jam-subtitle class="mt-0.5 text-xs text-slate-500">Pengaturan ini dipakai seluruh Guru.</p>
                    </div>
                    <button type="button" data-jam-close aria-label="Tutup"
                            class="km-close rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                        <x-lucide-x class="h-5 w-5" />
                    </button>
                </header>

                <form id="jam-form" class="grid gap-4 overflow-y-auto px-5 py-5" autocomplete="off" novalidate>
                    <div data-jam-operasional-fields class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="jam-masuk" class="mb-1.5 block text-xs font-medium text-slate-700">Jam Masuk Sekolah <span class="text-red-500">*</span></label>
                            <input id="jam-masuk" name="jam_masuk" type="time" required
                                   class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20">
                        </div>
                        <div>
                            <label for="jam-selesai" class="mb-1.5 block text-xs font-medium text-slate-700">Jam Selesai Sekolah <span class="text-red-500">*</span></label>
                            <input id="jam-selesai" name="jam_selesai" type="time" required
                                   class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="jam-keterangan" class="mb-1.5 block text-xs font-medium text-slate-700">Keterangan <span class="font-normal text-slate-400">(opsional)</span></label>
                            <input id="jam-keterangan" name="keterangan" type="text" maxlength="255"
                                   class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none placeholder:text-slate-400 focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20"
                                   placeholder="Contoh: Jam efektif hari Senin–Jumat">
                        </div>
                        <div class="sm:col-span-2">
                            <p class="mb-2 text-xs font-medium text-slate-700">Hari Belajar Aktif <span class="text-red-500">*</span></p>
                            <div class="flex flex-wrap gap-2">
                                @foreach (\App\Models\JadwalPelajaran::HARI as $namaHari)
                                    <label class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-600">
                                        <input type="checkbox" name="hari_aktif[]" value="{{ $namaHari }}"
                                               class="jam-hari-aktif h-3.5 w-3.5 rounded border-slate-300 text-primary-60 focus:ring-primary-60/30"
                                               @checked(in_array($namaHari, $jamBelajarData['konfigurasi']['hari_aktif'], true))>
                                        <span>{{ $namaHari }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="mt-1.5 text-xs text-slate-500">Hari yang tidak dipilih tidak tersedia pada jadwal Guru dan Siswa.</p>
                        </div>
                    </div>

                    <div data-jam-sesi-fields class="hidden grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="jam-nama" class="mb-1.5 block text-xs font-medium text-slate-700">Nama Sesi <span class="text-red-500">*</span></label>
                            <input id="jam-nama" name="nama" type="text" maxlength="60" required
                                   class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none placeholder:text-slate-400 focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20"
                                   placeholder="Contoh: Jam Pelajaran 6">
                        </div>
                        <div>
                            <label for="jam-jenis" class="mb-1.5 block text-xs font-medium text-slate-700">Jenis <span class="text-red-500">*</span></label>
                            <select id="jam-jenis" name="jenis" required
                                    class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20">
                                <option value="pelajaran">Pelajaran</option>
                                <option value="istirahat">Istirahat</option>
                            </select>
                        </div>
                        <div>
                            <label for="jam-urutan" class="mb-1.5 block text-xs font-medium text-slate-700">Urutan <span class="text-red-500">*</span></label>
                            <input id="jam-urutan" name="urutan" type="number" min="1" max="55" required readonly
                                   class="h-10 w-full rounded-xl border border-slate-300 bg-slate-50 px-3 text-sm text-slate-800 outline-none focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20">
                            <p class="mt-1 text-[11px] text-slate-400">Dihitung otomatis dari jam mulai. Maksimal 55 sesi.</p>
                        </div>
                        <div>
                            <label for="jam-mulai-sesi" class="mb-1.5 block text-xs font-medium text-slate-700">Jam Mulai <span class="text-red-500">*</span></label>
                            <input id="jam-mulai-sesi" name="jam_mulai" type="time" required
                                   class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20">
                        </div>
                        <div>
                            <label for="jam-selesai-sesi" class="mb-1.5 block text-xs font-medium text-slate-700">Jam Selesai <span class="text-red-500">*</span></label>
                            <input id="jam-selesai-sesi" name="jam_selesai" type="time" required
                                   class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20">
                        </div>
                        <p data-jam-info class="sm:col-span-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500"></p>
                    </div>

                    <p data-jam-error class="hidden rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700"></p>

                    <footer class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" data-jam-close
                                class="km-close inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                            Batal
                        </button>
                        <button data-jam-save type="submit"
                                class="inline-flex h-10 items-center rounded-xl bg-primary-60 px-4 text-sm font-medium text-white transition hover:bg-primary-70 disabled:cursor-not-allowed disabled:opacity-60">
                            <span data-jam-save-text>Simpan</span>
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <script>
            (function () {
                const root = document.getElementById('jam-page');
                const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                root.classList.add('reveal-ready');
                root.querySelectorAll('[data-reveal]').forEach((el) => {
                    if (reduceMotion || !('IntersectionObserver' in window)) {
                        el.classList.add('is-visible');

                        return;
                    }

                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach((entry) => {
                            if (!entry.isIntersecting) return;
                            entry.target.classList.add('is-visible');
                            observer.unobserve(entry.target);
                        });
                    }, { threshold: 0.08 });

                    observer.observe(el);
                });

                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

                const jsonRequest = (url, method, payload = null) => fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: payload === null ? undefined : JSON.stringify(payload),
                });

                const toast = (text) => {
                    if (!window.Swal) return;
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text,
                        showConfirmButton: false,
                        timer: 1400,
                        timerProgressBar: true,
                        allowOutsideClick: false,
                    });
                };

                const alertError = (title, text) => {
                    if (window.Swal) {
                        Swal.fire({ icon: 'error', title, text, confirmButtonText: 'Coba Lagi' });
                    } else {
                        window.alert(text);
                    }
                };

                const jamData = @json($jamBelajarData);
                const jamModal = document.getElementById('jam-modal');
                const jamForm = document.getElementById('jam-form');
                const jamTitle = jamModal.querySelector('[data-jam-title]');
                const jamSubtitle = jamModal.querySelector('[data-jam-subtitle]');
                const jamOperasionalFields = jamModal.querySelector('[data-jam-operasional-fields]');
                const jamSesiFields = jamModal.querySelector('[data-jam-sesi-fields]');
                const jamInfo = jamModal.querySelector('[data-jam-info]');
                const jamError = jamModal.querySelector('[data-jam-error]');
                const jamSave = jamModal.querySelector('[data-jam-save]');
                const jamSaveText = jamModal.querySelector('[data-jam-save-text]');
                const jamBody = document.getElementById('jam-sesi-body');
                const jamOperasionalUrl = @json(route('dashboard.admin.data-kelas.jam-belajar.operasional.update'));
                const jamSesiUrl = @json(route('dashboard.admin.data-kelas.jam-belajar.sesi.store'));
                const jamSesiUrlFor = (id) => jamSesiUrl + '/' + encodeURIComponent(id);
                const jamField = {
                    masuk: document.getElementById('jam-masuk'),
                    selesai: document.getElementById('jam-selesai'),
                    keterangan: document.getElementById('jam-keterangan'),
                    hariAktif: Array.from(document.querySelectorAll('.jam-hari-aktif')),
                    nama: document.getElementById('jam-nama'),
                    jenis: document.getElementById('jam-jenis'),
                    urutan: document.getElementById('jam-urutan'),
                    mulai: document.getElementById('jam-mulai-sesi'),
                    selesaiSesi: document.getElementById('jam-selesai-sesi'),
                };
                let jamMode = 'operasional';
                let jamEditId = null;

                const jamEscape = (nilai) => String(nilai ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/'/g, '&#39;')
                    .replace(/"/g, '&quot;');

                const jamPesanGalat = (body, status) => {
                    const errors = body?.errors ? Object.values(body.errors).flat() : [];

                    return errors[0] || body?.message
                        || (status === 422 ? 'Periksa kembali input yang diberikan.' : 'Terjadi kesalahan. Silakan coba lagi.');
                };

                const jamHitungUrutan = () => {
                    if (! jamField.mulai.value) return;
                    const waktu = jamData.sesi
                        .filter((item) => String(item.id) !== String(jamEditId))
                        .map((item) => item.jam_mulai)
                        .filter(Boolean);
                    waktu.push(jamField.mulai.value);
                    waktu.sort();
                    jamField.urutan.value = String(waktu.indexOf(jamField.mulai.value) + 1);
                };

                const jamRenderSesi = () => {
                    if (!jamData.sesi.length) {
                        jamBody.innerHTML = '<tr><td colspan="5" class="px-3 py-8 text-center text-xs text-slate-400">Belum ada sesi jam belajar.</td></tr>';

                        return;
                    }

                    jamBody.innerHTML = jamData.sesi.map((s) => `
                        <tr data-sesi-row data-sesi='${jamEscape(JSON.stringify(s))}'>
                            <td class="px-3 py-2.5 tabular-nums text-slate-400">${jamEscape(s.urutan)}</td>
                            <td class="px-3 py-2.5 font-medium text-slate-700">${jamEscape(s.nama)}${s.jenis === 'pelajaran' ? ' <span class="ml-1 text-[11px] font-normal text-slate-400">Jam ke-' + jamEscape(s.nomor_pelajaran) + '</span>' : ''}</td>
                            <td class="px-3 py-2.5">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium ${s.jenis === 'istirahat' ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-primary-70'}">${jamEscape(s.jenis_label)}</span>
                            </td>
                            <td class="px-3 py-2.5 tabular-nums text-slate-600">${jamEscape(s.waktu)}</td>
                            <td class="px-3 py-2.5">
                                <div class="inline-flex items-center justify-end gap-2">
                                    <button type="button" data-jam-sesi-edit aria-label="Ubah ${jamEscape(s.nama)}" title="Ubah ${jamEscape(s.nama)}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-blue-300 bg-white text-primary-60 transition hover:bg-blue-50 active:scale-90"><x-lucide-pencil class="h-4 w-4" /></button>
                                    <button type="button" data-jam-sesi-hapus aria-label="Hapus ${jamEscape(s.nama)}" title="Hapus ${jamEscape(s.nama)}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-red-300 bg-white text-red-500 transition hover:bg-red-50 active:scale-90"><x-lucide-trash-2 class="h-4 w-4" /></button>
                                </div>
                            </td>
                        </tr>
                    `).join('');
                };

                const muatUlangSesi = async () => {
                    try {
                        const respons = await fetch(window.location.href, { headers: { 'Accept': 'text/html' } });
                        if (!respons.ok) return false;
                        const dokumen = new DOMParser().parseFromString(await respons.text(), 'text/html');
                        const baris = Array.from(dokumen.querySelectorAll('[data-sesi-row]'));
                        if (!baris.length) return false;
                        jamData.sesi = baris.map((item) => JSON.parse(item.dataset.sesi));
                        jamRenderSesi();

                        return true;
                    } catch {
                        return false;
                    }
                };

                const jamTutup = () => {
                    if (jamModal.dataset.open !== 'true') return;
                    jamModal.dataset.open = 'false';
                    jamModal.setAttribute('aria-hidden', 'true');
                    const selesai = () => jamModal.setAttribute('inert', '');
                    jamModal.addEventListener('transitionend', selesai, { once: true });
                    setTimeout(selesai, 400);
                };

                const jamBuka = (mode, sesi = null) => {
                    jamMode = mode;
                    jamEditId = sesi?.id ?? null;
                    jamError.classList.add('hidden');
                    jamError.textContent = '';
                    jamSave.disabled = false;
                    jamSaveText.textContent = 'Simpan';
                    jamForm.reset();
                    jamOperasionalFields.classList.toggle('hidden', mode !== 'operasional');
                    jamSesiFields.classList.toggle('hidden', mode !== 'sesi');
                    jamInfo.textContent = 'Rentang jam operasional: '
                        + jamData.konfigurasi.jam_masuk + '–' + jamData.konfigurasi.jam_selesai + '.';

                    if (mode === 'operasional') {
                        jamTitle.textContent = 'Atur Jam Operasional';
                        jamSubtitle.textContent = 'Rentang ini membatasi seluruh sesi jam belajar.';
                        jamField.masuk.value = jamData.konfigurasi.jam_masuk;
                        jamField.selesai.value = jamData.konfigurasi.jam_selesai;
                        jamField.keterangan.value = jamData.konfigurasi.keterangan || '';
                        jamField.hariAktif.forEach((opsi) => {
                            opsi.checked = jamData.konfigurasi.hari_aktif.includes(opsi.value);
                        });

                        const jadwalPerHari = jamData.konfigurasi.jadwal_per_hari || {};
                        const jumlahTerdampak = jamData.konfigurasi.hari_aktif
                            .filter((hari) => !jamField.hariAktif.some((opsi) => opsi.value === hari && opsi.checked))
                            .reduce((total, hari) => total + Number(jadwalPerHari[hari] || 0), 0);
                        if (jumlahTerdampak > 0) {
                            jamInfo.textContent += ' ' + jumlahTerdampak + ' jadwal pada hari yang dinonaktifkan tidak akan dihapus.';
                        }
                    } else {
                        jamTitle.textContent = sesi ? 'Ubah Sesi Jam Belajar' : 'Tambah Sesi Jam Belajar';
                        jamSubtitle.textContent = sesi ? 'Perbarui sesi yang sudah terdaftar.' : 'Buat sesi baru di dalam jam operasional.';
                        const urutanBerikutnya = jamData.sesi.reduce((terbesar, item) => Math.max(terbesar, Number(item.urutan) || 0), 0) + 1;
                        jamField.nama.value = sesi?.nama || '';
                        jamField.jenis.value = sesi?.jenis || 'pelajaran';
                        jamField.urutan.value = sesi?.urutan || urutanBerikutnya;
                        jamField.mulai.value = sesi?.jam_mulai || '';
                        jamField.selesaiSesi.value = sesi?.jam_selesai || '';
                        jamHitungUrutan();

                        if ((sesi?.jumlah_jadwal || 0) > 0) {
                            jamInfo.textContent += ' Sesi ini dipakai ' + sesi.jumlah_jadwal + ' jadwal; perubahan akan diterapkan ke jadwal terkait.';
                        }
                    }

                    jamModal.removeAttribute('inert');
                    jamModal.setAttribute('aria-hidden', 'false');
                    requestAnimationFrame(() => { jamModal.dataset.open = 'true'; });
                    setTimeout(() => {
                        const fokus = mode === 'operasional' ? jamField.masuk : jamField.nama;
                        fokus.focus();
                    }, 30);
                };

                document.getElementById('jam-operasional-edit').addEventListener('click', () => jamBuka('operasional'));
                document.getElementById('jam-sesi-add').addEventListener('click', () => jamBuka('sesi'));
                jamModal.querySelectorAll('[data-jam-close]').forEach((b) => b.addEventListener('click', jamTutup));
                jamModal.querySelector('[data-jam-backdrop]').addEventListener('click', jamTutup);
                jamField.mulai.addEventListener('input', jamHitungUrutan);
                jamField.mulai.addEventListener('change', jamHitungUrutan);

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && jamModal.dataset.open === 'true') jamTutup();
                });

                jamBody.addEventListener('click', async (e) => {
                    const baris = e.target.closest('[data-sesi-row]');
                    if (!baris) return;

                    let sesi;
                    try {
                        sesi = JSON.parse(baris.dataset.sesi);
                    } catch {
                        return;
                    }

                    if (e.target.closest('[data-jam-sesi-edit]')) {
                        jamBuka('sesi', sesi);

                        return;
                    }

                    const tombolHapus = e.target.closest('[data-jam-sesi-hapus]');
                    if (!tombolHapus) return;

                    const jumlahJadwal = Number(sesi.jumlah_jadwal || 0);
                    if (jumlahJadwal > 0) {
                        const teks = 'Sesi ' + sesi.nama + ' masih terhubung dengan ' + jumlahJadwal
                            + ' jadwal. Sesuaikan atau hapus jadwal terkait terlebih dahulu sebelum menghapus sesi.';
                        if (window.Swal) {
                            await Swal.fire({ icon: 'warning', title: 'Sesi masih digunakan', text: teks, confirmButtonText: 'Mengerti' });
                        } else {
                            window.alert(teks);
                        }

                        return;
                    }

                    const setuju = window.Swal
                        ? (await Swal.fire({
                            icon: 'warning',
                            title: 'Hapus sesi ini?',
                            text: 'Sesi ' + sesi.nama + ' belum dipakai jadwal. Tindakan ini tidak dapat dibatalkan.',
                            showCancelButton: true,
                            confirmButtonText: 'Ya, hapus',
                            cancelButtonText: 'Batal',
                        })).isConfirmed
                        : window.confirm('Hapus sesi ' + sesi.nama + '?');

                    if (!setuju) return;

                    tombolHapus.disabled = true;
                    tombolHapus.classList.add('opacity-50', 'cursor-not-allowed');

                    try {
                        const respons = await jsonRequest(jamSesiUrlFor(sesi.id), 'DELETE');
                        const hasil = await respons.json().catch(() => ({}));
                        if (!respons.ok) throw new Error(jamPesanGalat(hasil, respons.status));
                        if (Array.isArray(hasil.data?.daftar_sesi)) {
                            jamData.sesi = hasil.data.daftar_sesi;
                        } else {
                            jamData.sesi = jamData.sesi.filter((item) => String(item.id) !== String(sesi.id));
                            jamData.sesi.forEach((item, indeks) => { item.urutan = indeks + 1; });
                        }
                        jamRenderSesi();
                        toast(hasil.message || 'Sesi berhasil dihapus.');
                    } catch (galat) {
                        alertError('Gagal Menghapus Sesi', galat.message);
                    } finally {
                        tombolHapus.disabled = false;
                        tombolHapus.classList.remove('opacity-50', 'cursor-not-allowed');
                    }
                });

                jamForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    jamError.classList.add('hidden');

                    if (jamMode === 'operasional' && ! jamField.hariAktif.some((opsi) => opsi.checked)) {
                        jamError.textContent = 'Pilih minimal satu hari belajar aktif.';
                        jamError.classList.remove('hidden');

                        return;
                    }

                    let konfirmasiHari = false;
                    let konfirmasiPerubahan = false;

                    if (jamMode === 'operasional') {
                        const dipilih = jamField.hariAktif.filter((opsi) => opsi.checked).map((opsi) => opsi.value);
                        const jadwalPerHari = jamData.konfigurasi.jadwal_per_hari || {};
                        const terdampak = jamData.konfigurasi.hari_aktif
                            .filter((hari) => !dipilih.includes(hari))
                            .reduce((total, hari) => total + Number(jadwalPerHari[hari] || 0), 0);

                        if (terdampak > 0) {
                            const setuju = window.Swal
                                ? (await Swal.fire({
                                    icon: 'warning',
                                    title: 'Nonaktifkan hari belajar?',
                                    text: 'Ada ' + terdampak + ' jadwal pada hari yang dinonaktifkan. Jadwal tidak akan dihapus, tetapi tidak tampil pada jadwal Guru dan Siswa.',
                                    showCancelButton: true,
                                    confirmButtonText: 'Ya, lanjutkan',
                                    cancelButtonText: 'Batal',
                                })).isConfirmed
                                : window.confirm('Ada ' + terdampak + ' jadwal pada hari yang dinonaktifkan. Lanjutkan?');

                            if (!setuju) return;
                            konfirmasiHari = true;
                        }
                    } else if (jamEditId !== null) {
                        const lama = jamData.sesi.find((item) => String(item.id) === String(jamEditId));
                        const berubah = lama && (
                            lama.jam_mulai !== jamField.mulai.value
                            || lama.jam_selesai !== jamField.selesaiSesi.value
                            || lama.jenis !== jamField.jenis.value
                        );

                        if (berubah && Number(lama.jumlah_jadwal || 0) > 0) {
                            const setuju = window.Swal
                                ? (await Swal.fire({
                                    icon: 'warning',
                                    title: 'Terapkan ke jadwal terkait?',
                                    text: 'Sesi ini dipakai ' + lama.jumlah_jadwal + ' jadwal. Perubahan waktu atau jenis akan diterapkan ke seluruh jadwal tersebut.',
                                    showCancelButton: true,
                                    confirmButtonText: 'Ya, terapkan',
                                    cancelButtonText: 'Batal',
                                })).isConfirmed
                                : window.confirm('Terapkan perubahan ke ' + lama.jumlah_jadwal + ' jadwal terkait?');

                            if (!setuju) return;
                            konfirmasiPerubahan = true;
                        }
                    }

                    if (jamMode === 'sesi') jamHitungUrutan();

                    jamSave.disabled = true;
                    jamSaveText.textContent = 'Menyimpan...';

                    const payload = jamMode === 'operasional'
                        ? {
                            jam_masuk: jamField.masuk.value,
                            jam_selesai: jamField.selesai.value,
                            keterangan: jamField.keterangan.value,
                            hari_aktif: jamField.hariAktif
                                .filter((opsi) => opsi.checked)
                                .map((opsi) => opsi.value),
                            konfirmasi_hari_nonaktif: konfirmasiHari,
                        }
                        : {
                            nama: jamField.nama.value,
                            jenis: jamField.jenis.value,
                            urutan: jamField.urutan.value,
                            jam_mulai: jamField.mulai.value,
                            jam_selesai: jamField.selesaiSesi.value,
                            konfirmasi_perubahan: konfirmasiPerubahan,
                        };
                    const url = jamMode === 'operasional'
                        ? jamOperasionalUrl
                        : (jamEditId === null ? jamSesiUrl : jamSesiUrlFor(jamEditId));
                    const method = jamMode === 'operasional' ? 'PUT' : (jamEditId === null ? 'POST' : 'PUT');

                    try {
                        const respons = await jsonRequest(url, method, payload);
                        const hasil = await respons.json().catch(() => ({}));
                        if (!respons.ok) throw new Error(jamPesanGalat(hasil, respons.status));

                        if (jamMode === 'operasional') {
                            Object.assign(jamData.konfigurasi, hasil.data.konfigurasi);
                            root.querySelector('[data-jam-masuk]').textContent = jamData.konfigurasi.jam_masuk;
                            root.querySelector('[data-jam-selesai]').textContent = jamData.konfigurasi.jam_selesai;
                            root.querySelector('[data-jam-rentang]').textContent = 'Berlaku untuk seluruh sesi jam belajar.';
                            root.querySelector('[data-jam-keterangan]').textContent = jamData.konfigurasi.keterangan || 'Belum ada keterangan tambahan.';
                            root.querySelector('[data-jam-hari-aktif]').textContent = 'Hari belajar: '
                                + jamData.konfigurasi.hari_aktif.join(', ');
                        } else if (Array.isArray(hasil.data?.daftar_sesi)) {
                            jamData.sesi = hasil.data.daftar_sesi;
                            jamRenderSesi();
                        } else if (! await muatUlangSesi()) {
                            const indeks = jamData.sesi.findIndex((item) => String(item.id) === String(hasil.data.sesi.id));
                            if (indeks >= 0) {
                                jamData.sesi[indeks] = hasil.data.sesi;
                            } else {
                                jamData.sesi.push(hasil.data.sesi);
                            }
                            jamData.sesi.sort((a, b) => Number(a.urutan) - Number(b.urutan));
                            jamRenderSesi();
                        }

                        jamTutup();
                        toast(hasil.message || 'Pengaturan berhasil disimpan.');
                    } catch (galat) {
                        jamError.textContent = galat.message;
                        jamError.classList.remove('hidden');
                    } finally {
                        jamSave.disabled = false;
                        jamSaveText.textContent = 'Simpan';
                    }
                });
            })();
        </script>
    </div>
@endsection
