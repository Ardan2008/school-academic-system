<?php

namespace App\Services;

use App\Models\MataPelajaran;
use App\Models\PenugasanMengajar;
use App\Models\User;
use App\Support\Kelas;
use Illuminate\Support\Collection;

class PenugasanMengajarService
{
    public function sudahDiatur(User $guru): bool
    {
        return (bool) ($guru->penugasan_diatur ?? false);
    }

    public function guruDiaturIds(): Collection
    {
        return User::query()
            ->where('role', 'guru')
            ->where('penugasan_diatur', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
    }

    public function guruBelumDiaturIds(): Collection
    {
        return User::query()
            ->where('role', 'guru')
            ->where('penugasan_diatur', false)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
    }

    public function mapelTersedia(User $guru): Collection
    {
        $nama = collect(explode(',', (string) $guru->mata_pelajaran))
            ->map(fn (string $item) => trim($item))
            ->filter(fn (string $item) => $item !== '')
            ->unique(fn (string $item) => mb_strtolower($item))
            ->values();

        if ($nama->isEmpty()) {
            return collect();
        }

        return MataPelajaran::query()
            ->orderBy('nama')
            ->get()
            ->filter(fn (MataPelajaran $mapel) => $nama->contains(
                fn (string $item) => strcasecmp($item, trim((string) $mapel->nama)) === 0
            ))
            ->values();
    }

    public function mapelDiampu(User $guru): Collection
    {
        if ($this->sudahDiatur($guru)) {
            $ids = PenugasanMengajar::query()
                ->where('guru_id', $guru->id)
                ->pluck('mata_pelajaran_id')
                ->all();

            return MataPelajaran::query()
                ->whereIn('id', $ids)
                ->orderBy('nama')
                ->get();
        }

        return $this->mapelTersedia($guru);
    }

    public function pasangan(User $guru): Collection
    {
        if (! $this->sudahDiatur($guru)) {
            return collect();
        }

        return PenugasanMengajar::query()
            ->join('kelas', 'kelas.id', '=', 'penugasan_mengajar.kelas_id')
            ->where('penugasan_mengajar.guru_id', $guru->id)
            ->orderBy('kelas.jurusan')
            ->orderBy('kelas.kelas')
            ->get([
                'penugasan_mengajar.mata_pelajaran_id',
                'kelas.kelas',
                'kelas.jurusan',
            ])
            ->map(fn (PenugasanMengajar $penugasan) => [
                'mata_pelajaran_id' => (int) $penugasan->mata_pelajaran_id,
                'kelas' => Kelas::normalize((string) $penugasan->kelas),
                'jurusan' => trim((string) $penugasan->jurusan),
            ])
            ->values();
    }

    /**
     * Pasangan guru dan mata pelajaran yang benar-benar ditugaskan ke satu
     * rombel. Dipakai supaya data yang tampil di siswa mengikuti penugasan.
     *
     * @return Collection<int, array{guru_id: int, mata_pelajaran_id: int}>
     */
    public function pasanganUntukRombel(string $kelas, ?string $jurusan = null): Collection
    {
        $kelas = Kelas::normalize($kelas);
        $jurusan = trim((string) $jurusan);

        if ($kelas === '') {
            return collect();
        }

        return PenugasanMengajar::query()
            ->join('users', 'users.id', '=', 'penugasan_mengajar.guru_id')
            ->join('kelas', 'kelas.id', '=', 'penugasan_mengajar.kelas_id')
            ->where('users.penugasan_diatur', true)
            ->where('kelas.kelas', $kelas)
            ->when($jurusan !== '', fn ($query) => $query->where(function ($sub) use ($jurusan) {
                $sub->whereNull('kelas.jurusan')
                    ->orWhere('kelas.jurusan', '')
                    ->orWhere('kelas.jurusan', $jurusan);
            }))
            ->get([
                'penugasan_mengajar.guru_id',
                'penugasan_mengajar.mata_pelajaran_id',
            ])
            ->map(fn (PenugasanMengajar $item) => [
                'guru_id' => (int) $item->guru_id,
                'mata_pelajaran_id' => (int) $item->mata_pelajaran_id,
            ])
            ->unique(fn (array $item) => $item['guru_id'].'|'.$item['mata_pelajaran_id'])
            ->values();
    }

    public function ringkasan(User $guru): Collection
    {
        if (! $this->sudahDiatur($guru)) {
            return collect();
        }

        return PenugasanMengajar::query()
            ->join('mata_pelajaran', 'mata_pelajaran.id', '=', 'penugasan_mengajar.mata_pelajaran_id')
            ->where('penugasan_mengajar.guru_id', $guru->id)
            ->orderBy('mata_pelajaran.nama')
            ->get([
                'penugasan_mengajar.mata_pelajaran_id',
                'penugasan_mengajar.kelas_id',
                'mata_pelajaran.nama',
                'mata_pelajaran.kode',
            ])
            ->groupBy('mata_pelajaran_id')
            ->map(fn (Collection $items, int $mapelId) => [
                'mata_pelajaran_id' => $mapelId,
                'nama' => (string) $items->first()->nama,
                'kode' => $items->first()->kode,
                'kelas_ids' => $items->pluck('kelas_id')->map(fn ($id) => (int) $id)->values()->all(),
            ])
            ->values();
    }

    public function detailKelas(User $guru): Collection
    {
        if (! $this->sudahDiatur($guru)) {
            return collect();
        }

        return PenugasanMengajar::query()
            ->join('mata_pelajaran', 'mata_pelajaran.id', '=', 'penugasan_mengajar.mata_pelajaran_id')
            ->join('kelas', 'kelas.id', '=', 'penugasan_mengajar.kelas_id')
            ->where('penugasan_mengajar.guru_id', $guru->id)
            ->orderBy('mata_pelajaran.nama')
            ->orderBy('kelas.jurusan')
            ->orderBy('kelas.kelas')
            ->get([
                'penugasan_mengajar.mata_pelajaran_id',
                'penugasan_mengajar.kelas_id',
                'mata_pelajaran.nama as mapel_nama',
                'mata_pelajaran.kode as mapel_kode',
                'kelas.kelas',
                'kelas.jurusan',
            ])
            ->groupBy('mata_pelajaran_id')
            ->map(function (Collection $items, int $mapelId): array {
                $mapel = $items->first();

                return [
                    'mata_pelajaran_id' => $mapelId,
                    'nama' => (string) $mapel->mapel_nama,
                    'kode' => $mapel->mapel_kode,
                    'kelas' => $items
                        ->map(fn ($item): array => [
                            'id' => (int) $item->kelas_id,
                            'nama' => Kelas::normalize((string) $item->kelas),
                            'jurusan' => trim((string) $item->jurusan),
                        ])
                        ->unique(fn (array $item): int => $item['id'])
                        ->values()
                        ->all(),
                ];
            })
            ->values();
    }
}
