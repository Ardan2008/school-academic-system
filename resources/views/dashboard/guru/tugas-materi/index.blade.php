@extends('layouts.dashboard')

@section('title', 'Tugas & Materi')
@section('subtitle', 'Dashboard Guru')

@section('content')
    <x-dashboard.animations />

    @if (session('delete_success'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (window.saskansaDelete) window.saskansaDelete.toast(@json(session('delete_success')));
            });
        </script>
    @endif

    <div id="tugas-materi-guru-page" data-dashboard>
        <style>
            [data-tugas-modal] { transition: opacity .3s ease; }
            [data-tugas-modal][data-open="false"] { opacity: 0; pointer-events: none; }
            [data-tugas-modal][data-open="true"] { opacity: 1; pointer-events: auto; }
            [data-tugas-panel] { opacity: 0; transform: translateY(20px) scale(.97); transition: opacity .3s cubic-bezier(.22,1,.36,1), transform .3s cubic-bezier(.22,1,.36,1); }
            [data-tugas-modal][data-open="true"] [data-tugas-panel] { opacity: 1; transform: none; }
            [data-materi-modal] { transition: opacity .3s ease; }
            [data-materi-modal][data-open="false"] { opacity: 0; pointer-events: none; }
            [data-materi-modal][data-open="true"] { opacity: 1; pointer-events: auto; }
            [data-materi-panel] { opacity: 0; transform: translateY(20px) scale(.97); transition: opacity .3s cubic-bezier(.22,1,.36,1), transform .3s cubic-bezier(.22,1,.36,1); }
            [data-materi-modal][data-open="true"] [data-materi-panel] { opacity: 1; transform: none; }
            [data-dropzone]{transition:border-color .2s ease,background-color .2s ease,box-shadow .2s ease}
            [data-dropzone].is-dragover{border-color:#2185F5;background-color:#EAF3FF;box-shadow:0 0 0 3px rgba(33,133,245,.12)}
            [data-dropzone].is-dragover [data-dropzone-icon]{transform:translateY(-2px) scale(1.04)}
            [data-dropzone-icon]{transition:transform .2s ease}
            [data-file-card].is-enter{animation:materiFileIn .22s ease}
            [data-file-card].is-leave{opacity:0;transform:translateY(6px);transition:opacity .18s ease,transform .18s ease}
            @keyframes materiFileIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
            [data-upload-progress-bar]{transition:width .25s ease}
            @media (prefers-reduced-motion: reduce) {
                [data-tugas-modal],[data-tugas-panel],[data-materi-modal],[data-materi-panel],[data-dropzone],[data-dropzone-icon],[data-file-card],[data-upload-progress-bar]{transition:none;animation:none}
                [data-tugas-panel],[data-materi-panel]{transform:none}
            }
        </style>

        @if (session('success'))
            <div role="status" class="mb-5 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                <x-lucide-badge-check class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <p class="text-sm font-medium text-emerald-800">{{ session('success') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="mb-5 flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4">
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
            $bisaKelola = $mapel->isNotEmpty() && $pasangan->isNotEmpty();
            $bukaModalTugas = $tab === 'tugas' && $errors->any() && $formTugas === null;
            $bukaModalMateri = $tab === 'materi' && $errors->any() && $formMateri === null;
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
                <span data-delete-counter="tugas" @class([
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
                <span data-delete-counter="materi" @class([
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
                           placeholder="Cari judul atau deskripsi..."
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
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <button type="button"
                        data-tambah-tugas-trigger
                        @disabled(! $bisaTugas)
                        class="inline-flex h-10 items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-primary-60 px-4 text-sm font-semibold text-white transition hover:bg-primary-70 disabled:cursor-not-allowed disabled:opacity-50">
                    <x-lucide-plus class="h-4 w-4" />
                    Tambah Tugas
                </button>
                @if (! $bisaTugas)
                    <p class="text-xs text-slate-400">Lengkapi mata pelajaran dan kelas yang diampu untuk menambah tugas.</p>
                @endif
            </div>

            <div id="tugas-edit-modal"
                 data-tugas-modal
                 data-open="{{ $formTugas ? 'true' : 'false' }}"
                 aria-hidden="{{ $formTugas ? 'false' : 'true' }}"
                 @if (! $formTugas) inert @endif
                 class="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto overscroll-contain bg-slate-900/50 p-4 backdrop-blur-sm sm:px-6 lg:left-64 lg:px-8"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="tugas-edit-title">
                <div data-tugas-edit-backdrop class="absolute inset-0"></div>
                <div data-tugas-panel class="relative z-10 flex max-h-[calc(100dvh-6rem)] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-[#FAFAFA] shadow-2xl">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                        <div class="min-w-0">
                            <h2 id="tugas-edit-title" class="text-base font-semibold text-slate-900">Edit Tugas</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Perbarui informasi tugas untuk kelas yang Anda ampu.</p>
                        </div>
                        <button type="button"
                                data-tugas-edit-close
                                class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 active:scale-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60"
                                aria-label="Tutup form edit tugas">
                            <x-lucide-x class="h-4.5 w-4.5" />
                        </button>
                    </div>

                    <form id="tugas-edit-form"
                          method="POST"
                          action="{{ $formTugas ? route('dashboard.guru.tugas-materi.tugas.update', $formTugas) : '#' }}"
                          data-update-action-template="{{ route('dashboard.guru.tugas-materi.tugas.update', ['tugas' => '__ID__']) }}"
                          enctype="multipart/form-data"
                          class="flex min-h-0 flex-1 flex-col">
                        @csrf
                        @method('PUT')
                        <div class="overflow-y-auto px-5 py-4">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="tugas-edit-mapel-tampil" class="mb-1 block text-xs font-medium text-slate-600">Mata Pelajaran</label>
                                    <div class="relative">
                                        <input id="tugas-edit-mapel-tampil" type="text" readonly aria-readonly="true" disabled tabindex="-1"
                                               value="{{ $mapelFormTugas?->nama ?? 'Belum ada mata pelajaran yang ditugaskan' }}"
                                               @error('mata_pelajaran_id') aria-invalid="true" @enderror
                                               class="h-10 w-full cursor-not-allowed select-none rounded-xl border border-slate-200 bg-slate-50 px-3 pr-9 text-sm text-slate-500 outline-none transition disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500 disabled:opacity-100 focus:border-slate-200 focus:outline-none focus:ring-0">
                                        <x-lucide-lock class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                    </div>
                                    <input type="hidden" id="tugas-edit-mapel" name="mata_pelajaran_id" value="{{ $mapelFormTugas?->id }}">
                                    @error('mata_pelajaran_id')
                                        <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="tugas-edit-rombel" class="mb-1 block text-xs font-medium text-slate-600">Kelas</label>
                                    <select id="tugas-edit-rombel" name="rombel" required @disabled(! $bisaKelola) @error('rombel') aria-invalid="true" @enderror
                                            class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30 disabled:bg-slate-50 disabled:text-slate-400">
                                        <option value="">Pilih kelas…</option>
                                        @foreach ($pasangan as $item)
                                            <option value="{{ $item['kelas'].'|'.$item['jurusan'] }}"
                                                    data-mapel="{{ $item['mata_pelajaran_id'] }}"
                                                    @selected($tugasRombel === $item['kelas'].'|'.$item['jurusan'])>
                                                Kelas {{ $item['kelas'] }}@if ($item['jurusan'] !== '') — {{ $item['jurusan'] }}@endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('rombel')
                                        <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="tugas-edit-judul" class="mb-1 block text-xs font-medium text-slate-600">Judul Tugas</label>
                                    <input id="tugas-edit-judul" type="text" name="judul" required maxlength="255"
                                           value="{{ old('judul', $formTugas?->judul) }}"
                                           @error('judul') aria-invalid="true" @enderror
                                           class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">
                                    @error('judul')
                                        <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="tugas-edit-deskripsi" class="mb-1 block text-xs font-medium text-slate-600">Deskripsi</label>
                                    <textarea id="tugas-edit-deskripsi" name="deskripsi" rows="4" maxlength="5000"
                                              @error('deskripsi') aria-invalid="true" @enderror
                                              class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">{{ old('deskripsi', $formTugas?->deskripsi) }}</textarea>
                                    @error('deskripsi')
                                        <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="tugas-edit-deadline" class="mb-1 block text-xs font-medium text-slate-600">Tenggat</label>
                                    <input id="tugas-edit-deadline" type="datetime-local" name="deadline"
                                           value="{{ old('deadline', $formTugas?->deadline?->format('Y-m-d\TH:i')) }}"
                                           @error('deadline') aria-invalid="true" @enderror
                                           class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">
                                    <p class="mt-1 text-[11px] text-slate-400">Kosongkan bila tugas tidak memiliki tenggat.</p>
                                    @error('deadline')
                                        <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="sm:col-span-2" data-file-uploader="edit-tugas">
                                    <label class="mb-1 block text-xs font-medium text-slate-600">Lampiran File (Opsional)</label>
                                    <input type="hidden" name="hapus_lampiran" value="0" data-hapus-lampiran-input>

                                    <div data-edit-stored-card data-file-card class="{{ $formTugas?->file_path ? '' : 'hidden' }} rounded-xl border border-slate-200 bg-white p-3">
                                        <div class="flex items-start gap-3">
                                            <span data-stored-file-icon class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600"><x-lucide-file-text class="h-5 w-5" /></span>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p data-stored-file-nama class="truncate text-sm font-semibold text-slate-900">{{ $formTugas?->nama_file ?? '-' }}</p>
                                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700">File Tersimpan</span>
                                                </div>
                                                <p class="mt-0.5 text-xs text-slate-500"><span data-stored-file-format>{{ $formTugas?->format_file ?? '-' }}</span> &middot; <span data-stored-file-ukuran>{{ $formTugas?->ukuran_file ?? '-' }}</span></p>
                                                <div class="mt-2 flex flex-wrap items-center gap-3 text-xs">
                                                    <a data-stored-file-preview href="{{ $formTugas?->file_path ? route('dashboard.guru.tugas-materi.tugas.preview', $formTugas) : '#' }}" target="_blank" rel="noopener" class="{{ $formTugas?->file_path ? 'inline-flex' : 'hidden' }} items-center gap-1 font-medium text-[#2185F5] hover:underline"><x-lucide-eye class="h-3.5 w-3.5" /> Buka</a>
                                                    <a data-stored-file-unduh href="{{ $formTugas?->file_path ? route('dashboard.guru.tugas-materi.tugas.unduh', $formTugas) : '#' }}" class="{{ $formTugas?->file_path ? 'inline-flex' : 'hidden' }} items-center gap-1 font-medium text-[#2185F5] hover:underline"><x-lucide-download class="h-3.5 w-3.5" /> Unduh</a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                                            <button type="button" data-ganti-baru @disabled(! $bisaKelola) class="inline-flex items-center gap-1.5 rounded-lg border border-[#2185F5]/30 bg-[#EAF3FF] px-3 py-1.5 text-xs font-semibold text-[#2185F5] transition hover:bg-[#dcebff] disabled:cursor-not-allowed disabled:opacity-50"><x-lucide-refresh-cw class="h-3.5 w-3.5" /> Ganti File</button>
                                            <button type="button" data-hapus-lampiran @disabled(! $bisaKelola) class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-50"><x-lucide-trash-2 class="h-3.5 w-3.5" /> Hapus Lampiran</button>
                                        </div>
                                    </div>

                                    <div data-replace-area class="{{ $formTugas?->file_path ? 'hidden' : '' }} space-y-2">
                                        <input id="tugas-edit-file" name="file" type="file" @disabled(! $bisaKelola) @error('file') aria-invalid="true" @enderror accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.zip,.rar" class="sr-only">
                                        <div data-dropzone tabindex="0" role="button" aria-label="Unggah lampiran tugas" class="cursor-pointer rounded-xl border-2 border-dashed border-sky-200 bg-slate-50/60 px-4 py-5 text-center outline-none transition hover:border-[#2185F5] hover:bg-white focus-visible:border-[#2185F5] focus-visible:ring-2 focus-visible:ring-[#2185F5]/20">
                                            <div data-dropzone-icon class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-[#2185F5]/10 text-[#2185F5]"><x-lucide-cloud-upload class="h-5 w-5" /></div>
                                            <p class="mt-2 text-sm font-medium text-slate-700">Drag &amp; Drop file di sini</p>
                                            <p class="mt-0.5 text-xs text-slate-500">atau pilih file dari perangkat Anda</p>
                                            <button type="button" data-pilih-file class="mt-3 inline-flex items-center justify-center gap-1.5 rounded-lg bg-[#2185F5] px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#1b74d8]"><x-lucide-folder-open class="h-4 w-4" /> Pilih File</button>
                                            <p class="mt-3 text-[11px] text-slate-400">Format: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, JPG, PNG, ZIP, RAR &middot; Maksimal 10 MB</p>
                                        </div>
                                        <div data-new-file-card data-file-card class="hidden rounded-xl border border-slate-200 bg-white p-3">
                                            <div class="flex items-start gap-3">
                                                <span data-file-type-icon class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#EAF3FF] text-[#2185F5]"><x-lucide-file class="h-5 w-5" /></span>
                                                <div class="min-w-0 flex-1">
                                                    <p data-file-name class="truncate text-sm font-semibold text-slate-900"></p>
                                                    <p class="mt-0.5 text-xs text-slate-500"><span data-file-ext></span> &middot; <span data-file-size></span></p>
                                                    <p data-file-status class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-emerald-600"><x-lucide-circle-check class="h-3.5 w-3.5" /> <span>Berhasil dipilih</span></p>
                                                </div>
                                                <button type="button" data-batal-file class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600" aria-label="Batalkan pilihan file"><x-lucide-x class="h-4 w-4" /></button>
                                            </div>
                                            <div data-upload-progress class="mt-3 hidden">
                                                <div class="mb-1 flex items-center justify-between text-[11px] text-slate-500"><span data-upload-progress-label>Mengunggah...</span><span data-upload-progress-text>0%</span></div>
                                                <div class="h-1.5 overflow-hidden rounded-full bg-slate-100"><div data-upload-progress-bar class="h-full w-0 rounded-full bg-[#2185F5]"></div></div>
                                            </div>
                                        </div>
                                        <p data-file-error class="hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700"></p>
                                        <button type="button" data-batal-ganti class="{{ $formTugas?->file_path ? '' : 'hidden' }} text-xs font-medium text-slate-500 underline-offset-2 hover:text-slate-700 hover:underline">Batal ganti file</button>
                                    </div>

                                    @error('file')
                                        <p data-server-file-error class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-white px-5 py-3.5">
                            <button type="button"
                                    data-tugas-edit-close
                                    data-tugas-edit-batal
                                    class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60">
                                Batal
                            </button>
                            <button type="submit"
                                    data-tugas-edit-simpan
                                    @disabled(! $bisaTugas)
                                    class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-primary-60 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-70 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:active:scale-100">
                                <x-lucide-save class="h-4 w-4" />
                                <span data-tugas-edit-simpan-label>Simpan</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="mt-4 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60" data-reveal>
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
                                <tr class="transition hover:bg-blue-50/40" data-delete-item="tugas:{{ $item->id }}">
                                    <td class="px-4 py-3.5 text-center text-xs text-slate-500">{{ ($tugas->firstItem() ?? 1) + $loop->index }}</td>
                                    <td class="px-4 py-3.5">
                                        <p class="font-medium text-slate-900">{{ $item->judul }}</p>
                                        @if (filled($item->deskripsi))
                                            <p class="mt-0.5 line-clamp-2 max-w-md text-xs text-slate-500">{{ $item->deskripsi }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-10 px-2.5 py-0.5 text-xs font-medium text-primary-70">
                                            {{ $item->mataPelajaran?->nama ?? '-' }}
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
                                        <div class="inline-flex items-center justify-end gap-2">
                                            <a href="{{ route('dashboard.guru.tugas-materi.pengumpulan', $item) }}"
                                               class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-primary-60 transition hover:bg-blue-50">
                                                <x-lucide-inbox class="h-3.5 w-3.5" />
                                                Kumpulan
                                            </a>
                                            <button type="button"
                                                    data-edit-tugas-trigger
                                                    data-id="{{ $item->id }}"
                                                    data-judul="{{ $item->judul }}"
                                                    data-deskripsi="{{ $item->deskripsi }}"
                                                    data-deadline="{{ $item->deadline?->format('Y-m-d\TH:i') }}"
                                                    data-mapel-id="{{ $item->mata_pelajaran_id }}"
                                                    data-mapel-nama="{{ $item->mataPelajaran?->nama ?? '' }}"
                                                    data-rombel="{{ $item->kelas.'|'.($item->jurusan ?? '') }}"
                                                    data-file-nama="{{ $item->nama_file }}"
                                                    data-file-format="{{ $item->format_file }}"
                                                    data-file-ukuran="{{ $item->ukuran_file }}"
                                                    data-file-preview="{{ $item->file_path ? route('dashboard.guru.tugas-materi.tugas.preview', $item) : '' }}"
                                                    data-file-unduh="{{ $item->file_path ? route('dashboard.guru.tugas-materi.tugas.unduh', $item) : '' }}"
                                                    data-has-file="{{ $item->file_path ? '1' : '0' }}"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-blue-300 bg-white text-primary-60 transition hover:bg-blue-50 active:scale-90"
                                                    aria-label="Ubah tugas {{ $item->judul }}" title="Ubah tugas {{ $item->judul }}">
                                                <x-lucide-pencil class="h-4 w-4" />
                                            </button>
                                            <form method="POST"
                                                  action="{{ route('dashboard.guru.tugas-materi.tugas.destroy', $item) }}"
                                                  data-delete-form
                                                  data-delete-kind="tugas"
                                                  data-delete-id="{{ $item->id }}"
                                                  data-delete-name="{{ $item->judul }}"
                                                  data-delete-title="Hapus data tugas?"
                                                  data-delete-text="{{ $item->judul }} akan dihapus dari daftar beserta seluruh pengumpulannya."
                                                  data-delete-loading="Menghapus tugas...">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-red-300 bg-white text-red-500 transition hover:bg-red-50 active:scale-90"
                                                        aria-label="Hapus tugas {{ $item->judul }}" title="Hapus tugas {{ $item->judul }}">
                                                    <x-lucide-trash-2 class="h-4 w-4" />
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
                        <div class="px-4 py-4" data-delete-item="tugas:{{ $item->id }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium text-slate-900">{{ $item->judul }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $item->mataPelajaran?->nama ?? '-' }}</p>
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
                                            <button type="button"
                                                    data-edit-tugas-trigger
                                                    data-id="{{ $item->id }}"
                                                    data-judul="{{ $item->judul }}"
                                                    data-deskripsi="{{ $item->deskripsi }}"
                                                    data-deadline="{{ $item->deadline?->format('Y-m-d\TH:i') }}"
                                                    data-mapel-id="{{ $item->mata_pelajaran_id }}"
                                                    data-mapel-nama="{{ $item->mataPelajaran?->nama ?? '' }}"
                                                    data-rombel="{{ $item->kelas.'|'.($item->jurusan ?? '') }}"
                                                    data-file-nama="{{ $item->nama_file }}"
                                                    data-file-format="{{ $item->format_file }}"
                                                    data-file-ukuran="{{ $item->ukuran_file }}"
                                                    data-file-preview="{{ $item->file_path ? route('dashboard.guru.tugas-materi.tugas.preview', $item) : '' }}"
                                                    data-file-unduh="{{ $item->file_path ? route('dashboard.guru.tugas-materi.tugas.unduh', $item) : '' }}"
                                                    data-has-file="{{ $item->file_path ? '1' : '0' }}"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-blue-300 bg-white text-primary-60 transition hover:bg-blue-50 active:scale-90"
                                                    aria-label="Ubah tugas {{ $item->judul }}" title="Ubah tugas {{ $item->judul }}">
                                                <x-lucide-pencil class="h-4 w-4" />
                                            </button>
                                <form method="POST"
                                      action="{{ route('dashboard.guru.tugas-materi.tugas.destroy', $item) }}"
                                      data-delete-form
                                      data-delete-kind="tugas"
                                      data-delete-id="{{ $item->id }}"
                                      data-delete-name="{{ $item->judul }}"
                                      data-delete-title="Hapus data tugas?"
                                      data-delete-text="{{ $item->judul }} akan dihapus dari daftar beserta seluruh pengumpulannya."
                                      data-delete-loading="Menghapus tugas...">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-red-300 bg-white text-red-500 transition hover:bg-red-50 active:scale-90"
                                            aria-label="Hapus tugas {{ $item->judul }}" title="Hapus tugas {{ $item->judul }}">
                                        <x-lucide-trash-2 class="h-4 w-4" />
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
            <div id="materi-edit-modal"
                 data-materi-modal
                 data-open="{{ $formMateri ? 'true' : 'false' }}"
                 aria-hidden="{{ $formMateri ? 'false' : 'true' }}"
                 @if (! $formMateri) inert @endif
                 class="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto overscroll-contain bg-slate-900/50 p-4 backdrop-blur-sm sm:px-6 lg:left-64 lg:px-8"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="materi-edit-title">
                <div data-materi-form-backdrop class="absolute inset-0"></div>
                <div data-materi-panel class="relative z-10 flex max-h-[calc(100dvh-6rem)] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-[#FAFAFA] shadow-2xl">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                        <div class="min-w-0">
                            <h2 id="materi-edit-title" class="text-base font-semibold text-slate-900">Edit Materi</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Perbarui bahan ajar untuk kelas yang Anda ampu.</p>
                        </div>
                        <button type="button"
                                data-materi-form-close
                                class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 active:scale-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60"
                                aria-label="Tutup form edit materi">
                            <x-lucide-x class="h-4.5 w-4.5" />
                        </button>
                    </div>

                    <form id="materi-edit-form"
                          method="POST"
                          action="{{ $formMateri ? route('dashboard.guru.tugas-materi.materi.update', $formMateri) : '#' }}"
                          data-update-action-template="{{ route('dashboard.guru.tugas-materi.materi.update', ['materi' => '__ID__']) }}"
                          enctype="multipart/form-data"
                          class="flex min-h-0 flex-1 flex-col">
                        @csrf
                        @method('PUT')
                        <div class="overflow-y-auto px-5 py-4">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="materi-edit-mapel-tampil" class="mb-1 block text-xs font-medium text-slate-600">Mata Pelajaran</label>
                                    <div class="relative">
                                        <input id="materi-edit-mapel-tampil" type="text" readonly aria-readonly="true" disabled tabindex="-1"
                                               value="{{ $mapelFormMateri?->nama ?? 'Belum ada mata pelajaran yang ditugaskan' }}"
                                               @error('mata_pelajaran_id') aria-invalid="true" @enderror
                                               class="h-10 w-full cursor-not-allowed select-none rounded-xl border border-slate-200 bg-slate-50 px-3 pr-9 text-sm text-slate-500 outline-none transition disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500 disabled:opacity-100 focus:border-slate-200 focus:outline-none focus:ring-0">
                                        <x-lucide-lock class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                    </div>
                                    <input type="hidden" id="materi-edit-mapel" name="mata_pelajaran_id" value="{{ $mapelFormMateri?->id }}">
                                    @error('mata_pelajaran_id')
                                        <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="materi-edit-rombel" class="mb-1 block text-xs font-medium text-slate-600">Kelas</label>
                                    <select id="materi-edit-rombel" name="rombel" required @disabled(! $bisaKelola) @error('rombel') aria-invalid="true" @enderror
                                            class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30 disabled:bg-slate-50 disabled:text-slate-400">
                                        <option value="">Pilih kelas…</option>
                                        @foreach ($pasangan as $item)
                                            <option value="{{ $item['kelas'].'|'.$item['jurusan'] }}"
                                                    data-mapel="{{ $item['mata_pelajaran_id'] }}"
                                                    @selected($materiRombel === $item['kelas'].'|'.$item['jurusan'])>
                                                Kelas {{ $item['kelas'] }}@if ($item['jurusan'] !== '') — {{ $item['jurusan'] }}@endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('rombel')
                                        <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="materi-edit-judul" class="mb-1 block text-xs font-medium text-slate-600">Judul Materi</label>
                                    <input id="materi-edit-judul" type="text" name="judul" required maxlength="255"
                                           value="{{ old('judul', $formMateri?->judul) }}"
                                           @error('judul') aria-invalid="true" @enderror
                                           class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">
                                    @error('judul')
                                        <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="materi-edit-deskripsi" class="mb-1 block text-xs font-medium text-slate-600">Deskripsi</label>
                                    <textarea id="materi-edit-deskripsi" name="deskripsi" rows="3" maxlength="5000"
                                              @error('deskripsi') aria-invalid="true" @enderror
                                              class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">{{ old('deskripsi', $formMateri?->deskripsi) }}</textarea>
                                    @error('deskripsi')
                                        <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="materi-edit-tanggal" class="mb-1 block text-xs font-medium text-slate-600">Tanggal</label>
                                    <input id="materi-edit-tanggal" type="date" name="tanggal"
                                           value="{{ old('tanggal', $formMateri?->tanggal?->format('Y-m-d') ?? now()->toDateString()) }}"
                                           @error('tanggal') aria-invalid="true" @enderror
                                           class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">
                                    @error('tanggal')
                                        <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="sm:col-span-2" data-materi-uploader="edit">
                                    <label class="mb-1 block text-xs font-medium text-slate-600">File Materi (Opsional)</label>

                                    <div id="materi-edit-file-current" data-edit-stored-card data-file-card data-materi-file-info class="{{ $formMateri?->file_path ? '' : 'hidden' }} rounded-xl border border-slate-200 bg-white p-3">
                                        <div class="flex items-start gap-3">
                                            <span data-stored-file-icon class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600"><x-lucide-file-text class="h-5 w-5" /></span>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p data-materi-edit-file-nama class="truncate text-sm font-semibold text-slate-900">{{ $formMateri?->nama_file ?? '-' }}</p>
                                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700">File Tersimpan</span>
                                                </div>
                                                <p class="mt-0.5 text-xs text-slate-500"><span data-materi-edit-file-format>{{ $formMateri?->format_file ?? '-' }}</span> ? <span data-materi-edit-file-ukuran>{{ $formMateri?->ukuran_file ?? '-' }}</span></p>
                                                <div class="mt-2 flex flex-wrap items-center gap-3 text-xs">
                                                    <a data-materi-edit-file-preview data-materi-preview href="{{ $formMateri?->file_path ? route('dashboard.guru.tugas-materi.materi.preview', $formMateri) : '#' }}" target="_blank" rel="noopener" class="{{ $formMateri?->file_path ? 'inline-flex' : 'hidden' }} items-center gap-1 font-medium text-[#2185F5] hover:underline"><x-lucide-eye class="h-3.5 w-3.5" /> Buka</a>
                                                    <a data-materi-edit-file-unduh href="{{ $formMateri?->file_path ? route('dashboard.guru.tugas-materi.materi.unduh', $formMateri) : '#' }}" class="{{ $formMateri?->file_path ? 'inline-flex' : 'hidden' }} items-center gap-1 font-medium text-[#2185F5] hover:underline"><x-lucide-download class="h-3.5 w-3.5" /> Unduh</a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-3 border-t border-slate-100 pt-3">
                                            <button type="button" data-ganti-baru data-materi-ganti @disabled(! $bisaKelola) class="inline-flex items-center gap-1.5 rounded-lg border border-[#2185F5]/30 bg-[#EAF3FF] px-3 py-1.5 text-xs font-semibold text-[#2185F5] transition hover:bg-[#dcebff] disabled:cursor-not-allowed disabled:opacity-50"><x-lucide-refresh-cw class="h-3.5 w-3.5" /> Ganti dengan File Baru</button>
                                        </div>
                                    </div>

                                    <div data-replace-area class="hidden space-y-2">
                                        <input id="materi-edit-file" name="file" type="file" @disabled(! $bisaKelola) @error('file') aria-invalid="true" @enderror accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.zip,.rar" class="sr-only">
                                        <div data-dropzone tabindex="0" role="button" aria-label="Unggah file materi pengganti" class="cursor-pointer rounded-xl border-2 border-dashed border-sky-200 bg-slate-50/60 px-4 py-5 text-center outline-none transition hover:border-[#2185F5] hover:bg-white focus-visible:border-[#2185F5] focus-visible:ring-2 focus-visible:ring-[#2185F5]/20">
                                            <div data-dropzone-icon class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-[#2185F5]/10 text-[#2185F5]"><x-lucide-cloud-upload class="h-5 w-5" /></div>
                                            <p class="mt-2 text-sm font-medium text-slate-700">Drag &amp; Drop file di sini</p>
                                            <p class="mt-0.5 text-xs text-slate-500">atau pilih file dari perangkat Anda</p>
                                            <button type="button" data-pilih-file class="mt-3 inline-flex items-center justify-center gap-1.5 rounded-lg bg-[#2185F5] px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#1b74d8]"><x-lucide-folder-open class="h-4 w-4" /> Pilih File</button>
                                            <p class="mt-3 text-[11px] text-slate-400">Format: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, JPG, PNG, ZIP, RAR ? Maksimal 20 MB</p>
                                        </div>
                                        <div data-new-file-card data-file-card class="hidden rounded-xl border border-slate-200 bg-white p-3">
                                            <div class="flex items-start gap-3">
                                                <span data-file-type-icon class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#EAF3FF] text-[#2185F5]"><x-lucide-file class="h-5 w-5" /></span>
                                                <div class="min-w-0 flex-1">
                                                    <p data-file-name class="truncate text-sm font-semibold text-slate-900"></p>
                                                    <p class="mt-0.5 text-xs text-slate-500"><span data-file-ext></span> ? <span data-file-size></span></p>
                                                    <p data-file-status class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-emerald-600"><x-lucide-circle-check class="h-3.5 w-3.5" /> <span>Berhasil dipilih</span></p>
                                                </div>
                                                <button type="button" data-batal-file class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600" aria-label="Batalkan pilihan file"><x-lucide-x class="h-4 w-4" /></button>
                                            </div>
                                            <div data-upload-progress class="mt-3 hidden">
                                                <div class="mb-1 flex items-center justify-between text-[11px] text-slate-500"><span>Mengunggah...</span><span data-upload-progress-text>0%</span></div>
                                                <div class="h-1.5 overflow-hidden rounded-full bg-slate-100"><div data-upload-progress-bar class="h-full w-0 rounded-full bg-[#2185F5]"></div></div>
                                            </div>
                                        </div>
                                        <p data-file-error class="hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700"></p>
                                        <button type="button" data-batal-ganti data-materi-batal class="text-xs font-medium text-slate-500 underline-offset-2 hover:text-slate-700 hover:underline">Batal ganti file</button>
                                    </div>

                                    @error('file')
                                        <p data-server-file-error class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-white px-5 py-3.5">
                            <button type="button"
                                    data-materi-form-close
                                    data-materi-edit-batal
                                    class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60">
                                Batal
                            </button>
                            <button type="submit"
                                    data-materi-edit-simpan
                                    @disabled(! $bisaMateri)
                                    class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-primary-60 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-70 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:active:scale-100">
                                <x-lucide-save class="h-4 w-4" />
                                <span data-materi-edit-simpan-label>Simpan</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <button type="button"
                            data-tambah-materi-trigger
                            @disabled(! $bisaMateri)
                            class="inline-flex h-10 items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-primary-60 px-4 text-sm font-semibold text-white transition hover:bg-primary-70 disabled:cursor-not-allowed disabled:opacity-50">
                        <x-lucide-plus class="h-4 w-4" />
                        Tambah Materi
                    </button>
                    @if (! $bisaMateri)
                        <p class="text-xs text-slate-400">Lengkapi mata pelajaran dan kelas yang diampu untuk menambah materi.</p>
                    @endif
                </div>

                <div id="materi-tambah-modal"
                     data-materi-modal
                     data-open="{{ $bukaModalMateri ? 'true' : 'false' }}"
                     aria-hidden="{{ $bukaModalMateri ? 'false' : 'true' }}"
                     @if (! $bukaModalMateri) inert @endif
                     class="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto overscroll-contain bg-slate-900/50 p-4 backdrop-blur-sm sm:px-6 lg:left-64 lg:px-8"
                     role="dialog"
                     aria-modal="true"
                     aria-labelledby="materi-tambah-title">
                    <div data-materi-form-backdrop class="absolute inset-0"></div>
                    <div data-materi-panel class="relative z-10 flex max-h-[calc(100dvh-6rem)] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-[#FAFAFA] shadow-2xl">
                        <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                            <div class="min-w-0">
                                <h2 id="materi-tambah-title" class="text-base font-semibold text-slate-900">Tambah Materi</h2>
                                <p class="mt-0.5 text-xs text-slate-500">Unggah bahan ajar untuk kelas yang Anda ampu.</p>
                            </div>
                            <button type="button"
                                    data-materi-form-close
                                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 active:scale-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60"
                                    aria-label="Tutup form tambah materi">
                                <x-lucide-x class="h-4.5 w-4.5" />
                            </button>
                        </div>

                        <form id="materi-form"
                              method="POST"
                              action="{{ route('dashboard.guru.tugas-materi.materi.store') }}"
                              enctype="multipart/form-data"
                              class="flex min-h-0 flex-1 flex-col">
                            @csrf
                            <div class="overflow-y-auto px-5 py-4">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label for="materi-mapel-tampil" class="mb-1 block text-xs font-medium text-slate-600">Mata Pelajaran</label>
                                        <div class="relative">
                                            <input id="materi-mapel-tampil" type="text" readonly aria-readonly="true" disabled tabindex="-1"
                                                   value="{{ $mapelFormMateri?->nama ?? 'Belum ada mata pelajaran yang ditugaskan' }}"
                                                   @error('mata_pelajaran_id') aria-invalid="true" @enderror
                                                   class="h-10 w-full cursor-not-allowed select-none rounded-xl border border-slate-200 bg-slate-50 px-3 pr-9 text-sm text-slate-500 outline-none transition disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500 disabled:opacity-100 focus:border-slate-200 focus:outline-none focus:ring-0">
                                            <x-lucide-lock class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                        </div>
                                        <input type="hidden" id="materi-mapel" name="mata_pelajaran_id" value="{{ $mapelFormMateri?->id }}">
                                        @if ($mapel->isEmpty())
                                            <p class="mt-1 text-[11px] text-amber-700">Belum ada mata pelajaran yang ditugaskan. Penyimpanan materi belum dapat dilakukan.</p>
                                        @endif
                                        @error('mata_pelajaran_id')
                                            <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="materi-rombel" class="mb-1 block text-xs font-medium text-slate-600">Kelas</label>
                                        <select id="materi-rombel" name="rombel" required @disabled(! $bisaKelola) @error('rombel') aria-invalid="true" @enderror
                                                class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30 disabled:bg-slate-50 disabled:text-slate-400">
                                            <option value="">Pilih kelas...</option>
                                            @foreach ($pasangan as $item)
                                                <option value="{{ $item['kelas'].'|'.$item['jurusan'] }}"
                                                        data-mapel="{{ $item['mata_pelajaran_id'] }}"
                                                        @selected($materiRombel === $item['kelas'].'|'.$item['jurusan'])>
                                                    Kelas {{ $item['kelas'] }}@if ($item['jurusan'] !== '') · {{ $item['jurusan'] }}@endif
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('rombel')
                                            <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="sm:col-span-2">
                                        <label for="materi-judul" class="mb-1 block text-xs font-medium text-slate-600">Judul Materi</label>
                                        <input id="materi-judul" type="text" name="judul" required maxlength="255"
                                               value="{{ old('judul') }}"
                                               @error('judul') aria-invalid="true" @enderror
                                               class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">
                                        @error('judul')
                                            <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="sm:col-span-2">
                                        <label for="materi-deskripsi" class="mb-1 block text-xs font-medium text-slate-600">Deskripsi</label>
                                        <textarea id="materi-deskripsi" name="deskripsi" rows="3" maxlength="5000"
                                                  @error('deskripsi') aria-invalid="true" @enderror
                                                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">{{ old('deskripsi') }}</textarea>
                                        @error('deskripsi')
                                            <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="materi-tanggal" class="mb-1 block text-xs font-medium text-slate-600">Tanggal</label>
                                        <input id="materi-tanggal" type="date" name="tanggal"
                                               value="{{ old('tanggal', now()->toDateString()) }}"
                                               @error('tanggal') aria-invalid="true" @enderror
                                               class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">
                                        @error('tanggal')
                                            <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="sm:col-span-2" data-materi-uploader="add">
                                        <label for="materi-file" class="mb-1 block text-xs font-medium text-slate-600">File Materi (Opsional)</label>
                                        <input id="materi-file" name="file" type="file" required @disabled(! $bisaKelola) @error('file') aria-invalid="true" @enderror accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.zip,.rar" class="sr-only">
                                        <div data-dropzone tabindex="0" role="button" aria-label="Unggah file materi" class="cursor-pointer rounded-xl border-2 border-dashed border-sky-200 bg-slate-50/60 px-4 py-5 text-center outline-none transition hover:border-[#2185F5] hover:bg-white focus-visible:border-[#2185F5] focus-visible:ring-2 focus-visible:ring-[#2185F5]/20">
                                            <div data-dropzone-icon class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-[#2185F5]/10 text-[#2185F5]"><x-lucide-cloud-upload class="h-5 w-5" /></div>
                                            <p class="mt-2 text-sm font-medium text-slate-700">Drag &amp; Drop file di sini</p>
                                            <p class="mt-0.5 text-xs text-slate-500">atau pilih file dari perangkat Anda</p>
                                            <button type="button" data-pilih-file class="mt-3 inline-flex items-center justify-center gap-1.5 rounded-lg bg-[#2185F5] px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#1b74d8]"><x-lucide-folder-open class="h-4 w-4" /> Pilih File</button>
                                            <p class="mt-3 text-[11px] text-slate-400">Format: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, JPG, PNG, ZIP, RAR ? Maksimal 20 MB</p>
                                        </div>
                                        <div data-new-file-card data-file-card class="mt-3 hidden rounded-xl border border-slate-200 bg-white p-3">
                                            <div class="flex items-start gap-3">
                                                <span data-file-type-icon class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#EAF3FF] text-[#2185F5]"><x-lucide-file class="h-5 w-5" /></span>
                                                <div class="min-w-0 flex-1">
                                                    <p data-file-name class="truncate text-sm font-semibold text-slate-900"></p>
                                                    <p class="mt-0.5 text-xs text-slate-500"><span data-file-ext></span> ? <span data-file-size></span></p>
                                                    <p data-file-status class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-emerald-600"><x-lucide-circle-check class="h-3.5 w-3.5" /> <span>Berhasil dipilih</span></p>
                                                </div>
                                                <button type="button" data-batal-file class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600" aria-label="Batalkan pilihan file"><x-lucide-x class="h-4 w-4" /></button>
                                            </div>
                                            <div data-upload-progress class="mt-3 hidden">
                                                <div class="mb-1 flex items-center justify-between text-[11px] text-slate-500"><span>Mengunggah...</span><span data-upload-progress-text>0%</span></div>
                                                <div class="h-1.5 overflow-hidden rounded-full bg-slate-100"><div data-upload-progress-bar class="h-full w-0 rounded-full bg-[#2185F5]"></div></div>
                                            </div>
                                        </div>
                                        <p data-file-error class="mt-2 hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700"></p>
                                        @error('file')
                                            <p data-server-file-error class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-white px-5 py-3.5">
                                <button type="button"
                                        data-materi-form-close
                                        data-materi-form-batal
                                        class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60 disabled:cursor-not-allowed disabled:opacity-60 disabled:active:scale-100">
                                    Batal
                                </button>
                                <button type="submit"
                                        data-materi-form-simpan
                                        @disabled(! $bisaMateri)
                                        class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-primary-60 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-70 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:active:scale-100">
                                    <x-lucide-save class="h-4 w-4" />
                                    <span data-materi-form-simpan-label>Simpan</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            <div class="mt-4 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md shadow-slate-200/60" data-reveal>
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
                                <tr class="transition hover:bg-violet-50/30" data-delete-item="materi:{{ $item->id }}">
                                    <td class="px-4 py-3.5 text-center text-xs text-slate-500">{{ ($materi->firstItem() ?? 1) + $loop->index }}</td>
                                    <td class="px-4 py-3.5">
                                        <p class="font-medium text-slate-900">{{ $item->judul }}</p>
                                        @if (filled($item->deskripsi))
                                            <p class="mt-0.5 line-clamp-2 max-w-md text-xs text-slate-500">{{ $item->deskripsi }}</p>
                                        @endif
                                        @if ($item->file_path)
                                            <p class="mt-1 inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                                                <x-lucide-file-text class="h-3 w-3" />
                                                {{ $item->nama_file }} · {{ $item->format_file }} · {{ $item->ukuran_file }}
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-50 px-2.5 py-0.5 text-xs font-medium text-violet-700">
                                            {{ $item->mataPelajaran?->nama ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-slate-600">
                                        {{ $item->kelas }}@if (filled($item->jurusan)) · {{ $item->jurusan }}@endif
                                    </td>
                                    <td class="px-4 py-3.5 tabular-nums text-slate-700">{{ \App\Support\Waktu::tanggal($item->tanggal) }}</td>
                                    <td class="px-4 py-3.5">
                                        <div class="inline-flex items-center justify-end gap-2">
                                            <button type="button"
                                                    data-detail-materi-trigger
                                                    data-detail-url="{{ route('dashboard.guru.tugas-materi.materi.show', $item) }}"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-blue-300 bg-white text-primary-60 transition hover:bg-blue-50 active:scale-90"
                                                    aria-label="Lihat detail {{ $item->judul }}" title="Lihat Detail">
                                                <x-lucide-eye class="h-4 w-4" />
                                            </button>
                                            <button type="button"
                                                    data-edit-materi-trigger
                                                    data-id="{{ $item->id }}"
                                                    data-judul="{{ $item->judul }}"
                                                    data-deskripsi="{{ $item->deskripsi }}"
                                                    data-tanggal="{{ $item->tanggal?->format('Y-m-d') }}"
                                                    data-mapel-id="{{ $item->mata_pelajaran_id }}"
                                                    data-mapel-nama="{{ $item->mataPelajaran?->nama ?? '' }}"
                                                    data-rombel="{{ $item->kelas.'|'.($item->jurusan ?? '') }}"
                                                    data-file-nama="{{ $item->nama_file }}"
                                                    data-file-format="{{ $item->format_file }}"
                                                    data-file-ukuran="{{ $item->ukuran_file }}"
                                                    data-file-preview="{{ $item->file_path ? route('dashboard.guru.tugas-materi.materi.preview', $item) : '' }}"
                                                    data-file-unduh="{{ $item->file_path ? route('dashboard.guru.tugas-materi.materi.unduh', $item) : '' }}"
                                                    data-has-file="{{ $item->file_path ? '1' : '0' }}"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-blue-300 bg-white text-primary-60 transition hover:bg-blue-50 active:scale-90"
                                                    aria-label="Ubah materi {{ $item->judul }}" title="Ubah materi {{ $item->judul }}">
                                                <x-lucide-pencil class="h-4 w-4" />
                                            </button>
                                            <form method="POST"
                                                  action="{{ route('dashboard.guru.tugas-materi.materi.destroy', $item) }}"
                                                  data-delete-form
                                                  data-delete-kind="materi"
                                                  data-delete-id="{{ $item->id }}"
                                                  data-delete-name="{{ $item->judul }}"
                                                  data-delete-title="Hapus data materi?"
                                                  data-delete-text="{{ $item->judul }} akan dihapus dari daftar."
                                                  data-delete-loading="Menghapus materi...">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-red-300 bg-white text-red-500 transition hover:bg-red-50 active:scale-90"
                                                        aria-label="Hapus materi {{ $item->judul }}" title="Hapus materi {{ $item->judul }}">
                                                    <x-lucide-trash-2 class="h-4 w-4" />
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
                        <div class="px-4 py-4" data-delete-item="materi:{{ $item->id }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium text-slate-900">{{ $item->judul }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $item->mataPelajaran?->nama ?? '-' }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                    {{ $item->kelas }}@if (filled($item->jurusan)) · {{ $item->jurusan }}@endif
                                </span>
                            </div>
                            <p class="mt-2 text-xs text-slate-500">{{ \App\Support\Waktu::tanggal($item->tanggal) }}</p>
                            @if ($item->file_path)
                                <p class="mt-1 inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                                    <x-lucide-file-text class="h-3 w-3" />
                                    {{ $item->nama_file }} · {{ $item->format_file }} · {{ $item->ukuran_file }}
                                </p>
                            @endif
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <button type="button"
                                        data-detail-materi-trigger
                                        data-detail-url="{{ route('dashboard.guru.tugas-materi.materi.show', $item) }}"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-blue-300 bg-white text-primary-60 transition hover:bg-blue-50 active:scale-90"
                                        aria-label="Lihat detail {{ $item->judul }}" title="Lihat Detail">
                                    <x-lucide-eye class="h-4 w-4" />
                                </button>
                                            <button type="button"
                                                    data-edit-materi-trigger
                                                    data-id="{{ $item->id }}"
                                                    data-judul="{{ $item->judul }}"
                                                    data-deskripsi="{{ $item->deskripsi }}"
                                                    data-tanggal="{{ $item->tanggal?->format('Y-m-d') }}"
                                                    data-mapel-id="{{ $item->mata_pelajaran_id }}"
                                                    data-mapel-nama="{{ $item->mataPelajaran?->nama ?? '' }}"
                                                    data-rombel="{{ $item->kelas.'|'.($item->jurusan ?? '') }}"
                                                    data-file-nama="{{ $item->nama_file }}"
                                                    data-file-format="{{ $item->format_file }}"
                                                    data-file-ukuran="{{ $item->ukuran_file }}"
                                                    data-file-preview="{{ $item->file_path ? route('dashboard.guru.tugas-materi.materi.preview', $item) : '' }}"
                                                    data-file-unduh="{{ $item->file_path ? route('dashboard.guru.tugas-materi.materi.unduh', $item) : '' }}"
                                                    data-has-file="{{ $item->file_path ? '1' : '0' }}"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-blue-300 bg-white text-primary-60 transition hover:bg-blue-50 active:scale-90"
                                                    aria-label="Ubah materi {{ $item->judul }}" title="Ubah materi {{ $item->judul }}">
                                                <x-lucide-pencil class="h-4 w-4" />
                                            </button>
                                <form method="POST"
                                      action="{{ route('dashboard.guru.tugas-materi.materi.destroy', $item) }}"
                                      data-delete-form
                                      data-delete-kind="materi"
                                      data-delete-id="{{ $item->id }}"
                                      data-delete-name="{{ $item->judul }}"
                                      data-delete-title="Hapus data materi?"
                                      data-delete-text="{{ $item->judul }} akan dihapus dari daftar."
                                      data-delete-loading="Menghapus materi...">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-red-300 bg-white text-red-500 transition hover:bg-red-50 active:scale-90"
                                            aria-label="Hapus materi {{ $item->judul }}" title="Hapus materi {{ $item->judul }}">
                                        <x-lucide-trash-2 class="h-4 w-4" />
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

            <div id="materi-detail-modal" data-materi-modal data-open="false" aria-hidden="true" inert
                 class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm transition-opacity lg:left-64">
                <div data-materi-panel role="dialog" aria-modal="true" aria-labelledby="materi-detail-title"
                     class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-xl">
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Detail Materi</p>
                            <h3 id="materi-detail-title" data-materi-detail-judul class="mt-0.5 text-base font-semibold text-slate-900"></h3>
                        </div>
                        <button type="button" data-materi-close
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60"
                                aria-label="Tutup detail materi">
                            <x-lucide-x class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="space-y-4 px-5 py-5 text-sm">
                        <dl class="grid gap-3 sm:grid-cols-2">
                            <div><dt class="text-xs text-slate-500">Mata Pelajaran</dt><dd data-materi-detail-mapel class="mt-0.5 font-medium text-slate-800"></dd></div>
                            <div><dt class="text-xs text-slate-500">Guru Pengunggah</dt><dd data-materi-detail-guru class="mt-0.5 font-medium text-slate-800"></dd></div>
                            <div><dt class="text-xs text-slate-500">Kelas</dt><dd data-materi-detail-kelas class="mt-0.5 font-medium text-slate-800"></dd></div>
                            <div><dt class="text-xs text-slate-500">Tanggal Unggah</dt><dd data-materi-detail-tanggal class="mt-0.5 font-medium text-slate-800"></dd></div>
                            <div><dt class="text-xs text-slate-500">Terakhir Diperbarui</dt><dd data-materi-detail-diperbarui class="mt-0.5 font-medium text-slate-800"></dd></div>
                            <div><dt class="text-xs text-slate-500">File</dt><dd data-materi-detail-file class="mt-0.5 font-medium text-slate-800"></dd></div>
                        </dl>
                        <div>
                            <p class="text-xs text-slate-500">Deskripsi</p>
                            <p data-materi-detail-deskripsi class="mt-1 whitespace-pre-line text-slate-700"></p>
                        </div>
                        <div data-materi-detail-pratinjau class="hidden overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                            <iframe data-materi-detail-frame title="Pratinjau PDF" class="hidden h-80 w-full bg-white"></iframe>
                            <img data-materi-detail-image alt="Pratinjau gambar" class="hidden max-h-80 w-full object-contain">
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-5 py-4">
                        <button type="button" data-materi-close class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-600 transition hover:bg-slate-50">Tutup</button>
                        <a data-materi-detail-unduh href="#" class="hidden inline-flex h-10 items-center gap-1.5 rounded-xl bg-primary-60 px-4 text-sm font-semibold text-white transition hover:bg-primary-70">
                            <x-lucide-download class="h-4 w-4" /> Unduh Materi
                        </a>
                    </div>
                </div>
            </div>
        @endif

        @unless ($formTugas)
            <div id="tugas-modal"
                 data-tugas-modal
                 data-open="{{ $bukaModalTugas ? 'true' : 'false' }}"
                 aria-hidden="{{ $bukaModalTugas ? 'false' : 'true' }}"
                 @if (! $bukaModalTugas) inert @endif
                 class="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto overscroll-contain bg-slate-900/50 p-4 backdrop-blur-sm sm:px-6 lg:left-64 lg:px-8"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="tugas-modal-title">
                <div data-tugas-backdrop class="absolute inset-0"></div>
                <div data-tugas-panel class="relative z-10 flex max-h-[calc(100dvh-6rem)] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-[#FAFAFA] shadow-2xl">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                        <div class="min-w-0">
                            <h2 id="tugas-modal-title" class="text-base font-semibold text-slate-900">Tambah Tugas</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Buat tugas baru untuk kelas yang Anda ampu.</p>
                        </div>
                        <button type="button"
                                data-tugas-close
                                class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 active:scale-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60"
                                aria-label="Tutup form tambah tugas">
                            <x-lucide-x class="h-4.5 w-4.5" />
                        </button>
                    </div>

                    <form id="tugas-form" method="POST" action="{{ route('dashboard.guru.tugas-materi.tugas.store') }}" enctype="multipart/form-data" class="flex min-h-0 flex-1 flex-col">
                        @csrf
                        <div class="overflow-y-auto px-5 py-4">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="tugas-mapel-tampil" class="mb-1 block text-xs font-medium text-slate-600">Mata Pelajaran</label>
                                    <div class="relative">
                                        <input id="tugas-mapel-tampil" type="text" readonly aria-readonly="true" disabled tabindex="-1"
                                               value="{{ $mapelFormTugas?->nama ?? 'Belum ada mata pelajaran yang ditugaskan' }}"
                                               @error('mata_pelajaran_id') aria-invalid="true" @enderror
                                               class="h-10 w-full cursor-not-allowed select-none rounded-xl border border-slate-200 bg-slate-50 px-3 pr-9 text-sm text-slate-500 outline-none transition disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500 disabled:opacity-100 focus:border-slate-200 focus:outline-none focus:ring-0">
                                        <x-lucide-lock class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                    </div>
                                    <input type="hidden" id="tugas-mapel" name="mata_pelajaran_id" value="{{ $mapelFormTugas?->id }}">
                                    @if ($mapel->isEmpty())
                                        <p class="mt-1 text-[11px] text-amber-700">Belum ada mata pelajaran yang ditugaskan. Penyimpanan tugas belum dapat dilakukan.</p>
                                    @endif
                                    @error('mata_pelajaran_id')
                                        <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="tugas-rombel" class="mb-1 block text-xs font-medium text-slate-600">Kelas</label>
                                    <select id="tugas-rombel" name="rombel" required @disabled(! $bisaKelola) @error('rombel') aria-invalid="true" @enderror
                                            class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30 disabled:bg-slate-50 disabled:text-slate-400">
                                        <option value="">Pilih kelas...</option>
                                        @foreach ($pasangan as $item)
                                            <option value="{{ $item['kelas'].'|'.$item['jurusan'] }}"
                                                    data-mapel="{{ $item['mata_pelajaran_id'] }}"
                                                    @selected($tugasRombel === $item['kelas'].'|'.$item['jurusan'])>
                                                Kelas {{ $item['kelas'] }}@if ($item['jurusan'] !== '') · {{ $item['jurusan'] }}@endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('rombel')
                                        <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="tugas-judul" class="mb-1 block text-xs font-medium text-slate-600">Judul Tugas</label>
                                    <input id="tugas-judul" type="text" name="judul" required maxlength="255"
                                           value="{{ old('judul') }}"
                                           @error('judul') aria-invalid="true" @enderror
                                           class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">
                                    @error('judul')
                                        <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="tugas-deskripsi" class="mb-1 block text-xs font-medium text-slate-600">Deskripsi</label>
                                    <textarea id="tugas-deskripsi" name="deskripsi" rows="4" maxlength="5000"
                                              @error('deskripsi') aria-invalid="true" @enderror
                                              class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">{{ old('deskripsi') }}</textarea>
                                    @error('deskripsi')
                                        <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="tugas-deadline" class="mb-1 block text-xs font-medium text-slate-600">Tenggat</label>
                                    <input id="tugas-deadline" type="datetime-local" name="deadline"
                                           value="{{ old('deadline') }}"
                                           @error('deadline') aria-invalid="true" @enderror
                                           class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-primary-50 focus:ring-2 focus:ring-primary-50/30">
                                    <p class="mt-1 text-[11px] text-slate-400">Kosongkan bila tugas tidak memiliki tenggat.</p>
                                    @error('deadline')
                                        <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="sm:col-span-2" data-file-uploader="add-tugas">
                                    <label for="tugas-file" class="mb-1 block text-xs font-medium text-slate-600">Lampiran File (Opsional)</label>
                                    <input id="tugas-file" name="file" type="file" @disabled(! $bisaKelola) @error('file') aria-invalid="true" @enderror accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.zip,.rar" class="sr-only">
                                    <div data-dropzone tabindex="0" role="button" aria-label="Unggah lampiran tugas" class="cursor-pointer rounded-xl border-2 border-dashed border-sky-200 bg-slate-50/60 px-4 py-5 text-center outline-none transition hover:border-[#2185F5] hover:bg-white focus-visible:border-[#2185F5] focus-visible:ring-2 focus-visible:ring-[#2185F5]/20">
                                        <div data-dropzone-icon class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-[#2185F5]/10 text-[#2185F5]"><x-lucide-cloud-upload class="h-5 w-5" /></div>
                                        <p class="mt-2 text-sm font-medium text-slate-700">Drag &amp; Drop file di sini</p>
                                        <p class="mt-0.5 text-xs text-slate-500">atau pilih file dari perangkat Anda</p>
                                        <button type="button" data-pilih-file class="mt-3 inline-flex items-center justify-center gap-1.5 rounded-lg bg-[#2185F5] px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#1b74d8]"><x-lucide-folder-open class="h-4 w-4" /> Pilih File</button>
                                        <p class="mt-3 text-[11px] text-slate-400">Format: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, JPG, PNG, ZIP, RAR &middot; Maksimal 10 MB</p>
                                    </div>
                                    <div data-new-file-card data-file-card class="mt-3 hidden rounded-xl border border-slate-200 bg-white p-3">
                                        <div class="flex items-start gap-3">
                                            <span data-file-type-icon class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#EAF3FF] text-[#2185F5]"><x-lucide-file class="h-5 w-5" /></span>
                                            <div class="min-w-0 flex-1">
                                                <p data-file-name class="truncate text-sm font-semibold text-slate-900"></p>
                                                <p class="mt-0.5 text-xs text-slate-500"><span data-file-ext></span> &middot; <span data-file-size></span></p>
                                                <p data-file-status class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-emerald-600"><x-lucide-circle-check class="h-3.5 w-3.5" /> <span>Berhasil dipilih</span></p>
                                            </div>
                                            <button type="button" data-batal-file class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600" aria-label="Batalkan pilihan file"><x-lucide-x class="h-4 w-4" /></button>
                                        </div>
                                        <div data-upload-progress class="mt-3 hidden">
                                            <div class="mb-1 flex items-center justify-between text-[11px] text-slate-500"><span data-upload-progress-label>Mengunggah...</span><span data-upload-progress-text>0%</span></div>
                                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100"><div data-upload-progress-bar class="h-full w-0 rounded-full bg-[#2185F5]"></div></div>
                                        </div>
                                    </div>
                                    <p data-file-error class="mt-2 hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700"></p>
                                    @error('file')
                                        <p data-server-file-error class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-white px-5 py-3.5">
                            <button type="button"
                                    data-tugas-close
                                    data-tugas-batal
                                    class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60 disabled:cursor-not-allowed disabled:opacity-60 disabled:active:scale-100">
                                Batal
                            </button>
                            <button type="submit"
                                    data-tugas-simpan
                                    @disabled(! $bisaTugas)
                                    class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-primary-60 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-70 active:scale-[.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:active:scale-100">
                                <x-lucide-save class="h-4 w-4" />
                                <span data-tugas-simpan-label>Simpan</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endunless

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
                        const mapelTerpilih = mapel.value;
                        const sesuai = semua.filter((option) => option.mapel === mapelTerpilih);

                        rombel.innerHTML = '<option value="">Pilih kelas...</option>';

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

                    saring();
                    mapel.addEventListener('change', saring);
                }

                init('tugas-mapel', 'tugas-rombel');
                init('materi-mapel', 'materi-rombel');
                init('tugas-edit-mapel', 'tugas-edit-rombel');
                init('materi-edit-mapel', 'materi-edit-rombel');

                function setRombelValue(mapelId, rombelId, mapelVal, rombelVal) {
                    const mapel = document.getElementById(mapelId);
                    const rombel = document.getElementById(rombelId);
                    if (!mapel || !rombel) return;
                    if (mapelVal) mapel.value = String(mapelVal);
                    mapel.dispatchEvent(new Event('change'));
                    if (rombelVal !== undefined && rombelVal !== null) rombel.value = String(rombelVal);
                }

                function anyModalOpen() {
                    return !!document.querySelector('[data-tugas-modal][data-open="true"], [data-materi-modal][data-open="true"], #materi-detail-modal[data-open="true"]');
                }

                function createModal(modalEl, opts) {
                    if (!modalEl) return null;
                    opts = opts || {};
                    let timer = null;
                    function syncBody() { document.body.classList.toggle('overflow-hidden', anyModalOpen()); }
                    function show(focusEl) {
                        window.clearTimeout(timer);
                        modalEl.removeAttribute('inert');
                        modalEl.dataset.open = 'true';
                        modalEl.setAttribute('aria-hidden', 'false');
                        syncBody();
                        window.requestAnimationFrame(() => {
                            const target = focusEl || modalEl.querySelector('[aria-invalid="true"]') || modalEl.querySelector('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])');
                            target && target.focus && target.focus();
                        });
                    }
                    function hide() {
                        if (modalEl.dataset.open !== 'true') return;
                        modalEl.dataset.open = 'false';
                        modalEl.setAttribute('aria-hidden', 'true');
                        syncBody();
                        window.clearTimeout(timer);
                        timer = window.setTimeout(() => { if (modalEl.dataset.open !== 'true') modalEl.setAttribute('inert', ''); }, 320);
                        if (opts.onHide) opts.onHide();
                    }
                    function animateIn(focusEl) {
                        if (modalEl.dataset.open === 'true') {
                            modalEl.dataset.open = 'false';
                            modalEl.setAttribute('aria-hidden', 'true');
                            modalEl.setAttribute('inert', '');
                            window.requestAnimationFrame(() => window.requestAnimationFrame(() => show(focusEl)));
                        } else { show(focusEl); }
                    }
                    if (modalEl.dataset.open === 'true') {
                        const initialFocus = modalEl.querySelector('[aria-invalid="true"]');
                        modalEl.dataset.open = 'false';
                        modalEl.setAttribute('aria-hidden', 'true');
                        modalEl.setAttribute('inert', '');
                        window.requestAnimationFrame(() => window.requestAnimationFrame(() => show(initialFocus)));
                    }
                    return { show, hide, animateIn, el: modalEl };
                }

                const mimeMapLampiran = @json(\App\Support\FileMateri::MIME);
                const batasUkuranMateri = {{ \App\Support\FileMateri::MAX_UKURAN_KB * 1024 }};
                const batasUkuranTugas = 10 * 1024 * 1024;

                function formatUkuranLampiran(byte) {
                    if (!Number.isFinite(byte) || byte < 0) return '-';
                    if (byte < 1024) return byte + ' B';
                    const satuan = ['KB', 'MB', 'GB'];
                    let nilai = byte / 1024;
                    let indeks = 0;
                    while (nilai >= 1024 && indeks < satuan.length - 1) { nilai /= 1024; indeks++; }
                    let teks = nilai >= 100 ? Math.round(nilai).toString() : (Math.round(nilai * 100) / 100).toFixed(2).replace(/\.?0+$/, '');
                    return teks.replace('.', ',') + ' ' + satuan[indeks];
                }

                function iconSvgLampiran(ext) {
                    const e = (ext || '').toLowerCase();
                    if (['jpg','jpeg','png'].includes(e)) return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>';
                    if (['pdf'].includes(e)) return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>';
                    if (['zip','rar'].includes(e)) return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><path d="M16 22h2a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v18"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><circle cx="10" cy="20" r="2"/><path d="M10 7V6"/><path d="M10 12v-1"/><path d="M10 18v-2"/></svg>';
                    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>';
                }

                function setupFileUploader(root, mode) {
                    if (!root) return null;
                    const tipe = (root.dataset.fileUploader || root.dataset.materiUploader || '');
                    const isTugas = tipe.indexOf('tugas') !== -1;
                    const batasUkuran = isTugas ? batasUkuranTugas : batasUkuranMateri;
                    const batasLabel = isTugas ? '10 MB' : '20 MB';
                    const input = root.querySelector('input[type="file"]');
                    const dropzone = root.querySelector('[data-dropzone]');
                    const pilihBtn = root.querySelector('[data-pilih-file]');
                    const newCard = root.querySelector('[data-new-file-card]');
                    const nameEl = root.querySelector('[data-file-name]');
                    const extEl = root.querySelector('[data-file-ext]');
                    const sizeEl = root.querySelector('[data-file-size]');
                    const iconEl = root.querySelector('[data-file-type-icon]');
                    const errorEl = root.querySelector('[data-file-error]');
                    const cancelBtn = root.querySelector('[data-batal-file]');
                    const progress = root.querySelector('[data-upload-progress]');
                    const progressBar = root.querySelector('[data-upload-progress-bar]');
                    const progressText = root.querySelector('[data-upload-progress-text]');
                    const serverError = root.querySelector('[data-server-file-error]');
                    const storedCard = root.querySelector('[data-edit-stored-card]');
                    const replaceArea = root.querySelector('[data-replace-area]');
                    const gantiBtn = root.querySelector('[data-ganti-baru]');
                    const batalGantiBtn = root.querySelector('[data-batal-ganti]');
                    const hapusBtn = root.querySelector('[data-hapus-lampiran]');
                    const hapusInput = root.querySelector('[data-hapus-lampiran-input]');
                    const storedMetaEls = {
                        nama: root.querySelector('[data-stored-file-nama], [data-materi-edit-file-nama]'),
                        format: root.querySelector('[data-stored-file-format], [data-materi-edit-file-format]'),
                        ukuran: root.querySelector('[data-stored-file-ukuran], [data-materi-edit-file-ukuran]'),
                        prev: root.querySelector('[data-stored-file-preview], [data-materi-edit-file-preview]'),
                        unduh: root.querySelector('[data-stored-file-unduh], [data-materi-edit-file-unduh]'),
                        icon: root.querySelector('[data-stored-file-icon]')
                    };
                    let hasStored = mode === 'edit' && storedCard && !storedCard.classList.contains('hidden');
                    let storedMeta = null;

                    function setHapus(flag) { if (hapusInput) hapusInput.value = flag ? '1' : '0'; }
                    function showError(msg) {
                        if (!errorEl) return;
                        errorEl.textContent = msg || '';
                        errorEl.classList.toggle('hidden', !msg);
                    }
                    function setProgress(pct, show) {
                        if (!progress) return;
                        progress.classList.toggle('hidden', !show);
                        if (progressBar) progressBar.style.width = (pct || 0) + '%';
                        if (progressText) progressText.textContent = Math.round(pct || 0) + '%';
                    }
                    function animateCard(card, show) {
                        if (!card) return;
                        if (show) {
                            card.classList.remove('hidden','is-leave');
                            card.classList.add('is-enter');
                            window.setTimeout(() => card && card.classList.remove('is-enter'), 240);
                        } else {
                            card.classList.add('is-leave');
                            window.setTimeout(() => { if (card) { card.classList.add('hidden'); card.classList.remove('is-leave'); } }, 180);
                        }
                    }
                    function clearSelection(silent) {
                        if (input) input.value = '';
                        if (input) input.setCustomValidity('');
                        if (newCard) { newCard.classList.add('hidden'); newCard.classList.remove('is-enter','is-leave'); }
                        setProgress(0, false);
                        if (!silent) showError('');
                    }
                    function applyFile(file) {
                        showError('');
                        if (serverError) serverError.classList.add('hidden');
                        if (!file) { clearSelection(true); return; }
                        const ext = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';
                        const mime = (file.type || '').toLowerCase();
                        const extOk = Object.prototype.hasOwnProperty.call(mimeMapLampiran, ext);
                        const mimeOk = extOk && mimeMapLampiran[ext].includes(mime);
                        if (!extOk || !mimeOk) {
                            clearSelection(true);
                            showError('Format file tidak didukung. Gunakan PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, JPG, PNG, ZIP, atau RAR.');
                            if (input) input.value = '';
                            return;
                        }
                        if (file.size > batasUkuran) {
                            clearSelection(true);
                            showError('Ukuran file melebihi batas maksimal ' + batasLabel + '.');
                            if (input) input.value = '';
                            return;
                        }
                        if (nameEl) nameEl.textContent = file.name;
                        if (extEl) extEl.textContent = ext ? ext.toUpperCase() : '-';
                        if (sizeEl) sizeEl.textContent = formatUkuranLampiran(file.size);
                        if (iconEl) iconEl.innerHTML = iconSvgLampiran(ext);
                        animateCard(newCard, true);
                        setProgress(0, false);
                        if (mode === 'edit') setHapus(false);
                    }
                    function setFileFromDt(files) {
                        if (!files || !files.length || !input) return;
                        try { const dt = new DataTransfer(); dt.items.add(files[0]); input.files = dt.files; } catch (e) { return; }
                        applyFile(files[0]);
                    }
                    function openPicker() { if (input && !input.disabled) input.click(); }

                    if (dropzone) {
                        dropzone.addEventListener('click', (e) => { if (e.target.closest('[data-pilih-file]') || e.target.closest('button')) return; openPicker(); });
                        dropzone.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openPicker(); } });
                        ['dragenter','dragover'].forEach(ev => dropzone.addEventListener(ev, (e) => { e.preventDefault(); e.stopPropagation(); dropzone.classList.add('is-dragover'); }));
                        ['dragleave','dragend','drop'].forEach(ev => dropzone.addEventListener(ev, (e) => { e.preventDefault(); e.stopPropagation(); if (ev !== 'drop') { if (e.relatedTarget && dropzone.contains(e.relatedTarget)) return; } dropzone.classList.remove('is-dragover'); }));
                        dropzone.addEventListener('drop', (e) => { const files = e.dataTransfer && e.dataTransfer.files; if (files && files.length) setFileFromDt(files); });
                    }
                    if (pilihBtn) pilihBtn.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); openPicker(); });
                    if (input) input.addEventListener('change', () => applyFile(input.files && input.files[0]));
                    if (cancelBtn) cancelBtn.addEventListener('click', () => { clearSelection(); });

                    function showReplaceArea(show) { if (replaceArea) replaceArea.classList.toggle('hidden', !show); }
                    function showStoredCard(show) { if (storedCard) storedCard.classList.toggle('hidden', !show); }
                    function setStored(meta) {
                        storedMeta = meta || null;
                        hasStored = !!(meta && meta.has);
                        if (storedMetaEls.nama) storedMetaEls.nama.textContent = meta?.nama || '-';
                        if (storedMetaEls.format) storedMetaEls.format.textContent = meta?.format || '-';
                        if (storedMetaEls.ukuran) storedMetaEls.ukuran.textContent = meta?.ukuran || '-';
                        if (storedMetaEls.icon) storedMetaEls.icon.innerHTML = iconSvgLampiran(meta?.format || '');
                        if (storedMetaEls.prev) { storedMetaEls.prev.href = meta?.preview || '#'; storedMetaEls.prev.classList.toggle('hidden', !meta?.preview); storedMetaEls.prev.classList.toggle('inline-flex', !!meta?.preview); }
                        if (storedMetaEls.unduh) { storedMetaEls.unduh.href = meta?.unduh || '#'; storedMetaEls.unduh.classList.toggle('hidden', !meta?.unduh); storedMetaEls.unduh.classList.toggle('inline-flex', !!meta?.unduh); }
                    }
                    function resetToStored(meta) {
                        if (meta) setStored(meta);
                        clearSelection(true);
                        showError('');
                        setHapus(false);
                        if (mode === 'edit') {
                            if (hasStored) { showStoredCard(true); showReplaceArea(false); if (batalGantiBtn) batalGantiBtn.classList.remove('hidden'); }
                            else { showStoredCard(false); showReplaceArea(true); if (batalGantiBtn) batalGantiBtn.classList.add('hidden'); }
                        }
                    }
                    if (gantiBtn) gantiBtn.addEventListener('click', () => { if (mode !== 'edit') return; setHapus(false); showError(''); showReplaceArea(true); if (batalGantiBtn) batalGantiBtn.classList.remove('hidden'); });
                    if (batalGantiBtn) batalGantiBtn.addEventListener('click', () => {
                        clearSelection();
                        setHapus(false);
                        if (mode === 'edit' && hasStored) { showReplaceArea(false); showStoredCard(true); }
                    });
                    if (hapusBtn) hapusBtn.addEventListener('click', async () => {
                        const namaLampiran = ((storedMetaEls.nama?.textContent || '').trim()) || 'Lampiran tugas';
                        const ok = window.saskansaDelete
                            ? await window.saskansaDelete.confirm({
                                title: 'Hapus lampiran tugas?',
                                text: `${namaLampiran} akan dihapus dari daftar setelah perubahan disimpan.`,
                            })
                            : false;
                        if (!ok) return;
                        clearSelection();
                        setHapus(true);
                        showError('');
                        showStoredCard(false);
                        showReplaceArea(true);
                        if (batalGantiBtn) batalGantiBtn.classList.add('hidden');
                    });

                    return {
                        reset() { clearSelection(); showError(''); setHapus(false); if (mode === 'add') { setProgress(0,false); } },
                        resetToStored(meta) { resetToStored(meta); },
                        setStored(meta) { setStored(meta); },
                        markUploading() { if (input && input.files && input.files[0]) { setProgress(12, true); window.setTimeout(() => setProgress(72, true), 180); } },
                        hasSelection() { return !!(input && input.files && input.files[0]); },
                        setHapus(flag) { setHapus(flag); }
                    };
                }

                const addMateriUploader = setupFileUploader(document.querySelector('[data-materi-uploader="add"]'), 'add');
                const editMateriUploader = setupFileUploader(document.querySelector('[data-materi-uploader="edit"]'), 'edit');
                const addTugasUploader = setupFileUploader(document.querySelector('[data-file-uploader="add-tugas"]'), 'add');
                const editTugasUploader = setupFileUploader(document.querySelector('[data-file-uploader="edit-tugas"]'), 'edit');

                const tambahMateriModal = document.getElementById('materi-tambah-modal');

                if (tambahMateriModal) {
                    const form = document.getElementById('materi-form');
                    const simpan = form.querySelector('[data-materi-form-simpan]');
                    const simpanLabel = form.querySelector('[data-materi-form-simpan-label]');
                    const batal = form.querySelector('[data-materi-form-batal]');
                    let trigger = null;
                    let submitting = false;

                    const setOpen = (open) => {
                        tambahMateriModal.dataset.open = open ? 'true' : 'false';
                        tambahMateriModal.setAttribute('aria-hidden', open ? 'false' : 'true');
                        if (open) tambahMateriModal.removeAttribute('inert');
                        else tambahMateriModal.setAttribute('inert', '');
                        document.body.classList.toggle('overflow-hidden', open);
                    };

                    const buka = (button) => {
                        trigger = button ?? document.activeElement;
                        submitting = false;
                        simpan.disabled = {{ $bisaMateri ? 'false' : 'true' }};
                        batal.disabled = false;
                        simpanLabel.textContent = 'Simpan';
                        form.reset();
                        addMateriUploader?.reset();
                        setOpen(true);
                        form.querySelector('input:not([type="hidden"]), select, textarea')?.focus();
                    };

                    const tutup = () => {
                        if (submitting) return;
                        setOpen(false);
                        if (trigger && document.contains(trigger)) trigger.focus();
                    };

                    document.querySelectorAll('[data-tambah-materi-trigger]').forEach((button) => {
                        button.addEventListener('click', () => buka(button));
                    });

                    tambahMateriModal.querySelectorAll('[data-materi-form-close]').forEach((button) => {
                        button.addEventListener('click', tutup);
                    });

                    tambahMateriModal.querySelector('[data-materi-form-backdrop]').addEventListener('click', tutup);

                    document.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape' && tambahMateriModal.dataset.open === 'true') tutup();
                    });

                    form.addEventListener('submit', (event) => {
                        if (submitting) {
                            event.preventDefault();
                            return;
                        }

                        submitting = true;
                        simpan.disabled = true;
                        batal.disabled = true;
                        simpanLabel.textContent = 'Menyimpan...';
                        addMateriUploader?.markUploading();
                    });

                    if (tambahMateriModal.dataset.open === 'true') {
                        addMateriUploader?.reset();
                        document.body.classList.add('overflow-hidden');
                        const firstError = tambahMateriModal.querySelector('[aria-invalid="true"]');
                        (firstError ?? tambahMateriModal.querySelector('input:not([type="hidden"]), select, textarea'))?.focus();
                    }
                }

                const detailModal = document.getElementById('materi-detail-modal');

                if (detailModal) {
                    const isi = (selector, nilai) => {
                        detailModal.querySelector(selector).textContent = nilai ?? '-';
                    };
                    const previews = detailModal.querySelectorAll('[data-materi-detail-pratinjau] iframe, [data-materi-detail-pratinjau] img');
                    const setOpen = (open) => {
                        detailModal.dataset.open = open ? 'true' : 'false';
                        detailModal.setAttribute('aria-hidden', open ? 'false' : 'true');
                        if (open) detailModal.removeAttribute('inert');
                        else detailModal.setAttribute('inert', '');
                        document.body.classList.toggle('overflow-hidden', open);
                    };
                    const tutup = () => {
                        previews.forEach((el) => { el.src = ''; });
                        setOpen(false);
                    };

                    document.querySelectorAll('[data-detail-materi-trigger]').forEach((button) => {
                        button.addEventListener('click', async () => {
                            isi('[data-materi-detail-judul]', 'Memuat...');
                            setOpen(true);

                            try {
                                const response = await fetch(button.dataset.detailUrl, {
                                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                });

                                if (!response.ok) throw new Error('Gagal memuat detail');

                                const payload = await response.json();
                                const detail = payload.detail ?? payload;
                                isi('[data-materi-detail-judul]', detail.judul);
                                isi('[data-materi-detail-mapel]', detail.mata_pelajaran);
                                isi('[data-materi-detail-guru]', detail.guru);
                                isi('[data-materi-detail-kelas]', detail.kelas);
                                isi('[data-materi-detail-tanggal]', detail.tanggal);
                                isi('[data-materi-detail-diperbarui]', detail.tanggal_diperbarui);
                                isi('[data-materi-detail-file]', detail.memiliki_file
                                    ? detail.nama_file + ' · ' + detail.format + ' · ' + detail.ukuran
                                    : 'Belum ada file');
                                isi('[data-materi-detail-deskripsi]', detail.deskripsi || 'Tidak ada deskripsi.');

                                const wadah = detailModal.querySelector('[data-materi-detail-pratinjau]');
                                const frameDetail = detailModal.querySelector('[data-materi-detail-frame]');
                                const gambarDetail = detailModal.querySelector('[data-materi-detail-image]');
                                wadah.classList.add('hidden');
                                frameDetail.classList.add('hidden');
                                gambarDetail.classList.add('hidden');
                                frameDetail.src = '';
                                gambarDetail.src = '';

                                if (detail.bisa_dipratinjau) {
                                    wadah.classList.remove('hidden');
                                    if (detail.format === 'PDF') {
                                        frameDetail.src = detail.preview_url;
                                        frameDetail.classList.remove('hidden');
                                    } else {
                                        gambarDetail.src = detail.preview_url;
                                        gambarDetail.classList.remove('hidden');
                                    }
                                }

                                const unduh = detailModal.querySelector('[data-materi-detail-unduh]');
                                unduh.classList.toggle('hidden', !detail.memiliki_file);
                                unduh.href = detail.unduh_url || '#';
                            } catch (error) {
                                isi('[data-materi-detail-judul]', 'Gagal memuat detail materi');
                            }
                        });
                    });

                    detailModal.querySelectorAll('[data-materi-close]').forEach((button) => button.addEventListener('click', tutup));
                    document.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape' && detailModal.dataset.open === 'true') tutup();
                    });
                }

                const modal = document.getElementById('tugas-modal');

                if (modal) {
                    const form = document.getElementById('tugas-form');
                    const simpan = form.querySelector('[data-tugas-simpan]');
                    const simpanLabel = form.querySelector('[data-tugas-simpan-label]');
                    const batal = form.querySelector('[data-tugas-batal]');
                    let trigger = null;
                    let submitting = false;

                    const setOpen = (open) => {
                        modal.dataset.open = open ? 'true' : 'false';
                        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
                        if (open) modal.removeAttribute('inert');
                        else modal.setAttribute('inert', '');
                        document.body.classList.toggle('overflow-hidden', open);
                    };

                    const buka = (button) => {
                        trigger = button ?? document.activeElement;
                        submitting = false;
                        simpan.disabled = {{ $bisaTugas ? 'false' : 'true' }};
                        batal.disabled = false;
                        simpanLabel.textContent = 'Simpan';
                        form.reset();
                        addTugasUploader?.reset();
                        setOpen(true);
                        modal.querySelector('input, select, textarea')?.focus();
                    };

                    const tutup = () => {
                        if (submitting) return;
                        setOpen(false);
                        if (trigger && document.contains(trigger)) trigger.focus();
                    };

                    document.querySelectorAll('[data-tambah-tugas-trigger]').forEach((button) => {
                        button.addEventListener('click', () => buka(button));
                    });

                    modal.querySelectorAll('[data-tugas-close]').forEach((button) => {
                        button.addEventListener('click', tutup);
                    });

                    modal.querySelector('[data-tugas-backdrop]').addEventListener('click', tutup);

                    document.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape' && modal.dataset.open === 'true') tutup();
                    });

                    form.addEventListener('submit', (event) => {
                        if (submitting) {
                            event.preventDefault();
                            return;
                        }

                        submitting = true;
                        simpan.disabled = true;
                        batal.disabled = true;
                        simpanLabel.textContent = 'Menyimpan...';
                        addTugasUploader?.markUploading();
                    });

                    if (modal.dataset.open === 'true') {
                        addTugasUploader?.reset();
                        document.body.classList.add('overflow-hidden');
                        const firstError = modal.querySelector('[aria-invalid="true"]');
                        (firstError ?? modal.querySelector('input, select, textarea'))?.focus();
                    }
                }
                // Edit Tugas (modal, tanpa pindah halaman)
                (function () {
                    const modalEl = document.getElementById('tugas-edit-modal');
                    if (!modalEl) return;
                    const form = document.getElementById('tugas-edit-form');
                    const tmpl = form?.dataset.updateActionTemplate || '';
                    const mapelTampil = document.getElementById('tugas-edit-mapel-tampil');
                    const mapelInput = document.getElementById('tugas-edit-mapel');
                    const judul = document.getElementById('tugas-edit-judul');
                    const desk = document.getElementById('tugas-edit-deskripsi');
                    const deadline = document.getElementById('tugas-edit-deadline');
                    const simpan = modalEl.querySelector('[data-tugas-edit-simpan]');
                    const simpanLabel = modalEl.querySelector('[data-tugas-edit-simpan-label]');
                    let trigger = null;
                    let submitting = false;
                    const ctrl = createModal(modalEl, {
                        onHide() {
                            if (trigger && document.contains(trigger)) trigger.focus();
                            try {
                                const url = new URL(window.location.href);
                                if (url.searchParams.get('edit')) { url.searchParams.delete('edit'); history.replaceState({}, '', url); }
                            } catch (e) {}
                        }
                    });
                    function buka(btn) {
                        trigger = btn || document.activeElement;
                        submitting = false;
                        if (simpanLabel) simpanLabel.textContent = 'Simpan';
                        const d = btn?.dataset || {};
                        if (tmpl && d.id) form.action = tmpl.replace('__ID__', d.id);
                        if (mapelTampil) mapelTampil.value = d.mapelNama || '';
                        if (mapelInput) mapelInput.value = d.mapelId || '';
                        if (judul) judul.value = d.judul || '';
                        if (desk) desk.value = d.deskripsi || '';
                        if (deadline) deadline.value = d.deadline || '';
                        setRombelValue('tugas-edit-mapel', 'tugas-edit-rombel', d.mapelId, d.rombel || '');
                        editTugasUploader?.resetToStored({
                            has: d.hasFile === '1',
                            nama: d.fileNama || '',
                            format: d.fileFormat || '',
                            ukuran: d.fileUkuran || '',
                            preview: d.filePreview || '',
                            unduh: d.fileUnduh || ''
                        });
                        ctrl?.animateIn(judul);
                    }
                    if (modalEl.dataset.open === 'true') {
                        editTugasUploader?.resetToStored({
                            has: {{ $formTugas?->file_path ? 'true' : 'false' }},
                            nama: @json($formTugas?->nama_file ?? ''),
                            format: @json($formTugas?->format_file ?? ''),
                            ukuran: @json($formTugas?->ukuran_file ?? ''),
                            preview: @json($formTugas?->file_path ? route('dashboard.guru.tugas-materi.tugas.preview', $formTugas) : ''),
                            unduh: @json($formTugas?->file_path ? route('dashboard.guru.tugas-materi.tugas.unduh', $formTugas) : '')
                        });
                    }
                    document.querySelectorAll('[data-edit-tugas-trigger]').forEach((btn) => btn.addEventListener('click', () => buka(btn)));
                    modalEl.querySelectorAll('[data-tugas-edit-close]').forEach((btn) => btn.addEventListener('click', () => { if (!submitting) ctrl?.hide(); }));
                    modalEl.querySelector('[data-tugas-edit-backdrop]')?.addEventListener('click', () => { if (!submitting) ctrl?.hide(); });
                    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modalEl.dataset.open === 'true' && !submitting) ctrl?.hide(); });
                    form?.addEventListener('submit', (e) => { if (submitting) { e.preventDefault(); return; } submitting = true; if (simpan) simpan.disabled = true; if (simpanLabel) simpanLabel.textContent = 'Menyimpan...'; editTugasUploader?.markUploading(); });
                })();

                // Edit Materi (modal, tanpa pindah halaman)
                (function () {
                    const modalEl = document.getElementById('materi-edit-modal');
                    if (!modalEl) return;
                    const form = document.getElementById('materi-edit-form');
                    const tmpl = form?.dataset.updateActionTemplate || '';
                    const mapelTampil = document.getElementById('materi-edit-mapel-tampil');
                    const mapelInput = document.getElementById('materi-edit-mapel');
                    const judul = document.getElementById('materi-edit-judul');
                    const desk = document.getElementById('materi-edit-deskripsi');
                    const tanggal = document.getElementById('materi-edit-tanggal');
                    const fileInput = document.getElementById('materi-edit-file');
                    const fileBox = document.getElementById('materi-edit-file-current');
                    const simpan = modalEl.querySelector('[data-materi-edit-simpan]');
                    const simpanLabel = modalEl.querySelector('[data-materi-edit-simpan-label]');
                    let trigger = null;
                    let submitting = false;
                    const ctrl = createModal(modalEl, {
                        onHide() {
                            if (trigger && document.contains(trigger)) trigger.focus();
                            try {
                                const url = new URL(window.location.href);
                                if (url.searchParams.get('edit')) { url.searchParams.delete('edit'); history.replaceState({}, '', url); }
                            } catch (e) {}
                        }
                    });
                    function buka(btn) {
                        trigger = btn || document.activeElement;
                        submitting = false;
                        if (simpanLabel) simpanLabel.textContent = 'Simpan';
                        const d = btn?.dataset || {};
                        if (tmpl && d.id) form.action = tmpl.replace('__ID__', d.id);
                        if (mapelTampil) mapelTampil.value = d.mapelNama || '';
                        if (mapelInput) mapelInput.value = d.mapelId || '';
                        if (judul) judul.value = d.judul || '';
                        if (desk) desk.value = d.deskripsi || '';
                        if (tanggal) tanggal.value = d.tanggal || '';
                        setRombelValue('materi-edit-mapel', 'materi-edit-rombel', d.mapelId, d.rombel || '');
                        editMateriUploader?.resetToStored({
                            has: d.hasFile === '1',
                            nama: d.fileNama || '',
                            format: d.fileFormat || '',
                            ukuran: d.fileUkuran || '',
                            preview: d.filePreview || '',
                            unduh: d.fileUnduh || ''
                        });
                        ctrl?.animateIn(judul);
                    }
                    if (modalEl.dataset.open === 'true') {
                        editMateriUploader?.resetToStored({
                            has: {{ $formMateri?->file_path ? 'true' : 'false' }},
                            nama: @json($formMateri?->nama_file ?? ''),
                            format: @json($formMateri?->format_file ?? ''),
                            ukuran: @json($formMateri?->ukuran_file ?? ''),
                            preview: @json($formMateri?->file_path ? route('dashboard.guru.tugas-materi.materi.preview', $formMateri) : ''),
                            unduh: @json($formMateri?->file_path ? route('dashboard.guru.tugas-materi.materi.unduh', $formMateri) : '')
                        });
                    }
                    document.querySelectorAll('[data-edit-materi-trigger]').forEach((btn) => btn.addEventListener('click', () => buka(btn)));
                    modalEl.querySelectorAll('[data-materi-form-close], [data-materi-edit-batal]').forEach((btn) => btn.addEventListener('click', (e) => { e.preventDefault?.(); if (!submitting) ctrl?.hide(); }));
                    modalEl.querySelector('[data-materi-form-backdrop]')?.addEventListener('click', () => { if (!submitting) ctrl?.hide(); });
                    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modalEl.dataset.open === 'true' && !submitting) ctrl?.hide(); });
                    form?.addEventListener('submit', (e) => { if (submitting) { e.preventDefault(); return; } submitting = true; if (simpan) simpan.disabled = true; if (simpanLabel) simpanLabel.textContent = 'Menyimpan...'; editMateriUploader?.markUploading(); });
                })();

            })();
        </script>
    </div>
@endsection
