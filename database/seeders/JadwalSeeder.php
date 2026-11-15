<?php

namespace Database\Seeders;

use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use Database\Seeders\Concerns\Mengundi;
use Illuminate\Database\Seeder;

/**
 * Jadwal pelajaran mingguan tiap rombel.
 *
 * Halaman jadwal dibaca apa adanya dari tabel `jadwal`, jadi tanpa baris di
 * tabel itu siswa hanya melihat keterangan "jadwal belum tersedia". Seeder ini
 * mengisi jadwal dari rombel, mata pelajaran, dan guru yang sudah dibuat
 * seeder sebelumnya, jadi harus dijalankan setelah SiswaSeeder dan
 * MataPelajaranSeeder.
 *
 * Jadwalnya dibuat tetap: penempatan mapel bergeser menurut kunci kelas dan
 * jurusan, jadi `db:seed` dua kali menghasilkan jadwal yang sama dan tidak ada
 * dua hari yang berurutan kebetulan identik.
 */
class JadwalSeeder extends Seeder
{
    use Mengundi;

    /** Jam pelajaran sekolah, buka, istirahat sebentar, lalu tutup. */
    protected const JAM = [
        ['07:00:00', '08:30:00'],
        ['08:30:00', '10:00:00'],
        ['10:15:00', '11:45:00'],
    ];

    /** Hari sekolah. Sabtu kosong supaya jadwal tidak terlihat penuh setiap hari. */
    protected const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

    public function run(): void
    {
        $mataPelajaran = MataPelajaran::query()->orderBy('nama')->get();
        $rombel = $this->rombel();

        if ($mataPelajaran->isEmpty() || $rombel->isEmpty()) {
            $this->command?->warn('Jadwal dilewati: mata pelajaran atau siswa belum ada.');

            return;
        }

        $guruPerMapel = $this->guruPerMapel();
        $total = 0;
        $baru = 0;
        $diperbarui = 0;

        foreach ($rombel as [$kelas, $jurusan]) {
            // Penempatan awal supaya urutan mapel tiap rombel tidak sama.
            $dasar = $this->undianInt('jadwal|'.$jurusan.'|'.$kelas, 0, max(0, $mataPelajaran->count() - 1));

            foreach (self::HARI as $hariKe => $hari) {
                foreach (self::JAM as $jamKe => [$mulai, $selesai]) {
                    $index = ($dasar + ($hariKe * count(self::JAM)) + $jamKe) % $mataPelajaran->count();
                    $mapel = $mataPelajaran[$index];

                    $jadwal = JadwalPelajaran::updateOrCreate(
                        [
                            'kelas' => $kelas,
                            'jurusan' => $jurusan === '' ? null : $jurusan,
                            'hari' => $hari,
                            'jam_mulai' => $mulai,
                        ],
                        [
                            'jam_selesai' => $selesai,
                            'mata_pelajaran_id' => $mapel->id,
                            'guru_id' => $guruPerMapel[$mapel->nama] ?? null,
                            'ruang' => 'R.'.$this->ruang($kelas, $jamKe),
                        ]
                    );

                    if ($jadwal->wasRecentlyCreated) {
                        $baru++;
                    } else {
                        $diperbarui++;
                    }

                    $total++;
                }
            }
        }

        $this->command?->newLine();
        $this->command?->info('Jadwal pelajaran: '.$total.' baris untuk '.$rombel->count().' rombel.');
        $this->command?->line('  Ditambah '.$baru.' baris, diperbarui '.$diperbarui.' baris.');
    }

    /** Ruang mengikuti tingkat dan slot jam, jadi tiap kelas punya ruang tetap. */
    protected function ruang(string $kelas, int $jamKe): string
    {
        $tingkat = (int) preg_replace('/\D.*/', '', $kelas);

        return sprintf('%03d', ($tingkat > 0 ? $tingkat : 10) * 10 + $jamKe + 1);
    }
}
