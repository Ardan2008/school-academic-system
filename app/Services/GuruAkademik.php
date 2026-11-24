<?php

namespace App\Services;

use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\User;
use App\Support\Kelas;
use App\Support\StrukturKelas;
use Illuminate\Support\Collection;

/**
 * Cakupan akademik seorang guru: mata pelajaran dan rombel yang boleh
 * dikelolanya.
 *
 * Sumber utama adalah jadwal mengajar, lalu nilai yang pernah diinput. Kalau
 * keduanya belum ada, semua rombel siswa aktif dipakai sebagai cadangan supaya
 * guru baru tetap bisa bekerja sebelum admin mengisi jadwal.
 */
class GuruAkademik
{
    public function __construct(
        private readonly StatistikNilai $statistik,
        private readonly PenugasanMengajarService $penugasan,
    ) {}

    /**
     * @return Collection<int, MataPelajaran>
     */
    public function mapelDiampu(User $guru): Collection
    {
        return $this->penugasan->mapelDiampu($guru);
    }

    public function sudahDiatur(User $guru): bool
    {
        return $this->penugasan->sudahDiatur($guru);
    }

    /**
     * Pasangan mata pelajaran dan rombel yang boleh dikelola guru. Mata
     * pelajaran yang belum punya pasangan eksplisit memakai rombel siswa aktif
     * sebagai cadangan supaya semua mapel yang diampu tetap bisa dikelola.
     *
     * @return Collection<int, array{mata_pelajaran_id: int, kelas: string, jurusan: string}>
     */
    public function pasanganMapelRombel(User $guru): Collection
    {
        $pasangan = $this->pasanganEksplisit($guru);
        $mapelSudahAda = $pasangan->pluck('mata_pelajaran_id')->unique()->all();
        $mapelIds = $this->statistik->mapelIdsDiampu($guru);
        $rombel = $this->rombelSiswaAktif();

        foreach ($mapelIds as $mapelId) {
            if (in_array($mapelId, $mapelSudahAda, true)) {
                continue;
            }

            foreach ($rombel as $item) {
                $pasangan->push([
                    'mata_pelajaran_id' => $mapelId,
                    'kelas' => $item['kelas'],
                    'jurusan' => $item['jurusan'],
                ]);
            }
        }

        return $this->rapikan($pasangan);
    }

    /**
     * Penugasan yang benar-benar tercatat di jadwal atau nilai. Dipakai untuk
     * membedakan guru yang sudah punya jadwal dari guru yang masih memakai
     * cadangan rombel siswa aktif.
     *
     * @return Collection<int, array{mata_pelajaran_id: int, kelas: string, jurusan: string}>
     */
    public function pasanganEksplisit(User $guru): Collection
    {
        $ditugaskan = $this->penugasan->pasangan($guru);

        if ($this->penugasan->sudahDiatur($guru)) {
            return $this->rapikan($ditugaskan);
        }

        $mapelIds = $this->statistik->mapelIdsDiampu($guru);

        if ($mapelIds === []) {
            return collect();
        }

        $pasangan = collect();

        $tambah = function (int $mapelId, ?string $kelas, ?string $jurusan) use ($pasangan): void {
            $kelas = Kelas::normalize((string) $kelas);

            if ($kelas === '') {
                return;
            }

            $pasangan->push([
                'mata_pelajaran_id' => $mapelId,
                'kelas' => $kelas,
                'jurusan' => trim((string) $jurusan),
            ]);
        };

        JadwalPelajaran::query()
            ->where('guru_id', $guru->id)
            ->whereIn('mata_pelajaran_id', $mapelIds)
            ->get(['mata_pelajaran_id', 'kelas', 'jurusan'])
            ->each(fn (JadwalPelajaran $jadwal) => $tambah(
                (int) $jadwal->mata_pelajaran_id,
                $jadwal->kelas,
                $jadwal->jurusan
            ));

        Nilai::query()
            ->whereIn('mata_pelajaran_id', $mapelIds)
            ->select('mata_pelajaran_id', 'kelas', 'jurusan')
            ->distinct()
            ->get()
            ->each(fn ($nilai) => $tambah(
                (int) $nilai->mata_pelajaran_id,
                $nilai->kelas,
                $nilai->jurusan
            ));

        return $this->rapikan($pasangan);
    }

    /**
     * @return Collection<int, array{kelas: string, jurusan: string}>
     */
    public function kelasDiampu(User $guru): Collection
    {
        return $this->pasanganMapelRombel($guru)
            ->map(fn (array $item) => [
                'kelas' => $item['kelas'],
                'jurusan' => $item['jurusan'],
            ])
            ->unique(fn (array $item) => $item['jurusan'].'|'.$item['kelas'])
            ->sort(fn (array $a, array $b) => strcasecmp($a['jurusan'], $b['jurusan'])
                ?: StrukturKelas::bandingkanKelas($a['kelas'], $b['kelas']))
            ->values();
    }

    /**
     * @return Collection<int, array{mata_pelajaran_id: int, kelas: string, jurusan: string}>
     */
    public function pasanganUntukMapel(User $guru, int $mapelId): Collection
    {
        return $this->pasanganMapelRombel($guru)
            ->where('mata_pelajaran_id', $mapelId)
            ->values();
    }

    public function bolehMengajar(User $guru, int $mapelId, string $kelas, ?string $jurusan = null): bool
    {
        return $this->cocokPenugasan($this->pasanganMapelRombel($guru), $mapelId, $kelas, $jurusan);
    }

    public function bolehMengajarEksplisit(User $guru, int $mapelId, string $kelas, ?string $jurusan = null): bool
    {
        return $this->cocokPenugasan($this->pasanganEksplisit($guru), $mapelId, $kelas, $jurusan);
    }

    public function cocokPasangan(Collection $pasangan, int $mapelId, string $kelas, ?string $jurusan = null): bool
    {
        return $this->cocokPenugasan($pasangan, $mapelId, $kelas, $jurusan);
    }

    /**
     * @param  Collection<int, array{mata_pelajaran_id: int, kelas: string, jurusan: string}>  $pasangan
     */
    protected function cocokPenugasan(Collection $pasangan, int $mapelId, string $kelas, ?string $jurusan = null): bool
    {
        $kelas = Kelas::normalize($kelas);
        $jurusan = trim((string) $jurusan);

        return $pasangan->contains(
            fn (array $item) => $item['mata_pelajaran_id'] === $mapelId
                && $item['kelas'] === $kelas
                && ($item['jurusan'] === '' || $item['jurusan'] === $jurusan)
        );
    }

    /**
     * @return Collection<int, array{kelas: string, jurusan: string}>
     */
    protected function rombelSiswaAktif(): Collection
    {
        return User::query()
            ->where('role', 'siswa')
            ->where('status', User::STATUS_AKTIF)
            ->whereNotNull('kelas')
            ->where('kelas', '!=', '')
            ->get(['kelas', 'jurusan'])
            ->map(fn (User $siswa) => [
                'kelas' => Kelas::normalize((string) $siswa->kelas),
                'jurusan' => trim((string) $siswa->jurusan),
            ])
            ->unique(fn (array $item) => $item['jurusan'].'|'.$item['kelas'])
            ->values();
    }

    /**
     * @param  Collection<int, array{mata_pelajaran_id: int, kelas: string, jurusan: string}>  $pasangan
     * @return Collection<int, array{mata_pelajaran_id: int, kelas: string, jurusan: string}>
     */
    protected function rapikan(Collection $pasangan): Collection
    {
        return $pasangan
            ->unique(fn (array $item) => $item['mata_pelajaran_id'].'|'.$item['jurusan'].'|'.$item['kelas'])
            ->sort(fn (array $a, array $b) => strcasecmp($a['jurusan'], $b['jurusan'])
                ?: StrukturKelas::bandingkanKelas($a['kelas'], $b['kelas'])
                ?: $a['mata_pelajaran_id'] <=> $b['mata_pelajaran_id'])
            ->values();
    }

    /**
     * @param  Collection<int, array{kelas: string, jurusan: string}>  $kelasList
     */
    public function cocokRombel(User $siswa, Collection $kelasList): bool
    {
        $kelas = Kelas::normalize((string) $siswa->kelas);
        $jurusan = trim((string) $siswa->jurusan);

        return $kelasList->contains(
            fn (array $item) => $item['kelas'] === $kelas
                && ($item['jurusan'] === '' || $item['jurusan'] === $jurusan)
        );
    }
}
