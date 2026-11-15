<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\User;
use App\Support\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithSpreadsheet;
use Tests\TestCase;

class GuruLaporanTest extends TestCase
{
    use InteractsWithSpreadsheet;
    use RefreshDatabase;

    private const JURUSAN = 'Rekayasa Perangkat Lunak';

    private const JURUSAN_LAIN = 'Teknik Komputer Jaringan';

    protected function guru(string $mapel = 'Matematika'): User
    {
        return User::factory()->guru()->create([
            'mata_pelajaran' => $mapel,
            'must_change_password' => false,
        ]);
    }

    protected function mapel(string $nama = 'Matematika', ?string $kode = null): MataPelajaran
    {
        return MataPelajaran::factory()->nama($nama)->kode($kode)->create();
    }

    protected function siswa(string $kelas, string $nama, string $jurusan = self::JURUSAN): User
    {
        return User::factory()->siswa($kelas)->create([
            'jurusan' => $jurusan,
            'name' => $nama,
            'status' => User::STATUS_AKTIF,
        ]);
    }

    protected function nilai(User $siswa, MataPelajaran $mapel, float|int $nilai, float|int $kkm = 75): Nilai
    {
        $periode = Periode::periodeSekarang();

        return Nilai::factory()->siswa($siswa)->mataPelajaran($mapel)
            ->periode($periode['tahun_ajaran'], $periode['semester'])
            ->nilai($nilai)->kkm($kkm)->create();
    }

    /**
     * Ambil teks yang terlihat di satu region halaman, supaya pemeriksaan
     * "nilai ini tidak boleh muncul" tidak salah positif karena class Tailwind.
     */
    private function region(TestResponse $response, string $nama): string
    {
        $html = $response->assertOk()->getContent();
        $mulai = strpos($html, 'data-region="'.$nama.'"');

        $this->assertNotFalse($mulai, "Region [{$nama}] tidak ditemukan.");

        $selesai = strpos($html, 'data-region="', $mulai + 1);
        $html = $selesai === false ? substr($html, $mulai) : substr($html, $mulai, $selesai - $mulai);

        $teks = preg_replace('/<script\b[^>]*>.*?<\/script>/s', ' ', $html);
        $teks = preg_replace('/<[^>]+>/', ' ', (string) $teks);

        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode($teks, ENT_QUOTES | ENT_HTML5)));
    }

    public function test_guru_can_access_laporan_page(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');
        $siswa = $this->siswa('12-1', 'Ani Lestari');
        $this->nilai($siswa, $mapel, 88);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.laporan.index'))
            ->assertOk()
            ->assertSee('Laporan Nilai Siswa')
            ->assertSee('Ringkasan Laporan')
            ->assertSee('Sebaran Nilai')
            ->assertSee('Rekap per Kelas')
            ->assertSee('Daftar Nilai per Siswa')
            ->assertSee('Ani Lestari')
            ->assertSee('Unduh Excel')
            ->assertSee('Cetak');
    }

    public function test_guru_tanpa_mapel_melihat_keterangan(): void
    {
        $guru = $this->guru('');

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.laporan.index'))
            ->assertOk()
            ->assertSee('Mata Pelajaran Belum Ditugaskan')
            ->assertDontSee('Daftar Nilai per Siswa');
    }

    public function test_laporan_kosong_menampilkan_keterangan_bukan_tabel(): void
    {
        $guru = $this->guru('Matematika');
        $this->mapel('Matematika');

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.laporan.index'))
            ->assertOk()
            ->assertSee('Belum ada nilai pada rentang ini')
            ->assertDontSee('Daftar Nilai per Siswa');
    }

    public function test_laporan_hanya_menampilkan_nilai_mapel_yang_diampu(): void
    {
        $guru = $this->guru('Matematika');
        $matematika = $this->mapel('Matematika');
        $fisika = $this->mapel('Fisika');

        $siswa = $this->siswa('12-1', 'Ani Lestari');
        $this->nilai($siswa, $matematika, 88);

        // Nilai Fisika milik guru lain tidak boleh ikut tampil sebagai kolom.
        $this->nilai($this->siswa('12-1', 'Budi Santoso'), $fisika, 60);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.laporan.index'))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertDontSee('Fisika')
            ->assertSee('Ani Lestari')
            ->assertDontSee('Budi Santoso');
    }

    public function test_filter_kelas_menyaring_laporan(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');

        $this->nilai($this->siswa('12-1', 'Siswa Dua Belas', self::JURUSAN), $mapel, 90);
        $this->nilai($this->siswa('11-1', 'Siswa Sebelas', self::JURUSAN_LAIN), $mapel, 70);

        $region = $this->region(
            $this->actingAs($guru, 'guru')->get(route('dashboard.guru.laporan.index', ['kelas' => '12-1'])),
            'laporan-siswa',
        );

        $this->assertStringContainsString('Siswa Dua Belas', $region);
        $this->assertStringNotContainsString('Siswa Sebelas', $region);
    }

    public function test_pencarian_menyaring_daftar_siswa(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');

        $ani = $this->siswa('12-1', 'Ani Lestari');
        $this->nilai($ani, $mapel, 90);
        $this->nilai($this->siswa('12-1', 'Budi Santoso'), $mapel, 70);

        $region = $this->region(
            $this->actingAs($guru, 'guru')->get(route('dashboard.guru.laporan.index', ['cari' => 'Ani'])),
            'laporan-siswa',
        );

        $this->assertStringContainsString('Ani Lestari', $region);
        $this->assertStringNotContainsString('Budi Santoso', $region);
    }

    public function test_sebaran_nilai_menghitung_jumlah_per_kelompok(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');

        // 1 nilai >= 90, 2 nilai 80-89, 1 nilai < 70.
        $this->nilai($this->siswa('12-1', 'Siswa A'), $mapel, 95);
        $this->nilai($this->siswa('12-1', 'Siswa B'), $mapel, 85);
        $this->nilai($this->siswa('12-1', 'Siswa C'), $mapel, 82);
        $this->nilai($this->siswa('12-1', 'Siswa D'), $mapel, 60);

        $html = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.laporan.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Sangat Baik', $html);
        $this->assertStringContainsString('Perlu Perbaikan', $html);
        // 4 nilai tercatat pada badge sebaran.
        $this->assertStringContainsString('4 nilai', $html);
    }

    public function test_pagination_membagi_daftar_nilai_per_siswa(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');

        for ($i = 1; $i <= 6; $i++) {
            $this->nilai($this->siswa('12-1', 'Siswa '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)), $mapel, 80);
        }

        // Halaman pertama: 5 siswa pertama, siswa ke-6 belum muncul di panel layar.
        $halamanSatu = $this->region(
            $this->actingAs($guru, 'guru')->get(route('dashboard.guru.laporan.index')),
            'laporan-siswa',
        );
        $this->assertStringContainsString('Menampilkan 1–5 dari 6 siswa', $halamanSatu);
        $this->assertStringContainsString('Siswa 01', $halamanSatu);
        $this->assertStringNotContainsString('Siswa 06', $halamanSatu);

        // Halaman kedua: siswa ke-6. Parameter siswa_page terpisah dari kelas_page.
        $halamanDua = $this->region(
            $this->actingAs($guru, 'guru')->get(route('dashboard.guru.laporan.index', ['siswa_page' => 2])),
            'laporan-siswa',
        );
        $this->assertStringContainsString('Menampilkan 6–6 dari 6 siswa', $halamanDua);
        $this->assertStringContainsString('Siswa 06', $halamanDua);
        $this->assertStringNotContainsString('Siswa 01', $halamanDua);
    }

    public function test_pagination_membagi_rekap_per_kelas(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');

        for ($i = 1; $i <= 6; $i++) {
            $this->nilai($this->siswa('12-'.$i, 'Siswa '.$i), $mapel, 80);
        }

        // Halaman pertama: 5 kelas pertama dari 6.
        $halamanSatu = $this->region(
            $this->actingAs($guru, 'guru')->get(route('dashboard.guru.laporan.index')),
            'laporan-kelas',
        );
        $this->assertStringContainsString('Menampilkan 1–5 dari 6 kelas', $halamanSatu);

        // Halaman kedua: kelas ke-6. Parameter kelas_page terpisah dari siswa_page.
        $halamanDua = $this->region(
            $this->actingAs($guru, 'guru')->get(route('dashboard.guru.laporan.index', ['kelas_page' => 2])),
            'laporan-kelas',
        );
        $this->assertStringContainsString('Menampilkan 6–6 dari 6 kelas', $halamanDua);
    }

    public function test_cetak_memuat_seluruh_baris_walau_layar_berhalaman(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');

        for ($i = 1; $i <= 6; $i++) {
            $this->nilai($this->siswa('12-1', 'Siswa '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)), $mapel, 80);
        }

        $response = $this->actingAs($guru, 'guru')->get(route('dashboard.guru.laporan.index'));

        // Panel layar hanya memuat lima baris.
        $layar = $this->region($response, 'laporan-siswa');
        $this->assertStringContainsString('Siswa 01', $layar);
        $this->assertStringNotContainsString('Siswa 06', $layar);

        // Salinan cetak memuat seluruh baris sesuai filter aktif.
        $cetak = $this->region($response, 'laporan-siswa-cetak');
        $this->assertStringContainsString('Siswa 01', $cetak);
        $this->assertStringContainsString('Siswa 06', $cetak);
        $this->assertStringContainsString('6 siswa', $cetak);
    }

    public function test_export_tetap_memproses_seluruh_data_pada_halaman_aktif(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');

        for ($i = 1; $i <= 6; $i++) {
            $this->nilai($this->siswa('12-1', 'Siswa '.$i), $mapel, 80);
        }

        // Parameter halaman tidak boleh membatasi baris yang diekspor.
        $response = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.laporan.export', ['siswa_page' => 2, 'kelas_page' => 2]));

        $response->assertOk()->assertHeader('X-Total-Baris', '6');
    }

    public function test_guru_can_export_laporan(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika', 'MTK');
        $siswa = $this->siswa('12-1', 'Ani Lestari');
        $this->nilai($siswa, $mapel, 88);

        $response = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.laporan.export'));

        $response->assertOk()->assertHeader('X-Total-Baris', '1');

        $this->assertMatchesRegularExpression(
            '/^attachment; filename="laporan-nilai-\d{8}-\d{6}\.xlsx"$/',
            (string) $response->headers->get('content-disposition')
        );

        $baris = $this->bacaBukuKerja($response);

        // Baris 0 judul, baris 2 header, baris 3 data.
        $this->assertSame('Laporan Nilai Siswa', $baris[0][0]);
        $this->assertSame('Nama', $baris[2][2]);
        $this->assertSame('Ani Lestari', $baris[3][2]);
        $this->assertSame('12-1', $baris[3][3]);
        $this->assertSame(88.0, (float) $baris[3][5]);
    }

    public function test_export_mengikuti_filter_kelas(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');

        $this->nilai($this->siswa('12-1', 'Siswa Dua Belas'), $mapel, 90);
        $this->nilai($this->siswa('11-1', 'Siswa Sebelas'), $mapel, 70);

        $response = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.laporan.export', ['kelas' => '12-1']));

        $response->assertOk()->assertHeader('X-Total-Baris', '1');

        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($response));
        $this->assertStringContainsString('Siswa Dua Belas', $isi);
        $this->assertStringNotContainsString('Siswa Sebelas', $isi);
    }

    public function test_non_guru_tidak_bisa_membuka_laporan(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard.guru.laporan.index'))
            ->assertForbidden();
    }

    public function test_non_guru_tidak_bisa_mengekspor_laporan(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard.guru.laporan.export'))
            ->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get(route('dashboard.guru.laporan.index'))
            ->assertRedirect(route('login'));
    }
}
