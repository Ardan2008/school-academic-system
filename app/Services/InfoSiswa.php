<?php

namespace App\Services;

use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\Materi;
use App\Models\PengumpulanTugas;
use App\Models\Tugas;
use App\Models\User;
use App\Support\Periode;
use Illuminate\Database\Eloquent\Builder;
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
    public function __construct(private readonly PenugasanMengajarService $penugasan) {}

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
        $query = Tugas::query()
            ->untukRombel($siswa->kelas, $siswa->jurusan)
            ->with([
                'mataPelajaran',
                'guru',
                'pengumpulan' => fn ($query) => $query->where('user_id', $siswa->id),
            ]);

        $this->batasiPenugasan($query, $siswa);

        return $query
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

    public function mataPelajaranIds(User $siswa): Collection
    {
        $ids = $this->pasanganPenugasan($siswa)
            ->pluck('mata_pelajaran_id')
            ->unique()
            ->values();

        $belumDiatur = $this->penugasan->guruBelumDiaturIds();

        if ($belumDiatur->isNotEmpty()) {
            $legacyIds = JadwalPelajaran::query()
                ->untukRombel($siswa->kelas, $siswa->jurusan)
                ->where(function ($query) use ($belumDiatur): void {
                    $query->whereIn('guru_id', $belumDiatur->all())
                        ->orWhereNull('guru_id');
                })
                ->pluck('mata_pelajaran_id')
                ->filter()
                ->unique()
                ->values();

            $ids = $ids->merge($legacyIds);

            if ($legacyIds->isEmpty()) {
                $ids = $ids->merge(MataPelajaran::query()->pluck('id'));
            }
        }

        if ($ids->isEmpty() && $this->penugasan->guruDiaturIds()->isEmpty()) {
            return MataPelajaran::query()->pluck('id');
        }

        return $ids->unique()->values();
    }

    public function materi(User $siswa): Collection
    {
        $query = Materi::query()
            ->untukRombel($siswa->kelas, $siswa->jurusan)
            ->whereIn('mata_pelajaran_id', $this->mataPelajaranIds($siswa))
            ->with(['mataPelajaran', 'guru']);

        $this->batasiPenugasan($query, $siswa);

        return $query->orderByDesc('tanggal')->get();
    }

    /** Status pengerjaan tugas untuk satu siswa: selesai, terlambat, atau menunggu. */
    public function statusTugas(Tugas $tugas): array
    {
        $pengumpulan = $tugas->pengumpulan->first();

        if ($pengumpulan !== null) {
            return [
                'kode' => 'dikumpulkan',
                'label' => $pengumpulan->status === PengumpulanTugas::STATUS_DINILAI ? 'Dinilai' : 'Dikumpulkan',
                'class' => 'bg-emerald-50 text-emerald-600',
            ];
        }

        if ($tugas->sudahLewatDeadline()) {
            return ['kode' => 'terlambat', 'label' => 'Terlambat', 'class' => 'bg-rose-50 text-rose-600'];
        }

        return ['kode' => 'menunggu', 'label' => 'Menunggu', 'class' => 'bg-amber-50 text-amber-600'];
    }

    /**
     * @return Collection<int, array{guru_id: int, mata_pelajaran_id: int}>
     */
    protected function pasanganPenugasan(User $siswa): Collection
    {
        return $this->penugasan->pasanganUntukRombel((string) $siswa->kelas, $siswa->jurusan);
    }

    /**
     * @param  Builder<Tugas|Materi>  $query
     */
    protected function batasiPenugasan(Builder $query, User $siswa): void
    {
        $diatur = $this->penugasan->guruDiaturIds();

        if ($diatur->isEmpty()) {
            return;
        }

        $pasangan = $this->pasanganPenugasan($siswa);
        $belumDiatur = $this->penugasan->guruBelumDiaturIds();

        $query->where(function (Builder $sub) use ($pasangan, $belumDiatur) {
            $sub->whereNull('guru_id');

            if ($belumDiatur->isNotEmpty()) {
                $sub->orWhere(fn (Builder $q) => $q->whereIn('guru_id', $belumDiatur->all()));
            }

            foreach ($pasangan as $item) {
                $sub->orWhere(fn (Builder $q) => $q
                    ->where('guru_id', $item['guru_id'])
                    ->where('mata_pelajaran_id', $item['mata_pelajaran_id']));
            }
        });
    }

    protected function jadwalQuery(User $siswa)
    {
        $periode = Periode::periodeSekarang();

        $query = JadwalPelajaran::query()
            ->untukRombel($siswa->kelas, $siswa->jurusan)
            ->where('tahun_ajaran', $periode['tahun_ajaran'])
            ->where('semester', $periode['semester'])
            ->with(['mataPelajaran', 'guru', 'sesi']);

        $this->batasiPenugasan($query, $siswa);

        return $query;
    }
}
