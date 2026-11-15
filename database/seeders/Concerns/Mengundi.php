<?php

namespace Database\Seeders\Concerns;

use App\Models\User;
use App\Support\Kelas;
use Illuminate\Support\Collection;

/**
 * Data contoh yang dibuat seeder. Semuanya dibuat deterministik supaya
 * `db:seed` yang dijalankan dua kali menghasilkan isi tabel yang sama persis:
 * tidak ada angka acak bawaan yang membuat hasil seeding tidak bisa dibandingkan.
 *
 * Dipakai JadwalSeeder, TugasSeeder, MateriSeeder, dan PengumpulanTugasSeeder.
 */
trait Mengundi
{
    /** Angka tetap di antara min dan max untuk kunci tertentu. */
    protected function undian(string $kunci, float $min, float $max): float
    {
        $pecahan = hexdec(substr(md5($kunci), 0, 7)) / 0xFFFFFFF;

        return round($min + (($max - $min) * $pecahan), 1);
    }

    /** Bulatan dari undian, dipakai memilih baris dari daftar tetap. */
    protected function undianInt(string $kunci, int $min, int $max): int
    {
        return (int) round($this->undian($kunci, (float) $min, (float) $max));
    }

    /** Mata pelajaran pertama yang terdaftar pada tiap nama, untuk penugasan guru. */
    protected function guruPerMapel(): array
    {
        return User::query()
            ->where('role', 'guru')
            ->whereNotNull('mata_pelajaran')
            ->get(['id', 'mata_pelajaran'])
            ->groupBy(fn (User $guru) => trim((string) $guru->mata_pelajaran))
            ->map(fn (Collection $guru) => $guru->first()->id)
            ->all();
    }

    /**
     * Rombel yang benar-benar dipakai siswa: pasangan kelas + jurusan, satu
     * kali saja, dinormalkan supaya "12 - 1" dan "12-1" tidak jadi dua rombel.
     *
     * @return Collection<int, array{0: string, 1: string}>
     */
    protected function rombel(): Collection
    {
        return User::query()
            ->where('role', 'siswa')
            ->whereNotNull('kelas')
            ->where('kelas', '!=', '')
            ->get(['kelas', 'jurusan'])
            ->map(fn (User $siswa) => [Kelas::normalize((string) $siswa->kelas), trim((string) $siswa->jurusan)])
            ->unique(fn (array $rombel) => $rombel[0].'|'.$rombel[1])
            ->values();
    }
}
