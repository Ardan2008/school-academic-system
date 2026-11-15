<?php

namespace Tests\Feature;

use App\Http\Controllers\Guru\InputNilaiController;
use App\Models\MataPelajaran;
use App\Models\Nilai;
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
            $payload['kelas'],
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
}
