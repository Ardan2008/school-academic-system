@extends('layouts.dashboard')

@section('title', 'Laporan')
@section('subtitle', 'Dashboard Guru')

@section('content')
    @php
        use App\Support\Periode;

        $jumlahKolomMapel = $mapelDiampu->count();
        // No, NIS, Nama, Kelas, Jurusan + kolom tiap mapel + rata-rata, nilai tercatat, tuntas, belum tuntas.
        $kolomSiswa = 5 + $jumlahKolomMapel + 4;

        $adaNilai = $ringkasan['jumlah_nilai'] > 0;
        $totalSebaran = collect($sebaran)->sum('jumlah');

        $filterLabel = 'mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-400';
        $filterSelect = 'h-10 w-full appearance-none rounded-lg border border-slate-300 bg-white pl-3 pr-9 text-sm text-slate-800 outline-none transition focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20';

        $kartu = [
            ['label' => 'Rata-Rata Nilai', 'value' => $ringkasan['rata_rata'], 'icon' => 'lucide-chart-no-axes-column', 'tone' => 'bg-blue-100 text-primary-60', 'accent' => '#1E86FF', 'decimals' => 1, 'catatan' => 'dari '.number_format($ringkasan['jumlah_nilai'], 0, ',', '.').' nilai tercatat'],
            ['label' => 'Ketuntasan (KKM)', 'value' => $ringkasan['persen_tuntas'], 'suffix' => '%', 'icon' => 'lucide-badge-check', 'tone' => 'bg-emerald-100 text-emerald-600', 'accent' => '#059669', 'decimals' => 0, 'catatan' => number_format($ringkasan['tuntas'], 0, ',', '.').' tuntas dari '.number_format($ringkasan['jumlah_nilai'], 0, ',', '.').' nilai'],
            ['label' => 'Siswa Dinilai', 'value' => $ringkasan['jumlah_siswa'], 'icon' => 'lucide-users', 'tone' => 'bg-sky-100 text-sky-600', 'accent' => '#0284C7', 'decimals' => 0, 'catatan' => 'punya nilai pada periode ini'],
            ['label' => 'Kelas Tercatat', 'value' => $perKelasSemua->count(), 'icon' => 'lucide-school', 'tone' => 'bg-violet-100 text-violet-600', 'accent' => '#7C3AED', 'decimals' => 0, 'catatan' => 'kelas pada rentang ini'],
        ];

        $sebaranTone = [
            'Sangat Baik' => ['bar' => 'bg-emerald-500', 'badge' => 'bg-emerald-50 text-emerald-600'],
            'Baik' => ['bar' => 'bg-primary-60', 'badge' => 'bg-blue-50 text-blue-600'],
            'Cukup' => ['bar' => 'bg-amber-500', 'badge' => 'bg-amber-50 text-amber-600'],
            'Perlu Perbaikan' => ['bar' => 'bg-rose-500', 'badge' => 'bg-rose-50 text-rose-600'],
        ];
    @endphp

    <div id="laporan-nilai-page" data-dashboard class="space-y-5 sm:space-y-6">
        <x-dashboard.animations />

        @if ($mapelDiampu->isEmpty())
            <div data-reveal style="--d: 0s" class="rounded-2xl border border-amber-200/70 bg-amber-50/60 p-5 shadow-sm sm:p-6">
                <div class="flex items-start gap-3.5">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                        <x-lucide-alert-circle class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-amber-900">Mata Pelajaran Belum Ditugaskan</h3>
                        <p class="mt-1 max-w-2xl text-sm leading-relaxed text-amber-700">
                            Akun Anda belum dihubungkan ke mata pelajaran, jadi belum ada laporan yang bisa disusun.
                            Hubungi administrator untuk penugasan mata pelajaran.
                        </p>
                    </div>
                </div>
            </div>
        @else
            <div data-reveal style="--d: 0s" class="flex flex-wrap items-center gap-2">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Mata Pelajaran Diampu</span>
                @foreach ($mapelDiampu as $mapel)
                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-medium text-primary-70 ring-1 ring-blue-100">
                        <x-lucide-book-open class="h-3.5 w-3.5" />
                        {{ $mapel->nama }}
                    </span>
                @endforeach
            </div>

            {{-- Filter --}}
            <form method="GET" action="{{ route('dashboard.guru.laporan.index') }}"
                  data-reveal style="--d: .05s"
                  class="rounded-2xl border border-slate-100 bg-white p-4 shadow-md shadow-slate-200/60 sm:p-5">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <label class="block">
                        <span class="{{ $filterLabel }}">Tahun Ajaran</span>
                        <div class="relative">
                            <select name="tahun_ajaran" class="{{ $filterSelect }}">
                                @foreach ($tahunAjarans as $tahun)
                                    <option value="{{ $tahun }}" @selected($filter['tahun_ajaran'] === $tahun)>{{ $tahun }}</option>
                                @endforeach
                            </select>
                            <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                        </div>
                    </label>

                    <label class="block">
                        <span class="{{ $filterLabel }}">Semester</span>
                        <div class="relative">
                            <select name="semester" class="{{ $filterSelect }}">
                                @foreach ($semesters as $semester)
                                    <option value="{{ $semester }}" @selected($filter['semester'] === $semester)>{{ $semester }}</option>
                                @endforeach
                            </select>
                            <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                        </div>
                    </label>

                    <label class="block">
                        <span class="{{ $filterLabel }}">Kelas</span>
                        <div class="relative">
                            <select name="kelas" class="{{ $filterSelect }}">
                                <option value="">Semua Kelas</option>
                                @foreach ($kelasOptions as $kelas)
                                    <option value="{{ $kelas['kelas'] }}" @selected($filter['kelas'] === $kelas['kelas'])>
                                        {{ $kelas['kelas'] }}{{ $kelas['jurusan'] ? ' · '.$kelas['jurusan'] : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <x-lucide-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                        </div>
                    </label>

                    <label class="block">
                        <span class="{{ $filterLabel }}">Pencarian</span>
                        <div class="relative">
                            <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input type="search" name="cari" value="{{ $filter['cari'] }}" maxlength="100"
                                placeholder="Nama atau NIS siswa"
                                class="h-10 w-full rounded-lg border border-slate-300 bg-white pl-10 pr-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-60 focus:ring-2 focus:ring-primary-60/20">
                        </div>
                    </label>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                    <p class="text-[11px] text-slate-400">Terapkan filter untuk menyusun laporan sesuai rentang yang dipilih.</p>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('dashboard.guru.laporan.index') }}"
                           class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                            <x-lucide-rotate-ccw class="h-3.5 w-3.5" />
                            Reset
                        </a>
                        <button type="submit"
                                class="inline-flex h-9 items-center gap-2 rounded-lg bg-primary-60 px-4 text-xs font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-primary-70 active:scale-[.98]">
                            <x-lucide-filter class="h-3.5 w-3.5" />
                            Terapkan
                        </button>
                    </div>
                </div>
            </form>

            {{-- Kop laporan: tersembunyi di layar, tampil saat dicetak --}}
            <div class="cetak-kop hidden print:block">
                <div class="flex flex-wrap items-end justify-between gap-2 border-b border-slate-300 pb-2">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Laporan Nilai Siswa</h2>
                        <p class="text-xs text-slate-500">SASKANSA · Sistem Akademik Sekolah</p>
                    </div>
                    <p class="text-xs font-medium text-slate-600">
                        {{ Periode::semesterLabel($filter['semester']) }}
                        {{ Periode::tahunAjaranLabel($filter['tahun_ajaran']) }}
                    </p>
                </div>
            </div>

            @if (! $adaNilai)
                <div data-reveal style="--d: .1s" class="rounded-2xl border border-amber-200/70 bg-amber-50/60 p-5 shadow-sm sm:p-6">
                    <div class="flex items-start gap-3.5">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                            <x-lucide-alert-triangle class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-amber-900">Belum ada nilai pada rentang ini</h3>
                            <p class="mt-1 max-w-2xl text-sm leading-relaxed text-amber-700">
                                Laporan ini dihitung dari tabel nilai, jadi tidak ada angka yang bisa ditampilkan
                                selama belum ada nilai yang tercatat untuk
                                {{ Periode::semesterLabel($filter['semester']) }}
                                {{ $filter['tahun_ajaran'] }}
                                @if ($filter['kelas'] !== ''), kelas {{ $filter['kelas'] }}@endif.
                                Isi nilai lewat menu Input Nilai terlebih dahulu.
                            </p>
                        </div>
                    </div>
                </div>
            @else
                {{-- Aksi laporan: cetak & unduh --}}
                <div data-reveal style="--d: .1s" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-md shadow-slate-200/60 sm:p-5">
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-slate-900">Ringkasan Laporan</h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ Periode::label($filter['tahun_ajaran'], $filter['semester']) }}
                            @if ($filter['kelas'] !== '') · Kelas {{ $filter['kelas'] }}@endif
                            @if ($filter['cari'] !== '') · Pencarian "{{ $filter['cari'] }}"@endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" data-laporan-cetak
                                class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
                            <x-lucide-printer class="h-3.5 w-3.5" />
                            Cetak
                        </button>
                        <button type="button" id="laporan-export" data-laporan-export
                                class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary-60 px-4 text-xs font-semibold text-white shadow-sm shadow-blue-500/20 transition hover:bg-primary-70 active:scale-[.98] disabled:cursor-progress disabled:opacity-70">
                            <x-lucide-download class="h-3.5 w-3.5" />
                            <span data-ekspor-label>Unduh Excel</span>
                        </button>
                    </div>
                </div>

                {{-- Kartu ringkasan --}}
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-4">
                @foreach ($kartu as $item)
                    <div data-reveal style="--d: {{ .1 + $loop->index * .05 }}s">
                        <x-ui.stat-card
                            :label="$item['label']"
                            :value="$item['value']"
                            :icon="$item['icon']"
                            :tone="$item['tone']"
                            :accent="$item['accent']"
                            :decimals="$item['decimals']"
                            :suffix="$item['suffix'] ?? ''"
                            :catatan="$item['catatan']"
                            :count="true"
                        />
                    </div>
                @endforeach
                </div>

                {{-- Sebaran nilai --}}
                <div data-reveal style="--d: .3s" class="rounded-2xl border border-slate-100 bg-white p-4 shadow-md shadow-slate-200/60 sm:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">Sebaran Nilai</h3>
                            <p class="mt-0.5 text-xs text-slate-500">Persebaran seluruh nilai pada rentang dan filter ini.</p>
                        </div>
                        <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-500">{{ number_format($totalSebaran, 0, ',', '.') }} nilai</span>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach ($sebaran as $item)
                            @php $tone = $sebaranTone[$item['label']] ?? ['bar' => 'bg-slate-400', 'badge' => 'bg-slate-100 text-slate-600']; @endphp
                            <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-3.5">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $tone['badge'] }}">{{ $item['keterangan'] }}</span>
                                    <span class="text-sm font-bold tabular-nums text-slate-900">{{ number_format($item['jumlah'], 0, ',', '.') }}</span>
                                </div>
                                <p class="mt-2 text-xs font-medium text-slate-600">{{ $item['label'] }}</p>
                                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                                    <div class="h-full rounded-full {{ $tone['bar'] }}" style="width: {{ $item['persen'] }}%"></div>
                                </div>
                                <p class="mt-1 text-[11px] text-slate-400">{{ $item['persen'] }}% dari nilai</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Rekap per kelas (berhalaman, mengikuti pola panel dashboard) --}}
                <div data-region="laporan-kelas" data-reveal style="--d: .35s"
                     class="flex flex-col rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60 print:hidden">
                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-slate-900">Rekap per Kelas</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                @if ($perKelas->total() > 0)
                                    Menampilkan {{ $perKelas->firstItem() }}–{{ $perKelas->lastItem() }} dari {{ $perKelas->total() }} kelas · rata-rata dihitung dari seluruh nilai di kelas itu.
                                @else
                                    Rata-rata dihitung dari seluruh nilai di kelas itu.
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="flex-1 overflow-x-auto border-t border-slate-100">
                        @include('dashboard.guru.laporan.partials.tabel-kelas', ['rows' => $perKelas])
                    </div>

                    <x-ui.table-footer :paginator="$perKelas" label="kelas" />
                </div>

                {{-- Leger nilai per siswa (berhalaman, mengikuti pola panel dashboard) --}}
                <div data-region="laporan-siswa" data-reveal style="--d: .4s"
                     class="flex flex-col rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60 print:hidden">
                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-slate-900">Daftar Nilai per Siswa</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                @if ($perSiswa->total() > 0)
                                    @if ($filter['cari'] !== '')
                                        Hasil pencarian "{{ $filter['cari'] }}" · Menampilkan {{ $perSiswa->firstItem() }}–{{ $perSiswa->lastItem() }} dari {{ $perSiswa->total() }} siswa.
                                    @else
                                        Menampilkan {{ $perSiswa->firstItem() }}–{{ $perSiswa->lastItem() }} dari {{ $perSiswa->total() }} siswa.
                                    @endif
                                @elseif ($filter['cari'] !== '')
                                    Hasil pencarian "{{ $filter['cari'] }}".
                                @else
                                    Daftar nilai tiap siswa pada rentang ini.
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="flex-1 overflow-x-auto border-t border-slate-100">
                        @include('dashboard.guru.laporan.partials.tabel-siswa', [
                            'rows' => $perSiswa,
                            'nomorAwal' => $perSiswa->firstItem() ?? 1,
                        ])
                    </div>

                    <x-ui.table-footer :paginator="$perSiswa" label="siswa" />
                </div>

                {{-- Salinan cetak: seluruh baris sesuai filter, tanpa pagination karena jendela cetak hanya mencetak isi dokumen. --}}
                <div class="hidden space-y-4 print:block">
                    <div data-region="laporan-kelas-cetak" class="cetak-panel rounded-2xl border border-slate-100 bg-white p-4 shadow-md shadow-slate-200/60 sm:p-5">
                        <h3 class="text-sm font-semibold text-slate-900">Rekap per Kelas</h3>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $perKelasSemua->count() }} kelas · {{ Periode::label($filter['tahun_ajaran'], $filter['semester']) }}</p>

                        <div class="cetak-tabel mt-2 overflow-x-auto">
                            @include('dashboard.guru.laporan.partials.tabel-kelas', ['rows' => $perKelasSemua])
                        </div>
                    </div>

                    <div data-region="laporan-siswa-cetak" class="cetak-panel rounded-2xl border border-slate-100 bg-white p-4 shadow-md shadow-slate-200/60 sm:p-5">
                        <h3 class="text-sm font-semibold text-slate-900">Daftar Nilai per Siswa</h3>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ $perSiswaSemua->count() }} siswa
                            @if ($filter['kelas'] !== '') · Kelas {{ $filter['kelas'] }}@endif
                            @if ($filter['cari'] !== '') · Pencarian "{{ $filter['cari'] }}"@endif
                        </p>

                        <div class="cetak-tabel mt-2 overflow-x-auto">
                            @include('dashboard.guru.laporan.partials.tabel-siswa', [
                                'rows' => $perSiswaSemua,
                                'nomorAwal' => 1,
                            ])
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>
@endsection

@push('styles')
    <style>
        @media print {
            /* Warna latar kartu dan badge tidak tercetak tanpa ini. */
            #laporan-nilai-page, #laporan-nilai-page * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /* Kerangka dashboard disembunyikan agar tidak menyisakan celah tinggi di kertas. */
            #app-sidebar,
            #sidebar-backdrop,
            header,
            #logout-modal,
            #site-loading {
                display: none !important;
            }

            body { background: #fff !important; }

            body > div.min-h-screen {
                min-height: 0 !important;
                padding-left: 0 !important;
            }
            main {
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            form, button, a { display: none !important; }

            #laporan-nilai-page {
                position: static !important;
                padding-bottom: 0 !important;
            }
            #laporan-nilai-page > * + * { margin-top: 0.55rem !important; }

            .cetak-kop { display: block !important; }

            .cetak-panel {
                break-inside: avoid;
                page-break-inside: avoid;
                padding: 0.7rem 0.85rem !important;
                box-shadow: none !important;
            }

            .cetak-tabel {
                overflow: visible !important;
                margin-top: 0.5rem !important;
                break-inside: auto;
                page-break-inside: auto;
            }
            .cetak-tabel table {
                width: 100% !important;
                min-width: 0 !important;
                font-size: 10px;
                border-collapse: collapse;
            }
            .cetak-tabel thead { display: table-header-group; }
            .cetak-tabel tr, .cetak-tabel th, .cetak-tabel td {
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .cetak-tabel th {
                border: 1px solid #cbd5e1;
                background: #f1f5f9 !important;
                color: #334155 !important;
                font-weight: 700;
                text-align: left;
                vertical-align: middle;
            }
            .cetak-tabel td {
                border: 1px solid #e2e8f0;
                vertical-align: middle;
            }
            .cetak-tabel th, .cetak-tabel td {
                padding: 0.3rem 0.4rem;
                line-height: 1.35;
                white-space: normal;
                overflow-wrap: anywhere;
            }
            .cetak-tabel th.text-center, .cetak-tabel td.text-center { text-align: center !important; }

            @page { margin: 1.2cm; }
        }
    </style>
@endpush

@push('scripts')
    <script>
        (() => {
            const halaman = document.getElementById('laporan-nilai-page');
            if (!halaman) return;

            const csrf = null; // export hanya GET, tidak perlu token

            // ===== Cetak =====
            halaman.querySelector('[data-laporan-cetak]')?.addEventListener('click', () => window.print());

            // ===== Unduh Excel =====
            const tombol = document.getElementById('laporan-export');
            if (!tombol) return;

            const label = tombol.querySelector('[data-ekspor-label]');
            const LABEL_AWAL = label ? label.textContent.trim() : 'Unduh Excel';
            const exportUrl = @json(route('dashboard.guru.laporan.export'));
            const filterAktif = @json(array_filter($filter, fn ($nilai) => $nilai !== ''));

            let state = 'idle';

            const setState = (s, teks) => {
                state = s;
                tombol.dataset.state = s;
                tombol.disabled = s === 'muat';
                if (teks !== undefined && label) label.textContent = teks;
            };

            const namaBerkas = (disposisi) => {
                const cocok = /filename="?([^";]+)"?/i.exec(disposisi || '');
                return cocok ? cocok[1] : 'laporan-nilai.xlsx';
            };

            const unduhBlob = (blob, nama) => {
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = nama;
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(() => URL.revokeObjectURL(url), 1000);
            };

            const info = (icon, title, text) => {
                if (window.Swal) {
                    window.Swal.fire({ icon, title, text, confirmButtonText: 'Tutup' });
                } else {
                    alert(title + (text ? '\n' + text : ''));
                }
            };

            tombol.addEventListener('click', async () => {
                if (state === 'muat') return;

                setState('muat', 'Menyiapkan...');

                // Indikator proses (tanpa tombol) supaya guru tahu laporan sedang dibuat.
                if (window.Swal) {
                    window.Swal.fire({
                        title: 'Menyusun laporan...',
                        text: 'Mohon tunggu sebentar.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => window.Swal.showLoading(),
                    });
                }

                try {
                    const params = new URLSearchParams();
                    Object.entries(filterAktif).forEach(([k, v]) => {
                        if (v !== '' && v !== null && v !== undefined) params.set(k, v);
                    });

                    const q = params.toString();
                    const opsi = {
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: { Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
                    };
                    if (typeof AbortSignal !== 'undefined' && AbortSignal.timeout) {
                        opsi.signal = AbortSignal.timeout(45000);
                    }

                    const res = await fetch(q ? `${exportUrl}?${q}` : exportUrl, opsi);
                    const tipe = res.headers.get('Content-Type') || '';

                    if (!res.ok) throw new Error('Gagal mengunduh (HTTP ' + res.status + ')');
                    if (!tipe.includes('spreadsheet')) throw new Error('Server tidak mengembalikan berkas Excel.');

                    const total = Number(res.headers.get('X-Total-Baris') || 0);
                    unduhBlob(await res.blob(), namaBerkas(res.headers.get('Content-Disposition')));

                    window.Swal?.close();
                    setState('sukses', total > 0 ? `${total} siswa` : 'Tanpa data');

                    if (total > 0) {
                        info('success', 'Laporan diunduh', `${total} siswa tersimpan di berkas Excel.`);
                    } else {
                        info('info', 'Tidak ada data', 'Berkas tetap diunduh, tetapi belum ada nilai pada rentang ini.');
                    }

                    setTimeout(() => setState('idle', LABEL_AWAL), 2400);
                } catch (e) {
                    window.Swal?.close();
                    setState('idle', LABEL_AWAL);
                    info('error', 'Gagal mengunduh', e.message || 'Terjadi kesalahan saat mengunduh laporan.');
                }
            });
        })();
    </script>
@endpush
