@props(['paginator' => null, 'label' => 'data', 'ringkasan' => null, 'mini' => false])

@php
    // Paginasi server hanya muncul bila lebih dari satu halaman. Paginasi mini
    // tetap dirender lalu disembunyikan skrip bila ternyata hanya satu halaman.
    $adaPaginasi = $mini || ($paginator && method_exists($paginator, 'hasPages') && $paginator->hasPages());
@endphp

@if ($ringkasan || $adaPaginasi)
    <div data-table-footer class="mt-auto rounded-b-2xl border-t border-slate-200 bg-slate-50">
        @if ($ringkasan)
            <div class="grid grid-cols-1 gap-2 border-b border-slate-200/70 px-4 py-3 text-xs sm:grid-cols-2">
                @foreach (['tertinggi' => ['Rata-rata tertinggi', 'lucide-trending-up', 'text-emerald-600'], 'terendah' => ['Rata-rata terendah', 'lucide-trending-down', 'text-rose-600']] as $key => [$judul, $ikon, $warna])
                    @php $item = $ringkasan[$key] ?? null; @endphp
                    <div class="flex min-w-0 items-center gap-2">
                        <x-dynamic-component :component="$ikon" class="h-4 w-4 shrink-0 {{ $warna }}" />
                        <span class="text-slate-500">{{ $judul }}</span>
                        @if ($item)
                            <span class="truncate font-medium text-slate-800" title="{{ $item['nama'] }}">{{ $item['nama'] }}</span>
                            <span class="ml-auto shrink-0 font-semibold tabular-nums {{ $warna }}">{{ $item['rata_rata'] }}</span>
                        @else
                            <span class="ml-auto text-slate-300">—</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($adaPaginasi)
            <div class="px-4 py-3">
                @if ($mini)
                    <x-ui.mini-pagination :label="$label" />
                @elseif ($paginator)
                    <x-ui.server-pagination :paginator="$paginator" :label="$label" />
                @endif
            </div>
        @endif
    </div>
@endif
