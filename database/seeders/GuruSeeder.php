<?php

namespace Database\Seeders;

/**
 * Akun guru. Login memakai NIP (lihat App\Support\LoginIdentifier).
 *
 * Password tidak ditentukan di sini: sistem membuatkannya unik (berbeda untuk
 * tiap guru) dan hasilnya dicetak oleh seeder di konsol.
 */
class GuruSeeder extends LoginAccountSeeder
{
    protected function role(): string
    {
        return 'guru';
    }

    protected function accounts(): array
    {
        return [
            [
                'name' => 'Budi Santoso, S.Pd.',
                'email' => 'budi.santoso@saskansa.sch.id',
                'nip' => '1001',
                'mata_pelajaran' => 'Matematika',
                'kelas' => null,
                'jurusan' => null,
                'nis' => null,
            ],
            [
                'name' => 'Siti Aminah, S.Pd.',
                'email' => 'siti.aminah@saskansa.sch.id',
                'nip' => '1002',
                'mata_pelajaran' => 'Bahasa Inggris',
                'kelas' => null,
                'jurusan' => null,
                'nis' => null,
            ],
            // Mata pelajaran di Data Mata Pelajaran harus ada gurunya,
            // kalau tidak kolom Guru Pengampu di Laporan Akademik akan
            // kosong padahal mapelnya sedang dinilai.
            [
                'name' => 'Rangga Kusuma, S.Pd.',
                'email' => 'rangga.kusuma@saskansa.sch.id',
                'nip' => '1003',
                'mata_pelajaran' => 'Fisika',
                'kelas' => null,
                'jurusan' => null,
                'nis' => null,
            ],
        ];
    }
}
