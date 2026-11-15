<?php

namespace Database\Seeders;

use App\Models\MataPelajaran;
use App\Models\Materi;
use Database\Seeders\Concerns\Mengundi;
use Illuminate\Database\Seeder;

/**
 * Materi ajar tiap rombel untuk halaman Tugas & Materi siswa.
 *
 * Materinya dibaca apa adanya dari tabel `materi`, dan tanggalnya dihitung
 * mundur dari sekarang supaya daftarnya selalu terurut sebagai bahan yang
 * baru-baru ini dibagikan.
 *
 * Harus dijalankan setelah SiswaSeeder dan MataPelajaranSeeder.
 */
class MateriSeeder extends Seeder
{
    use Mengundi;

    /** Tanggal rilis relatif dari sekarang, negatif berarti sudah lama. */
    protected const TANGGAL_HARI = [-7, -4, -1];

    protected const JUDUL = [
        'Materi {mapel} Minggu Ini',
        'Rangkuman {mapel}',
        'Pendamping {mapel}',
    ];

    public function run(): void
    {
        $mataPelajaran = MataPelajaran::query()->orderBy('nama')->get();
        $rombel = $this->rombel();

        if ($mataPelajaran->isEmpty() || $rombel->isEmpty()) {
            $this->command?->warn('Materi dilewati: mata pelajaran atau siswa belum ada.');

            return;
        }

        $guruPerMapel = $this->guruPerMapel();
        $total = 0;

        foreach ($rombel as [$kelas, $jurusan]) {
            foreach ($mataPelajaran as $mapel) {
                foreach (self::TANGGAL_HARI as $index => $hari) {
                    $judul = strtr(self::JUDUL[$index], ['{mapel}' => $mapel->nama]);

                    Materi::updateOrCreate(
                        [
                            'kelas' => $kelas,
                            'jurusan' => $jurusan === '' ? null : $jurusan,
                            'mata_pelajaran_id' => $mapel->id,
                            'judul' => $judul,
                        ],
                        [
                            'guru_id' => $guruPerMapel[$mapel->nama] ?? null,
                            'deskripsi' => 'Rincian materi '.$mapel->nama.' beserta latihan mandiri yang bisa dikerjakan di rumah.',
                            'tanggal' => now()->addDays($hari)->startOfDay(),
                        ]
                    );

                    $total++;
                }
            }
        }

        $this->command?->newLine();
        $this->command?->info('Materi: '.$total.' baris untuk '.$rombel->count().' rombel.');
    }
}
