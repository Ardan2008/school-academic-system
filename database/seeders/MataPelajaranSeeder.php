<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MataPelajaranSeeder extends Seeder
{
    /**
     * @var array<int, array{kode: string, nama: string}>
     */
    protected const MATA_PELAJARAN = [
        ['kode' => 'MTK', 'nama' => 'Matematika'],
        ['kode' => 'FKS', 'nama' => 'Fisika'],
        ['kode' => 'BING', 'nama' => 'Bahasa Inggris'],
    ];

    public function run(): void
    {
        foreach (self::MATA_PELAJARAN as $row) {
            DB::table('mata_pelajaran')->updateOrInsert(
                ['kode' => $row['kode']],
                ['nama' => $row['nama'], 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }
}
