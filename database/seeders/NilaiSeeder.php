<?php

namespace Database\Seeders;

use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\User;
use App\Support\Kelas;
use App\Support\Periode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Nilai contoh untuk Laporan Akademik Admin.
 *
 * Tabel nilai dibaca apa adanya, jadi tanpa baris di sini seluruh angka pada
 * laporan ikut kosong. Seeder ini harus dijalankan setelah SiswaSeeder,
 * MataPelajaranSeeder, dan KelasSeeder.
 *
 * Nilai dibuat deterministik (dari md5 NIS, kelas, mapel, dan periode) supaya
 * `db:seed` berulang menghasilkan laporan yang sama; kemampuan siswa konsisten
 * antarperiode; dan kelas dicatat sesuai masa siswa menempatinya, bukan kelas
 * saat ini.
 *
 * Seeder ini menulis ulang baris nilai milik siswa yang di-seed, jadi jalankan
 * di database contoh sebelum ada nilai sungguhan.
 */
class NilaiSeeder extends Seeder
{
    /**
     * Berapa banyak periode yang diisi, 1 berarti hanya periode berjalan.
     * Periodenya dihitung mundur dari periode berjalan, jadi seeder ini tidak
     * pernah menulis nilai ke periode karangan.
     */
    protected const JUMLAH_PERIODE = 3;

    /**
     * Titik pusat sebaran nilai dan KKM per mata pelajaran.
     *
     * KKM dipisah dari rata-rata karena KKM dihitung guru per kelas per mapel,
     * sedangkan angka di sini hanya titik pusat sebaran. KKM sengaja lebih
     * rendah dari rata-rata supaya laporan tidak terlihat serba tuntas, karena
     * laporan yang selalu 100% tidak pernah dipakai untuk mengambil keputusan.
     *
     * @var array<string, array{rata: float, kkm: float}>
     */
    protected const MATA_PELAJARAN = [
        'Matematika' => ['rata' => 80.0, 'kkm' => 75.0],
        'Fisika' => ['rata' => 76.0, 'kkm' => 70.0],
        'Bahasa Inggris' => ['rata' => 78.0, 'kkm' => 72.0],
    ];

    /** Titik pusat sebaran dan KKM cadangan, dipakai kalau ada mapel baru. */
    protected const RATA_DEFAULT = 75.0;

    protected const KKM_DEFAULT = 70.0;

    protected const TINGKAT_TERENDAH = 10;

    /** Kenaikan rata-rata per semester; periode lama lebih rendah. */
    protected const KENAIKAN_PER_SEMESTER = 2.5;

    /** Batas nilai rapor, supaya sebaran tidak keluar dari 0-100. */
    protected const NILAI_MINIMUM = 40.0;

    protected const NILAI_MAKSIMUM = 99.0;

    public function run(): void
    {
        $mataPelajaran = MataPelajaran::query()->orderBy('nama')->get();
        $siswa = $this->siswaTerdaftar();

        if ($mataPelajaran->isEmpty() || $siswa->isEmpty()) {
            $this->command?->warn('Nilai dilewati: mata pelajaran atau siswa belum ada.');

            return;
        }

        $total = 0;
        $barisBaru = 0;
        $barisDiperbarui = 0;
        $perKelas = [];

        foreach ($this->periode() as $mundur => [$tahunAjaran, $semester]) {
            foreach ($siswa as $akun) {
                $kelas = $this->kelasPadaMasa($akun, $mundur);

                if ($kelas === null) {
                    continue;
                }

                foreach ($mataPelajaran as $mapel) {
                    $nilai = Nilai::updateOrCreate(
                        [
                            'user_id' => $akun->id,
                            'mata_pelajaran_id' => $mapel->id,
                            'tahun_ajaran' => $tahunAjaran,
                            'semester' => $semester,
                        ],
                        [
                            'kelas' => $kelas,
                            'jurusan' => trim((string) $akun->jurusan),
                            'nilai' => $this->nilai($akun, $kelas, $mapel, $mundur),
                            'kkm' => self::MATA_PELAJARAN[$mapel->nama]['kkm'] ?? self::KKM_DEFAULT,
                        ]
                    );

                    if ($nilai->wasRecentlyCreated) {
                        $barisBaru++;
                    } else {
                        $barisDiperbarui++;
                    }

                    $total++;
                }

                $perKelas[$akun->jurusan.'|'.$kelas] = true;
            }
        }

        $this->command?->newLine();
        $this->command?->info('Nilai Laporan Akademik: '.$total.' baris untuk '.count($perKelas).' kelas.');
        $this->command?->line(
            '  Ditambah '.$barisBaru.' baris, diperbarui '.$barisDiperbarui.' baris.'
        );

        $this->command?->table(['Periode', 'Jumlah Nilai'], $this->rekapPeriode());
    }

    /**
     * Siswa yang nilainya bisa dicatat: sudah punya kelas dan jurusan, karena
     * keduanya disimpan ulang di baris nilai.
     *
     * @return Collection<int, User>
     */
    protected function siswaTerdaftar(): Collection
    {
        return User::query()
            ->where('role', 'siswa')
            ->whereNotNull('kelas')
            ->where('kelas', '!=', '')
            ->whereNotNull('jurusan')
            ->where('jurusan', '!=', '')
            ->orderBy('id')
            ->get();
    }

    /**
     * Periode yang diisi, dihitung mundur dari periode berjalan. Kunci array
     * adalah jarak dalam semester dari sekarang, dipakai sebagai besaran
     * penurunan nilai pada periode lama.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function periode(): array
    {
        $berjalan = Periode::urutan(
            Periode::tahunAjaranSekarang(),
            Periode::semesterSekarang()
        );

        $hasil = [];

        for ($mundur = 0; $mundur < self::JUMLAH_PERIODE; $mundur++) {
            $urutan = $berjalan - $mundur;
            $tahun = intdiv($urutan, 2);

            $hasil[$mundur] = [
                $tahun.'/'.($tahun + 1),
                $urutan % 2 === 0 ? Periode::SEMESTER_GANJIL : Periode::SEMESTER_GENAP,
            ];
        }

        return $hasil;
    }

    /**
     * Kelas yang ditempati siswa pada periode tersebut, atau null kalau memang
     * belum ada nilainya.
     *
     * Dua aturan di sini yang tidak boleh ditebak:
     *
     * - Setiap semester kelas turun satu tingkat, karena siswa naik kelas dan
     *   nilai dicatat pada kelas yang sedang ditempati. Kelas "12-1" satu
     *   semester lalu berarti "11-1".
     * - Sekolah ini mulai dari kelas 10, jadi kelas yang akan turun ke bawah
     *   10 tidak pernah ada dan nilainya pun tidak dicatat. Itu sebabnya siswa
     *   kelas 10 tidak punya nilai pada periode yang lebih lama.
     */
    protected function kelasPadaMasa(User $siswa, int $mundur): ?string
    {
        if ($mundur === 0) {
            // Siswa yang sudah lulus tidak lagi mengikuti penilaian pada
            // periode berjalan. Nilainya yang terakhir tetap ada di periodenya
            // sendiri, jadi laporan tahun lalu tidak ikut hilang.
            if ($siswa->status === User::STATUS_LULUS) {
                return null;
            }

            return Kelas::normalize((string) $siswa->kelas);
        }

        $tingkat = (int) Kelas::tingkat($siswa->kelas);

        if ($tingkat === 0) {
            return null;
        }

        $tujuan = $tingkat - $mundur;

        if ($tujuan < self::TINGKAT_TERENDAH) {
            return null;
        }

        // Nomor rombel ikut turun supaya "12-2" tidak menjadi kelas "10" dan
        // tercampur dengan rombel yang lain.
        $rombel = Kelas::rombel($siswa->kelas);

        return $rombel === null ? (string) $tujuan : $tujuan.'-'.$rombel;
    }

    /**
     * Nilai satu siswa untuk satu mata pelajaran pada satu periode.
     *
     * Nilainya dirakit dari empat bagian supaya pola di laporan punya sebab:
     * titik pusat mata pelajaran, kemampuan siswa, corak kelas, dan kenaikan
     * dari periode ke periode. Sebagian kecilnya diambil dari undian per mapel
     * dan per periode supaya rapor tidak terlihat seperti hasil kali.
     */
    protected function nilai(User $siswa, string $kelas, MataPelajaran $mapel, int $mundur): float
    {
        $nilai = $this->rataMataPelajaran($mapel)
            + $this->kemampuanSiswa($siswa)
            + $this->corakKelas($siswa, $kelas)
            - (self::KENAIKAN_PER_SEMESTER * $mundur)
            + $this->undian($siswa->nis.'|'.$mapel->kode.'|'.$mundur, -3.5, 3.5);

        return max(self::NILAI_MINIMUM, min(self::NILAI_MAKSIMUM, round($nilai, 1)));
    }

    /** Kemampuan siswa tetap di semua periode, kunci diturunkan dari NIS. */
    protected function kemampuanSiswa(User $siswa): float
    {
        return $this->undian('kemampuan|'.$siswa->nis, -9.0, 9.0);
    }

    /** Corak kelas ikut dihitung supaya rekap antarkelas tidak seragam. */
    protected function corakKelas(User $siswa, string $kelas): float
    {
        return $this->undian('kelas|'.$siswa->jurusan.'|'.$kelas, -6.0, 6.0);
    }

    protected function rataMataPelajaran(MataPelajaran $mapel): float
    {
        return self::MATA_PELAJARAN[$mapel->nama]['rata'] ?? self::RATA_DEFAULT;
    }

    /** Angka tetap di antara min dan max; md5 dipakai agar tidak bergantung urutan pemanggilan. */
    protected function undian(string $kunci, float $min, float $max): float
    {
        // Tujuh digit heksadesimal cukup: nilainya masih muat di integer dan
        // fraksinya membagi habis tanpa sisa pembulatan.
        $pecahan = hexdec(substr(md5($kunci), 0, 7)) / 0xFFFFFFF;

        return round($min + (($max - $min) * $pecahan), 1);
    }

    /**
     * Jumlah nilai per periode untuk dicetak di konsol, jadi hasil seeding bisa
     * diperiksa tanpa membuka halaman.
     *
     * @return array<int, array{0: string, 1: int}>
     */
    protected function rekapPeriode(): array
    {
        $baris = [];

        foreach ($this->periode() as [$tahunAjaran, $semester]) {
            $baris[] = [
                Periode::label($tahunAjaran, $semester),
                Nilai::query()->untukPeriode($tahunAjaran, $semester)->count(),
            ];
        }

        return $baris;
    }
}
