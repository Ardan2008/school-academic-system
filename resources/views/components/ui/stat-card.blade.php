@props([
    'label',
    'value' => null,
    'suffix' => '',
    'decimals' => 0,
    'accent' => '#1E86FF',
    'icon' => null,
    'tone' => 'bg-blue-100 text-primary-60',
    'catatan' => null,
    'statKey' => null,
    'valueClass' => '',
    'count' => false,
    'layout' => 'horizontal',
])

@php
    $numerik = is_numeric($value);
    $pakaiCount = filter_var($count, FILTER_VALIDATE_BOOLEAN);
    $kosong = $value === null || $value === '';
    $tampil = $kosong ? '—' : (string) $value;
    if (! $kosong && $suffix !== '') {
        $tampil .= $suffix;
    }
    $tumpuk = $layout === 'stacked';
    $samping = $layout === 'aside';

    // Dibangun di sini agar atribut data selalu dipisah tepat satu spasi di HTML
    // akhir (pola ini diuji oleh test feature).
    $atributData = '';
    if ($statKey !== null && $statKey !== '') {
        $atributData .= ' data-stat="' . e($statKey) . '"';
    }
    if ($pakaiCount && $numerik) {
        $atributData .= ' data-count="' . e($value) . '" data-decimals="' . e($decimals) . '"';
    }
@endphp

{{-- Satu acuan hover untuk semua stat card dashboard Admin & Guru: terangkat 3px,
     bayangan lembut, ikon ke warna aksen. Dirender sekali per halaman. --}}
@once
    <style>
        .ui-stat-card {
            transition: transform .3s cubic-bezier(.22, 1, .36, 1),
                        box-shadow .3s cubic-bezier(.22, 1, .36, 1),
                        border-color .3s ease;
        }

        .ui-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px -12px rgba(15, 23, 42, .2);
            border-color: rgba(148, 163, 184, .35);
        }

        .ui-stat-icon { transition: background-color .3s ease, color .3s ease; }
        .ui-stat-card:hover .ui-stat-icon {
            background-color: var(--stat-accent, #1E86FF);
            color: #fff;
        }

        @media (prefers-reduced-motion: reduce) {
            .ui-stat-card { transition: none; }
        }
    </style>
@endonce

@if ($tumpuk)
    <div {{ $attributes->merge(['class' => 'ui-stat-card flex h-full flex-col rounded-2xl border border-slate-100 bg-white p-4 shadow-sm sm:p-5']) }} style="--stat-accent: {{ $accent }}">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
            <div class="min-w-0">
                <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400 sm:text-xs">{{ $label }}</p>
                <p{!! $atributData !!} class="mt-1.5 text-2xl font-bold tabular-nums text-slate-900 sm:mt-2 {{ $valueClass }}">{{ $tampil }}</p>
                @if ($catatan !== null && $catatan !== '')
                    <p class="mt-1 text-xs text-slate-400">{{ $catatan }}</p>
                @endif
            </div>

            <span class="ui-stat-icon order-first flex h-10 w-10 shrink-0 items-center justify-center rounded-xl sm:order-last sm:h-11 sm:w-11 {{ $tone }}">
                <x-dynamic-component :component="$icon" class="h-5 w-5 sm:h-5.5 sm:w-5.5" />
            </span>
        </div>
    </div>
@elseif ($samping)
    <div {{ $attributes->merge(['class' => 'ui-stat-card relative rounded-2xl border border-slate-100 bg-white p-5 shadow-md shadow-slate-200/60']) }} style="--stat-accent: {{ $accent }}">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $label }}</span>
                <span class="mt-0.5 block text-3xl font-bold tracking-tight text-slate-900">{{ $tampil }}</span>
                @if ($catatan !== null && $catatan !== '')
                    <span class="mt-1 block text-[11px] text-slate-400">{{ $catatan }}</span>
                @endif
            </div>
            <span class="ui-stat-icon flex h-12 w-12 shrink-0 items-center justify-center rounded-full {{ $tone }}">
                <x-dynamic-component :component="$icon" class="h-5 w-5" />
            </span>
        </div>
    </div>
@else
    <div {{ $attributes->merge(['class' => 'ui-stat-card flex h-full items-center gap-4 rounded-2xl border border-slate-100 bg-white p-4 shadow-md shadow-slate-200/60 sm:p-5']) }} style="--stat-accent: {{ $accent }}">
        <span class="ui-stat-icon flex h-12 w-12 shrink-0 items-center justify-center rounded-full {{ $tone }}">
            <x-dynamic-component :component="$icon" class="h-5 w-5" />
        </span>
        <div class="min-w-0">
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500 sm:text-xs">{{ $label }}</p>
            <p{!! $atributData !!} class="mt-0.5 text-2xl font-bold tabular-nums text-slate-900 {{ $valueClass }}">{{ $tampil }}</p>
            @if ($catatan !== null && $catatan !== '')
                <p class="mt-0.5 truncate text-[11px] text-slate-400" title="{{ $catatan }}">{{ $catatan }}</p>
            @endif
        </div>
    </div>
@endif
