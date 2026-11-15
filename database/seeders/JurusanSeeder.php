<?php

namespace Database\Seeders;

use App\Models\Jurusan;
use App\Models\KelasRombel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Master Data Jurusan.
 *
 * Jurusan yang benar-benar dipakai (oleh siswa atau kelas) ikut didaftarkan,
 * supaya Data Jurusan tidak terlihat kosong padahal sudah dipakai. Selain itu
 * ditambahkan beberapa jurusan contoh yang belum dipakai, sebagai contoh bahwa
 * jurusan boleh didaftarkan lebih dulu sebelum ada siswanya.
 */
class JurusanSeeder extends Seeder
{
    /**
     * Jurusan contoh yang belum tentu dipakai siswa, ditambahkan supaya
     * daftarnya terasa seperti sekolah kejuruan yang menyiapkan paket keahlian.
     */
    protected const CONTOH = [
        'Rekayasa Perangkat Lunak',
        'Teknik Komputer Jaringan',
        'Multimedia',
        'Animasi',
        'Desain Komunikasi Visual',
    ];

    public function run(): void
    {
        foreach (self::CONTOH as $nama) {
            Jurusan::firstOrCreate(['nama' => $nama]);
        }

        foreach ($this->dariPemakaian() as $nama) {
            Jurusan::firstOrCreate(['nama' => $nama]);
        }
    }

    /**
     * Nama jurusan yang sedang dipakai siswa atau kelas, diurutkan supaya
     * hasil seedingnya sama setiap kali dijalankan.
     *
     * @return Collection<int, string>
     */
    protected function dariPemakaian(): Collection
    {
        $siswa = User::query()
            ->where('role', 'siswa')
            ->whereNotNull('jurusan')
            ->where('jurusan', '!=', '')
            ->pluck('jurusan')
            ->map(fn (string $nama) => trim($nama));

        $kelas = KelasRombel::query()
            ->whereNotNull('jurusan')
            ->where('jurusan', '!=', '')
            ->pluck('jurusan')
            ->map(fn (string $nama) => trim($nama));

        return $siswa
            ->concat($kelas)
            ->filter(fn (string $nama) => $nama !== '')
            ->unique()
            ->sort()
            ->values();
    }
}
