<?php

namespace Tests\Unit;

use App\Support\StrukturKelas;
use PHPUnit\Framework\TestCase;

class StrukturKelasTest extends TestCase
{
    /**
     * @param  array<int, array{jurusan: string, kelas: string}>  $rows
     */
    protected function struktur(array $rows): array
    {
        return StrukturKelas::dariSiswa($rows);
    }

    /**
     * @param  array<int, array{jurusan: string, kelas: string}>  $rows
     * @return array<int, array{nilai: string, jumlah: int, berrombel: bool, rombel: array<int, array{nilai: string, jumlah: int, match: string}>}>
     */
    protected function tingkat(string $jurusan, array $rows): array
    {
        foreach ($this->struktur($rows) as $item) {
            if ($item['nama'] === $jurusan) {
                return $item['tingkat'];
            }
        }

        $this->fail("Jurusan {$jurusan} tidak ada di struktur.");
    }

    protected function tingkatSatu(array $tingkat, string $nilai): array
    {
        foreach ($tingkat as $item) {
            if ($item['nilai'] === $nilai) {
                return $item;
            }
        }

        $this->fail("Tingkat {$nilai} tidak ada.");
    }

    public function test_tingkat_dengan_lebih_dari_satu_kelas_menampilkan_nomor_rombel(): void
    {
        $rows = [
            ['jurusan' => 'RPL', 'kelas' => '10-1'],
            ['jurusan' => 'RPL', 'kelas' => '10-1'],
            ['jurusan' => 'RPL', 'kelas' => '10-2'],
            ['jurusan' => 'RPL', 'kelas' => '12-1'],
            ['jurusan' => 'RPL', 'kelas' => '12-2'],
            ['jurusan' => 'RPL', 'kelas' => '12-3'],
        ];

        $tingkat = $this->tingkat('RPL', $rows);

        $sepuluh = $this->tingkatSatu($tingkat, '10');
        $this->assertTrue($sepuluh['berrombel']);
        $this->assertSame(['10-1', '10-2'], array_column($sepuluh['rombel'], 'nilai'));
        $this->assertSame([2, 1], array_column($sepuluh['rombel'], 'jumlah'));

        // Rombel disaring lewat kelas, bukan lewat tingkat.
        $this->assertSame(
            [StrukturKelas::MATCH_KELAS, StrukturKelas::MATCH_KELAS],
            array_column($sepuluh['rombel'], 'match')
        );

        $duabelas = $this->tingkatSatu($tingkat, '12');
        $this->assertTrue($duabelas['berrombel']);
        $this->assertSame(['12-1', '12-2', '12-3'], array_column($duabelas['rombel'], 'nilai'));
    }

    public function test_tingkat_dengan_satu_kelas_hanya_menampilkan_nomor_tingkat(): void
    {
        $rows = [
            ['jurusan' => 'RPL', 'kelas' => '11'],
            ['jurusan' => 'RPL', 'kelas' => '11'],
        ];

        $tingkat = $this->tingkatSatu($this->tingkat('RPL', $rows), '11');

        $this->assertFalse($tingkat['berrombel']);
        $this->assertSame([[
            'nilai' => '11',
            'jumlah' => 2,
            'match' => StrukturKelas::MATCH_TINGKAT,
            'terdaftar' => false,
        ]], $tingkat['rombel']);
    }

    public function test_kelas_tunggal_yang_tersimpan_sebagai_rombel_tetap_tampil_sebagai_tingkat(): void
    {
        // Kelas 11-1 adalah satu-satunya kelas di tingkat 11, jadi yang perlu
        // ditampilkan admin cukup "11". Saringannya tetap dipasang pada
        // tingkat supaya siswa di 11-1 ikut terambil.
        $rows = [
            ['jurusan' => 'RPL', 'kelas' => '11-1'],
            ['jurusan' => 'RPL', 'kelas' => '11-1'],
        ];

        $tingkat = $this->tingkatSatu($this->tingkat('RPL', $rows), '11');

        $this->assertFalse($tingkat['berrombel']);
        $this->assertSame('11', $tingkat['rombel'][0]['nilai']);
        $this->assertSame(StrukturKelas::MATCH_TINGKAT, $tingkat['rombel'][0]['match']);
        $this->assertSame(2, $tingkat['rombel'][0]['jumlah']);
    }

    public function test_kelas_kosong_ditampilkan_sebagai_nol_siswa(): void
    {
        $rows = [
            ['jurusan' => 'TKJ', 'kelas' => '12-1'],
        ];

        $sepuluh = $this->tingkatSatu($this->tingkat('TKJ', $rows), '10');

        $this->assertSame(0, $sepuluh['jumlah']);
        $this->assertFalse($sepuluh['berrombel']);
        $this->assertSame(['10'], array_column($sepuluh['rombel'], 'nilai'));
    }

    public function test_tingkat_10_sampai_12_selalu_ada_di_setiap_jurusan(): void
    {
        $rows = [
            ['jurusan' => 'TKJ', 'kelas' => '12-1'],
            ['jurusan' => 'RPL', 'kelas' => '10-1'],
        ];

        foreach (['TKJ', 'RPL'] as $jurusan) {
            $this->assertSame(
                ['12', '11', '10'],
                array_column($this->tingkat($jurusan, $rows), 'nilai'),
                "Tingkat {$jurusan} harus lengkap dan urut dari yang tertinggi."
            );
        }
    }

    public function test_tingkat_lain_yang_muncul_di_data_ikut_ditampilkan(): void
    {
        $rows = [
            ['jurusan' => 'RPL', 'kelas' => '13-1'],
            ['jurusan' => 'RPL', 'kelas' => '12-1'],
        ];

        $this->assertSame(
            ['13', '12', '11', '10'],
            array_column($this->tingkat('RPL', $rows), 'nilai')
        );
    }

    public function test_rombel_diurutkan_sebagai_angka(): void
    {
        $rows = [
            ['jurusan' => 'RPL', 'kelas' => '12-10'],
            ['jurusan' => 'RPL', 'kelas' => '12-2'],
            ['jurusan' => 'RPL', 'kelas' => '12-1'],
        ];

        $tingkat = $this->tingkatSatu($this->tingkat('RPL', $rows), '12');

        // Diurutkan sebagai teks, 12-10 akan muncul sebelum 12-2.
        $this->assertSame(['12-1', '12-2', '12-10'], array_column($tingkat['rombel'], 'nilai'));
    }

    public function test_jurusan_diurutkan_dan_jumlah_siswanya_dihitung(): void
    {
        $rows = [
            ['jurusan' => 'Teknik Komputer Jaringan', 'kelas' => '12-1'],
            ['jurusan' => 'Rekayasa Perangkat Lunak', 'kelas' => '10-1'],
            ['jurusan' => 'Rekayasa Perangkat Lunak', 'kelas' => '10-2'],
            ['jurusan' => 'Multimedia', 'kelas' => '11-1'],
        ];

        $struktur = $this->struktur($rows);

        $this->assertSame(
            ['Multimedia', 'Rekayasa Perangkat Lunak', 'Teknik Komputer Jaringan'],
            array_column($struktur, 'nama')
        );
        $this->assertSame([1, 2, 1], array_column($struktur, 'jumlah'));
    }

    public function test_siswa_tanpa_jurusan_dilewati(): void
    {
        $struktur = $this->struktur([
            ['jurusan' => null, 'kelas' => '12-1'],
            ['jurusan' => '   ', 'kelas' => '12-1'],
            ['jurusan' => 'RPL', 'kelas' => '12-1'],
        ]);

        $this->assertSame(['RPL'], array_column($struktur, 'nama'));
    }

    public function test_siswa_dengan_kelas_tidak_dikenal_tetap_terhitung_di_jurusannya(): void
    {
        $struktur = $this->struktur([
            ['jurusan' => 'RPL', 'kelas' => 'XII'],
            ['jurusan' => 'RPL', 'kelas' => '12-1'],
        ]);

        $this->assertSame(2, $struktur[0]['jumlah']);
        $this->assertSame(1, $this->tingkatSatu($struktur[0]['tingkat'], '12')['jumlah']);
    }

    public function test_kelas_yang_belum_terpakai_saja_tidak_membuat_tingkat_kosong(): void
    {
        $struktur = $this->struktur([
            ['jurusan' => 'RPL', 'kelas' => 'XII'],
        ]);

        $this->assertSame(1, $struktur[0]['jumlah']);
        $this->assertSame(['12', '11', '10'], array_column($struktur[0]['tingkat'], 'nilai'));
        $this->assertSame([0, 0, 0], array_column($struktur[0]['tingkat'], 'jumlah'));
    }

    public function test_kelas_dinormalisasi_sebelum_dihitung(): void
    {
        // "12 - 1" dan "12-1" adalah kelas yang sama, jadi tidak boleh
        // terhitung sebagai dua rombel berbeda.
        $struktur = $this->struktur([
            ['jurusan' => 'RPL', 'kelas' => ' 12 - 1 '],
            ['jurusan' => 'RPL', 'kelas' => '12-1'],
            ['jurusan' => 'RPL', 'kelas' => '12-2'],
        ]);

        $rombel = $this->tingkatSatu($struktur[0]['tingkat'], '12')['rombel'];

        $this->assertSame(['12-1', '12-2'], array_column($rombel, 'nilai'));
        $this->assertSame([2, 1], array_column($rombel, 'jumlah'));
    }

    public function test_data_kosong_menghasilkan_struktur_kosong(): void
    {
        $this->assertSame([], StrukturKelas::dariSiswa([]));
    }

    public function test_kelas_terdaftar_muncul_lalu_belum_ada_siswanya(): void
    {
        $struktur = StrukturKelas::dariSiswa(
            [],
            [
                ['jurusan' => 'Multimedia', 'kelas' => '11-1'],
                ['jurusan' => 'Multimedia', 'kelas' => '11-2'],
            ]
        );

        $this->assertCount(1, $struktur);
        $this->assertSame('Multimedia', $struktur[0]['nama']);
        $this->assertSame(0, $struktur[0]['jumlah']);

        $tingkat = $this->tingkatSatu($struktur[0]['tingkat'], '11');

        // Dua kelas terdaftar, jadi tingkat ini ber-rombel walau belum diisi.
        $this->assertTrue($tingkat['berrombel']);
        $this->assertSame(0, $tingkat['jumlah']);
        $this->assertSame(['11-1', '11-2'], array_column($tingkat['rombel'], 'nilai'));
        $this->assertSame([0, 0], array_column($tingkat['rombel'], 'jumlah'));
        $this->assertSame([true, true], array_column($tingkat['rombel'], 'terdaftar'));
    }

    public function test_kelas_terdaftar_dan_kelas_dari_siswa_digabung(): void
    {
        $struktur = StrukturKelas::dariSiswa(
            [
                ['jurusan' => 'RPL', 'kelas' => '12-1'],
                ['jurusan' => 'RPL', 'kelas' => '12-1'],
                ['jurusan' => 'RPL', 'kelas' => '12-3'],
            ],
            [
                ['jurusan' => 'RPL', 'kelas' => '12-1'],
                ['jurusan' => 'RPL', 'kelas' => '12-2'],
            ]
        );

        $tingkat = $this->tingkatSatu($struktur[0]['tingkat'], '12');

        // 12-1 ada di kedua sumber, jadi tidak terhitung dua kali.
        $this->assertSame(['12-1', '12-2', '12-3'], array_column($tingkat['rombel'], 'nilai'));
        $this->assertSame([2, 0, 1], array_column($tingkat['rombel'], 'jumlah'));
        $this->assertSame([true, true, false], array_column($tingkat['rombel'], 'terdaftar'));
    }

    public function test_kelas_terdaftar_tidak_berganda_saat_disalin_beberapa_kali(): void
    {
        $struktur = StrukturKelas::dariSiswa(
            [
                ['jurusan' => 'RPL', 'kelas' => '12-1'],
                ['jurusan' => 'RPL', 'kelas' => '12-1'],
            ],
            [
                ['jurusan' => 'RPL', 'kelas' => '12-1'],
                ['jurusan' => 'RPL', 'kelas' => '12-1'],
                ['jurusan' => 'RPL', 'kelas' => '12-1'],
                ['jurusan' => 'RPL', 'kelas' => '12-2'],
            ]
        );

        $tingkat = $this->tingkatSatu($struktur[0]['tingkat'], '12');

        // Kelas yang sama disebut beberapa kali tetap dihitung satu rombel.
        $this->assertSame(['12-1', '12-2'], array_column($tingkat['rombel'], 'nilai'));
        $this->assertSame([2, 0], array_column($tingkat['rombel'], 'jumlah'));
    }

    public function test_kelas_tunggal_bernomor_rombel_tetap_tercatat_sebagai_terdaftar(): void
    {
        // Kelas "10-1" sendiri akan ditampilkan sebagai tingkat "10", karena
        // tingkat 10 cuma punya satu kelas. Status terdaftarnya tetap harus
        // ikut terbawa, walau nama kelasnya bukan sama dengan tingkatnya.
        $struktur = StrukturKelas::dariSiswa(
            [],
            [['jurusan' => 'TKJ', 'kelas' => '10-1']]
        );

        $tingkat = $this->tingkatSatu($struktur[0]['tingkat'], '10');

        $this->assertFalse($tingkat['berrombel']);
        $this->assertCount(1, $tingkat['rombel']);
        $this->assertSame('10', $tingkat['rombel'][0]['nilai']);
        $this->assertSame(StrukturKelas::MATCH_TINGKAT, $tingkat['rombel'][0]['match']);
        $this->assertSame(0, $tingkat['rombel'][0]['jumlah']);
        $this->assertTrue($tingkat['rombel'][0]['terdaftar']);
    }

    public function test_kelas_terdaftar_tidak_berlaku_untuk_kelas_yang_tidak_dikenal(): void
    {
        $struktur = StrukturKelas::dariSiswa(
            [],
            [
                ['jurusan' => 'RPL', 'kelas' => 'XII-1'],
                ['jurusan' => '  ', 'kelas' => '12-1'],
                ['jurusan' => 'TKJ', 'kelas' => ''],
            ]
        );

        $this->assertSame([], $struktur);
    }

    public function test_kelas_terdaftar_memang_ikut_dihitung_ke_total_jurusan(): void
    {
        $struktur = StrukturKelas::dariSiswa(
            [['jurusan' => 'RPL', 'kelas' => '12-1']],
            [['jurusan' => 'RPL', 'kelas' => '11']]
        );

        // Total jurusan adalah jumlah siswa, bukan jumlah kelas.
        $this->assertSame(1, $struktur[0]['jumlah']);
    }
}
