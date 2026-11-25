@extends('layouts.dashboard')

@section('title', 'Input Nilai')
@section('subtitle', 'Dashboard Guru')

@section('content')
    <x-dashboard.animations />

    <div id="input-nilai-page" data-dashboard>

        @if (count($inputNilaiData['mapel']) === 0)
            {{-- Guru belum punya mata pelajaran, jadi tidak ada yang bisa dinilai. --}}
                <div class="rounded-2xl border border-amber-200/70 bg-amber-50/60 p-5 shadow-sm sm:p-6" data-reveal>
                <div class="flex items-start gap-3.5">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                        <x-lucide-alert-circle class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-amber-900">Mata Pelajaran Belum Ditugaskan</h3>
                        <p class="mt-1 max-w-2xl text-sm leading-relaxed text-amber-700">
                            Akun Anda belum memiliki mata pelajaran yang ditetapkan administrator sekolah.
                            Input nilai hanya terbuka untuk mata pelajaran yang Anda ampu, supaya nilai tidak
                            tercatat atas nama guru yang salah. Hubungi administrator untuk penugasan mata pelajaran.
                        </p>
                        <p class="mt-4 text-xs font-medium text-amber-700">
                            Hubungi administrator untuk penugasan mata pelajaran.
                        </p>
                    </div>
                </div>
            </div>
        @elseif (count($inputNilaiData['siswa']) === 0)
            {{-- Ada mapel tetapi belum ada siswa aktif dengan kelas. --}}
            <div class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-white/70 px-6 py-16 text-center" data-reveal>
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-10 text-primary-70">
                    <x-lucide-users class="h-7 w-7" />
                </span>
                <h3 class="mt-4 text-base font-semibold text-slate-900">Belum Ada Siswa Aktif</h3>
                <p class="mt-1.5 max-w-md text-sm leading-relaxed text-slate-500">
                    Belum ada siswa aktif yang memiliki kelas, sehingga tidak ada yang bisa dinilai.
                    Data nilai akan muncul setelah administrator menambahkan siswa ke kelasnya.
                </p>
            </div>
        @else
            @php
                $mapelAwal = $inputNilaiData['mapel'][0] ?? null;
            @endphp

            {{-- Ringkasan penilaian, diperbarui mengikuti kelas yang dipilih. --}}
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-4">
                @php
                    $kartu = [
                        ['key' => 'total', 'label' => 'Siswa di Kelas', 'icon' => 'lucide-users', 'tone' => 'bg-blue-100 text-primary-60', 'accent' => '#1E86FF'],
                        ['key' => 'sudah', 'label' => 'Sudah Dinilai', 'icon' => 'lucide-circle-check-big', 'tone' => 'bg-emerald-100 text-emerald-600', 'accent' => '#059669'],
                        ['key' => 'belum', 'label' => 'Belum Dinilai', 'icon' => 'lucide-hourglass', 'tone' => 'bg-amber-100 text-amber-600', 'accent' => '#D97706'],
                        ['key' => 'rata', 'label' => 'Rata-Rata', 'icon' => 'lucide-chart-no-axes-column', 'tone' => 'bg-indigo-100 text-indigo-600', 'accent' => '#4F46E5'],
                    ];
                @endphp

                @foreach ($kartu as $item)
                    <div>
                        <x-ui.stat-card
                            :label="$item['label']"
                            :value="0"
                            :icon="$item['icon']"
                            :tone="$item['tone']"
                            :accent="$item['accent']"
                            :stat-key="$item['key']"
                        />
                    </div>
                @endforeach
            </div>

            {{-- Kartu utama: pemilihan dan tabel nilai. --}}
            <form id="nilai-form" class="mt-5 rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60 sm:mt-6" data-reveal autocomplete="off">
                @csrf

                <div class="rounded-t-2xl bg-slate-50 p-4 sm:p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-60 text-white shadow-md shadow-blue-500/20">
                                <x-lucide-square-pen class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <h3 id="nilai-selected-title" class="text-sm font-bold text-slate-900 sm:text-base">Pilih kelas untuk mulai mengisi nilai</h3>
                                <p id="nilai-scope-info" class="mt-0.5 text-xs text-slate-500 sm:text-sm">Nilai akan tersimpan sesuai periode yang dipilih.</p>
                            </div>
                        </div>

                        <button type="submit" id="nilai-save"
                            class="inline-flex h-10 w-full shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-primary-60 px-4 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-primary-70 active:scale-[.98] disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">
                            <x-lucide-save class="h-4 w-4" />
                            <span>Simpan Nilai</span>
                        </button>
                    </div>

                    {{-- Filter utama --}}
                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <label class="block">
                            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Mata Pelajaran</span>
                            <div class="relative">
                                <input id="nilai-mapel-tampil" type="text" readonly aria-readonly="true" disabled tabindex="-1"
                                    value="{{ $mapelAwal ? (($mapelAwal['kode'] ? $mapelAwal['kode'].' · ' : '').$mapelAwal['nama']) : 'Belum ada mata pelajaran yang ditugaskan' }}"
                                    class="h-10 w-full cursor-not-allowed select-none rounded-lg border border-slate-300 bg-slate-50 pl-3 pr-9 text-sm text-slate-500 outline-none transition disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500 disabled:opacity-100 focus:border-slate-300 focus:outline-none focus:ring-0">
                                <x-lucide-lock class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            </div>
                            <input type="hidden" id="nilai-mapel" name="mata_pelajaran_id" value="{{ $mapelAwal['id'] ?? '' }}" data-change-ignore>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Jurusan</span>
                            <div class="relative">
                                <select id="nilai-jurusan"
                                    data-change-ignore
                                    class="h-10 w-full appearance-none rounded-lg border border-slate-300 bg-white pl-3 pr-9 text-sm text-slate-800 outline-none transition focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20">
                                </select>
                                <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                            </div>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Kelas</span>
                            <div class="relative">
                                <select id="nilai-kelas"
                                    data-change-ignore
                                    class="h-10 w-full appearance-none rounded-lg border border-slate-300 bg-white pl-3 pr-9 text-sm text-slate-800 outline-none transition focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20">
                                </select>
                                <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                            </div>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Semester</span>
                            <div class="relative">
                                <select id="nilai-semester"
                                    data-change-ignore
                                    class="h-10 w-full appearance-none rounded-lg border border-slate-300 bg-white pl-3 pr-9 text-sm text-slate-800 outline-none transition focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20">
                                </select>
                                <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                            </div>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Tahun Ajaran</span>
                            <div class="relative">
                                <select id="nilai-tahun"
                                    data-change-ignore
                                    class="h-10 w-full appearance-none rounded-lg border border-slate-300 bg-white pl-3 pr-9 text-sm text-slate-800 outline-none transition focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20">
                                </select>
                                <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                            </div>
                        </label>
                    </div>

                    {{-- Bantuan KKM massal --}}
                    <div class="mt-4 flex flex-col gap-3 border-t border-slate-200/80 pt-4 sm:flex-row sm:items-end sm:justify-between">
                        <div class="flex items-end gap-2">
                            <label class="block">
                                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Isi KKM Massal</span>
                                <input type="number" id="nilai-kkm-mass" min="0" max="100" step="0.01" inputmode="decimal"
                                    data-change-ignore
                                    placeholder="Contoh: 75"
                                    class="h-10 w-32 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20">
                            </label>
                        <button type="button" id="nilai-kkm-apply"
                            class="inline-flex h-10 w-full items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 text-xs font-medium text-slate-600 transition hover:border-primary-60 hover:bg-blue-50 hover:text-primary-70 active:scale-[.98] sm:w-auto">
                            <x-lucide-wand-sparkles class="h-4 w-4" />
                            Terapkan ke Semua
                        </button>
                        </div>

                        <p class="text-[11px] text-slate-400">
                            Rentang nilai {{ (int) $inputNilaiData['batas']['min'] }}–{{ (int) $inputNilaiData['batas']['maks'] }}.
                            Kosongkan nilai untuk menghapus catatan.
                        </p>
                    </div>
                </div>

                {{-- Pesan galat di dalam kartu, supaya tetap terlihat saat popup ditutup. --}}
                <div id="nilai-error" data-change-error class="mx-4 mt-4 hidden items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-3.5 py-3 text-sm text-red-700 sm:mx-5">
                    <x-lucide-triangle-alert class="mt-0.5 h-4 w-4 shrink-0" />
                    <span data-error-text></span>
                </div>

                {{-- Tabel nilai --}}
                <div id="nilai-table-wrap" class="hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px] border-collapse text-left text-sm">
                            <thead>
                                <tr class="border-y border-slate-100 bg-white text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                    <th class="w-12 px-4 py-3 text-center">No</th>
                                    <th class="px-3 py-3">NIS</th>
                                    <th class="px-3 py-3">Nama Siswa</th>
                                    <th class="px-3 py-3">Kelas</th>
                                    <th class="w-28 px-3 py-3 text-center">Nilai</th>
                                    <th class="w-28 px-3 py-3 text-center">KKM</th>
                                    <th class="w-36 px-4 py-3">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody id="nilai-tbody" class="divide-y divide-slate-100"></tbody>
                        </table>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/60 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <p class="text-xs text-slate-500">
                            Perubahan belum tersimpan sampai Anda menekan <strong class="text-slate-700">Simpan Nilai</strong>.
                        </p>
                        <button type="submit" id="nilai-save-bottom"
                            class="inline-flex h-10 items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-primary-60 px-5 text-sm font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-primary-70 active:scale-[.98] disabled:cursor-not-allowed disabled:opacity-60">
                            <x-lucide-save class="h-4 w-4" />
                            <span>Simpan Nilai</span>
                        </button>
                    </div>
                </div>

                {{-- Keadaan kosong saat belum ada kelas dipilih. --}}
                <div id="nilai-empty" class="flex flex-col items-center justify-center px-6 py-16 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-10 text-primary-70">
                        <x-lucide-clipboard-list class="h-7 w-7" />
                    </span>
                    <h3 id="nilai-empty-title" class="mt-4 text-base font-semibold text-slate-900">Pilih Kelas Terlebih Dahulu</h3>
                    <p id="nilai-empty-text" class="mt-1.5 max-w-md text-sm leading-relaxed text-slate-500">
                        Pilih jurusan dan kelas di atas untuk menampilkan daftar siswa beserta kolom nilainya.
                    </p>
                </div>
            </form>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        #nilai-kkm-mass::-webkit-outer-spin-button,
        #nilai-kkm-mass::-webkit-inner-spin-button,
        .nilai-input::-webkit-outer-spin-button,
        .nilai-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        #nilai-kkm-mass,
        .nilai-input {
            -moz-appearance: textfield;
        }

        .nilai-input {
            transition: border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
        }

        .nilai-input.is-invalid {
            border-color: #ef4444;
            background-color: #fef2f2;
        }

        .nilai-input.is-invalid:focus {
            box-shadow: 0 0 0 3px rgba(239, 68, 68, .18);
        }

        .nilai-baris-baru {
            animation: nilaiBarisMasuk .35s ease both;
        }

        @keyframes nilaiBarisMasuk {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            .nilai-baris-baru {
                transition: none;
                animation: none;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        (() => {
            const data = @json($inputNilaiData);
            const storeUrl = @json(route('dashboard.guru.input-nilai.store'));

            const root = document.getElementById('input-nilai-page');
            if (!root || !data.mapel || data.mapel.length === 0) return;

            const $ = (sel) => root.querySelector(sel);

            const form = $('#nilai-form');
            const csrf = form.querySelector('input[name="_token"]')?.value
                || document.querySelector('meta[name="csrf-token"]')?.content
                || '';

            const mapelEl = $('#nilai-mapel');
            const mapelTampilEl = $('#nilai-mapel-tampil');
            const jurusanEl = $('#nilai-jurusan');
            const kelasEl = $('#nilai-kelas');
            const semesterEl = $('#nilai-semester');
            const tahunEl = $('#nilai-tahun');
            const tbody = $('#nilai-tbody');
            const tableWrap = $('#nilai-table-wrap');
            const emptyEl = $('#nilai-empty');
            const emptyTitle = $('#nilai-empty-title');
            const emptyText = $('#nilai-empty-text');
            const errorEl = $('#nilai-error');
            const errorText = errorEl.querySelector('[data-error-text]');
            const scopeInfo = $('#nilai-scope-info');
            const selectedTitle = $('#nilai-selected-title');
            const kkmMassEl = $('#nilai-kkm-mass');
            const kkmApplyBtn = $('#nilai-kkm-apply');
            const saveButtons = [$('#nilai-save'), $('#nilai-save-bottom')].filter(Boolean);

            const batas = data.batas || { min: 0, maks: 100 };
            const kkmBawaan = data.kkm_bawaan ?? 75;

            const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            }[c]));

            const kunci = (mapelId, userId, tahun, semester) => `${mapelId}|${userId}|${tahun}|${semester}`;

            // Indeks nilai yang sudah tersimpan, supaya berpindah kelas/periode
            // tidak perlu memuat ulang halaman.
            const nilaiIndex = new Map();
            (data.nilai || []).forEach((n) => {
                nilaiIndex.set(kunci(n.mata_pelajaran_id, n.user_id, n.tahun_ajaran, n.semester), n);
            });

            const mapelTerpilihId = data.mapel_terpilih_id ?? (data.mapel[0] ? data.mapel[0].id : null);
            const state = {
                mapelId: mapelTerpilihId,
                jurusan: '',
                kelas: '',
                semester: data.periode.semester,
                tahun: data.periode.tahun_ajaran,
            };

            const siswaAktif = (data.siswa || []).map((s) => ({ ...s }));

            // ===== Isi select =====
            const fillSelect = (sel, items, placeholder) => {
                sel.innerHTML = '';
                sel.add(new Option(placeholder, ''));

                items.forEach((i) => {
                    const option = new Option(i.teks, i.nilai);
                    if (i.data) {
                        Object.entries(i.data).forEach(([k, v]) => option.dataset[k] = v);
                    }
                    sel.add(option);
                });
            };

            const renderMapel = () => {
                const aktif = (data.mapel || []).find((m) => String(m.id) === String(state.mapelId));

                mapelEl.value = state.mapelId !== null ? String(state.mapelId) : '';
                mapelTampilEl.value = aktif
                    ? (aktif.kode ? `${aktif.kode} · ${aktif.nama}` : aktif.nama)
                    : 'Belum ada mata pelajaran yang ditugaskan';
            };

            const renderSemester = () => {
                fillSelect(semesterEl, (data.semesters || []).map((s) => ({
                    nilai: s,
                    teks: `Semester ${s}`,
                })), 'Pilih Semester');

                semesterEl.value = state.semester;
            };

            const renderTahun = () => {
                fillSelect(tahunEl, (data.tahun_ajarans || []).map((t) => ({
                    nilai: t,
                    teks: `T.A. ${t}`,
                })), 'Pilih Tahun Ajaran');

                tahunEl.value = state.tahun;
            };

            const renderJurusan = () => {
                fillSelect(jurusanEl, (data.jurusan || []).map((j) => ({
                    nilai: j,
                    teks: j,
                })), 'Semua Jurusan');

                jurusanEl.value = state.jurusan;
            };

            const kelasTersedia = () => {
                const daftar = data.kelas_semua || [];
                return daftar.filter((k) => !state.jurusan || k.jurusan === state.jurusan);
            };

            const renderKelas = () => {
                const items = kelasTersedia().map((k) => {
                    const jumlah = siswaAktif.filter((s) => s.kelas_normal === k.kelas && s.jurusan === k.jurusan).length;
                    const label = state.jurusan ? `${k.kelas} (${jumlah} siswa)` : `${k.jurusan} · ${k.kelas} (${jumlah} siswa)`;

                    return {
                        nilai: `${k.jurusan}||${k.kelas}`,
                        teks: label,
                        data: {
                            jurusan: k.jurusan,
                            kelas: k.kelas,
                            mapel: k.mata_pelajaran_id,
                            mapelNama: k.mata_pelajaran_nama,
                        },
                    };
                });

                fillSelect(kelasEl, items, 'Pilih Kelas');

                const cocok = items.find((i) => i.data.kelas === state.kelas && i.data.jurusan === state.jurusan);
                if (cocok) kelasEl.value = cocok.nilai;
            };

            // ===== Tampilkan galat =====
            const showError = (msg) => {
                errorText.textContent = msg || '';
                errorEl.classList.toggle('hidden', !msg);
                errorEl.classList.toggle('flex', !!msg);
            };

            const showLoading = (text) => {
                const loading = document.getElementById('site-loading');
                if (!loading) return null;
                const t = loading.querySelector('[data-loading-text]');
                if (t) t.textContent = text;
                loading.removeAttribute('inert');
                loading.setAttribute('aria-hidden', 'false');
                requestAnimationFrame(() => { loading.dataset.open = 'true'; });
                return loading;
            };

            const hideLoading = (loading) => {
                if (!loading) return;
                loading.dataset.open = 'false';
                loading.setAttribute('aria-hidden', 'true');
                const finish = () => loading.setAttribute('inert', '');
                loading.addEventListener('transitionend', finish, { once: true });
                setTimeout(finish, 400);
            };

            const toast = (text) => {
                if (!window.Swal) return;
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text,
                    showConfirmButton: false,
                    timer: 1800,
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

            // ===== Baris siswa =====
            const siswaTerpilih = () => siswaAktif.filter((s) => s.kelas_normal === state.kelas && s.jurusan === state.jurusan);

            const nilaiTersimpan = (userId) => nilaiIndex.get(kunci(state.mapelId, userId, state.tahun, state.semester)) || null;

            const kelasLabel = (kelas) => (kelas.jurusan ? `${kelas.kelas} · ${kelas.jurusan}` : kelas.kelas);

            const statusDari = (nilai, kkm) => {
                if (nilai === '' || nilai === null || nilai === undefined) {
                    return { teks: 'Belum dinilai', kelas: 'bg-slate-100 text-slate-500' };
                }

                const n = Number(nilai);
                const k = (kkm === '' || kkm === null || kkm === undefined) ? kkmBawaan : Number(kkm);

                if (!Number.isFinite(n)) return { teks: 'Belum dinilai', kelas: 'bg-slate-100 text-slate-500' };

                return Number.isFinite(k) && n >= k
                    ? { teks: 'Tuntas', kelas: 'bg-emerald-100 text-emerald-700' }
                    : { teks: 'Belum tuntas', kelas: 'bg-amber-100 text-amber-700' };
            };

            const inputNilai = (userId, field, value, extra) => `
                <input type="number" inputmode="decimal" min="${batas.min}" max="${batas.maks}" step="0.01"
                    data-field="${field}" data-user="${userId}"
                    class="nilai-input h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-center text-sm tabular-nums text-slate-800 outline-none transition focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20"
                    value="${value}" ${extra || ''}>`;

            const render = () => {
                showError('');

                if (state.kelas === '' || state.jurusan === '') {
                    tableWrap.classList.add('hidden');
                    emptyEl.classList.remove('hidden');
                    emptyTitle.textContent = 'Pilih Kelas Terlebih Dahulu';
                    emptyText.textContent = 'Pilih jurusan dan kelas di atas untuk menampilkan daftar siswa beserta kolom nilainya.';
                    selectedTitle.textContent = 'Pilih kelas untuk mulai mengisi nilai';
                    scopeInfo.textContent = 'Nilai akan tersimpan sesuai periode yang dipilih.';
                    perbaruiStatistik([]);
                    refreshPerubahan();
                    return;
                }

                const murid = siswaTerpilih();

                if (murid.length === 0) {
                    tableWrap.classList.add('hidden');
                    emptyEl.classList.remove('hidden');
                    emptyTitle.textContent = 'Tidak Ada Siswa Aktif';
                    emptyText.textContent = 'Tidak ada siswa aktif pada kelas yang dipilih.';
                    selectedTitle.textContent = 'Tidak ada siswa pada kelas ini';
                    scopeInfo.textContent = 'Coba pilih kelas lain.';
                    perbaruiStatistik([]);
                    refreshPerubahan();
                    return;
                }

                emptyEl.classList.add('hidden');
                tableWrap.classList.remove('hidden');
                selectedTitle.textContent = `${kelasLabel({ kelas: state.kelas, jurusan: state.jurusan })} · ${data.mapel.find((m) => String(m.id) === String(state.mapelId))?.nama ?? ''}`;
                scopeInfo.textContent = `Semester ${state.semester} · T.A. ${state.tahun} · ${murid.length} siswa`;

                tbody.innerHTML = murid.map((s, i) => {
                    const tersimpan = nilaiTersimpan(s.id);
                    const nilai = tersimpan ? String(tersimpan.nilai) : '';
                    const kkm = tersimpan ? String(tersimpan.kkm) : String(kkmBawaan);
                    const status = statusDari(nilai, kkm);

                    return `
                        <tr class="nilai-baris-baru transition hover:bg-slate-50/70" data-user="${s.id}">
                            <td class="px-4 py-2.5 text-center text-slate-400">${i + 1}</td>
                            <td class="px-3 py-2.5 font-mono text-xs text-slate-500">${esc(s.nis ?? '-')}</td>
                            <td class="px-3 py-2.5 font-medium text-slate-800">
                                <p class="max-w-[140px] truncate sm:max-w-[200px]">${esc(s.nama)}</p>
                            </td>
                            <td class="px-3 py-2.5 text-slate-600">${esc(kelasLabel({ kelas: s.kelas_normal, jurusan: s.jurusan }))}</td>
                            <td class="px-3 py-2.5">
                                ${inputNilai(s.id, 'nilai', esc(nilai), `placeholder="0"`)}
                            </td>
                            <td class="px-3 py-2.5">
                                ${inputNilai(s.id, 'kkm', esc(kkm), `placeholder="${kkmBawaan}"`)}
                            </td>
                            <td class="px-4 py-2.5">
                                <span data-status class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold ${status.kelas}">${status.teks}</span>
                            </td>
                        </tr>`;
                }).join('');

                perbaruiStatistik(murid);
                refreshPerubahan();
            };

            const barisDOM = () => Array.from(tbody.querySelectorAll('tr[data-user]'));

            const bacaBaris = (tr) => {
                const nilaiEl = tr.querySelector('[data-field="nilai"]');
                const kkmEl = tr.querySelector('[data-field="kkm"]');

                return {
                    userId: Number(tr.dataset.user),
                    nilai: nilaiEl.value.trim(),
                    kkm: kkmEl.value.trim(),
                    nilaiEl,
                    kkmEl,
                };
            };

            const sumberPerubahanNilai = () => barisDOM().map((tr) => {
                const b = bacaBaris(tr);
                const tersimpan = nilaiIndex.get(kunci(state.mapelId, b.userId, state.tahun, state.semester));
                const nilaiAwal = tersimpan ? String(tersimpan.nilai) : '';
                const kkmAwal = tersimpan ? String(tersimpan.kkm) : String(kkmBawaan);
                if (b.nilai === nilaiAwal && (b.nilai === '' || b.kkm === kkmAwal)) return '';
                return `${b.userId}:${b.nilai}:${b.kkm}`;
            }).filter(Boolean).join('|');

            const refreshPerubahan = () => window.saskansaChange?.refresh?.(form);

            const perbaruiStatusBaris = (tr) => {
                const { nilai, kkm } = bacaBaris(tr);
                const badge = tr.querySelector('[data-status]');
                const status = statusDari(nilai, kkm);
                badge.textContent = status.teks;
                badge.className = `inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold ${status.kelas}`;
            };

            const perbaruiStatistik = (murid) => {
                const total = murid.length;
                let sudah = 0;
                let jumlah = 0;

                barisDOM().forEach((tr) => {
                    const nilai = tr.querySelector('[data-field="nilai"]').value.trim();
                    if (nilai !== '') {
                        const n = Number(nilai);
                        if (Number.isFinite(n)) {
                            sudah++;
                            jumlah += n;
                        }
                    }
                });

                const set = (key, value) => {
                    const el = root.querySelector(`[data-stat="${key}"]`);
                    if (el) el.textContent = value;
                };

                set('total', total);
                set('sudah', sudah);
                set('belum', Math.max(total - sudah, 0));
                set('rata', sudah > 0 ? (Math.round((jumlah / sudah) * 10) / 10).toFixed(1) : '—');
            };

            // ===== Event pilihan =====
            jurusanEl.addEventListener('change', () => {
                state.jurusan = jurusanEl.value;
                state.kelas = '';
                renderKelas();
                render();
            });

            kelasEl.addEventListener('change', () => {
                const option = kelasEl.options[kelasEl.selectedIndex];
                state.kelas = option?.dataset.kelas || '';
                state.jurusan = option?.dataset.jurusan || '';

                if (option?.dataset.mapel) {
                    state.mapelId = Number(option.dataset.mapel);
                }

                renderMapel();
                jurusanEl.value = state.jurusan;
                render();
            });

            semesterEl.addEventListener('change', () => {
                state.semester = semesterEl.value;
                render();
            });

            tahunEl.addEventListener('change', () => {
                state.tahun = tahunEl.value;
                render();
            });

            tbody.addEventListener('input', (e) => {
                const tr = e.target.closest('tr[data-user]');
                if (!tr) return;
                e.target.classList.toggle('is-invalid', !nilaiValid(e.target.value.trim()));
                perbaruiStatusBaris(tr);
                perbaruiStatistik(siswaTerpilih());
            });

            const nilaiValid = (value) => {
                if (value === '') return true;
                const n = Number(value);
                return Number.isFinite(n) && n >= batas.min && n <= batas.maks;
            };

            kkmApplyBtn.addEventListener('click', () => {
                const value = kkmMassEl.value.trim();
                if (!nilaiValid(value) || value === '') {
                    alertError('KKM tidak valid', `Isi KKM dengan angka ${batas.min} sampai ${batas.maks}.`);
                    kkmMassEl.focus();
                    return;
                }

                barisDOM().forEach((tr) => {
                    const input = tr.querySelector('[data-field="kkm"]');
                    input.value = value;
                    input.classList.remove('is-invalid');
                    perbaruiStatusBaris(tr);
                });

                perbaruiStatistik(siswaTerpilih());
                refreshPerubahan();
            });

            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                if (state.kelas === '' || state.jurusan === '' || state.mapelId === null) {
                    alertError('Belum lengkap', 'Pilih mata pelajaran dan kelas terlebih dahulu.');
                    return;
                }

                const baris = barisDOM().map((tr) => bacaBaris(tr));

                if (baris.length === 0) {
                    alertError('Tidak ada siswa', 'Tidak ada siswa pada kelas yang dipilih.');
                    return;
                }

                let adaGalat = false;
                baris.forEach((b) => {
                    const nilaiOk = nilaiValid(b.nilai);
                    const kkmOk = nilaiValid(b.kkm);
                    b.nilaiEl.classList.toggle('is-invalid', !nilaiOk);
                    b.kkmEl.classList.toggle('is-invalid', !kkmOk);
                    if (!nilaiOk || !kkmOk) adaGalat = true;
                });

                if (adaGalat) {
                    showError(`Nilai dan KKM harus berupa angka ${batas.min} sampai ${batas.maks}.`);
                    alertError('Data belum benar', `Periksa kembali nilai dan KKM. Isi hanya angka ${batas.min} sampai ${batas.maks}.`);
                    return;
                }

                const payload = {
                    mata_pelajaran_id: state.mapelId,
                    tahun_ajaran: state.tahun,
                    semester: state.semester,
                    kelas: state.kelas,
                    jurusan: state.jurusan,
                    nilai: baris.map((b) => ({
                        user_id: b.userId,
                        nilai: b.nilai === '' ? null : Number(b.nilai),
                        kkm: b.kkm === '' ? null : Number(b.kkm),
                    })),
                };

                setBusy(true);
                showError('');

                const loading = showLoading('Menyimpan nilai siswa...');

                try {
                    const res = await fetch(storeUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(payload),
                    });

                    const result = await res.json().catch(() => null);

                    if (!res.ok || !result || result.status !== 'success') {
                        const errors = (result && result.errors) || {};
                        const pesan = Object.values(errors).flat()[0]
                            || (result && result.message)
                            || 'Gagal menyimpan nilai. Coba lagi.';

                        showError(pesan);
                        alertError('Gagal menyimpan', pesan);
                        setBusy(false);
                        hideLoading(loading);
                        return;
                    }

                    // Segarkan indeks lokal agar berpindah kelas/periode menampilkan nilai yang baru disimpan.
                    baris.forEach((b) => {
                        const k = kunci(state.mapelId, b.userId, state.tahun, state.semester);
                        if (b.nilai === '') {
                            nilaiIndex.delete(k);
                        } else {
                            const kkm = b.kkm === '' ? kkmBawaan : Number(b.kkm);
                            nilaiIndex.set(k, {
                                mata_pelajaran_id: state.mapelId,
                                user_id: b.userId,
                                tahun_ajaran: state.tahun,
                                semester: state.semester,
                                nilai: Number(b.nilai),
                                kkm,
                            });
                        }
                    });

                    setBusy(false);
                    hideLoading(loading);
                    toast(result.message || 'Nilai berhasil disimpan.');
                } catch (err) {
                    showError('Tidak dapat terhubung ke server. Silakan coba lagi.');
                    alertError('Koneksi gagal', 'Tidak dapat terhubung ke server. Silakan coba lagi.');
                    setBusy(false);
                    hideLoading(loading);
                }
            });

            const setBusy = (busy) => {
                saveButtons.forEach((btn) => {
                    btn.disabled = busy;
                    btn.classList.toggle('opacity-60', busy);
                    const span = btn.querySelector('span');
                    if (span) span.textContent = busy ? 'Menyimpan...' : 'Simpan Nilai';
                });
            };

            // ===== Mulai =====
            renderMapel();
            renderSemester();
            renderTahun();
            renderJurusan();
            renderKelas();
            render();

            const pasangChange = () => window.saskansaChange?.bind(form, {
                save: '#nilai-save, #nilai-save-bottom',
                sources: () => ({ baris: sumberPerubahanNilai() }),
            });
            if (document.readyState === 'loading') window.addEventListener('DOMContentLoaded', pasangChange, { once: true });
            else pasangChange();
        })();
    </script>
@endpush
