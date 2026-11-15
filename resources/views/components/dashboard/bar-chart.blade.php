{{--
    Diagram batang bersama untuk seluruh role.

    Tampilan, warna, ukuran batang, grid, sumbu, tipografi, spacing, serta
    tooltip hover sengaja dibuat identik dengan diagram batang pada Dashboard
    Admin, supaya tidak ada perbedaan antar-role. Komponen ini hanya mengurus
    gambar diagramnya; kartu, judul, dan filter tetap milik halaman pemanggil.

    Data masuk sebagai `bars` (array):
        ['label' => string, 'value' => float|null, 'sub' => string, 'details' => [
            ['label' => string, 'value' => string, 'weight' => float],
        ]]

    - `value` null berarti periode/kolom tanpa nilai: kolom tetap tampil sebagai
      label, tetapi tanpa batang dan tanpa tooltip.
    - Tooltip memakai struktur yang sama dengan Dashboard Admin: judul + nomor
      peringkat, nilai besar + lencana mutu, selisih terhadap rata-rata, lalu
      daftar rincian (opsional).

    Tinggi: area plot mengisi sisa tinggi pembungkusnya (flex-1), dengan tinggi
    minimum `minHeightClass`. Supaya mengisi kartu, pembungkus komponen ini harus
    `flex flex-col` dan memiliki tinggi (mis. `flex flex-1 flex-col`). Tanpa itu,
    diagram tampil setinggi tinggi minimumnya saja.
--}}
@props([
    'id',
    'bars' => [],
    'min' => 60,
    'max' => 100,
    'yTicks' => [100, 90, 80, 70, 60],
    'emptyText' => 'Belum ada data untuk ditampilkan.',
    'rankNoun' => 'data',
    'valueLabel' => 'Rata-rata nilai',
    // Tinggi minimum seluruh diagram = area plot (12rem / 14rem) + baris label sumbu X (1.5rem).
    'minHeightClass' => 'min-h-[13.5rem] sm:min-h-[15.5rem]',
    'touchHint' => true,
    // 'slim' = batang ramping (gaya Dashboard Admin, maks 70px).
    // 'fill' = batang mengisi lebar kolomnya, dipakai saat periode sedikit agar
    // tidak menyisakan ruang kosong di kanan/kiri kartu.
    'barWidth' => 'slim',
])

@php
    $normalized = collect($bars)->map(function ($bar) {
        $details = collect($bar['details'] ?? [])->map(fn ($detail) => [
            'label' => (string) ($detail['label'] ?? ''),
            'value' => (string) ($detail['value'] ?? ''),
            'weight' => is_numeric($detail['weight'] ?? null) ? (float) $detail['weight'] : 0.0,
        ])->values()->all();

        return [
            'label' => (string) ($bar['label'] ?? ''),
            'sub' => (string) ($bar['sub'] ?? ''),
            'value' => is_numeric($bar['value'] ?? null) ? round((float) $bar['value'], 1) : null,
            'details' => $details,
        ];
    })->values();

    $payload = [
        'bars' => $normalized,
        'min' => (float) $min,
        'max' => (float) $max,
        'rankNoun' => (string) $rankNoun,
        'valueLabel' => (string) $valueLabel,
        'barMaxClass' => $barWidth === 'fill' ? 'max-w-full' : 'max-w-17.5',
    ];
@endphp

<div data-bar-chart id="{{ $id }}" class="flex h-full flex-1 flex-col">
    <div class="relative mt-5 flex flex-1 gap-2 sm:mt-6 sm:gap-4 {{ $minHeightClass }}" data-chart>
        {{-- Sumbu Y: label mengisi tinggi plot, spacer h-6 setara baris label sumbu X --}}
        <div class="flex flex-col text-xs text-slate-400">
            <div class="flex flex-1 flex-col justify-between py-1">
                @foreach ($yTicks as $tick)
                    <span>{{ $tick }}</span>
                @endforeach
            </div>
            <div class="h-6 shrink-0"></div>
        </div>

        <div class="relative min-w-0 flex-1">
            <div class="pointer-events-none absolute bottom-6 left-0 top-0 border-l border-slate-100"></div>

            {{-- Garis panduan nilai saat hover / tap --}}
            <div class="pointer-events-none absolute inset-x-0 bottom-6 top-0">
                <div data-guide
                     class="absolute inset-x-0 border-t border-dashed border-slate-300 opacity-0 transition-[bottom,opacity] duration-300 ease-out"
                     style="bottom: 0%"></div>
            </div>

            <div class="absolute inset-0 flex items-stretch pl-1 sm:pl-4" data-columns></div>

            <p data-empty
               class="pointer-events-none absolute inset-x-0 bottom-6 top-0 flex items-center justify-center px-4 text-center text-sm text-slate-400 opacity-0 transition-opacity duration-300">
                {{ $emptyText }}
            </p>
        </div>

        {{-- Tooltip --}}
        <div data-tooltip
             class="pointer-events-none absolute left-0 top-0 z-20 w-52 rounded-2xl border border-slate-100 bg-white p-4 opacity-0 shadow-2xl shadow-slate-900/10 sm:w-60">
            <span data-arrow class="absolute h-3 w-3 rotate-45 border-slate-100 bg-white" style="border-style: solid"></span>
            <div data-tip-body class="relative"></div>
        </div>
    </div>

    @if ($touchHint)
        <p class="mt-3 text-center text-[11px] text-slate-400 [@media(hover:hover)]:hidden">
            Ketuk batang untuk melihat detail
        </p>
    @endif
</div>

<script>
    (function () {
        const cfg  = @json($payload);
        const root = document.getElementById(@json($id));
        if (!root) return;

        const esc = (v) => String(v).replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));

        const ICON_CHART = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>';
        const ICON_UP    = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 7-7 7 7"/><path d="M12 19V5"/></svg>';
        const ICON_DOWN  = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>';

        function tween(el, from, to, ms = 700) {
            cancelAnimationFrame(el._raf);
            const t0 = performance.now();
            const step = (now) => {
                const t = Math.min(1, (now - t0) / ms);
                const eased = t === 1 ? 1 : 1 - Math.pow(2, -10 * t);   // easeOutExpo
                el.textContent = t < 1 ? Math.round((from + (to - from) * eased) * 10) / 10 : to;
                if (t < 1) el._raf = requestAnimationFrame(step);
            };
            el._raf = requestAnimationFrame(step);
        }

        function grade(v) {
            if (v >= 90) return { text: 'Sangat Baik',        badge: 'bg-emerald-50 text-emerald-600', bar: 'bg-emerald-500' };
            if (v >= 80) return { text: 'Baik',               badge: 'bg-primary-10 text-primary-70',  bar: 'bg-primary-60' };
            if (v >= 75) return { text: 'Cukup',              badge: 'bg-amber-50 text-amber-600',     bar: 'bg-amber-500' };
            return           { text: 'Perlu Ditingkatkan', badge: 'bg-rose-50 text-rose-600',       bar: 'bg-rose-500' };
        }

        const min     = cfg.min;
        const max     = cfg.max;
        const wrap    = root.querySelector('[data-chart]');
        const colsEl  = root.querySelector('[data-columns]');
        const emptyEl = root.querySelector('[data-empty]');
        const guide   = root.querySelector('[data-guide]');
        const tip     = root.querySelector('[data-tooltip]');
        const tipBody = tip.querySelector('[data-tip-body]');
        const arrow   = tip.querySelector('[data-arrow]');

        const pct = (n) => Math.max(0, Math.min(100, (n - min) / (max - min) * 100));

        // ----- Tooltip -----
        let tipVisible = false;
        let hideTimer  = null;
        let lastNum    = min;
        let activeCol  = null;

        function place(col) {
            const box    = wrap.getBoundingClientRect();
            const barBox = col.bar.getBoundingClientRect();
            const zone   = col.bar.parentElement.getBoundingClientRect();
            const w = tip.offsetWidth;
            const h = tip.offsetHeight;
            const gap = 14;

            // sisi kanan batang; kalau tidak muat pindah ke kiri
            let side = 'right';
            let x = barBox.right - box.left + gap;
            if (x + w > box.width) { side = 'left'; x = barBox.left - box.left - gap - w; }
            x = Math.max(0, Math.min(box.width - w, x));

            // vertikal: sejajar tengah batang (pakai tinggi target, bukan tinggi saat animasi)
            const cy = zone.top - box.top + zone.height - zone.height * col.pct / 200;
            const y  = Math.max(0, Math.min(box.height - h, cy - h / 2));

            tip.style.transform = `translate(${x}px, ${y}px)`;

            // panah menunjuk ke batang
            arrow.style.top = `${Math.max(20, Math.min(h - 20, cy - y)) - 7}px`;
            if (side === 'right') {
                arrow.style.left = '-7px'; arrow.style.right = 'auto'; arrow.style.borderWidth = '0 0 1px 1px';
            } else {
                arrow.style.right = '-7px'; arrow.style.left = 'auto'; arrow.style.borderWidth = '1px 1px 0 0';
            }
        }

        function tipHtml(col, vis) {
            const g      = grade(col.value);
            const sorted = vis.map((c) => c.value).sort((a, b) => b - a);
            const rank   = sorted.indexOf(col.value) + 1;

            let delta = '';
            if (vis.length > 1) {
                const overall = vis.reduce((a, c) => a + c.value, 0) / vis.length;
                const diff = Math.round((col.value - overall) * 10) / 10;
                if (Math.abs(diff) < 0.05) {
                    delta = `<p class="mt-3 text-[11px] text-slate-400">Sama dengan rata-rata keseluruhan</p>`;
                } else {
                    const up = diff > 0;
                    delta = `
                        <div class="mt-3 flex items-center gap-2 text-[11px] text-slate-400">
                            <span class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 font-semibold ${up ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'}">
                                ${up ? ICON_UP : ICON_DOWN}${Math.abs(diff)}
                            </span>
                            <span>dari rata-rata ${esc(cfg.rankNoun)}</span>
                        </div>`;
                }
            }

            const detail = (col.details || []).slice(0, 4).map((d) => `
                <div>
                    <div class="flex items-center justify-between text-[11px]">
                        <span class="text-slate-500">${esc(d.label)}</span>
                        <span class="font-semibold text-slate-800">${esc(d.value)}</span>
                    </div>
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div data-w="${d.weight}" class="h-full rounded-full ${g.bar} transition-[width] duration-700 ease-out" style="width: 0%"></div>
                    </div>
                </div>`).join('');

            const detailBlock = detail
                ? `<div class="mt-4 space-y-3 border-t border-slate-100 pt-3">${detail}</div>`
                : '';

            return `
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-10 text-primary-70">${ICON_CHART}</span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900" title="${esc(col.label)}">${esc(col.label)}</p>
                        <p class="truncate text-[11px] text-slate-400" title="${col.sub ? esc(col.sub) + ' · ' : ''}Peringkat ${rank} dari ${vis.length} ${esc(cfg.rankNoun)}">${col.sub ? esc(col.sub) + ' · ' : ''}Peringkat ${rank} dari ${vis.length} ${esc(cfg.rankNoun)}</p>
                    </div>
                </div>

                <div class="mt-4 flex items-end justify-between gap-2">
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">${esc(cfg.valueLabel)}</p>
                        <p data-num class="mt-1 text-3xl font-bold leading-none tabular-nums text-slate-900">${col.value}</p>
                    </div>
                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold ${g.badge}">${g.text}</span>
                </div>

                ${delta}

                ${detailBlock}`;
        }

        function showTip(col) {
            if (!col.active) return;
            clearTimeout(hideTimer);
            activeCol = col;

            const vis = cols.filter((c) => c.active);
            tipBody.innerHTML = tipHtml(col, vis);

            const first = !tipVisible;
            if (first) tip.style.transition = 'opacity 150ms ease';   // muncul tanpa meluncur dari pojok

            place(col);   // sekaligus memaksa layout, jadi lebar progress bar mulai dari 0

            tipBody.querySelectorAll('[data-w]').forEach((f) => { f.style.width = f.dataset.w + '%'; });
            tween(tipBody.querySelector('[data-num]'), first ? min : lastNum, col.value, 500);
            lastNum = col.value;

            tip.style.opacity = 1;
            if (first) {
                tipVisible = true;
                requestAnimationFrame(() => {
                    tip.style.transition = 'opacity 150ms ease, transform 300ms cubic-bezier(.22, 1, .36, 1)';
                });
            }

            // garis panduan + sorot batang, redupkan yang lain
            guide.style.bottom = col.pct + '%';
            guide.style.opacity = 1;
            cols.forEach((c) => {
                const on = c === col;
                c.inner.style.opacity = on ? 1 : 0.4;
                c.bar.classList.toggle('brightness-110', on);
                c.bar.classList.toggle('shadow-xl', on);
            });
        }

        function resetTip() {
            tip.style.opacity = 0;
            guide.style.opacity = 0;
            tipVisible = false;
            activeCol = null;
            cols.forEach((c) => {
                c.inner.style.opacity = 1;
                c.bar.classList.remove('brightness-110', 'shadow-xl');
            });
        }

        // jeda kecil supaya tooltip meluncur antar batang, bukan hilang lalu muncul lagi
        function hideTip(now) {
            clearTimeout(hideTimer);
            if (now) resetTip(); else hideTimer = setTimeout(resetTip, 90);
        }

        // ----- Kolom dibuat sekali per label, lalu ditampilkan / disembunyikan -----
        const cols = cfg.bars.map((row) => {
            const el = document.createElement('div');
            el.className = 'flex min-w-0 flex-col overflow-hidden transition-[flex-grow,opacity] duration-500 ease-out';
            el.style.flex = '0 1 0%';
            el.style.opacity = 0;
            // Batang diposisikan absolut dari bawah (inline style agar tidak tertimpa CSS lain),
            // sehingga tinggi persentasenya selalu mengikuti tinggi zona batang.
            el.innerHTML = `
                <div data-inner class="flex flex-1 flex-col px-1.5 transition-opacity duration-300 sm:px-3">
                    <div class="relative flex-1">
                        <div data-bar class="inset-x-0 bottom-0 mx-auto w-full ${cfg.barMaxClass} overflow-hidden rounded-t-lg bg-primary-60 transition-[height,filter,box-shadow] duration-700 ease-out" style="position: absolute; height: 0%">
                            <span class="pointer-events-none absolute inset-0" style="background: linear-gradient(180deg, rgba(255,255,255,.28), rgba(255,255,255,0) 70%)"></span>
                            <span data-val class="absolute inset-x-0 top-1.5 text-center text-xs font-semibold text-white"></span>
                        </div>
                    </div>
                    <p class="h-6 shrink-0 truncate pt-2 text-center text-xs font-medium leading-4 text-slate-500" title="${esc(row.label)}">${esc(row.label)}</p>
                </div>`;

            const col = {
                label: row.label,
                sub: row.sub,
                details: row.details,
                target: row.value,
                active: false,
                value: null,
                pct: 0,
                el,
                inner: el.querySelector('[data-inner]'),
                bar:   el.querySelector('[data-bar]'),
                val:   el.querySelector('[data-val]'),
            };

            // Mouse: tooltip mengikuti hover; sentuh/ pena: ketuk untuk buka-tutup.
            let lastType = 'mouse';
            el.addEventListener('pointerdown', (e) => { lastType = e.pointerType; });
            el.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse') showTip(col); });
            el.addEventListener('pointerleave', (e) => { if (e.pointerType === 'mouse') hideTip(false); });
            el.addEventListener('click', () => {
                if (lastType === 'mouse') return;
                if (tipVisible && activeCol === col) hideTip(true); else showTip(col);
            });

            colsEl.appendChild(el);
            return col;
        });

        // Ketuk di luar grafik / ubah ukuran layar: tutup tooltip
        document.addEventListener('pointerdown', (e) => {
            if (tipVisible && !wrap.contains(e.target)) hideTip(true);
        });
        window.addEventListener('resize', () => hideTip(true));

        // Batang tumbuh bergantian dari kiri ke kanan
        function draw() {
            hideTip(true);
            let visible = 0;

            cols.forEach((col, i) => {
                const hasValue = col.target !== null && !isNaN(col.target);

                if (hasValue) {
                    visible++;
                    const from = col.active ? col.value : min;
                    col.value  = col.target;
                    col.active = true;
                    col.pct    = pct(col.value);
                    setTimeout(() => tween(col.val, from, col.value, 1400), i * 60);
                    col.el.style.flexGrow = 1;
                    col.el.style.opacity  = 1;
                    col.el.classList.remove('pointer-events-none');
                    // delay hanya untuk tinggi; filter & shadow (hover) tetap instan
                    col.bar.style.transitionDelay = `${i * 60}ms, 0ms, 0ms`;
                    col.bar.style.height = col.pct + '%';
                } else {
                    col.active = false;
                    col.value  = null;
                    col.pct    = 0;
                    col.el.style.flexGrow = 1;   // kolom tanpa nilai tetap menjaga jarak label
                    col.el.style.opacity  = 1;
                    col.el.classList.add('pointer-events-none');
                    col.bar.style.transitionDelay = '0ms, 0ms, 0ms';
                    col.bar.style.height = '0%';
                }
            });

            emptyEl.classList.toggle('opacity-0', cfg.bars.length > 0);
        }

        void colsEl.offsetHeight; // paksa reflow supaya animasi awal berjalan dari 0
        draw();
    })();
</script>