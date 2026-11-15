@extends('layouts.dashboard')

@section('title', 'Hasil Pencarian')

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        <div class="rounded-2xl border border-slate-100 bg-white p-4 sm:p-5 shadow-sm">
            <form action="{{ route('dashboard.search') }}" method="GET" class="flex items-center gap-3" id="page-search-form">
                <x-lucide-search class="h-4 w-4 shrink-0 text-blue-500" />
                <input
                    type="search"
                    name="q"
                    id="page-search-input"
                    value="{{ $q }}"
                    autocomplete="off"
                    placeholder="Cari menu, data, atau informasi..."
                    class="w-full border-0 bg-transparent text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-0 [&::-webkit-search-cancel-button]:appearance-none [&::-webkit-search-decoration]:appearance-none"
                />
                <button
                    type="button"
                    id="page-search-clear"
                    aria-label="Bersihkan pencarian"
                    class="{{ filled($q) ? '' : 'hidden' }} shrink-0 rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 focus-visible:outline-none"
                >
                    <x-lucide-x class="h-4 w-4" />
                </button>
                <button
                    type="submit"
                    class="rounded-xl bg-primary-60 px-4 py-2 text-xs font-semibold text-white transition hover:bg-primary-70 shadow-sm"
                >
                    Cari
                </button>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5 bg-slate-50/50">
                <p class="text-sm font-semibold text-slate-800">Hasil Pencarian</p>
                <p class="text-xs text-slate-500 font-medium">{{ count($results) }} hasil ditemukan untuk "<span class="text-slate-800">{{ $q }}</span>"</p>
            </div>

            @if (empty($results))
                <div class="flex flex-col items-center justify-center px-5 py-14 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 ring-8 ring-slate-50/50 mb-3">
                        <x-lucide-search-x class="h-7 w-7 text-slate-400" />
                    </span>
                    <p class="text-base font-semibold text-slate-800">Tidak ada hasil yang ditemukan</p>
                    <p class="mt-1 max-w-sm text-xs leading-relaxed text-slate-400">
                        Tidak ada data yang cocok dengan kata kunci "{{ $q }}". Coba gunakan kata kunci lain yang relevan dengan hak akses {{ ucfirst($role) }}.
                    </p>
                </div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($results as $item)
                        <a href="{{ $item['url'] }}" class="flex items-center gap-3.5 px-5 py-3.5 transition hover:bg-blue-50/50 group">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 transition group-hover:scale-105">
                                <x-dynamic-component :component="'lucide-'.($item['icon'] ?? 'search')" class="h-4 w-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-800 group-hover:text-blue-700 transition">{{ $item['label'] }}</p>
                                <span class="inline-block text-[11px] font-medium text-slate-400">{{ $item['group'] ?? 'Hasil' }}</span>
                            </div>
                            <x-lucide-chevron-right class="h-4 w-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-blue-500" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const pageInput = document.getElementById('page-search-input');
                const pageClear = document.getElementById('page-search-clear');
                if (!pageInput || !pageClear) return;

                pageInput.addEventListener('input', () => {
                    pageClear.classList.toggle('hidden', pageInput.value.trim().length === 0);
                });

                pageClear.addEventListener('click', () => {
                    pageInput.value = '';
                    pageClear.classList.add('hidden');
                    pageInput.focus();
                });
            });
        </script>
    @endpush
@endsection
