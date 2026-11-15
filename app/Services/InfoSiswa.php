<?php

namespace App\Services;

use App\Models\JadwalPelajaran;
use App\Models\Materi;
use App\Models\PengumpulanTugas;
use App\Models\Tugas;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Info kegiatan siswa: jadwal, tugas, materi, dan aktivitas pengumpulan.
 *
 * Semua diambil dari rombel siswa (kelas + jurusan) sehingga siswa hanya
 * melihat kegiatan kelasnya sendiri. Siswa tanpa data rombel menerima koleksi
 * kosong, bukan contoh palsu.
 *
 * Catatan: daftar notifikasi siswa TIDAK diambil dari sini — satu-satunya
 * sumbernya adalah tabel notifications (lihat NotifikasiAkademikService),
 * yang juga dipakai lonceng header dan endpoint notifikasi.
 */
class InfoSiswa
{
    /** Jadwal hari ini untuk kelas siswa; koleksi kosong di luar hari sekolah. */
    public function jadwalHariIni(User $siswa): Collection
    {
        $hari = JadwalPelajaran::hariIni();

        if ($hari === null) {
            return collect();
        }

        return $this->jadwalQuery($siswa)
            ->where('hari', $hari)
            ->orderBy('jam_mulai')
            ->get();
    }

    /**
     * Jadwal sepekan dikelompokkan per hari, hanya hari yang terisi.
     *
     * Bila $hari diisi dengan nama hari yang sah, hanya hari itu yang
     * dikembalikan. Hari di luar daftar diabaikan supaya filter tidak bisa
     * dikira-kirakan dari sisi klien.
     */
    public function jadwalMingguan(User $siswa, ?string $hari = null): Collection
    {
        $query = $this->jadwalQuery($siswa)->orderBy('jam_mulai');

        if ($hari !== null && in_array($hari, JadwalPelajaran::HARI, true)) {
            $query->where('hari', $hari);
        }

        $jadwal = $query->get()->groupBy('hari');

        return collect(JadwalPelajaran::HARI)
            ->mapWithKeys(fn (string $namaHari): array => [$namaHari => $jadwal->get($namaHari, collect())])
            ->filter(fn (Collection $items): bool => $items->isNotEmpty());
    }

    /** Tugas rombel siswa, terdekat tenggat lebih dulu, sudah membawa status pengumpulan. */
    public function tugas(User $siswa): Collection
    {
        return Tugas::query()
            ->untukRombel($siswa->kelas, $siswa->jurusan)
            ->with([
                'mataPelajaran',
                'guru',
                'pengumpulan' => fn ($query) => $query->where('user_id', $siswa->id),
            ])
            ->orderByRaw('deadline IS NULL')
            ->orderBy('deadline')
            ->get();
    }

    public function tugasBelumDikumpulkan(User $siswa): Collection
    {
        return $this->tugas($siswa)
            ->filter(fn (Tugas $tugas): bool => $tugas->pengumpulan->isEmpty())
            ->values();
    }

    /**
     * Aktivitas siswa yang sudah tercatat: pengumpulan tugas, terbaru lebih
     * dulu. Dipakai tabel "Aktivitas Terbaru" di Dashboard Siswa, jadi yang
     * tampil adalah perbuatan siswa sendiri, bukan jadwal yang belum tentu
     * berlangsung.
     *
     * @return Collection<int, PengumpulanTugas>
     */
    public function aktivitasTerbaru(User $siswa, int $limit = 10): Collection
    {
        return $this->pengumpulan($siswa, $limit);
    }

    /**
     * Seluruh pengumpulan tugas milik siswa, terbaru lebih dulu. Batas $limit
     * null berarti tanpa batas dan dipakai halaman Riwayat Aktivitas.
     *
     * @return Collection<int, PengumpulanTugas>
     */
    public function pengumpulan(User $siswa, ?int $limit = null): Collection
    {
        $query = PengumpulanTugas::query()
            ->where('user_id', $siswa->id)
            ->with(['tugas.mataPelajaran', 'tugas.guru'])
            ->orderByDesc('dikumpulkan_at')
            ->orderByDesc('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function materi(User $siswa): Collection
    {
        return Materi::query()
            ->untukRombel($siswa->kelas, $siswa->jurusan)
            ->with(['mataPelajaran', 'guru'])
            ->orderByDesc('tanggal')
            ->get();
    }

    /** Status pengerjaan tugas untuk satu siswa: selesai, terlambat, atau menunggu. */
    public function statusTugas(Tugas $tugas): array
    {
        $pengumpulan = $tugas->pengumpulan->first();

        if ($pengumpulan !== null) {
            return ['kode' => 'selesai', 'label' => 'Dikumpulkan', 'class' => 'bg-emerald-50 text-emerald-600'];
        }

        if ($tugas->sudahLewatDeadline()) {
            return ['kode' => 'terlambat', 'label' => 'Terlambat', 'class' => 'bg-rose-50 text-rose-600'];
        }

        return ['kode' => 'menunggu', 'label' => 'Menunggu', 'class' => 'bg-amber-50 text-amber-600'];
    }

    protected function jadwalQuery(User $siswa)
    {
        return JadwalPelajaran::query()
            ->untukRombel($siswa->kelas, $siswa->jurusan)
            ->with(['mataPelajaran', 'guru']);
    }
}
