{{-- Overlay loading sebelum berpindah halaman --}}
<div
    id="site-loading"
    data-open="false"
    aria-hidden="true"
    inert
    class="site-loading fixed inset-0 z-80 flex flex-col items-center justify-center gap-4 bg-[#FDFDFD]/95 px-6 text-center backdrop-blur-sm"
>

    <div class="relative flex h-14 w-14 items-center justify-center">
        <span
            aria-hidden="true"
            class="absolute inset-0 animate-spin rounded-full
                   border-[3px] border-blue-100 border-t-blue-600"
        ></span>
        <x-lucide-graduation-cap class="h-6 w-6 text-blue-600" />
    </div>

    <p class="text-sm font-bold tracking-wide text-blue-600">SASKANSA</p>
    <p data-loading-text class="text-xs font-medium text-gray-500">Membuka Halaman...</p>

</div>

<style>
    .site-loading {
        opacity: 0;
        pointer-events: none;
        transition: opacity .3s ease;
    }

    .site-loading[data-open="true"] {
        opacity: 1;
        pointer-events: auto;
    }

    @media (prefers-reduced-motion: reduce) {
        .site-loading {
            transition: none;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const loading = document.getElementById('site-loading');
        if (!loading) return;

        const loadingText = loading.querySelector('[data-loading-text]');

        const open = (text) => {
            if (text) loadingText.textContent = text;
            loading.removeAttribute('inert');
            loading.setAttribute('aria-hidden', 'false');
            requestAnimationFrame(() => { loading.dataset.open = 'true'; });
        };

        document.querySelectorAll('a[data-loading-link]').forEach((link) => {
            link.addEventListener('click', (e) => {
                e.preventDefault();

                open(link.dataset.loadingText || 'Membuka Halaman...');

                setTimeout(() => {
                    window.location.href = link.href;
                }, 800);
            });
        });

        document.querySelectorAll('a[data-loading-back]').forEach((link) => {
            link.addEventListener('click', (e) => {
                e.preventDefault();

                open(link.dataset.loadingText || 'Kembali...');

                setTimeout(() => {
                    window.location.href = link.href;
                }, 800);
            });
        });
    });
</script>