<?php

namespace Tests\Feature;

use App\Models\KelasRombel;
use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\PenugasanMengajar;
use App\Models\User;
use App\Support\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithSpreadsheet;
use Tests\TestCase;

class GuruPenugasanRekapTest extends TestCase
{
    use InteractsWithSpreadsheet;
    use RefreshDatabase;

    private const JURUSAN = 'Rekayasa Perangkat Lunak';

    protected function guru(string $mapel = 'Matematika', bool $diatur = false): User
    {
        return User::factory()->guru()->create([
            'mata_pelajaran' => $mapel,
            'penugasan_diatur' => $diatur,
            'must_change_password' => false,
        ]);
    }

    protected function mapel(string $nama = 'Matematika', ?string $kode = null): MataPelajaran
    {
        return MataPelajaran::factory()->nama($nama)->kode($kode)->create();
    }

    protected function kelas(string $kelas, string $jurusan = self::JURUSAN): KelasRombel
    {
        return KelasRombel::create([
            'jurusan' => $jurusan,
            'kelas' => $kelas,
        ]);
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

    protected function tugaskan(User $guru, MataPelajaran $mapel, KelasRombel $kelas): void
    {
        PenugasanMengajar::create([
            'guru_id' => $guru->id,
            'mata_pelajaran_id' => $mapel->id,
            'kelas_id' => $kelas->id,
        ]);

        $guru->update(['penugasan_diatur' => true]);
    }

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

    public function test_guru_terkonfigurasi_hanya_melihat_pasangan_penugasan(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika', 'MTK');
        $kelas12 = $this->kelas('12-1');
        $this->kelas('11-1');
        $this->tugaskan($guru, $mapel, $kelas12);

        $this->nilai($this->siswa('12-1', 'Ani Lestari'), $mapel, 90);
        $this->nilai($this->siswa('11-1', 'Budi Santoso'), $mapel, 70);

        $dashboard = $this->actingAs($guru, 'guru')->get(route('dashboard.guru.index'));

        $kelas = $this->region($dashboard, 'kelas');
        $this->assertStringContainsString('12-1', $kelas);
        $this->assertStringNotContainsString('11-1', $kelas);

        $siswa = $this->region($dashboard, 'siswa');
        $this->assertStringContainsString('Ani Lestari', $siswa);
        $this->assertStringNotContainsString('Budi Santoso', $siswa);

        $perhatian = $this->region($dashboard, 'perhatian');
        $this->assertStringNotContainsString('Budi Santoso', $perhatian);

        $rekap = $this->actingAs($guru, 'guru')->get(route('dashboard.guru.rekap-nilai.index'));

        $siswa = $this->region($rekap, 'siswa');
        $this->assertStringContainsString('Ani Lestari', $siswa);
        $this->assertStringNotContainsString('Budi Santoso', $siswa);

        $kelas = $this->region($rekap, 'kelas');
        $this->assertStringContainsString('12-1', $kelas);
        $this->assertStringNotContainsString('11-1', $kelas);

        $rekap->assertSee('<option value="12-1"', false)
            ->assertDontSee('<option value="11-1"', false);

        $laporan = $this->actingAs($guru, 'guru')->get(route('dashboard.guru.laporan.index'));

        $siswa = $this->region($laporan, 'laporan-siswa');
        $this->assertStringContainsString('Ani Lestari', $siswa);
        $this->assertStringNotContainsString('Budi Santoso', $siswa);

        $kelas = $this->region($laporan, 'laporan-kelas');
        $this->assertStringContainsString('12-1', $kelas);
        $this->assertStringNotContainsString('11-1', $kelas);

        $laporan->assertSee('<option value="12-1"', false)
            ->assertDontSee('<option value="11-1"', false);
    }

    public function test_export_guru_terkonfigurasi_mengikuti_penugasan(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika', 'MTK');
        $kelas12 = $this->kelas('12-1');
        $this->kelas('11-1');
        $this->tugaskan($guru, $mapel, $kelas12);

        $this->nilai($this->siswa('12-1', 'Ani Lestari'), $mapel, 90);
        $this->nilai($this->siswa('11-1', 'Budi Santoso'), $mapel, 70);

        $rekapSiswa = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.rekap-nilai.export-siswa'));
        $rekapSiswa->assertOk()->assertHeader('X-Total-Baris', '1');
        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($rekapSiswa));
        $this->assertStringContainsString('Ani Lestari', $isi);
        $this->assertStringNotContainsString('Budi Santoso', $isi);

        $rekapKelas = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.rekap-nilai.export-kelas'));
        $rekapKelas->assertOk()->assertHeader('X-Total-Baris', '1');
        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($rekapKelas));
        $this->assertStringContainsString('12-1', $isi);
        $this->assertStringNotContainsString('11-1', $isi);

        $rekapMapel = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.rekap-nilai.export-mata-pelajaran'));
        $rekapMapel->assertOk()->assertHeader('X-Total-Baris', '1');

        $laporan = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.laporan.export'));
        $laporan->assertOk()->assertHeader('X-Total-Baris', '1');
        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($laporan));
        $this->assertStringContainsString('Ani Lestari', $isi);
        $this->assertStringNotContainsString('Budi Santoso', $isi);
    }

    public function test_pilihan_tahun_ajaran_rekap_dan_laporan_mengikuti_penugasan(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika', 'MTK');
        $this->tugaskan($guru, $mapel, $this->kelas('12-1'));

        $this->nilai($this->siswa('12-1', 'Ani Lestari'), $mapel, 90);
        Nilai::factory()
            ->siswa($this->siswa('11-1', 'Budi Santoso'))
            ->mataPelajaran($mapel)
            ->periode('2020/2021', Periode::SEMESTER_GANJIL)
            ->nilai(70)
            ->kkm(75)
            ->create();

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.rekap-nilai.index'))
            ->assertOk()
            ->assertDontSee('2020/2021');

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.laporan.index'))
            ->assertOk()
            ->assertDontSee('2020/2021');
    }

    public function test_guru_belum_diatur_tetap_melihat_data_lama(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika', 'MTK');

        $this->nilai($this->siswa('12-1', 'Ani Lestari'), $mapel, 90);
        $this->nilai($this->siswa('11-1', 'Budi Santoso'), $mapel, 70);

        $dashboard = $this->actingAs($guru, 'guru')->get(route('dashboard.guru.index'));

        $kelas = $this->region($dashboard, 'kelas');
        $this->assertStringContainsString('12-1', $kelas);
        $this->assertStringContainsString('11-1', $kelas);

        $siswa = $this->region($dashboard, 'siswa');
        $this->assertStringContainsString('Ani Lestari', $siswa);
        $this->assertStringContainsString('Budi Santoso', $siswa);

        $rekap = $this->actingAs($guru, 'guru')->get(route('dashboard.guru.rekap-nilai.index'));

        $siswa = $this->region($rekap, 'siswa');
        $this->assertStringContainsString('Ani Lestari', $siswa);
        $this->assertStringContainsString('Budi Santoso', $siswa);

        $rekap->assertSee('<option value="12-1"', false)
            ->assertSee('<option value="11-1"', false);

        $laporan = $this->actingAs($guru, 'guru')->get(route('dashboard.guru.laporan.index'));

        $siswa = $this->region($laporan, 'laporan-siswa');
        $this->assertStringContainsString('Ani Lestari', $siswa);
        $this->assertStringContainsString('Budi Santoso', $siswa);

        $laporan->assertSee('<option value="12-1"', false)
            ->assertSee('<option value="11-1"', false);

        $rekapSiswa = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.rekap-nilai.export-siswa'));
        $rekapSiswa->assertOk()->assertHeader('X-Total-Baris', '2');

        $laporanExport = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.laporan.export'));
        $laporanExport->assertOk()->assertHeader('X-Total-Baris', '2');
    }

    public function test_guru_terkonfigurasi_tanpa_penugasan_melihat_data_kosong(): void
    {
        $guru = $this->guru('Matematika', true);
        $mapel = $this->mapel('Matematika', 'MTK');

        $this->nilai($this->siswa('12-1', 'Ani Lestari'), $mapel, 90);
        $this->nilai($this->siswa('11-1', 'Budi Santoso'), $mapel, 70);

        $dashboard = $this->actingAs($guru, 'guru')->get(route('dashboard.guru.index'));

        $dashboard->assertOk()
            ->assertSee('Mata Pelajaran Belum Ditugaskan')
            ->assertDontSee('Ani Lestari')
            ->assertDontSee('Budi Santoso');

        $rekap = $this->actingAs($guru, 'guru')->get(route('dashboard.guru.rekap-nilai.index'));

        $rekap->assertOk()
            ->assertSee('Mata Pelajaran Belum Ditugaskan')
            ->assertDontSee('Rekap per Siswa')
            ->assertDontSee('Ani Lestari')
            ->assertDontSee('Budi Santoso')
            ->assertDontSee('<option value="12-1"', false)
            ->assertDontSee('<option value="11-1"', false);

        $laporan = $this->actingAs($guru, 'guru')->get(route('dashboard.guru.laporan.index'));

        $laporan->assertOk()
            ->assertSee('Mata Pelajaran Belum Ditugaskan')
            ->assertDontSee('Daftar Nilai per Siswa')
            ->assertDontSee('Ani Lestari')
            ->assertDontSee('Budi Santoso')
            ->assertDontSee('<option value="12-1"', false)
            ->assertDontSee('<option value="11-1"', false);

        $rekapSiswa = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.rekap-nilai.export-siswa'));
        $rekapSiswa->assertOk()->assertHeader('X-Total-Baris', '0');
        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($rekapSiswa));
        $this->assertStringNotContainsString('Ani Lestari', $isi);
        $this->assertStringNotContainsString('Budi Santoso', $isi);

        $rekapKelas = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.rekap-nilai.export-kelas'));
        $rekapKelas->assertOk()->assertHeader('X-Total-Baris', '0');

        $laporanExport = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.laporan.export'));
        $laporanExport->assertOk()->assertHeader('X-Total-Baris', '0');
        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($laporanExport));
        $this->assertStringNotContainsString('Ani Lestari', $isi);
        $this->assertStringNotContainsString('Budi Santoso', $isi);
    }
}
