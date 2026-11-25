@extends('layouts.dashboard')

@section('title', 'Tugas & Materi')
@section('subtitle', 'Dashboard Siswa')

@section('content')
    <x-dashboard.animations />

    <div id="tugas-materi-page" data-dashboard>
        <style>
            [data-tm-modal] { transition: opacity .3s ease; }
            [data-tm-modal][data-open="false"] { opacity: 0; pointer-events: none; }
            [data-tm-modal][data-open="true"] { opacity: 1; pointer-events: auto; }
            [data-tm-panel] { opacity: 0; transform: translateY(20px) scale(.97); transition: opacity .3s cubic-bezier(.22,1,.36,1), transform .3s cubic-bezier(.22,1,.36,1); }
            [data-tm-modal][data-open="true"] [data-tm-panel] { opacity: 1; transform: none; }
            @media (prefers-reduced-motion: reduce) {
                [data-tm-modal], [data-tm-panel] { transition: none; }
                [data-tm-panel] { transform: none; }
            }
            [data-truncate-tip][data-truncated="1"] { cursor: help; }
            .tm-truncate-pop {
                position: fixed;
                z-index: 100;
                max-width: min(24rem, calc(100vw - 2rem));
                padding: .5rem .625rem;
                border-radius: .5rem;
                background: #0f172a;
                color: #f8fafc;
                font-size: .75rem;
                line-height: 1.25rem;
                box-shadow: 0 10px 15px -3px rgb(15 23 42 / .2), 0 4px 6px -4px rgb(15 23 42 / .2);
                pointer-events: none;
                opacity: 0;
                transform: translateY(2px);
                transition: opacity .12s ease, transform .12s ease;
                white-space: pre-line;
                word-break: break-word;
            }
            .tm-truncate-pop[data-show="1"] { opacity: 1; transform: none; }
        </style>

        @if (session('success'))
            <div data-flash-success="{{ session('success') }}" class="hidden"></div>
        @endif

        @if ($errors->any())
            <div role="alert" class="mb-5 flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4">
                <x-lucide-triangle-alert class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
                <p class="text-sm font-medium text-red-800">{{ $errors->first() }}</p>
            </div>
        @endif

        {{-- Judul halaman + identitas rombel siswa --}}
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            @if (filled($siswa->kelas))
                <span class="inline-flex w-fit items-center gap-2 rounded-xl border border-blue-100 bg-blue-50 px-3.5 py-2 text-xs font-medium text-primary-70">
                    <x-lucide-school class="h-4 w-4 shrink-0" />
                    <span class="truncate" title="Kelas {{ $siswa->kelas }}@if (filled($siswa->jurusan)) · {{ $siswa->jurusan }}@endif">
                        Kelas {{ $siswa->kelas }}@if (filled($siswa->jurusan)) · {{ $siswa->jurusan }}@endif
                    </span>
                </span>
            @endif
        </div>

        @php
            $tabUrl = fn (string $t, ?string $s = null, string $cari = ''): string => route('dashboard.siswa.tugas-materi.index', array_filter([
                'tab' => $t,
                'status' => $s,
                'q' => $cari,
            ], fn ($nilai): bool => $nilai !== null && $nilai !== ''));
        @endphp

        @if (blank($siswa->kelas))
            {{-- Siswa belum punya kelas: tugas & materi tidak bisa ditentukan. --}}
            <div class="rounded-2xl border border-amber-200/70 bg-amber-50/60 p-4 shadow-sm sm:p-5">
                <div class="flex items-start gap-3">
                    <x-lucide-alert-circle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                    <div>
                        <h3 class="text-sm font-semibold text-amber-900">Data Kelas Belum Diatur</h3>
                        <p class="mt-0.5 text-xs leading-relaxed text-amber-700">
                            Akun Anda belum memiliki data kelas, sehingga tugas dan materi belum dapat ditampilkan.
                            Silakan hubungi administrator sekolah untuk melengkapi data kelas Anda.
                        </p>
                    </div>
                </div>
            </div>
        @elseif ($ringkasan['total_tugas'] === 0 && $ringkasan['total_materi'] === 0 && $q === '' && $status === null)
            {{-- Sudah punya kelas, tetapi guru belum memberi tugas atau materi. --}}
            <div class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-white/70 px-6 py-16 text-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-10 text-primary-70">
                    <x-lucide-book-marked class="h-7 w-7" />
                </span>
                <h3 class="mt-4 text-base font-semibold text-slate-900">Belum Ada Tugas &amp; Materi</h3>
                <p class="mt-1.5 max-w-md text-sm leading-relaxed text-slate-500">
                    Untuk kelas Anda belum ada tugas maupun materi. Semuanya akan muncul di sini setelah dibagikan oleh guru.
                </p>
            </div>
        @else
            {{-- Kartu ringkasan --}}
            @php
                $statTone = [
                    'total_tugas' => ['bg-blue-100 text-primary-60', '#1E86FF'],
                    'menunggu' => ['bg-amber-100 text-amber-600', '#D97706'],
                    'terlambat' => ['bg-rose-100 text-rose-600', '#E11D48'],
                    'total_materi' => ['bg-violet-100 text-violet-600', '#7C3AED'],
                ];
                $stats = [
                    ['key' => 'total_tugas', 'label' => 'Total Tugas', 'icon' => 'lucide-clipboard-list', 'value' => $ringkasan['total_tugas']],
                    ['key' => 'menunggu', 'label' => 'Menunggu', 'icon' => 'lucide-clock', 'value' => $ringkasan['menunggu']],
                    ['key' => 'terlambat', 'label' => 'Terlambat', 'icon' => 'lucide-alert-circle', 'value' => $ringkasan['terlambat']],
                    ['key' => 'total_materi', 'label' => 'Materi Ajar', 'icon' => 'lucide-book-open', 'value' => $ringkasan['total_materi']],
                ];
            @endphp

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-4" data-reveal>
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
                        :stat-key="$stat['key']"
                        layout="stacked"
                    />
                @endforeach
            </div>

            {{-- Tab Tugas / Materi (server-side lewat query string) --}}
            <div class="mt-5 flex max-w-full items-center gap-1 overflow-x-auto rounded-xl border border-slate-100 bg-white p-1 shadow-sm sm:mt-6" data-reveal>
                <a href="{{ $tabUrl('tugas', $status, $q) }}"
                   @class([
                        'inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60',
                        'bg-primary-60 text-white shadow-sm shadow-primary-60/25' => $tab === 'tugas',
                        'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => $tab !== 'tugas',
                   ])>
                    <x-lucide-clipboard-list class="h-4 w-4" />
                    Tugas
                    <span @class([
                        'rounded-full px-2 py-0.5 text-xs font-semibold',
                        'bg-white/20 text-white' => $tab === 'tugas',
                        'bg-slate-100 text-slate-500' => $tab !== 'tugas',
                    ])>{{ $ringkasan['total_tugas'] }}</span>
                </a>

                <a href="{{ $tabUrl('materi', null, $q) }}"
                   @class([
                        'inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60',
                        'bg-primary-60 text-white shadow-sm shadow-primary-60/25' => $tab === 'materi',
                        'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => $tab !== 'materi',
                   ])>
                    <x-lucide-book-open class="h-4 w-4" />
                    Materi
                    <span @class([
                        'rounded-full px-2 py-0.5 text-xs font-semibold',
                        'bg-white/20 text-white' => $tab === 'materi',
                        'bg-slate-100 text-slate-500' => $tab !== 'materi',
                    ])>{{ $ringkasan['total_materi'] }}</span>
                </a>
            </div>

            {{-- Pencarian tugas/materi (server-side, mempertahankan tab & filter) --}}
            <form method="GET" action="{{ route('dashboard.siswa.tugas-materi.index') }}"
                  class="mt-4 flex items-center gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                @if ($tab === 'tugas' && $status !== null)
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif

                <div class="relative min-w-0 flex-1">
                    <label for="tugas-materi-search" class="sr-only">Cari {{ $tab === 'tugas' ? 'tugas' : 'materi' }}</label>
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                        <x-lucide-search class="h-4 w-4" />
                    </span>
                    <input
                        id="tugas-materi-search"
                        type="search"
                        name="q"
                        value="{{ $q }}"
                        placeholder="Cari {{ $tab === 'tugas' ? 'tugas' : 'materi' }}…"
                        class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-9 pr-9 text-sm text-slate-800 placeholder-slate-400 shadow-xs transition focus:border-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-50/30"
                    />
                    @if ($q !== '')
                        <a href="{{ $tabUrl($tab, $tab === 'tugas' ? $status : null) }}"
                           aria-label="Bersihkan pencarian"
                           title="Bersihkan pencarian"
                           class="absolute inset-y-0 right-2 flex items-center px-1 text-slate-400 transition hover:text-slate-600">
                            <x-lucide-x class="h-4 w-4" />
                        </a>
                    @endif
                </div>

                <button type="submit"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-primary-60 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-70">
                    <x-lucide-search class="h-4 w-4" />
                    Cari
                </button>
            </form>

            @if ($tab === 'tugas')
                {{-- Filter status tugas (server-side) --}}
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    @php
                        $chips = [
                            ['kode' => null, 'label' => 'Semua', 'jumlah' => $ringkasan['total_tugas']],
                            ['kode' => 'menunggu', 'label' => 'Menunggu', 'jumlah' => $ringkasan['menunggu']],
                            ['kode' => 'terlambat', 'label' => 'Terlambat', 'jumlah' => $ringkasan['terlambat']],
                            ['kode' => 'dikumpulkan', 'label' => 'Dikumpulkan', 'jumlah' => $ringkasan['dikumpulkan']],
                        ];
                    @endphp

                    @foreach ($chips as $chip)
                        <a href="{{ $tabUrl('tugas', $chip['kode'], $q) }}"
                           @class([
                                'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60',
                                'bg-primary-60 text-white shadow-sm shadow-primary-60/25' => $status === $chip['kode'],
                                'border border-slate-200 bg-white text-slate-600 hover:border-primary-60 hover:bg-blue-50 hover:text-primary-70' => $status !== $chip['kode'],
                           ])>
                            {{ $chip['label'] }}
                            <span @class([
                                'text-[11px]',
                                'text-white/70' => $status === $chip['kode'],
                                'text-slate-400' => $status !== $chip['kode'],
                            ])>{{ $chip['jumlah'] }}</span>
                        </a>
                    @endforeach
                </div>

                {{-- Daftar tugas --}}
                <div class="mt-4 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60 sm:mt-5" data-reveal>
                    @if ($tugas->isEmpty())
                        <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-10 text-primary-70">
                                <x-lucide-clipboard-list class="h-6 w-6" />
                            </span>
                            <h3 class="mt-4 text-sm font-semibold text-slate-900">Tidak Ada Tugas</h3>
                            <p class="mt-1.5 max-w-md text-sm text-slate-500">
                                @if ($q !== '')
                                    Tidak ada tugas yang cocok dengan pencarian "{{ $q }}".
                                @elseif ($status !== null)
                                    Tidak ada tugas dengan status ini.
                                @else
                                    Belum ada tugas yang dibagikan untuk kelas Anda.
                                @endif
                            </p>
                            @if ($status !== null || $q !== '')
                                <a href="{{ $tabUrl('tugas') }}"
                                   class="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-primary-60 transition hover:bg-slate-50">
                                    <x-lucide-rotate-ccw class="h-3.5 w-3.5" />
                                    Tampilkan Semua Tugas
                                </a>
                            @endif
                        </div>
                    @else
                        {{-- Tabel (md ke atas) --}}
                        <div class="hidden overflow-x-auto md:block">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                        <th class="w-14 px-4 py-3.5 text-center font-medium">No</th>
                                        <th class="px-4 py-3.5 font-medium">Tugas</th>
                                        <th class="px-4 py-3.5 font-medium">Mata Pelajaran</th>
                                        <th class="px-4 py-3.5 font-medium">Guru Pengampu</th>
                                        <th class="w-40 px-4 py-3.5 font-medium">Tenggat</th>
                                        <th class="w-36 px-4 py-3.5 font-medium">Status</th>
                                        <th class="w-64 px-4 py-3.5 font-medium">Pengumpulan</th>
                                        <th class="w-20 px-4 py-3.5 text-center font-medium">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-800">
                                    @foreach ($tugas as $i => $item)
                                        @php
                                            $st = $statusTugas[$item->id];
                                            $pengumpulan = $item->pengumpulan->first();
                                        @endphp
                                        <tr class="transition hover:bg-blue-50/40">
                                            <td class="px-4 py-3.5 text-center text-xs text-slate-500">{{ $i + 1 }}</td>
                                            <td class="px-4 py-3.5">
                                                <button type="button"
                                                        data-detail-trigger
                                                        data-detail-url="{{ route('dashboard.siswa.tugas-materi.tugas.show', $item) }}"
                                                        class="text-left font-medium text-slate-900 transition hover:text-primary-60 hover:underline">
                                                    {{ $item->judul }}
                                                </button>
                                                @if (filled($item->deskripsi))
                                                    <p data-truncate-tip class="mt-0.5 max-w-md line-clamp-2 text-xs text-slate-500">{{ $item->deskripsi }}</p>
                                                @endif
                                                @if ($item->file_path)
                                                    @php
                                                        $lampiranBadge = $item->format_file && $item->ukuran_file
                                                            ? $item->format_file . ' · ' . $item->ukuran_file
                                                            : ($item->format_file ?: ($item->nama_file ?? 'Lampiran'));
                                                    @endphp
                                                    <p class="mt-1.5 inline-flex max-w-full items-center gap-1 rounded-full border border-blue-100 bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-primary-70" title="{{ $item->nama_file ?? 'Lampiran' }}">
                                                        <x-lucide-paperclip class="h-3 w-3 shrink-0" />
                                                        <span class="truncate">{{ $lampiranBadge }}</span>
                                                    </p>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3.5">
                                                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-10 px-2.5 py-0.5 text-xs font-medium text-primary-70">
                                                    {{ $item->mataPelajaran?->nama ?? '—' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3.5 text-slate-600">{{ $item->guru?->name ?? '—' }}</td>
                                            <td class="px-4 py-3.5">
                                                @if ($item->deadline !== null)
                                                    <p class="tabular-nums text-slate-700">{{ \App\Support\Waktu::tanggal($item->deadline) }}</p>
                                                    <p @class([
                                                        'mt-0.5 text-xs font-medium',
                                                        'text-rose-600' => $st['kode'] === 'terlambat',
                                                        'text-amber-600' => $st['kode'] === 'menunggu',
                                                        'text-slate-400' => $st['kode'] === 'dikumpulkan',
                                                    ])>{{ $item->labelDeadline() }}</p>
                                                @else
                                                    <span class="text-xs text-slate-400">Tanpa tenggat</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3.5">
                                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $st['class'] }}">
                                                    <x-dynamic-component :component="$st['icon']" class="h-3.5 w-3.5" />
                                                    {{ $st['label'] }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3.5">
                                                @if (filled($pengumpulan?->jawaban))
                                                    <p data-truncate-tip class="line-clamp-2 text-xs text-slate-500">Jawaban: {{ $pengumpulan->jawaban }}</p>
                                                @endif
                                                @if ($pengumpulan?->nilai !== null)
                                                    <p class="mt-1 text-xs text-slate-500">Nilai: <span class="font-semibold text-slate-700">{{ rtrim(rtrim(number_format((float) $pengumpulan->nilai, 2, ',', '.'), '0'), ',') }}</span></p>
                                                @endif
                                                @if (filled($pengumpulan?->umpan_balik))
                                                    <p data-truncate-tip class="mt-1 line-clamp-2 text-xs text-slate-500">Umpan balik: {{ $pengumpulan->umpan_balik }}</p>
                                                @endif

                                                <button type="button"
                                                        data-submit-trigger
                                                        data-tugas-id="{{ $item->id }}"
                                                        data-action="{{ route('dashboard.siswa.tugas-materi.pengumpulan.store', $item) }}"
                                                        data-judul="{{ $item->judul }}"
                                                        data-jawaban="{{ old('jawaban', $pengumpulan?->jawaban) }}"
                                                        data-sudah="{{ $pengumpulan !== null ? '1' : '0' }}"
                                                        class="mt-2 inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-primary-60 transition hover:bg-slate-50">
                                                    <x-lucide-send class="h-3.5 w-3.5" />
                                                    {{ $pengumpulan === null ? 'Kumpulkan Tugas' : 'Kumpulkan Ulang' }}
                                                </button>
                                            </td>
                                            <td class="px-4 py-3.5 text-center">
                                                <button type="button"
                                                        data-detail-trigger
                                                        data-detail-url="{{ route('dashboard.siswa.tugas-materi.tugas.show', $item) }}"
                                                        title="Lihat Detail"
                                                        aria-label="Lihat Detail"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-primary-60 transition hover:bg-slate-50 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60">
                                                    <x-lucide-eye class="h-4 w-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Kartu (di bawah md) --}}
                        <div class="divide-y divide-slate-100 md:hidden">
                            @foreach ($tugas as $item)
                                @php
                                    $st = $statusTugas[$item->id];
                                    $pengumpulan = $item->pengumpulan->first();
                                @endphp
                                <div class="px-4 py-3.5">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <button type="button"
                                                    data-detail-trigger
                                                    data-detail-url="{{ route('dashboard.siswa.tugas-materi.tugas.show', $item) }}"
                                                    class="line-clamp-2 text-left font-medium text-slate-900 transition hover:text-primary-60 hover:underline"
                                                    title="{{ $item->judul }}">
                                                {{ $item->judul }}
                                            </button>
                                            <p class="mt-0.5 truncate text-xs text-slate-500" title="{{ $item->mataPelajaran?->nama ?? '-' }}">{{ $item->mataPelajaran?->nama ?? '-' }}</p>
                                            @if ($item->file_path)
                                                    @php
                                                        $lampiranBadge = $item->format_file && $item->ukuran_file
                                                            ? $item->format_file . ' · ' . $item->ukuran_file
                                                            : ($item->format_file ?: ($item->nama_file ?? 'Lampiran'));
                                                    @endphp
                                                <p class="mt-1.5 inline-flex max-w-full items-center gap-1 rounded-full border border-blue-100 bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-primary-70" title="{{ $item->nama_file ?? 'Lampiran' }}">
                                                    <x-lucide-paperclip class="h-3 w-3 shrink-0" />
                                                    <span class="truncate">{{ $lampiranBadge }}</span>
                                                </p>
                                            @endif
                                        </div>
                                        <div class="flex shrink-0 items-center gap-2">
                                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $st['class'] }}">
                                                <x-dynamic-component :component="$st['icon']" class="h-3.5 w-3.5" />
                                                {{ $st['label'] }}
                                            </span>
                                            <button type="button"
                                                    data-detail-trigger
                                                    data-detail-url="{{ route('dashboard.siswa.tugas-materi.tugas.show', $item) }}"
                                                    title="Lihat Detail"
                                                    aria-label="Lihat Detail"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-primary-60 transition hover:bg-slate-50 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60">
                                                <x-lucide-eye class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                                        <span class="inline-flex items-center gap-1.5">
                                            <x-lucide-user-round class="h-3.5 w-3.5 shrink-0 text-slate-400" />
                                            {{ $item->guru?->name ?? 'Belum ada guru pengampu' }}
                                        </span>
                                        <span @class([
                                            'inline-flex items-center gap-1.5',
                                            'text-rose-600' => $st['kode'] === 'terlambat',
                                            'text-amber-600' => $st['kode'] === 'menunggu',
                                        ])>
                                            <x-lucide-clock class="h-3.5 w-3.5 shrink-0" />
                                            @if ($item->deadline !== null)
                                                {{ \App\Support\Waktu::tanggal($item->deadline) }} · {{ $item->labelDeadline() }}
                                            @else
                                                Tanpa tenggat
                                            @endif
                                        </span>
                                        @if ($pengumpulan?->nilai !== null)
                                            <span class="text-slate-500">Nilai: <span class="font-semibold text-slate-700">{{ rtrim(rtrim(number_format((float) $pengumpulan->nilai, 2, ',', '.'), '0'), ',') }}</span></span>
                                        @endif
                                    </div>

                                    @if (filled($pengumpulan?->jawaban))
                                        <p data-truncate-tip class="mt-2 line-clamp-2 text-xs text-slate-500">Jawaban: {{ $pengumpulan->jawaban }}</p>
                                    @endif
                                    @if (filled($pengumpulan?->umpan_balik))
                                        <p data-truncate-tip class="mt-1 line-clamp-2 text-xs text-slate-500">Umpan balik: {{ $pengumpulan->umpan_balik }}</p>
                                    @endif

                                    <button type="button"
                                            data-submit-trigger
                                            data-tugas-id="{{ $item->id }}"
                                            data-action="{{ route('dashboard.siswa.tugas-materi.pengumpulan.store', $item) }}"
                                            data-judul="{{ $item->judul }}"
                                            data-jawaban="{{ old('jawaban', $pengumpulan?->jawaban) }}"
                                            data-sudah="{{ $pengumpulan !== null ? '1' : '0' }}"
                                            class="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-primary-60 transition hover:bg-slate-50">
                                        <x-lucide-send class="h-3.5 w-3.5" />
                                        {{ $pengumpulan === null ? 'Kumpulkan Tugas' : 'Kumpulkan Ulang' }}
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                {{-- Daftar materi --}}
                <div class="mt-4 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60 sm:mt-5" data-reveal>
                    @if ($materi->isEmpty())
                        <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-10 text-primary-70">
                                <x-lucide-book-open class="h-6 w-6" />
                            </span>
                            <h3 class="mt-4 text-sm font-semibold text-slate-900">
                                {{ $q !== '' ? 'Materi Tidak Ditemukan' : 'Belum Ada Materi' }}
                            </h3>
                            <p class="mt-1.5 max-w-md text-sm text-slate-500">
                                @if ($q !== '')
                                    Tidak ada materi yang cocok dengan pencarian "{{ $q }}".
                                @else
                                    Belum ada materi yang dibagikan untuk kelas Anda.
                                @endif
                            </p>
                            @if ($q !== '')
                                <a href="{{ $tabUrl('materi') }}"
                                   class="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-primary-60 transition hover:bg-slate-50">
                                    <x-lucide-rotate-ccw class="h-3.5 w-3.5" />
                                    Tampilkan Semua Materi
                                </a>
                            @endif
                        </div>
                    @else
                        {{-- Tabel (md ke atas) --}}
                        <div class="hidden overflow-x-auto md:block">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                        <th class="w-14 px-4 py-3.5 text-center font-medium">No</th>
                                        <th class="px-4 py-3.5 font-medium">Materi</th>
                                        <th class="px-4 py-3.5 font-medium">Mata Pelajaran</th>
                                        <th class="px-4 py-3.5 font-medium">Guru Pengampu</th>
                                        <th class="w-40 px-4 py-3.5 font-medium">Tanggal</th>
                                        <th class="w-20 px-4 py-3.5 text-center font-medium">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-800">
                                    @foreach ($materi as $i => $item)
                                        <tr class="transition hover:bg-blue-50/40">
                                            <td class="px-4 py-3.5 text-center text-xs text-slate-500">{{ $i + 1 }}</td>
                                            <td class="px-4 py-3.5">
                                                <div class="flex items-start gap-2.5">
                                                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-10 text-primary-70">
                                                        <x-lucide-file-text class="h-4 w-4" />
                                                    </span>
                                                    <div class="min-w-0">
                                                        <p class="font-medium text-slate-900">{{ $item->judul }}</p>
                                                        @if (filled($item->deskripsi))
                                                            <p data-truncate-tip class="mt-0.5 max-w-md line-clamp-2 text-xs text-slate-500">{{ $item->deskripsi }}</p>
                                                        @endif
                                                        @if ($item->file_path)
                                                            @php
                                                                $lampiranBadge = $item->format_file && $item->ukuran_file
                                                                    ? $item->format_file . ' · ' . $item->ukuran_file
                                                                    : ($item->format_file ?: ($item->nama_file ?? 'Lampiran'));
                                                            @endphp
                                                            <p class="mt-1.5 inline-flex max-w-full items-center gap-1 rounded-full border border-blue-100 bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-primary-70" title="{{ $item->nama_file ?? 'Lampiran' }}">
                                                                <x-lucide-paperclip class="h-3 w-3 shrink-0" />
                                                                <span class="truncate">{{ $lampiranBadge }}</span>
                                                            </p>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3.5">
                                                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-10 px-2.5 py-0.5 text-xs font-medium text-primary-70">
                                                    {{ $item->mataPelajaran?->nama ?? '—' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3.5 text-slate-600">{{ $item->guru?->name ?? '—' }}</td>
                                            <td class="px-4 py-3.5 tabular-nums text-slate-600">
                                                {{ $item->tanggal !== null ? \App\Support\Waktu::tanggal($item->tanggal) : '—' }}
                                            </td>
                                            <td class="px-4 py-3.5 text-center">
                                                <button type="button"
                                                        data-detail-materi-trigger
                                                        data-detail-url="{{ route('dashboard.siswa.tugas-materi.materi.show', $item) }}"
                                                        title="Buka Materi"
                                                        aria-label="Buka Materi"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-primary-70 transition hover:bg-slate-50 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60">
                                                    <x-lucide-eye class="h-4 w-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Kartu (di bawah md) --}}
                        <div class="divide-y divide-slate-100 md:hidden">
                            @foreach ($materi as $item)
                                <div class="flex items-start gap-3 px-4 py-3.5">
                                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-10 text-primary-70">
                                        <x-lucide-file-text class="h-4.5 w-4.5" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-slate-900">{{ $item->judul }}</p>
                                        <p class="mt-0.5 truncate text-xs text-slate-500" title="{{ $item->mataPelajaran?->nama ?? '—' }}">{{ $item->mataPelajaran?->nama ?? '—' }}</p>
                                        @if ($item->file_path)
                                            @php
                                                $lampiranBadge = $item->format_file && $item->ukuran_file
                                                    ? $item->format_file . ' · ' . $item->ukuran_file
                                                    : ($item->format_file ?: ($item->nama_file ?? 'Lampiran'));
                                            @endphp
                                            <p class="mt-1.5 inline-flex max-w-full items-center gap-1 rounded-full border border-blue-100 bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-primary-70" title="{{ $item->nama_file ?? 'Lampiran' }}">
                                                <x-lucide-paperclip class="h-3 w-3 shrink-0" />
                                                <span class="truncate">{{ $lampiranBadge }}</span>
                                            </p>
                                        @endif
                                        <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                                            <span class="inline-flex items-center gap-1.5">
                                                <x-lucide-user-round class="h-3.5 w-3.5 shrink-0 text-slate-400" />
                                                {{ $item->guru?->name ?? 'Belum ada guru pengampu' }}
                                            </span>
                                            <span class="inline-flex items-center gap-1.5">
                                                <x-lucide-calendar-days class="h-3.5 w-3.5 shrink-0 text-slate-400" />
                                                {{ $item->tanggal !== null ? \App\Support\Waktu::tanggal($item->tanggal) : '—' }}
                                            </span>
                                        </div>
                                        <button type="button"
                                                data-detail-materi-trigger
                                                data-detail-url="{{ route('dashboard.siswa.tugas-materi.materi.show', $item) }}"
                                                title="Buka Materi"
                                                aria-label="Buka Materi"
                                                class="mt-2 inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-primary-70 transition hover:bg-slate-50 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60">
                                            <x-lucide-eye class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        @endif

        <div id="detail-tugas-modal"
             data-tm-modal
             data-open="false"
             aria-hidden="true"
             inert
             class="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto overscroll-contain bg-slate-900/50 p-4 backdrop-blur-sm lg:left-64"
             role="dialog"
             aria-modal="true"
             aria-labelledby="detail-tugas-title">
            <div data-tm-backdrop class="absolute inset-0"></div>
            <div data-tm-panel class="relative z-10 flex max-h-[90dvh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Detail Tugas</p>
                        <h2 id="detail-tugas-title" data-detail-title class="mt-0.5 truncate text-base font-semibold text-slate-900">Detail Tugas</h2>
                        <p data-detail-subtitle class="mt-0.5 truncate text-xs text-slate-500">Memuat detail tugas...</p>
                    </div>
                    <button type="button"
                            data-tm-close
                            class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 active:scale-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60"
                            aria-label="Tutup detail tugas">
                        <x-lucide-x class="h-4.5 w-4.5" />
                    </button>
                </div>
                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    <div data-detail-error hidden role="alert" class="rounded-xl bg-rose-50 px-3 py-2 text-xs text-rose-700"></div>
                    <span data-detail-lampiran-empty hidden>Belum ada lampiran dari Guru.</span>

                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-3">
                            <div>
                                <dt class="text-xs text-slate-500">Mata Pelajaran</dt>
                                <dd data-detail-mapel class="mt-0.5 text-sm font-medium text-slate-800">-</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Kelas Tujuan</dt>
                                <dd data-detail-kelas class="mt-0.5 text-sm font-medium text-slate-800">-</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Tenggat Pengumpulan</dt>
                                <dd class="mt-0.5 text-sm font-medium tabular-nums text-slate-800">
                                    <span data-detail-tenggat>-</span>
                                    <span data-detail-label-tenggat class="ml-1 text-xs font-medium text-slate-400"></span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Waktu Pengumpulan</dt>
                                <dd data-detail-waktu class="mt-0.5 text-sm font-medium tabular-nums text-slate-800">-</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Umpan Balik Guru</dt>
                                <dd data-detail-umpan class="mt-0.5 whitespace-pre-line text-sm text-slate-700">-</dd>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <dt class="text-xs text-slate-500">Guru Pengampu</dt>
                                <dd data-detail-guru class="mt-0.5 text-sm font-medium text-slate-800">-</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Tanggal Tugas Diberikan</dt>
                                <dd data-detail-tanggal class="mt-0.5 text-sm font-medium tabular-nums text-slate-800">-</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Status Pengumpulan</dt>
                                <dd class="mt-0.5">
                                    <span data-detail-status class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">-</span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Nilai</dt>
                                <dd data-detail-nilai class="mt-0.5 text-sm font-semibold text-slate-800">-</dd>
                            </div>
                        </div>
                    </dl>

                    <div data-detail-deskripsi-wrap hidden class="rounded-xl border border-slate-100 bg-white p-3.5">
                        <p class="text-xs font-medium text-slate-500">Deskripsi / Instruksi Tugas</p>
                        <p data-detail-deskripsi class="mt-1 whitespace-pre-line text-sm leading-relaxed text-slate-700"></p>
                    </div>

                    <div data-detail-lampiran-wrap hidden class="space-y-3 rounded-xl border border-slate-100 bg-white p-3.5">
                        <p class="text-xs font-medium text-slate-500">Lampiran Tugas</p>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-start gap-3">
                                <span data-detail-lampiran-icon class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-primary-70">
                                    <x-lucide-file-text class="h-5 w-5" />
                                </span>
                                <div class="min-w-0">
                                    <p data-detail-lampiran-nama class="truncate text-sm font-semibold text-slate-800"></p>
                                    <p data-detail-lampiran-meta class="mt-0.5 text-xs text-slate-500"></p>
                                    <p data-detail-lampiran-error hidden class="mt-1 text-xs font-medium text-rose-600"></p>
                                </div>
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <a data-detail-lampiran-buka href="#" target="_blank" rel="noopener" class="hidden inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-xs font-medium text-primary-70 transition hover:bg-slate-50 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60">
                                    <x-lucide-eye class="h-3.5 w-3.5" /> Buka
                                </a>
                                <button type="button"
                                        data-detail-lampiran-download
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-primary-60 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-primary-70 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60 disabled:cursor-not-allowed disabled:opacity-60">
                                    <x-lucide-download class="h-3.5 w-3.5" />
                                    <span data-detail-lampiran-download-text>Download</span>
                                </button>
                            </div>
                        </div>
                        <div data-detail-lampiran-pratinjau class="hidden overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                            <iframe data-detail-lampiran-frame title="Pratinjau lampiran tugas" class="hidden h-[60vh] w-full bg-white"></iframe>
                            <img data-detail-lampiran-image alt="Pratinjau gambar lampiran tugas" class="hidden max-h-[60vh] w-full object-contain">
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-slate-100 bg-white px-5 py-3.5">
                    <button type="button"
                            data-tm-close
                            class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-xs font-medium text-slate-600 transition hover:bg-slate-50 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60">
                        Tutup
                    </button>
                    <button type="button"
                            data-detail-collect
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-60 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-primary-70 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60">
                        <x-lucide-send class="h-3.5 w-3.5" />
                        <span data-detail-collect-text>Kumpulkan Tugas</span>
                    </button>
                </div>
            </div>
        </div>
        <div id="pengumpulan-modal"
             data-tm-modal
             data-open="false"
             aria-hidden="true"
             inert
             class="fixed inset-0 z-[70] flex items-center justify-center overflow-y-auto overscroll-contain bg-slate-900/50 p-4 backdrop-blur-sm lg:left-64"
             role="dialog"
             aria-modal="true"
             aria-labelledby="pengumpulan-modal-title">
            <div data-tm-backdrop class="absolute inset-0"></div>
            <div data-tm-panel class="relative z-10 flex max-h-[90dvh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-[#FAFAFA] shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                    <div class="min-w-0">
                        <h2 id="pengumpulan-modal-title" data-submit-title class="text-base font-semibold text-slate-900">Kumpulkan Tugas</h2>
                        <p data-submit-subtitle class="mt-0.5 truncate text-xs text-slate-500"></p>
                    </div>
                    <button type="button"
                            data-tm-close
                            class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 active:scale-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60"
                            aria-label="Tutup konfirmasi pengumpulan">
                        <x-lucide-x class="h-4.5 w-4.5" />
                    </button>
                </div>
                <form id="pengumpulan-form" method="POST" action="#" class="flex min-h-0 flex-1 flex-col">
                    @csrf
                    <div class="overflow-y-auto px-5 py-4">
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                            <div class="flex items-start gap-2">
                                <x-lucide-info class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                                <p data-submit-info-text class="text-xs leading-relaxed text-amber-800">
                                    Jawaban yang Anda kirim akan disimpan sebagai pengumpulan tugas ini.
                                </p>
                            </div>
                        </div>
                        <label for="pengumpulan-jawaban" class="mt-4 block text-xs font-semibold text-slate-600">Jawaban / Catatan Pengumpulan</label>
                        <textarea id="pengumpulan-jawaban"
                                  name="jawaban"
                                  rows="6"
                                  required
                                  maxlength="5000"
                                  placeholder="Tulis jawaban atau catatan pengumpulan..."
                                  class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 placeholder-slate-400 outline-none transition focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30"></textarea>
                        <div class="mt-1 flex items-center justify-between text-xs text-slate-400">
                            <span data-submit-error role="alert" class="hidden font-medium text-red-600">Jawaban wajib diisi.</span>
                            <span class="ml-auto tabular-nums"><span data-submit-count>0</span>/5000 karakter</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-white px-5 py-3.5">
                        <button type="button"
                                data-tm-close
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-xs font-medium text-slate-600 transition hover:bg-slate-50 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60 disabled:cursor-not-allowed disabled:opacity-60 disabled:active:scale-100">
                            Batal
                        </button>
                        <button type="submit"
                                data-submit-confirm
                                class="inline-flex items-center gap-1.5 rounded-lg bg-primary-60 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-primary-70 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60 disabled:cursor-not-allowed disabled:opacity-60 disabled:active:scale-100">
                            <x-lucide-send class="h-3.5 w-3.5" />
                            <span data-submit-confirm-text>Kumpulkan Tugas</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div id="materi-detail-modal"
             data-tm-modal
             data-open="false"
             aria-hidden="true"
             inert
             class="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto overscroll-contain bg-slate-900/50 p-4 backdrop-blur-sm lg:left-64"
             role="dialog"
             aria-modal="true"
             aria-labelledby="materi-detail-title">
            <div data-tm-backdrop class="absolute inset-0"></div>
            <div data-tm-panel class="relative z-10 flex max-h-[90dvh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Detail Materi</p>
                        <h2 id="materi-detail-title" data-materi-detail-judul class="mt-0.5 text-base font-semibold text-slate-900"></h2>
                        <p data-materi-detail-mapel class="mt-0.5 truncate text-xs text-slate-500"></p>
                    </div>
                    <button type="button"
                            data-tm-close
                            class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 active:scale-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60"
                            aria-label="Tutup detail materi">
                        <x-lucide-x class="h-4.5 w-4.5" />
                    </button>
                </div>
                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    <div data-materi-detail-error hidden class="rounded-xl bg-rose-50 px-3 py-2 text-xs text-rose-700"></div>
                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div><dt class="text-xs text-slate-500">Guru Pengunggah</dt><dd data-materi-detail-guru class="mt-0.5 text-sm font-medium text-slate-800"></dd></div>
                        <div><dt class="text-xs text-slate-500">Kelas</dt><dd data-materi-detail-kelas class="mt-0.5 text-sm font-medium text-slate-800"></dd></div>
                        <div><dt class="text-xs text-slate-500">Tanggal Unggah</dt><dd data-materi-detail-tanggal class="mt-0.5 text-sm font-medium text-slate-800"></dd></div>
                        <div><dt class="text-xs text-slate-500">Terakhir Diperbarui</dt><dd data-materi-detail-diperbarui class="mt-0.5 text-sm font-medium text-slate-800"></dd></div>
                    </dl>
                    <div>
                        <p class="text-xs text-slate-500">Deskripsi</p>
                        <p data-materi-detail-deskripsi class="mt-1 whitespace-pre-line text-sm text-slate-700"></p>
                    </div>
                    <div data-materi-lampiran-wrap hidden class="space-y-3 rounded-xl border border-slate-100 bg-white p-3.5">
                        <p class="text-xs font-medium text-slate-500">Lampiran Materi</p>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-start gap-3">
                                <span data-materi-lampiran-icon class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-primary-70">
                                    <x-lucide-file-text class="h-5 w-5" />
                                </span>
                                <div class="min-w-0">
                                    <p data-materi-lampiran-nama class="truncate text-sm font-semibold text-slate-800"></p>
                                    <p data-materi-lampiran-meta class="mt-0.5 text-xs text-slate-500"></p>
                                </div>
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <a data-materi-lampiran-buka href="#" target="_blank" rel="noopener" class="hidden inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-xs font-medium text-primary-70 transition hover:bg-slate-50 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60">
                                    <x-lucide-eye class="h-3.5 w-3.5" /> Buka
                                </a>
                                <a data-materi-lampiran-unduh href="#" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-primary-60 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-primary-70 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60">
                                    <x-lucide-download class="h-3.5 w-3.5" />
                                    <span>Download</span>
                                </a>
                            </div>
                        </div>
                        <div data-materi-lampiran-pratinjau class="hidden overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                            <iframe data-materi-lampiran-frame title="Pratinjau lampiran materi" class="hidden h-[60vh] w-full bg-white"></iframe>
                            <img data-materi-lampiran-image alt="Pratinjau gambar lampiran materi" class="hidden max-h-[60vh] w-full object-contain">
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end border-t border-slate-100 bg-white px-5 py-3.5">
                    <button type="button" data-tm-close class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-xs font-medium text-slate-600 transition hover:bg-slate-50 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60">Tutup</button>
                </div>
            </div>
        </div>

        <script>
            (() => {
                const detailModal = document.getElementById('detail-tugas-modal');
                const materiModal = document.getElementById('materi-detail-modal');
                const submitModal = document.getElementById('pengumpulan-modal');
                const form = document.getElementById('pengumpulan-form');
                const submitButton = form.querySelector('[data-submit-confirm]');
                const submitText = form.querySelector('[data-submit-confirm-text]');
                const submitClose = form.querySelector('[data-tm-close]');
                const jawaban = document.getElementById('pengumpulan-jawaban');
                const charCount = form.querySelector('[data-submit-count]');
                const inlineError = form.querySelector('[data-submit-error]');
                const detailCollect = detailModal.querySelector('[data-detail-collect]');
                const detailLampiranDownload = detailModal.querySelector('[data-detail-lampiran-download]');
                const detailLampiranDownloadText = detailModal.querySelector('[data-detail-lampiran-download-text]');
                const detailLampiranError = detailModal.querySelector('[data-detail-lampiran-error]');
                let lastFocused = null;
                let detailTrigger = null;
                let submitting = false;

                const setText = (selector, value) => {
                    const element = document.querySelector(selector);
                    if (!element) return;
                    const text = value == null ? '' : String(value);
                    element.textContent = text.trim() !== '' ? text : '-';
                };
                detailLampiranDownload?.addEventListener('click', async () => {
                    const url = detailLampiranDownload.dataset.url;
                    if (!url || detailLampiranDownload.disabled) return;
                    if (detailLampiranError) {
                        detailLampiranError.hidden = true;
                        detailLampiranError.textContent = '';
                    }
                    detailLampiranDownload.disabled = true;
                    if (detailLampiranDownloadText) detailLampiranDownloadText.textContent = 'Mengunduh...';
                    try {
                        const response = await fetch(url, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        if (!response.ok) {
                            throw new Error(response.status === 404 ? 'Lampiran tidak ditemukan atau sudah dihapus.' : 'Lampiran tidak dapat diunduh. Silakan coba lagi.');
                        }
                        const blob = await response.blob();
                        const disposition = response.headers.get('content-disposition') || '';
                        const match = disposition.match(/filename\*?=(?:UTF-8''|")?([^";]+)/i);
                        const fallback = detailLampiranDownload.dataset.nama || 'lampiran-tugas';
                        let filename = fallback;
                        if (match && match[1]) {
                            const raw = match[1].replace(/"/g, '').trim();
                            try { filename = decodeURIComponent(raw); } catch (_) { filename = raw; }
                        }
                        const objectUrl = URL.createObjectURL(blob);
                        const link = document.createElement('a');
                        link.href = objectUrl;
                        link.download = filename || fallback;
                        document.body.appendChild(link);
                        link.click();
                        link.remove();
                        setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
                    } catch (error) {
                        if (detailLampiranError) {
                            detailLampiranError.textContent = error?.message || 'Gagal mengunduh lampiran. Silakan coba lagi.';
                            detailLampiranError.hidden = false;
                        }
                    } finally {
                        detailLampiranDownload.disabled = false;
                        if (detailLampiranDownloadText) detailLampiranDownloadText.textContent = 'Download';
                    }
                });

                const syncBodyLock = () => {
                    document.body.classList.toggle(
                        'overflow-hidden',
                        detailModal.dataset.open === 'true'
                            || submitModal.dataset.open === 'true'
                            || materiModal.dataset.open === 'true',
                    );
                };

                const setOpen = (modal, open) => {
                    modal.dataset.open = open ? 'true' : 'false';
                    modal.setAttribute('aria-hidden', open ? 'false' : 'true');
                    if (open) modal.removeAttribute('inert');
                    else modal.setAttribute('inert', '');
                    syncBodyLock();
                };

                const openModal = (modal, trigger) => {
                    lastFocused = trigger ?? document.activeElement;
                    setOpen(modal, true);
                    const closeButton = modal.querySelector('[data-tm-close]');
                    if (closeButton) closeButton.focus();
                };

                const closeModal = (modal) => {
                    if (modal === submitModal && submitting) return;
                    setOpen(modal, false);
                    if (lastFocused && document.contains(lastFocused)) lastFocused.focus();
                };

                const lampiranIconSvg = (format) => {
                    const f = String(format || '').toLowerCase();
                    if (['jpg','jpeg','png','gif','webp'].includes(f)) return '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>';
                    if (f === 'pdf') return '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>';
                    if (['xls','xlsx'].includes(f)) return '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M8 13h2"/><path d="M14 13h2"/><path d="M8 17h2"/><path d="M14 17h2"/></svg>';
                    if (['ppt','pptx'].includes(f)) return '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M9 13v-2h2a1 1 0 1 1 0 2z"/></svg>';
                    if (['zip','rar'].includes(f)) return '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 22h2a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v18"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 12v-2"/><path d="M10 18v-2"/><path d="M10 7h.01"/></svg>';
                    if (['doc','docx'].includes(f)) return '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>';
                    return '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>';
                };

                const openDetail = async (trigger) => {
                    detailTrigger = trigger;
                    const errorBox = detailModal.querySelector('[data-detail-error]');
                    const deskripsiWrap = detailModal.querySelector('[data-detail-deskripsi-wrap]');
                    const deskripsi = detailModal.querySelector('[data-detail-deskripsi]');
                    const lampiranWrap = detailModal.querySelector('[data-detail-lampiran-wrap]');
                    const lampiranNama = detailModal.querySelector('[data-detail-lampiran-nama]');
                    const lampiranMeta = detailModal.querySelector('[data-detail-lampiran-meta]');
                    const lampiranIcon = detailModal.querySelector('[data-detail-lampiran-icon]');
                    const lampiranDownload = detailModal.querySelector('[data-detail-lampiran-download]');
                    const lampiranDownloadText = detailModal.querySelector('[data-detail-lampiran-download-text]');
                    const lampiranBuka = detailModal.querySelector('[data-detail-lampiran-buka]');
                    const lampiranError = detailModal.querySelector('[data-detail-lampiran-error]');
                    const pratinjauWrap = detailModal.querySelector('[data-detail-lampiran-pratinjau]');
                    const pratinjauFrame = detailModal.querySelector('[data-detail-lampiran-frame]');
                    const pratinjauImage = detailModal.querySelector('[data-detail-lampiran-image]');

                    if (errorBox) errorBox.hidden = true;
                    setText('[data-detail-title]', 'Detail Tugas');
                    setText('[data-detail-subtitle]', 'Memuat detail tugas...');
                    setText('[data-detail-label-tenggat]', '');
                    if (deskripsiWrap) deskripsiWrap.hidden = true;
                    if (deskripsi) deskripsi.textContent = '';
                    if (lampiranWrap) lampiranWrap.hidden = true;
                    if (lampiranError) { lampiranError.hidden = true; lampiranError.textContent = ''; }
                    if (lampiranDownload) { lampiranDownload.disabled = false; delete lampiranDownload.dataset.url; delete lampiranDownload.dataset.nama; }
                    if (lampiranDownloadText) lampiranDownloadText.textContent = 'Download';
                    if (lampiranBuka) { lampiranBuka.href = '#'; lampiranBuka.classList.add('hidden'); }
                    if (pratinjauWrap) pratinjauWrap.classList.add('hidden');
                    if (pratinjauFrame) { pratinjauFrame.src = ''; pratinjauFrame.classList.add('hidden'); }
                    if (pratinjauImage) { pratinjauImage.src = ''; pratinjauImage.classList.add('hidden'); }
                    openModal(detailModal, trigger);

                    try {
                        const response = await fetch(trigger.dataset.detailUrl, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (!response.ok) throw new Error('Detail tugas tidak dapat dimuat.');

                        const { detail } = await response.json();
                        setText('[data-detail-title]', detail.judul);
                        setText('[data-detail-subtitle]', detail.mata_pelajaran);
                        setText('[data-detail-mapel]', detail.mata_pelajaran);
                        setText('[data-detail-guru]', detail.guru);
                        setText('[data-detail-kelas]', detail.kelas);
                        setText('[data-detail-tanggal]', detail.tanggal_diberikan);
                        setText('[data-detail-tenggat]', detail.tenggat);
                        setText('[data-detail-label-tenggat]', detail.label_tenggat ?? '');
                        setText('[data-detail-waktu]', detail.waktu_pengumpulan);
                        setText('[data-detail-nilai]', detail.nilai);
                        setText('[data-detail-umpan]', detail.umpan_balik);

                        const deskripsiText = String(detail.deskripsi ?? '').trim();
                        if (deskripsiWrap && deskripsi) {
                            if (deskripsiText !== '') {
                                deskripsi.textContent = detail.deskripsi;
                                deskripsiWrap.hidden = false;
                            } else {
                                deskripsi.textContent = '';
                                deskripsiWrap.hidden = true;
                            }
                        }

                        const statusBadge = detailModal.querySelector('[data-detail-status]');
                        if (statusBadge) {
                            statusBadge.textContent = detail.status ?? '-';
                            statusBadge.className = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ' + (detail.status_class ?? 'bg-slate-100 text-slate-600');
                        }

                        if (detail.lampiran_url && lampiranWrap) {
                            lampiranWrap.hidden = false;
                            if (lampiranNama) lampiranNama.textContent = detail.lampiran || 'Lampiran tugas';
                            if (lampiranMeta) lampiranMeta.textContent = [detail.lampiran_format, detail.lampiran_ukuran].filter(Boolean).join(' \u00B7 ') || 'File lampiran';
                            if (lampiranIcon) lampiranIcon.innerHTML = lampiranIconSvg(detail.lampiran_format);
                            if (lampiranDownload) {
                                lampiranDownload.dataset.url = detail.lampiran_url;
                                lampiranDownload.dataset.nama = detail.lampiran || 'lampiran-tugas';
                            }
                            if (lampiranBuka) {
                                const previewUrl = detail.lampiran_preview_url || detail.lampiran_url;
                                lampiranBuka.href = previewUrl;
                                lampiranBuka.classList.toggle('hidden', !detail.lampiran_preview_url);
                            }
                            if (detail.lampiran_preview_url && pratinjauWrap) {
                                pratinjauWrap.classList.remove('hidden');
                                const fmt = String(detail.lampiran_format || '').toUpperCase();
                                if (fmt === 'PDF' && pratinjauFrame) {
                                    pratinjauFrame.src = detail.lampiran_preview_url;
                                    pratinjauFrame.classList.remove('hidden');
                                } else if (pratinjauImage) {
                                    pratinjauImage.src = detail.lampiran_preview_url;
                                    pratinjauImage.classList.remove('hidden');
                                }
                            }
                        } else if (lampiranWrap) {
                            lampiranWrap.hidden = true;
                        }

                        detailCollect.dataset.action = detail.action;
                        detailCollect.dataset.judul = detail.judul;
                        detailCollect.dataset.jawaban = detail.jawaban ?? '';
                        detailCollect.dataset.sudah = detail.sudah_dikumpulkan ? '1' : '0';
                        setText('[data-detail-collect-text]', detail.sudah_dikumpulkan ? 'Kumpulkan Ulang' : 'Kumpulkan Tugas');
                    } catch (error) {
                        if (errorBox) {
                            errorBox.textContent = error.message || 'Detail tugas tidak dapat dimuat.';
                            errorBox.hidden = false;
                        }
                        setText('[data-detail-subtitle]', 'Detail tidak dapat dimuat.');
                    }
                };
                const openMateriDetail = async (trigger) => {
                    const errorBox = materiModal.querySelector('[data-materi-detail-error]');
                    const lampiranWrap = materiModal.querySelector('[data-materi-lampiran-wrap]');
                    const lampiranNama = materiModal.querySelector('[data-materi-lampiran-nama]');
                    const lampiranMeta = materiModal.querySelector('[data-materi-lampiran-meta]');
                    const lampiranIcon = materiModal.querySelector('[data-materi-lampiran-icon]');
                    const lampiranBuka = materiModal.querySelector('[data-materi-lampiran-buka]');
                    const lampiranUnduh = materiModal.querySelector('[data-materi-lampiran-unduh]');
                    const pratinjauWrap = materiModal.querySelector('[data-materi-lampiran-pratinjau]');
                    const pratinjauFrame = materiModal.querySelector('[data-materi-lampiran-frame]');
                    const pratinjauImage = materiModal.querySelector('[data-materi-lampiran-image]');

                    if (errorBox) errorBox.hidden = true;
                    setText('[data-materi-detail-judul]', 'Memuat...');
                    setText('[data-materi-detail-mapel]', '');
                    if (lampiranWrap) lampiranWrap.hidden = true;
                    if (lampiranBuka) { lampiranBuka.href = '#'; lampiranBuka.classList.add('hidden'); }
                    if (lampiranUnduh) lampiranUnduh.href = '#';
                    if (pratinjauWrap) pratinjauWrap.classList.add('hidden');
                    if (pratinjauFrame) { pratinjauFrame.src = ''; pratinjauFrame.classList.add('hidden'); }
                    if (pratinjauImage) { pratinjauImage.src = ''; pratinjauImage.classList.add('hidden'); }
                    openModal(materiModal, trigger);

                    try {
                        const response = await fetch(trigger.dataset.detailUrl, {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });

                        if (!response.ok) throw new Error('Detail materi tidak dapat dimuat.');

                        const payload = await response.json();
                        const detail = payload.detail ?? payload;
                        setText('[data-materi-detail-judul]', detail.judul);
                        setText('[data-materi-detail-mapel]', detail.mata_pelajaran);
                        setText('[data-materi-detail-guru]', detail.guru);
                        setText('[data-materi-detail-kelas]', detail.kelas);
                        setText('[data-materi-detail-tanggal]', detail.tanggal);
                        setText('[data-materi-detail-diperbarui]', detail.tanggal_diperbarui);
                        setText('[data-materi-detail-deskripsi]', detail.deskripsi || 'Tidak ada deskripsi.');

                        if (detail.memiliki_file && lampiranWrap) {
                            lampiranWrap.hidden = false;
                            if (lampiranNama) lampiranNama.textContent = detail.nama_file || 'Lampiran materi';
                            if (lampiranMeta) lampiranMeta.textContent = [detail.format, detail.ukuran].filter(Boolean).join(' \u00B7 ') || 'File lampiran';
                            if (lampiranIcon) lampiranIcon.innerHTML = lampiranIconSvg(detail.format);
                            if (lampiranUnduh) lampiranUnduh.href = detail.unduh_url || '#';
                            if (lampiranBuka) {
                                const previewUrl = detail.bisa_dipratinjau ? (detail.preview_url || '') : '';
                                lampiranBuka.href = previewUrl || '#';
                                lampiranBuka.classList.toggle('hidden', !previewUrl);
                            }
                            if (detail.bisa_dipratinjau && pratinjauWrap) {
                                pratinjauWrap.classList.remove('hidden');
                                if (String(detail.format || '').toUpperCase() === 'PDF' && pratinjauFrame) {
                                    pratinjauFrame.src = detail.preview_url || '';
                                    pratinjauFrame.classList.remove('hidden');
                                } else if (pratinjauImage) {
                                    pratinjauImage.src = detail.preview_url || '';
                                    pratinjauImage.classList.remove('hidden');
                                }
                            }
                        } else if (lampiranWrap) {
                            lampiranWrap.hidden = true;
                        }
                    } catch (error) {
                        if (errorBox) {
                            errorBox.textContent = error.message || 'Detail materi tidak dapat dimuat.';
                            errorBox.hidden = false;
                        }
                        setText('[data-materi-detail-judul]', 'Detail tidak dapat dimuat.');
                    }
                };
                const resetSubmit = () => {
                    submitting = false;
                    submitButton.disabled = false;
                    submitClose.disabled = false;
                    jawaban.readOnly = false;
                    inlineError.classList.add('hidden');
                };

                const openSubmit = (source, trigger) => {
                    resetSubmit();
                    const sudah = source.dataset.sudah === '1';
                    const label = sudah ? 'Kumpulkan Ulang' : 'Kumpulkan Tugas';

                    form.action = source.dataset.action;
                    submitButton.dataset.defaultText = label;
                    submitText.textContent = label;
                    setText('[data-submit-title]', label + ' Tugas');
                    setText('[data-submit-subtitle]', source.dataset.judul);
                    setText('[data-submit-info-text]', sudah
                        ? 'Pengumpulan sebelumnya akan diganti dengan jawaban ini. Nilai dan umpan balik Guru tetap tersimpan.'
                        : 'Jawaban yang Anda kirim akan disimpan sebagai pengumpulan tugas ini.');

                    jawaban.value = source.dataset.jawaban ?? '';
                    charCount.textContent = String(jawaban.value.length);
                    const change = window.saskansaChange?.bind(form, { save: '[data-submit-confirm]' });
                    change?.refresh();
                    change?.snapshot();
                    openModal(submitModal, trigger);
                    jawaban.focus();
                };

                // Tooltip untuk teks terpotong (muncul hanya saat terpotong, tidak terklip)
                const tipPop = document.createElement('div');
                tipPop.className = 'tm-truncate-pop';
                tipPop.setAttribute('role', 'tooltip');
                document.body.appendChild(tipPop);
                let tipOwner = null;

                const hideTip = (el) => {
                    if (el && tipOwner !== el) return;
                    tipOwner = null;
                    tipPop.dataset.show = '0';
                };

                const showTip = (el) => {
                    if (!el || el.dataset.truncated !== '1') return;
                    tipOwner = el;
                    tipPop.textContent = el.textContent.trim();
                    tipPop.dataset.show = '1';
                    const r = el.getBoundingClientRect();
                    const pad = 8;
                    tipPop.style.left = '0px';
                    tipPop.style.top = '0px';
                    const tw = tipPop.offsetWidth;
                    const th = tipPop.offsetHeight;
                    let left = r.left;
                    if (left + tw > window.innerWidth - pad) left = window.innerWidth - tw - pad;
                    if (left < pad) left = pad;
                    let top = r.bottom + 6;
                    if (top + th > window.innerHeight - pad) top = r.top - th - 6;
                    if (top < pad) top = pad;
                    tipPop.style.left = left + 'px';
                    tipPop.style.top = top + 'px';
                };

                const refreshTruncTips = () => {
                    document.querySelectorAll('[data-truncate-tip]').forEach((el) => {
                        const truncated = el.scrollHeight > el.clientHeight + 1 || el.scrollWidth > el.clientWidth + 1;
                        el.dataset.truncated = truncated ? '1' : '0';
                        if (!truncated) hideTip(el);
                    });
                };

                document.querySelectorAll('[data-truncate-tip]').forEach((el) => {
                    el.setAttribute('tabindex', '0');
                    el.addEventListener('mouseenter', () => showTip(el));
                    el.addEventListener('mouseleave', () => hideTip(el));
                    el.addEventListener('focus', () => showTip(el));
                    el.addEventListener('blur', () => hideTip(el));
                    el.addEventListener('click', (e) => {
                        if (el.dataset.truncated !== '1') return;
                        e.stopPropagation();
                        if (tipOwner === el && tipPop.dataset.show === '1') hideTip(el);
                        else showTip(el);
                    });
                });
                document.addEventListener('click', (e) => {
                    if (tipOwner && !tipOwner.contains(e.target)) hideTip(tipOwner);
                });
                window.addEventListener('resize', refreshTruncTips, { passive: true });
                window.addEventListener('scroll', () => hideTip(tipOwner), { passive: true, capture: true });
                requestAnimationFrame(refreshTruncTips);

                document.querySelectorAll('[data-detail-trigger]').forEach((button) => {
                    button.addEventListener('click', () => openDetail(button));
                });

                document.querySelectorAll('[data-detail-materi-trigger]').forEach((button) => {
                    button.addEventListener('click', () => openMateriDetail(button));
                });

                document.querySelectorAll('[data-submit-trigger]').forEach((button) => {
                    button.addEventListener('click', () => openSubmit(button, button));
                });

                detailCollect.addEventListener('click', () => {
                    setOpen(detailModal, false);
                    openSubmit(detailCollect, detailTrigger ?? detailCollect);
                });

                document.querySelectorAll('[data-tm-close]').forEach((button) => {
                    button.addEventListener('click', () => closeModal(button.closest('[data-tm-modal]')));
                });

                document.querySelectorAll('[data-tm-modal]').forEach((modal) => {
                    modal.querySelector('[data-tm-backdrop]').addEventListener('click', () => closeModal(modal));
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key !== 'Escape') return;
                    if (submitModal.dataset.open === 'true') closeModal(submitModal);
                    else if (materiModal.dataset.open === 'true') closeModal(materiModal);
                    else if (detailModal.dataset.open === 'true') closeModal(detailModal);
                });

                jawaban.addEventListener('input', () => {
                    charCount.textContent = String(jawaban.value.length);
                    inlineError.classList.add('hidden');
                });

                form.addEventListener('submit', (event) => {
                    if (submitting) {
                        event.preventDefault();
                        return;
                    }

                    if (jawaban.value.trim() === '') {
                        event.preventDefault();
                        inlineError.classList.remove('hidden');
                        jawaban.focus();
                        return;
                    }

                    submitting = true;
                    submitButton.disabled = true;
                    submitClose.disabled = true;
                    jawaban.readOnly = true;
                    submitText.textContent = 'Mengirim…';
                });
            })();
        </script>
    </div>
@endsection
