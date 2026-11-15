<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Menyiapkan master data (mata pelajaran) dan akun login untuk Admin,
     * Guru, dan Siswa. Setiap akun memakai identifier sesuai form login
     * (Admin = email, Guru = NIP, Siswa = NIS) dengan nilai tetap supaya
     * kredensialnya bisa langsung diketik di halaman login.
     */
    public function run(): void
    {
        $this->call([
            MataPelajaranSeeder::class,
            AdminSeeder::class,
            GuruSeeder::class,
            SiswaSeeder::class,
            // Setelah siswa, supaya kelas-kelas yang dipakai siswa ikut
            // terdaftar di Data Kelas.
            KelasSeeder::class,
            // Setelah kelas, supaya jurusan yang dipakai siswa maupun kelas
            // ikut terdaftar di Data Jurusan.
            JurusanSeeder::class,
            // Paling akhir: nilai butuh mata pelajaran, siswa, dan kelas yang
            // sudah ada di atas. Tanpa baris nilai, Laporan Akademik admin
            // hanya bisa menampilkan keterangan bahwa belum ada nilai.
            NilaiSeeder::class,
            // Data kegiatan siswa. Semuanya membaca rombel yang dibuat di atas,
            // jadi harus berjalan setelah SiswaSeeder dan MataPelajaranSeeder.
            JadwalSeeder::class,
            TugasSeeder::class,
            MateriSeeder::class,
            PengumumanSeeder::class,
            // Pengumpulan tugas harus setelah tugas dan siswa.
            PengumpulanTugasSeeder::class,
        ]);

        $this->command?->newLine();
        $this->command?->info('Halaman login: '.route('login', absolute: false));
        $this->command?->line(
            'Guru & siswa diarahkan membuat password baru pada kunjungan pertama.'
        );
    }
}
