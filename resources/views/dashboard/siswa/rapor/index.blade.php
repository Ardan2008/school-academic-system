@extends('layouts.dashboard')

@section('title', 'Rapor')
@section('subtitle', 'Dashboard Siswa')

@section('content')
    <x-dashboard.animations />

    <div id="rapor-page" data-dashboard>

        {{-- Judul halaman + identitas rombel siswa --}}
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0 print:hidden">
                <h2 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Rapor</h2>
                <p class="mt-1 text-sm text-slate-500">Hasil belajar Anda pada periode yang dipilih.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2 print:hidden">
                @if (filled($siswa->kelas))
                    <span class="inline-flex w-fit max-w-full items-center gap-2 rounded-xl border border-blue-100 bg-blue-50 px-3.5 py-2 text-xs font-medium text-primary-70">
                        <x-lucide-school class="h-4 w-4 shrink-0" />
                        <span class="truncate" title="Kelas {{ $siswa->kelas }}@if (filled($siswa->jurusan)) - {{ $siswa->jurusan }}@endif">
                            Kelas {{ $siswa->kelas }}@if (filled($siswa->jurusan)) - {{ $siswa->jurusan }}@endif
                        </span>
                    </span>
                @endif

                @if ($nilai->isNotEmpty())
                    <button type="button" onclick="window.print()"
                            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                        <x-lucide-printer class="h-4 w-4" />
                        Cetak
                    </button>
                    <button type="button" id="rapor-export" data-state="idle" aria-live="polite"
                            data-export-url="{{ route('dashboard.siswa.rapor.export', request()->query()) }}"
                            title="Unduh rapor periode yang dipilih dalam format PDF"
                            class="ekspor relative inline-flex shrink-0 items-center justify-center gap-2 overflow-hidden whitespace-nowrap rounded-xl border border-primary-60 bg-primary-60 px-4 py-2 text-sm font-medium text-white shadow-sm shadow-primary-60/25 transition hover:bg-primary-70 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60">
                        <span data-ekspor-kilau class="ekspor-kilau" aria-hidden="true"></span>
                        <x-lucide-file-down data-ekspor-ikon="idle" class="ekspor-ikon h-4 w-4" />
                        <svg data-ekspor-ikon="muat" class="ekspor-ikon h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                            <path d="M21 12a9 9 0 1 1-6.219-8.56" />
                        </svg>
                        <x-lucide-check data-ekspor-ikon="sukses" class="ekspor-ikon h-4 w-4" />
                        <span data-ekspor-label>Export PDF</span>
                    </button>
                @endif
            </div>
        </div>

        {{-- Kop cetak: tersembunyi di layar, tampil saat dicetak. --}}
        <div class="mb-4 hidden print:block">
            <div class="flex flex-wrap items-end justify-between gap-2 border-b border-slate-300 pb-2">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Rapor Hasil Belajar</h2>
                    <p class="text-xs text-slate-500">SASKANSA - Sistem Akademik Sekolah</p>
                </div>
                <p class="text-xs font-medium text-slate-600">{{ $periodeLabel }}</p>
            </div>
        </div>

        {{-- Identitas siswa + filter periode --}}
        <div class="mb-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-md shadow-slate-200/60 sm:p-6">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-slate-900">Identitas Siswa</h3>
                    <dl class="mt-3 grid grid-cols-2 gap-x-8 gap-y-2 text-sm">
                        <dt class="text-slate-400">Nama</dt>
                        <dd class="truncate font-medium text-slate-800" title="{{ $siswa->name }}">{{ $siswa->name }}</dd>
                        <dt class="text-slate-400">NIS</dt>
                        <dd class="font-medium text-slate-800">{{ $siswa->nis ?? '-' }}</dd>
                        <dt class="text-slate-400">Kelas</dt>
                        <dd class="font-medium text-slate-800">{{ $siswa->kelas ?? '-' }}{{ filled($siswa->jurusan) ? ' - '.$siswa->jurusan : '' }}</dd>
                    </dl>
                </div>

                <form method="GET" class="flex flex-wrap items-end gap-3 print:hidden">
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-slate-500">Tahun Ajaran</span>
                        <select name="tahun_ajaran" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 transition focus:border-primary-40 focus:outline-none focus:ring-2 focus:ring-primary-60/20">
                            @foreach ($tahunAjarans as $tahun)
                                <option value="{{ $tahun }}" @selected($periode['tahun_ajaran'] === $tahun)>{{ $tahun }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-slate-500">Semester</span>
                        <select name="semester" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 transition focus:border-primary-40 focus:outline-none focus:ring-2 focus:ring-primary-60/20">
                            @foreach ($semesters as $semester)
                                <option value="{{ $semester }}" @selected($periode['semester'] === $semester)>{{ $semester }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary-60 px-4 py-2 text-sm font-medium text-white shadow-sm shadow-primary-60/25 transition hover:bg-primary-70 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60">
                        <x-lucide-filter class="h-4 w-4" />
                        Tampilkan
                    </button>
                </form>
            </div>
        </div>

        @if ($nilai->isEmpty())
            {{-- Belum ada nilai pada periode yang dipilih. --}}
            <div class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-white/70 px-6 py-16 text-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-10 text-primary-70">
                    <x-lucide-award class="h-7 w-7" />
                </span>
                <h3 class="mt-4 text-base font-semibold text-slate-900">Rapor Belum Tersedia</h3>
                <p class="mt-1.5 max-w-md text-sm leading-relaxed text-slate-500">
                    Belum ada nilai tercatat untuk {{ $periodeLabel }}. Coba pilih tahun ajaran atau semester lain di atas.
                </p>
            </div>
        @else
            {{-- Kartu ringkasan periode --}}
            @php
                $statTone = [
                    'jumlah_mapel' => ['bg-violet-100 text-violet-600', '#7C3AED'],
                    'rata_rata' => ['bg-blue-100 text-primary-60', '#1E86FF'],
                    'tuntas' => ['bg-emerald-100 text-emerald-600', '#059669'],
                    'belum_tuntas' => ['bg-rose-100 text-rose-600', '#E11D48'],
                ];
                $stats = [
                    ['key' => 'jumlah_mapel', 'label' => 'Mata Pelajaran', 'icon' => 'lucide-book-open', 'value' => $ringkasan['jumlah_mapel'], 'catatan' => 'Dinilai pada periode ini'],
                    ['key' => 'rata_rata', 'label' => 'Rata-Rata Nilai', 'icon' => 'lucide-trending-up', 'value' => $ringkasan['rata_rata'] !== null ? number_format($ringkasan['rata_rata'], 2, ',', '.') : null, 'catatan' => $periodeLabel],
                    ['key' => 'tuntas', 'label' => 'Tuntas', 'icon' => 'lucide-circle-check', 'value' => $ringkasan['tuntas'], 'catatan' => $ringkasan['persen_tuntas'] !== null ? $ringkasan['persen_tuntas'].'% ketuntasan' : null],
                    ['key' => 'belum_tuntas', 'label' => 'Belum Tuntas', 'icon' => 'lucide-alert-circle', 'value' => $ringkasan['belum_tuntas'], 'catatan' => 'Di bawah KKM'],
                ];
            @endphp

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-4 print:hidden" data-reveal>
                @foreach ($stats as $stat)
                    @php
                        $statPill = $statTone[$stat['key']][0] ?? 'bg-blue-100 text-primary-60';
                        $statAccent = $statTone[$stat['key']][1] ?? '#1E86FF';
                    @endphp
                    <x-ui.stat-card
                        :label="$stat['label']"
                        :value="$stat['value']"
                        :icon="$stat['icon']"
                        :tone="$statPill"
                        :accent="$statAccent"
                        :catatan="$stat['catatan']"
                        :stat-key="$stat['key']"
                        layout="stacked"
                    />
                @endforeach
            </div>

            {{-- Daftar nilai periode terpilih --}}
            <div class="mt-5 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60 sm:mt-6" data-reveal>
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3.5 sm:px-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-60 text-white">
                            <x-lucide-award class="h-4.5 w-4.5" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-slate-900">Daftar Nilai</h3>
                            <p class="text-xs text-slate-500">{{ $ringkasan['jumlah_mapel'] }} mata pelajaran - {{ $ringkasan['tuntas'] }} tuntas</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 ring-1 ring-slate-100">
                        <x-lucide-calendar-days class="h-3.5 w-3.5 text-slate-400" />
                        {{ $periodeLabel }}
                    </span>
                </div>

                {{-- Tabel (md ke atas) --}}
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <th class="w-14 px-4 py-3.5 text-center font-medium">No</th>
                                <th class="px-4 py-3.5 font-medium">Mata Pelajaran</th>
                                <th class="w-24 px-4 py-3.5 text-center font-medium">KKM</th>
                                <th class="w-28 px-4 py-3.5 text-center font-medium">Nilai</th>
                                <th class="w-40 px-4 py-3.5 font-medium">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-800">
                            @foreach ($nilai as $i => $baris)
                                <tr class="transition hover:bg-blue-50/40">
                                    <td class="px-4 py-3.5 text-center text-xs text-slate-500">{{ $i + 1 }}</td>
                                    <td class="px-4 py-3.5 font-medium text-slate-900">{{ $baris->mataPelajaran?->nama ?? '-' }}</td>
                                    <td class="px-4 py-3.5 text-center tabular-nums text-slate-600">{{ $baris->kkm }}</td>
                                    <td class="px-4 py-3.5 text-center text-base font-bold tabular-nums text-slate-900">{{ $baris->nilai }}</td>
                                    <td class="px-4 py-3.5">
                                        @if ($baris->tuntas())
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-600">
                                                <x-lucide-circle-check class="h-3.5 w-3.5" />
                                                Tuntas
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-medium text-rose-600">
                                                <x-lucide-alert-circle class="h-3.5 w-3.5" />
                                                Belum Tuntas
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-slate-100 bg-slate-50/60">
                                <td colspan="3" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Rata-Rata</td>
                                <td colspan="2" class="px-4 py-3 text-left text-base font-bold tabular-nums text-slate-900">{{ number_format($ringkasan['rata_rata'], 2, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- Kartu (di bawah md) --}}
                <div class="divide-y divide-slate-100 md:hidden">
                    @foreach ($nilai as $baris)
                        <div class="flex items-start justify-between gap-3 px-4 py-3.5">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-slate-900" title="{{ $baris->mataPelajaran?->nama ?? '-' }}">{{ $baris->mataPelajaran?->nama ?? '-' }}</p>
                                <span @class([
                                    'mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium',
                                    'bg-emerald-50 text-emerald-600' => $baris->tuntas(),
                                    'bg-rose-50 text-rose-600' => ! $baris->tuntas(),
                                ])>
                                    <span @class([
                                        'h-1.5 w-1.5 rounded-full',
                                        'bg-emerald-500' => $baris->tuntas(),
                                        'bg-rose-500' => ! $baris->tuntas(),
                                    ])></span>
                                    {{ $baris->tuntas() ? 'Tuntas' : 'Belum Tuntas' }}
                                </span>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-lg font-bold tabular-nums text-slate-900">{{ $baris->nilai }}</p>
                                <p class="text-[11px] text-slate-400">KKM {{ $baris->kkm }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tombolExport = document.getElementById('rapor-export');
            if (!tombolExport) return;

            const labelExport = tombolExport.querySelector('[data-ekspor-label]');
            const LABEL_AWAL = labelExport ? labelExport.textContent.trim() : 'Export PDF';
            const KEMBALI_SUKSES_MS = 2400;

            let exportState = 'idle';
            let exportTimer = null;

            const setExportState = (state, text) => {
                clearTimeout(exportTimer);
                exportState = state;
                tombolExport.dataset.state = state;
                tombolExport.disabled = state === 'muat';
                if (text && labelExport) labelExport.textContent = text;
            };

            const alertError = (title, text) => {
                if (window.Swal) {
                    window.Swal.fire({ icon: 'error', title, text, confirmButtonText: 'Coba Lagi' });
                } else {
                    window.alert(text);
                }
            };

            const namaBerkas = (disposisi) => {
                if (!disposisi) return 'Rapor.pdf';
                const utf = disposisi.match(/filename\*=UTF-8''([^;]+)/i);
                if (utf && utf[1]) {
                    try { return decodeURIComponent(utf[1]); } catch (_) { return utf[1]; }
                }
                const biasa = /filename="?([^";]+)"?/i.exec(disposisi);
                return biasa ? biasa[1] : 'Rapor.pdf';
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

            tombolExport.addEventListener('click', async () => {
                if (exportState === 'muat') return;

                const exportUrl = tombolExport.dataset.exportUrl;
                if (!exportUrl) return;

                setExportState('muat', 'Menyiapkan PDF…');

                try {
                    const respons = await fetch(exportUrl, {
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'Accept': 'application/pdf, application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    const tipe = respons.headers.get('Content-Type') || '';

                    if (!respons.ok || !tipe.includes('pdf')) {
                        let pesan = 'Export PDF gagal. Silakan coba lagi.';
                        if (tipe.includes('application/json')) {
                            const data = await respons.json().catch(() => null);
                            if (data?.message) pesan = data.message;
                        } else if (respons.status === 401 || respons.status === 419) {
                            pesan = 'Sesi Anda sudah habis. Muat ulang halaman lalu coba lagi.';
                        } else if (respons.status === 403) {
                            pesan = 'Anda tidak punya akses untuk mengunduh data ini.';
                        } else if (respons.status === 422) {
                            pesan = 'Rapor belum tersedia untuk periode yang dipilih.';
                        }
                        throw new Error(pesan);
                    }

                    const blob = await respons.blob();
                    if (!blob || blob.size === 0) {
                        throw new Error('Export PDF gagal. Silakan coba lagi.');
                    }

                    unduhBlob(blob, namaBerkas(respons.headers.get('Content-Disposition')));

                    setExportState('sukses', 'PDF berhasil diunduh');
                    exportTimer = setTimeout(() => {
                        setExportState('idle', LABEL_AWAL);
                    }, KEMBALI_SUKSES_MS);
                } catch (galat) {
                    setExportState('idle', LABEL_AWAL);
                    alertError('Gagal Mengunduh', galat?.message || 'Tidak dapat terhubung ke server. Silakan coba lagi.');
                }
            });
        });
    </script>
@endpush

@push('styles')
    <style>
        /* ===== Tombol export: tiga keadaan (diam, mengunduh, berhasil) ===== */
        .ekspor-ikon { transition: opacity .18s ease, transform .18s ease; }

        .ekspor[data-state="idle"] [data-ekspor-ikon]:not([data-ekspor-ikon="idle"]),
        .ekspor[data-state="muat"] [data-ekspor-ikon]:not([data-ekspor-ikon="muat"]),
        .ekspor[data-state="sukses"] [data-ekspor-ikon]:not([data-ekspor-ikon="sukses"]) {
            display: none;
        }

        .ekspor[data-state="muat"] {
            cursor: progress;
            translate: none;
            box-shadow: none;
            filter: saturate(.85);
        }

        .ekspor[data-state="muat"]:hover { transform: none; }
        .ekspor[data-state="muat"] [data-ekspor-label] { opacity: .85; }

        .ekspor[data-state="sukses"] {
            cursor: default;
            border-color: rgb(167 243 208);
            background-color: rgb(236 253 245);
            color: rgb(4 120 87);
            translate: none;
            box-shadow: 0 6px 14px -10px rgb(16 185 129);
        }

        .ekspor[data-state="sukses"] [data-ekspor-ikon="sukses"] {
            animation: ekspor-pop .45s cubic-bezier(.34, 1.56, .64, 1);
        }

        @keyframes ekspor-pop {
            0%   { transform: scale(.3) rotate(-25deg); opacity: 0; }
            60%  { transform: scale(1.18) rotate(6deg); opacity: 1; }
            100% { transform: scale(1) rotate(0); opacity: 1; }
        }

        .ekspor-kilau {
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: 0;
            background: linear-gradient(100deg, transparent 20%, rgb(255 255 255 / .75) 50%, transparent 80%);
            transform: translateX(-100%);
        }

        .ekspor[data-state="muat"] .ekspor-kilau {
            opacity: 1;
            animation: ekspor-kilau 1.1s ease-in-out infinite;
        }

        @keyframes ekspor-kilau {
            0%   { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        @media (prefers-reduced-motion: reduce) {
            .ekspor[data-state="muat"] .ekspor-kilau { animation: none; opacity: .45; }
            .ekspor[data-state="sukses"] [data-ekspor-ikon="sukses"] { animation: none; }
        }

        @page {
            margin: 10mm;
        }

        @media print {
            /* Chrome & Firefox tidak otomatis mencetak latar kartu dan badge. */
            #rapor-page, #rapor-page * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            #rapor-page [data-reveal] {
                opacity: 1 !important;
                transform: none !important;
                filter: none !important;
            }

            /* Kerangka dashboard disembunyikan agar tidak menyisakan celah di kertas. */
            #app-sidebar,
            #sidebar-backdrop,
            header,
            #logout-modal,
            #site-loading {
                display: none !important;
            }

            main {
                max-width: none;
                padding: 0;
            }
        }
    </style>
@endpush
