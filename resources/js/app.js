import SwalBase from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

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