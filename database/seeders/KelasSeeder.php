<?php

namespace Database\Seeders;

use App\Models\KelasRombel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Master Data Kelas.
 *
 * Daftar kelas dibuat dari kelas yang benar-benar dipakai siswa, supaya Data
 * Kelas tidak terlihat kosong padahal sudah ada siswanya. Rombel yang sengaja
 * belum ada siswanya juga ikut dibuat sebagai contoh, supaya terlihat bahwa
 * kelas boleh didaftarkan lebih dulu.
 */
class KelasSeeder extends Seeder
{
    /**
     * Kelas yang belum ada siswanya. Ada dua jenis: kelas angkatan sebelumnya
     * yang masih dirujuk nilai lama, dan kelas yang disiapkan sebelum ada
     * murid. Keduanya menjaga Data Kelas tetap sinkron dengan Laporan Akademik.
     */
    protected const KOSONG = [
        ['jurusan' => 'Rekayasa Perangkat Lunak', 'kelas' => '11-2'],
        ['jurusan' => 'Rekayasa Perangkat Lunak', 'kelas' => '10-2'],
        ['jurusan' => 'Rekayasa Perangkat Lunak', 'kelas' => '12-3'],
        ['jurusan' => 'Teknik Komputer Jaringan', 'kelas' => '12-2'],
    ];

    public function run(): void
    {
        $wali = $this->waliKelas();

        foreach ($this->dariSiswa() as $baris) {
            $kelas = KelasRombel::firstOrCreate(
                ['jurusan' => $baris['jurusan'], 'kelas' => $baris['kelas']],
                ['wali_kelas_id' => $wali[$baris['jurusan']] ?? null]
            );

            // Wali kelas diisi lagi kalau kelas ini sebelumnya dibuat tanpa wali.
            if ($kelas->wali_kelas_id === null && isset($wali[$baris['jurusan']])) {
                $kelas->forceFill(['wali_kelas_id' => $wali[$baris['jurusan']]])->save();
            }
        }

        foreach (self::KOSONG as $baris) {
            KelasRombel::firstOrCreate($baris);
        }
    }

    /**
     * Kelas yang dipakai siswa, dikelompokkan per jurusan supaya tidak ada
     * query per akun.
     *
     * @return Collection<int, array{jurusan: string, kelas: string}>
     */
    protected function dariSiswa(): Collection
    {
        return User::query()
            ->where('role', 'siswa')
            ->whereNotNull('jurusan')
            ->whereNotNull('kelas')
            ->where('kelas', '!=', '')
            ->distinct()
            ->orderBy('jurusan')
            ->orderBy('kelas')
            ->get(['jurusan', 'kelas'])
            ->map(fn (User $siswa) => [
                'jurusan' => trim((string) $siswa->jurusan),
                'kelas' => trim((string) $siswa->kelas),
            ])
            ->filter(fn (array $baris) => $baris['jurusan'] !== '' && $baris['kelas'] !== '')
            ->unique(fn (array $baris) => $baris['jurusan'].'|'.$baris['kelas'])
            ->values();
    }

    /**
     * Satu guru jadi wali kelas untuk satu jurusan, diambil berurutan supaya
     * hasilnya sama tiap kali seeding dijalankan.
     *
     * @return array<string, int>
     */
    protected function waliKelas(): array
    {
        $guru = User::query()
            ->where('role', 'guru')
            ->orderBy('id')
            ->pluck('id');

        $jurusan = $this->dariSiswa()->pluck('jurusan')->unique()->sort()->values();

        $wali = [];

        foreach ($jurusan as $i => $namaJurusan) {
            if ($guru->isEmpty()) {
                break;
            }

            $wali[$namaJurusan] = $guru[$i % $guru->count()];
        }

        return $wali;
    }
}
