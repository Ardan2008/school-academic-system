<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\JamBelajarKonfigurasi;
use App\Models\MataPelajaran;
use App\Models\SesiJamBelajar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminJamBelajarTest extends TestCase
{
    use RefreshDatabase;

    protected ?User $adminAccount = null;

    protected function admin(): User
    {
        return $this->adminAccount ??= User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'must_change_password' => false,
        ]);
    }

    protected function konfigurasi(): JamBelajarKonfigurasi
    {
        return JamBelajarKonfigurasi::query()->firstOrFail();
    }

    protected function sesi(string $mulai): SesiJamBelajar
    {
        return SesiJamBelajar::query()->where('jam_mulai', $mulai)->firstOrFail();
    }

    protected function payload(array $override = []): array
    {
        return array_merge([
            'nama' => 'Jam Pelajaran Tambahan',
            'jenis' => SesiJamBelajar::JENIS_PELAJARAN,
            'urutan' => 7,
            'jam_mulai' => '11:00',
            'jam_selesai' => '11:45',
        ], $override);
    }

    protected function buatJadwal(
        User $guru,
        MataPelajaran $mapel,
        SesiJamBelajar $sesi,
        string $hari = 'Senin',
        string $ruang = 'R.101',
    ): JadwalPelajaran {
        return JadwalPelajaran::create([
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'hari' => $hari,
            'sesi_id' => $sesi->id,
            'jam_mulai' => substr((string) $sesi->jam_mulai, 0, 5),
            'jam_selesai' => substr((string) $sesi->jam_selesai, 0, 5),
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'ruang' => $ruang,
        ]);
    }

    public function test_halaman_pengaturan_jam_belajar_tersendiri_menampilkan_pengaturan(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.jam-belajar.index'))
            ->assertOk()
            ->assertSee('Pengaturan Jam Belajar')
            ->assertSee('Jam Operasional Sekolah')
            ->assertSee('Jam Pelajaran 1')
            ->assertSee('Istirahat')
            ->assertSee('06:45')
            ->assertSee('15:00')
            ->assertSee('Jam ke-3')
            ->assertSee('Jam ke-5')
            ->assertDontSee('DATA MASTER')
            ->assertDontSee('PENGELOLAAN SEKOLAH')
            ->assertDontSee('MANAJEMEN');
    }

    public function test_halaman_data_kelas_tidak_lagi_menampilkan_pengaturan_jam_belajar(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-kelas.index'))
            ->assertOk()
            ->assertDontSee('Jam Operasional Sekolah')
            ->assertDontSee('Atur Jam Operasional')
            ->assertDontSee('Tambah Sesi');
    }

    public function test_admin_dapat_mengubah_jam_operasional(): void
    {
        $this->actingAs($this->admin())
            ->putJson(route('dashboard.admin.data-kelas.jam-belajar.operasional.update'), [
                'jam_masuk' => '06:30',
                'jam_selesai' => '15:30',
                'keterangan' => 'Jam efektif sekolah.',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('jam_belajar_konfigurasi', [
            'id' => $this->konfigurasi()->id,
            'jam_masuk' => '06:30',
            'jam_selesai' => '15:30',
            'keterangan' => 'Jam efektif sekolah.',
        ]);
    }

    public function test_jam_operasional_tidak_boleh_membuat_sesi_luar_rentang(): void
    {
        $this->actingAs($this->admin())
            ->putJson(route('dashboard.admin.data-kelas.jam-belajar.operasional.update'), [
                'jam_masuk' => '08:00',
                'jam_selesai' => '14:00',
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['jam_selesai']]);

        $this->assertSame('06:45', substr((string) $this->konfigurasi()->jam_masuk, 0, 5));
    }

    public function test_admin_dapat_menambah_sesi_dan_menolak_waktu_bentrok(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('dashboard.admin.data-kelas.jam-belajar.sesi.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.sesi.waktu', '11:00–11:45')
            ->assertJsonCount(7, 'data.daftar_sesi')
            ->assertJsonFragment(['nama' => 'Jam Pelajaran Tambahan', 'waktu' => '11:00–11:45']);

        $this->assertDatabaseHas('sesi_jam_belajar', [
            'nama' => 'Jam Pelajaran Tambahan',
            'urutan' => 7,
            'jam_mulai' => '11:00',
            'jam_selesai' => '11:45',
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('dashboard.admin.data-kelas.jam-belajar.sesi.store'), $this->payload([
                'nama' => 'Sesi Bentrok',
                'urutan' => 8,
                'jam_mulai' => '08:00',
                'jam_selesai' => '09:30',
            ]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['jam_mulai']]);
    }

    public function test_sesi_tidak_boleh_luar_jam_operasional_atau_urutan_tidak_sesuai_waktu(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('dashboard.admin.data-kelas.jam-belajar.sesi.store'), $this->payload([
                'jam_mulai' => '05:00',
                'jam_selesai' => '06:00',
            ]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['jam_mulai']]);

        $this->actingAs($this->admin())
            ->postJson(route('dashboard.admin.data-kelas.jam-belajar.sesi.store'), $this->payload([
                'urutan' => 1,
                'jam_mulai' => '12:00',
                'jam_selesai' => '12:45',
            ]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['urutan']]);

        $this->assertDatabaseMissing('sesi_jam_belajar', ['nama' => 'Jam Pelajaran Tambahan', 'jam_mulai' => '12:00']);
    }

    public function test_admin_dapat_mengubah_sesi(): void
    {
        $sesi = $this->sesi('06:45');

        $this->actingAs($this->admin())
            ->putJson(route('dashboard.admin.data-kelas.jam-belajar.sesi.update', $sesi), $this->payload([
                'nama' => 'Jam Pelajaran Pertama',
                'urutan' => 6,
                'jam_mulai' => '12:00',
                'jam_selesai' => '12:45',
            ]))
            ->assertOk()
            ->assertJsonPath('data.sesi.nama', 'Jam Pelajaran Pertama');

        $this->assertDatabaseHas('sesi_jam_belajar', [
            'id' => $sesi->id,
            'nama' => 'Jam Pelajaran Pertama',
            'jam_mulai' => '12:00',
            'jam_selesai' => '12:45',
        ]);
        $this->assertSame(
            range(1, 6),
            SesiJamBelajar::query()->orderBy('jam_mulai')->pluck('urutan')->map(fn ($value) => (int) $value)->all()
        );
    }

    public function test_sesi_yang_masih_dipakai_jadwal_tidak_boleh_dihapus(): void
    {
        $sesi = $this->sesi('06:45');
        $guru = User::factory()->guru()->create(['must_change_password' => false]);
        $mapel = MataPelajaran::factory()->nama('Matematika')->create();
        $jadwal = $this->buatJadwal($guru, $mapel, $sesi, 'Senin');

        $this->actingAs($this->admin())
            ->deleteJson(route('dashboard.admin.data-kelas.jam-belajar.sesi.destroy', $sesi))
            ->assertStatus(422)
            ->assertJsonPath('errors.sesi.0', 'Sesi '.$sesi->nama.' masih terhubung dengan 1 jadwal. Sesuaikan atau hapus jadwal terkait terlebih dahulu; sesi tidak dihapus.');

        $this->assertDatabaseHas('sesi_jam_belajar', ['id' => $sesi->id]);
        $this->assertDatabaseHas('jadwal', [
            'id' => $jadwal->id,
            'sesi_id' => $sesi->id,
            'jam_mulai' => '06:45',
            'jam_selesai' => '07:30',
        ]);
    }

    public function test_sesi_yang_belum_dipakai_dapat_dihapus_dan_urutan_rapih(): void
    {
        $sesi = $this->sesi('06:45');

        $this->actingAs($this->admin())
            ->deleteJson(route('dashboard.admin.data-kelas.jam-belajar.sesi.destroy', $sesi))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('sesi_jam_belajar', ['id' => $sesi->id]);
        $this->assertSame(
            range(1, 5),
            SesiJamBelajar::query()->orderBy('urutan')->pluck('urutan')->map(fn ($value) => (int) $value)->all()
        );
    }

    public function test_guru_tidak_dapat_membuka_halaman_pengaturan_jam_belajar(): void
    {
        $guru = User::factory()->guru()->create(['must_change_password' => false]);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.admin.jam-belajar.index'))
            ->assertForbidden();
    }

    public function test_admin_dapat_menonaktifkan_hari_belajar(): void
    {
        $hariAktif = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Sabtu'];

        $this->actingAs($this->admin())
            ->putJson(route('dashboard.admin.data-kelas.jam-belajar.operasional.update'), [
                'jam_masuk' => '06:45',
                'jam_selesai' => '15:00',
                'hari_aktif' => $hariAktif,
            ])
            ->assertOk()
            ->assertJsonPath('data.konfigurasi.hari_aktif', $hariAktif);

        $this->assertSame($hariAktif, $this->konfigurasi()->fresh()->hari_aktif);
    }

    public function test_hari_aktif_tidak_boleh_kosong(): void
    {
        $this->actingAs($this->admin())
            ->putJson(route('dashboard.admin.data-kelas.jam-belajar.operasional.update'), [
                'jam_masuk' => '06:45',
                'jam_selesai' => '15:00',
                'hari_aktif' => [],
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['hari_aktif']]);
    }

    public function test_sesi_istirahat_tidak_menambah_nomor_jam_pelajaran(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('dashboard.admin.data-kelas.jam-belajar.sesi.store'), $this->payload([
                'nama' => 'Istirahat Tambahan',
                'jenis' => SesiJamBelajar::JENIS_ISTIRAHAT,
                'jam_mulai' => '11:00',
                'jam_selesai' => '11:15',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.sesi.jenis', SesiJamBelajar::JENIS_ISTIRAHAT)
            ->assertJsonPath('data.sesi.nomor_pelajaran', 0)
            ->assertJsonPath('data.sesi.urutan', 7);

        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.jam-belajar.index'))
            ->assertSee('Jam ke-5');
    }

    public function test_perubahan_sesi_terpakai_membutuhkan_konfirmasi_dan_menyinkronkan_jadwal(): void
    {
        $sesi = $this->sesi('06:45');
        $guru = User::factory()->guru()->create(['must_change_password' => false]);
        $mapel = MataPelajaran::factory()->nama('Matematika')->create();
        $jadwal = $this->buatJadwal($guru, $mapel, $sesi);
        $perubahan = $this->payload([
            'nama' => $sesi->nama,
            'urutan' => 6,
            'jam_mulai' => '11:00',
            'jam_selesai' => '11:45',
        ]);

        $this->actingAs($this->admin())
            ->putJson(route('dashboard.admin.data-kelas.jam-belajar.sesi.update', $sesi), $perubahan)
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['jam_mulai']]);

        $this->actingAs($this->admin())
            ->putJson(route('dashboard.admin.data-kelas.jam-belajar.sesi.update', $sesi), $perubahan + [
                'konfirmasi_perubahan' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.sesi.jam_mulai', '11:00');

        $this->assertDatabaseHas('jadwal', [
            'id' => $jadwal->id,
            'sesi_id' => $sesi->id,
            'jam_mulai' => '11:00',
            'jam_selesai' => '11:45',
        ]);
    }

    public function test_perubahan_waktu_sesi_yang_membuat_jadwal_bentrok_ditolak(): void
    {
        $sesi = $this->sesi('06:45');
        $guru = User::factory()->guru()->create(['must_change_password' => false]);
        $mapel = MataPelajaran::factory()->nama('Matematika')->create();
        $this->buatJadwal($guru, $mapel, $sesi);

        JadwalPelajaran::create([
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'hari' => 'Senin',
            'jam_mulai' => '11:30',
            'jam_selesai' => '12:15',
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'ruang' => 'R.202',
        ]);

        $this->actingAs($this->admin())
            ->putJson(route('dashboard.admin.data-kelas.jam-belajar.sesi.update', $sesi), $this->payload([
                'nama' => $sesi->nama,
                'urutan' => 6,
                'jam_mulai' => '11:00',
                'jam_selesai' => '11:45',
                'konfirmasi_perubahan' => true,
            ]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['jam_mulai']]);

        $this->assertDatabaseHas('sesi_jam_belajar', [
            'id' => $sesi->id,
            'jam_mulai' => '06:45',
            'jam_selesai' => '07:30',
        ]);
    }

    public function test_jam_operasional_tidak_boleh_membuat_jadwal_tersimpan_luar_rentang(): void
    {
        $guru = User::factory()->guru()->create(['must_change_password' => false]);
        $mapel = MataPelajaran::factory()->nama('Matematika')->create();
        JadwalPelajaran::create([
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'hari' => 'Senin',
            'jam_mulai' => '14:30',
            'jam_selesai' => '15:00',
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'ruang' => 'R.101',
        ]);

        $this->actingAs($this->admin())
            ->putJson(route('dashboard.admin.data-kelas.jam-belajar.operasional.update'), [
                'jam_masuk' => '06:45',
                'jam_selesai' => '14:00',
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['jam_selesai']]);
    }

    public function test_menonaktifkan_hari_berisi_jadwal_membutuhkan_konfirmasi(): void
    {
        $sesi = $this->sesi('06:45');
        $guru = User::factory()->guru()->create(['must_change_password' => false]);
        $mapel = MataPelajaran::factory()->nama('Matematika')->create();
        $jadwal = $this->buatJadwal($guru, $mapel, $sesi, 'Jumat');
        $hariAktif = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Sabtu'];
        $perubahan = [
            'jam_masuk' => '06:45',
            'jam_selesai' => '15:00',
            'hari_aktif' => $hariAktif,
        ];

        $this->actingAs($this->admin())
            ->putJson(route('dashboard.admin.data-kelas.jam-belajar.operasional.update'), $perubahan)
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['hari_aktif']]);

        $this->actingAs($this->admin())
            ->putJson(route('dashboard.admin.data-kelas.jam-belajar.operasional.update'), $perubahan + [
                'konfirmasi_hari_nonaktif' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.konfigurasi.hari_aktif', $hariAktif);

        $this->assertSame($hariAktif, $this->konfigurasi()->fresh()->hari_aktif);
        $this->assertDatabaseHas('jadwal', ['id' => $jadwal->id, 'hari' => 'Jumat']);
    }

    public function test_sesi_pelajaran_yang_dipakai_tidak_boleh_diubah_menjadi_istirahat(): void
    {
        $sesi = $this->sesi('06:45');
        $guru = User::factory()->guru()->create(['must_change_password' => false]);
        $mapel = MataPelajaran::factory()->nama('Matematika')->create();
        $this->buatJadwal($guru, $mapel, $sesi);

        $this->actingAs($this->admin())
            ->putJson(route('dashboard.admin.data-kelas.jam-belajar.sesi.update', $sesi), $this->payload([
                'nama' => $sesi->nama,
                'jenis' => SesiJamBelajar::JENIS_ISTIRAHAT,
                'urutan' => 1,
                'jam_mulai' => '06:45',
                'jam_selesai' => '07:30',
            ]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['jenis']]);

        $this->assertDatabaseHas('sesi_jam_belajar', [
            'id' => $sesi->id,
            'jenis' => SesiJamBelajar::JENIS_PELAJARAN,
        ]);
    }

    public function test_guru_tidak_dapat_mengatur_jam_belajar(): void
    {
        $guru = User::factory()->guru()->create(['must_change_password' => false]);
        $sesi = $this->sesi('06:45');

        $this->actingAs($guru, 'guru')
            ->putJson(route('dashboard.admin.data-kelas.jam-belajar.operasional.update'), [
                'jam_masuk' => '07:00',
                'jam_selesai' => '14:00',
            ])
            ->assertForbidden();

        $this->actingAs($guru, 'guru')
            ->deleteJson(route('dashboard.admin.data-kelas.jam-belajar.sesi.destroy', $sesi))
            ->assertForbidden();
    }
}
