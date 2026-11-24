<?php

namespace Tests\Feature;

use App\Http\Controllers\Guru\InputNilaiController;
use App\Models\KelasRombel;
use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\PenugasanMengajar;
use App\Models\User;
use App\Support\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuruInputNilaiTest extends TestCase
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

    protected function mapel(string $nama = 'Matematika'): MataPelajaran
    {
        return MataPelajaran::factory()->nama($nama)->create();
    }

    protected function siswa(string $kelas, string $jurusan = self::JURUSAN, string $nama = 'Siswa Uji'): User
    {
        return User::factory()->siswa($kelas)->create([
            'jurusan' => $jurusan,
            'name' => $nama,
            'status' => User::STATUS_AKTIF,
        ]);
    }

    /**
     * Ambil payload yang benar-benar dikirim ke browser.
     *
     * @return array<string, mixed>
     */
    protected function payloadHalaman(User $akun): array
    {
        $response = $this->actingAs($akun, 'guru')->get(route('dashboard.guru.input-nilai.index'));

        $response->assertOk();

        $this->assertSame(1, preg_match(
            '/^\s*const data = (.+);$/m',
            $response->getContent(),
            $match
        ), 'Payload inputNilaiData tidak ditemukan di halaman Input Nilai.');

        return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
    }

    protected function payloadNilai(int $userId, float|int|string|null $nilai = 88, float|int|string|null $kkm = 75): array
    {
        return [
            'user_id' => $userId,
            'nilai' => $nilai,
            'kkm' => $kkm,
        ];
    }

    protected function tetapkan(User $guru, MataPelajaran $mapel, KelasRombel $kelas): void
    {
        $guru->forceFill(['penugasan_diatur' => true])->save();

        PenugasanMengajar::create([
            'guru_id' => $guru->id,
            'mata_pelajaran_id' => $mapel->id,
            'kelas_id' => $kelas->id,
        ]);
    }

    public function test_guru_can_access_input_nilai_page_with_own_mapel_and_students(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');
        $this->mapel('Fisika');

        $siswa = $this->siswa('12-1');

        $payload = $this->payloadHalaman($guru);

        // Hanya mapel yang diampu yang boleh muncul, walau ada mapel lain.
        $this->assertCount(1, $payload['mapel']);
        $this->assertSame($mapel->id, $payload['mapel'][0]['id']);
        $this->assertSame('Matematika', $payload['mapel'][0]['nama']);

        // Daftar siswa dan kelas berasal dari siswa aktif.
        $this->assertSame([$siswa->id], array_column($payload['siswa'], 'id'));
        $this->assertContains(
            ['kelas' => '12-1', 'jurusan' => self::JURUSAN],
            $payload['kelas'][(string) $mapel->id],
            'Kelas siswa tidak muncul pada pilihan kelas.'
        );
    }

    public function test_guru_tanpa_mapel_melihat_keterangan_bukan_tabel(): void
    {
        $guru = $this->guru('');
        $this->mapel('Matematika');
        $this->siswa('12-1');

        $response = $this->actingAs($guru, 'guru')->get(route('dashboard.guru.input-nilai.index'));

        $response->assertOk()
            ->assertSee('Mata Pelajaran Belum Ditugaskan')
            ->assertDontSee('Isi KKM Massal');

        $this->assertSame([], $this->payloadHalaman($guru)['mapel']);
    }

    public function test_store_menyimpan_nilai_baru_dengan_kelas_dan_jurusan_siswa(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');
        $siswa = $this->siswa('12-1');
        $periode = Periode::periodeSekarang();

        $this->actingAs($guru, 'guru')
            ->postJson(route('dashboard.guru.input-nilai.store'), [
                'mata_pelajaran_id' => $mapel->id,
                'tahun_ajaran' => $periode['tahun_ajaran'],
                'semester' => $periode['semester'],
                'kelas' => '12-1',
                'jurusan' => self::JURUSAN,
                'nilai' => [$this->payloadNilai($siswa->id, 88, 75)],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.disimpan', 1);

        $this->assertDatabaseHas('nilai', [
            'user_id' => $siswa->id,
            'mata_pelajaran_id' => $mapel->id,
            'kelas' => '12-1',
            'jurusan' => self::JURUSAN,
            'tahun_ajaran' => $periode['tahun_ajaran'],
            'semester' => $periode['semester'],
            'nilai' => 88,
            'kkm' => 75,
        ]);
    }

    public function test_store_kedua_kali_memperbarui_baris_yang_sama(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');
        $siswa = $this->siswa('12-1');
        $periode = Periode::periodeSekarang();

        $kirim = fn (int $nilai) => $this->actingAs($guru, 'guru')
            ->postJson(route('dashboard.guru.input-nilai.store'), [
                'mata_pelajaran_id' => $mapel->id,
                'tahun_ajaran' => $periode['tahun_ajaran'],
                'semester' => $periode['semester'],
                'kelas' => '12-1',
                'jurusan' => self::JURUSAN,
                'nilai' => [$this->payloadNilai($siswa->id, $nilai, 70)],
            ]);

        $kirim(80)->assertOk();
        $kirim(95)->assertOk();

        // Kunci unik (user + mapel + periode) dijaga: tidak boleh ada dua baris.
        $this->assertSame(1, Nilai::query()
            ->where('user_id', $siswa->id)
            ->where('mata_pelajaran_id', $mapel->id)
            ->count());

        $this->assertDatabaseHas('nilai', [
            'user_id' => $siswa->id,
            'mata_pelajaran_id' => $mapel->id,
            'nilai' => 95,
            'kkm' => 70,
        ]);
    }

    public function test_nilai_kosong_menghapus_catatan_yang_sudah_ada(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');
        $siswa = $this->siswa('12-1');
        $periode = Periode::periodeSekarang();

        Nilai::factory()->siswa($siswa)->mataPelajaran($mapel)
            ->periode($periode['tahun_ajaran'], $periode['semester'])
            ->nilai(80)->create();

        $this->actingAs($guru, 'guru')
            ->postJson(route('dashboard.guru.input-nilai.store'), [
                'mata_pelajaran_id' => $mapel->id,
                'tahun_ajaran' => $periode['tahun_ajaran'],
                'semester' => $periode['semester'],
                'kelas' => '12-1',
                'jurusan' => self::JURUSAN,
                'nilai' => [$this->payloadNilai($siswa->id, null, null)],
            ])
            ->assertOk()
            ->assertJsonPath('data.dihapus', 1);

        $this->assertDatabaseMissing('nilai', [
            'user_id' => $siswa->id,
            'mata_pelajaran_id' => $mapel->id,
        ]);
    }

    public function test_store_menolak_nilai_di_luar_rentang(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');
        $siswa = $this->siswa('12-1');
        $periode = Periode::periodeSekarang();

        $this->actingAs($guru, 'guru')
            ->postJson(route('dashboard.guru.input-nilai.store'), [
                'mata_pelajaran_id' => $mapel->id,
                'tahun_ajaran' => $periode['tahun_ajaran'],
                'semester' => $periode['semester'],
                'kelas' => '12-1',
                'jurusan' => self::JURUSAN,
                'nilai' => [$this->payloadNilai($siswa->id, 120, 75)],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nilai.0.nilai');

        $this->assertDatabaseCount('nilai', 0);
    }

    public function test_store_menolak_mapel_yang_tidak_diampu(): void
    {
        $guru = $this->guru('Matematika');
        $this->mapel('Matematika');
        $mapelLain = $this->mapel('Fisika');
        $siswa = $this->siswa('12-1');
        $periode = Periode::periodeSekarang();

        $this->actingAs($guru, 'guru')
            ->postJson(route('dashboard.guru.input-nilai.store'), [
                'mata_pelajaran_id' => $mapelLain->id,
                'tahun_ajaran' => $periode['tahun_ajaran'],
                'semester' => $periode['semester'],
                'kelas' => '12-1',
                'jurusan' => self::JURUSAN,
                'nilai' => [$this->payloadNilai($siswa->id, 80, 75)],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mata_pelajaran_id');

        $this->assertDatabaseCount('nilai', 0);
    }

    public function test_store_menolak_siswa_dari_kelas_lain(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');
        $siswaLain = $this->siswa('11-1', self::JURUSAN_LAIN);
        $periode = Periode::periodeSekarang();

        $this->actingAs($guru, 'guru')
            ->postJson(route('dashboard.guru.input-nilai.store'), [
                'mata_pelajaran_id' => $mapel->id,
                'tahun_ajaran' => $periode['tahun_ajaran'],
                'semester' => $periode['semester'],
                'kelas' => '12-1',
                'jurusan' => self::JURUSAN,
                'nilai' => [$this->payloadNilai($siswaLain->id, 80, 75)],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nilai');

        $this->assertDatabaseCount('nilai', 0);
    }

    public function test_kkm_terisi_otomatis_kalau_tidak_dikirim(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');
        $siswa = $this->siswa('12-1');
        $periode = Periode::periodeSekarang();

        $this->actingAs($guru, 'guru')
            ->postJson(route('dashboard.guru.input-nilai.store'), [
                'mata_pelajaran_id' => $mapel->id,
                'tahun_ajaran' => $periode['tahun_ajaran'],
                'semester' => $periode['semester'],
                'kelas' => '12-1',
                'jurusan' => self::JURUSAN,
                'nilai' => [$this->payloadNilai($siswa->id, 80, null)],
            ])
            ->assertOk();

        $this->assertDatabaseHas('nilai', [
            'user_id' => $siswa->id,
            'mata_pelajaran_id' => $mapel->id,
            'kkm' => InputNilaiController::KKM_BAWAAN,
        ]);
    }

    public function test_non_guru_tidak_bisa_membuka_halaman_input_nilai(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard.guru.input-nilai.index'))
            ->assertForbidden();
    }

    public function test_non_guru_tidak_bisa_menyimpan_nilai(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('dashboard.guru.input-nilai.store'), [])
            ->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get(route('dashboard.guru.input-nilai.index'))
            ->assertRedirect(route('login'));
    }

    public function test_kelas_dropdown_hanya_berisi_kelas_yang_ditugaskan_untuk_mapel_itu(): void
    {
        $guru = $this->guru();
        $matematika = $this->mapel('Matematika');
        $fisika = $this->mapel('Fisika');
        $kelasDuaBelas = KelasRombel::create(['jurusan' => self::JURUSAN, 'kelas' => '12-1']);
        $kelasSebelas = KelasRombel::create(['jurusan' => self::JURUSAN, 'kelas' => '11-1']);
        $this->siswa('12-1', self::JURUSAN, 'Siswa Dua Belas');
        $this->siswa('11-1', self::JURUSAN, 'Siswa Sebelas');

        $this->tetapkan($guru, $matematika, $kelasDuaBelas);
        $this->tetapkan($guru, $fisika, $kelasSebelas);

        $payload = $this->payloadHalaman($guru);

        $this->assertContains(
            ['kelas' => '12-1', 'jurusan' => self::JURUSAN],
            $payload['kelas'][(string) $matematika->id]
        );
        $this->assertNotContains(
            ['kelas' => '11-1', 'jurusan' => self::JURUSAN],
            $payload['kelas'][(string) $matematika->id]
        );
        $this->assertContains(
            ['kelas' => '11-1', 'jurusan' => self::JURUSAN],
            $payload['kelas'][(string) $fisika->id]
        );
        $this->assertNotContains(
            ['kelas' => '12-1', 'jurusan' => self::JURUSAN],
            $payload['kelas'][(string) $fisika->id]
        );
    }

    public function test_guru_terkonfigurasi_ditolak_input_nilai_di_luar_penugasan(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');
        $kelasDitugaskan = KelasRombel::create(['jurusan' => self::JURUSAN, 'kelas' => '12-1']);
        $this->tetapkan($guru, $mapel, $kelasDitugaskan);
        $siswaLain = $this->siswa('11-1', self::JURUSAN, 'Siswa Di Luar Penugasan');
        $periode = Periode::periodeSekarang();

        $this->actingAs($guru, 'guru')
            ->postJson(route('dashboard.guru.input-nilai.store'), [
                'mata_pelajaran_id' => $mapel->id,
                'tahun_ajaran' => $periode['tahun_ajaran'],
                'semester' => $periode['semester'],
                'kelas' => '11-1',
                'jurusan' => self::JURUSAN,
                'nilai' => [$this->payloadNilai($siswaLain->id, 80, 75)],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('kelas');

        $this->assertDatabaseCount('nilai', 0);
    }

    public function test_guru_dengan_penugasan_kosong_tidak_bisa_input_nilai(): void
    {
        $guru = $this->guru('Matematika');
        $mapel = $this->mapel('Matematika');
        $siswa = $this->siswa('12-1');
        $periode = Periode::periodeSekarang();

        $guru->forceFill(['penugasan_diatur' => true])->save();

        $this->actingAs($guru, 'guru')
            ->postJson(route('dashboard.guru.input-nilai.store'), [
                'mata_pelajaran_id' => $mapel->id,
                'tahun_ajaran' => $periode['tahun_ajaran'],
                'semester' => $periode['semester'],
                'kelas' => '12-1',
                'jurusan' => self::JURUSAN,
                'nilai' => [$this->payloadNilai($siswa->id, 80, 75)],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mata_pelajaran_id');

        $this->assertDatabaseCount('nilai', 0);
    }

    public function test_form_input_nilai_menampilkan_mapel_readonly_dari_penugasan(): void
    {
        $guru = $this->guru();
        $mapel = $this->mapel('Matematika');
        $mapelLain = $this->mapel('Fisika');
        $kelas = KelasRombel::create(['jurusan' => self::JURUSAN, 'kelas' => '12-1']);
        $this->siswa('12-1');
        $this->tetapkan($guru, $mapel, $kelas);

        $html = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.input-nilai.index'))
            ->assertOk()
            ->assertDontSee('<select id="nilai-mapel"', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/id="nilai-mapel-tampil"(?=[^>]*value="[^"]*Matematika")(?=[^>]*disabled)(?=[^>]*tabindex="-1")(?=[^>]*cursor-not-allowed)(?=[^>]*select-none)/', $html);
        $this->assertMatchesRegularExpression('/<input type="hidden" id="nilai-mapel" name="mata_pelajaran_id" value="'.$mapel->id.'">/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input type="hidden" id="nilai-mapel" name="mata_pelajaran_id" value="'.$mapelLain->id.'">/', $html);
    }

    public function test_payload_kelas_semua_memetakan_mapel_ke_kelasnya(): void
    {
        $guru = $this->guru();
        $matematika = $this->mapel('Matematika');
        $fisika = $this->mapel('Fisika');
        $kelasDuaBelas = KelasRombel::create(['jurusan' => self::JURUSAN, 'kelas' => '12-1']);
        $kelasSebelas = KelasRombel::create(['jurusan' => self::JURUSAN, 'kelas' => '11-1']);
        $this->siswa('12-1', self::JURUSAN, 'Siswa Dua Belas');
        $this->siswa('11-1', self::JURUSAN, 'Siswa Sebelas');
        $this->tetapkan($guru, $matematika, $kelasDuaBelas);
        $this->tetapkan($guru, $fisika, $kelasSebelas);

        $payload = $this->payloadHalaman($guru);

        $this->assertContains([
            'mata_pelajaran_id' => $matematika->id,
            'mata_pelajaran_nama' => 'Matematika',
            'kelas' => '12-1',
            'jurusan' => self::JURUSAN,
        ], $payload['kelas_semua']);
        $this->assertContains([
            'mata_pelajaran_id' => $fisika->id,
            'mata_pelajaran_nama' => 'Fisika',
            'kelas' => '11-1',
            'jurusan' => self::JURUSAN,
        ], $payload['kelas_semua']);
        $this->assertNotContains([
            'mata_pelajaran_id' => $matematika->id,
            'mata_pelajaran_nama' => 'Matematika',
            'kelas' => '11-1',
            'jurusan' => self::JURUSAN,
        ], $payload['kelas_semua']);
    }

    public function test_input_nilai_memilih_mapel_dari_query_yang_diampu(): void
    {
        $guru = $this->guru();
        $matematika = $this->mapel('Matematika');
        $fisika = $this->mapel('Fisika');
        $kelas = KelasRombel::create(['jurusan' => self::JURUSAN, 'kelas' => '12-1']);
        $this->siswa('12-1');
        $this->tetapkan($guru, $matematika, $kelas);
        $this->tetapkan($guru, $fisika, $kelas);

        $response = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.input-nilai.index', ['mapel_id' => $fisika->id]));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('value="'.$fisika->id.'"', $html);
        $this->assertMatchesRegularExpression('/id="nilai-mapel-tampil"(?=[^>]*value="[^"]*Fisika")/', $html);
    }

    public function test_input_nilai_menolak_query_mapel_yang_tidak_diampu(): void
    {
        $guru = $this->guru('Matematika');
        $this->mapel('Matematika');
        $fisika = $this->mapel('Fisika');
        $this->siswa('12-1');

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.input-nilai.index', ['mapel_id' => $fisika->id]))
            ->assertForbidden();
    }
}
