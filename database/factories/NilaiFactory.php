<?php

namespace Database\Factories;

use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\User;
use App\Support\Periode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Nilai>
 */
class NilaiFactory extends Factory
{
    protected $model = Nilai::class;

    /**
     * Nilai selalu butuh siswa dan mata pelajaran yang benar-benar ada.
     * Keduanya dibuat lewat closure supaya lazy: kalau pemanggil memakai
     * state siswa() atau mataPelajaran(), closure ini tidak pernah jalan
     * dan tidak ada record sia-sia yang tercipta.
     */
    public function definition(): array
    {
        $periode = Periode::periodeSekarang();

        return [
            'user_id' => fn () => User::factory()->siswa()->create()->id,
            'mata_pelajaran_id' => fn () => MataPelajaran::factory()->create()->id,
            // user_id dipecah lebih dulu, jadi kelas/jurusan bisa ikut siswa.
            'kelas' => fn (array $atribut) => $this->kelasSiswa($atribut),
            'jurusan' => fn (array $atribut) => $this->jurusanSiswa($atribut),
            'tahun_ajaran' => $periode['tahun_ajaran'],
            'semester' => $periode['semester'],
            'nilai' => fake()->randomFloat(2, 60, 95),
            'kkm' => 75,
        ];
    }

    /**
     * @param  array<string, mixed>  $atribut
     */
    private function kelasSiswa(array $atribut): string
    {
        return $this->siswaDari($atribut)?->kelas ?? '';
    }

    /**
     * @param  array<string, mixed>  $atribut
     */
    private function jurusanSiswa(array $atribut): ?string
    {
        return $this->siswaDari($atribut)?->jurusan;
    }

    /**
     * @param  array<string, mixed>  $atribut
     */
    private function siswaDari(array $atribut): ?User
    {
        $id = $atribut['user_id'] ?? null;

        return is_numeric($id) ? User::find($id) : null;
    }

    public function siswa(User $siswa): static
    {
        return $this->state(fn () => [
            'user_id' => $siswa->id,
            'kelas' => $siswa->kelas,
            'jurusan' => $siswa->jurusan,
        ]);
    }

    public function mataPelajaran(MataPelajaran $mataPelajaran): static
    {
        return $this->state(fn () => ['mata_pelajaran_id' => $mataPelajaran->id]);
    }

    public function kelas(string $kelas, ?string $jurusan = null): static
    {
        return $this->state(fn () => [
            'kelas' => $kelas,
            'jurusan' => $jurusan,
        ]);
    }

    public function periode(string $tahunAjaran, string $semester): static
    {
        return $this->state(fn () => [
            'tahun_ajaran' => $tahunAjaran,
            'semester' => $semester,
        ]);
    }

    public function nilai(float|int|string $nilai): static
    {
        return $this->state(fn () => ['nilai' => $nilai]);
    }

    public function kkm(float|int|string $kkm): static
    {
        return $this->state(fn () => ['kkm' => $kkm]);
    }
}
