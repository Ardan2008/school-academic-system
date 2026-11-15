<?php

namespace Database\Seeders;

use App\Models\MataPelajaran;
use App\Models\Tugas;
use Database\Seeders\Concerns\Mengundi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Tugas tiap rombel untuk halaman Tugas & Materi siswa.
 *
 * Halaman itu membaca tabel `tugas` apa adanya, jadi tanpa baris di situ siswa
 * melihat "belum ada tugas". Tugas dibuat per rombel per mata pelajaran dengan
 * tenggat dihitung dari sekarang: ada yang masih mendekat, ada yang lewat
 * supaya kolom status "Terlambat" ikut teruji dengan data sungguhan.
 *
 * Harus dijalankan setelah SiswaSeeder dan MataPelajaranSeeder.
 */
class TugasSeeder extends Seeder
{
    use Mengundi;

    /** Tenggat relatif dari sekarang, negatif berarti sudah lewat. */
    protected const TENGGAT_HARI = [6, 2, -2];

    protected const JUDUL = [
        'Latihan Soal {mapel}',
        'Pendalaman Materi {mapel}',
        'Evaluasi {mapel}',
    ];

    public function run(): void
    {
        $mataPelajaran = MataPelajaran::query()->orderBy('nama')->get();
        $rombel = $this->rombel();

        if ($mataPelajaran->isEmpty() || $rombel->isEmpty()) {
            $this->command?->warn('Tugas dilewati: mata pelajaran atau siswa belum ada.');

            return;
        }

        $guruPerMapel = $this->guruPerMapel();
        $total = 0;

        foreach ($rombel as [$kelas, $jurusan]) {
            foreach ($mataPelajaran as $mapelKe => $mapel) {
                foreach (self::TENGGAT_HARI as $index => $hari) {
                    $judul = strtr(self::JUDUL[$index], ['{mapel}' => $mapel->nama]);
                    $kode = $kelas.'|'.$jurusan.'|'.$mapel->kode.'|'.$index;

                    Tugas::updateOrCreate(
                        [
                            'kelas' => $kelas,
                            'jurusan' => $jurusan === '' ? null : $jurusan,
                            'mata_pelajaran_id' => $mapel->id,
                            'judul' => $judul,
                        ],
                        [
                            'guru_id' => $guruPerMapel[$mapel->nama] ?? null,
                            'deskripsi' => $this->deskripsi($mapel->nama, $mapelKe),
                            'deadline' => $this->hari($index)->setTime(23, 59),
                        ]
                    );

                    $total++;
                }
            }
        }

        $this->command?->newLine();
        $this->command?->info('Tugas: '.$total.' baris untuk '.$rombel->count().' rombel.');
    }

    protected function deskripsi(string $mapel, int $mapelKe): string
    {
        return 'Kerjakan soal '.$mapel.' sesuai materi yang sudah dipelajari minggu ini.'
            .($mapelKe % 2 === 0 ? ' Kerjakan rapi dan tandai langkah pengerjaan.' : ' Jawab dengan kalimat lengkap.');
    }

    protected function hari(int $mundur): Carbon
    {
        return now()->addDays($mundur)->startOfDay();
    }
}
