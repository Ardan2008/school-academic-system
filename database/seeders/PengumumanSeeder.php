<?php

namespace Database\Seeders;

use App\Models\Pengumuman;
use App\Support\Kelas;
use Illuminate\Database\Seeder;

/**
 * Pengumuman untuk notifikasi siswa.
 *
 * Kolom kelas dan jurusan kosong berarti pengumuman berlaku untuk semua siswa.
 * Notifikasi Dashboard Siswa membaca tabel ini apa adanya, jadi tanpa barisnya
 * siswa melihat "belum ada notifikasi".
 */
class PengumumanSeeder extends Seeder
{
    /**
     * @var array<int, array{judul: string, isi: string, kelas: string|null, jurusan: string|null, hari: int}>
     */
    protected const PENGUMUMAN = [
        [
            'judul' => 'Jadwal Ujian Tengah Semester',
            'isi' => 'Ujian tengah semester berlangsung minggu ke tiga. Pastikan siswa membawa kartu ujian dan alat tulis sendiri.',
            'kelas' => null,
            'jurusan' => null,
            'hari' => -1,
        ],
        [
            'judul' => 'Pengumpulan Tugas Akhir Rekayasa Perangkat Lunak',
            'isi' => 'Batas akhir pengumpulan tugas akhir kelas 12 RPL diperpanjang. Kumpulkan lewat wali kelas masing-masing.',
            'kelas' => null,
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'hari' => -3,
        ],
        [
            'judul' => 'Praktikum Jaringan Komputer',
            'isi' => 'Siswa Teknik Komputer Jaringan wajib membawa buku catatan praktikum untuk sesi laboratorium pekan ini.',
            'kelas' => null,
            'jurusan' => 'Teknik Komputer Jaringan',
            'hari' => -5,
        ],
        [
            'judul' => 'Kebersihan Kelas 10-1',
            'isi' => 'Kelas 10-1 ditunjuk sebagai piket kebersihan terbaik minggu lalu. Terima kasih atas kedisiplinannya.',
            'kelas' => '10-1',
            'jurusan' => null,
            'hari' => -7,
        ],
    ];

    public function run(): void
    {
        $total = 0;
        $sekutu = 0;

        foreach (self::PENGUMUMAN as $baris) {
            $kelas = $baris['kelas'] !== null ? Kelas::normalize($baris['kelas']) : null;

            $pengumuman = Pengumuman::updateOrCreate(
                [
                    'judul' => $baris['judul'],
                    'kelas' => $kelas,
                    'jurusan' => $baris['jurusan'],
                ],
                [
                    'isi' => $baris['isi'],
                    'publish_at' => now()->addDays($baris['hari'])->startOfDay(),
                ]
            );

            if ($pengumuman->wasRecentlyCreated) {
                $total++;
            } else {
                $sekutu++;
            }
        }

        $this->command?->newLine();
        $this->command?->info('Pengumuman: '.count(self::PENGUMUMAN).' baris ('.$total.' baru, '.$sekutu.' diperbarui).');
    }
}
