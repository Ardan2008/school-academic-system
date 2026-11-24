{{-- Overlay loading global full-screen --}}
<div
    id="site-loading"
    data-open="false"
    aria-hidden="true"
    inert
    class="site-loading fixed inset-0 z-[11500] flex flex-col items-center justify-center gap-4 bg-[#FDFDFD]/90 px-6 text-center backdrop-blur-sm"
>
    <div class="relative flex h-14 w-14 items-center justify-center">
        <span
            aria-hidden="true"
            class="site-loading__spinner absolute inset-0 rounded-full border-[3px] border-blue-100 border-t-blue-600"
        ></span>
        <x-lucide-graduation-cap class="h-6 w-6 text-blue-600" />
    </div>

    <p class="text-sm font-bold tracking-wide text-blue-600">SASKANSA</p>
    <p data-loading-text class="text-xs font-medium text-gray-500">Memproses...</p>
</div>

<style>
    .site-loading {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity .25s ease, visibility 0s linear .25s;
    }

    .site-loading[data-open="true"] {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
        transition: opacity .25s ease, visibility 0s linear 0s;
    }

    .site-loading__spinner { animation: site-loading-spin .9s linear infinite; }

    @keyframes site-loading-spin { to { transform: rotate(360deg); } }

    @media (prefers-reduced-motion: reduce) {
        .site-loading, .site-loading__spinner { transition: none; animation: none; }
    }
</style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const loading = document.getElementById('site-loading');
            if (!loading) return;

            const loadingText = loading.querySelector('[data-loading-text]');
            let hideTimer = null;

            const open = (text) => {
                window.clearTimeout(hideTimer);
                if (loadingText && text) loadingText.textContent = text;
                loading.removeAttribute('inert');
                loading.setAttribute('aria-hidden', 'false');
                requestAnimationFrame(() => { loading.dataset.open = 'true'; });
            };

            const close = () => {
                loading.dataset.open = 'false';
                loading.setAttribute('aria-hidden', 'true');
                const lock = () => {
                    if (loading.dataset.open !== 'true') loading.setAttribute('inert', '');
                };
                loading.addEventListener('transitionend', lock, { once: true });
                hideTimer = window.setTimeout(lock, 300);
            };

            window.saskansaLoading = { show: open, hide: close, el: loading };

            window.addEventListener('pageshow', close);
            window.addEventListener('pagehide', close);

            document.querySelectorAll('a[data-loading-link]').forEach((link) => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    open(link.dataset.loadingText || 'Membuka Halaman...');
                    setTimeout(() => { window.location.href = link.href; }, 800);
                });
            });

            document.querySelectorAll('a[data-loading-back]').forEach((link) => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    open(link.dataset.loadingText || 'Kembali...');
                    setTimeout(() => { window.location.href = link.href; }, 800);
                });
            });
        });
    </script>