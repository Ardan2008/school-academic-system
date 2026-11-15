{{--
    Animasi masuk dashboard (scroll reveal + count-up angka) yang dipakai bersama
    oleh dashboard guru & siswa, disamakan dengan dashboard admin.

    Cara pakai:
        <div data-dashboard> ... </div>
        <x-dashboard.animations />

    - Tandai elemen dengan `data-reveal` (opsional `style="--d: .2s"` untuk stagger).
    - Tandai angka dengan `data-count="123"` (opsional `data-decimals="2"`).
    - Beri class `dashboard-bar` + tinggi inline pada batang grafik agar tumbuh dari 0.
    - Tandai wadah daftar/tabel dengan `data-stagger` agar barisnya muncul bergantian.
--}}

<style>
    /* ---------- Reveal ---------- */
    [data-dashboard].reveal-ready [data-reveal] {
        opacity: 0;
        transform: translateY(14px);
        filter: blur(2px);
        transition:
            opacity .6s cubic-bezier(.22, 1, .36, 1) var(--d, 0s),
            transform .6s cubic-bezier(.22, 1, .36, 1) var(--d, 0s),
            filter .6s cubic-bezier(.22, 1, .36, 1) var(--d, 0s);
        will-change: opacity, transform;
    }

    [data-dashboard].reveal-ready [data-reveal].is-visible {
        opacity: 1;
        transform: none;
        filter: none;
    }

    /* ---------- Batang grafik: tumbuh dari 0 ---------- */
    @keyframes dashboard-bar-grow {
        from { height: 0; }
    }

    [data-dashboard].reveal-ready [data-reveal].is-visible .dashboard-bar {
        animation: dashboard-bar-grow .8s cubic-bezier(.22, 1, .36, 1) both;
        animation-delay: calc(var(--i, 0) * 60ms + .15s);
    }

    /* ---------- Baris daftar/tabel: muncul bergantian ---------- */
    @keyframes dashboard-row-in {
        from { opacity: 0; transform: translateY(8px); }
        to   { opacity: 1; transform: none; }
    }

    [data-dashboard].reveal-ready [data-reveal].is-visible [data-stagger] > * {
        animation: dashboard-row-in .4s cubic-bezier(.22, 1, .36, 1) both;
        animation-delay: calc(var(--i, 0) * 45ms);
    }

    @media (prefers-reduced-motion: reduce) {
        [data-dashboard].reveal-ready [data-reveal] {
            opacity: 1;
            transform: none;
            filter: none;
            transition: none;
        }

        [data-dashboard].reveal-ready [data-reveal].is-visible .dashboard-bar,
        [data-dashboard].reveal-ready [data-reveal].is-visible [data-stagger] > * {
            animation: none !important;
        }
    }
</style>

<script>
    (function () {
        const init = () => {
            // Tanpa IntersectionObserver: biarkan konten tampil apa adanya.
            if (!('IntersectionObserver' in window)) return;

            const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
            const easeOutExpo = (t) => (t === 1 ? 1 : 1 - Math.pow(2, -10 * t));

            const countUp = (el, ms = 1800) => {
                const to = Number(el.dataset.count);
                if (isNaN(to) || reduce) return;

                const decimals = Number(el.dataset.decimals || 0);
                const format = (v) => (decimals > 0 ? v.toFixed(decimals) : String(Math.round(v)));

                const t0 = performance.now();
                const step = (now) => {
                    const t = Math.min(1, (now - t0) / ms);
                    el.textContent = format(to * easeOutExpo(t));
                    if (t < 1) requestAnimationFrame(step);
                };

                el.textContent = format(0);
                requestAnimationFrame(step);
            };

            document.querySelectorAll('[data-dashboard]').forEach((scope) => {
                // Indeks stagger: untuk batang grafik & baris daftar, per kartu.
                scope.querySelectorAll('[data-reveal]').forEach((card) => {
                    card.querySelectorAll('.dashboard-bar').forEach((el, i) => {
                        el.style.setProperty('--i', i);
                    });

                    card.querySelectorAll('[data-stagger]').forEach((list) => {
                        [...list.children].forEach((el, i) => {
                            el.style.setProperty('--i', i);
                        });
                    });
                });

                scope.classList.add('reveal-ready');

                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) return;

                        const target = entry.target;
                        target.classList.add('is-visible');
                        observer.unobserve(target);

                        const delay = (parseFloat(getComputedStyle(target).getPropertyValue('--d')) || 0) * 1000;
                        target.querySelectorAll('[data-count]').forEach((el) => {
                            const decimals = Number(el.dataset.decimals || 0);
                            if (!reduce) el.textContent = decimals > 0 ? (0).toFixed(decimals) : '0';
                            setTimeout(() => countUp(el), delay + 300);
                        });
                    });
                }, { threshold: 0.1, rootMargin: '0px 0px -30px 0px' });

                scope.querySelectorAll('[data-reveal]').forEach((el) => observer.observe(el));
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
</script>
