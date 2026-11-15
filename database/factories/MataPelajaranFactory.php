<?php

namespace Database\Factories;

use App\Models\MataPelajaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MataPelajaran>
 */
class MataPelajaranFactory extends Factory
{
    protected $model = MataPelajaran::class;

    public function definition(): array
    {
        return [
            'kode' => strtoupper(fake()->bothify('MP-?###')),
            'nama' => fake()->randomElement(self::DAFTAR_NAMA),
        ];
    }

    /**
     * Nama mapel yang dipakai sebagai nilai bawaan factory. Nama tidak
     * harus unik di tabel, jadi tidak perlu generator unique yang bisa
     * habis dipakai setelah banyak test.
     */
    private const DAFTAR_NAMA = [
        'Matematika',
        'Fisika',
        'Kimia',
        'Biologi',
        'Informatika',
        'Bahasa Inggris',
        'Bahasa Indonesia',
        'Pendidikan Pancasila',
        'Pendidikan Agama',
        'Pendidikan Kewarganegaraan',
        'Sejarah',
        'Geografi',
        'Ekonomi',
        'Sosiologi',
        'Antropologi',
        'Seni Budaya',
        'Bahasa Jawa',
        'Olahraga',
        'Prakarya',
        'Bimbingan Konseling',
    ];

    public function nama(string $nama): static
    {
        return $this->state(fn () => ['nama' => $nama]);
    }

    public function kode(?string $kode): static
    {
        return $this->state(fn () => ['kode' => $kode]);
    }
}
