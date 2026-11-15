<?php

namespace Database\Seeders;

use App\Models\User;

/**
 * Akun siswa. Login memakai NIS dan password dibuat unik oleh sistem
 * (dicetak di konsol). Empat digit pertama NIS adalah tahun masuk, jadi
 * "2025..." berarti siswa yang masuk pada tahun ajaran 2025/2026.
 *
 * Format kelas: "12" kalau satu tingkat hanya punya satu rombel, atau "12-1"
 * kalau dibagi beberapa rombel; jurusan disimpan terpisah di kolom `jurusan`.
 * Siswa sengaja tersebar di beberapa rombel per tingkat agar Laporan Akademik
 * punya perbandingan kelas yang nyata, bukan satu titik.
 */
class SiswaSeeder extends LoginAccountSeeder
{
    protected const RPL = 'Rekayasa Perangkat Lunak';

    protected const TKJ = 'Teknik Komputer Jaringan';

    protected function role(): string
    {
        return 'siswa';
    }

    protected function accounts(): array
    {
        return [
            // Rekayasa Perangkat Lunak
            // Kelas 12: angkatan yang sudah tiga tahun sekolah dan nilainya
            // dipakai sebagai pembanding perkembangan antar periode.
            [
                'name' => 'Ahmad Fauzi',
                'email' => 'ahmad.fauzi@saskansa.sch.id',
                'nis' => '2025001001',
                'kelas' => '12-1',
                'jurusan' => self::RPL,
                'nip' => null,
                'mata_pelajaran' => null,
            ],
            [
                'name' => 'Citra Ayu',
                'email' => 'citra.ayu@saskansa.sch.id',
                'nis' => '2025001002',
                'kelas' => '12-1',
                'jurusan' => self::RPL,
                'nip' => null,
                'mata_pelajaran' => null,
            ],
            [
                'name' => 'Reza Maulana',
                'email' => 'reza.maulana@saskansa.sch.id',
                'nis' => '2024001005',
                'kelas' => '12-1',
                'jurusan' => self::RPL,
                'nip' => null,
                'mata_pelajaran' => null,
            ],
            [
                'name' => 'Nurul Hidayah',
                'email' => 'nurul.hidayah@saskansa.sch.id',
                'nis' => '2024001006',
                'kelas' => '12-1',
                'jurusan' => self::RPL,
                'nip' => null,
                'mata_pelajaran' => null,
            ],
            [
                'name' => 'Nabila Putri',
                'email' => 'nabila.putri@saskansa.sch.id',
                'nis' => '2024001004',
                'kelas' => '12-2',
                'jurusan' => self::RPL,
                'nip' => null,
                'mata_pelajaran' => null,
                // Contoh siswa yang sudah lulus: akunnya otomatis tidak aktif
                'status' => User::STATUS_LULUS,
            ],
            [
                'name' => 'Rina Oktaviani',
                'email' => 'rina.oktaviani@saskansa.sch.id',
                'nis' => '2025001004',
                'kelas' => '11-1',
                'jurusan' => self::RPL,
                'nip' => null,
                'mata_pelajaran' => null,
            ],
            [
                'name' => 'Yoga Pratama',
                'email' => 'yoga.pratama@saskansa.sch.id',
                'nis' => '2025001005',
                'kelas' => '11-1',
                'jurusan' => self::RPL,
                'nip' => null,
                'mata_pelajaran' => null,
            ],
            [
                'name' => 'Bagas Nugroho',
                'email' => 'bagas.nugroho@saskansa.sch.id',
                'nis' => '2025001006',
                'kelas' => '11-1',
                'jurusan' => self::RPL,
                'nip' => null,
                'mata_pelajaran' => null,
            ],
            [
                'name' => 'Dinda Larasati',
                'email' => 'dinda.larasati@saskansa.sch.id',
                'nis' => '2026001001',
                'kelas' => '10-1',
                'jurusan' => self::RPL,
                'nip' => null,
                'mata_pelajaran' => null,
            ],

            // Teknik Komputer Jaringan
            [
                'name' => 'Fajar Nugroho',
                'email' => 'fajar.nugroho@saskansa.sch.id',
                'nis' => '2024002001',
                'kelas' => '12-1',
                'jurusan' => self::TKJ,
                'nip' => null,
                'mata_pelajaran' => null,
            ],
            [
                'name' => 'Rizky Ramadhan',
                'email' => 'rizky.ramadhan@saskansa.sch.id',
                'nis' => '2024002002',
                'kelas' => '12-1',
                'jurusan' => self::TKJ,
                'nip' => null,
                'mata_pelajaran' => null,
            ],
            [
                'name' => 'Sinta Dewi',
                'email' => 'sinta.dewi@saskansa.sch.id',
                'nis' => '2025002001',
                'kelas' => '11-1',
                'jurusan' => self::TKJ,
                'nip' => null,
                'mata_pelajaran' => null,
            ],
            [
                'name' => 'Andi Saputra',
                'email' => 'andi.saputra@saskansa.sch.id',
                'nis' => '2025002002',
                'kelas' => '11-1',
                'jurusan' => self::TKJ,
                'nip' => null,
                'mata_pelajaran' => null,
            ],
            [
                'name' => 'Dimas Prasetyo',
                'email' => 'dimas.prasetyo@saskansa.sch.id',
                'nis' => '2025001003',
                'kelas' => '10-1',
                'jurusan' => self::TKJ,
                'nip' => null,
                'mata_pelajaran' => null,
            ],
            [
                'name' => 'Mega Lestari',
                'email' => 'mega.lestari@saskansa.sch.id',
                'nis' => '2026002002',
                'kelas' => '10-1',
                'jurusan' => self::TKJ,
                'nip' => null,
                'mata_pelajaran' => null,
            ],
            [
                'name' => 'Bayu Setiawan',
                'email' => 'bayu.setiawan@saskansa.sch.id',
                'nis' => '2026002003',
                'kelas' => '10-1',
                'jurusan' => self::TKJ,
                'nip' => null,
                'mata_pelajaran' => null,
            ],
        ];
    }
}
