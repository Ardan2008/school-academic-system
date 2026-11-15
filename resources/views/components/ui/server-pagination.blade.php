{{--
    Paginasi sisi server: tampilan disamakan dengan `<x-ui.mini-pagination>`,
    tetapi tautannya berupa URL halaman baru (penyaringan & pembagian data
    dilakukan di server, bukan di browser).
--}}
@props(['paginator', 'label' => 'data'])

@php
    $total = $paginator->total();
    $halamanAktif = $paginator->currentPage();
    $halamanAkhir = $paginator->lastPage();

    // Nomor halaman yang ditampilkan: halaman pertama, terakhir, dan jendela di
    // sekitar halaman aktif. Jarak yang terlewat disingkat dengan elipsis.
    $nomor = [];
    if ($halamanAkhir > 0) {
        $kandidat = collect([1, $halamanAkhir, $halamanAktif - 1, $halamanAktif, $halamanAktif + 1])
            ->filter(fn ($p) => $p >= 1 && $p <= $halamanAkhir)
            ->unique()
            ->sort()
            ->values();

        $sebelumnya = null;
        foreach ($kandidat as $p) {
            if ($sebelumnya !== null && $p - $sebelumnya > 1) {
                $nomor[] = null;
            }
            $nomor[] = $p;
            $sebelumnya = $p;
        }
    }
@endphp

<div class="flex flex-col items-start justify-between gap-2 text-xs sm:flex-row sm:items-center sm:gap-3">
    <span class="text-slate-500">
        @if ($total > 0)
            Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $total }} {{ $label }}
        @else
            Tidak ada {{ $label }}
        @endif
    </span>

    @if ($halamanAkhir > 1)
        <div class="flex w-full items-center justify-between gap-1 sm:w-auto sm:justify-start">
            @if ($halamanAktif > 1)
                <a href="{{ $paginator->url($halamanAktif - 1) }}" rel="prev" aria-label="Halaman sebelumnya"
                   class="flex h-7 w-7 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">
                    <x-lucide-chevron-left class="h-3.5 w-3.5" />
                </a>
            @else
                <span aria-disabled="true" class="flex h-7 w-7 items-center justify-center rounded-md text-slate-300 opacity-60">
                    <x-lucide-chevron-left class="h-3.5 w-3.5" />
                </span>
            @endif

            <div class="flex items-center gap-1">
                @foreach ($nomor as $p)
                    @if ($p === null)
                        <span class="px-1 text-slate-400">…</span>
                    @elseif ($p === $halamanAktif)
                        <span aria-current="page"
                              class="flex min-w-7 items-center justify-center rounded-md bg-primary-60 px-1.5 py-1 text-center text-[11px] font-semibold text-white sm:text-xs">
                            {{ $p }}
                        </span>
                    @else
                        <a href="{{ $paginator->url($p) }}"
                           class="flex min-w-7 items-center justify-center rounded-md px-1.5 py-1 text-center text-[11px] text-slate-600 transition hover:bg-slate-100 sm:text-xs">
                            {{ $p }}
                        </a>
                    @endif
                @endforeach
            </div>

            @if ($halamanAktif < $halamanAkhir)
                <a href="{{ $paginator->url($halamanAktif + 1) }}" rel="next" aria-label="Halaman berikutnya"
                   class="flex h-7 w-7 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">
                    <x-lucide-chevron-right class="h-3.5 w-3.5" />
                </a>
            @else
                <span aria-disabled="true" class="flex h-7 w-7 items-center justify-center rounded-md text-slate-300 opacity-60">
                    <x-lucide-chevron-right class="h-3.5 w-3.5" />
                </span>
            @endif
        </div>
    @endif
</div>
