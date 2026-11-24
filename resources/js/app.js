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

// mixin() mengembalikan subclass sehingga tema otomatis berlaku pada
// setiap Swal.fire() tanpa perlu menimpa statis fire (yang akan rekursif).
const Swal = SwalBase.mixin(swalTheme);

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
    if (!window.Swal) return;
    window.Swal.fire({
        icon: 'success',
        title: judul,
        text: teks,
        showConfirmButton: false,
        timer: 1400,
        timerProgressBar: true,
        allowOutsideClick: false,
    });
};

const notifikasiGalat = (judul, teks) => {
    if (!window.Swal) {
        window.alert(`${judul}: ${teks}`);
        return;
    }
    window.Swal.fire({
        icon: 'error',
        title: judul,
        text: teks,
        confirmButtonText: 'Coba Lagi',
    });
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

document.addEventListener('DOMContentLoaded', sinkronExpandable);
window.addEventListener('resize', jadwalkanSinkronExpandable);

// Baris tabel yang dibuat lewat JS setelah halaman siap tetap dipantau.
new MutationObserver(jadwalkanSinkronExpandable).observe(document.documentElement, {
    childList: true,
    subtree: true,
});