const HARI_URUTAN = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
const NAMA_BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

const elemen = (selector, induk = document) => induk.querySelector(selector);
const daftarElemen = (selector, induk = document) => Array.from(induk.querySelectorAll(selector));

const bacaJson = (root, kunci, bawaan) => {
    try {
        const nilai = JSON.parse(root.dataset[kunci] ?? '');
        return nilai ?? bawaan;
    } catch {
        return bawaan;
    }
};

const setTeks = (el, nilai) => {
    if (el && el.textContent !== String(nilai)) el.textContent = String(nilai);
};

const normalTeks = (nilai) => String(nilai ?? '').trim().toLowerCase().replace(/\s+/g, ' ');

const menitDari = (jam) => {
    const bagian = String(jam ?? '').split(':');
    if (bagian.length < 2) return Number.NaN;
    const jamAngka = Number(bagian[0]);
    const menitAngka = Number(bagian[1]);
    if (!Number.isFinite(jamAngka) || !Number.isFinite(menitAngka)) return Number.NaN;
    return jamAngka * 60 + menitAngka;
};

const waktuJakarta = () => {
    const bagian = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Jakarta',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(new Date());
    const ambil = (jenis) => bagian.find((item) => item.type === jenis)?.value ?? '';

    return {
        iso: `${ambil('year')}-${ambil('month')}-${ambil('day')}`,
        jam: `${ambil('hour')}:${ambil('minute')}`,
    };
};

const jamSekarang = () => waktuJakarta().jam;

const tanggalIsoLokal = (tanggal) => [
    tanggal.getUTCFullYear(),
    String(tanggal.getUTCMonth() + 1).padStart(2, '0'),
    String(tanggal.getUTCDate()).padStart(2, '0'),
].join('-');

const tanggalPendek = (tanggal) => `${String(tanggal.getUTCDate()).padStart(2, '0')} ${NAMA_BULAN[tanggal.getUTCMonth()]}`;

const teksHitungMundur = (menit) => {
    const nilai = Math.max(0, Math.round(Number(menit) || 0));
    const jam = Math.floor(nilai / 60);
    const sisa = nilai % 60;

    return jam > 0 ? `Mulai dalam ${jam} jam ${sisa} menit` : `Mulai dalam ${sisa} menit`;
};

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

const notifikasi = (pesan, sukses = true) => {
    if (window.saskansaToast) {
        window.saskansaToast[sukses ? 'success' : 'error'](pesan, sukses ? 'Berhasil' : 'Gagal');
        return;
    }
    if (window.Swal) {
        window.Swal.fire({
            icon: sukses ? 'success' : 'error',
            title: sukses ? 'Berhasil' : 'Gagal',
            text: pesan,
            toast: !sukses ? undefined : true,
        });
        return;
    }
    window.alert(pesan);
};

const konfirmasi = async (judul, teks) => {
    if (!window.Swal) return window.confirm(`${judul}\n\n${teks}`);
    const hasil = await window.Swal.fire({
        icon: 'question',
        title: judul,
        text: teks,
        showCancelButton: true,
        confirmButtonText: 'Ya, lanjutkan',
        cancelButtonText: 'Batal',
    });
    return Boolean(hasil.isConfirmed);
};

function initJadwalGuru() {
    const root = elemen('[data-jadwal-page]');
    if (!root || root.dataset.jgInit === '1') return;
    root.dataset.jgInit = '1';

    const items = bacaJson(root, 'items', []);
    const semuaItems = bacaJson(root, 'semuaItems', items);
    const hariAktif = bacaJson(root, 'hariAktif', []);
    const daftarSesi = bacaJson(root, 'sesi', []);
    const tanggalAwal = bacaJson(root, 'tanggal', {});
    let grupBentrok = bacaJson(root, 'bentrok', []);
    let daftarSaran = bacaJson(root, 'saran', []);
    const adminSearch = root.dataset.role === 'admin';
    const teksCariItem = (item) => [
        item.guru,
        item.mapel,
        item.kelas,
        item.jurusan,
        item.jurusan_singkat,
        item.ruang,
        String(item.rombel_key ?? '').replace('|', ' '),
        `${item.kelas ?? ''} ${item.jurusan_singkat ?? ''}`,
        `${item.jurusan_singkat ?? ''} ${item.kelas ?? ''}`,
    ].filter(Boolean).join(' ').toLowerCase().replace(/\s+/g, ' ');
    const petaCari = new Map(items.map((item) => [String(item.id), teksCariItem(item)]));
    const storeUrl = root.dataset.storeUrl || root.dataset.intentUrl || '';
    const intentUrl = root.dataset.intentUrl || storeUrl;
    const updateTemplate = root.dataset.updateTemplate;
    const csrf = elemen('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const overlayForm = elemen('[data-form-overlay]', root);
    const overlayPenyesuaian = elemen('[data-adjust-overlay]', root);
    const form = elemen('#jadwal-form', root);
    const query = new URLSearchParams(window.location.search);

    const viewTersimpan = (() => {
        try {
            return window.localStorage.getItem(`saskansa.jadwal.view.${root.dataset.role ?? 'guru'}`) ?? '';
        } catch {
            return '';
        }
    })();

    const tampilanBawaan = window.matchMedia('(max-width: 640px)').matches ? 'hari' : 'minggu';
    const viewDariQuery = query.get('view') ?? '';
    const menitServerAwal = menitDari(root.dataset.now);
    const menitBrowserAwal = menitDari(jamSekarang());
    const penyesuaiWaktu = Number.isFinite(menitServerAwal) && Number.isFinite(menitBrowserAwal)
        ? menitServerAwal - menitBrowserAwal
        : 0;
    const jamSistem = () => {
        const menit = menitDari(jamSekarang()) + penyesuaiWaktu;
        if (!Number.isFinite(menit)) return root.dataset.now || jamSekarang();
        const total = ((Math.round(menit) % 1440) + 1440) % 1440;

        return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
    };
    const state = {
        view: ['minggu', 'hari', 'daftar'].includes(viewDariQuery)
            ? viewDariQuery
            : (['minggu', 'hari', 'daftar'].includes(viewTersimpan) ? viewTersimpan : tampilanBawaan),
        hariFokus: hariAktif.includes(root.dataset.hariAwal)
            ? root.dataset.hariAwal
            : (hariAktif.includes(root.dataset.hariIni) ? root.dataset.hariIni : (hariAktif[0] ?? '')),
        hariUrl: root.dataset.hariAwal || '',
        filter: 'semua',
        cari: adminSearch ? normalTeks(elemen('[data-cari="utama"]', root)?.value ?? '') : '',
        tampilkanKosong: false,
        weekOffset: 0,
        jam: jamSistem(),
        cekToken: 0,
        cekTimer: null,
    };

    const cocokCariItem = (item) => !adminSearch || state.cari === '' || (petaCari.get(String(item.id)) ?? '').includes(state.cari);

    const cocokCariElemen = (el) => {
        if (!adminSearch || state.cari === '') return true;
        const id = String(el.dataset.id ?? '');
        if (id !== '' && petaCari.has(id)) return petaCari.get(id).includes(state.cari);

        return normalTeks(el.dataset.search ?? '').includes(state.cari);
    };

    const itemsCari = () => items.filter(cocokCariItem);

    const simpanTampilan = () => {
        try {
            window.localStorage.setItem(`saskansa.jadwal.view.${root.dataset.role ?? 'guru'}`, state.view);
        } catch {
            return;
        }
        const url = new URL(window.location.href);
        url.searchParams.set('view', state.view);
        if (state.hariUrl) url.searchParams.set('hari', state.hariUrl);
        else url.searchParams.delete('hari');
        window.history.replaceState({}, '', url.toString());

        if (!tombolEkspor?.dataset.exportUrl) return;
        const urlEkspor = new URL(tombolEkspor.dataset.exportUrl, window.location.origin);
        if (state.hariUrl) urlEkspor.searchParams.set('hari', state.hariUrl);
        else urlEkspor.searchParams.delete('hari');
        tombolEkspor.dataset.exportUrl = urlEkspor.toString();
    };

    const kunciBody = () => {
        document.body.style.overflow = 'hidden';
    };

    const lepasBody = () => {
        if (daftarElemen('.jg-overlay[data-open="true"]', root).length > 0) return;
        document.body.style.overflow = '';
    };

    const bukaOverlay = (overlay) => {
        if (!overlay) return;
        overlay.removeAttribute('inert');
        overlay.dataset.open = 'true';
        overlay.setAttribute('aria-hidden', 'false');
        kunciBody();
        window.setTimeout(() => elemen('button, input, select', overlay)?.focus(), 40);
    };

    const tutupOverlay = (overlay) => {
        if (!overlay) return;
        overlay.dataset.open = 'false';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.setAttribute('inert', '');
        lepasBody();
    };

    const terapkanTampilan = () => {
        daftarElemen('[data-view-pilih]', root).forEach((tombol) => {
            tombol.setAttribute('aria-pressed', tombol.dataset.viewPilih === state.view ? 'true' : 'false');
        });
        daftarElemen('[data-view-panel]', root).forEach((panel) => {
            panel.hidden = panel.dataset.viewPanel !== state.view;
        });
    };

    let gulirTampilanFrame = 0;

    const panelTampilan = (mode) => daftarElemen(`[data-view-panel="${mode}"]`, root)
        .find((panel) => mode !== 'hari' || panel.getClientRects().length > 0) ?? null;

    const gulirKeTampilan = (mode) => {
        panelTampilan(mode)?.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' });
    };

    const jadwalkanGulirTampilan = (mode) => {
        if (gulirTampilanFrame) window.cancelAnimationFrame(gulirTampilanFrame);
        gulirTampilanFrame = window.requestAnimationFrame(() => {
            gulirTampilanFrame = 0;
            gulirKeTampilan(mode);
        });
    };

    const terapkanFilterGrid = () => {
        const kelas = elemen('[data-filter-kelas]', root)?.value ?? '';
        const status = elemen('[data-filter-status]', root)?.value ?? '';

        daftarElemen('[data-jadwal-chip], [data-conflict-box]', root).forEach((el) => {
            const kelasCocok = kelas === '' || el.dataset.kelasKey === '*' || el.dataset.kelasKey === kelas;
            const statusCocok = status === '' || el.dataset.status === status;
            el.hidden = !(kelasCocok && statusCocok && cocokCariElemen(el));
        });

        daftarElemen('[data-day-count]', root).forEach((label) => {
            const hari = label.dataset.dayCount;
            const jumlah = daftarElemen('[data-grid-cell]', root)
                .filter((sel) => sel.dataset.cellHari === hari)
                .reduce((total, sel) => total
                    + daftarElemen('[data-jadwal-chip]:not([hidden])', sel).length
                    + daftarElemen('[data-conflict-box]:not([hidden])', sel)
                        .reduce((isi, kotak) => isi + Number(kotak.dataset.jumlah ?? 0), 0), 0);
            setTeks(label, jumlah);

            const kepala = label.closest('[data-day-head]');
            if (kepala) {
                const tanggal = elemen(`[data-day-date="${hari}"]`, kepala)?.textContent?.trim() ?? '';
                kepala.title = `${hari}, ${tanggal} · ${jumlah} jadwal`;
            }
        });

        terapkanSesiKosong();
    };

    const terapkanSesiKosong = () => {
        const tampilkan = elemen('[data-toggle-empty-session]', root)?.checked ?? state.tampilkanKosong;
        const jam = menitDari(state.jam);
        const grupSesi = new Map();

        daftarElemen('[data-sesi-baris]', root).forEach((el) => {
            const kunci = String(el.dataset.sesiBaris ?? '');
            if (!grupSesi.has(kunci)) grupSesi.set(kunci, []);
            grupSesi.get(kunci).push(el);
        });

        grupSesi.forEach((elemenSesi, kunci) => {
            const adaIsi = elemenSesi.some((el) => elemen(
                '[data-jadwal-chip]:not([hidden]), [data-conflict-box]:not([hidden])',
                el,
            ));
            const memuatSekarang = state.weekOffset === 0 && elemenSesi.some((el) => {
                if (el.dataset.cellHari !== root.dataset.hariIni) return false;
                const mulai = menitDari(el.dataset.cellMulai);
                const selesai = menitDari(el.dataset.cellSelesai);

                return Number.isFinite(mulai) && Number.isFinite(selesai)
                    && jam >= mulai && jam <= selesai;
            });
            const tampil = tampilkan || adaIsi || memuatSekarang;

            elemenSesi.forEach((el) => {
                el.dataset.sesiKosong = adaIsi ? '0' : '1';
                el.hidden = !tampil;
            });
        });

        const elemenLuar = daftarElemen('[data-luar-row]', root);
        const adaLuar = elemenLuar.some((el) => elemen(
            '[data-jadwal-chip]:not([hidden]), [data-conflict-box]:not([hidden])',
            el,
        ));
        elemenLuar.forEach((el) => {
            el.hidden = !adaLuar;
        });
    };

    const terapkanFilterDaftar = () => {
        const hari = elemen('[data-list-hari]', root)?.value ?? '';
        const kelas = elemen('[data-list-kelas]', root)?.value ?? '';
        const cari = adminSearch ? state.cari : normalTeks(elemen('[data-list-search]', root)?.value ?? '');

        daftarElemen('[data-list-row]', root).forEach((baris) => {
            const statusCocok = state.filter === 'semua' || baris.dataset.status === state.filter;
            const hariCocok = hari === '' || baris.dataset.hari === hari;
            const kelasCocok = kelas === '' || baris.dataset.kelasKey === kelas;
            const cariCocok = cari === '' || (adminSearch
                ? cocokCariElemen(baris)
                : normalTeks(baris.dataset.search ?? '').includes(cari));
            baris.hidden = !(statusCocok && hariCocok && kelasCocok && cariCocok);
        });

        daftarElemen('[data-day-group]', root).forEach((grup) => {
            const adaBaris = daftarElemen('[data-list-row]', grup).some((baris) => !baris.hidden);
            grup.hidden = !adaBaris;
        });

        daftarElemen('[data-list-filter]', root).forEach((tombol) => {
            const aktif = tombol.dataset.listFilter === state.filter;
            tombol.setAttribute('aria-pressed', aktif ? 'true' : 'false');
            tombol.style.outline = aktif ? '2px solid rgba(26,107,219,.28)' : '';
        });

        const kosongDaftar = elemen('[data-cari-kosong]', root);
        if (kosongDaftar) {
            const adaBaris = daftarElemen('[data-list-row]', root).some((baris) => !baris.hidden);
            kosongDaftar.hidden = !adminSearch || cari === '' || adaBaris;
        }
    };

    const terapkanCariItemLive = () => {
        if (!adminSearch) return;
        daftarElemen('[data-timeline] [data-live-item], [data-agenda-list] [data-live-item]', root).forEach((el) => {
            el.hidden = !cocokCariElemen(el);
        });
    };

    const perbaruiEmptyCari = () => {
        const kosong = elemen('[data-cari-kosong-global]', root);
        const konten = elemen('[data-cari-konten]', root);
        const tidakAda = adminSearch && state.cari !== '' && itemsCari().length === 0;
        if (kosong) kosong.hidden = !tidakAda;
        if (konten) konten.hidden = tidakAda;
    };

    const sinkronkanRingkasan = () => {
        setTeks(elemen('[data-banner-saran]', root), daftarSaran.length);
        setTeks(elemen('[data-banner-bentrok]', root), grupBentrok.length);

        const sumber = itemsCari();
        const nilaiStatistik = {
            total: sumber.length,
            kelas: new Set(sumber.map((item) => item.rombel_key).filter(Boolean)).size,
            mapel: new Set(sumber.map((item) => item.mapel_id).filter(Boolean)).size,
            guru: new Set(sumber.map((item) => item.guru_id).filter(Boolean)).size,
            hari_ini: sumber.filter((item) => item.hari === root.dataset.hariIni).length,
        };

        Object.entries(nilaiStatistik).forEach(([kunci, nilai]) => {
            const target = elemen(`[data-stat="${kunci}"]`, root);
            setTeks(target, nilai);
            if (target) target.dataset.count = String(nilai);
        });
    };

    const posisiHari = (nama) => {
        const posisi = HARI_URUTAN.indexOf(nama);

        return posisi < 0 ? HARI_URUTAN.length : posisi;
    };

    const jadwalBerikutnya = (semua, sekarang) => {
        if (state.weekOffset < 0) return null;
        const posisiHariIni = posisiHari(root.dataset.hariIni);

        return semua
            .filter((item) => {
                const mulai = menitDari(item.mulai);
                if (! Number.isFinite(mulai)) return false;
                if (state.weekOffset > 0) return true;
                const posisi = posisiHari(item.hari);

                return posisi > posisiHariIni || (posisi === posisiHariIni && mulai > sekarang);
            })
            .sort((a, b) => posisiHari(a.hari) - posisiHari(b.hari)
                || menitDari(a.mulai) - menitDari(b.mulai))[0] ?? null;
    };

    const selisihMenuju = (item, sekarang) => {
        const posisi = posisiHari(item.hari);
        const posisiHariIni = posisiHari(root.dataset.hariIni);
        const hariLain = posisi < posisiHariIni ? posisi + 7 - posisiHariIni : posisi - posisiHariIni;

        return menitDari(item.mulai) - sekarang
            + (hariLain + (state.weekOffset * 7)) * 1440;
    };

    const perbaruiLive = (hariFokus) => {
        const sekarang = menitDari(state.jam);
        const semuaItem = itemsCari();
        const itemHariIni = semuaItem
            .filter((item) => item.hari === root.dataset.hariIni)
            .sort((a, b) => menitDari(a.mulai) - menitDari(b.mulai));

        const berjalan = itemHariIni.find((item) => {
            const mulai = menitDari(item.mulai);
            const selesai = menitDari(item.selesai);
            return Number.isFinite(mulai) && Number.isFinite(selesai) && sekarang >= mulai && sekarang <= selesai;
        });
        const berikutnya = berjalan !== null ? null : jadwalBerikutnya(semuaItem, sekarang);
        const selesaiHari = itemHariIni.length > 0 && berjalan === null
            && ! itemHariIni.some((item) => menitDari(item.mulai) > sekarang);
        const selesai = selesaiHari && berikutnya === null;
        const fokus = berjalan ?? berikutnya ?? (selesaiHari ? itemHariIni[itemHariIni.length - 1] : null);

        daftarElemen('[data-live-card]', root).forEach((kartu) => {
            const statusTeks = elemen('[data-live-status-text]', kartu);
            const titik = elemen('[data-live-status] span', kartu);
            const namaJurusan = {
                RPL: 'Rekayasa Perangkat Lunak',
                TKJ: 'Teknik Komputer Jaringan',
            };
            const jurusan = fokus
                ? (namaJurusan[fokus.jurusan_singkat] ?? (fokus.jurusan !== '' ? fokus.jurusan : 'Semua Jurusan'))
                : '';

            setTeks(statusTeks, berjalan ? 'Sedang berlangsung'
                : (berikutnya ? 'Berikutnya · '+(berikutnya.hari || '')
                    : (selesai ? 'Selesai hari ini' : 'Tidak ada jadwal')));
            if (titik) titik.style.background = berjalan ? '#6EE7A5' : (selesai ? '#8A98AC' : (fokus ? '#FDE68A' : '#8A98AC'));
            setTeks(elemen('[data-live-sesi]', kartu), fokus ? fokus.sesi_label : 'Belum ada jadwal');
            setTeks(elemen('[data-live-kelas]', kartu), fokus ? `${fokus.kelas} · ${jurusan}` : 'Tidak ada pelajaran hari ini');
            setTeks(elemen('[data-live-mapel]', kartu), fokus
                ? `${fokus.mapel} · Ruang ${fokus.ruang !== '' ? fokus.ruang : '—'}`
                : 'Nikmati waktu kosong Anda.');
            setTeks(elemen('[data-live-start]', kartu), fokus ? fokus.mulai : '--:--');
            setTeks(elemen('[data-live-end]', kartu), fokus ? fokus.selesai : '--:--');

            let keterangan = '—';
            let persen = 0;
            if (berjalan) {
                const total = Math.max(1, menitDari(berjalan.selesai) - menitDari(berjalan.mulai));
                const lewat = Math.max(0, sekarang - menitDari(berjalan.mulai));
                persen = Math.round(Math.min(100, Math.max(0, lewat / total * 100)));
                keterangan = `Sisa ${Math.max(0, total - lewat)} menit`;
            } else if (selesai) {
                keterangan = 'Selesai hari ini';
            } else if (fokus) {
                keterangan = teksHitungMundur(selisihMenuju(fokus, sekarang));
            }
            setTeks(elemen('[data-live-sisa]', kartu), keterangan);

            const timeline = elemen('[data-mini-timeline]', kartu);
            if (!timeline) return;
            const waktuMulai = fokus ? menitDari(fokus.mulai) : Number.NaN;
            const waktuSelesai = fokus ? menitDari(fokus.selesai) : Number.NaN;
            const timelineValid = fokus !== null
                && Number.isFinite(waktuMulai)
                && Number.isFinite(waktuSelesai)
                && waktuSelesai >= waktuMulai;
            timeline.hidden = !timelineValid;
            if (!timelineValid) return;

            const stateTimeline = berjalan ? 'berjalan' : (selesai ? 'selesai' : 'menunggu');
            const persenTimeline = berjalan ? persen : (selesai ? 100 : 0);
            timeline.dataset.state = stateTimeline;
            setTeks(elemen('[data-timeline-now]', timeline), state.jam);
            setTeks(elemen('[data-timeline-start]', timeline), fokus.mulai);
            setTeks(elemen('[data-timeline-end]', timeline), fokus.selesai);
            setTeks(elemen('[data-timeline-label]', timeline), berjalan
                ? 'Sedang berlangsung'
                : (selesai ? 'Sesi telah berakhir' : 'Menunggu dimulai'));
            const timelineFill = elemen('[data-timeline-fill]', timeline);
            if (timelineFill) timelineFill.style.width = `${persenTimeline}%`;
            const timelineDot = elemen('[data-timeline-dot]', timeline);
            if (timelineDot) timelineDot.style.left = `${persenTimeline}%`;
        });
    };

    const buatBarisRingkasan = (waktu) => {
        const baris = document.createElement('div');
        baris.className = 'jg-ringkasan-baris';
        baris.dataset.ringkasanBar = '';
        baris.dataset.waktu = waktu;

        const label = document.createElement('span');
        label.className = 'jg-ringkasan-waktu';
        label.dataset.ringkasanWaktu = '';
        label.textContent = waktu;

        const track = document.createElement('span');
        track.className = 'jg-ringkasan-track';
        track.setAttribute('aria-hidden', 'true');
        const fill = document.createElement('span');
        fill.className = 'jg-ringkasan-fill';
        fill.dataset.ringkasanFill = '';
        track.append(fill);

        const jumlah = document.createElement('span');
        jumlah.className = 'jg-ringkasan-jumlah';
        jumlah.dataset.ringkasanJumlah = '';

        baris.append(label, track, jumlah);
        return baris;
    };

    const perbaruiRingkasan = (hariFokus) => {
        const kartu = elemen('[data-ringkasan-hari-ini]', root);
        if (!kartu) return;

        try {
            const itemHari = itemsCari().filter((item) => item.hari === hariFokus);
            const total = itemHari.length;
            const jumlahKelas = new Set(itemHari.map((item) => item.rombel_key).filter(Boolean)).size;
            const jumlahRuang = new Set(itemHari
                .map((item) => String(item.ruang ?? '').trim())
                .filter(Boolean)).size;

            setTeks(elemen('[data-ringkasan-total]', kartu), total);
            setTeks(elemen('[data-ringkasan-kelas]', kartu), jumlahKelas);
            setTeks(elemen('[data-ringkasan-ruang]', kartu), jumlahRuang);
            setTeks(elemen('[data-ringkasan-badge]', kartu), `${total} Sesi`);

            const posisiHari = HARI_URUTAN.indexOf(hariFokus);
            const awal = new Date(`${root.dataset.weekStart}T00:00:00Z`);
            if (posisiHari >= 0 && !Number.isNaN(awal.getTime())) {
                awal.setUTCDate(awal.getUTCDate() + state.weekOffset * 7 + posisiHari);
                const tanggal = new Intl.DateTimeFormat('id-ID', {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                    timeZone: 'UTC',
                }).format(awal);
                const targetTanggal = elemen('[data-ringkasan-tanggal]', kartu);
                if (targetTanggal && targetTanggal.textContent !== tanggal) {
                    setTeks(targetTanggal, tanggal);
                    targetTanggal.classList.remove('jg-ringkasan-fade');
                    void targetTanggal.offsetWidth;
                    targetTanggal.classList.add('jg-ringkasan-fade');
                }
            }

            const adaBentrok = itemHari.some((item) => item.bentrok || item.status === 'bentrok');
            const adaPeringatan = itemHari.some((item) => item.perlu_disesuaikan || item.di_luar_sesi)
                || daftarSaran.some((item) => item.hari === hariFokus);
            const status = elemen('[data-ringkasan-status]', kartu);
            if (status) {
                const kunci = total === 0
                    ? 'kosong'
                    : (adaBentrok ? 'bentrok' : (adaPeringatan ? 'peringatan' : 'aman'));
                status.dataset.status = kunci;
                setTeks(elemen('[data-ringkasan-status-text]', status), {
                    kosong: 'Belum ada jadwal',
                    bentrok: 'Jadwal bentrok',
                    peringatan: 'Peringatan jadwal',
                    aman: 'Jadwal tidak bentrok',
                }[kunci]);
            }

            const kosong = elemen('[data-ringkasan-kosong]', kartu);
            if (kosong) kosong.hidden = total > 0;

            const chart = elemen('[data-ringkasan-chart]', kartu);
            if (chart) {
                chart.hidden = total === 0;
                if (total > 0) {
                    const labelSesi = daftarSesi
                        .map((sesi) => String(sesi.mulai ?? '').slice(0, 5))
                        .filter(Boolean);
                    const jumlahPerWaktu = new Map();
                    itemHari.forEach((item) => {
                        const waktu = String(item.mulai ?? '').slice(0, 5);
                        if (waktu === '') return;
                        if (!labelSesi.includes(waktu)) labelSesi.push(waktu);
                        jumlahPerWaktu.set(waktu, (jumlahPerWaktu.get(waktu) ?? 0) + 1);
                    });
                    labelSesi.sort();
                    const maks = Math.max(1, ...jumlahPerWaktu.values());
                    setTeks(elemen('[data-ringkasan-maks]', kartu), `Maks. ${maks} sesi`);

                    const barisAda = new Map(daftarElemen('[data-ringkasan-bar]', chart)
                        .map((baris) => [baris.dataset.waktu, baris]));
                    labelSesi.forEach((waktu) => {
                        let baris = barisAda.get(waktu);
                        if (!baris) baris = buatBarisRingkasan(waktu);
                        else barisAda.delete(waktu);
                        chart.append(baris);

                        const jumlah = jumlahPerWaktu.get(waktu) ?? 0;
                        baris.title = `${waktu} · ${jumlah} sesi`;
                        setTeks(elemen('[data-ringkasan-waktu]', baris), waktu);
                        setTeks(elemen('[data-ringkasan-jumlah]', baris), jumlah);
                        const fill = elemen('[data-ringkasan-fill]', baris);
                        if (fill) fill.style.width = `${jumlah > 0 ? Math.round(jumlah / maks * 100) : 0}%`;
                    });
                    barisAda.forEach((baris) => baris.remove());
                }
            }

            const galat = elemen('[data-ringkasan-error]', kartu);
            if (galat) galat.hidden = true;
        } catch {
            const galat = elemen('[data-ringkasan-error]', kartu);
            if (galat) galat.hidden = false;
        }
    };

    const perbaruiJam = () => {
        state.jam = jamSistem();
        daftarElemen('[data-now-label]', root).forEach((label) => setTeks(label, state.jam));

        const hariLive = state.weekOffset === 0 ? root.dataset.hariIni : '';
        daftarElemen('[data-live-item]', root).forEach((el) => {
            const mulai = menitDari(el.dataset.mulai);
            const selesai = menitDari(el.dataset.selesai);
            const sekarang = menitDari(state.jam);
            const live = hariLive !== ''
                && el.dataset.hari === hariLive
                && Number.isFinite(mulai) && Number.isFinite(selesai)
                && sekarang >= mulai && sekarang <= selesai;
            el.dataset.live = live ? 'true' : 'false';
            const badge = elemen('[data-live-badge]', el);
            if (badge) {
                badge.hidden = !live;
                badge.classList.toggle('hidden', !live);
            }
        });

        terapkanSesiKosong();
        daftarElemen('[data-now-line]', root).forEach((garis) => garis.remove());
        const hariIni = root.dataset.hariIni;
        if (state.weekOffset === 0 && hariAktif.includes(hariIni)) {
            const jam = menitDari(state.jam);
            const sel = daftarElemen('[data-grid-cell]', root).find((cell) => cell.dataset.cellJenis === 'sesi'
                && cell.dataset.cellHari === hariIni
                && !cell.hidden
                && jam >= menitDari(cell.dataset.cellMulai)
                && jam <= menitDari(cell.dataset.cellSelesai));
            if (sel) {
                const garis = document.createElement('div');
                garis.className = 'jg-now-line';
                garis.dataset.nowLine = '';
                const label = document.createElement('span');
                label.dataset.nowLabel = '';
                label.textContent = state.jam;
                garis.append(label);
                sel.append(garis);
            }
        }

        perbaruiLive(state.hariFokus);
    };

    const terapkanPekan = () => {
        const awal = new Date(`${root.dataset.weekStart}T00:00:00Z`);
        if (Number.isNaN(awal.getTime())) return;
        awal.setUTCDate(awal.getUTCDate() + state.weekOffset * 7);

        const tanggal = {};
        hariAktif.forEach((namaHari) => {
            const posisi = HARI_URUTAN.indexOf(namaHari);
            const hari = new Date(awal);
            hari.setUTCDate(hari.getUTCDate() + Math.max(0, posisi));
            tanggal[namaHari] = hari;
            const label = elemen(`[data-day-date="${namaHari}"]`, root);
            if (label) setTeks(label, tanggalPendek(hari));
        });

        const tanggalAktif = hariAktif.map((nama) => tanggal[nama]).filter(Boolean);
        if (tanggalAktif.length > 0) {
            const pertama = tanggalAktif[0];
            const terakhir = tanggalAktif[tanggalAktif.length - 1];
            const label = pertama.getUTCMonth() === terakhir.getUTCMonth() && pertama.getUTCFullYear() === terakhir.getUTCFullYear()
                ? `${pertama.getUTCDate()}–${terakhir.getUTCDate()} ${NAMA_BULAN[terakhir.getUTCMonth()]} ${terakhir.getUTCFullYear()}`
                : `${tanggalPendek(pertama)}–${tanggalPendek(terakhir)} ${terakhir.getFullYear()}`;
            setTeks(elemen('[data-week-label]', root), label);
        }

        const hariIniIso = root.dataset.todayIso;
        const tanggalHariIni = state.weekOffset === 0 ? hariIniIso : '';
        daftarElemen('[data-day-head]', root).forEach((kepala) => {
            const namaHari = kepala.dataset.dayHead;
            const hariIni = tanggalIsoLokal(tanggal[namaHari]) === tanggalHariIni;
            kepala.classList.toggle('jg-grid-head-today', hariIni);
            let badge = elemen('[data-today-badge]', kepala);
            if (hariIni && !badge) {
                badge = document.createElement('span');
                badge.className = 'jg-today-badge';
                badge.dataset.todayBadge = '';
                badge.textContent = 'Hari ini';
                kepala.querySelector('div')?.append(badge);
            } else if (!hariIni && badge) {
                badge.remove();
            }
        });
        daftarElemen('[data-grid-cell]', root).forEach((sel) => {
            sel.classList.toggle('jg-grid-cell-today',
                state.weekOffset === 0 && sel.dataset.cellHari === root.dataset.hariIni);
        });

        terapkanFilterGrid();
        perbaruiJam();
        perbaruiRingkasan(state.hariFokus);
    };

    const terapkanHari = (sinkronkanDaftar = false) => {
        daftarElemen('[data-day-tab]', root).forEach((tombol) => {
            tombol.setAttribute('aria-pressed', tombol.dataset.dayTab === state.hariFokus ? 'true' : 'false');
        });
        daftarElemen('[data-mobile-day]', root).forEach((tombol) => {
            tombol.setAttribute('aria-pressed', tombol.dataset.mobileDay === state.hariFokus ? 'true' : 'false');
        });
        daftarElemen('[data-timeline]', root).forEach((panel) => {
            panel.hidden = panel.dataset.timeline !== state.hariFokus;
        });
        daftarElemen('[data-mobile-item]', root).forEach((el) => {
            el.hidden = el.dataset.hari !== state.hariFokus || !cocokCariElemen(el);
        });
        daftarElemen('[data-mobile-empty]', root).forEach((el) => {
            const adaIsi = daftarElemen('[data-mobile-item]', root).some((item) => item.dataset.hari === el.dataset.hari && !item.hidden);
            el.hidden = el.dataset.hari !== state.hariFokus || adaIsi;
        });
        if (sinkronkanDaftar) {
            const pilihHari = elemen('[data-list-hari]', root);
            if (pilihHari && hariAktif.includes(state.hariFokus)) pilihHari.value = state.hariFokus;
        }
        const itemFokus = itemsCari().filter((item) => item.hari === state.hariFokus);
        setTeks(elemen('[data-mobile-count]', root), itemFokus.length);
        const perlu = itemFokus.filter((item) => item.perlu_disesuaikan).length;
        setTeks(elemen('[data-mobile-perlu]', root), perlu);
        terapkanFilterDaftar();
        perbaruiLive(state.hariFokus);
        perbaruiRingkasan(state.hariFokus);
    };

    const aturSesiTerisi = () => {
        const formHari = elemen('#jadwal-hari', form)?.value ?? '';
        const formSesi = elemen('#jadwal-sesi', form);
        const jadwalId = String(form.dataset.jadwalId ?? '');
        const terisi = new Map();

        items.forEach((item) => {
            if (item.hari !== formHari || item.sesi_id === null || String(item.id) === jadwalId) return;
            const nama = `${item.kelas}${item.jurusan_singkat !== '' ? ` ${item.jurusan_singkat}` : ''}`;
            terisi.set(String(item.sesi_id), nama);
        });

        daftarElemen('[data-sesi-pilih]', form).forEach((tombol) => {
            const pemilik = terisi.get(String(tombol.dataset.id));
            const dipilih = String(formSesi?.value ?? '') === String(tombol.dataset.id);
            tombol.disabled = root.dataset.role === 'admin' ? false : Boolean(pemilik);
            tombol.setAttribute('aria-pressed', dipilih ? 'true' : 'false');
            const status = elemen('[data-sesi-status]', tombol);
            if (status) {
                status.textContent = pemilik ? `Terisi · ${pemilik}` : (dipilih ? 'Dipilih' : 'Kosong');
                status.style.color = pemilik ? '#8A98AC' : '#0F6B3C';
            }
        });
    };

    const aturHariTerpilih = () => {
        const hari = elemen('#jadwal-hari', form)?.value ?? '';
        daftarElemen('[data-hari-pilih]', form).forEach((tombol) => {
            tombol.setAttribute('aria-pressed', tombol.dataset.hariPilih === hari ? 'true' : 'false');
        });
    };

    const buatElemen = (tag, kelas = '', teks = '') => {
        const node = document.createElement(tag);
        if (kelas) node.className = kelas;
        if (teks !== '') node.textContent = teks;
        return node;
    };

    const terapkanRekomendasi = (nilai = {}) => {
        if (nilai.sesi_id !== undefined && nilai.sesi_id !== null) {
            elemen('#jadwal-sesi', form).value = String(nilai.sesi_id);
            aturSesiTerisi();
        }
        if (nilai.ruang !== undefined && nilai.ruang !== null) {
            elemen('#jadwal-ruang', form).value = String(nilai.ruang);
        }
        cekBentrok();
    };

    const aturCheck = ({ aman = true, judul = 'Pemeriksaan bentrok', pesan = '', ringkasan = '', pemeriksaan = [], konflik = [], rekomendasi = [] }) => {
        const panel = elemen('[data-check-panel]', form);
        if (!panel) return;
        const sukses = aman;
        panel.style.borderColor = sukses ? '#B7E4C7' : '#F3B7B1';
        panel.style.background = sukses ? '#E0F6EA' : '#FDECEC';
        setTeks(elemen('[data-check-judul]', panel), judul);
        setTeks(elemen('[data-check-pesan]', panel), pesan);
        const ringkasanEl = elemen('[data-check-ringkasan]', panel);
        setTeks(ringkasanEl, ringkasan);
        if (ringkasanEl) ringkasanEl.classList.toggle('hidden', ringkasan === '');
        const detail = elemen('[data-check-detail]', panel);
        if (detail) {
            detail.replaceChildren();
            pemeriksaan.forEach((item) => {
                const baris = buatElemen('li', 'flex items-center gap-1.5 font-semibold');
                baris.style.color = item.aman ? '#0F6B3C' : '#A3261C';
                baris.append(buatElemen('span', '', item.aman ? '✓' : '×'), buatElemen('span', '', item.label));
                detail.append(baris);
            });
            detail.classList.toggle('hidden', pemeriksaan.length === 0);
        }
        const konflikEl = elemen('[data-check-konflik]', panel);
        if (konflikEl) {
            konflikEl.replaceChildren();
            konflik.forEach((item) => {
                const kartu = buatElemen('div', 'rounded-lg border border-[#F3B7B1] bg-white/70 px-3 py-2');
                kartu.append(buatElemen('p', 'text-xs font-bold text-[#A3261C]', item.judul ?? 'Bentrok'));
                kartu.append(buatElemen('p', 'mt-0.5 text-xs text-[#7A1D16]', item.pesan ?? ''));
                if (item.jadwal) {
                    const info = buatElemen('p', 'mt-1 text-[11px] text-[#7A1D16]');
                    info.textContent = `${item.jadwal.hari} · ${item.jadwal.jam} · ${item.jadwal.guru} · ${item.jadwal.mapel} · ${item.jadwal.kelas} · Ruang ${item.jadwal.ruang}`;
                    kartu.append(info);
                }
                konflikEl.append(kartu);
            });
            konflikEl.classList.toggle('hidden', konflik.length === 0);
        }
        const rekomendasiWrap = elemen('[data-check-rekomendasi-wrap]', panel);
        const rekomendasiEl = elemen('[data-check-rekomendasi]', panel);
        if (rekomendasiEl) {
            rekomendasiEl.replaceChildren();
            rekomendasi.forEach((item) => {
                const tombol = buatElemen(
                    'button',
                    'w-full rounded-lg border border-[var(--jg-line)] bg-white px-3 py-2 text-left transition hover:bg-[var(--jg-surface-soft)]',
                );
                tombol.type = 'button';
                tombol.dataset.rekomendasi = JSON.stringify(item.nilai ?? {});
                tombol.append(buatElemen('p', 'text-xs font-bold jg-title', item.label ?? 'Rekomendasi'));
                if (item.deskripsi) tombol.append(buatElemen('p', 'mt-0.5 text-[11px] jg-muted', item.deskripsi));
                tombol.addEventListener('click', () => terapkanRekomendasi(item.nilai ?? {}));
                rekomendasiEl.append(tombol);
            });
        }
        if (rekomendasiWrap) rekomendasiWrap.classList.toggle('hidden', !Array.isArray(rekomendasi) || rekomendasi.length === 0);
        const ikon = elemen('[data-check-icon]', panel);
        if (ikon) ikon.style.color = sukses ? '#0F6B3C' : '#A3261C';
        daftarElemen('[data-simpan-utama], [data-simpan-lagi]', root).forEach((tombol) => {
            tombol.disabled = !sukses;
        });
    };

    const cekBentrok = () => {
        if (!form || !storeUrl) return;
        window.clearTimeout(state.cekTimer);
        state.cekTimer = window.setTimeout(async () => {
            const hari = elemen('#jadwal-hari', form)?.value ?? '';
            const sesiId = elemen('#jadwal-sesi', form)?.value ?? '';
            const rombel = elemen('#jadwal-rombel', form)?.value ?? '';
            const ruang = elemen('#jadwal-ruang', form)?.value.trim() ?? '';

            if (!hari || !sesiId || !rombel) {
                aturCheck({
                    judul: 'Pemeriksaan bentrok',
                    pesan: 'Pilih hari, kelas, dan sesi untuk memeriksa bentrok. Ruang opsional, tetapi tetap dicek bila diisi.',
                });
                return;
            }

            const token = ++state.cekToken;
            const sesi = daftarSesi.find((item) => String(item.id) === String(sesiId));
            const data = new FormData(form);
            data.set('intent', 'cek-bentrok');
            data.set('jadwal_id', form.dataset.jadwalId || '0');
            data.set('sesi_label', sesi ? `Sesi ${sesi.nama}` : 'Sesi —');

            try {
                const respons = await fetch(intentUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: data,
                });
                const hasil = await respons.json().catch(() => null);
                if (token !== state.cekToken) return;
                if (!respons.ok || hasil?.status !== 'success') {
                    aturCheck({
                        aman: true,
                        judul: 'Pemeriksaan belum tersedia',
                        pesan: pesanGalat(hasil, 'Bentrok tidak dapat diperiksa saat ini. Jadwal tetap dapat disimpan.'),
                    });
                    return;
                }
                aturCheck({
                    aman: hasil.data.aman,
                    judul: hasil.data.aman ? 'Tidak ada bentrok' : 'Bentrok terdeteksi',
                    pesan: hasil.data.pesan,
                    ringkasan: hasil.data.ringkasan,
                    pemeriksaan: hasil.data.pemeriksaan ?? [],
                    konflik: hasil.data.konflik ?? [],
                    rekomendasi: hasil.data.rekomendasi ?? [],
                });
            } catch {
                if (token !== state.cekToken) return;
                aturCheck({
                    aman: true,
                    judul: 'Pemeriksaan belum tersedia',
                    pesan: 'Tidak dapat terhubung ke server. Jadwal tetap dapat disimpan.',
                });
            }
        }, 250);
    };

    const sinkronMapel = () => {
        const rombel = elemen('#jadwal-rombel', form);
        const opsi = rombel?.selectedOptions[0];
        if (!opsi?.value) return;
        const mapelInput = elemen('#jadwal-mapel', form);
        const mapelTampil = elemen('#jadwal-mapel-tampil', form);
        if (mapelInput && opsi.dataset.mapel) mapelInput.value = opsi.dataset.mapel;
        if (mapelTampil) mapelTampil.value = String(opsi.textContent).split(' — ')[0].trim();
        cekBentrok();
    };

    const sinkronGuru = () => {
        const guru = elemen('#jadwal-guru', form);
        if (!guru) return;

        daftarElemen('#jadwal-rombel option[data-guru]', form).forEach((opsi) => {
            opsi.hidden = opsi.dataset.guru !== '' && opsi.dataset.guru !== guru.value;
            opsi.disabled = opsi.hidden;
        });

        const rombel = elemen('#jadwal-rombel', form);
        if (rombel && rombel.selectedOptions[0]?.hidden) {
            rombel.value = '';
        }

        sinkronMapel();
    };

    const bukaForm = (mode, data = {}) => {
        if (!form) return;
        const id = String(data.id ?? '');
        const edit = mode === 'edit' && id !== '';
        form.dataset.mode = edit ? 'edit' : 'tambah';
        form.dataset.jadwalId = id;
        form.action = edit ? updateTemplate.replace('__ID__', id) : storeUrl;

        let metode = elemen('input[name="_method"]', form);
        if (edit && !metode) {
            metode = document.createElement('input');
            metode.type = 'hidden';
            metode.name = '_method';
            form.append(metode);
        }
        if (edit) {
            metode.value = 'PUT';
        } else if (metode) {
            metode.remove();
        }

        const item = semuaItems.find((siswa) => String(siswa.id) === id);
        const bukaDariOld = root.dataset.bukaModal === '1' && root.dataset.errorsAny === '1';
        const guruId = data.guruId ?? (bukaDariOld ? root.dataset.oldGuruId : '') ?? item?.guru_id ?? root.dataset.guruAwalId ?? '';
        const hari = data.hari ?? item?.hari ?? state.hariFokus ?? hariAktif[0] ?? '';
        const rombel = data.rombel ?? (bukaDariOld ? root.dataset.oldRombel : '') ?? (item ? `${item.kelas}|${item.jurusan}` : '');
        const ruang = data.ruang ?? item?.ruang ?? '';
        const sesiId = data.sesiId ?? item?.sesi_id ?? '';
        const mapelId = data.mapel ?? (bukaDariOld ? root.dataset.oldMapelId : '') ?? item?.mapel_id ?? root.dataset.mapelAwalId ?? '';
        const mapelNama = data.mapelNama ?? item?.mapel ?? root.dataset.mapelAwalNama ?? 'Mata Pelajaran';

        const inputGuru = elemen('#jadwal-guru', form);
        if (inputGuru) inputGuru.value = String(guruId);
        elemen('#jadwal-hari', form).value = hari;
        elemen('#jadwal-rombel', form).value = rombel;
        elemen('#jadwal-ruang', form).value = ruang;
        elemen('#jadwal-sesi', form).value = sesiId === null ? '' : String(sesiId);
        elemen('#jadwal-mapel', form).value = String(mapelId);
        elemen('#jadwal-mapel-tampil', form).value = String(mapelNama);
        form.dataset.jamLama = data.jamLama ?? item?.tersimpan ?? '';

        setTeks(elemen('#jadwal-modal-title', overlayForm), edit ? 'Ubah jadwal mengajar' : 'Tambah jadwal mengajar');
        setTeks(elemen('[data-label-simpan]', root), edit ? 'Simpan perubahan' : 'Simpan jadwal');
        aturHariTerpilih();
        sinkronGuru();
        aturSesiTerisi();
        aturCheck({
            judul: 'Pemeriksaan bentrok',
            pesan: 'Pilih hari, kelas, ruang, dan sesi untuk memeriksa bentrok secara langsung.',
        });
        bukaOverlay(overlayForm);
        cekBentrok();
    };

    const hitungPerubahan = () => {
        const saranTerpilih = daftarElemen('[data-saran-check]:checked', root).length;
        const bentrokTerpilih = daftarElemen('[data-bentrok-pilih]', root).filter((select) => select.value !== '').length;
        const jumlah = saranTerpilih + bentrokTerpilih;
        setTeks(elemen('[data-jumlah-perubahan]', root), jumlah);
        setTeks(elemen('[data-terapkan-label]', root), `Terapkan ${jumlah} perubahan`);
        const tombol = elemen('[data-terapkan]', root);
        if (tombol) tombol.disabled = jumlah === 0;
    };

    const bukaPenyesuaian = (fokus = '') => {
        if (fokus !== '') {
            const target = daftarElemen('[data-bentrok-grup]', root).find((el) => el.dataset.bentrokGrup === fokus);
            target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        hitungPerubahan();
        bukaOverlay(overlayPenyesuaian);
    };

    const kirimIntent = async (body, pesanBawaan) => {
        body.set('_token', csrf);
        const respons = await fetch(intentUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            body,
        });
        const hasil = await respons.json().catch(() => null);
        if (!respons.ok || hasil?.status !== 'success') {
            throw new Error(pesanGalat(hasil, pesanBawaan));
        }
        return hasil;
    };

    const selesaiPerubahan = (pesan) => {
        tutupOverlay(overlayPenyesuaian);
        notifikasi(pesan);
        window.setTimeout(() => window.location.reload(), 800);
    };

    const namaBerkasEkspor = (disposisi) => {
        const utf8 = /filename\*=UTF-8''([^;]+)/i.exec(disposisi ?? '');
        if (utf8) {
            try {
                return decodeURIComponent(utf8[1]);
            } catch {
                return utf8[1];
            }
        }

        return /filename="?([^";]+)"?/i.exec(disposisi ?? '')?.[1]
            ?? `jadwal-mengajar-${root.dataset.todayIso}.xlsx`;
    };

    const unduhBlobEkspor = (blob, nama) => {
        const url = URL.createObjectURL(blob);
        const tautan = document.createElement('a');
        tautan.href = url;
        tautan.download = nama;
        document.body.append(tautan);
        tautan.click();
        tautan.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    };

    const gagalEkspor = (pesan) => {
        if (window.Swal) {
            window.Swal.fire({
                icon: 'error',
                title: 'Gagal Mengunduh',
                text: pesan,
                confirmButtonText: 'Coba Lagi',
            });
            return;
        }
        window.alert(pesan);
    };

    const tombolEkspor = elemen('[data-ekspor]', root);
    const labelEkspor = elemen('[data-ekspor-label]', tombolEkspor ?? root);
    const labelEksporAwal = labelEkspor?.textContent.trim() || 'Export Excel';
    let timerEkspor = null;

    const perbaruiUrlCari = (nilai) => {
        const url = new URL(window.location.href);
        if (nilai === '') url.searchParams.delete('cari');
        else url.searchParams.set('cari', nilai.trim());
        window.history.replaceState({}, '', url.toString());

        if (!tombolEkspor?.dataset.exportUrl) return;
        const urlEkspor = new URL(tombolEkspor.dataset.exportUrl, window.location.origin);
        if (nilai === '') urlEkspor.searchParams.delete('cari');
        else urlEkspor.searchParams.set('cari', nilai.trim());
        tombolEkspor.dataset.exportUrl = urlEkspor.toString();
    };

    const terapkanCari = (nilai, sumber) => {
        if (!adminSearch) return;
        const mentah = String(nilai ?? '');
        state.cari = normalTeks(mentah);
        daftarElemen('[data-cari]', root).forEach((input) => {
            if (input !== sumber && input.value !== mentah) input.value = mentah;
        });
        perbaruiUrlCari(mentah);
        sinkronkanRingkasan();
        terapkanTampilan();
        terapkanPekan();
        terapkanHari();
        terapkanFilterGrid();
        terapkanFilterDaftar();
        terapkanCariItemLive();
        perbaruiJam();
        perbaruiEmptyCari();
    };

    const aturEkspor = (state, teks) => {
        window.clearTimeout(timerEkspor);
        if (!tombolEkspor) return;
        tombolEkspor.dataset.state = state;
        tombolEkspor.disabled = state === 'muat';
        if (labelEkspor && teks) labelEkspor.textContent = teks;
    };

    const jalankanEkspor = async () => {
        if (tombolEkspor?.dataset.state === 'muat') return;
        aturEkspor('muat', 'Menyiapkan...');

        try {
            const respons = await fetch(tombolEkspor?.dataset.exportUrl ?? '', {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const tipe = respons.headers.get('Content-Type') ?? '';

            if (!respons.ok || !tipe.includes('spreadsheet')) {
                let pesan = 'Export Excel gagal. Silakan coba lagi.';
                if (tipe.includes('application/json')) {
                    const data = await respons.json().catch(() => null);
                    if (data?.message) pesan = data.message;
                } else if (respons.status === 401 || respons.status === 419) {
                    pesan = 'Sesi Anda sudah habis. Muat ulang halaman lalu coba lagi.';
                } else if (respons.status === 403) {
                    pesan = 'Anda tidak punya akses untuk mengunduh data ini.';
                }
                throw new Error(pesan);
            }

            unduhBlobEkspor(await respons.blob(), namaBerkasEkspor(respons.headers.get('Content-Disposition')));
            const total = respons.headers.get('X-Total-Jadwal');
            aturEkspor('sukses', total && Number(total) > 0 ? `${total} jadwal diunduh` : 'Unduh selesai');
            timerEkspor = window.setTimeout(() => aturEkspor('idle', labelEksporAwal), 2400);
        } catch (galat) {
            aturEkspor('idle', labelEksporAwal);
            gagalEkspor(galat?.message || 'Tidak dapat terhubung ke server. Silakan coba lagi.');
        }
    };

    daftarElemen('[data-view-pilih]', root).forEach((tombol) => {
        tombol.addEventListener('click', () => {
            const mode = tombol.dataset.viewPilih;
            if (!['minggu', 'hari', 'daftar'].includes(mode) || state.view === mode) return;
            state.view = mode;
            terapkanTampilan();
            simpanTampilan();
            jadwalkanGulirTampilan(mode);
        });
    });

    daftarElemen('[data-day-tab], [data-mobile-day]', root).forEach((tombol) => {
        tombol.addEventListener('click', () => {
            const hari = tombol.dataset.dayTab ?? tombol.dataset.mobileDay;
            if (!hariAktif.includes(hari)) return;
            state.hariFokus = hari;
            state.hariUrl = hari;
            state.weekOffset = 0;
            terapkanPekan();
            terapkanHari(true);
            simpanTampilan();
        });
    });

    daftarElemen('[data-filter-kelas], [data-filter-status]', root).forEach((select) => {
        select.addEventListener('change', terapkanFilterGrid);
    });
    elemen('[data-toggle-empty-session]', root)?.addEventListener('change', (event) => {
        state.tampilkanKosong = event.target.checked;
        terapkanSesiKosong();
    });
    daftarElemen('[data-list-filter]', root).forEach((tombol) => {
        tombol.addEventListener('click', () => {
            state.filter = tombol.dataset.listFilter;
            terapkanFilterDaftar();
        });
    });
    daftarElemen('[data-list-hari], [data-list-kelas]', root).forEach((select) => {
        select.addEventListener('change', terapkanFilterDaftar);
    });
    if (adminSearch) {
        daftarElemen('[data-cari]', root).forEach((input) => {
            input.addEventListener('input', () => terapkanCari(input.value, input));
            input.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                terapkanCari(input.value, input);
            });
        });
    } else {
        elemen('[data-list-search]', root)?.addEventListener('input', terapkanFilterDaftar);
    }

    elemen('[data-week-prev]', root)?.addEventListener('click', () => {
        state.weekOffset -= 1;
        terapkanPekan();
    });
    elemen('[data-week-next]', root)?.addEventListener('click', () => {
        state.weekOffset += 1;
        terapkanPekan();
    });
    elemen('[data-week-today]', root)?.addEventListener('click', () => {
        state.weekOffset = 0;
        terapkanPekan();
    });

    elemen('[data-periode-button]', root)?.addEventListener('click', () => {
        notifikasi('Periode pembelajaran mengikuti pengaturan akademik yang sedang berjalan.');
    });
    tombolEkspor?.addEventListener('click', jalankanEkspor);
    elemen('[data-lihat-bentrok]', root)?.addEventListener('click', () => {
        state.view = 'daftar';
        state.filter = 'bentrok';
        terapkanTampilan();
        terapkanFilterDaftar();
        simpanTampilan();
        jadwalkanGulirTampilan('daftar');
    });

    daftarElemen('[data-buka-penyesuaian]', root).forEach((tombol) => {
        tombol.addEventListener('click', () => bukaPenyesuaian(tombol.dataset.fokusBentrok ?? ''));
    });
    daftarElemen('[data-tutup-penyesuaian]', root).forEach((tombol) => {
        tombol.addEventListener('click', () => tutupOverlay(overlayPenyesuaian));
    });
    daftarElemen('[data-tutup-jadwal-modal]', root).forEach((tombol) => {
        tombol.addEventListener('click', () => tutupOverlay(overlayForm));
    });
    [overlayForm, overlayPenyesuaian].forEach((overlay) => {
        overlay?.addEventListener('mousedown', (event) => {
            if (event.target === overlay) tutupOverlay(overlay);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (overlayForm?.dataset.open === 'true') tutupOverlay(overlayForm);
        else if (overlayPenyesuaian?.dataset.open === 'true') tutupOverlay(overlayPenyesuaian);
    });

    document.addEventListener('saskansa:delete-success', (event) => {
        if (event.detail?.jenis !== 'jadwal') return;
        const id = Number(event.detail.id);
        if (! Number.isFinite(id) || id <= 0) return;

        const indeks = items.findIndex((item) => Number(item.id) === id);
        if (indeks >= 0) items.splice(indeks, 1);

        daftarSaran = daftarSaran.filter((item) => Number(item.id) !== id);
        grupBentrok = grupBentrok
            .map((grup) => ({
                ...grup,
                items: (grup.items ?? []).filter((item) => Number(item.id) !== id),
                alternatif: (grup.alternatif ?? []).filter((item) => Number(item.id) !== id),
            }))
            .map((grup) => ({ ...grup, jumlah: (grup.items ?? []).length }))
            .filter((grup) => (grup.items ?? []).length > 1);

        daftarElemen('[data-saran-check], [data-bentrok-pilih]', root)
            .filter((el) => Number(el.dataset.id) === id)
            .forEach((el) => {
                if (el.matches('[data-saran-check]')) el.closest('label')?.remove();
                else el.closest('div.rounded-lg')?.remove();
            });

        daftarElemen(`[data-jadwal-chip][data-id="${id}"], [data-live-item][data-id="${id}"]`, root)
            .forEach((el) => el.remove());

        sinkronkanRingkasan();
        terapkanHari();
        terapkanFilterGrid();
        terapkanFilterDaftar();
        terapkanCariItemLive();
        perbaruiJam();
        perbaruiEmptyCari();
        hitungPerubahan();
    });

    daftarElemen('[data-tambah-jadwal-trigger]', root).forEach((tombol) => {
        tombol.addEventListener('click', () => {
            if (!tombol.disabled) bukaForm('tambah');
        });
    });

    root.addEventListener('click', (event) => {
        const edit = event.target.closest('[data-edit-jadwal-trigger]');
        if (edit) {
            event.preventDefault();
            const item = items.find((siswa) => String(siswa.id) === String(edit.dataset.id));
            if (!item) return;
            const waktuTersimpan = String(item.tersimpan ?? '').split('–');
            bukaForm('edit', {
                id: item.id,
                hari: item.hari,
                mapel: item.mapel_id,
                mapelNama: item.mapel,
                rombel: `${item.kelas}|${item.jurusan}`,
                sesiId: item.sesi_id,
                ruang: item.ruang,
                jamLama: item.tersimpan,
                mulai: waktuTersimpan[0] ?? '',
                selesai: waktuTersimpan[1] ?? '',
            });
            return;
        }

        const more = event.target.closest('[data-more-toggle]');
        if (more) {
            event.stopPropagation();
            const menu = elemen('[data-more-menu]', more.parentElement);
            const akanDibuka = menu?.classList.contains('hidden');
            daftarElemen('[data-more-menu]', root).forEach((item) => item.classList.add('hidden'));
            daftarElemen('[data-more-toggle]', root).forEach((item) => item.setAttribute('aria-expanded', 'false'));
            if (menu && akanDibuka) {
                menu.classList.remove('hidden');
                more.setAttribute('aria-expanded', 'true');
            }
            return;
        }

        if (!event.target.closest('[data-more-menu]')) {
            daftarElemen('[data-more-menu]', root).forEach((item) => item.classList.add('hidden'));
            daftarElemen('[data-more-toggle]', root).forEach((item) => item.setAttribute('aria-expanded', 'false'));
        }
    });

    if (form) {
        elemen('#jadwal-hari', form)?.addEventListener('change', () => {
            aturHariTerpilih();
            aturSesiTerisi();
            cekBentrok();
        });
        daftarElemen('[data-hari-pilih]', form).forEach((tombol) => {
            tombol.addEventListener('click', () => {
                elemen('#jadwal-hari', form).value = tombol.dataset.hariPilih;
                aturHariTerpilih();
                aturSesiTerisi();
                cekBentrok();
            });
        });
        daftarElemen('[data-sesi-pilih]', form).forEach((tombol) => {
            tombol.addEventListener('click', () => {
                if (tombol.disabled) return;
                elemen('#jadwal-sesi', form).value = tombol.dataset.id;
                aturSesiTerisi();
                cekBentrok();
            });
        });
        elemen('#jadwal-guru', form)?.addEventListener('change', sinkronGuru);
        elemen('#jadwal-rombel', form)?.addEventListener('change', sinkronMapel);
        elemen('#jadwal-ruang', form)?.addEventListener('input', cekBentrok);
        form.addEventListener('submit', () => {
            const label = elemen('[data-label-simpan]', root);
            if (label) label.textContent = 'Menyimpan...';
            daftarElemen('[data-simpan-utama], [data-simpan-lagi]', root).forEach((tombol) => {
                tombol.dataset.loading = '1';
                tombol.disabled = true;
            });
        });
    }

    const selectSemua = elemen('[data-saran-semua]', root);
    selectSemua?.addEventListener('change', () => {
        daftarElemen('[data-saran-check]', root).forEach((checkbox) => {
            checkbox.checked = selectSemua.checked;
        });
        hitungPerubahan();
    });
    daftarElemen('[data-saran-check]', root).forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            selectSemua.checked = daftarElemen('[data-saran-check]', root).every((item) => item.checked);
            hitungPerubahan();
        });
    });
    daftarElemen('[data-bentrok-pilih]', root).forEach((select) => {
        select.addEventListener('change', hitungPerubahan);
    });

    const tombolLain = elemen('[data-saran-lain]', root);
    if (tombolLain) {
        const teksAwal = tombolLain.textContent.trim();
        let terbuka = false;
        tombolLain.addEventListener('click', () => {
            terbuka = !terbuka;
            daftarElemen('[data-saran-extra="1"]', root).forEach((baris) => {
                baris.hidden = !terbuka;
            });
            tombolLain.textContent = terbuka ? 'Sembunyikan jadwal lainnya' : teksAwal;
        });
    }

    elemen('[data-terapkan]', root)?.addEventListener('click', async () => {
        const perubahan = [];
        daftarElemen('[data-saran-check]:checked', root).forEach((checkbox) => {
            perubahan.push({
                id: Number(checkbox.dataset.id),
                sesi_id: Number(checkbox.dataset.sesiId),
            });
        });
        daftarElemen('[data-bentrok-pilih]', root).forEach((select) => {
            if (select.value === '') return;
            perubahan.push({
                id: Number(select.dataset.id),
                sesi_id: Number(select.value),
            });
        });
        if (perubahan.length === 0) {
            notifikasi('Pilih minimal satu perubahan.', false);
            return;
        }
        const body = new FormData();
        body.set('intent', 'sesuaikan-massal');
        perubahan.forEach((item, index) => {
            body.set(`perubahan[${index}][id]`, String(item.id));
            body.set(`perubahan[${index}][sesi_id]`, String(item.sesi_id));
        });
        const tombol = elemen('[data-terapkan]', root);
        const label = elemen('[data-terapkan-label]', root);
        const teksAwal = label?.textContent ?? '';
        if (tombol) tombol.disabled = true;
        if (label) label.textContent = 'Menerapkan...';
        try {
            const hasil = await kirimIntent(body, 'Perubahan gagal diterapkan.');
            selesaiPerubahan(hasil.message);
        } catch (error) {
            notifikasi(error.message, false);
        } finally {
            if (label) label.textContent = teksAwal;
            if (tombol) tombol.disabled = false;
        }
    });

    daftarElemen('[data-undo-batch]', root).forEach((tombol) => {
        tombol.addEventListener('click', async () => {
            const setuju = await konfirmasi('Batalkan penyesuaian?', 'Jadwal akan dikembalikan ke jam sebelum perubahan ini diterapkan.');
            if (!setuju) return;
            const body = new FormData();
            body.set('intent', 'batalkan-penyesuaian');
            body.set('batch', tombol.dataset.undoBatch);
            const teksAwal = tombol.textContent.trim();
            tombol.disabled = true;
            tombol.textContent = 'Membatalkan...';
            try {
                const hasil = await kirimIntent(body, 'Penyesuaian gagal dibatalkan.');
                selesaiPerubahan(hasil.message);
            } catch (error) {
                notifikasi(error.message, false);
            } finally {
                tombol.disabled = false;
                tombol.textContent = teksAwal;
            }
        });
    });

    const flash = elemen('[data-flash-toast]', root);
    if (flash) {
        const pesan = flash.textContent.replace(/\s+/g, ' ').trim();
        if (pesan) {
            if (window.saskansaToast) window.saskansaToast.success(pesan, 'Berhasil');
            else notifikasi(pesan, true);
        }
        flash.remove();
    }

    const topbar = elemen('header.sticky');
    const perbaruiTopbar = () => topbar?.classList.toggle('jg-topbar-scroll', window.scrollY > 4);
    window.addEventListener('scroll', perbaruiTopbar, { passive: true });
    perbaruiTopbar();

    state.tampilkanKosong = elemen('[data-toggle-empty-session]', root)?.checked ?? false;
    sinkronkanRingkasan();
    terapkanTampilan();
    terapkanPekan();
    terapkanHari();
    terapkanFilterGrid();
    terapkanFilterDaftar();
    terapkanCariItemLive();
    perbaruiEmptyCari();
    hitungPerubahan();

    if (root.dataset.bukaModal === '1' && form) {
        bukaForm(root.dataset.modeAwal, {
            id: form.dataset.jadwalId,
            hari: elemen('#jadwal-hari', form)?.value,
            rombel: elemen('#jadwal-rombel', form)?.value,
            ruang: elemen('#jadwal-ruang', form)?.value,
            sesiId: elemen('#jadwal-sesi', form)?.value,
            mapel: elemen('#jadwal-mapel', form)?.value,
            mapelNama: elemen('#jadwal-mapel-tampil', form)?.value,
            jamLama: root.dataset.jamLama,
        });
    }

    window.setInterval(perbaruiJam, 60000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initJadwalGuru);
} else {
    initJadwalGuru();
}
