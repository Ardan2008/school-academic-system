@php
    $faqs = [
        [
            'q' => 'Apa itu SASKANSA?',
            'a' => 'SASKANSA adalah portal internal sekolah untuk mengelola data akademik secara terstruktur dan terintegrasi, mulai dari data siswa dan guru, kelas, mata pelajaran, jadwal, penilaian, hingga laporan nilai.',
        ],
        [
            'q' => 'Siapa saja yang dapat memakai SASKANSA?',
            'a' => 'Ada tiga role, yaitu Admin, Guru, dan Siswa. Admin masuk memakai email, Guru memakai NIP, dan Siswa memakai NIS. Setiap role hanya melihat menu dan aksi sesuai kewenangannya.',
        ],
        [
            'q' => 'Bagaimana cara mendapatkan akun untuk login?',
            'a' => 'Akun dibuat oleh Admin lewat menu Data Siswa, Data Guru, atau Manajemen Akun. Untuk banyak data sekaligus, Admin bisa mengunggah CSV memakai template yang sudah disediakan; setiap baris diperiksa dan baris yang gagal bisa diunduh kembali sebagai daftar kesalahan. Saat pertama kali login, Guru dan Siswa wajib membuat password baru minimal 8 karakter yang memuat huruf besar dan angka.',
        ],
        [
            'q' => 'Bagaimana menulis kelas di SASKANSA?',
            'a' => 'Kelas ditulis dengan angka, contoh 12 untuk satu tingkat satu rombel, atau 12-1 kalau tingkat tersebut dibagi menjadi beberapa rombel. Jurusan disimpan terpisah dari kelas, jadi tidak perlu ditulis ulang di dalam nama kelas.',
        ],
        [
            'q' => 'Apa arti status akun di Data Siswa?',
            'a' => 'Setiap siswa berstatus Aktif, Nonaktif, atau Lulus. Akun Nonaktif dan Lulus tidak dapat login sampai Admin mengubahnya kembali menjadi Aktif, sehingga berguna untuk siswa yang sudah lulus, pindah sekolah, atau keluar.',
        ],
        [
            'q' => 'Bagaimana cara lupa password atau tidak bisa login?',
            'a' => 'Ajukan Atur Ulang Password lewat halaman login, kemudian Admin memeriksa pengajuan tersebut. Jika disetujui, tautan token dikirim ke email yang terdaftar dan hanya berlaku 30 menit untuk satu kali pemakaian. Untuk kendala lain, gunakan tombol Hubungi Tim IT Sekolah di samping daftar ini.',
        ],
    ];
@endphp

<style>
    /* Scroll Reveal */
    #faq.reveal-ready [data-reveal] {
        opacity: 0;
        transform: translateY(28px);
        filter: blur(6px);
        transition:
            opacity .8s cubic-bezier(.22, 1, .36, 1) var(--d, 0s),
            transform .8s cubic-bezier(.22, 1, .36, 1) var(--d, 0s),
            filter .8s cubic-bezier(.22, 1, .36, 1) var(--d, 0s);
        will-change: opacity, transform;
    }

    /* Kartu bantuan masuk dari kanan hanya saat 2 kolom (desktop).
       Saat bertumpuk, masuk dari bawah supaya tidak menyebabkan scroll horizontal. */
    @media (min-width: 1024px) {
        #faq.reveal-ready [data-reveal="right"] {
            transform: translateX(32px) scale(.97);
        }
    }

    #faq.reveal-ready [data-reveal].is-visible {
        opacity: 1;
        transform: none;
        filter: none;
    }

    /* Jeda daftar FAQ: tanpa jeda di HP, bertahap mulai tablet */
    #faq .faq-item {
        --d: 0s;
    }

    @media (min-width: 640px) {
        #faq .faq-item { --d: var(--dl, 0s); }
    }

    /* FAQ Answer Text */
    #faq [data-faq-content] p {
        opacity: 0;
        transform: translateY(-8px);
        transition: opacity .35s ease, transform .35s ease;
    }

    #faq [data-faq][data-state="open"] [data-faq-content] p {
        opacity: 1;
        transform: none;
        transition-delay: .12s;
    }

    /* Badge Dot Pulse */
    @keyframes faq-pulse {
        0%   { transform: scale(1);   opacity: .6; }
        100% { transform: scale(3.2); opacity: 0; }
    }

    #faq .faq-dot::after {
        content: "";
        position: absolute;
        inset: 0;
        border-radius: 9999px;
        background: currentColor;
        animation: faq-pulse 1.8s ease-out infinite;
    }

    /* CTA Icon Wiggle */
    @keyframes faq-wiggle {
        0%, 100% { transform: rotate(0); }
        25%      { transform: rotate(-14deg); }
        50%      { transform: rotate(10deg); }
        75%      { transform: rotate(-6deg); }
    }

    /* Efek hover hanya untuk perangkat yang punya hover */
    @media (hover: hover) {
        #faq .faq-cta:hover .faq-headset {
            animation: faq-wiggle .6s ease-in-out;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        #faq.reveal-ready [data-reveal] {
            opacity: 1;
            transform: none;
            filter: none;
            transition: none;
        }

        #faq [data-faq-content] p {
            opacity: 1;
            transform: none;
            transition: none;
        }

        #faq .faq-dot::after,
        #faq .faq-cta:hover .faq-headset {
            animation: none;
        }
    }

    /* Popup Hubungi Tim IT (sistem sama seperti popup Lupa Password) */
    .faq-modal {
        opacity: 0;
        pointer-events: none;
        transition: opacity .3s ease;
    }

    .faq-modal[data-open="true"] {
        opacity: 1;
        pointer-events: auto;
    }

    .faq-modal-backdrop {
        opacity: 0;
        transition: opacity .3s ease;
    }

    .faq-modal[data-open="true"] .faq-modal-backdrop {
        opacity: 1;
    }

    .faq-modal-panel {
        opacity: 0;
        transform: translateY(20px) scale(.97);
        transition: opacity .3s cubic-bezier(.22,1,.36,1), transform .3s cubic-bezier(.22,1,.36,1);
    }

    .faq-modal[data-open="true"] .faq-modal-panel {
        opacity: 1;
        transform: translateY(0) scale(1);
    }

    /* Sembunyikan scrollbar panel tanpa mematikan scroll; petunjuk halus di
       tepi atas/bawah hanya muncul selama masih ada isi yang bisa di-scroll. */
    .faq-modal-panel {
        scrollbar-width: none;      /* Firefox */
        -ms-overflow-style: none;   /* Edge lama */
        background-image:
            linear-gradient(to bottom, #fff 30%, rgba(255, 255, 255, 0)),
            linear-gradient(to top, #fff 30%, rgba(255, 255, 255, 0)),
            radial-gradient(farthest-side at 50% 0, rgba(15, 23, 42, .16), rgba(15, 23, 42, 0)),
            radial-gradient(farthest-side at 50% 100%, rgba(15, 23, 42, .16), rgba(15, 23, 42, 0));
        background-position: top, bottom, top, bottom;
        background-size: 100% 24px, 100% 24px, 100% 9px, 100% 9px;
        background-repeat: no-repeat;
        background-attachment: local, local, scroll, scroll;
    }

    .faq-modal-panel::-webkit-scrollbar {
        display: none;              /* Chrome, Safari, Edge */
    }

    @media (prefers-reduced-motion: reduce) {
        .faq-modal, .faq-modal-backdrop, .faq-modal-panel {
            transition: none;
        }
    }
</style>

{{-- overflow-x-clip: mencegah scroll horizontal akibat animasi masuk dari samping --}}
<section id="faq" class="scroll-mt-16 overflow-x-clip bg-[#FDFDFD]">

    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-16 lg:py-20">

        <div class="grid items-start gap-10 lg:grid-cols-2 lg:gap-12">

            <div>

                <div data-reveal style="--d: 0s">
                    <div
                        class="mb-4 inline-flex items-center gap-2
                               rounded-full bg-blue-50 px-3 py-1.5
                               text-[11px] font-medium uppercase
                               tracking-wider text-blue-600 sm:mb-5"
                    >
                        <span
                            class="faq-dot relative h-1.5 w-1.5
                                   rounded-full bg-blue-600 text-blue-600"
                        ></span>

                        Digitalisasi Pendidikan
                    </div>
                </div>

                <h2
                    data-reveal
                    style="--d: .1s"
                    class="max-w-md text-[1.75rem] font-semibold
                           leading-tight tracking-tight
                           text-gray-900 sm:text-4xl"
                >
                    Frequently Asked
                    <span class="block">
                        Questions
                    </span>
                </h2>

                <div class="mt-8 space-y-3 sm:mt-10">

                    @foreach ($faqs as $faq)
                        <div
                            data-reveal
                            style="--dl: {{ .2 + $loop->index * .1 }}s"
                            class="faq-item"
                        >
                            <details
                                data-faq
                                data-state="{{ $loop->first ? 'open' : 'closed' }}"
                                {{ $loop->first ? 'open' : '' }}
                                class="group overflow-hidden rounded-2xl
                                       border border-gray-100 bg-white
                                       shadow-sm
                                       transition-[transform,box-shadow]
                                       duration-300 ease-out
                                       hover:-translate-y-0.5 hover:shadow-md
                                       data-[state=open]:shadow-md"
                            >

                                <summary
                                    class="flex min-h-14 cursor-pointer list-none
                                           items-center justify-between gap-4
                                           px-4 py-4 text-sm font-semibold
                                           text-gray-900 outline-none
                                           focus-visible:ring-2
                                           focus-visible:ring-inset
                                           focus-visible:ring-blue-500
                                           sm:px-5 sm:py-5 sm:text-xs
                                           [&::-webkit-details-marker]:hidden"
                                >
                                    <span
                                        class="min-w-0 leading-snug
                                               transition-colors duration-300
                                               group-data-[state=open]:text-blue-600"
                                    >
                                        {{ $faq['q'] }}
                                    </span>

                                    <span
                                        class="flex h-8 w-8 shrink-0
                                               items-center justify-center
                                               rounded-full bg-gray-50
                                               text-gray-400
                                               transition-all duration-300
                                               ease-[cubic-bezier(.34,1.56,.64,1)]
                                               group-hover:scale-110
                                               group-data-[state=open]:bg-blue-50
                                               group-data-[state=open]:text-blue-600
                                               sm:h-7 sm:w-7"
                                    >
                                        <x-lucide-chevron-down
                                            class="h-4 w-4
                                                   transition-transform
                                                   duration-500
                                                   ease-[cubic-bezier(.34,1.56,.64,1)]
                                                   group-data-[state=open]:rotate-180"
                                        />
                                    </span>
                                </summary>

                                <div data-faq-content class="overflow-hidden">
                                    <div class="px-4 pb-4 sm:px-5 sm:pb-5">
                                        <p class="text-[13px] leading-relaxed text-gray-500
                                                  sm:text-xs sm:leading-5">
                                            {{ $faq['a'] }}
                                        </p>
                                    </div>
                                </div>

                            </details>
                        </div>
                    @endforeach

                </div>
            </div>

            <div data-reveal="right" style="--d: .25s" class="self-start lg:sticky lg:top-20">

                <div
                    class="group/card overflow-hidden rounded-2xl
                           border border-gray-100 bg-white
                           shadow-sm transition-shadow duration-500
                           hover:shadow-xl hover:shadow-blue-900/5"
                >

                    <div class="overflow-hidden">
                        <img
                            src="{{ asset('image/faq/faq-ilustrasi.webp') }}"
                            alt="Ilustrasi bantuan dan FAQ SASKANSA"
                            loading="lazy"
                            decoding="async"
                            class="h-auto w-full object-cover
                                   transition-transform duration-700
                                   ease-out
                                   group-hover/card:scale-105"
                        >
                    </div>

                    <div class="p-5 sm:p-6">

                        <h3 class="text-base font-semibold text-gray-900">
                            Kendala Akses atau Lupa Password?
                        </h3>

                        <p class="mt-2 text-[13px] leading-relaxed text-gray-500
                                  sm:text-xs sm:leading-5">
                            Tim IT Sekolah siap membantu jika Anda
                            mengalami masalah saat login, lupa kata
                            sandi, atau memerlukan bantuan teknis
                            lainnya di portal SASKANSA.
                        </p>

                        <button
                            type="button"
                            id="faq-it-btn"
                            class="faq-cta group relative mt-4 inline-flex
                                   w-full cursor-pointer items-center justify-center gap-2
                                   overflow-hidden rounded-lg border
                                   border-transparent bg-blue-600
                                   px-4 py-3 text-[13px] font-medium text-white
                                   transition duration-300
                                   hover:-translate-y-0.5
                                   hover:border-blue-300 hover:bg-white
                                   hover:text-blue-600 hover:shadow-lg
                                   hover:shadow-blue-600/25
                                   focus-visible:outline-2
                                   focus-visible:outline-offset-2
                                    focus-visible:outline-blue-500
                                    active:translate-y-0 active:scale-[0.98]
                                    sm:w-auto"
                        >
                            <x-lucide-headset class="faq-headset relative h-4 w-4 shrink-0" />

                            <span class="relative">Hubungi Tim IT Sekolah</span>

                            <x-lucide-arrow-right
                                class="relative h-3.5 w-3.5 shrink-0
                                       transition-transform
                                       duration-200
                                       group-hover:translate-x-1"
                            />
                        </button>

                    </div>
                </div>

            </div>

        </div>
    </div>

    @php
        // Definisi NIP/NIS per role diambil dari LoginIdentifier supaya sama
        // dengan form login dan pop-up lupa password, tidak ada daftar terpisah.
        $itAllRoles = \App\Support\LoginIdentifier::frontendPayload();
        $itDefaultRole = \App\Support\LoginIdentifier::RESETTABLE_ROLES[0];
        $itRoles = array_intersect_key($itAllRoles, array_flip(\App\Support\LoginIdentifier::RESETTABLE_ROLES));
    @endphp

    <div
        id="faq-it-modal"
        data-open="false"
        aria-hidden="true"
        inert
        class="faq-modal fixed inset-0 z-70 flex items-center justify-center p-4"
    >
        <div data-faq-backdrop class="faq-modal-backdrop absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>

        <div
            data-faq-panel
            role="dialog"
            aria-modal="true"
            aria-labelledby="faq-it-title"
            class="faq-modal-panel relative z-10 max-h-[85dvh] w-full max-w-sm overflow-y-auto overscroll-contain
                   rounded-2xl bg-white p-5 text-left shadow-2xl sm:max-w-2xl sm:p-6"
        >
            <button
                type="button"
                data-faq-close
                class="absolute right-3 top-3 flex min-h-10 min-w-10 items-center justify-center
                       rounded-full p-2 text-slate-400
                       transition hover:bg-slate-50 hover:text-slate-600
                       focus-visible:outline-2
                       focus-visible:outline-offset-2 focus-visible:outline-slate-400"
                aria-label="Tutup"
            >
                <x-lucide-x class="h-4.5 w-4.5" />
            </button>

            <div class="mb-4 flex flex-col items-center gap-2.5 text-center sm:flex-row sm:items-start sm:gap-3.5 sm:pr-8 sm:text-left">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                    <x-lucide-headset class="h-5 w-5" />
                </div>

                <div>
                    <h3 id="faq-it-title" class="text-base font-semibold text-slate-900">
                        Hubungi Tim IT Sekolah
                    </h3>
                    <p class="mt-1 text-sm leading-relaxed text-slate-500">
                        Pilih role kamu, lalu tuliskan NIP/NIS dan kendala yang kamu alami.
                        Tim IT Sekolah akan menghubungi kamu pada hari dan jam kerja.
                    </p>
                </div>
            </div>

            <form id="faq-it-form" class="mt-4 space-y-3.5 sm:grid sm:grid-cols-2 sm:gap-x-4 sm:gap-y-3.5 sm:space-y-0" novalidate>

                <div class="sm:col-start-1 sm:row-start-1">
                    <label for="faq-it-role" class="mb-1.5 block text-sm font-semibold text-slate-800">
                        Role
                    </label>

                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <x-lucide-user-round-cog class="h-4.5 w-4.5" />
                        </span>

                        <select
                            id="faq-it-role"
                            name="role"
                            class="w-full appearance-none rounded-xl border border-slate-200 bg-white py-3 pl-10 pr-10
                                   text-base text-slate-900 outline-none transition
                                   focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10
                                   sm:text-sm"
                        >
                            <option value="">Pilih role</option>
                            @foreach (\App\Support\LoginIdentifier::frontendPayload() as $roleKey => $roleMeta)
                                @if (in_array($roleKey, \App\Support\LoginIdentifier::RESETTABLE_ROLES, true))
                                    <option value="{{ $roleKey }}">{{ $roleMeta['label'] }}</option>
                                @endif
                            @endforeach
                        </select>

                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400">
                            <x-lucide-chevron-down class="h-4 w-4" />
                        </span>
                    </div>

                    <p data-faq-error="role" class="mt-1.5 hidden text-xs text-red-500"></p>
                </div>

                <div class="sm:col-start-1 sm:row-start-2">
                    <label for="faq-it-identifier" class="mb-1.5 block text-sm font-semibold text-slate-800">
                        <span data-faq-identifier-label>{{ $itRoles[$itDefaultRole]['input_label'] }}</span>
                    </label>

                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <x-lucide-id-card class="h-4.5 w-4.5" />
                        </span>

                        <input
                            type="text"
                            id="faq-it-identifier"
                            name="identifier"
                            placeholder="{{ $itRoles[$itDefaultRole]['placeholder'] }}"
                            inputmode="{{ $itRoles[$itDefaultRole]['input_mode'] }}"
                            autocomplete="off"
                            maxlength="{{ $itRoles[$itDefaultRole]['maxlength'] }}"
                            class="w-full rounded-xl border border-slate-200 bg-white py-3 pl-10 pr-4
                                   text-base text-slate-900 outline-none transition
                                   placeholder:text-slate-400
                                   focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10
                                   sm:text-sm"
                        >
                    </div>

                    <p data-faq-error="identifier" class="mt-1.5 hidden text-xs text-red-500"></p>
                </div>

                <div class="sm:col-start-1 sm:row-start-3">
                    <label for="faq-it-email" class="mb-1.5 block text-sm font-semibold text-slate-800">
                        Email
                    </label>

                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <x-lucide-mail class="h-4.5 w-4.5" />
                        </span>

                        <input
                            type="email"
                            id="faq-it-email"
                            name="email"
                            placeholder="Masukkan email kamu"
                            required
                            autocomplete="email"
                            class="w-full rounded-xl border border-slate-200 bg-white py-3 pl-10 pr-4
                                   text-base text-slate-900 outline-none transition
                                   placeholder:text-slate-400
                                   focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10
                                   sm:text-sm"
                        >
                    </div>

                    <p data-faq-error="email" class="mt-1.5 hidden text-xs text-red-500"></p>
                </div>

                <div class="sm:col-start-2 sm:row-span-3 sm:row-start-1 sm:flex sm:flex-col">
                    <label for="faq-it-pesan" class="mb-1.5 block text-sm font-semibold text-slate-800">
                        Kendala
                    </label>

                    <div class="relative sm:flex-1">
                        <span class="pointer-events-none absolute left-0 top-0 flex pl-3.5 pt-4 text-slate-400">
                            <x-lucide-message-square-text class="h-4.5 w-4.5" />
                        </span>

                        <textarea
                            id="faq-it-pesan"
                            name="pesan"
                            rows="3"
                            maxlength="1000"
                            placeholder="Contoh: Saya tidak bisa masuk memakai NIP 1234, sudah coba dua kali."
                            required
                            class="w-full resize-none rounded-xl border border-slate-200 bg-white py-3 pl-10 pr-4
                                   text-base text-slate-900 outline-none transition
                                   placeholder:text-slate-400
                                   focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10
                                   sm:h-full sm:min-h-47 sm:text-sm"
                        ></textarea>
                    </div>

                    <p data-faq-error="pesan" class="mt-1.5 hidden text-xs text-red-500"></p>
                </div>

                {{-- Honeypot: tidak pernah dilihat pengguna asli, hanya untuk menangkap bot pengisi form otomatis. --}}
                <div class="hidden" aria-hidden="true">
                    <label for="faq-it-website">Website</label>
                    <input type="text" id="faq-it-website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <button
                    type="submit"
                    data-faq-submit
                    class="flex w-full items-center justify-center gap-2 rounded-2xl border
                           border-transparent bg-blue-700 px-4 py-3.5
                           text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition
                           hover:border-blue-300 hover:bg-white hover:text-blue-600
                           focus-visible:outline-2
                           focus-visible:outline-offset-2 focus-visible:outline-blue-700
                           active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-70
                           disabled:border-transparent disabled:bg-blue-700 disabled:text-white
                           sm:col-span-2"
                >
                    <x-lucide-send data-faq-submit-icon class="h-4 w-4" />
                    <span data-faq-submit-text>Kirim Permintaan</span>
                </button>

            </form>

            <p class="mt-3.5 text-center text-xs text-slate-400">
                Permintaan akan ditindaklanjuti oleh Tim IT Sekolah pada hari dan jam kerja.
            </p>
        </div>
    </div>

    <div
        id="faq-it-success-modal"
        data-open="false"
        aria-hidden="true"
        inert
        class="faq-modal fixed inset-0 z-70 flex items-center justify-center p-4"
    >
        <div data-faq-backdrop class="faq-modal-backdrop absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>

        <div
            data-faq-panel
            role="dialog"
            aria-modal="true"
            aria-labelledby="faq-it-success-title"
            class="faq-modal-panel relative z-10 w-full max-w-sm rounded-2xl bg-white p-5 text-center shadow-2xl sm:p-6"
        >
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                <x-lucide-mail-check class="h-5 w-5" />
            </div>

            <h3 id="faq-it-success-title" class="text-base font-semibold text-slate-900">
                Permintaan Terkirim
            </h3>
            <p data-faq-success-text class="mt-1.5 text-sm leading-relaxed text-slate-500">
                Terima kasih! Permintaan bantuan sudah kami terima.
            </p>

            <button
                type="button"
                data-faq-close
                class="mt-5 w-full rounded-xl border border-transparent bg-blue-700 py-3
                       text-sm font-semibold text-white transition
                       hover:border-blue-300 hover:bg-white hover:text-blue-600
                       focus-visible:outline-2
                       focus-visible:outline-offset-2 focus-visible:outline-blue-700 sm:py-2.5"
            >
                Mengerti
            </button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const section = document.getElementById('faq');

            /* Scroll Reveal */
            const items = section.querySelectorAll('[data-reveal]');

            if ('IntersectionObserver' in window) {
                section.classList.add('reveal-ready');

                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

                items.forEach((el) => observer.observe(el));
            }

            /* Smooth Accordion */
            const DURATION = 300;
            const EASING = 'ease-in-out';
            const ACCORDION = false;
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            section.querySelectorAll('[data-faq]').forEach((details) => {
                const summary = details.querySelector('summary');
                const content = details.querySelector('[data-faq-content]');
                let animation = null;

                const animate = (from, to, onFinish) => {
                    if (animation) animation.cancel();

                    if (reduceMotion) {
                        onFinish();
                        return;
                    }

                    animation = content.animate(
                        { height: [`${from}px`, `${to}px`] },
                        { duration: DURATION, easing: EASING }
                    );

                    animation.onfinish = () => {
                        animation = null;
                        onFinish();
                    };
                    animation.oncancel = () => (animation = null);
                };

                const open = () => {
                    if (ACCORDION) {
                        section
                            .querySelectorAll('[data-faq][data-state="open"]')
                            .forEach((other) => {
                                if (other !== details) other.querySelector('summary').click();
                            });
                    }

                    const from = content.offsetHeight; // 0 jika sedang tertutup
                    details.open = true;
                    details.dataset.state = 'open';

                    animate(from, content.scrollHeight, () => {});
                };

                const close = () => {
                    const from = content.offsetHeight;
                    details.dataset.state = 'closed';

                    animate(from, 0, () => {
                        details.open = false;
                    });
                };

                summary.addEventListener('click', (e) => {
                    e.preventDefault();

                    if (details.dataset.state === 'open') {
                        close();
                    } else {
                        open();
                    }
                });
            });

            /* Popup Hubungi Tim IT Sekolah */
            const IT_ENDPOINT = @json(route('landing.hubungi-tim-it'));
            const IT_ROLES    = @json($itRoles);

            const itBtn        = document.getElementById('faq-it-btn');
            const itModal      = document.getElementById('faq-it-modal');
            const itForm       = document.getElementById('faq-it-form');
            const itRole       = document.getElementById('faq-it-role');
            const itIdentifier = document.getElementById('faq-it-identifier');
            const itIdLabel    = itForm.querySelector('[data-faq-identifier-label]');
            const itEmail      = document.getElementById('faq-it-email');
            const itPesan      = document.getElementById('faq-it-pesan');
            const itSubmit     = itForm.querySelector('[data-faq-submit]');
            const itSubmitText = itForm.querySelector('[data-faq-submit-text]');
            const itSubmitIcon = itForm.querySelector('[data-faq-submit-icon]');
            const itSuccess    = document.getElementById('faq-it-success-modal');
            const itSuccessText = itSuccess.querySelector('[data-faq-success-text]');

            const itFields = { role: itRole, identifier: itIdentifier, email: itEmail, pesan: itPesan };

            const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

            const openItModal = (modal) => {
                modal.removeAttribute('inert');
                modal.setAttribute('aria-hidden', 'false');
                requestAnimationFrame(() => { modal.dataset.open = 'true'; });
            };

            const closeItModal = (modal) => {
                modal.dataset.open = 'false';
                modal.setAttribute('aria-hidden', 'true');
                const finish = () => modal.setAttribute('inert', '');
                modal.addEventListener('transitionend', finish, { once: true });
                setTimeout(finish, 400);
            };

            const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
            const isValidCode = (value) => /^[A-Za-z0-9\-\/]+$/.test(value);

            const setItError = (field, message) => {
                const input = itFields[field];
                const error = itForm.querySelector(`[data-faq-error="${field}"]`);

                input.classList.toggle('border-red-400', Boolean(message));
                input.classList.toggle('ring-4', Boolean(message));
                input.classList.toggle('ring-red-500/10', Boolean(message));

                error.textContent = message || '';
                error.classList.toggle('hidden', !message);
            };

            const clearItErrors = () => {
                Object.keys(itFields).forEach((field) => setItError(field, null));
            };

            const setItLoading = (isLoading) => {
                itSubmit.disabled = isLoading;
                itSubmitText.textContent = isLoading ? 'Mengirim...' : 'Kirim Permintaan';
                itSubmitIcon.classList.toggle('animate-pulse', isLoading);
            };

            // Role menentukan field identitas: Guru memakai NIP, Siswa memakai NIS.
            const applyItRole = (role) => {
                const cfg = IT_ROLES[role];

                itIdentifier.value = '';
                setItError('identifier', null);

                if (!cfg) {
                    itIdLabel.textContent = 'NIP / NIS';
                    itIdentifier.placeholder = 'Pilih role terlebih dahulu';
                    itIdentifier.disabled = true;
                    itIdentifier.value = '';
                    itIdentifier.removeAttribute('inputmode');
                    itIdentifier.removeAttribute('maxlength');
                    return;
                }

                itIdentifier.disabled = false;
                itIdLabel.textContent = cfg.input_label;
                itIdentifier.placeholder = cfg.placeholder;
                itIdentifier.setAttribute('inputmode', cfg.input_mode);
                itIdentifier.setAttribute('maxlength', cfg.maxlength);
                itIdentifier.setAttribute('autocomplete', cfg.autocomplete);
            };

            const validateItForm = () => {
                clearItErrors();

                let valid = true;
                const cfg = IT_ROLES[itRole.value];

                if (!cfg) {
                    setItError('role', 'Pilih role terlebih dahulu.');
                    valid = false;
                } else {
                    const identifier = itIdentifier.value.trim();

                    if (!identifier) {
                        setItError('identifier', `${cfg.input_label} wajib diisi.`);
                        valid = false;
                    } else if (!isValidCode(identifier)) {
                        setItError(
                            'identifier',
                            `${cfg.input_label} hanya boleh berisi huruf, angka, tanda hubung, atau garis miring.`
                        );
                        valid = false;
                    }
                }

                if (!isValidEmail(itEmail.value.trim())) {
                    setItError('email', 'Masukkan alamat email yang valid.');
                    valid = false;
                }

                if (itPesan.value.trim().length < 10) {
                    setItError('pesan', 'Kendala minimal 10 karakter.');
                    valid = false;
                }

                if (!valid) {
                    itForm.querySelector('.border-red-400')?.focus();
                }

                return valid;
            };

            itBtn.addEventListener('click', () => {
                itForm.reset();
                clearItErrors();
                setItLoading(false);
                applyItRole(itRole.value);
                openItModal(itModal);
                setTimeout(() => (itRole.value ? itIdentifier : itRole).focus(), 80);
            });

            // Ganti role -> langsung fokuskan ke kolom NIP/NIS yang sesuai.
            itRole.addEventListener('change', () => {
                applyItRole(itRole.value);
                setItError('role', null);
                itIdentifier.focus();
            });

            document.querySelectorAll('#faq-it-modal [data-faq-close], #faq-it-success-modal [data-faq-close]')
                .forEach((btn) => {
                    btn.addEventListener('click', () => closeItModal(btn.closest('.faq-modal')));
                });

            [itModal, itSuccess].forEach((modal) => {
                modal.querySelector('[data-faq-backdrop]').addEventListener('click', () => closeItModal(modal));
            });

            document.addEventListener('keydown', (e) => {
                if (e.key !== 'Escape') return;
                [itModal, itSuccess].forEach((modal) => {
                    if (modal.dataset.open === 'true') closeItModal(modal);
                });
            });

            // Bersihkan error begitu pengguna mulai memperbaiki isian
            Object.entries(itFields).forEach(([field, input]) => {
                input.addEventListener('input', () => {
                    const error = itForm.querySelector(`[data-faq-error="${field}"]`);
                    if (!error.classList.contains('hidden')) validateItForm();
                });
            });

            itForm.addEventListener('submit', async (e) => {
                e.preventDefault();

                if (!validateItForm()) {
                    return;
                }

                const cfg = IT_ROLES[itRole.value];

                const payload = {
                    role: itRole.value,
                    [cfg.field]: itIdentifier.value.trim(),
                    email: itEmail.value.trim(),
                    pesan: itPesan.value.trim(),
                };

                setItLoading(true);

                try {
                    const res = await fetch(IT_ENDPOINT, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(payload),
                    });

                    const data = await res.json().catch(() => null);

                    if (!res.ok || !data || data.status !== 'success') {
                        if (data?.errors) {
                            // Server membalas error NIP/NIS memakai nama kolom
                            // aslinya, jadi diterjemahkan ke field "identifier".
                            if (cfg.field && data.errors[cfg.field]?.[0]) {
                                setItError('identifier', data.errors[cfg.field][0]);
                            }

                            Object.keys(itFields).forEach((field) => {
                                if (field !== 'identifier' && data.errors[field]?.[0]) {
                                    setItError(field, data.errors[field][0]);
                                }
                            });
                        }

                        if (window.Swal) {
                            window.Swal.fire({
                                icon: 'error',
                                title: 'Gagal Mengirim',
                                text: data?.message || 'Terjadi kesalahan. Silakan coba lagi.',
                                confirmButtonText: 'Coba Lagi',
                            });
                        }

                        itForm.querySelector('.border-red-400')?.focus();
                        return;
                    }

                    itSuccessText.textContent = data.message;
                    closeItModal(itModal);
                    openItModal(itSuccess);
                } catch (e) {
                    if (window.Swal) {
                        window.Swal.fire({
                            icon: 'error',
                            title: 'Gagal Mengirim',
                            text: 'Tidak dapat terhubung ke server. Silakan coba lagi.',
                            confirmButtonText: 'Coba Lagi',
                        });
                    }
                } finally {
                    setItLoading(false);
                }
            });
        });
    </script>

</section>