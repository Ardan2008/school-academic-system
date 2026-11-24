<?php

namespace App\Services;

use App\Models\JadwalPelajaran;
use App\Models\KelasRombel;
use App\Models\MataPelajaran;
use App\Models\Materi;
use App\Models\Nilai;
use App\Models\Notification;
use App\Models\PengumpulanTugas;
use App\Models\Pengumuman;
use App\Models\Tugas;
use App\Models\User;
use App\Support\Kelas;
use App\Support\Periode;
use App\Support\Waktu;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Satu-satunya pembuat notifikasi Guru dan Siswa.
 *
 * Setiap notifikasi yang keluar dari kelas ini bersumber dari baris data nyata
 * yang tersimpan di database (tugas, materi, nilai, pengumpulan, jadwal,
 * pengumuman), dan penerimanya dihitung dari data yang sama. reference_key
 * dipakai agar satu peristiwa tidak pernah menghasilkan dua baris notifikasi
 * untuk orang yang sama, jadi pemeriksaan berkala boleh dijalankan sesering
 * apa pun tanpa menumbuhkan duplikat.
 */
class NotifikasiAkademikService
{
    /** Ikon bawaan per kategori; halaman notifikasi mengenali daftar ini. */
    private const IKON = [
        Notification::TIPE_TUGAS_BARU => 'clipboard-list',
        Notification::TIPE_DEADLINE_TUGAS => 'clock',
        Notification::TIPE_NILAI => 'award',
        Notification::TIPE_JADWAL => 'calendar-days',
        Notification::TIPE_PENGUMUMAN => 'megaphone',
        Notification::TIPE_PENGUMPULAN_TUGAS => 'inbox',
        Notification::TIPE_INPUT_NILAI => 'square-pen',
        Notification::TIPE_TUGAS_AKADEMIK => 'clipboard-list',
    ];

    /**
     * Sisipkan satu notifikasi. Mengembalikan model ketika baris baru benar-benar
     * dibuat, dan null ketika notifikasi untuk peristiwa yang sama sudah ada.
     */
    public function kirim(
        User $penerima,
        string $tipe,
        string $judul,
        string $pesan,
        ?string $url = null,
        ?string $referenceKey = null,
        ?string $ikon = null,
    ): ?Notification {
        $baris = $this->baris($penerima, $tipe, $judul, $pesan, $url, $referenceKey, $ikon);

        return DB::transaction(function () use ($baris, $referenceKey): ?Notification {
            if ($this->sisipkan([$baris]) === 1) {
                return Notification::query()
                    ->where('user_id', $baris['user_id'])
                    ->where('reference_key', $referenceKey)
                    ->first();
            }

            return null;
        });
    }

    /**
     * Sisipkan notifikasi yang sama untuk banyak penerima sekaligus.
     *
     * @param  Collection<int, User>|array<int, User>  $penerima
     */
    public function kirimKeBanyak(
        Collection|array $penerima,
        string $tipe,
        string $judul,
        string $pesan,
        ?string $url = null,
        ?string $referenceKey = null,
        ?string $ikon = null,
    ): int {
        $baris = collect($penerima)
            ->filter(fn ($user) => $user instanceof User)
            ->map(fn (User $user) => $this->baris($user, $tipe, $judul, $pesan, $url, $referenceKey, $ikon))
            ->values()
            ->all();

        return $this->sisipkan($baris);
    }

    /**
     * Siswa aktif pada satu rombel. Jurusan kosong berarti semua siswa pada
     * kelas itu, sama seperti arti kolom jurusan pada tugas dan materi.
     *
     * @return Collection<int, User>
     */
    public function siswaDiRombel(?string $kelas, ?string $jurusan): Collection
    {
        $kelas = Kelas::normalize((string) $kelas);

        if ($kelas === '') {
            return collect();
        }

        $jurusan = trim((string) $jurusan);

        return User::query()
            ->where('role', 'siswa')
            ->where('status', User::STATUS_AKTIF)
            ->where('kelas', $kelas)
            ->when($jurusan !== '', fn ($query) => $query->where('jurusan', $jurusan))
            ->get();
    }

    /**
     * Guru yang berhak tahu tentang satu tugas: guru pembuatnya, atau bila
     * baris tugas tidak mencantumkan guru, semua guru yang mengampu mata
     * pelajaran tugas itu (dicocokkan lewat nama seperti Input Nilai).
     *
     * @return Collection<int, User>
     */
    public function guruPengampuTugas(Tugas $tugas): Collection
    {
        if ($tugas->guru_id !== null) {
            $guru = User::query()
                ->where('id', $tugas->guru_id)
                ->where('role', 'guru')
                ->where('status', User::STATUS_AKTIF)
                ->first();

            return $guru !== null ? collect([$guru]) : collect();
        }

        $namaMapel = trim((string) ($tugas->mataPelajaran?->nama ?? ''));

        if ($namaMapel === '') {
            return collect();
        }

        return User::query()
            ->where('role', 'guru')
            ->where('status', User::STATUS_AKTIF)
            ->get()
            ->filter(fn (User $guru) => strcasecmp(trim((string) $guru->mata_pelajaran), $namaMapel) === 0)
            ->values();
    }

    /** Guru pengampu satu baris jadwal (guru_id kosong berarti tidak ada). */
    public function guruPengampuJadwal(JadwalPelajaran $jadwal): Collection
    {
        if ($jadwal->guru_id === null) {
            return collect();
        }

        $guru = User::query()
            ->where('id', $jadwal->guru_id)
            ->where('role', 'guru')
            ->where('status', User::STATUS_AKTIF)
            ->first();

        return $guru !== null ? collect([$guru]) : collect();
    }

    // Peristiwa yang langsung menghasilkan notifikasi

    /** Tugas Baru: guru memberi tugas kepada satu rombel. */
    public function tugasBaru(Tugas $tugas): int
    {
        $tenggat = $tugas->deadline !== null
            ? 'Tenggat '.Waktu::lengkap($tugas->deadline)
            : 'Tanpa tenggat';

        return $this->kirimKeBanyak(
            $this->siswaDiRombel($tugas->kelas, $tugas->jurusan),
            Notification::TIPE_TUGAS_BARU,
            'Tugas Baru: '.$tugas->judul,
            ($tugas->mataPelajaran?->nama ?? 'Mata pelajaran').' · Kelas '.$tugas->kelas
                .($tugas->jurusan ? ' ('.$tugas->jurusan.')' : '').' · '.$tenggat,
            route('dashboard.siswa.tugas-materi.index', ['tab' => 'tugas']),
            'tugas-baru:tugas:'.$tugas->id,
        );
    }

    /** Tugas Baru (materi): guru membagikan materi kepada satu rombel. */
    public function materiBaru(Materi $materi): int
    {
        return $this->kirimKeBanyak(
            $this->siswaDiRombel($materi->kelas, $materi->jurusan),
            Notification::TIPE_TUGAS_BARU,
            'Materi Baru: '.$materi->judul,
            ($materi->mataPelajaran?->nama ?? 'Mata pelajaran').' · Kelas '.$materi->kelas
                .($materi->jurusan ? ' ('.$materi->jurusan.')' : ''),
            route('dashboard.siswa.tugas-materi.index', ['tab' => 'materi']),
            'materi-baru:materi:'.$materi->id,
        );
    }

    /** Nilai Diperbarui: guru menginput atau memperbarui nilai satu siswa. */
    public function nilaiDiperbarui(User $siswa, int $mataPelajaranId, float $nilai, float $kkm, string $tahunAjaran, string $semester): ?Notification
    {
        $mapel = MataPelajaran::find($mataPelajaranId);

        return $this->kirim(
            $siswa,
            Notification::TIPE_NILAI,
            'Nilai '.($mapel?->nama ?? 'mata pelajaran').' Diperbarui',
            'Nilai '.$this->formatAngka($nilai).' (KKM '.$this->formatAngka($kkm).') · '
                .'Semester '.$semester.' '.$tahunAjaran,
            route('dashboard.siswa.rapor.index', ['semester' => $semester, 'tahun_ajaran' => $tahunAjaran]),
            'nilai:mapel:'.$mataPelajaranId.':'.$tahunAjaran.':'.$semester.':'.$this->formatAngka($nilai).':'.$this->formatAngka($kkm),
        );
    }

    /**
     * Pengumpulan Tugas: siswa mengumpulkan (atau mengumpulkan ulang) tugas.
     * Hanya guru pengampu tugas yang menerima notifikasi ini.
     */
    public function pengumpulanTugas(PengumpulanTugas $pengumpulan, bool $baru): int
    {
        $tugas = $pengumpulan->tugas;
        $siswa = $pengumpulan->user;

        if ($tugas === null || $siswa === null) {
            return 0;
        }

        $terlambat = $tugas->deadline !== null
            && $pengumpulan->dikumpulkan_at !== null
            && $tugas->deadline->lt($pengumpulan->dikumpulkan_at);

        $pesan = $siswa->name.' (Kelas '.($siswa->kelas ?? '-')
            .($siswa->jurusan ? ' · '.$siswa->jurusan : '').') '
            .($baru ? 'mengumpulkan' : 'mengumpulkan ulang')
            .' tugas "'.$tugas->judul.'"'
            .($terlambat ? ' · terlambat dari tenggat' : '');

        return $this->kirimKeBanyak(
            $this->guruPengampuTugas($tugas),
            Notification::TIPE_PENGUMPULAN_TUGAS,
            'Pengumpulan Tugas: '.$tugas->judul,
            $pesan,
            $this->guruUrlTugas($tugas),
            'pengumpulan:tugas:'.$tugas->id.':siswa:'.$siswa->id.':'.($pengumpulan->dikumpulkan_at?->getTimestamp() ?? 0),
        );
    }

    /**
     * Penilaian Tugas: guru menyimpan nilai dan umpan balik, siswa yang bersangkutan
     * menerima notifikasi yang mengarah ke halaman Tugas & Materi.
     */
    public function penilaianTugas(PengumpulanTugas $pengumpulan): ?Notification
    {
        $tugas = $pengumpulan->tugas;
        $siswa = $pengumpulan->user;

        if ($tugas === null || $siswa === null) {
            return null;
        }

        $pesan = 'Tugas "'.$tugas->judul.'" dinilai '
            .($pengumpulan->nilai !== null ? $this->formatAngka((float) $pengumpulan->nilai) : '-');

        if (filled($pengumpulan->umpan_balik)) {
            $pesan .= ' · Umpan balik: '.$pengumpulan->umpan_balik;
        }

        return $this->kirim(
            $siswa,
            Notification::TIPE_NILAI,
            'Nilai Tugas: '.$tugas->judul,
            $pesan,
            route('dashboard.siswa.tugas-materi.index', ['tab' => 'tugas']),
            'penilaian-tugas:'.$pengumpulan->id.':'.$pengumpulan->updated_at?->getTimestamp(),
        );
    }

    /**
     * Perubahan Jadwal: satu baris jadwal dibuat, diubah, atau dihapus.
     * Siswa penerima adalah siswa rombel baris jadwal (ditambah rombel lama
     * bila rombelnya dipindahkan), guru penerima adalah guru pengampunya.
     *
     * @param  Collection<int, User>|array<int, User>|null  $penerimaTambahan
     */
    public function jadwalBerubah(
        JadwalPelajaran $jadwal,
        string $aksi,
        ?string $kelasLama = null,
        ?string $jurusanLama = null,
        ?Collection $penerimaTambahan = null,
    ): int {
        $label = match ($aksi) {
            'dihapus' => 'Jadwal dihapus',
            'diubah' => 'Jadwal diubah',
            default => 'Jadwal baru',
        };

        $deskripsi = ($jadwal->mataPelajaran?->nama ?? 'Mata pelajaran')
            .' · Kelas '.$jadwal->kelas
            .($jadwal->jurusan ? ' ('.$jadwal->jurusan.')' : '')
            .' · '.$jadwal->hari.' '.$jadwal->jamLabel()
            .($jadwal->ruang ? ' · Ruang '.$jadwal->ruang : '');

        $siswa = $this->siswaDiRombel($jadwal->kelas, $jadwal->jurusan);

        // Rombel lama ikut diberi tahu ketika jadwal dipindahkan ke kelas lain,
        // karena jadwal yang tadinya ada di kelas mereka juga berubah.
        if ($kelasLama !== null && Kelas::normalize((string) $kelasLama) !== Kelas::normalize((string) $jadwal->kelas)) {
            $siswa = $siswa->merge($this->siswaDiRombel($kelasLama, $jurusanLama))->unique('id')->values();
        }

        $terkirim = $this->kirimKeBanyak(
            $siswa,
            Notification::TIPE_JADWAL,
            'Perubahan Jadwal Pelajaran',
            $label.': '.$deskripsi,
            route('dashboard.siswa.jadwal-pelajaran.index'),
            'jadwal:'.$jadwal->id.':'.$aksi.':'.$jadwal->updated_at?->getTimestamp(),
        );

        $guru = $this->guruPengampuJadwal($jadwal);

        if ($penerimaTambahan !== null) {
            $guru = $guru->merge($penerimaTambahan->filter(fn ($u) => $u instanceof User))->unique('id')->values();
        }

        return $terkirim + $this->kirimKeBanyak(
            $guru,
            Notification::TIPE_JADWAL,
            'Perubahan Jadwal Mengajar',
            $label.': '.$deskripsi,
            $this->guruUrlJadwal(),
            'jadwal:'.$jadwal->id.':'.$aksi.':'.$jadwal->updated_at?->getTimestamp(),
        );
    }

    /**
     * Pengumuman Sekolah: bagikan notifikasi pengumuman kepada sasaran yang
     * ditentukan admin (role, kelas, dan jurusan). Setiap pengumuman hanya
     * terkirim sekali per penerima lewat reference_key.
     */
    public function kirimPengumuman(Pengumuman $pengumuman): int
    {
        if (! $pengumuman->exists || ! $pengumuman->terbit()->whereKey($pengumuman->id)->exists()) {
            return 0;
        }

        $judul = 'Pengumuman Sekolah: '.$pengumuman->judul;
        // Isi penuh disimpan (bukan dipotong) supaya panel notifikasi siswa
        // dapat menampilkan teks lengkap dengan tombol "Lihat selengkapnya";
        // pemotongan visual dilakukan oleh UI (line-clamp) di tiap tampilan.
        $pesan = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $pengumuman->isi)));
        $reference = 'pengumuman:'.$pengumuman->id;

        $terkirim = 0;

        if ($pengumuman->untukGuru()) {
            $guru = User::query()
                ->where('role', 'guru')
                ->where('status', User::STATUS_AKTIF)
                ->get();

            $terkirim += $this->kirimKeBanyak(
                $guru,
                Notification::TIPE_PENGUMUMAN,
                $judul,
                $pesan,
                route('dashboard.guru.index'),
                $reference,
            );
        }

        if ($pengumuman->untukSiswaRole()) {
            if (blank($pengumuman->kelas)) {
                $siswa = User::query()
                    ->where('role', 'siswa')
                    ->where('status', User::STATUS_AKTIF)
                    ->when(filled($pengumuman->jurusan), fn ($q) => $q->where('jurusan', $pengumuman->jurusan))
                    ->get();
            } else {
                $siswa = $this->siswaDiRombel($pengumuman->kelas, $pengumuman->jurusan);
            }

            $terkirim += $this->kirimKeBanyak(
                $siswa,
                Notification::TIPE_PENGUMUMAN,
                $judul,
                $pesan,
                route('dashboard.siswa.index'),
                $reference,
            );
        }

        return $terkirim;
    }

    // Pengingat yang dihasilkan pemeriksaan berkala

    /**
     * Pengingat Deadline untuk siswa yang belum mengumpulkan tugas yang
     * tenggatnya sudah dekat. Hanya siswa rombel tugas tanpa pengumpulan.
     */
    public function pengingatDeadlineTugas(Tugas $tugas): int
    {
        if ($tugas->deadline === null || $tugas->deadline->isPast()) {
            return 0;
        }

        $sudah = PengumpulanTugas::query()
            ->where('tugas_id', $tugas->id)
            ->pluck('user_id')
            ->all();

        $belum = $this->siswaDiRombel($tugas->kelas, $tugas->jurusan)
            ->reject(fn (User $siswa) => in_array($siswa->id, $sudah, true));

        return $this->kirimKeBanyak(
            $belum,
            Notification::TIPE_DEADLINE_TUGAS,
            'Tenggat Tugas Mendekat: '.$tugas->judul,
            ($tugas->mataPelajaran?->nama ?? 'Mata pelajaran').' · berakhir '
                .Waktu::lengkap($tugas->deadline).'. Segera kumpulkan sebelum tenggat.',
            route('dashboard.siswa.tugas-materi.index', ['tab' => 'tugas']),
            'deadline:tugas:'.$tugas->id.':'.$tugas->deadline->getTimestamp(),
        );
    }

    /**
     * Pengingat Tugas Akademik untuk guru: tugas yang guru itu berikan akan
     * mencapai tenggat pengumpulan, lengkap dengan jumlah siswa yang belum
     * mengumpulkan.
     */
    public function pengingatTugasAkademik(Tugas $tugas): int
    {
        if ($tugas->deadline === null || $tugas->deadline->isPast()) {
            return 0;
        }

        $rombel = $this->siswaDiRombel($tugas->kelas, $tugas->jurusan);
        $sudahKumpul = PengumpulanTugas::query()
            ->where('tugas_id', $tugas->id)
            ->whereIn('user_id', $rombel->pluck('id')->all())
            ->distinct()
            ->pluck('user_id')
            ->count();
        $belum = $rombel->count() - $sudahKumpul;

        return $this->kirimKeBanyak(
            $this->guruPengampuTugas($tugas),
            Notification::TIPE_TUGAS_AKADEMIK,
            'Tenggat Tugas Anda Mendekat: '.$tugas->judul,
            'Tugas Kelas '.$tugas->kelas.($tugas->jurusan ? ' ('.$tugas->jurusan.')' : '')
                .' berakhir '.Waktu::lengkap($tugas->deadline)
                .'; '.$belum.' dari '.$rombel->count().' siswa belum mengumpulkan.',
            $this->guruUrlTugas($tugas),
            'tugas-akademik:tugas:'.$tugas->id.':'.$tugas->deadline->getTimestamp(),
        );
    }

    /**
     * Pengingat Input Nilai untuk guru: masih ada siswa yang belum punya
     * nilai pada mata pelajaran yang diampu. reference_key memakai pekan ISO,
     * sehingga pengingat ini paling sering muncul sekali per pekan.
     *
     * @param  array<int, array{kelas: string, jurusan?: string|null}>  $kelas  daftar rombel yang belum lengkap
     */
    public function pengingatInputNilai(
        User $guru,
        int $jumlahSiswa,
        array $kelas,
        string $mataPelajaran,
        string $tahunAjaran,
        string $semester,
        ?string $pekanIso = null,
    ): ?Notification {
        $labelKelas = collect($kelas)
            ->map(fn ($rombel) => is_array($rombel)
                ? ($rombel['kelas'] ?? '').(filled($rombel['jurusan'] ?? null) ? ' ('.$rombel['jurusan'].')' : '')
                : (string) $rombel)
            ->implode(', ');

        return $this->kirim(
            $guru,
            Notification::TIPE_INPUT_NILAI,
            'Pengingat Input Nilai',
            $jumlahSiswa.' siswa belum memiliki nilai '.$mataPelajaran
                .' pada Semester '.$semester.' '.$tahunAjaran.' ('.$labelKelas.').',
            route('dashboard.guru.input-nilai.index'),
            'input-nilai:guru:'.$guru->id.':'.$tahunAjaran.':'.$semester.':'.($pekanIso ?? now()->format('o-W')),
        );
    }

    /**
     * URL tujuan notifikasi guru untuk tugas: mengarah ke halaman rekap/detail tugas.
     */
    public function guruUrlTugas(Tugas $tugas): string
    {
        if (Route::has('dashboard.guru.tugas-materi.tugas.show')) {
            return route('dashboard.guru.tugas-materi.tugas.show', ['tugas' => $tugas->id]);
        }

        return route('dashboard.guru.rekap-nilai.index');
    }

    /**
     * URL tujuan notifikasi guru untuk jadwal: mengarah ke halaman mata pelajaran / jadwal mengajar.
     */
    public function guruUrlJadwal(): string
    {
        if (Route::has('dashboard.guru.jadwal-mengajar.index')) {
            return route('dashboard.guru.jadwal-mengajar.index');
        }

        return route('dashboard.guru.index');
    }

    /**
     * Sinkronkan notifikasi dan pengingat dari data nyata di database untuk
     * akun pengguna yang sedang aktif (Guru atau Siswa).
     */
    public function sinkronkanPengingat(User $user): void
    {
        if ($user->role === 'siswa') {
            $this->sinkronkanSiswa($user);
        } elseif ($user->role === 'guru') {
            $this->sinkronkanGuru($user);
        }
    }

    /**
     * Sinkronisasi data notifikasi nyata untuk Siswa: pengumuman, tugas &
     * materi rombel, pengingat deadline, jadwal, dan nilai.
     */
    public function sinkronkanSiswa(User $siswa): void
    {
        // 1. Pengumuman Sekolah
        $pengumuman = Pengumuman::query()->untukSiswa($siswa)->get();
        foreach ($pengumuman as $p) {
            $this->kirimPengumuman($p);
        }

        // Jika siswa belum memiliki kelas, kategori berbasis rombel tidak diproses
        if (blank($siswa->kelas)) {
            return;
        }

        // 2. Tugas Baru (Tugas & Materi)
        $daftarTugas = Tugas::untukRombel($siswa->kelas, $siswa->jurusan)
            ->with(['mataPelajaran', 'guru'])
            ->get();
        foreach ($daftarTugas as $tugas) {
            $this->tugasBaru($tugas);
        }

        $daftarMateri = Materi::untukRombel($siswa->kelas, $siswa->jurusan)
            ->with(['mataPelajaran', 'guru'])
            ->get();
        foreach ($daftarMateri as $materi) {
            $this->materiBaru($materi);
        }

        // 3. Pengingat Deadline
        // Tugas yang memiliki deadline di masa depan dalam 3 hari ke depan (atau hari ini)
        // dan belum dikumpulkan oleh siswa ini
        $tugasMendekat = Tugas::untukRombel($siswa->kelas, $siswa->jurusan)
            ->whereNotNull('deadline')
            ->where('deadline', '>', now())
            ->where('deadline', '<=', now()->addDays(3))
            ->with(['mataPelajaran', 'guru'])
            ->get();
        foreach ($tugasMendekat as $tugas) {
            $this->pengingatDeadlineTugas($tugas);
        }

        // 4. Perubahan Jadwal Pelajaran
        $daftarJadwal = JadwalPelajaran::untukRombel($siswa->kelas, $siswa->jurusan)
            ->with('mataPelajaran')
            ->get();
        foreach ($daftarJadwal as $jadwal) {
            $ref = 'jadwal:'.$jadwal->id.':awal:'.($jadwal->updated_at?->getTimestamp() ?? 0);
            if (! Notification::where('user_id', $siswa->id)->where('reference_key', $ref)->exists()) {
                $deskripsi = ($jadwal->mataPelajaran?->nama ?? 'Mata pelajaran')
                    .' · Kelas '.$jadwal->kelas
                    .($jadwal->jurusan ? ' ('.$jadwal->jurusan.')' : '')
                    .' · '.$jadwal->hari.' '.$jadwal->jamLabel()
                    .($jadwal->ruang ? ' · Ruang '.$jadwal->ruang : '');
                $this->kirim(
                    $siswa,
                    Notification::TIPE_JADWAL,
                    'Perubahan Jadwal Pelajaran',
                    'Jadwal pelajaran: '.$deskripsi,
                    route('dashboard.siswa.jadwal-pelajaran.index'),
                    $ref,
                );
            }
        }

        // 5. Nilai Diperbarui
        $daftarNilai = Nilai::where('user_id', $siswa->id)
            ->with('mataPelajaran')
            ->get();
        foreach ($daftarNilai as $n) {
            $this->nilaiDiperbarui(
                $siswa,
                $n->mata_pelajaran_id,
                (float) $n->nilai,
                (float) ($n->kkm ?? 75.0),
                (string) $n->tahun_ajaran,
                (string) $n->semester
            );
        }
    }

    /**
     * Sinkronisasi data notifikasi nyata untuk Guru.
     * Mencakup 5 kategori:
     * 1. Pengumpulan Tugas Siswa (Siswa mengumpulkan tugas yang diampu guru)
     * 2. Pengingat Input Nilai (Pemberitahuan siswa yang belum memiliki nilai)
     * 3. Perubahan Jadwal Mengajar (Jadwal mengajar guru)
     * 4. Pengumuman Sekolah (Pengumuman terbit untuk Guru)
     * 5. Pengingat Tugas Akademik (Tugas yang diampu mendekati batas tenggat)
     */
    public function sinkronkanGuru(User $guru): void
    {
        // 1. Pengumuman Sekolah
        $pengumuman = Pengumuman::query()->untukGuru()->get();
        foreach ($pengumuman as $p) {
            $this->kirimPengumuman($p);
        }

        $namaMapel = trim((string) $guru->mata_pelajaran);
        $mapelIds = $namaMapel !== '' ? MataPelajaran::where('nama', $namaMapel)->pluck('id') : collect();

        // 2 & 3. Tugas dan Pengumpulan Tugas
        $tugasQuery = Tugas::query()->where(function ($q) use ($guru, $mapelIds) {
            $q->where('guru_id', $guru->id);
            if ($mapelIds->isNotEmpty()) {
                $q->orWhereIn('mata_pelajaran_id', $mapelIds);
            }
        });
        $daftarTugas = $tugasQuery->get();
        $tugasIds = $daftarTugas->pluck('id');

        if ($tugasIds->isNotEmpty()) {
            // Pengumpulan Tugas Siswa
            $submissions = PengumpulanTugas::whereIn('tugas_id', $tugasIds)
                ->with(['tugas.mataPelajaran', 'user'])
                ->get();
            foreach ($submissions as $pengumpulan) {
                $this->pengumpulanTugas($pengumpulan, true);
            }

            // Pengingat Tugas Akademik (mendekati tenggat dalam 3 hari)
            foreach ($daftarTugas as $tugas) {
                if ($tugas->deadline !== null && $tugas->deadline->isFuture() && $tugas->deadline->lte(now()->addDays(3))) {
                    $this->pengingatTugasAkademik($tugas);
                }
            }
        }

        // 4. Perubahan Jadwal Mengajar
        $daftarJadwal = JadwalPelajaran::query()->where('guru_id', $guru->id)
            ->when($mapelIds->isNotEmpty(), fn ($q) => $q->orWhereIn('mata_pelajaran_id', $mapelIds))
            ->with('mataPelajaran')
            ->get();
        foreach ($daftarJadwal as $jadwal) {
            $ref = 'jadwal:'.$jadwal->id.':awal:'.($jadwal->updated_at?->getTimestamp() ?? 0);
            if (! Notification::where('user_id', $guru->id)->where('reference_key', $ref)->exists()) {
                $deskripsi = ($jadwal->mataPelajaran?->nama ?? 'Mata pelajaran')
                    .' · Kelas '.$jadwal->kelas
                    .($jadwal->jurusan ? ' ('.$jadwal->jurusan.')' : '')
                    .' · '.$jadwal->hari.' '.$jadwal->jamLabel()
                    .($jadwal->ruang ? ' · Ruang '.$jadwal->ruang : '');
                $this->kirim(
                    $guru,
                    Notification::TIPE_JADWAL,
                    'Perubahan Jadwal Mengajar',
                    'Jadwal mengajar: '.$deskripsi,
                    $this->guruUrlJadwal(),
                    $ref,
                );
            }
        }

        // 5. Pengingat Input Nilai
        if ($namaMapel !== '' && $mapelIds->isNotEmpty()) {
            $periode = Periode::periodeSekarang();
            $tahunAjaran = $periode['tahun_ajaran'];
            $semester = $periode['semester'];

            $kelasDiampu = JadwalPelajaran::where('guru_id', $guru->id)
                ->orWhereIn('mata_pelajaran_id', $mapelIds)
                ->pluck('kelas')
                ->filter()
                ->unique()
                ->values();

            if ($kelasDiampu->isEmpty()) {
                $kelasDiampu = KelasRombel::pluck('kelas')->filter()->unique()->values();
            }

            if ($kelasDiampu->isNotEmpty()) {
                $siswaList = User::query()
                    ->where('role', 'siswa')
                    ->where('status', User::STATUS_AKTIF)
                    ->whereIn('kelas', $kelasDiampu)
                    ->get();

                $sudahDinilai = Nilai::whereIn('mata_pelajaran_id', $mapelIds)
                    ->where('tahun_ajaran', $tahunAjaran)
                    ->where('semester', $semester)
                    ->pluck('user_id')
                    ->all();

                $belumDinilai = $siswaList->reject(fn (User $s) => in_array($s->id, $sudahDinilai, true));

                if ($belumDinilai->isNotEmpty()) {
                    $daftarKelas = $belumDinilai->pluck('kelas')->unique()->values()->all();
                    $this->pengingatInputNilai(
                        $guru,
                        $belumDinilai->count(),
                        $daftarKelas,
                        $namaMapel,
                        $tahunAjaran,
                        $semester
                    );
                }
            }
        }
    }

    /**
     * Periksa semua pengingat berkala untuk seluruh pengguna aktif.
     * Dipanggil oleh console command atau scheduler cron.
     */
    public function periksaSemuaPengingat(): void
    {
        User::query()
            ->whereIn('role', ['guru', 'siswa'])
            ->where('status', User::STATUS_AKTIF)
            ->each(function (User $user) {
                $this->sinkronkanPengingat($user);
            });
    }

    // Bantuan

    /**
     * @return array<string, mixed>
     */
    protected function baris(
        User $penerima,
        string $tipe,
        string $judul,
        string $pesan,
        ?string $url,
        ?string $referenceKey,
        ?string $ikon,
    ): array {
        return [
            'user_id' => $penerima->id,
            'role' => $penerima->role,
            'type' => $tipe,
            'title' => $judul,
            'message' => $pesan,
            'icon' => $ikon ?? self::IKON[$tipe] ?? 'bell',
            'url' => $url,
            'reference_key' => $referenceKey,
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Sisipkan baris notifikasi, abaikan yang sudah ada supaya pemeriksaan
     * berkala tidak pernah menduplikasi peristiwa yang sama.
     *
     * @param  array<int, array<string, mixed>>  $baris
     */
    protected function sisipkan(array $baris): int
    {
        if ($baris === []) {
            return 0;
        }

        return Notification::query()->insertOrIgnore($baris);
    }

    protected function formatAngka(float $angka): string
    {
        return rtrim(rtrim(number_format($angka, 2, '.', ''), '0'), '.');
    }
}
