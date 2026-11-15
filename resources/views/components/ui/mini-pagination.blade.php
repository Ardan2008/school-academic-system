@props(['label' => 'data'])

<div class="flex flex-col items-start justify-between gap-2 text-xs sm:flex-row sm:items-center sm:gap-3" data-pagination data-label="{{ $label }}">
    <span data-info class="text-slate-500"></span>

    <div class="flex w-full items-center justify-between gap-1 sm:w-auto sm:justify-start">
        <button type="button" data-prev
                class="flex h-7 w-7 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 disabled:text-slate-300 disabled:opacity-60 disabled:hover:bg-transparent">
            <x-lucide-chevron-left class="h-3.5 w-3.5" />
        </button>

        <div class="flex items-center gap-1" data-pages></div>

        <button type="button" data-next
                class="flex h-7 w-7 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 disabled:text-slate-300 disabled:opacity-60 disabled:hover:bg-transparent">
            <x-lucide-chevron-right class="h-3.5 w-3.5" />
        </button>
    </div>
</div>