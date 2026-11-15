<?php

namespace Database\Seeders;

/**
 * Akun admin sekolah. Login memakai email (lihat App\Support\LoginIdentifier).
 */
class AdminSeeder extends LoginAccountSeeder
{
    protected function role(): string
    {
        return 'admin';
    }

    protected function accounts(): array
    {
        return [
            [
                'name' => 'Administrator Sekolah',
                'email' => 'admin@saskansa.sch.id',
                'password' => 'Admin123',
                'kelas' => null,
                'jurusan' => null,
                'nip' => null,
                'nis' => null,
                'mata_pelajaran' => null,
            ],
        ];
    }
}
