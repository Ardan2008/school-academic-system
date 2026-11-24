import SwalBase from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import './jadwal-guru.js';

/**
 * Tema SweetAlert mengikuti palet aplikasi (biru SASKANSA).
 * Dijadikan global karena dipakai langsung di banyak halaman.
 */
const swalTheme = {
    background: '#ffffff',
    color: '#0f172a',
    confirmButtonColor: '#2563eb',
    cancelButtonColor: '#64748b',
};

const SwalBaseThemed = SwalBase.mixin(swalTheme);
const swalFireAsli = SwalBaseThemed.fire.bind(SwalBaseThemed);

const TOAST_DEFAULTS = {
    success: { duration: 3500, title: 'Berhasil', icon: 'success' },
    error: { duration: 5000, title: 'Terjadi Kesalahan', icon: 'error' },
    warning: { duration: 4500, title: 'Perhatian', icon: 'warning' },
    information: { duration: 3500, title: 'Informasi', icon: 'information' },
    info: { duration: 3500, title: 'Informasi', icon: 'information' },
};

const toastState = {
    container: null,
    items: new Map(),
};

const pastikanToastContainer = () => {
    if (toastState.container?.isConnected) return toastState.container;
    let container = document.getElementById('saskansa-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'saskansa-toast-container';
        container.className = 'saskansa-toast-container';
        container.setAttribute('aria-live', 'polite');
        container.setAttribute('aria-atomic', 'false');
        document.body.appendChild(container);
    }
    toastState.container = container;
    return container;
};

const ikonToast = (jenis) => {
    const umum = 'width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
    if (jenis === 'success') return `<svg ${umum}><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>`;
    if (jenis === 'error') return `<svg ${umum}><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>`;
    if (jenis === 'warning') return `<svg ${umum}><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>`;
    return `<svg ${umum}><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>`;
};

const normalisasiJenisToast = (jenis) => {
    if (jenis === 'info') return 'information';
    return TOAST_DEFAULTS[jenis] ? jenis : 'information';
};

const hapusToast = (id) => {
    const item = toastState.items.get(id);
    if (!item) return;
    const { el, timer } = item;
    window.clearTimeout(timer?.id);
    toastState.items.delete(id);
    el.classList.add('is-leaving');
    el.addEventListener('animationend', () => el.remove(), { once: true });
    window.setTimeout(() => el.remove(), 260);
};

const tampilkanToast = (opsi = {}) => {
    const container = pastikanToastContainer();
    const jenis = normalisasiJenisToast(opsi.type || opsi.icon || 'information');
    const preset = TOAST_DEFAULTS[jenis];
    const judul = opsi.title ?? preset.title;
    const deskripsi = opsi.message ?? opsi.text ?? opsi.description ?? '';
    const durasi = Math.max(1200, Number(opsi.duration ?? preset.duration));
    const id = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;

    const el = document.createElement('div');
    el.className = `saskansa-toast saskansa-toast--${jenis}`;
    el.setAttribute('role', jenis === 'error' || jenis === 'warning' ? 'alert' : 'status');
    el.innerHTML = `
        <div class="saskansa-toast__icon">${ikonToast(jenis)}</div>
        <div class="saskansa-toast__content">
            <p class="saskansa-toast__title"></p>
            ${deskripsi ? '<p class="saskansa-toast__desc"></p>' : ''}
        </div>
        <button type="button" class="saskansa-toast__close" aria-label="Tutup notifikasi">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
        <div class="saskansa-toast__progress"><span class="saskansa-toast__progress-bar"></span></div>
    `;
    el.querySelector('.saskansa-toast__title').textContent = String(judul ?? '');
    if (deskripsi) el.querySelector('.saskansa-toast__desc').textContent = String(deskripsi);

    const progress = el.querySelector('.saskansa-toast__progress-bar');
    progress.style.animationDuration = `${durasi}ms`;

    const timer = { id: null, remaining: durasi, startedAt: Date.now() };
    const start = () => {
        window.clearTimeout(timer.id);
        timer.startedAt = Date.now();
        timer.id = window.setTimeout(() => hapusToast(id), timer.remaining);
        progress.style.animationPlayState = 'running';
    };
    const pause = () => {
        if (!timer.id) return;
        window.clearTimeout(timer.id);
        timer.id = null;
        timer.remaining = Math.max(0, timer.remaining - (Date.now() - timer.startedAt));
        progress.style.animationPlayState = 'paused';
    };

    el.addEventListener('mouseenter', pause);
    el.addEventListener('mouseleave', () => { if (timer.remaining > 0) start(); });
    el.querySelector('.saskansa-toast__close').addEventListener('click', () => hapusToast(id));

    container.appendChild(el);
    toastState.items.set(id, { el, timer });
    start();
    return id;
};

const toastApi = {
    show: tampilkanToast,
    success: (message, title, options = {}) => tampilkanToast({ ...options, type: 'success', message, title }),
    error: (message, title, options = {}) => tampilkanToast({ ...options, type: 'error', message, title }),
    warning: (message, title, options = {}) => tampilkanToast({ ...options, type: 'warning', message, title }),
    information: (message, title, options = {}) => tampilkanToast({ ...options, type: 'information', message, title }),
    info: (message, title, options = {}) => tampilkanToast({ ...options, type: 'information', message, title }),
    dismiss: hapusToast,
};

window.saskansaToast = toastApi;

const adalahKonfirmasiSwal = (opsi = {}) => Boolean(
    opsi.showCancelButton || opsi.showDenyButton || opsi.input || opsi.showLoaderOnConfirm
);

const swalToastResult = (opsi = {}) => Promise.resolve({
    isConfirmed: false,
    isDenied: false,
    isDismissed: true,
    dismiss: 'toast',
    value: undefined,
    ...opsi._saskansaResult,
});

const fireSwalTerpusat = (...args) => {
    const opsi = (args.length === 1 && args[0] && typeof args[0] === 'object')
        ? { ...args[0] }
        : { title: args[0], text: args[1], icon: args[2] };

    const icon = opsi.icon;
    const konfirmasi = adalahKonfirmasiSwal(opsi);
    const paksaModal = opsi.toast === false || opsi.saskansaModal === true || opsi.critical === true;

    if (!konfirmasi && icon === 'success' && opsi.toast !== false) {
        tampilkanToast({
            type: 'success',
            title: opsi.title || 'Berhasil',
            message: opsi.text || opsi.html || '',
            duration: opsi.timer && Number(opsi.timer) > 0 ? Number(opsi.timer) : undefined,
        });
        return swalToastResult();
    }

    if (!konfirmasi && (icon === 'warning' || icon === 'info' || icon === 'question') && !paksaModal) {
        tampilkanToast({
            type: icon === 'question' ? 'information' : icon,
            title: opsi.title,
            message: opsi.text || opsi.html || '',
            duration: opsi.timer && Number(opsi.timer) > 0 ? Number(opsi.timer) : undefined,
        });
        return swalToastResult();
    }

    if (!konfirmasi && icon === 'error' && !paksaModal) {
        tampilkanToast({
            type: 'error',
            title: opsi.title || 'Terjadi Kesalahan',
            message: opsi.text || opsi.html || '',
            duration: opsi.timer && Number(opsi.timer) > 0 ? Number(opsi.timer) : 5000,
        });
        return swalToastResult();
    }

    return swalFireAsli(opsi);
};

const Swal = Object.assign(fireSwalTerpusat, SwalBaseThemed, {
    fire: fireSwalTerpusat,
    mixin: (...args) => SwalBaseThemed.mixin(...args),
});

window.Swal = Swal;

const ambilTokenCsrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const pesanGalat = (hasil, bawaan) => {
    if (hasil && typeof hasil.message === 'string' && hasil.message.trim() !== '') return hasil.message;
    const daftar = hasil?.errors;
    if (daftar && typeof daftar === 'object') {
        const pertama = Object.values(daftar)[0];
        if (Array.isArray(pertama) && pertama.length > 0) return String(pertama[0]);
        if (typeof pertama === 'string') return pertama;
    }
    return bawaan;
};

const mulaiLoading = (teks) => {
    if (window.saskansaLoading?.show) {
        window.saskansaLoading.show(teks || 'Memproses...');
        return window.saskansaLoading.el || document.getElementById('site-loading');
    }
    const loading = document.getElementById('site-loading');
    if (!loading) return null;
    const label = loading.querySelector('[data-loading-text]');
    if (label && teks) label.textContent = teks;
    loading.removeAttribute('inert');
    loading.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(() => { loading.dataset.open = 'true'; });
    return loading;
};

const selesaiLoading = (loading) => {
    if (window.saskansaLoading?.hide) {
        window.saskansaLoading.hide();
        return;
    }
    if (!loading) return;
    loading.dataset.open = 'false';
    loading.setAttribute('aria-hidden', 'true');
    const kunci = () => loading.setAttribute('inert', '');
    loading.addEventListener('transitionend', kunci, { once: true });
    window.setTimeout(kunci, 400);
};

const konfirmasiHapus = ({ title, text }) => {
    if (!window.Swal) return Promise.resolve(false);
    return window.Swal.fire({
        icon: 'warning',
        title,
        text,
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
    }).then((hasil) => hasil.isConfirmed);
};

const notifikasiSukses = (teks, judul = 'Berhasil') => {
    toastApi.success(teks, judul);
};

const notifikasiGalat = (judul, teks, opsi = {}) => {
    const berat = opsi.critical === true || opsi.modal === true;
    if (berat) {
        return fireSwalTerpusat({
            icon: 'error',
            title: judul,
            text: teks,
            confirmButtonText: opsi.confirmButtonText || 'Tutup',
            saskansaModal: true,
        });
    }
    toastApi.error(teks, judul);
    return Promise.resolve();
};

const kurangiPenghitung = (selector) => {
    document.querySelectorAll(selector).forEach((el) => {
        const teks = el.textContent ?? '';
        const cocok = teks.match(/\d+/);
        if (!cocok) return;
        const nilai = Math.max(0, Number(cocok[0]) - 1);
        el.textContent = teks.replace(/\d+/, String(nilai));
    });
};

const hapusItem = (jenis, id) => {
    document.querySelectorAll(`[data-delete-item="${jenis}:${id}"]`).forEach((el) => el.remove());
};

const sedangMenghapus = new WeakSet();

document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('[data-delete-form]')) return;

    event.preventDefault();
    if (sedangMenghapus.has(form)) return;
    sedangMenghapus.add(form);

    const data = form.dataset;
    const jenis = data.deleteKind || 'data';
    const id = data.deleteId || '';
    const nama = data.deleteName || 'Data ini';
    const konfirmasi = await konfirmasiHapus({
        title: data.deleteTitle || `Hapus data ${nama}?`,
        text: data.deleteText || `${nama} akan dihapus dari daftar.`,
    });

    if (!konfirmasi) {
        sedangMenghapus.delete(form);
        return;
    }

    const loading = mulaiLoading(data.deleteLoading || `Menghapus ${nama}...`);

    try {
        const response = await fetch(form.action, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': ambilTokenCsrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const hasil = await response.json().catch(() => null);

        if (!response.ok || !hasil || hasil.status !== 'success') {
            selesaiLoading(loading);
            notifikasiGalat('Gagal Menghapus', pesanGalat(hasil, 'Data gagal dihapus. Silakan coba lagi.'));
            return;
        }

        hapusItem(jenis, id);
        kurangiPenghitung(`[data-delete-counter="${jenis}"]`);
        if (data.deleteCounterKey) kurangiPenghitung(`[data-delete-counter="${data.deleteCounterKey}"]`);

        const statKey = { jadwal: 'total', tugas: 'tugas', materi: 'materi' }[jenis];
        if (statKey) kurangiPenghitung(`[data-stat="${statKey}"]`);

        document.dispatchEvent(new CustomEvent('saskansa:delete-success', {
            detail: { jenis, id, nama, hasil },
        }));

        selesaiLoading(loading);
        notifikasiSukses(hasil.message || `Data ${nama} berhasil dihapus.`);

        const halaman = Number(new URLSearchParams(window.location.search).get('page') ?? '1');
        if (halaman > 1 && document.querySelectorAll(`[data-delete-item^="${jenis}:"]`).length === 0) {
            window.setTimeout(() => {
                const url = new URL(window.location.href);
                url.searchParams.delete('page');
                window.location.assign(url.toString());
            }, 1600);
        }
    } catch (error) {
        selesaiLoading(loading);
        notifikasiGalat('Gagal Menghapus', 'Tidak dapat terhubung ke server. Silakan coba lagi.');
    } finally {
        sedangMenghapus.delete(form);
    }
});

window.saskansaDelete = {
    confirm: konfirmasiHapus,
    toast: notifikasiSukses,
    error: notifikasiGalat,
    loading: mulaiLoading,
    hideLoading: selesaiLoading,
};

window.saskansaNotify = toastApi;
window.saskansaUi = {
    toast: toastApi,
    loading: { show: mulaiLoading, hide: selesaiLoading },
    confirm: konfirmasiHapus,
    error: notifikasiGalat,
};

export default Swal;

/**
 * Navigasi input dengan arrow key.
 * ArrowDown / ArrowUp pada input teks memindahkan fokus ke input terlihat
 * berikutnya / sebelumnya dalam urutan DOM.
 */
document.addEventListener('keydown', (e) => {
    if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;

    const el = e.target;
    if (!(el instanceof HTMLInputElement)) return;

    // Lewati tipe input yang punya perilaku panah bawaan / bukan teks.
    const skipTypes = [
        'hidden', 'checkbox', 'radio', 'file', 'range', 'color',
        'number', 'date', 'datetime-local', 'month', 'time', 'week',
    ];
    if (skipTypes.includes(el.type)) return;

    const inputs = Array.from(document.querySelectorAll('input:not([type=hidden])')).filter((i) =>
        !i.disabled
        && i.offsetParent !== null
        && !i.closest('[inert]')
    );

    const idx = inputs.indexOf(el);
    if (idx === -1) return;

    const next = e.key === 'ArrowDown' ? inputs[idx + 1] : inputs[idx - 1];
    if (!next) return;

    e.preventDefault();
    next.focus();
    next.select?.();
});

/**
 * Sembunyikan footer paginasi bila hanya ada satu halaman.
 * Paginasi sisi klien (mini-pagination) mengisi tombol halaman lewat
 * JavaScript, jadi footernya dipantau dan disembunyikan otomatis saat jumlah
 * halamannya tinggal satu (mis. data kurang dari jumlah per halaman).
 */
const syncTableFooter = (pag) => {
    const footer = pag.closest('[data-table-footer]');
    if (!footer) return;

    const jumlahHalaman = pag.querySelectorAll('[data-pages] [data-page]').length;
    footer.style.display = jumlahHalaman > 1 ? '' : 'none';
};

const amatiPaginasi = (pag) => {
    syncTableFooter(pag);

    const daftarHalaman = pag.querySelector('[data-pages]');
    if (!daftarHalaman) return;

    new MutationObserver(() => syncTableFooter(pag)).observe(daftarHalaman, {
        childList: true,
    });
};

document.querySelectorAll('[data-pagination]').forEach(amatiPaginasi);

/**
 * Teks panjang yang dipotong (`line-clamp-N`) diberi tombol "Lihat
 * selengkapnya" hanya bila isinya benar-benar terpotong, lalu bisa dibuka dan
 * ditutup. Dipakai komponen <x-ui.expandable-text>.
 *
 * Ukuran isi diperiksa langsung dari DOM (bukan dari panjang karakter) supaya
 * tombol muncul tepat saat teks meluber, berapa pun lebar kolomnya.
 */
const sinkronExpandable = () => {
    document.querySelectorAll('[data-expandable]').forEach((root) => {
        const isi = root.querySelector('[data-expandable-content]');
        const tombol = root.querySelector('[data-expandable-toggle]');
        if (!isi || !tombol) return;

        // Saat sedang terbuka, tombol tetap tampil sebagai "Sembunyikan".
        if (root.dataset.expanded === 'true') {
            tombol.classList.remove('hidden');
            return;
        }

        // Elemen yang disembunyikan secara responsif (mis. blok mobile saat
        // layar desktop) tidak punya ukuran, jadi tidak bisa diukur.
        if (isi.offsetParent === null) return;

        const terpotong = isi.scrollHeight > isi.clientHeight + 1;
        tombol.classList.toggle('hidden', !terpotong);
    });
};

let expandableRaf = null;
const jadwalkanSinkronExpandable = () => {
    if (expandableRaf !== null) return;
    expandableRaf = requestAnimationFrame(() => {
        expandableRaf = null;
        sinkronExpandable();
    });
};

document.addEventListener('click', (e) => {
    const tombol = e.target.closest('[data-expandable-toggle]');
    if (!tombol) return;

    const root = tombol.closest('[data-expandable]');
    const isi = root?.querySelector('[data-expandable-content]');
    if (!isi) return;

    const buka = root.dataset.expanded !== 'true';
    root.dataset.expanded = buka ? 'true' : 'false';

    // Kelas clamp aslinya disimpan di data-attribute (bisa line-clamp-2/3).
    const clamp = isi.dataset.expandableClamp || 'line-clamp-2';
    isi.classList.toggle(clamp, !buka);
    tombol.textContent = buka ? 'Sembunyikan' : 'Lihat selengkapnya';

    if (!buka) jadwalkanSinkronExpandable();
});

document.addEventListener('DOMContentLoaded', () => {
    sinkronExpandable();
    document.querySelectorAll('[data-flash-success]').forEach((el) => {
        const pesan = (el.getAttribute('data-flash-success') || el.textContent || '').replace(/\s+/g, ' ').trim();
        if (pesan) toastApi.success(pesan, 'Berhasil');
        el.remove();
    });
    document.querySelectorAll('[data-flash-error]').forEach((el) => {
        const pesan = (el.getAttribute('data-flash-error') || el.textContent || '').replace(/\s+/g, ' ').trim();
        if (pesan) toastApi.error(pesan, 'Terjadi Kesalahan');
        el.remove();
    });
    document.querySelectorAll('[data-flash-warning]').forEach((el) => {
        const pesan = (el.getAttribute('data-flash-warning') || el.textContent || '').replace(/\s+/g, ' ').trim();
        if (pesan) toastApi.warning(pesan, 'Perhatian');
        el.remove();
    });
    document.querySelectorAll('[data-flash-info]').forEach((el) => {
        const pesan = (el.getAttribute('data-flash-info') || el.textContent || '').replace(/\s+/g, ' ').trim();
        if (pesan) toastApi.information(pesan, 'Informasi');
        el.remove();
    });
});
window.addEventListener('resize', jadwalkanSinkronExpandable);

// Baris tabel yang dibuat lewat JS setelah halaman siap tetap dipantau.
new MutationObserver(jadwalkanSinkronExpandable).observe(document.documentElement, {
    childList: true,
    subtree: true,
});