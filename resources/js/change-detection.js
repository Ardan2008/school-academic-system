const Registry = new WeakMap();
const ButtonState = new WeakMap();

const normalisasi = (nilai) => {
    if (nilai === undefined || nilai === null) return '';
    if (nilai instanceof FileList) {
        return Array.from(nilai)
            .map((file) => `${file.name}:${file.size}:${file.lastModified}`)
            .sort()
            .join('|');
    }
    if (typeof File !== 'undefined' && nilai instanceof File) {
        return `${nilai.name}:${nilai.size}:${nilai.lastModified}`;
    }
    if (nilai instanceof Date) return nilai.toISOString();
    if (Array.isArray(nilai)) {
        return nilai
            .map(normalisasi)
            .sort((a, b) => JSON.stringify(a).localeCompare(JSON.stringify(b)))
            .join('|');
    }
    if (typeof nilai === 'object') {
        return Object.keys(nilai)
            .sort()
            .map((kunci) => `${kunci}=${normalisasi(nilai[kunci])}`)
            .join('|');
    }
    if (typeof nilai === 'boolean') return nilai ? '1' : '';
    if (typeof nilai === 'string') return nilai.trim();
    return String(nilai);
};

const kunci = (nilai) => JSON.stringify(normalisasi(nilai));

const nilaiKontrol = (el) => {
    if (el.type === 'checkbox') {
        const grup = el.form?.querySelectorAll(`input[type="checkbox"][name="${CSS.escape(el.name)}"]`);
        if (grup && grup.length > 1) {
            return Array.from(grup).filter((item) => item.checked).map((item) => item.value);
        }
        return el.checked;
    }
    if (el.type === 'radio') {
        const grup = el.form?.querySelectorAll(`input[type="radio"][name="${CSS.escape(el.name)}"]`);
        return Array.from(grup || []).find((item) => item.checked)?.value ?? '';
    }
    if (el.tagName === 'SELECT' && el.multiple) {
        return Array.from(el.selectedOptions).map((option) => option.value);
    }
    if (el.type === 'file') return el.files;
    return el.value;
};

const kumpulkanNilai = (form, sources) => {
    const hasil = {};

    form.querySelectorAll('input, select, textarea').forEach((el) => {
        if (el.disabled || el.hasAttribute('data-change-ignore')) return;
        const nama = el.name || el.id;
        if (!nama) return;
        hasil[nama] = nilaiKontrol(el);
    });

    if (sources) {
        const sumber = typeof sources === 'function' ? sources() : sources;
        Object.entries(sumber || {}).forEach(([nama, nilai]) => {
            hasil[nama] = typeof nilai === 'function' ? nilai() : nilai;
        });
    }

    return Object.keys(hasil)
        .sort()
        .reduce((rapi, nama) => {
            rapi[nama] = kunci(hasil[nama]);
            return rapi;
        }, {});
};

const sama = (a, b) => {
    const ka = Object.keys(a);
    const kb = Object.keys(b);
    if (ka.length !== kb.length) return false;
    return ka.every((nama) => Object.prototype.hasOwnProperty.call(b, nama) && a[nama] === b[nama]);
};

const tombolForm = (form, selector) => {
    const hasil = new Set();
    const tambah = (el) => {
        if (el?.matches?.('button, input[type="submit"]') && (el.form === form || form.contains(el))) {
            hasil.add(el);
        }
    };

    form.querySelectorAll(selector).forEach(tambah);
    document.querySelectorAll(selector).forEach(tambah);
    return [...hasil];
};

const pasangKontrolTombol = (tombol, state, sinkron) => {
    if (ButtonState.has(tombol)) {
        ButtonState.get(tombol).sinkron = sinkron;
        return;
    }

    const kontrol = { sibuk: Boolean(tombol.disabled), sinkron };
    ButtonState.set(tombol, kontrol);

    Object.defineProperty(tombol, 'disabled', {
        configurable: true,
        get() {
            if (state.selaluAktif) return kontrol.sibuk;
            return kontrol.sibuk || !state.bersih || !state.valid || !state.aktif;
        },
        set(nilai) {
            kontrol.sibuk = Boolean(nilai);
            kontrol.sinkron();
        },
    });
};

const buatPengontrol = (form, options) => {
    const state = {
        awal: null,
        bersih: false,
        valid: true,
        aktif: true,
        selaluAktif: Boolean(options.always),
        sumber: options.sources,
        selectorSimpan: options.save || '[data-change-save]',
        selectorError: options.error || '[data-change-error], [data-modal-error], [data-submit-error]',
        tombol: [],
        observer: null,
        errorObserver: null,
        onPerubahan: options.onDirtyChange,
    };

    const nilaiSekarang = () => kumpulkanNilai(form, state.sumber);

    const adaError = () => Array.from(form.querySelectorAll(state.selectorError)).some((el) =>
        !el.classList.contains('hidden') && el.textContent.trim() !== ''
    );

    const hitungValid = () => state.valid && !adaError() && (state.selaluAktif || form.checkValidity());

    const sinkron = () => {
        const bersih = state.awal !== null ? !sama(state.awal, nilaiSekarang()) : false;
        const berubah = state.bersih !== bersih;
        state.bersih = bersih;
        state.tombol.forEach((tombol) => {
            const kontrol = ButtonState.get(tombol);
            if (!kontrol) return;
            tombol.classList.toggle('opacity-60', Object.getOwnPropertyDescriptor(tombol, 'disabled').get.call(tombol));
        });
        if (berubah) state.onPerubahan?.(bersih);
    };

    let jadwal = null;
    const jadwalkanSinkron = () => {
        if (jadwal !== null) return;
        jadwal = requestAnimationFrame(() => {
            jadwal = null;
            sinkron();
        });
    };

    state.tombol = tombolForm(form, state.selectorSimpan);
    state.tombol.forEach((tombol) => pasangKontrolTombol(tombol, state, sinkron));

    form.addEventListener('input', jadwalkanSinkron);
    form.addEventListener('change', jadwalkanSinkron);

    state.observer = new MutationObserver(jadwalkanSinkron);
    state.observer.observe(form, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class', 'aria-pressed', 'data-selected', 'value'],
    });

    state.errorObserver = new MutationObserver(jadwalkanSinkron);
    form.querySelectorAll(state.selectorError).forEach((el) => {
        state.errorObserver.observe(el, {
            childList: true,
            subtree: true,
            characterData: true,
            attributes: true,
            attributeFilter: ['class', 'hidden'],
        });
    });

    const api = {
        form,
        snapshot() {
            state.awal = nilaiSekarang();
            sinkron();
            return this;
        },
        refresh: jadwalkanSinkron,
        dirty() {
            return state.awal !== null && !sama(state.awal, nilaiSekarang());
        },
        valid(nilai) {
            state.valid = nilai !== false;
            sinkron();
            return this;
        },
        active(nilai) {
            state.aktif = nilai !== false;
            sinkron();
            return this;
        },
        busy(nilai) {
            state.tombol.forEach((tombol) => {
                ButtonState.get(tombol).sibuk = Boolean(nilai);
            });
            sinkron();
            return this;
        },
        always(nilai) {
            state.selaluAktif = nilai !== false;
            sinkron();
            return this;
        },
        updateSources(sumber) {
            state.sumber = sumber;
            jadwalkanSinkron();
            return this;
        },
        destroy() {
            state.observer?.disconnect();
            state.errorObserver?.disconnect();
            Registry.delete(form);
            return this;
        },
    };

    api.snapshot();
    return api;
};

const changeDetection = () => {
    const bind = (form, options = {}) => {
        if (!form) return null;
        const ada = Registry.get(form);
        if (ada) {
            if (options.sources) ada.updateSources(options.sources);
            if (options.always !== undefined) ada.always(options.always);
            return ada;
        }
        const pengontrol = buatPengontrol(form, options);
        Registry.set(form, pengontrol);
        return pengontrol;
    };

    const get = (form) => Registry.get(form) || null;
    const resolve = (form) => get(form);

    return {
        bind,
        get,
        resolve,
        snapshot(form) {
            const pengontrol = resolve(form);
            pengontrol?.snapshot();
            return pengontrol;
        },
        refresh(form) {
            resolve(form)?.refresh();
        },
        dirty(form) {
            return Boolean(resolve(form)?.dirty());
        },
        valid(form, nilai) {
            resolve(form)?.valid(nilai);
        },
        active(form, nilai) {
            resolve(form)?.active(nilai);
        },
        busy(form, nilai) {
            resolve(form)?.busy(nilai);
        },
        always(form, nilai) {
            resolve(form)?.always(nilai);
        },
    };
};

export default changeDetection;
