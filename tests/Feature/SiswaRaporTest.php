<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\User;
use App\Support\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman Rapor siswa menampilkan nilai satu periode terpilih, hanya milik
 * siswa yang login, dengan ketuntasan dihitung terhadap KKM tiap baris.
 * Ringkasan periode (jumlah mapel, rata-rata, tuntas/belum tuntas) harus
 * konsisten dengan baris yang tampil, dan periode tanpa nilai diberi
 * keterangan yang jelas.
 */
class SiswaRaporTest extends TestCase
{
    use RefreshDatabase;

    private function buatNilai(
        User $siswa,
        MataPelajaran $mapel,
        string $tahunAjaran,
        string $semester,
        float $nilai,
        float $kkm = 75,
    ): Nilai {
        return Nilai::create([
            'user_id' => $siswa->id,
            'mata_pelajaran_id' => $mapel->id,
            'kelas' => $siswa->kelas,
            'jurusan' => $siswa->jurusan,
            'tahun_ajaran' => $tahunAjaran,
            'semester' => $semester,
            'nilai' => $nilai,
            'kkm' => $kkm,
        ]);
    }

    public function test_rapor_default_menampilkan_periode_berjalan(): void
    {
        $mtk = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $fis = MataPelajaran::factory()->create(['kode' => 'FIS', 'nama' => 'Fisika']);
        $kim = MataPelajaran::factory()->create(['kode' => 'KIM', 'nama' => 'Kimia']);

        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);
        $periode = Periode::periodeSekarang();

        // Periode berjalan: rata-rata 79, satu tuntas satu belum.
        $this->buatNilai($siswa, $mtk, $periode['tahun_ajaran'], $periode['semester'], 88);
        $this->buatNilai($siswa, $fis, $periode['tahun_ajaran'], $periode['semester'], 70);

        // Periode lain tidak boleh ikut tampil secara bawaan.
        $this->buatNilai($siswa, $kim, '2020/2021', 'Ganjil', 95);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.rapor.index'));

        $res->assertOk();
        $res->assertSee('Matematika');
        $res->assertSee('Fisika');
        $res->assertDontSee('Kimia');
        $res->assertSee('Belum Tuntas');
        $res->assertSee('79,00');

        $ringkasan = $res->viewData('ringkasan');
        $this->assertSame(2, $ringkasan['jumlah_mapel']);
        $this->assertEqualsWithDelta(79.0, $ringkasan['rata_rata'], 0.001);
        $this->assertSame(1, $ringkasan['tuntas']);
        $this->assertSame(1, $ringkasan['belum_tuntas']);
        $this->assertSame(50, $ringkasan['persen_tuntas']);
    }

    public function test_rapor_mengikuti_filter_tahun_ajaran_dan_semester(): void
    {
        $mtk = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $kim = MataPelajaran::factory()->create(['kode' => 'KIM', 'nama' => 'Kimia']);

        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);
        $periode = Periode::periodeSekarang();

        $this->buatNilai($siswa, $mtk, $periode['tahun_ajaran'], $periode['semester'], 88);
        $this->buatNilai($siswa, $kim, '2020/2021', 'Genap', 95);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.rapor.index', [
            'tahun_ajaran' => '2020/2021',
            'semester' => 'Genap',
        ]));

        $res->assertOk();
        $res->assertSee('Kimia');
        $res->assertDontSee('Matematika');
        $this->assertSame('Genap 2020/2021', $res->viewData('periodeLabel'));
        $this->assertSame(1, $res->viewData('ringkasan')['jumlah_mapel']);
    }

    public function test_periode_tak_dikenal_kembali_ke_periode_berjalan(): void
    {
        $mtk = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);

        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);
        $periode = Periode::periodeSekarang();

        $this->buatNilai($siswa, $mtk, $periode['tahun_ajaran'], $periode['semester'], 88);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.rapor.index', [
            'tahun_ajaran' => '2020/2023',
            'semester' => 'Libur',
        ]));

        $res->assertOk();
        $this->assertSame($periode['tahun_ajaran'], $res->viewData('periode')['tahun_ajaran']);
        $this->assertSame($periode['semester'], $res->viewData('periode')['semester']);
        $res->assertSee('Matematika');
    }

    public function test_tahun_ajaran_terpilih_tetap_muncul_di_pilihan_meski_tanpa_data(): void
    {
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.rapor.index', [
            'tahun_ajaran' => '2019/2020',
            'semester' => 'Ganjil',
        ]));

        $res->assertOk();
        $res->assertSee('Rapor Belum Tersedia');
        $this->assertContains('2019/2020', $res->viewData('tahunAjarans'));
    }

    public function test_rapor_hanya_menampilkan_nilai_milik_siswa_yang_login(): void
    {
        $mtk = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $fis = MataPelajaran::factory()->create(['kode' => 'FIS', 'nama' => 'Fisika Khusus']);

        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);
        $siswaLain = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);
        $periode = Periode::periodeSekarang();

        $this->buatNilai($siswa, $mtk, $periode['tahun_ajaran'], $periode['semester'], 88);
        $this->buatNilai($siswaLain, $fis, $periode['tahun_ajaran'], $periode['semester'], 60);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.rapor.index'));

        $res->assertOk();
        $res->assertSee('Matematika');
        $res->assertDontSee('Fisika Khusus');
        $this->assertSame(1, $res->viewData('ringkasan')['jumlah_mapel']);
    }

    public function test_rapor_periode_tanpa_nilai_menampilkan_keterangan(): void
    {
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.rapor.index'));

        $res->assertOk();
        $res->assertSee('Rapor Belum Tersedia');
        $res->assertDontSee('Daftar Nilai');
    }

    public function test_rapor_menampilkan_identitas_siswa(): void
    {
        $siswa = User::factory()->siswa('12-1')->create([
            'name' => 'Ahmad Fauzi',
            'nis' => '1234567890',
            'jurusan' => 'Rekayasa Perangkat Lunak',
        ]);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.rapor.index'));

        $res->assertOk();
        $res->assertSee('Ahmad Fauzi');
        $res->assertSee('1234567890');
        $res->assertSee('Identitas Siswa');
    }
}
