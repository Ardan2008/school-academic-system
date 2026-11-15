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

class GuruRekapNilaiTest extends TestCase
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

    public function test_guru_can_access_rekap_nilai_page(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');
        $siswa = $this->siswa('12-1', 'Siswa Satu');
        $this->nilai($siswa, $mapel, 88);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.rekap-nilai.index'))
            ->assertOk()
            ->assertSee('Rekap per Siswa')
            ->assertSee('Rekap per Kelas')
            ->assertSee('Rekap per Mata Pelajaran')
            ->assertSee('Siswa Satu')
            ->assertSee('Ekspor XLSX');
    }

    /** Grafik perkembangan memakai komponen diagram batang bersama (gaya Dashboard Admin). */
    public function test_grafik_perkembangan_memakai_komponen_bar_chart(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');
        $this->nilai($this->siswa('12-1', 'Siswa Satu'), $mapel, 88);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.rekap-nilai.index'))
            ->assertOk()
            ->assertSee('rekap-perkembangan', false)
            ->assertSee('data-bar-chart', false)
            ->assertSee('data-tooltip', false)
            // Baris periode sedikit: batang dibuat mengisi lebar kolom agar kartu tidak kosong.
            ->assertSee('max-w-full', false)
            ->assertSee('Ketuk batang untuk melihat detail');
    }

    /** Periode tanpa nilai tidak boleh ditambahkan sebagai kolom kosong di ujung grafik. */
    public function test_grafik_perkembangan_tidak_menambah_periode_tanpa_nilai(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');

        Nilai::factory()->siswa($this->siswa('12-1', 'Siswa Satu'))->mataPelajaran($mapel)
            ->periode('2025/2026', Periode::SEMESTER_GANJIL)
            ->nilai(80)->kkm(75)->create();

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.rekap-nilai.index'))
            ->assertOk()
            // Label grafik dikirim lewat JSON, jadi garis miring ikut di-escape.
            ->assertSee('Ganjil 2025\/2026')
            // Genap 2025/2026 belum punya nilai, jadi tidak boleh muncul sebagai kolom kosong.
            ->assertDontSee('Genap 2025\/2026');
    }

    public function test_guru_tanpa_mapel_melihat_keterangan_bukan_tabel(): void
    {
        $guru = $this->guru('');
        $this->mapel('Matematika');
        $this->siswa('12-1', 'Siswa Satu');

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.rekap-nilai.index'))
            ->assertOk()
            ->assertSee('Mata Pelajaran Belum Ditugaskan')
            ->assertDontSee('Rekap per Siswa');
    }

    /** Nilai milik mata pelajaran guru lain tidak boleh ikut terhitung. */
    public function test_rekap_hanya_menghitung_mapel_yang_diampu(): void
    {
        $guru = $this->guru('Matematika');
        $matematika = $this->mapel('Matematika');
        $fisika = $this->mapel('Fisika');

        $siswa = $this->siswa('12-1', 'Siswa Satu');
        $siswa->update(['nis' => '1000000001']);
        $this->nilai($siswa, $matematika, 88);
        $this->nilai($siswa, $fisika, 55);

        $response = $this->actingAs($guru, 'guru')->get(route('dashboard.guru.rekap-nilai.index'));

        $siswaRegion = $this->region($response, 'siswa');
        $this->assertStringContainsString('88', $siswaRegion);
        // 55 dari Fisika (mapel guru lain) tidak boleh muncul.
        $this->assertStringNotContainsString('55', $siswaRegion);

        $mapelRegion = $this->region($response, 'mapel');
        $this->assertStringContainsString('Matematika', $mapelRegion);
        $this->assertStringNotContainsString('Fisika', $mapelRegion);
    }

    public function test_pencarian_menyaring_per_siswa_berdasarkan_nama_dan_nis(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');

        $ani = $this->siswa('12-1', 'Ani Lestari');
        $budi = $this->siswa('12-1', 'Budi Santoso');
        $this->nilai($ani, $mapel, 90);
        $this->nilai($budi, $mapel, 70);

        // Pencarian nama.
        $region = $this->region(
            $this->actingAs($guru, 'guru')->get(route('dashboard.guru.rekap-nilai.index', ['cari' => 'Ani'])),
            'siswa',
        );
        $this->assertStringContainsString('Ani Lestari', $region);
        $this->assertStringNotContainsString('Budi Santoso', $region);

        // Pencarian NIS.
        $region = $this->region(
            $this->actingAs($guru, 'guru')->get(route('dashboard.guru.rekap-nilai.index', ['cari' => $budi->nis])),
            'siswa',
        );
        $this->assertStringContainsString('Budi Santoso', $region);
        $this->assertStringNotContainsString('Ani Lestari', $region);
    }

    public function test_pencarian_tanpa_hasil_menampilkan_keterangan(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');
        $this->nilai($this->siswa('12-1', 'Ani Lestari'), $mapel, 90);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.rekap-nilai.index', ['cari' => 'Tidak Ada']))
            ->assertOk()
            ->assertSee('Tidak ada siswa yang cocok dengan pencarian');
    }

    public function test_pagination_membagi_rekap_siswa(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');

        for ($i = 1; $i <= 12; $i++) {
            $this->nilai($this->siswa('12-1', 'Siswa '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)), $mapel, 80);
        }

        // Halaman pertama: 5 siswa pertama, siswa ke-6 belum muncul.
        $halamanSatu = $this->region(
            $this->actingAs($guru, 'guru')->get(route('dashboard.guru.rekap-nilai.index')),
            'siswa',
        );
        $this->assertStringContainsString('Siswa 01', $halamanSatu);
        $this->assertStringContainsString('Siswa 05', $halamanSatu);
        $this->assertStringNotContainsString('Siswa 06', $halamanSatu);

        // Halaman kedua: siswa ke-6 sampai ke-10.
        $halamanDua = $this->region(
            $this->actingAs($guru, 'guru')->get(route('dashboard.guru.rekap-nilai.index', ['page' => 2])),
            'siswa',
        );
        $this->assertStringContainsString('Siswa 06', $halamanDua);
        $this->assertStringContainsString('Siswa 10', $halamanDua);
        $this->assertStringNotContainsString('Siswa 01', $halamanDua);

        // Halaman ketiga: sisa siswa.
        $halamanTiga = $this->region(
            $this->actingAs($guru, 'guru')->get(route('dashboard.guru.rekap-nilai.index', ['page' => 3])),
            'siswa',
        );
        $this->assertStringContainsString('Siswa 12', $halamanTiga);
        $this->assertStringNotContainsString('Siswa 01', $halamanTiga);
    }

    public function test_pagination_membagi_rekap_kelas(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');

        for ($i = 1; $i <= 6; $i++) {
            $this->nilai($this->siswa('12-'.$i, 'Siswa '.$i), $mapel, 80);
        }

        // Halaman pertama: 5 kelas pertama dari 6.
        $halamanSatu = $this->region(
            $this->actingAs($guru, 'guru')->get(route('dashboard.guru.rekap-nilai.index')),
            'kelas',
        );
        $this->assertStringContainsString('Menampilkan 1–5 dari 6 kelas', $halamanSatu);

        // Halaman kedua: kelas ke-6. Parameter kelas_page terpisah dari siswa.
        $halamanDua = $this->region(
            $this->actingAs($guru, 'guru')->get(route('dashboard.guru.rekap-nilai.index', ['kelas_page' => 2])),
            'kelas',
        );
        $this->assertStringContainsString('Menampilkan 6–6 dari 6 kelas', $halamanDua);
    }

    public function test_pagination_membagi_rekap_mata_pelajaran(): void
    {
        $guru = $this->guru('Matematika');

        // Enam mata pelajaran dengan nama sama (diampu guru) -> enam baris rekap.
        for ($i = 1; $i <= 6; $i++) {
            $mapel = $this->mapel('Matematika', 'MTK-'.$i);
            $this->nilai($this->siswa('12-'.$i, 'Siswa '.$i), $mapel, 80);
        }

        // Halaman pertama: 5 mata pelajaran pertama dari 6.
        $halamanSatu = $this->region(
            $this->actingAs($guru, 'guru')->get(route('dashboard.guru.rekap-nilai.index')),
            'mapel',
        );
        $this->assertStringContainsString('Menampilkan 1–5 dari 6 mata pelajaran', $halamanSatu);

        // Halaman kedua: mata pelajaran ke-6. Parameter mapel_page terpisah dari siswa/kelas.
        $halamanDua = $this->region(
            $this->actingAs($guru, 'guru')->get(route('dashboard.guru.rekap-nilai.index', ['mapel_page' => 2])),
            'mapel',
        );
        $this->assertStringContainsString('Menampilkan 6–6 dari 6 mata pelajaran', $halamanDua);
    }

    /**
     * Saat rekap mata pelajaran cuma sehalaman (data kurang dari lima), footer
     * paginasi tidak dirender karena tidak ada halaman lain untuk dituju.
     */
    public function test_rekap_per_mata_pelajaran_tanpa_footer_paginasi_saat_data_kurang(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');
        $this->nilai($this->siswa('12-1', 'Siswa Satu'), $mapel, 88);

        $html = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.rekap-nilai.index'))
            ->assertOk()
            ->getContent();

        $mulai = strpos($html, 'data-region="mapel"');
        $this->assertNotFalse($mulai, 'Region [mapel] tidak ditemukan.');
        $selesai = strpos($html, 'data-region="', $mulai + 1);
        $region = $selesai === false ? substr($html, $mulai) : substr($html, $mulai, $selesai - $mulai);

        $this->assertStringNotContainsString('data-table-footer', $region);
    }

    public function test_filter_kelas_menyaring_rekap(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');

        $this->nilai($this->siswa('12-1', 'Siswa Dua Belas', self::JURUSAN), $mapel, 90);
        $this->nilai($this->siswa('11-1', 'Siswa Sebelas', self::JURUSAN_LAIN), $mapel, 70);

        $region = $this->region(
            $this->actingAs($guru, 'guru')->get(route('dashboard.guru.rekap-nilai.index', ['kelas' => '12-1'])),
            'siswa',
        );
        $this->assertStringContainsString('Siswa Dua Belas', $region);
        $this->assertStringNotContainsString('Siswa Sebelas', $region);
    }

    public function test_guru_can_export_rekap_siswa(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika', 'MTK');
        $siswa = $this->siswa('12-1', 'Ani Lestari');
        $this->nilai($siswa, $mapel, 88);

        $response = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.rekap-nilai.export-siswa'));

        $response->assertOk()->assertHeader('X-Total-Baris', '1');

        $this->assertMatchesRegularExpression(
            '/^attachment; filename="rekap-nilai-siswa-\d{8}-\d{6}\.xlsx"$/',
            (string) $response->headers->get('content-disposition')
        );

        $baris = $this->bacaBukuKerja($response);

        // Baris 0 judul, baris 2 header, baris 3 data.
        $this->assertSame('Rekap Nilai per Siswa', $baris[0][0]);
        $this->assertSame('Nama', $baris[2][2]);
        $this->assertSame('Ani Lestari', $baris[3][2]);
        $this->assertSame('12-1', $baris[3][3]);
        $this->assertSame(88.0, (float) $baris[3][5]);
    }

    public function test_guru_can_export_rekap_kelas(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');
        $this->nilai($this->siswa('12-1', 'Ani Lestari'), $mapel, 87);

        $response = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.rekap-nilai.export-kelas'));

        $response->assertOk()->assertHeader('X-Total-Baris', '1');

        $baris = $this->bacaBukuKerja($response);

        $this->assertSame('Rekap Nilai per Kelas', $baris[0][0]);
        $this->assertSame('12-1', $baris[3][1]);
        $this->assertSame(87.0, (float) $baris[3][5]);
    }

    public function test_guru_can_export_rekap_mata_pelajaran(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika', 'MTK');
        $this->nilai($this->siswa('12-1', 'Ani Lestari'), $mapel, 76);

        $response = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.rekap-nilai.export-mata-pelajaran'));

        $response->assertOk()->assertHeader('X-Total-Baris', '1');

        $baris = $this->bacaBukuKerja($response);

        $this->assertSame('Rekap Nilai per Mata Pelajaran', $baris[0][0]);
        $this->assertSame('Matematika', $baris[3][2]);
        $this->assertSame(76.0, (float) $baris[3][5]);
    }

    public function test_non_guru_tidak_bisa_membuka_rekap_nilai(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard.guru.rekap-nilai.index'))
            ->assertForbidden();
    }

    public function test_non_guru_tidak_bisa_mengekspor_rekap_nilai(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard.guru.rekap-nilai.export-siswa'))
            ->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get(route('dashboard.guru.rekap-nilai.index'))
            ->assertRedirect(route('login'));
    }
}
