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

class LaporanAkademikTest extends TestCase
{
    use InteractsWithSpreadsheet;
    use RefreshDatabase;

    private const JURUSAN = 'Rekayasa Perangkat Lunak';

    private const JURUSAN_LAIN = 'Teknik Komputer Jaringan';

    /**
     * Ambil teks yang benar-benar terlihat di satu region halaman.
     *
     * Class Tailwind penuh angka (mis. bg-slate-50/60) dan konfigurasi chart
     * bukan data laporan, jadi keduanya dibuang. Dengan begitu pemeriksaan
     * "nilai ini tidak boleh muncul" tidak salah positif.
     */
    private function region(TestResponse $response, string $nama): string
    {
        $html = $response->assertOk()->getContent();
        $mulai = strpos($html, 'data-region="'.$nama.'"');

        $this->assertNotFalse($mulai, "Region [{$nama}] tidak ditemukan di halaman laporan.");

        $selesai = strpos($html, 'data-region="', $mulai + 1);
        $html = $selesai === false
            ? substr($html, $mulai)
            : substr($html, $mulai, $selesai - $mulai);

        $teks = preg_replace('/<script\b[^>]*>.*?<\/script>/s', ' ', $html);
        $teks = preg_replace('/<[^>]+>/', ' ', (string) $teks);
        $teks = html_entity_decode($teks, ENT_QUOTES | ENT_HTML5);

        return trim((string) preg_replace('/\s+/', ' ', $teks));
    }

    public function test_admin_can_access_laporan_akademik_page(): void
    {
        $admin = User::factory()->admin()->create();
        $periode = Periode::periodeSekarang();
        $mapel = MataPelajaran::factory()->create(['nama' => 'Matematika']);
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => self::JURUSAN]);

        Nilai::factory()->siswa($siswa)->mataPelajaran($mapel)
            ->periode($periode['tahun_ajaran'], $periode['semester'])
            ->nilai(85)->create();

        // Dengan nilai ada, seluruh bagian laporan harus muncul.
        $this->actingAs($admin)
            ->get(route('dashboard.admin.laporan-akademik.index', [
                'semester' => $periode['semester'],
                'tahun_ajaran' => $periode['tahun_ajaran'],
            ]))
            ->assertOk()
            ->assertSee('Laporan Akademik')
            ->assertSee('Rata-Rata Nilai per Kelas')
            ->assertSee('Rekap Nilai per Kelas')
            ->assertSee('Rekap Nilai per Mata Pelajaran')
            ->assertSee('Tingkat Ketuntasan (KKM)')
            ->assertSee('Perkembangan Rata-Rata per Semester');
    }

    /**
     * Grafik perkembangan hanya menampilkan periode yang benar-benar punya
     * nilai. Semester berikutnya yang belum diisi tidak boleh ikut tampil
     * sebagai titik kosong (mis. "Genap 2025/2026" saat baru ada data Ganjil).
     */
    public function test_grafik_perkembangan_tidak_menambah_periode_tanpa_nilai(): void
    {
        $admin = User::factory()->admin()->create();
        $mapel = MataPelajaran::factory()->kode('MTK-01')->create(['nama' => 'Matematika']);
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => self::JURUSAN]);

        Nilai::factory()->siswa($siswa)->mataPelajaran($mapel)
            ->periode('2025/2026', Periode::SEMESTER_GANJIL)
            ->nilai(85)->create();

        $this->actingAs($admin)
            ->get(route('dashboard.admin.laporan-akademik.index'))
            ->assertOk()
            // Label dikirim lewat @json, jadi garis miring ikut di-escape.
            ->assertSee('Ganjil 2025\/2026')
            ->assertDontSee('Genap 2025\/2026');
    }

    /**
     * Setelah `db:seed`, tabel nilai harus sudah terisi sehingga Laporan
     * Akademik tidak hanya menampilkan keterangan kosong. Laporan ini pernah
     * selalu kosong tanpa disadari karena tidak ada seeder yang mengisi tabel
     * nilai, jadi test inilah yang menjaganya.
     */
    public function test_seeder_mengisi_laporan_akademik(): void
    {
        $admin = User::factory()->admin()->create();

        $this->seed();

        $response = $this->actingAs($admin)->get(route('dashboard.admin.laporan-akademik.index'));

        $response->assertOk()
            ->assertDontSee('Belum ada nilai pada rentang ini')
            ->assertSee('Rekap Nilai per Kelas')
            ->assertSee('Rekap Nilai per Mata Pelajaran')
            ->assertSee('Tingkat Ketuntasan (KKM)')
            ->assertSee('<canvas id="chartNilaiKelas">', false)
            ->assertDontSee('dari 0 nilai tercatat');
    }

    public function test_non_admin_cannot_access_laporan_akademik(): void
    {
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)
            ->get(route('dashboard.admin.laporan-akademik.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard.admin.laporan-akademik.index'))
            ->assertRedirect(route('login'));
    }

    /**
     * Filter memakai kelas angka dan jurusan terpisah, sama seperti Data Kelas
     * dan Data Siswa. "XII RPL" bukan lagi cara menulis kelas di sistem ini.
     */
    public function test_admin_can_filter_laporan_akademik_by_params(): void
    {
        $admin = User::factory()->admin()->create();
        $periode = Periode::periodeSekarang();
        $mapel = MataPelajaran::factory()->kode('MTK-01')->create(['nama' => 'Matematika']);

        $siswaDipilih = User::factory()->siswa('12-1')->create([
            'jurusan' => self::JURUSAN,
            'status' => 'Aktif',
        ]);
        Nilai::factory()->siswa($siswaDipilih)->mataPelajaran($mapel)
            ->periode($periode['tahun_ajaran'], $periode['semester'])
            ->nilai(88)->create();

        // Kelas dan jurusan lain yang harus tetap tersaring keluar.
        $siswaLain = User::factory()->siswa('11-1')->create(['jurusan' => self::JURUSAN_LAIN]);
        Nilai::factory()->siswa($siswaLain)->mataPelajaran($mapel)
            ->periode($periode['tahun_ajaran'], $periode['semester'])
            ->nilai(45)->create();

        $response = $this->actingAs($admin)->get(route('dashboard.admin.laporan-akademik.index', [
            'semester' => $periode['semester'],
            'tahun_ajaran' => $periode['tahun_ajaran'],
            'jurusan' => self::JURUSAN,
            'kelas' => '12-1',
        ]));

        $response->assertOk()
            ->assertSee($periode['tahun_ajaran'])
            ->assertSee('12-1')
            ->assertSee(self::JURUSAN);

        $rekap = $this->region($response, 'rekap');

        $this->assertStringContainsString('88', $rekap);
        // 45 dari kelas dan jurusan lain tidak boleh ikut masuk tabel.
        $this->assertStringNotContainsString('45', $rekap);
        $this->assertStringNotContainsString(self::JURUSAN_LAIN, $rekap);
    }

    /** Kelas dinormalkan, jadi "12 - 1" tetap cocok dengan kelas "12-1". */
    public function test_filter_kelas_mentoler_spasi_di_sekitar_tanda_hubung(): void
    {
        $admin = User::factory()->admin()->create();
        $periode = Periode::periodeSekarang();
        $mapel = MataPelajaran::factory()->create(['nama' => 'Matematika']);
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => self::JURUSAN]);

        Nilai::factory()->siswa($siswa)->mataPelajaran($mapel)
            ->periode($periode['tahun_ajaran'], $periode['semester'])
            ->nilai(91)->create();

        $this->actingAs($admin)
            ->get(route('dashboard.admin.laporan-akademik.index', [
                'semester' => $periode['semester'],
                'tahun_ajaran' => $periode['tahun_ajaran'],
                'kelas' => '12 - 1',
            ]))
            ->assertOk()
            ->assertSee('91');
    }

    /** Semester lain tidak boleh bocor ke laporan periode berjalan. */
    public function test_filter_semester_terpisah_antar_periode(): void
    {
        $admin = User::factory()->admin()->create();
        $mapel = MataPelajaran::factory()->kode('MTK-01')->create(['nama' => 'Matematika']);
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => self::JURUSAN]);

        Nilai::factory()->siswa($siswa)->mataPelajaran($mapel)
            ->periode('2024/2025', Periode::SEMESTER_GANJIL)->nilai(60)->create();
        Nilai::factory()->siswa($siswa)->mataPelajaran($mapel)
            ->periode('2024/2025', Periode::SEMESTER_GENAP)->nilai(95)->create();

        $response = $this->actingAs($admin)->get(route('dashboard.admin.laporan-akademik.index', [
            'semester' => Periode::SEMESTER_GANJIL,
            'tahun_ajaran' => '2024/2025',
        ]));

        // Grafik perkembangan memang lintas periode, jadi 95 boleh muncul
        // di sana. Yang dijaga adalah tabel rekap periode berjalan saja.
        $rekap = $this->region($response, 'rekap');

        $this->assertStringContainsString('60', $rekap);
        $this->assertStringNotContainsString('95', $rekap);
    }

    /**
     * Rata-rata dihitung dari semua nilai yang tercatat. Kelas dengan satu
     * nilai tidak boleh memberi bobot sama dengan kelas dengan dua nilai.
     */
    public function test_rata_rata_menghitung_seluruh_nilai_bukan_rata_rata_antar_kelas(): void
    {
        $admin = User::factory()->admin()->create();
        $periode = Periode::periodeSekarang();
        $mapel = MataPelajaran::factory()->create(['nama' => 'Matematika']);

        // Kelas A: dua siswa, rata-rata 80.
        $a1 = User::factory()->siswa('12-1')->create(['jurusan' => self::JURUSAN]);
        $a2 = User::factory()->siswa('12-1')->create(['jurusan' => self::JURUSAN]);
        // Kelas B: satu siswa, rata-rata 40.
        $b1 = User::factory()->siswa('11-1')->create(['jurusan' => self::JURUSAN]);

        foreach ([[$a1, 80], [$a2, 80], [$b1, 40]] as [$siswa, $nilai]) {
            Nilai::factory()->siswa($siswa)->mataPelajaran($mapel)
                ->periode($periode['tahun_ajaran'], $periode['semester'])
                ->nilai($nilai)->create();
        }

        // (80 + 80 + 40) / 3 = 66,7. Kalau dirata-rata antar kelas jadi 60.
        $this->actingAs($admin)
            ->get(route('dashboard.admin.laporan-akademik.index', [
                'semester' => $periode['semester'],
                'tahun_ajaran' => $periode['tahun_ajaran'],
            ]))
            ->assertOk()
            ->assertSee('66,7')
            ->assertDontSee('>60</span>');
    }

    /** Kelas yang sama dipakai dua jurusan harus tetap terpisah. */
    public function test_kelas_yang_sama_di_jurusan_berbeda_tidak_tercampur(): void
    {
        $admin = User::factory()->admin()->create();
        $periode = Periode::periodeSekarang();
        $mapel = MataPelajaran::factory()->kode('MTK-01')->create(['nama' => 'Matematika']);

        $rpl = User::factory()->siswa('12-1')->create(['jurusan' => self::JURUSAN]);
        $tkj = User::factory()->siswa('12-1')->create(['jurusan' => self::JURUSAN_LAIN]);

        Nilai::factory()->siswa($rpl)->mataPelajaran($mapel)
            ->periode($periode['tahun_ajaran'], $periode['semester'])->nilai(90)->create();
        Nilai::factory()->siswa($tkj)->mataPelajaran($mapel)
            ->periode($periode['tahun_ajaran'], $periode['semester'])->nilai(50)->create();

        $rekap = $this->region(
            $this->actingAs($admin)->get(route('dashboard.admin.laporan-akademik.index', [
                'semester' => $periode['semester'],
                'tahun_ajaran' => $periode['tahun_ajaran'],
                'jurusan' => self::JURUSAN,
                'kelas' => '12-1',
            ])),
            'rekap',
        );

        $this->assertStringContainsString('90', $rekap);
        // Kelas sama, jurusan lain: nilai 50 tidak boleh ikut terhitung.
        $this->assertStringNotContainsString('50', $rekap);
        $this->assertStringNotContainsString(self::JURUSAN_LAIN, $rekap);
    }

    /** KKM berasal dari baris nilai, jadi ambang per mapel ikut terhitung. */
    public function test_ketuntasan_dihitung_terhadap_kkm_pada_baris_nilai(): void
    {
        $admin = User::factory()->admin()->create();
        $periode = Periode::periodeSekarang();
        $mapel = MataPelajaran::factory()->create(['nama' => 'Matematika']);

        $tuntas = User::factory()->siswa('12-1')->create(['jurusan' => self::JURUSAN]);
        $belum = User::factory()->siswa('12-1')->create(['jurusan' => self::JURUSAN]);

        Nilai::factory()->siswa($tuntas)->mataPelajaran($mapel)
            ->periode($periode['tahun_ajaran'], $periode['semester'])
            ->nilai(90)->kkm(75)->create();
        Nilai::factory()->siswa($belum)->mataPelajaran($mapel)
            ->periode($periode['tahun_ajaran'], $periode['semester'])
            ->nilai(60)->kkm(75)->create();

        // Dua nilai tercatat, satu tuntas satu belum: 50%.
        $this->actingAs($admin)
            ->get(route('dashboard.admin.laporan-akademik.index', [
                'semester' => $periode['semester'],
                'tahun_ajaran' => $periode['tahun_ajaran'],
            ]))
            ->assertOk()
            ->assertSee('50%');
    }

    /**
     * KKM dihitung guru per kelas per mata pelajaran, jadi ambang berbeda
     * harus menghasilkan ketuntasan yang berbeda untuk nilai yang sama.
     */
    public function test_kkm_berbeda_menghasilkan_ketuntasan_berbeda(): void
    {
        $admin = User::factory()->admin()->create();
        $periode = Periode::periodeSekarang();
        $mapel = MataPelajaran::factory()->create(['nama' => 'Matematika']);

        $longgar = User::factory()->siswa('12-1')->create(['jurusan' => self::JURUSAN]);
        $ketat = User::factory()->siswa('12-1')->create(['jurusan' => self::JURUSAN]);

        // Nilai sama persis, ambang berbeda.
        Nilai::factory()->siswa($longgar)->mataPelajaran($mapel)
            ->periode($periode['tahun_ajaran'], $periode['semester'])
            ->nilai(70)->kkm(65)->create();
        Nilai::factory()->siswa($ketat)->mataPelajaran($mapel)
            ->periode($periode['tahun_ajaran'], $periode['semester'])
            ->nilai(70)->kkm(80)->create();

        // Satu tuntas satu belum: 50%.
        $this->actingAs($admin)
            ->get(route('dashboard.admin.laporan-akademik.index', [
                'semester' => $periode['semester'],
                'tahun_ajaran' => $periode['tahun_ajaran'],
            ]))
            ->assertOk()
            ->assertSee('50%');
    }

    /** Nilai tepat di KKM dihitung tuntas. */
    public function test_nilai_sama_dengan_kkm_terhitung_tuntas(): void
    {
        $admin = User::factory()->admin()->create();
        $periode = Periode::periodeSekarang();
        $mapel = MataPelajaran::factory()->create(['nama' => 'Matematika']);
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => self::JURUSAN]);

        Nilai::factory()->siswa($siswa)->mataPelajaran($mapel)
            ->periode($periode['tahun_ajaran'], $periode['semester'])
            ->nilai(75)->kkm(75)->create();

        $this->actingAs($admin)
            ->get(route('dashboard.admin.laporan-akademik.index', [
                'semester' => $periode['semester'],
                'tahun_ajaran' => $periode['tahun_ajaran'],
            ]))
            ->assertOk()
            ->assertSee('100%');
    }

    /**
     * Tanpa nilai, laporan harus kosong dan jujur. Angka perkiraan tidak
     * boleh muncul, karena akan disalahartikan sebagai hasil belajar siswa.
     */
    public function test_laporan_tanpa_nilai_tidak_menampilkan_angka_perkiraan(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->siswa('12-1')->create(['jurusan' => self::JURUSAN]);

        $html = $this->actingAs($admin)
            ->get(route('dashboard.admin.laporan-akademik.index'))
            ->assertOk()
            ->assertSee('Belum ada nilai pada rentang ini')
            ->assertSee('Belum ada nilai')
            ->getContent();

        // Kartu ringkasan dan seluruh grafik harus kosong, bukan diisi angka.
        $this->assertStringContainsString('dari 0 nilai tercatat', $html);
        $this->assertStringNotContainsString('Rata-rata Tertinggi', $html);
        $this->assertStringNotContainsString('Rata-rata Terendah', $html);
        $this->assertStringNotContainsString('Rekap Nilai per Kelas', $html);
        $this->assertStringNotContainsString('Rekap Nilai per Mata Pelajaran', $html);
        $this->assertStringNotContainsString('Tingkat Ketuntasan (KKM)', $html);
        // Kanvas grafik tidak boleh dirender kalau tidak ada data.
        $this->assertStringNotContainsString('<canvas id="chartNilaiKelas">', $html);
        $this->assertStringNotContainsString('<canvas id="chartPerkembangan">', $html);
        $this->assertStringNotContainsString('<canvas id="chartKetuntasan">', $html);
    }

    /** Tahun ajaran yang tidak sah jatuh ke periode berjalan, bukan periode karangan. */
    public function test_periode_tidak_sah_kembali_ke_periode_berjalan(): void
    {
        $admin = User::factory()->admin()->create();
        $periode = Periode::periodeSekarang();

        $this->actingAs($admin)
            ->get(route('dashboard.admin.laporan-akademik.index', [
                'semester' => 'Musiman',
                'tahun_ajaran' => '2025/2027',
            ]))
            ->assertOk()
            ->assertSee($periode['semester'])
            ->assertSee($periode['tahun_ajaran']);
    }

    /** Kelas diurutkan secara angka, bukan sebagai teks. */
    public function test_kelas_diurutkan_secara_angka(): void
    {
        $admin = User::factory()->admin()->create();
        $periode = Periode::periodeSekarang();
        $mapel = MataPelajaran::factory()->create(['nama' => 'Matematika']);

        foreach (['12-2', '12-10', '12-1'] as $kelas) {
            $siswa = User::factory()->siswa($kelas)->create(['jurusan' => self::JURUSAN]);
            Nilai::factory()->siswa($siswa)->mataPelajaran($mapel)
                ->periode($periode['tahun_ajaran'], $periode['semester'])
                ->nilai(80)->create();
        }

        $html = $this->actingAs($admin)
            ->get(route('dashboard.admin.laporan-akademik.index', [
                'semester' => $periode['semester'],
                'tahun_ajaran' => $periode['tahun_ajaran'],
            ]))
            ->assertOk()
            ->getContent();

        // "12-10" salah urut kalau kelas dibandingkan sebagai teks.
        $posisi = [
            '12-1' => strpos($html, '>12-1<'),
            '12-2' => strpos($html, '>12-2<'),
            '12-10' => strpos($html, '>12-10<'),
        ];

        $this->assertNotFalse($posisi['12-1']);
        $this->assertNotFalse($posisi['12-2']);
        $this->assertNotFalse($posisi['12-10']);
        $this->assertLessThan($posisi['12-2'], $posisi['12-1']);
        $this->assertLessThan($posisi['12-10'], $posisi['12-2']);
    }

    /** Grafik perkembangan harus lintas periode, bukan satu titik. */
    public function test_perkembangan_menampilkan_lewat_dua_periode(): void
    {
        $admin = User::factory()->admin()->create();
        $periode = Periode::periodeSekarang();
        $mapel = MataPelajaran::factory()->create(['nama' => 'Matematika']);
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => self::JURUSAN]);

        Nilai::factory()->siswa($siswa)->mataPelajaran($mapel)
            ->periode('2024/2025', Periode::SEMESTER_GENAP)->nilai(70)->create();
        Nilai::factory()->siswa($siswa)->mataPelajaran($mapel)
            ->periode('2025/2026', Periode::SEMESTER_GANJIL)->nilai(85)->create();

        $html = $this->actingAs($admin)
            ->get(route('dashboard.admin.laporan-akademik.index', [
                'semester' => $periode['semester'],
                'tahun_ajaran' => $periode['tahun_ajaran'],
            ]))
            ->assertOk()
            ->getContent();

        // Dua periode harus muncul sebagai label titik grafik.
        $this->assertStringContainsString('Genap 2024\/2025', $html);
        $this->assertStringContainsString('Ganjil 2025\/2026', $html);
    }

    /** Guru pengampu diambil dari akun guru, bukan pasangan berurutan. */
    public function test_guru_pengampus_diambil_dari_akun_guru(): void
    {
        $admin = User::factory()->admin()->create();
        $periode = Periode::periodeSekarang();
        $mapel = MataPelajaran::factory()->create(['nama' => 'Matematika']);
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => self::JURUSAN]);

        User::factory()->guru()->create([
            'name' => 'Guru Matematika Sejati',
            'mata_pelajaran' => 'Matematika',
        ]);

        Nilai::factory()->siswa($siswa)->mataPelajaran($mapel)
            ->periode($periode['tahun_ajaran'], $periode['semester'])
            ->nilai(85)->create();

        $this->actingAs($admin)
            ->get(route('dashboard.admin.laporan-akademik.index', [
                'semester' => $periode['semester'],
                'tahun_ajaran' => $periode['tahun_ajaran'],
            ]))
            ->assertOk()
            ->assertSee('Guru Matematika Sejati');
    }

    /** Mata pelajaran tanpa guru pengampu dinyatakan terbuka, bukan diisi nama lain. */
    public function test_mapel_tanpa_guru_pengampus_disebut_terbuka(): void
    {
        $admin = User::factory()->admin()->create();
        $periode = Periode::periodeSekarang();
        $mapel = MataPelajaran::factory()->create(['nama' => 'Matematika']);
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => self::JURUSAN]);

        Nilai::factory()->siswa($siswa)->mataPelajaran($mapel)
            ->periode($periode['tahun_ajaran'], $periode['semester'])
            ->nilai(85)->create();

        $this->actingAs($admin)
            ->get(route('dashboard.admin.laporan-akademik.index', [
                'semester' => $periode['semester'],
                'tahun_ajaran' => $periode['tahun_ajaran'],
            ]))
            ->assertOk()
            ->assertSee('Belum ada guru pengampu');
    }

    public function test_admin_can_export_rekap_kelas(): void
    {
        $admin = User::factory()->admin()->create();
        $periode = Periode::periodeSekarang();
        $mapel = MataPelajaran::factory()->create(['nama' => 'Matematika']);
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => self::JURUSAN]);

        Nilai::factory()->siswa($siswa)->mataPelajaran($mapel)
            ->periode($periode['tahun_ajaran'], $periode['semester'])
            ->nilai(87)->create();

        $response = $this->actingAs($admin)->get(route('dashboard.admin.laporan-akademik.export-kelas', [
            'semester' => $periode['semester'],
            'tahun_ajaran' => $periode['tahun_ajaran'],
        ]));

        $response->assertOk()
            ->assertHeader('X-Total-Baris', '1');

        // Nama file ditempeli jam dari waktu request, jadi tidak boleh
        // dibandingkan persis dengan now() yang dievaluasi setelahnya.
        $this->assertMatchesRegularExpression(
            '/^attachment; filename="rekap-nilai-kelas-\d{8}-\d{6}\.xlsx"$/',
            (string) $response->headers->get('content-disposition')
        );

        $baris = $this->bukaLembar($response)->toArray();

        $this->assertSame('Rekap Nilai per Kelas', $baris[0][0]);
        $this->assertSame('Kelas', $baris[2][1]);
        $this->assertSame('12-1', $baris[3][1]);
        $this->assertSame(87.0, (float) $baris[3][5]);
    }

    public function test_admin_can_export_rekap_mata_pelajaran(): void
    {
        $admin = User::factory()->admin()->create();
        $periode = Periode::periodeSekarang();
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => self::JURUSAN]);

        Nilai::factory()->siswa($siswa)->mataPelajaran($mapel)
            ->periode($periode['tahun_ajaran'], $periode['semester'])
            ->nilai(76)->create();

        $response = $this->actingAs($admin)->get(route('dashboard.admin.laporan-akademik.export-mata-pelajaran', [
            'semester' => $periode['semester'],
            'tahun_ajaran' => $periode['tahun_ajaran'],
        ]));

        $response->assertOk()->assertHeader('X-Total-Baris', '1');

        $baris = $this->bukaLembar($response)->toArray();

        $this->assertSame('Matematika', $baris[3][2]);
        $this->assertSame('Belum ada guru pengampu', $baris[3][3]);
        $this->assertSame(76.0, (float) $baris[3][6]);
    }

    public function test_tidak_bisa_export_laporan_akademik(): void
    {
        $siswa = User::factory()->siswa('12-1')->create();

        $this->actingAs($siswa)
            ->get(route('dashboard.admin.laporan-akademik.export-kelas'))
            ->assertForbidden();
    }

    /** Nilai disimpan terpisah per periode, jadi input ulang tidak menambah baris. */
    public function test_satu_siswa_satu_nilai_per_mapel_per_periode(): void
    {
        $periode = Periode::periodeSekarang();
        $mapel = MataPelajaran::factory()->create(['nama' => 'Matematika']);
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => self::JURUSAN]);

        Nilai::factory()->siswa($siswa)->mataPelajaran($mapel)
            ->periode($periode['tahun_ajaran'], $periode['semester'])
            ->nilai(70)->create();

        $ini = Nilai::query()
            ->where('user_id', $siswa->id)
            ->where('mata_pelajaran_id', $mapel->id)
            ->where('tahun_ajaran', $periode['tahun_ajaran'])
            ->where('semester', $periode['semester'])
            ->first();

        $ini->update(['nilai' => 92]);

        $this->assertSame(1, Nilai::query()->where('user_id', $siswa->id)->count());
        $this->assertSame(92.0, (float) $ini->fresh()->nilai);
    }

    /** Kelas di baris nilai dinormalkan, sama seperti di Data Kelas. */
    public function test_kelas_pada_nilai_selalu_dinormalkan(): void
    {
        $periode = Periode::periodeSekarang();
        $mapel = MataPelajaran::factory()->create(['nama' => 'Matematika']);
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => self::JURUSAN]);

        $nilai = Nilai::factory()->siswa($siswa)->mataPelajaran($mapel)
            ->kelas('12 - 1', self::JURUSAN)
            ->periode($periode['tahun_ajaran'], $periode['semester'])
            ->create();

        $this->assertSame('12-1', $nilai->kelas);
    }
}
