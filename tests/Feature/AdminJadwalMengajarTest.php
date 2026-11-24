<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\JadwalPelajaran;
use App\Models\JamBelajarKonfigurasi;
use App\Models\KelasRombel;
use App\Models\MataPelajaran;
use App\Models\SesiJamBelajar;
use App\Models\User;
use App\Support\Kelas;
use App\Support\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSpreadsheet;
use Tests\TestCase;

class AdminJadwalMengajarTest extends TestCase
{
    use InteractsWithSpreadsheet;
    use RefreshDatabase;

    private const JURUSAN = 'Rekayasa Perangkat Lunak';

    private function admin(): User
    {
        return User::factory()->admin()->create([
            'name' => 'Admin Jadwal',
            'must_change_password' => false,
        ]);
    }

    private function guru(string $mapel = 'Matematika', string $nama = 'Guru Pengampu'): User
    {
        return User::factory()->guru()->create([
            'name' => $nama,
            'mata_pelajaran' => $mapel,
            'must_change_password' => false,
        ]);
    }

    private function siswa(string $kelas = '12-1', ?string $jurusan = self::JURUSAN): User
    {
        KelasRombel::query()->firstOrCreate([
            'kelas' => Kelas::normalize($kelas),
            'jurusan' => trim((string) $jurusan),
        ]);

        return User::factory()->siswa($kelas)->create([
            'name' => 'Siswa Rombel',
            'jurusan' => $jurusan,
        ]);
    }

    private function sesi(
        string $mulai = '07:00',
        string $selesai = '08:30',
        string $nama = 'Jam Uji',
    ): SesiJamBelajar {
        $konfigurasi = JamBelajarKonfigurasi::query()->firstOrFail();
        $ada = SesiJamBelajar::query()
            ->where('jam_mulai', $mulai)
            ->where('jam_selesai', $selesai)
            ->first();

        if ($ada !== null) {
            return $ada;
        }

        return SesiJamBelajar::create([
            'jam_belajar_konfigurasi_id' => $konfigurasi->id,
            'nama' => $nama,
            'jenis' => SesiJamBelajar::JENIS_PELAJARAN,
            'urutan' => (int) SesiJamBelajar::query()->max('urutan') + 1,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
        ]);
    }

    private function jadwal(
        User $guru,
        string $namaMapel,
        string $hari = 'Senin',
        string $ruang = 'R.101',
        string $kelas = '12-1',
        ?string $jurusan = self::JURUSAN,
        ?SesiJamBelajar $sesi = null,
    ): JadwalPelajaran {
        $mapel = MataPelajaran::factory()->nama($namaMapel)->create();
        $sesi ??= $this->sesi();

        return JadwalPelajaran::create([
            'kelas' => $kelas,
            'jurusan' => $jurusan,
            'hari' => $hari,
            'sesi_id' => $sesi->id,
            'jam_mulai' => substr((string) $sesi->jam_mulai, 0, 5),
            'jam_selesai' => substr((string) $sesi->jam_selesai, 0, 5),
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'ruang' => $ruang,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(MataPelajaran $mapel, User $guru, array $overrides = []): array
    {
        $periode = Periode::periodeSekarang();

        return array_merge([
            'guru_id' => $guru->id,
            'mata_pelajaran_id' => $mapel->id,
            'rombel' => '12-1|'.self::JURUSAN,
            'hari' => 'Senin',
            'sesi_id' => $this->sesi()->id,
            'ruang' => 'R.101',
            'tahun_ajaran' => $periode['tahun_ajaran'],
            'semester' => $periode['semester'],
        ], $overrides);
    }

    public function test_halaman_admin_menampilkan_seluruh_jadwal_dan_kontrol_kelola(): void
    {
        $admin = $this->admin();
        $guruA = $this->guru('Matematika', 'Guru Matematika');
        $guruB = $this->guru('Fisika', 'Guru Fisika');
        $this->siswa();
        $this->jadwal($guruA, 'Matematika', 'Senin', 'R.101');
        $this->jadwal($guruB, 'Fisika', 'Selasa', 'R.102');

        $html = (string) $this->actingAs($admin)
            ->get(route('dashboard.admin.jadwal-mengajar.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-jadwal-page', $html);
        $this->assertStringContainsString('data-role="admin"', $html);
        $this->assertStringContainsString('data-store-url=', $html);
        $this->assertStringContainsString('Guru Matematika', $html);
        $this->assertStringContainsString('Guru Fisika', $html);
        $this->assertStringContainsString('R.101', $html);
        $this->assertStringContainsString('R.102', $html);
    }

    public function test_halaman_admin_memiliki_kontrol_pencarian_live(): void
    {
        $admin = $this->admin();
        $guru = $this->guru('Matematika', 'Guru Matematika');
        $this->siswa();
        $this->jadwal($guru, 'Matematika', 'Senin', 'R.101');

        $html = (string) $this->actingAs($admin)
            ->get(route('dashboard.admin.jadwal-mengajar.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Cari mata pelajaran, guru, kelas, atau ruang...', $html);
        $this->assertStringContainsString('data-cari="utama"', $html);
        $this->assertStringContainsString('data-cari="daftar"', $html);
        $this->assertStringContainsString('data-cari-terapkan', $html);
        $this->assertStringContainsString('data-cari-konten', $html);
        $this->assertStringContainsString('data-cari-kosong-global', $html);
        $this->assertStringContainsString('data-list-row', $html);
        $this->assertMatchesRegularExpression('/data-id="\d+"/', $html);
    }

    public function test_admin_dapat_menambah_jadwal_guru_lain(): void
    {
        $admin = $this->admin();
        $guru = $this->guru();
        $this->siswa();
        $mapel = MataPelajaran::factory()->nama('Matematika')->create();

        $this->actingAs($admin)
            ->post(route('dashboard.admin.jadwal-mengajar.store'), $this->payload($mapel, $guru))
            ->assertRedirect(route('dashboard.admin.jadwal-mengajar.index'));

        $this->assertDatabaseHas('jadwal', [
            'guru_id' => $guru->id,
            'mata_pelajaran_id' => $mapel->id,
            'kelas' => '12-1',
            'jurusan' => self::JURUSAN,
            'hari' => 'Senin',
            'ruang' => 'R.101',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => 'jadwal_penyesuaian',
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_dapat_menambah_jadwal_untuk_periode_lama(): void
    {
        $admin = $this->admin();
        $guru = $this->guru();
        $this->siswa();
        $mapel = MataPelajaran::factory()->nama('Matematika')->create();

        $this->actingAs($admin)
            ->post(route('dashboard.admin.jadwal-mengajar.store'), $this->payload($mapel, $guru, [
                'tahun_ajaran' => '2025/2026',
                'semester' => Periode::SEMESTER_GENAP,
            ]))
            ->assertRedirect(route('dashboard.admin.jadwal-mengajar.index'));

        $this->assertDatabaseHas('jadwal', [
            'guru_id' => $guru->id,
            'mata_pelajaran_id' => $mapel->id,
            'kelas' => '12-1',
            'jurusan' => self::JURUSAN,
            'hari' => 'Senin',
            'tahun_ajaran' => '2025/2026',
            'semester' => Periode::SEMESTER_GENAP,
        ]);
    }

    public function test_admin_menolak_periode_mutasi_yang_tidak_valid(): void
    {
        $admin = $this->admin();
        $guru = $this->guru();
        $this->siswa();
        $mapel = MataPelajaran::factory()->nama('Matematika')->create();

        $this->actingAs($admin)
            ->post(route('dashboard.admin.jadwal-mengajar.store'), $this->payload($mapel, $guru, [
                'tahun_ajaran' => '2025/2027',
                'semester' => 'Musiman',
            ]))
            ->assertSessionHasErrors('tahun_ajaran');

        $this->assertDatabaseCount('jadwal', 0);
    }

    public function test_admin_menolak_guru_nonaktif(): void
    {
        $admin = $this->admin();
        $guru = $this->guru();
        $guru->update(['status' => User::STATUS_NONAKTIF]);
        $this->siswa();
        $mapel = MataPelajaran::factory()->nama('Matematika')->create();

        $this->actingAs($admin)
            ->post(route('dashboard.admin.jadwal-mengajar.store'), $this->payload($mapel, $guru))
            ->assertSessionHasErrors(['guru_id' => 'Guru tidak aktif. Aktifkan akun guru sebelum membuat atau mengubah jadwal.']);

        $this->assertDatabaseCount('jadwal', 0);
    }

    public function test_admin_dapat_mengubah_jadwal_guru_lain(): void
    {
        $admin = $this->admin();
        $guru = $this->guru();
        $this->siswa();
        $jadwal = $this->jadwal($guru, 'Matematika');
        $mapel = MataPelajaran::factory()->nama('Matematika')->create();
        $sesiBaru = $this->sesi('08:30', '10:00', 'Jam Uji 2');

        $this->actingAs($admin)
            ->put(
                route('dashboard.admin.jadwal-mengajar.update', $jadwal),
                $this->payload($mapel, $guru, [
                    'hari' => 'Selasa',
                    'sesi_id' => $sesiBaru->id,
                    'ruang' => 'R.202',
                ])
            )
            ->assertRedirect(route('dashboard.admin.jadwal-mengajar.index'));

        $this->assertDatabaseHas('jadwal', [
            'id' => $jadwal->id,
            'guru_id' => $guru->id,
            'hari' => 'Selasa',
            'jam_mulai' => '08:30',
            'jam_selesai' => '10:00',
            'ruang' => 'R.202',
        ]);
    }

    public function test_admin_dapat_menghapus_jadwal_dan_mengembalikan_json(): void
    {
        $admin = $this->admin();
        $guru = $this->guru();
        $this->siswa();
        $jadwal = $this->jadwal($guru, 'Matematika');

        $this->actingAs($admin)
            ->deleteJson(route('dashboard.admin.jadwal-mengajar.destroy', $jadwal))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $jadwal->id);

        $this->assertDatabaseMissing('jadwal', ['id' => $jadwal->id]);
        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => 'jadwal_penyesuaian',
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_menolak_mapel_yang_tidak_diampu_guru(): void
    {
        $admin = $this->admin();
        $guru = $this->guru('Matematika');
        $this->siswa();
        $mapelLain = MataPelajaran::factory()->nama('Fisika')->create();

        $this->actingAs($admin)
            ->post(
                route('dashboard.admin.jadwal-mengajar.store'),
                $this->payload($mapelLain, $guru)
            )
            ->assertSessionHasErrors(['mata_pelajaran_id' => 'Mata pelajaran ini tidak ditugaskan ke guru pada rombel tersebut.']);

        $this->assertDatabaseCount('jadwal', 0);
    }

    public function test_admin_menolak_jadwal_yang_bentrok(): void
    {
        $admin = $this->admin();
        $guruA = $this->guru('Matematika', 'Guru Matematika');
        $guruB = $this->guru('Fisika', 'Guru Fisika');
        $this->siswa();
        $this->jadwal($guruA, 'Matematika', 'Senin', 'R.101');
        $mapelB = MataPelajaran::factory()->nama('Fisika')->create();

        $this->actingAs($admin)
            ->post(route('dashboard.admin.jadwal-mengajar.store'), $this->payload($mapelB, $guruB))
            ->assertSessionHasErrors('hari');

        $this->assertDatabaseCount('jadwal', 1);
    }

    public function test_intent_cek_bentrok_tidak_menulis_database(): void
    {
        $admin = $this->admin();
        $guru = $this->guru();
        $this->siswa();
        $mapel = MataPelajaran::factory()->nama('Matematika')->create();
        $payload = $this->payload($mapel, $guru);

        $this->actingAs($admin)
            ->postJson(route('dashboard.admin.jadwal-mengajar.intent'), $payload + [
                'intent' => 'cek-bentrok',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.aman', true)
            ->assertJsonPath('data.konflik', []);

        $this->assertDatabaseCount('jadwal', 0);
    }

    public function test_penyesuaian_massal_admin_dapat_dibatalkan(): void
    {
        $admin = $this->admin();
        $guru = $this->guru();
        $this->siswa();
        $jadwal = $this->jadwal($guru, 'Matematika');
        $sesiBaru = $this->sesi('08:30', '10:00', 'Jam Uji 2');

        $respons = $this->actingAs($admin)
            ->postJson(route('dashboard.admin.jadwal-mengajar.intent'), [
                'intent' => 'sesuaikan-massal',
                'perubahan' => [
                    ['id' => $jadwal->id, 'sesi_id' => $sesiBaru->id],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.jumlah', 1);

        $batch = $respons->json('data.batch');

        $this->assertDatabaseHas('jadwal', [
            'id' => $jadwal->id,
            'jam_mulai' => '08:30',
            'jam_selesai' => '10:00',
        ]);

        $this->actingAs($admin)
            ->postJson(route('dashboard.admin.jadwal-mengajar.intent'), [
                'intent' => 'batalkan-penyesuaian',
                'batch' => $batch,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.jumlah', 1);

        $this->assertDatabaseHas('jadwal', [
            'id' => $jadwal->id,
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:30',
        ]);
    }

    public function test_cek_bentrok_mengembalikan_detail_konflik_dan_rekomendasi(): void
    {
        $admin = $this->admin();
        $guru = $this->guru('Matematika', 'Guru Matematika');
        $guruLain = $this->guru('Fisika', 'Guru Fisika');
        $siswa = $this->siswa();
        $this->jadwal($guruLain, 'Fisika', 'Senin', 'R.101');
        $sesi = $this->sesi();
        $sesiAlternatif = $this->sesi('10:30', '11:15', 'Sesi Alternatif');

        $respons = $this->actingAs($admin)
            ->postJson(route('dashboard.admin.jadwal-mengajar.intent'), [
                'intent' => 'cek-bentrok',
                'guru_id' => $guru->id,
                'mata_pelajaran_id' => MataPelajaran::where('nama', 'Matematika')->value('id'),
                'rombel' => $siswa->kelas.'|'.$siswa->jurusan,
                'hari' => 'Senin',
                'sesi_id' => $sesi->id,
                'ruang' => 'R.101',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.aman', false);

        $this->assertNotEmpty($respons->json('data.konflik'));
        $this->assertTrue(collect($respons->json('data.rekomendasi'))->contains(
            fn (array $item) => ($item['nilai']['sesi_id'] ?? null) === $sesiAlternatif->id
        ));
    }

    public function test_store_gagal_menyimpan_periode_dipertahankan_pada_redirect(): void
    {
        $admin = $this->admin();
        $guru = $this->guru();
        $this->siswa();
        $mapel = MataPelajaran::factory()->nama('Matematika')->create();
        $periode = ['tahun_ajaran' => '2024/2025', 'semester' => 'Ganjil'];

        $this->actingAs($admin)
            ->from(route('dashboard.admin.jadwal-mengajar.index', $periode))
            ->post(route('dashboard.admin.jadwal-mengajar.store'), array_merge(
                $this->payload($mapel, $guru),
                $periode,
                ['rombel' => '99-9|Tidak Ada'],
            ))
            ->assertRedirect(route('dashboard.admin.jadwal-mengajar.index', array_merge(['tambah' => 1], $periode)));
    }

    public function test_penyesuaian_massal_menolak_perubahan_yang_mempertahankan_bentrok_lama(): void
    {
        $admin = $this->admin();
        $guru = $this->guru('Matematika', 'Guru Sama');
        $siswaA = $this->siswa('12-1');
        $siswaB = $this->siswa('12-2');
        $mapelA = MataPelajaran::factory()->nama('Matematika')->create();
        $mapelB = MataPelajaran::factory()->nama('Fisika')->create();
        $sesi = $this->sesi();
        $sesiBentrok = $this->sesi('07:15', '08:00', 'Sesi Bentrok');

        $a = JadwalPelajaran::create([
            'guru_id' => $guru->id,
            'mata_pelajaran_id' => $mapelA->id,
            'kelas' => $siswaA->kelas,
            'jurusan' => $siswaA->jurusan,
            'hari' => 'Senin',
            'sesi_id' => $sesi->id,
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:30',
            'ruang' => 'R.101',
            'tahun_ajaran' => Periode::periodeSekarang()['tahun_ajaran'],
            'semester' => Periode::periodeSekarang()['semester'],
        ]);
        $b = JadwalPelajaran::create([
            'guru_id' => $guru->id,
            'mata_pelajaran_id' => $mapelB->id,
            'kelas' => $siswaB->kelas,
            'jurusan' => $siswaB->jurusan,
            'hari' => 'Senin',
            'sesi_id' => $sesi->id,
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:30',
            'ruang' => 'R.102',
            'tahun_ajaran' => Periode::periodeSekarang()['tahun_ajaran'],
            'semester' => Periode::periodeSekarang()['semester'],
        ]);

        $this->actingAs($admin)
            ->postJson(route('dashboard.admin.jadwal-mengajar.intent'), [
                'intent' => 'sesuaikan-massal',
                'tahun_ajaran' => $a->tahun_ajaran,
                'semester' => $a->semester,
                'perubahan' => [
                    ['id' => $a->id, 'sesi_id' => $sesiBentrok->id],
                ],
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('jadwal', ['id' => $a->id, 'sesi_id' => $sesi->id]);
        $this->assertDatabaseHas('jadwal', ['id' => $b->id, 'sesi_id' => $sesi->id]);
    }

    public function test_audit_perubahan_jadwal_admin_tetap_tersimpan(): void
    {
        $admin = $this->admin();
        $guru = $this->guru('Matematika', 'Guru Matematika');
        $this->siswa();
        $mapel = MataPelajaran::factory()->nama('Matematika')->create();

        $this->actingAs($admin)
            ->post(route('dashboard.admin.jadwal-mengajar.store'), $this->payload($mapel, $guru));

        $this->actingAs($admin)
            ->get(route('dashboard.admin.jadwal-mengajar.index'))
            ->assertOk()
            ->assertDontSee('Riwayat Perubahan')
            ->assertDontSee('Aktivitas Jadwal Terbaru')
            ->assertDontSee('Belum ada perubahan jadwal yang tercatat.');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'user_name' => 'Admin Jadwal',
            'activity_type' => ActivityLog::TIPE_JADWAL_PENYESUAIAN,
        ]);
    }

    public function test_export_xlsx_admin_memuat_semua_guru_dan_mengikuti_filter(): void
    {
        $this->admin();
        $guruA = $this->guru('Matematika', 'Guru Matematika');
        $guruB = $this->guru('Fisika', 'Guru Fisika');
        $this->siswa();
        $this->jadwal($guruA, 'Matematika', 'Senin', 'R.101');
        $this->jadwal($guruB, 'Fisika', 'Selasa', 'R.102');

        $semua = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.jadwal-mengajar.export'))
            ->assertOk()
            ->assertHeader('X-Total-Jadwal', '2');
        $isiSemua = $this->gabungBukuKerja($this->bacaBukuKerja($semua));

        $this->assertStringContainsString('Guru Matematika', $isiSemua);
        $this->assertStringContainsString('Guru Fisika', $isiSemua);

        $terfilter = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.jadwal-mengajar.export', [
                'guru_id' => $guruB->id,
                'hari' => 'Selasa',
            ]))
            ->assertOk()
            ->assertHeader('X-Total-Jadwal', '1');
        $isiTerfilter = $this->gabungBukuKerja($this->bacaBukuKerja($terfilter));

        $this->assertStringContainsString('Guru Fisika', $isiTerfilter);
        $this->assertStringNotContainsString('Guru Matematika', $isiTerfilter);
    }

    public function test_non_admin_tidak_dapat_mengakses_halaman_atau_export(): void
    {
        $guru = $this->guru();

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.admin.jadwal-mengajar.index'))
            ->assertForbidden();
        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.admin.jadwal-mengajar.export'))
            ->assertForbidden();
    }
}
