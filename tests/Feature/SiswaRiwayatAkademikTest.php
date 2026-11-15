<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman Riwayat Akademik siswa harus membaca tabel `nilai` apa adanya:
 * dikelompokkan per periode, hanya milik siswa yang login, ketuntasan dihitung
 * terhadap KKM tiap baris, dan tetap memberi keterangan yang jelas saat belum
 * ada nilai. Ringkasan & grafik selalu memakai seluruh periode, sedangkan
 * filter hanya menyaring kartu periode yang ditampilkan.
 */
class SiswaRiwayatAkademikTest extends TestCase
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

    public function test_riwayat_menampilkan_nilai_per_periode_dari_database(): void
    {
        $mtk = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $fis = MataPelajaran::factory()->create(['kode' => 'FIS', 'nama' => 'Fisika']);
        $kim = MataPelajaran::factory()->create(['kode' => 'KIM', 'nama' => 'Kimia']);

        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        // Periode lama: rata-rata 75 (Fisika belum tuntas).
        $this->buatNilai($siswa, $mtk, '2024/2025', 'Ganjil', 80);
        $this->buatNilai($siswa, $fis, '2024/2025', 'Ganjil', 70);

        // Periode baru: rata-rata 85 (semua tuntas).
        $this->buatNilai($siswa, $mtk, '2024/2025', 'Genap', 90);
        $this->buatNilai($siswa, $kim, '2024/2025', 'Genap', 80);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.riwayat-akademik.index'));

        $res->assertOk();
        $res->assertSee('Ganjil 2024/2025');
        $res->assertSee('Genap 2024/2025');
        $res->assertSee('Matematika');
        $res->assertSee('Fisika');
        $res->assertSee('Kimia');
        $res->assertSee('Perkembangan Rata-Rata Nilai');
        $res->assertSee('riwayat-perkembangan');
        $res->assertSee('Tuntas');
        $res->assertSee('Belum Tuntas');

        $ringkasan = $res->viewData('ringkasan');
        $this->assertSame(2, $ringkasan['total_periode']);
        $this->assertSame(4, $ringkasan['total_nilai']);
        $this->assertSame(3, $ringkasan['tuntas']);
        $this->assertSame(75, $ringkasan['persen_tuntas']);
        $this->assertSame(3, $ringkasan['total_mapel']);
        $this->assertEqualsWithDelta(80.0, $ringkasan['rata_rata'], 0.001);
    }

    public function test_riwayat_hanya_menampilkan_nilai_milik_siswa_yang_login(): void
    {
        $mtk = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $fis = MataPelajaran::factory()->create(['kode' => 'FIS', 'nama' => 'Fisika Khusus']);

        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);
        $siswaLain = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $this->buatNilai($siswa, $mtk, '2024/2025', 'Ganjil', 88);
        $this->buatNilai($siswaLain, $fis, '2024/2025', 'Ganjil', 60);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.riwayat-akademik.index'));

        $res->assertOk();
        $res->assertSee('Matematika');
        $res->assertDontSee('Fisika Khusus');

        $ringkasan = $res->viewData('ringkasan');
        $this->assertSame(1, $ringkasan['total_nilai']);
        $this->assertSame(1, $ringkasan['total_mapel']);
    }

    public function test_filter_periode_hanya_menampilkan_periode_yang_dipilih(): void
    {
        $mtk = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $kim = MataPelajaran::factory()->create(['kode' => 'KIM', 'nama' => 'Kimia']);

        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $this->buatNilai($siswa, $mtk, '2024/2025', 'Ganjil', 80);
        $this->buatNilai($siswa, $kim, '2024/2025', 'Genap', 85);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.riwayat-akademik.index', [
            'periode' => 'Ganjil 2024/2025',
        ]));

        $res->assertOk();

        $riwayat = $res->viewData('riwayat');
        $this->assertCount(1, $riwayat);
        $this->assertSame('Ganjil 2024/2025', $riwayat->first()['label']);

        // Ringkasan tetap dihitung dari seluruh periode, bukan hanya yang tampil.
        $this->assertSame(2, $res->viewData('ringkasan')['total_periode']);
    }

    public function test_filter_periode_tak_dikenal_diabaikan(): void
    {
        $mtk = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $kim = MataPelajaran::factory()->create(['kode' => 'KIM', 'nama' => 'Kimia']);

        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $this->buatNilai($siswa, $mtk, '2024/2025', 'Ganjil', 80);
        $this->buatNilai($siswa, $kim, '2024/2025', 'Genap', 85);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.riwayat-akademik.index', [
            'periode' => 'Periode Ngawur',
        ]));

        $res->assertOk();
        $this->assertNull($res->viewData('periodeDipilih'));
        $this->assertCount(2, $res->viewData('riwayat'));
    }

    public function test_selisih_rata_rata_dihitung_dari_periode_sebelumnya(): void
    {
        $mtk = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);

        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $this->buatNilai($siswa, $mtk, '2024/2025', 'Ganjil', 80);
        $this->buatNilai($siswa, $mtk, '2024/2025', 'Genap', 90);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.riwayat-akademik.index'));

        $res->assertOk();

        $riwayat = $res->viewData('riwayat');
        // Kartu diurutkan dari periode terbaru lebih dulu.
        $this->assertSame('Genap 2024/2025', $riwayat->first()['label']);
        $this->assertEqualsWithDelta(10.0, $riwayat->first()['selisih'], 0.001);

        // Periode paling awal tidak punya pembanding.
        $this->assertNull($riwayat->last()['selisih']);

        // Angka selisih tampil di kartu (dipisah koma sesuai format Indonesia).
        $res->assertSee('10,0');
    }

    public function test_siswa_tanpa_nilai_melihat_keterangan_belum_ada(): void
    {
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.riwayat-akademik.index'));

        $res->assertOk();
        $res->assertSee('Riwayat Akademik Belum Tersedia');
        $res->assertDontSee('Perkembangan Rata-Rata Nilai');
    }
}
