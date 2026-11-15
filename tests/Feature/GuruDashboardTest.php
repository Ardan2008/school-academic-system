<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\User;
use App\Support\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GuruDashboardTest extends TestCase
{
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
        // Kode deterministik agar dua mapel dalam satu test tidak bertabrakan
        // dengan generator acak bawaan factory.
        $kode ??= 'MP-'.strtoupper(substr(md5($nama), 0, 6));

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
     * "tidak boleh muncul" tidak salah positif karena class Tailwind.
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

    public function test_guru_can_access_dashboard(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');
        $this->nilai($this->siswa('12-1', 'Ani Lestari'), $mapel, 90);
        $this->nilai($this->siswa('12-1', 'Budi Santoso'), $mapel, 80);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.index'))
            ->assertOk()
            ->assertSee('Total Siswa')
            ->assertSee('Kelas Diampu')
            ->assertSee('Mata Pelajaran')
            ->assertSee('Rata-Rata Nilai')
            ->assertSee('Perkembangan Rata-Rata Nilai')
            ->assertSee('Nilai Kelas Tertinggi')
            ->assertSee('Rekap per Kelas')
            ->assertSee('Perlu Perhatian')
            ->assertSee('Data Siswa')
            ->assertSee('Ani Lestari')
            // Rata-rata (90 + 80) / 2 = 85 dan keduanya tuntas.
            ->assertSee('100% tuntas · 2 nilai');
    }

    public function test_guru_tanpa_mapel_melihat_keterangan(): void
    {
        $guru = $this->guru('');

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.index'))
            ->assertOk()
            ->assertSee('Mata Pelajaran Belum Ditugaskan')
            ->assertDontSee('Perkembangan Rata-Rata Nilai');
    }

    public function test_kosong_menampilkan_keterangan_bukan_angka_salah(): void
    {
        $guru = $this->guru('Matematika');
        $this->mapel('Matematika');

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.index'))
            ->assertOk()
            ->assertSee('Belum ada nilai')
            ->assertSee('Belum ada nilai pada periode ini.')
            ->assertSee('Semua siswa sudah mencapai KKM.');
    }

    /** Angka dashboard hanya boleh datang dari mata pelajaran yang diampu guru. */
    public function test_hanya_menghitung_nilai_mapel_yang_diampu(): void
    {
        $guru = $this->guru('Matematika');
        $matematika = $this->mapel('Matematika');
        $fisika = $this->mapel('Fisika');

        $this->nilai($this->siswa('12-1', 'Ani Lestari'), $matematika, 88);
        // Nilai Fisika milik guru lain, di kelas lain, tidak boleh masuk rekap.
        $this->nilai($this->siswa('11-1', 'Budi Santoso', self::JURUSAN_LAIN), $fisika, 60);

        $response = $this->actingAs($guru, 'guru')->get(route('dashboard.guru.index'));

        $kelas = $this->region($response, 'kelas');
        $this->assertStringContainsString('12-1', $kelas);
        $this->assertStringNotContainsString('11-1', $kelas);
        $this->assertStringNotContainsString('Fisika', $response->getContent());
    }

    public function test_perkembangan_memakai_komponen_bar_chart(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');
        $this->nilai($this->siswa('12-1', 'Ani Lestari'), $mapel, 88);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.index'))
            ->assertOk()
            ->assertSee('guru-perkembangan', false)
            ->assertSee('data-bar-chart', false)
            ->assertSee('data-tooltip', false)
            // Batang dibuat mengisi lebar kolom agar kartu tidak kosong.
            ->assertSee('max-w-full', false)
            ->assertSee('Ketuk batang untuk melihat detail');
    }

    public function test_rekap_per_kelas_menampilkan_semua_kelas_ampu(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');

        $this->nilai($this->siswa('12-1', 'Siswa Dua Belas', self::JURUSAN), $mapel, 90);
        $this->nilai($this->siswa('11-1', 'Siswa Sebelas', self::JURUSAN_LAIN), $mapel, 70);

        $kelas = $this->region(
            $this->actingAs($guru, 'guru')->get(route('dashboard.guru.index')),
            'kelas',
        );

        $this->assertStringContainsString('12-1', $kelas);
        $this->assertStringContainsString('11-1', $kelas);
    }

    public function test_siswa_perlu_perhatian_menampilkan_yang_belum_tuntas(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');

        $this->nilai($this->siswa('12-1', 'Ani Lestari'), $mapel, 90);
        $this->nilai($this->siswa('12-1', 'Budi Santoso'), $mapel, 55);

        $perhatian = $this->region(
            $this->actingAs($guru, 'guru')->get(route('dashboard.guru.index')),
            'perhatian',
        );

        // Budi belum tuntas, jadi harus muncul. Ani sudah tuntas, jadi tidak.
        $this->assertStringContainsString('Budi Santoso', $perhatian);
        $this->assertStringNotContainsString('Ani Lestari', $perhatian);
    }

    public function test_perlu_perhatian_berpaginasi_lima_per_halaman(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');

        for ($i = 1; $i <= 6; $i++) {
            // Semua di bawah KKM, diurutkan rata-rata terendah lebih dulu.
            $this->nilai($this->siswa('12-'.$i, 'Siswa '.$i), $mapel, 50 + $i);
        }

        $halamanSatu = $this->region(
            $this->actingAs($guru, 'guru')->get(route('dashboard.guru.index')),
            'perhatian',
        );
        $this->assertStringContainsString('Menampilkan 1–5 dari 6 siswa', $halamanSatu);
        $this->assertStringContainsString('Siswa 1', $halamanSatu);
        $this->assertStringNotContainsString('Siswa 6', $halamanSatu);

        // Parameter halaman khusus supaya tidak bentrok dengan tabel lain.
        $halamanDua = $this->region(
            $this->actingAs($guru, 'guru')->get(route('dashboard.guru.index', ['perlu_perhatian_page' => 2])),
            'perhatian',
        );
        $this->assertStringContainsString('Menampilkan 6–6 dari 6 siswa', $halamanDua);
        $this->assertStringContainsString('Siswa 6', $halamanDua);
    }

    public function test_perlu_perhatian_tanpa_footer_paginasi_saat_data_kurang(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');
        $this->nilai($this->siswa('12-1', 'Budi Santoso'), $mapel, 55);

        $html = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.index'))
            ->assertOk()
            ->getContent();

        $mulai = strpos($html, 'data-region="perhatian"');
        $this->assertNotFalse($mulai, 'Region [perhatian] tidak ditemukan.');
        $selesai = strpos($html, 'data-region="', $mulai + 1);
        $region = $selesai === false ? substr($html, $mulai) : substr($html, $mulai, $selesai - $mulai);

        // Hanya satu halaman, jadi footer paginasi tidak perlu dirender.
        $this->assertStringNotContainsString('data-table-footer', $region);
    }

    public function test_non_guru_tidak_bisa_membuka_dashboard_guru(): void
    {
        foreach (['admin', 'siswa'] as $role) {
            $user = $role === 'admin'
                ? User::factory()->admin()->create()
                : User::factory()->siswa('12-1')->create();

            $this->actingAs($user)
                ->get(route('dashboard.guru.index'))
                ->assertForbidden();
        }
    }

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get(route('dashboard.guru.index'))
            ->assertRedirect(route('login'));
    }
}
