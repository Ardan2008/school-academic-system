<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\JadwalPelajaran;
use App\Models\JamBelajarKonfigurasi;
use App\Models\Jurusan;
use App\Models\KelasRombel;
use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\Notification;
use App\Models\PengumpulanTugas;
use App\Models\Tugas;
use App\Models\User;
use App\Support\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class PerubahanIdentikTest extends TestCase
{
    use RefreshDatabase;

    private const JURUSAN = 'Rekayasa Perangkat Lunak';

    private function admin(): User
    {
        return User::factory()->admin()->create(['must_change_password' => false]);
    }

    private function guru(string $mapel = 'Matematika'): User
    {
        return User::factory()->guru()->create([
            'mata_pelajaran' => $mapel,
            'must_change_password' => false,
        ]);
    }

    private function siswa(string $kelas = '12-1'): User
    {
        return User::factory()->siswa($kelas)->create([
            'jurusan' => self::JURUSAN,
            'status' => User::STATUS_AKTIF,
            'must_change_password' => false,
        ]);
    }

    public function test_admin_put_data_guru_unchanged_noop_and_changed_writes(): void
    {
        $admin = $this->admin();
        $guru = User::factory()->guru()->withPassword('Guru1234')->create([
            'name' => 'Guru Lama',
            'email' => 'guru@example.com',
            'nip' => 'NIP-001',
            'mata_pelajaran' => 'Matematika',
            'status' => 'Aktif',
            'penugasan_diatur' => false,
        ]);
        $payload = [
            'nama' => 'Guru Lama',
            'nis' => 'NIP-001',
            'email' => 'guru@example.com',
            'mapel' => 'Matematika',
            'status' => 'Aktif',
            'password' => '',
        ];

        $this->actingAs($admin)
            ->putJson(route('dashboard.admin.data-guru.update', $guru), $payload)
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseCount('activity_logs', 0);

        $this->actingAs($admin)
            ->putJson(route('dashboard.admin.data-guru.update', $guru), array_merge($payload, ['nama' => 'Guru Baru']))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $guru->refresh();
        $this->assertSame('Guru Baru', $guru->name);
        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertDatabaseHas('activity_logs', ['activity_type' => ActivityLog::TIPE_DATA_GURU]);
    }

    public function test_admin_put_data_siswa_unchanged_noop_and_changed_writes(): void
    {
        $admin = $this->admin();
        $jurusan = Jurusan::create(['nama' => self::JURUSAN, 'status' => 'aktif']);
        $siswa = User::factory()->siswa('12-1')->withPassword('Siswa1234')->create([
            'name' => 'Siswa Lama',
            'email' => 'siswa@example.com',
            'nis' => 'NIS-001',
            'jurusan' => self::JURUSAN,
            'status' => User::STATUS_AKTIF,
        ]);
        $payload = [
            'nama' => 'Siswa Lama',
            'nis' => 'NIS-001',
            'email' => 'siswa@example.com',
            'kelas' => '12-1',
            'jurusan' => $jurusan->id,
            'status' => User::STATUS_AKTIF,
            'password' => '',
        ];

        $updatedAt = $siswa->updated_at;
        $this->actingAs($admin)
            ->putJson(route('dashboard.admin.data-siswa.update', $siswa), $payload)
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $siswa->refresh();
        $this->assertSame('Siswa Lama', $siswa->name);
        $this->assertSame('siswa@example.com', $siswa->email);
        $this->assertSame('12-1', $siswa->kelas);
        $this->assertSame(self::JURUSAN, $siswa->jurusan);
        $this->assertSame('NIS-001', $siswa->nis);
        $this->assertSame(User::STATUS_AKTIF, $siswa->status);
        $this->assertTrue($siswa->updated_at->equalTo($updatedAt));

        $this->actingAs($admin)
            ->putJson(route('dashboard.admin.data-siswa.update', $siswa), array_merge($payload, ['nama' => 'Siswa Baru']))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $siswa->refresh();
        $this->assertSame('Siswa Baru', $siswa->name);
        $this->assertDatabaseHas('activity_logs', ['activity_type' => ActivityLog::TIPE_DATA_SISWA, 'description' => 'Memperbarui data siswa Siswa Baru.']);
    }

    public function test_admin_put_data_kelas_and_jurusan_unchanged_noop(): void
    {
        $admin = $this->admin();
        $jurusan = Jurusan::create(['nama' => self::JURUSAN, 'status' => 'aktif']);
        $kelas = KelasRombel::create(['jurusan' => self::JURUSAN, 'kelas' => '12-1', 'wali_kelas_id' => null]);

        $kelasUpdatedAt = $kelas->updated_at;
        $this->actingAs($admin)
            ->putJson(route('dashboard.admin.data-kelas.update', $kelas), [
                'jurusan' => $jurusan->id,
                'kelas' => '12-1',
                'wali_kelas_id' => null,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $kelas->refresh();
        $this->assertSame('12-1', $kelas->kelas);
        $this->assertSame(self::JURUSAN, $kelas->jurusan);
        $this->assertNull($kelas->wali_kelas_id);
        $this->assertTrue($kelas->updated_at->equalTo($kelasUpdatedAt));

        $jurusanUpdatedAt = $jurusan->updated_at;
        $this->actingAs($admin)
            ->putJson(route('dashboard.admin.data-jurusan.update', $jurusan), [
                'nama' => self::JURUSAN,
                'status' => 'aktif',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $jurusan->refresh();
        $this->assertSame(self::JURUSAN, $jurusan->nama);
        $this->assertSame('aktif', $jurusan->status);
        $this->assertTrue($jurusan->updated_at->equalTo($jurusanUpdatedAt));

        $this->actingAs($admin)
            ->putJson(route('dashboard.admin.data-jurusan.update', $jurusan), [
                'nama' => self::JURUSAN,
                'status' => 'nonaktif',
            ])
            ->assertOk();

        $this->assertDatabaseHas('activity_logs', ['activity_type' => ActivityLog::TIPE_DATA_JURUSAN]);
    }

    public function test_admin_put_jam_belajar_operasional_unchanged_noop(): void
    {
        $admin = $this->admin();
        $konfigurasi = JamBelajarKonfigurasi::query()->firstOrFail();
        $payload = [
            'jam_masuk' => substr((string) $konfigurasi->jam_masuk, 0, 5),
            'jam_selesai' => substr((string) $konfigurasi->jam_selesai, 0, 5),
            'keterangan' => $konfigurasi->keterangan,
            'hari_aktif' => $konfigurasi->hari_aktif ?? ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'],
        ];
        $updatedAt = $konfigurasi->updated_at;

        $this->actingAs($admin)
            ->putJson(route('dashboard.admin.data-kelas.jam-belajar.operasional.update'), $payload)
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $konfigurasi->refresh();
        $this->assertTrue($konfigurasi->updated_at->equalTo($updatedAt));
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_guru_put_tugas_unchanged_noop_and_same_file_keeps_path(): void
    {
        Storage::fake('local');
        $guru = $this->guru();
        $mapel = MataPelajaran::factory()->nama('Matematika')->create();
        $this->siswa();
        JadwalPelajaran::create([
            'kelas' => '12-1',
            'jurusan' => self::JURUSAN,
            'hari' => 'Senin',
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:30',
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'ruang' => 'R.101',
        ]);
        $sameFile = UploadedFile::fake()->create('lampiran.pdf', 12, 'application/pdf');
        $path = 'tugas/uji/lampiran.pdf';
        Storage::disk('local')->put($path, file_get_contents($sameFile->getRealPath()));
        $tugas = Tugas::create([
            'kelas' => '12-1',
            'jurusan' => self::JURUSAN,
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'judul' => 'Tugas Uji',
            'deskripsi' => 'Deskripsi',
            'deadline' => now()->addDay()->startOfMinute(),
            'file_path' => $path,
            'nama_file' => 'lampiran.pdf',
            'mime_type' => 'application/pdf',
            'ekstensi' => 'pdf',
            'ukuran_bytes' => 12,
        ]);
        $updatedAt = $tugas->updated_at;
        $payload = [
            'mata_pelajaran_id' => $mapel->id,
            'rombel' => '12-1|'.self::JURUSAN,
            'judul' => 'Tugas Uji',
            'deskripsi' => 'Deskripsi',
            'deadline' => $tugas->deadline?->format('Y-m-d\TH:i'),
        ];

        $this->actingAs($guru, 'guru')
            ->from(route('dashboard.guru.tugas-materi.index'))
            ->put(route('dashboard.guru.tugas-materi.tugas.update', $tugas), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard.guru.tugas-materi.index', ['tab' => 'tugas']));

        $tugas->refresh();
        $this->assertTrue($tugas->updated_at->equalTo($updatedAt));
        $this->assertSame($path, $tugas->file_path);

        $uploadSame = UploadedFile::fake()->create('lampiran.pdf', 12, 'application/pdf');
        file_put_contents($uploadSame->getRealPath(), file_get_contents($sameFile->getRealPath()));
        $this->actingAs($guru, 'guru')
            ->from(route('dashboard.guru.tugas-materi.index'))
            ->put(route('dashboard.guru.tugas-materi.tugas.update', $tugas), array_merge($payload, ['file' => $uploadSame]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard.guru.tugas-materi.index', ['tab' => 'tugas']));

        $tugas->refresh();
        $this->assertSame($path, $tugas->file_path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_guru_put_pengumpulan_unchanged_noop(): void
    {
        $guru = $this->guru();
        $mapel = MataPelajaran::factory()->nama('Matematika')->create();
        $siswa = $this->siswa();
        $tugas = Tugas::create([
            'kelas' => '12-1',
            'jurusan' => self::JURUSAN,
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'judul' => 'Tugas',
            'deadline' => now()->addDay(),
        ]);
        $pengumpulan = PengumpulanTugas::create([
            'tugas_id' => $tugas->id,
            'user_id' => $siswa->id,
            'dikumpulkan_at' => now()->subHour(),
            'jawaban' => 'Jawaban',
            'status' => PengumpulanTugas::STATUS_DINILAI,
            'nilai' => 90,
            'umpan_balik' => 'Bagus',
        ]);
        $updatedAt = $pengumpulan->updated_at;
        $notifCount = Notification::query()->count();

        $this->actingAs($guru, 'guru')
            ->put(route('dashboard.guru.tugas-materi.pengumpulan.update', $pengumpulan), [
                'nilai' => 90,
                'umpan_balik' => 'Bagus',
            ])
            ->assertRedirect(route('dashboard.guru.tugas-materi.pengumpulan', $tugas));

        $pengumpulan->refresh();
        $this->assertTrue($pengumpulan->updated_at->equalTo($updatedAt));
        $this->assertSame(PengumpulanTugas::STATUS_DINILAI, $pengumpulan->status);
        $this->assertDatabaseCount('notifications', $notifCount);
    }

    public function test_guru_post_input_nilai_identical_noop(): void
    {
        $guru = $this->guru();
        $mapel = MataPelajaran::factory()->nama('Matematika')->create();
        $siswa = $this->siswa();
        $periode = Periode::periodeSekarang();

        Nilai::factory()->siswa($siswa)->mataPelajaran($mapel)
            ->periode($periode['tahun_ajaran'], $periode['semester'])
            ->nilai(88)->kkm(75)->create();

        $updatedAt = Nilai::query()->firstOrFail()->updated_at;
        $notifCount = Notification::query()->count();

        $this->actingAs($guru, 'guru')
            ->postJson(route('dashboard.guru.input-nilai.store'), [
                'mata_pelajaran_id' => $mapel->id,
                'tahun_ajaran' => $periode['tahun_ajaran'],
                'semester' => $periode['semester'],
                'kelas' => '12-1',
                'jurusan' => self::JURUSAN,
                'nilai' => [['user_id' => $siswa->id, 'nilai' => 88, 'kkm' => 75]],
            ])
            ->assertOk()
            ->assertJsonPath('data.disimpan', 0);

        $nilai = Nilai::query()->firstOrFail();
        $this->assertTrue($nilai->updated_at->equalTo($updatedAt));
        $this->assertDatabaseCount('activity_logs', 0);
        $this->assertDatabaseCount('notifications', $notifCount);
    }

    public function test_siswa_post_pengumpulan_identical_noop(): void
    {
        $guru = $this->guru();
        $mapel = MataPelajaran::factory()->nama('Matematika')->create();
        $siswa = $this->siswa();
        $tugas = Tugas::create([
            'kelas' => '12-1',
            'jurusan' => self::JURUSAN,
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'judul' => 'Tugas',
            'deadline' => now()->addDay(),
        ]);
        $pengumpulan = PengumpulanTugas::create([
            'tugas_id' => $tugas->id,
            'user_id' => $siswa->id,
            'dikumpulkan_at' => now()->subHour(),
            'jawaban' => 'Jawaban sama',
            'status' => PengumpulanTugas::STATUS_DIKUMPULKAN,
        ]);
        $dikumpulkanAt = $pengumpulan->dikumpulkan_at;
        $notifCount = Notification::query()->count();

        $this->actingAs($siswa)
            ->post(route('dashboard.siswa.tugas-materi.pengumpulan.store', $tugas), [
                'jawaban' => 'Jawaban sama',
            ])
            ->assertRedirect(route('dashboard.siswa.tugas-materi.index', ['tab' => 'tugas']));

        $pengumpulan->refresh();
        $this->assertTrue($pengumpulan->dikumpulkan_at->equalTo($dikumpulkanAt));
        $this->assertDatabaseCount('pengumpulan_tugas', 1);
        $this->assertDatabaseCount('notifications', $notifCount);
    }

    public function test_profile_web_password_same_photo_identical_and_delete_null_noop(): void
    {
        Storage::fake('public');
        $user = User::factory()->admin()->create([
            'password' => 'Lama1234',
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('profile.password', ['role' => 'admin']), [
                'current_password' => 'Lama1234',
                'password' => 'Lama1234',
                'password_confirmation' => 'Lama1234',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertTrue(Hash::check('Lama1234', $user->fresh()->password));
        $this->assertDatabaseCount('activity_logs', 0);

        $base = UploadedFile::fake()->image('foto.png', 100, 100);
        $path = $base->storeAs('profile-photos/admin', 'foto.png', 'public');
        $user->forceFill(['profile_photo_path' => $path])->save();
        $storedBytes = Storage::disk('public')->get($path);

        $tmp = tempnam(sys_get_temp_dir(), 'img');
        $tmpPng = $tmp.'.png';
        file_put_contents($tmpPng, $storedBytes);
        @unlink($tmp);
        $sameFile = new UploadedFile($tmpPng, 'foto-baru.png', 'image/png', null, true);

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('profile.photo.update', ['role' => 'admin']), ['photo' => $sameFile])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $user->refresh();
        $this->assertSame($path, $user->profile_photo_path);
        $this->assertCount(1, Storage::disk('public')->files('profile-photos/admin'));
        $this->assertDatabaseCount('activity_logs', 0);

        $user->forceFill(['profile_photo_path' => null])->save();
        Storage::disk('public')->delete($path);

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->delete(route('profile.photo.delete', ['role' => 'admin']))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertNull($user->fresh()->profile_photo_path);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_api_change_password_same_password_noop(): void
    {
        $user = User::factory()->admin()->create([
            'password' => 'Admin123',
            'must_change_password' => false,
        ]);
        $token = JWTAuth::fromUser($user);
        $updatedAt = $user->updated_at;

        $this->withToken($token)
            ->postJson(route('auth.change-password'), [
                'current_password' => 'Admin123',
                'password' => 'Admin123',
                'password_confirmation' => 'Admin123',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $user->refresh();
        $this->assertTrue(Hash::check('Admin123', $user->password));
        $this->assertTrue($user->updated_at->equalTo($updatedAt));
        $this->assertDatabaseCount('activity_logs', 0);
    }
}
