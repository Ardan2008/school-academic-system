@extends('layouts.dashboard')

@section('title', 'Riwayat Aktivitas')
@section('subtitle', 'Dashboard Siswa')

@section('content')
    @php
        $badgeKategori = [
            'tugas' => 'bg-blue-50 text-blue-700 ring-blue-200',
            'nilai' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'jadwal' => 'bg-violet-50 text-violet-700 ring-violet-200',
            'pengumuman' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'materi' => 'bg-rose-50 text-rose-700 ring-rose-200',
        ];

        $ikonKategori = [
            'tugas' => 'clipboard-list',
            'nilai' => 'award',
            'jadwal' => 'calendar-days',
            'pengumuman' => 'megaphone',
            'materi' => 'book-open',
        ];

        $urlKategori = fn (string $nilai): string => route('dashboard.siswa.riwayat-aktivitas.index', array_filter([
            'kategori' => $nilai,
            'search' => $search !== '' ? $search : null,
            'dari' => $dari,
            'sampai' => $sampai,
        ]));
    @endphp

    <div id="riwayat-aktivitas-page">

        {{-- Identitas rombel siswa --}}
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            @if (filled($siswa->kelas))
                <span class="inline-flex max-w-full items-center gap-2 rounded-xl border border-blue-100 bg-blue-50 px-3.5 py-2 text-xs font-medium text-primary-70">
                    <x-lucide-school class="h-4 w-4 shrink-0" />
                    <span class="truncate" title="Kelas {{ $siswa->kelas }}@if (filled($siswa->jurusan)) · {{ $siswa->jurusan }}@endif">
                        Kelas {{ $siswa->kelas }}@if (filled($siswa->jurusan)) · {{ $siswa->jurusan }}@endif
                    </span>
                </span>
            @endif

            <span class="inline-flex w-fit items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-600">
                <x-lucide-activity class="h-3.5 w-3.5" />
                {{ $totalSemua }} aktivitas tercatat
            </span>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                        <x-lucide-activity class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-slate-900 sm:text-base">Riwayat Aktivitas</h2>
                        <p class="mt-0.5 text-xs text-slate-400">
                            Aktivitas akademik akun dan kelas Anda &middot; diurutkan dari yang terbaru
                        </p>
                    </div>
                </div>
            </div>

            {{-- Filter kategori --}}
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50/40 px-4 py-3 sm:px-5">
                <span class="mr-1 hidden text-xs font-semibold uppercase tracking-wide text-slate-400 sm:inline">Kategori</span>

                <a href="{{ $urlKategori('') }}"
                   @class([
                        'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60',
                        'bg-primary-60 text-white shadow-sm shadow-primary-60/25' => $kategori === '',
                        'border border-slate-200 bg-white text-slate-600 hover:border-primary-60 hover:bg-blue-50 hover:text-primary-70' => $kategori !== '',
                   ])>
                    Semua
                </a>

                @foreach ($kategoriOptions as $nilai => $label)
                    <a href="{{ $urlKategori($nilai) }}"
                       @class([
                            'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60',
                            'bg-primary-60 text-white shadow-sm shadow-primary-60/25' => $kategori === $nilai,
                            'border border-slate-200 bg-white text-slate-600 hover:border-primary-60 hover:bg-blue-50 hover:text-primary-70' => $kategori !== $nilai,
                       ])>
                        <x-dynamic-component :component="'lucide-'.$ikonKategori[$nilai]" class="h-3.5 w-3.5" />
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            {{-- Pencarian dan rentang tanggal --}}
            <form method="GET" action="{{ route('dashboard.siswa.riwayat-aktivitas.index') }}"
                  class="grid grid-cols-1 gap-2.5 border-b border-slate-100 bg-slate-50/40 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-12">
                @if ($kategori !== '')
                    <input type="hidden" name="kategori" value="{{ $kategori }}">
                @endif

                <div class="relative lg:col-span-5">
                    <label for="search" class="sr-only">Cari aktivitas</label>
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                        <x-lucide-search class="h-4 w-4" />
                    </span>
                    <input
                        id="search"
                        type="search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Cari judul atau deskripsi aktivitas…"
                        class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-9 pr-4 text-sm text-slate-800 placeholder-slate-400 shadow-xs transition focus:border-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-50/30"
                    />
                </div>

                <div class="lg:col-span-5">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="dari" class="sr-only">Dari tanggal</label>
                            <input id="dari" type="date" name="dari" value="{{ $dari }}"
                                   title="Dari tanggal"
                                   class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-xs transition focus:border-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-50/30" />
                        </div>
                        <div>
                            <label for="sampai" class="sr-only">Sampai tanggal</label>
                            <input id="sampai" type="date" name="sampai" value="{{ $sampai }}"
                                   title="Sampai tanggal"
                                   class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-xs transition focus:border-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-50/30" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-2">
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-primary-60 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-70">
                        <x-lucide-filter class="h-4 w-4" />
                        Filter
                    </button>

                    @if ($adaFilter)
                        <a href="{{ route('dashboard.siswa.riwayat-aktivitas.index') }}"
                           title="Reset semua filter"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-600 shadow-xs transition hover:border-slate-300 hover:bg-slate-50">
                            <x-lucide-rotate-ccw class="h-4 w-4" />
                            Reset
                        </a>
                    @endif
                </div>
            </form>

            @if ($aktivitas->isEmpty())
                <div class="flex flex-col items-center gap-3 px-6 py-16 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <x-lucide-activity class="h-7 w-7" />
                    </span>
                    <p class="text-sm font-medium text-slate-500">
                        @if ($adaFilter)
                            Tidak ada aktivitas yang cocok dengan filter saat ini.
                        @else
                            Belum ada aktivitas akademik tercatat.
                        @endif
                    </p>
                    @if ($adaFilter)
                        <a href="{{ route('dashboard.siswa.riwayat-aktivitas.index') }}"
                           class="text-xs text-primary-60 underline underline-offset-2 hover:text-primary-70">
                            Hapus semua filter
                        </a>
                    @else
                        <p class="max-w-md text-sm leading-relaxed text-slate-400">
                            Aktivitas tugas, nilai, jadwal, pengumuman, dan materi akan muncul di sini setelah tersedia untuk akun dan kelas Anda.
                        </p>
                    @endif
                </div>
            @else
                {{-- Tabel untuk layar sedang/besar --}}
                <div class="hidden overflow-x-auto sm:block">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/60 text-xs font-semibold uppercase tracking-wider text-slate-400">
                                <th class="w-48 px-5 py-3.5">Waktu Kejadian</th>
                                <th class="px-5 py-3.5">Aktivitas</th>
                                <th class="w-40 px-5 py-3.5">Kategori</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($aktivitas as $item)
                                @php
                                    $badge = $badgeKategori[$item['kategori']] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
                                    $ikon = $ikonKategori[$item['kategori']] ?? 'activity';
                                @endphp
                                <tr class="transition hover:bg-slate-50/60">
                                    <td class="whitespace-nowrap px-5 py-3.5 align-top text-xs text-slate-500">
                                        <span class="inline-flex items-center gap-1.5">
                                            <x-lucide-clock class="h-3.5 w-3.5 shrink-0" />
                                            {{ \App\Support\Waktu::lengkap($item['waktu']) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 align-top">
                                        <p class="font-medium leading-snug text-slate-800">{{ $item['judul'] }}</p>
                                        <x-ui.expandable-text :text="$item['deskripsi']" :lines="2" class="mt-1 text-xs leading-relaxed text-slate-500" />
                                    </td>
                                    <td class="px-5 py-3.5 align-top">
                                        <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $badge }}">
                                            <x-dynamic-component :component="'lucide-'.$ikon" class="h-3.5 w-3.5" />
                                            {{ $kategoriOptions[$item['kategori']] ?? 'Aktivitas' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Kartu untuk layar kecil --}}
                <div class="divide-y divide-slate-100 sm:hidden">
                    @foreach ($aktivitas as $item)
                        @php
                            $badge = $badgeKategori[$item['kategori']] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
                            $ikon = $ikonKategori[$item['kategori']] ?? 'activity';
                        @endphp
                        <div class="flex items-start gap-3 p-4">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-10 text-primary-70">
                                <x-dynamic-component :component="'lucide-'.$ikon" class="h-4 w-4" />
                            </span>
                            <div class="min-w-0 flex-1 space-y-1.5">
                                <p class="text-sm font-medium leading-snug text-slate-800">{{ $item['judul'] }}</p>
                                <x-ui.expandable-text :text="$item['deskripsi']" :lines="2" class="text-xs leading-relaxed text-slate-500" />
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 pt-0.5">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $badge }}">
                                        <x-dynamic-component :component="'lucide-'.$ikon" class="h-3 w-3" />
                                        {{ $kategoriOptions[$item['kategori']] ?? 'Aktivitas' }}
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 text-[11px] text-slate-400">
                                        <x-lucide-clock class="h-3.5 w-3.5 shrink-0" />
                                        {{ \App\Support\Waktu::lengkap($item['waktu']) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="border-t border-slate-100 px-4 py-3 sm:px-5">
                    <x-ui.server-pagination :paginator="$aktivitas" label="aktivitas" />
                </div>
            @endif
        </div>
    </div>
@endsection
