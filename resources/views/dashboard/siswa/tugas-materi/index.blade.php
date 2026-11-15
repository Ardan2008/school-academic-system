@extends('layouts.dashboard')

@section('title', 'Tugas & Materi')
@section('subtitle', 'Dashboard Siswa')

@section('content')
    <div id="tugas-materi-page">

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

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-4">
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
            <div class="mt-5 flex max-w-full items-center gap-1 overflow-x-auto rounded-xl border border-slate-100 bg-white p-1 shadow-sm sm:mt-6">
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
                <div class="mt-4 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60 sm:mt-5">
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
                                                <p class="font-medium text-slate-900">{{ $item->judul }}</p>
                                                @if (filled($item->deskripsi))
                                                    <x-ui.expandable-text :text="$item->deskripsi" :lines="2" class="mt-0.5 max-w-md text-xs text-slate-500" />
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
                                                        'text-slate-400' => $st['kode'] === 'selesai',
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
                                                @if ($pengumpulan?->nilai !== null)
                                                    <p class="mt-1 text-xs text-slate-500">Nilai: <span class="font-semibold text-slate-700">{{ rtrim(rtrim(number_format((float) $pengumpulan->nilai, 2, ',', '.'), '0'), ',') }}</span></p>
                                                @endif
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
                                        <div class="min-w-0">
                                            <p class="line-clamp-2 font-medium text-slate-900" title="{{ $item->judul }}">{{ $item->judul }}</p>
                                            <p class="mt-0.5 truncate text-xs text-slate-500" title="{{ $item->mataPelajaran?->nama ?? '—' }}">{{ $item->mataPelajaran?->nama ?? '—' }}</p>
                                        </div>
                                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $st['class'] }}">
                                            <x-dynamic-component :component="$st['icon']" class="h-3.5 w-3.5" />
                                            {{ $st['label'] }}
                                        </span>
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
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                {{-- Daftar materi --}}
                <div class="mt-4 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60 sm:mt-5">
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
                                                            <x-ui.expandable-text :text="$item->deskripsi" :lines="2" class="mt-0.5 max-w-md text-xs text-slate-500" />
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
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        @endif
    </div>
@endsection
