@props([
    'text' => null,
    'lines' => 2,
    'class' => '',
])

@php
    $teks = trim((string) $text);

    // Kelas clamp ditulis literal (bukan disusun dari variabel) supaya Tailwind
    // mendeteksinya saat build dan tetap ikut ter-generate.
    $clamp = $lines >= 3 ? 'line-clamp-3' : 'line-clamp-2';
@endphp

@if ($teks !== '')
    <div data-expandable>
        <p
            data-expandable-content
            data-expandable-clamp="{{ $clamp }}"
            title="{{ $teks }}"
            class="{{ $clamp }} {{ $class }}"
        >{{ $teks }}</p>

        {{-- Tombol disembunyikan dulu; JS menampilkannya hanya bila teks
             benar-benar terpotong oleh clamp. Bila JS mati, teks tetap utuh
             dan bisa dibaca lewat tooltip `title`. --}}
        <button
            type="button"
            data-expandable-toggle
            class="mt-1 hidden text-xs font-semibold text-primary-60 transition hover:text-primary-70 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-60"
        >
            Lihat selengkapnya
        </button>
    </div>
@endif
