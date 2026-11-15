@extends('layouts.dashboard')

@section('title', 'Tugas & Materi')
@section('subtitle', 'Dashboard Guru')

@section('content')
    <div id="tugas-materi-guru-page">

        @if (session('success'))
            <div class="mb-5 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                <x-lucide-badge-check class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <p class="text-sm font-medium text-emerald-800">{{ session('success') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-5 flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4">
                <x-lucide-triangle-alert class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
                <p class="text-sm font-medium text-red-800">{{ $errors->first() }}</p>
            </div>
        @endif

        @php
            $statTone = [
                'tugas' => ['bg-blue-100 text-primary-60', '#1E86FF'],
                'materi' => ['bg-violet-100 text-violet-600', '#7C3AED'],
                'pengumpulan' => ['bg-emerald-100 text-emerald-600', '#059669'],
                'belum_dinilai' => ['bg-amber-100 text-amber-600', '#D97706'],
            ];

            $tabUrl = function (string $tujuan): string {
                $query = request()->query();
                unset($query['edit'], $query['tugas_page'], $query['materi_page']);
                $query['tab'] = $tujuan;

                return route('dashboard.guru.tugas-materi.index', $query);
            };

            $editUrl = fn (string $jenis, int $id): string => route('dashboard.guru.tugas-materi.index', array_merge(
                request()->query(),
                ['tab' => $jenis, 'edit' => $id]
            ));

            $formTugas = $editTugas;
            $formMateri = $editMateri;
            $tugasRombel = old('rombel', $formTugas ? $formTugas->kelas.'|'.($formTugas->jurusan ?? '') : '');
            $materiRombel = old('rombel', $formMateri ? $formMateri->kelas.'|'.($formMateri->jurusan ?? '') : '');
            $mapelTugas = old('mata_pelajaran_id', $formTugas?->mata_pelajaran_id ?? ($mapel->count() === 1 ? $mapel->first()->id : ''));
            $mapelMateri = old('mata_pelajaran_id', $formMateri?->mata_pelajaran_id ?? ($mapel->count() === 1 ? $mapel->first()->id : ''));
            $bisaKelola = $mapel->isNotEmpty() && $pasangan->isNotEmpty();
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

        @if ($mapel->isEmpty())
            <div class="mt-5 rounded-2xl border border-amber-200/70 bg-amber-50/60 p-4 shadow-sm sm:p-5">
                <div class="flex items-start gap-3">
                    <x-lucide-alert-circle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                    <div>
                        <h3 class="text-sm font-semibold text-amber-900">Mata Pelajaran Belum Ditugaskan</h3>
                        <p class="mt-0.5 text-xs leading-relaxed text-amber-700">
                            Akun Anda belum memiliki mata pelajaran. Hubungi administrator sebelum membagikan tugas atau materi.
                        </p>
                    </div>
                </div>
            </div>
        @elseif ($pasangan->isEmpty())
            <div class="mt-5 rounded-2xl border border-amber-200/70 bg-amber-50/60 p-4 shadow-sm sm:p-5">
                <div class="flex items-start gap-3">
                    <x-lucide-alert-circle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                    <div>
                        <h3 class="text-sm font-semibold text-amber-900">Belum Ada Kelas yang Diampu</h3>
                        <p class="mt-0.5 text-xs leading-relaxed text-amber-700">
                            Belum ada jadwal mengajar atau siswa aktif pada mata pelajaran Anda. Hubungi administrator untuk melengkapi jadwal.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <div class="mt-5 flex max-w-full items-center gap-1 overflow-x-auto rounded-xl border border-slate-100 bg-white p-1 shadow-sm sm:mt-6">
            <a href="{{ $tabUrl('tugas') }}"
               @class([
                    'inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition',
                    'bg-primary-60 text-white shadow-sm shadow-primary-60/25' => $tab === 'tugas',
                    'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => $tab !== 'tugas',
               ])>
                <x-lucide-clipboard-list class="h-4 w-4" />
                Tugas
                <span @class([
                    'rounded-full px-2 py-0.5 text-xs font-semibold',
                    'bg-white/20 text-white' => $tab === 'tugas',
                    'bg-slate-100 text-slate-500' => $tab !== 'tugas',
                ])>{{ $tugas->total() }}</span>
            </a>

            <a href="{{ $tabUrl('materi') }}"
               @class([
                    'inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition',
                    'bg-primary-60 text-white shadow-sm shadow-primary-60/25' => $tab === 'materi',
                    'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => $tab !== 'materi',
               ])>
                <x-lucide-book-open class="h-4 w-4" />
                Materi
                <span @class([
                    'rounded-full px-2 py-0.5 text-xs font-semibold',
                    'bg-white/20 text-white' => $tab === 'materi',
                    'bg-slate-100 text-slate-500' => $tab !== 'materi',
                ])>{{ $materi->total() }}</span>
            </a>
        </div>

        <form method="GET" action="{{ route('dashboard.guru.tugas-materi.index') }}"
              class="mt-4 rounded-2xl border border-slate-100 bg-white p-3 shadow-sm sm:p-4">
            <input type="hidden" name="tab" value="{{ $tab }}">

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <div class="relative min-w-0 flex-1">
                    <label for="tugas-materi-search" class="sr-only">Cari tugas atau materi</label>
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                        <x-lucide-search class="h-4 w-4" />
                    </span>
                    <input id="tugas-materi-search" type="search" name="q" value="{{ $filters['q'] }}"
                           placeholder="Cari judul atau deskripsi…"
                           class="h-10 w-full rounded-xl border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm text-slate-800 placeholder-slate-400 transition focus:border-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-50/30">
                </div>

                <select name="mapel_id" aria-label="Filter mata pelajaran"
                        class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">
                    <option value="">Semua Mapel</option>
                    @foreach ($mapel as $m)
                        <option value="{{ $m->id }}" @selected((int) $filters['mapel_id'] === (int) $m->id)>
                            {{ $m->nama }}
                        </option>
                    @endforeach
                </select>

                <select name="rombel" aria-label="Filter kelas"
                        class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">
                    <option value="">Semua Kelas</option>
                    @foreach ($rombelOptions as $opsi)
                        <option value="{{ $opsi['value'] }}" @selected(($filters['rombel']['kelas'] ?? null) === explode('|', $opsi['value'])[0] && ($filters['rombel']['jurusan'] ?? '') === (explode('|', $opsi['value'])[1] ?? ''))>
                            {{ $opsi['label'] }}
                        </option>
                    @endforeach
                </select>

                @if ($tab === 'tugas')
                    <select name="status" aria-label="Filter status tugas"
                            class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">
                        <option value="">Semua Status</option>
                        <option value="aktif" @selected($filters['status'] === 'aktif')>Aktif</option>
                        <option value="lewat" @selected($filters['status'] === 'lewat')>Lewat Tenggat</option>
                    </select>
                @endif

                <button type="submit"
                        class="inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-primary-60 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-70">
                    <x-lucide-search class="h-4 w-4" />
                    Cari
                </button>

                @if ($filters['q'] !== '' || $filters['mapel_id'] !== null || $filters['rombel'] !== null || $filters['status'] !== null)
                    <a href="{{ route('dashboard.guru.tugas-materi.index', ['tab' => $tab]) }}"
                       class="inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                        <x-lucide-rotate-ccw class="h-4 w-4" />
                        Reset
                    </a>
                @endif
            </div>
        </form>

        @if ($tab === 'tugas')
            <details @if ($formTugas || ($errors->any() && $tab === 'tugas')) open @endif class="group mt-4 rounded-2xl border border-slate-100 bg-white shadow-sm">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3.5 sm:px-5">
                    <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-900">
                        <x-lucide-plus-circle class="h-4.5 w-4.5 text-primary-60" />
                        {{ $formTugas ? 'Edit Tugas' : 'Tambah Tugas' }}
                    </span>
                    <x-lucide-chevron-down class="h-4 w-4 text-slate-400 transition group-open:rotate-180" />
                </summary>

                <form method="POST"
                      action="{{ $formTugas ? route('dashboard.guru.tugas-materi.tugas.update', $formTugas) : route('dashboard.guru.tugas-materi.tugas.store') }}"
                      class="grid gap-3 border-t border-slate-100 px-4 py-4 sm:grid-cols-2 sm:px-5">
                    @csrf
                    @if ($formTugas)
                        @method('PUT')
                    @endif

                    <div>
                        <label for="tugas-mapel" class="mb-1 block text-xs font-medium text-slate-600">Mata Pelajaran</label>
                        <select id="tugas-mapel" name="mata_pelajaran_id" required @disabled(! $bisaKelola)
                                class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30 disabled:bg-slate-50 disabled:text-slate-400">
                            <option value="">Pilih mapel…</option>
                            @foreach ($mapel as $m)
                                <option value="{{ $m->id }}" @selected((string) $mapelTugas === (string) $m->id)>
                                    {{ $m->nama }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="tugas-rombel" class="mb-1 block text-xs font-medium text-slate-600">Kelas</label>
                        <select id="tugas-rombel" name="rombel" required @disabled(! $bisaKelola)
                                class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30 disabled:bg-slate-50 disabled:text-slate-400">
                            <option value="">Pilih kelas…</option>
                            @foreach ($pasangan as $item)
                                <option value="{{ $item['kelas'].'|'.$item['jurusan'] }}"
                                        data-mapel="{{ $item['mata_pelajaran_id'] }}"
                                        @selected($tugasRombel === $item['kelas'].'|'.$item['jurusan'])>
                                    Kelas {{ $item['kelas'] }}@if ($item['jurusan'] !== '') · {{ $item['jurusan'] }}@endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="tugas-judul" class="mb-1 block text-xs font-medium text-slate-600">Judul Tugas</label>
                        <input id="tugas-judul" type="text" name="judul" required maxlength="255"
                               value="{{ old('judul', $formTugas?->judul) }}"
                               class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="tugas-deskripsi" class="mb-1 block text-xs font-medium text-slate-600">Deskripsi</label>
                        <textarea id="tugas-deskripsi" name="deskripsi" rows="3" maxlength="5000"
                                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">{{ old('deskripsi', $formTugas?->deskripsi) }}</textarea>
                    </div>

                    <div>
                        <label for="tugas-deadline" class="mb-1 block text-xs font-medium text-slate-600">Tenggat</label>
                        <input id="tugas-deadline" type="datetime-local" name="deadline"
                               value="{{ old('deadline', $formTugas?->deadline?->format('Y-m-d\TH:i')) }}"
                               class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">
                        <p class="mt-1 text-[11px] text-slate-400">Kosongkan bila tugas tidak memiliki tenggat.</p>
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit" @disabled(! $bisaKelola)
                                class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-primary-60 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-70 disabled:cursor-not-allowed disabled:bg-slate-300">
                            <x-lucide-save class="h-4 w-4" />
                            {{ $formTugas ? 'Simpan Perubahan' : 'Tambah Tugas' }}
                        </button>
                        @if ($formTugas)
                            <a href="{{ route('dashboard.guru.tugas-materi.index', ['tab' => 'tugas']) }}"
                               class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                                Batal
                            </a>
                        @endif
                    </div>
                </form>
            </details>

            <div class="mt-4 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60">
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <th class="w-14 px-4 py-3.5 text-center font-medium">No</th>
                                <th class="px-4 py-3.5 font-medium">Tugas</th>
                                <th class="px-4 py-3.5 font-medium">Mata Pelajaran</th>
                                <th class="px-4 py-3.5 font-medium">Kelas</th>
                                <th class="w-40 px-4 py-3.5 font-medium">Tenggat</th>
                                <th class="w-36 px-4 py-3.5 font-medium">Pengumpulan</th>
                                <th class="w-44 px-4 py-3.5 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-800">
                            @forelse ($tugas as $item)
                                <tr class="transition hover:bg-blue-50/40">
                                    <td class="px-4 py-3.5 text-center text-xs text-slate-500">{{ ($tugas->firstItem() ?? 1) + $loop->index }}</td>
                                    <td class="px-4 py-3.5">
                                        <p class="font-medium text-slate-900">{{ $item->judul }}</p>
                                        @if (filled($item->deskripsi))
                                            <p class="mt-0.5 line-clamp-2 max-w-md text-xs text-slate-500">{{ $item->deskripsi }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-10 px-2.5 py-0.5 text-xs font-medium text-primary-70">
                                            {{ $item->mataPelajaran?->nama ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-slate-600">
                                        {{ $item->kelas }}@if (filled($item->jurusan)) · {{ $item->jurusan }}@endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        @if ($item->deadline !== null)
                                            <p class="tabular-nums text-slate-700">{{ \App\Support\Waktu::tanggalJam($item->deadline) }}</p>
                                            <p @class([
                                                'mt-0.5 text-xs font-medium',
                                                'text-rose-600' => $item->sudahLewatDeadline(),
                                                'text-slate-400' => ! $item->sudahLewatDeadline(),
                                            ])>{{ $item->labelDeadline() }}</p>
                                        @else
                                            <span class="text-xs text-slate-400">Tanpa tenggat</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <p class="text-sm font-medium text-slate-800">{{ $item->pengumpulan_count }} siswa</p>
                                        <p class="mt-0.5 text-xs text-slate-500">{{ $item->pengumpulan_dinilai_count }} dinilai</p>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a href="{{ route('dashboard.guru.tugas-materi.pengumpulan', $item) }}"
                                               class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-primary-60 transition hover:bg-blue-50">
                                                <x-lucide-inbox class="h-3.5 w-3.5" />
                                                Kumpulan
                                            </a>
                                            <a href="{{ $editUrl('tugas', $item->id) }}"
                                               class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                                                <x-lucide-pencil class="h-3.5 w-3.5" />
                                                Edit
                                            </a>
                                            <form method="POST" action="{{ route('dashboard.guru.tugas-materi.tugas.destroy', $item) }}"
                                                  onsubmit="return confirm('Hapus tugas ini beserta pengumpulannya?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-medium text-rose-600 transition hover:bg-rose-50">
                                                    <x-lucide-trash-2 class="h-3.5 w-3.5" />
                                                    Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-12 text-center">
                                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-10 text-primary-70">
                                            <x-lucide-clipboard-list class="h-6 w-6" />
                                        </span>
                                        <h3 class="mt-3 text-sm font-semibold text-slate-900">Belum Ada Tugas</h3>
                                        <p class="mt-1 text-sm text-slate-500">Tugas yang Anda bagikan akan muncul di sini.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-slate-100 md:hidden">
                    @forelse ($tugas as $item)
                        <div class="px-4 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium text-slate-900">{{ $item->judul }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $item->mataPelajaran?->nama ?? '—' }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                    {{ $item->kelas }}@if (filled($item->jurusan)) · {{ $item->jurusan }}@endif
                                </span>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                                <span>{{ $item->deadline ? \App\Support\Waktu::tanggalJam($item->deadline) : 'Tanpa tenggat' }}</span>
                                <span>{{ $item->pengumpulan_count }} pengumpulan · {{ $item->pengumpulan_dinilai_count }} dinilai</span>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <a href="{{ route('dashboard.guru.tugas-materi.pengumpulan', $item) }}"
                                   class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-primary-60 transition hover:bg-blue-50">
                                    <x-lucide-inbox class="h-3.5 w-3.5" /> Kumpulan
                                </a>
                                <a href="{{ $editUrl('tugas', $item->id) }}"
                                   class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                                    <x-lucide-pencil class="h-3.5 w-3.5" /> Edit
                                </a>
                                <form method="POST" action="{{ route('dashboard.guru.tugas-materi.tugas.destroy', $item) }}"
                                      onsubmit="return confirm('Hapus tugas ini beserta pengumpulannya?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-medium text-rose-600 transition hover:bg-rose-50">
                                        <x-lucide-trash-2 class="h-3.5 w-3.5" /> Hapus
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-12 text-center">
                            <h3 class="text-sm font-semibold text-slate-900">Belum Ada Tugas</h3>
                            <p class="mt-1 text-sm text-slate-500">Tugas yang Anda bagikan akan muncul di sini.</p>
                        </div>
                    @endforelse
                </div>

                <div class="border-t border-slate-100 px-4 py-3">
                    <x-ui.server-pagination :paginator="$tugas" label="tugas" />
                </div>
            </div>
        @else
            <details @if ($formMateri || ($errors->any() && $tab === 'materi')) open @endif class="group mt-4 rounded-2xl border border-slate-100 bg-white shadow-sm">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3.5 sm:px-5">
                    <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-900">
                        <x-lucide-plus-circle class="h-4.5 w-4.5 text-primary-60" />
                        {{ $formMateri ? 'Edit Materi' : 'Tambah Materi' }}
                    </span>
                    <x-lucide-chevron-down class="h-4 w-4 text-slate-400 transition group-open:rotate-180" />
                </summary>

                <form method="POST"
                      action="{{ $formMateri ? route('dashboard.guru.tugas-materi.materi.update', $formMateri) : route('dashboard.guru.tugas-materi.materi.store') }}"
                      class="grid gap-3 border-t border-slate-100 px-4 py-4 sm:grid-cols-2 sm:px-5">
                    @csrf
                    @if ($formMateri)
                        @method('PUT')
                    @endif

                    <div>
                        <label for="materi-mapel" class="mb-1 block text-xs font-medium text-slate-600">Mata Pelajaran</label>
                        <select id="materi-mapel" name="mata_pelajaran_id" required @disabled(! $bisaKelola)
                                class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30 disabled:bg-slate-50 disabled:text-slate-400">
                            <option value="">Pilih mapel…</option>
                            @foreach ($mapel as $m)
                                <option value="{{ $m->id }}" @selected((string) $mapelMateri === (string) $m->id)>
                                    {{ $m->nama }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="materi-rombel" class="mb-1 block text-xs font-medium text-slate-600">Kelas</label>
                        <select id="materi-rombel" name="rombel" required @disabled(! $bisaKelola)
                                class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30 disabled:bg-slate-50 disabled:text-slate-400">
                            <option value="">Pilih kelas…</option>
                            @foreach ($pasangan as $item)
                                <option value="{{ $item['kelas'].'|'.$item['jurusan'] }}"
                                        data-mapel="{{ $item['mata_pelajaran_id'] }}"
                                        @selected($materiRombel === $item['kelas'].'|'.$item['jurusan'])>
                                    Kelas {{ $item['kelas'] }}@if ($item['jurusan'] !== '') · {{ $item['jurusan'] }}@endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="materi-judul" class="mb-1 block text-xs font-medium text-slate-600">Judul Materi</label>
                        <input id="materi-judul" type="text" name="judul" required maxlength="255"
                               value="{{ old('judul', $formMateri?->judul) }}"
                               class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="materi-deskripsi" class="mb-1 block text-xs font-medium text-slate-600">Deskripsi</label>
                        <textarea id="materi-deskripsi" name="deskripsi" rows="3" maxlength="5000"
                                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">{{ old('deskripsi', $formMateri?->deskripsi) }}</textarea>
                    </div>

                    <div>
                        <label for="materi-tanggal" class="mb-1 block text-xs font-medium text-slate-600">Tanggal</label>
                        <input id="materi-tanggal" type="date" name="tanggal"
                               value="{{ old('tanggal', $formMateri?->tanggal?->format('Y-m-d') ?? now()->toDateString()) }}"
                               class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit" @disabled(! $bisaKelola)
                                class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-primary-60 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-70 disabled:cursor-not-allowed disabled:bg-slate-300">
                            <x-lucide-save class="h-4 w-4" />
                            {{ $formMateri ? 'Simpan Perubahan' : 'Tambah Materi' }}
                        </button>
                        @if ($formMateri)
                            <a href="{{ route('dashboard.guru.tugas-materi.index', ['tab' => 'materi']) }}"
                               class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                                Batal
                            </a>
                        @endif
                    </div>
                </form>
            </details>

            <div class="mt-4 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60">
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <th class="w-14 px-4 py-3.5 text-center font-medium">No</th>
                                <th class="px-4 py-3.5 font-medium">Materi</th>
                                <th class="px-4 py-3.5 font-medium">Mata Pelajaran</th>
                                <th class="px-4 py-3.5 font-medium">Kelas</th>
                                <th class="w-36 px-4 py-3.5 font-medium">Tanggal</th>
                                <th class="w-44 px-4 py-3.5 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-800">
                            @forelse ($materi as $item)
                                <tr class="transition hover:bg-violet-50/30">
                                    <td class="px-4 py-3.5 text-center text-xs text-slate-500">{{ ($materi->firstItem() ?? 1) + $loop->index }}</td>
                                    <td class="px-4 py-3.5">
                                        <p class="font-medium text-slate-900">{{ $item->judul }}</p>
                                        @if (filled($item->deskripsi))
                                            <p class="mt-0.5 line-clamp-2 max-w-md text-xs text-slate-500">{{ $item->deskripsi }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-50 px-2.5 py-0.5 text-xs font-medium text-violet-700">
                                            {{ $item->mataPelajaran?->nama ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-slate-600">
                                        {{ $item->kelas }}@if (filled($item->jurusan)) · {{ $item->jurusan }}@endif
                                    </td>
                                    <td class="px-4 py-3.5 tabular-nums text-slate-700">{{ \App\Support\Waktu::tanggal($item->tanggal) }}</td>
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a href="{{ $editUrl('materi', $item->id) }}"
                                               class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                                                <x-lucide-pencil class="h-3.5 w-3.5" /> Edit
                                            </a>
                                            <form method="POST" action="{{ route('dashboard.guru.tugas-materi.materi.destroy', $item) }}"
                                                  onsubmit="return confirm('Hapus materi ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-medium text-rose-600 transition hover:bg-rose-50">
                                                    <x-lucide-trash-2 class="h-3.5 w-3.5" /> Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center">
                                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-50 text-violet-600">
                                            <x-lucide-book-open class="h-6 w-6" />
                                        </span>
                                        <h3 class="mt-3 text-sm font-semibold text-slate-900">Belum Ada Materi</h3>
                                        <p class="mt-1 text-sm text-slate-500">Materi yang Anda bagikan akan muncul di sini.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-slate-100 md:hidden">
                    @forelse ($materi as $item)
                        <div class="px-4 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium text-slate-900">{{ $item->judul }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $item->mataPelajaran?->nama ?? '—' }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                    {{ $item->kelas }}@if (filled($item->jurusan)) · {{ $item->jurusan }}@endif
                                </span>
                            </div>
                            <p class="mt-2 text-xs text-slate-500">{{ \App\Support\Waktu::tanggal($item->tanggal) }}</p>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <a href="{{ $editUrl('materi', $item->id) }}"
                                   class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                                    <x-lucide-pencil class="h-3.5 w-3.5" /> Edit
                                </a>
                                <form method="POST" action="{{ route('dashboard.guru.tugas-materi.materi.destroy', $item) }}"
                                      onsubmit="return confirm('Hapus materi ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-medium text-rose-600 transition hover:bg-rose-50">
                                        <x-lucide-trash-2 class="h-3.5 w-3.5" /> Hapus
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-12 text-center">
                            <h3 class="text-sm font-semibold text-slate-900">Belum Ada Materi</h3>
                            <p class="mt-1 text-sm text-slate-500">Materi yang Anda bagikan akan muncul di sini.</p>
                        </div>
                    @endforelse
                </div>

                <div class="border-t border-slate-100 px-4 py-3">
                    <x-ui.server-pagination :paginator="$materi" label="materi" />
                </div>
            </div>
        @endif

        <script>
            (function () {
                function init(mapelId, rombelId) {
                    const mapel = document.getElementById(mapelId);
                    const rombel = document.getElementById(rombelId);

                    if (!mapel || !rombel) {
                        return;
                    }

                    const semua = Array.from(rombel.querySelectorAll('option[data-mapel]')).map((option) => ({
                        value: option.value,
                        label: option.textContent.trim(),
                        mapel: option.dataset.mapel,
                    }));

                    function saring() {
                        const terpilih = rombel.value;
                        const sesuai = semua.filter((option) => option.mapel === mapel.value);

                        rombel.innerHTML = '<option value="">Pilih kelas…</option>';

                        sesuai.forEach((option) => {
                            const el = document.createElement('option');
                            el.value = option.value;
                            el.textContent = option.label;
                            el.dataset.mapel = option.mapel;

                            if (option.value === terpilih) {
                                el.selected = true;
                            }

                            rombel.appendChild(el);
                        });

                        if (!sesuai.some((option) => option.value === terpilih)) {
                            rombel.value = '';
                        }
                    }

                    mapel.addEventListener('change', saring);
                    saring();
                }

                init('tugas-mapel', 'tugas-rombel');
                init('materi-mapel', 'materi-rombel');
            })();
        </script>
    </div>
@endsection
