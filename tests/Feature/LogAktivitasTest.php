<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\MataPelajaran;
use App\Models\User;
use App\Support\Periode;
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LogAktivitasTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->admin()->create([
            'name' => 'Administrator',
            'email' => 'admin-log@example.com',
            'must_change_password' => false,
        ]);
    }

    protected function log(
        string $role,
        string $name,
        string $type,
        string $description,
        ?Carbon $createdAt = null
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => null,
            'user_name' => $name,
            'role' => $role,
            'activity_type' => $type,
            'description' => $description,
            'created_at' => $createdAt ?? now(),
        ]);
    }

    public function test_admin_dapat_membuka_halaman_log_aktivitas(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.log-aktivitas.index'))
            ->assertOk()
            ->assertSee('Log Aktivitas')
            ->assertSee('Cari Aktivitas')
            ->assertSee('Role')
            ->assertSee('Semua Role')
            ->assertSee('Jenis Aktivitas')
            ->assertSee('Dari Tanggal')
            ->assertSee('Sampai Tanggal')
            ->assertSee('placeholder="Pilih tanggal mulai"', false)
            ->assertSee('placeholder="Pilih tanggal akhir"', false)
            ->assertSee('Maksimal 10 data per halaman')
            ->assertSee('Riwayat tidak dapat diubah');
    }

    public function test_guru_dan_siswa_tidak_dapat_membuka_halaman_log_aktivitas(): void
    {
        $this->actingAs(User::factory()->guru()->create())
            ->get(route('dashboard.admin.log-aktivitas.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->siswa()->create())
            ->get(route('dashboard.admin.log-aktivitas.index'))
            ->assertForbidden();
    }

    public function test_halaman_menampilkan_aktivitas_semua_role_dari_yang_terbaru(): void
    {
        $this->log('admin', 'Admin Lama', ActivityLog::TIPE_DATA_GURU, 'Aktivitas admin lama', now()->subHours(3));
        $this->log('guru', 'Guru Baru', ActivityLog::TIPE_INPUT_NILAI, 'Aktivitas guru terbaru', now()->subHour());
        $this->log('siswa', 'Siswa Tengah', ActivityLog::TIPE_PASSWORD, 'Aktivitas siswa tengah', now()->subHours(2));

        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.log-aktivitas.index'))
            ->assertOk()
            ->assertSee('Admin Lama')
            ->assertSee('Guru Baru')
            ->assertSee('Siswa Tengah')
            ->assertSee('Aktivitas guru terbaru')
            ->assertViewHas('logs', function ($logs) {
                return $logs->total() === 3
                    && $logs->first()->description === 'Aktivitas guru terbaru';
            });
    }

    public function test_filter_role_jenis_pencarian_dan_tanggal_bekerja(): void
    {
        $admin = $this->admin();

        $this->log('admin', 'Admin Satu', ActivityLog::TIPE_DATA_GURU, 'Menambah guru fisika', now()->subDays(2));
        $this->log('guru', 'Guru Satu', ActivityLog::TIPE_INPUT_NILAI, 'Menyimpan nilai matematika', now()->subDay());
        $this->log('siswa', 'Siswa Satu', ActivityLog::TIPE_PASSWORD, 'Mengubah password siswa', now());

        $this->actingAs($admin)
            ->get(route('dashboard.admin.log-aktivitas.index', ['role' => 'guru']))
            ->assertOk()
            ->assertSee('Guru Satu')
            ->assertDontSee('Admin Satu')
            ->assertDontSee('Siswa Satu')
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1);

        $this->actingAs($admin)
            ->get(route('dashboard.admin.log-aktivitas.index', ['tipe' => ActivityLog::TIPE_PASSWORD]))
            ->assertOk()
            ->assertSee('Siswa Satu')
            ->assertDontSee('Guru Satu')
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1);

        $this->actingAs($admin)
            ->get(route('dashboard.admin.log-aktivitas.index', ['search' => 'fisika']))
            ->assertOk()
            ->assertSee('Admin Satu')
            ->assertDontSee('Guru Satu')
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1);

        $this->actingAs($admin)
            ->get(route('dashboard.admin.log-aktivitas.index', [
                'dari' => Waktu::zona(now()->subDay())->format('Y-m-d'),
                'sampai' => Waktu::zona(now())->format('Y-m-d'),
            ]))
            ->assertOk()
            ->assertSee('Guru Satu')
            ->assertSee('Siswa Satu')
            ->assertDontSee('Admin Satu')
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 2);
    }

    public function test_filter_tanggal_inklusif_di_batas_hari_wib_dan_boleh_hanya_satu_sisi(): void
    {
        $admin = $this->admin();

        $this->log('admin', 'Sebelum', ActivityLog::TIPE_DATA_GURU, 'Aktivitas sebelum rentang', Carbon::parse('2026-11-14 23:59:59', Waktu::ZONA)->utc());
        $this->log('admin', 'Awal', ActivityLog::TIPE_DATA_GURU, 'Aktivitas awal rentang', Carbon::parse('2026-11-15 00:00:00', Waktu::ZONA)->utc());
        $this->log('admin', 'Akhir', ActivityLog::TIPE_DATA_GURU, 'Aktivitas akhir rentang', Carbon::parse('2026-11-15 23:59:59', Waktu::ZONA)->utc());
        $this->log('admin', 'Sesudah', ActivityLog::TIPE_DATA_GURU, 'Aktivitas sesudah rentang', Carbon::parse('2026-11-16 00:00:00', Waktu::ZONA)->utc());

        $this->actingAs($admin)
            ->get(route('dashboard.admin.log-aktivitas.index', [
                'dari' => '2026-11-15',
                'sampai' => '2026-11-15',
            ]))
            ->assertOk()
            ->assertSee('Aktivitas awal rentang')
            ->assertSee('Aktivitas akhir rentang')
            ->assertDontSee('Aktivitas sebelum rentang')
            ->assertDontSee('Aktivitas sesudah rentang')
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 2);

        $this->actingAs($admin)
            ->get(route('dashboard.admin.log-aktivitas.index', ['dari' => '2026-11-15']))
            ->assertOk()
            ->assertSee('Aktivitas awal rentang')
            ->assertSee('Aktivitas akhir rentang')
            ->assertSee('Aktivitas sesudah rentang')
            ->assertDontSee('Aktivitas sebelum rentang')
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 3);

        $this->actingAs($admin)
            ->get(route('dashboard.admin.log-aktivitas.index', ['sampai' => '2026-11-15']))
            ->assertOk()
            ->assertSee('Aktivitas sebelum rentang')
            ->assertSee('Aktivitas awal rentang')
            ->assertSee('Aktivitas akhir rentang')
            ->assertDontSee('Aktivitas sesudah rentang')
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 3);
    }

    public function test_rentang_tanggal_terbalik_menampilkan_pesan_dan_tidak_menukar_filter(): void
    {
        $admin = $this->admin();

        $this->log('admin', 'Di Luar', ActivityLog::TIPE_DATA_GURU, 'Aktivitas di luar rentang terbalik', Carbon::parse('2026-12-01 12:00:00', Waktu::ZONA)->utc());

        $this->actingAs($admin)
            ->get(route('dashboard.admin.log-aktivitas.index', [
                'dari' => '2026-11-20',
                'sampai' => '2026-11-10',
            ]))
            ->assertOk()
            ->assertSee('Tanggal akhir tidak boleh lebih awal daripada tanggal mulai.')
            ->assertSee('Aktivitas di luar rentang terbalik')
            ->assertViewHas('dari', '2026-11-20')
            ->assertViewHas('sampai', '2026-11-10')
            ->assertViewHas('tanggalError', 'Tanggal akhir tidak boleh lebih awal daripada tanggal mulai.')
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1);
    }

    public function test_filter_tanggal_dikombinasikan_dengan_filter_lain_dan_pagination(): void
    {
        $admin = $this->admin();

        foreach (range(1, 12) as $i) {
            $this->log(
                'guru',
                'Guru Kombinasi '.$i,
                ActivityLog::TIPE_INPUT_NILAI,
                'Aktivitas kombinasi '.$i,
                Carbon::parse('2026-11-15 08:00:00', Waktu::ZONA)->addMinutes($i)->utc()
            );
        }

        $this->log('admin', 'Admin Kombinasi', ActivityLog::TIPE_INPUT_NILAI, 'Aktivitas kombinasi admin', Carbon::parse('2026-11-15 09:00:00', Waktu::ZONA)->utc());
        $this->log('guru', 'Guru Tipe Lain', ActivityLog::TIPE_DATA_GURU, 'Aktivitas kombinasi tipe lain', Carbon::parse('2026-11-15 09:00:00', Waktu::ZONA)->utc());

        $this->actingAs($admin)
            ->get(route('dashboard.admin.log-aktivitas.index', [
                'search' => 'kombinasi',
                'role' => 'guru',
                'tipe' => ActivityLog::TIPE_INPUT_NILAI,
                'dari' => '2026-11-15',
                'sampai' => '2026-11-15',
            ]))
            ->assertOk()
            ->assertViewHas('logs', function ($logs) {
                return $logs->total() === 12
                    && $logs->count() === 10
                    && $logs->lastPage() === 2
                    && $logs->first()->description === 'Aktivitas kombinasi 12'
                    && str_contains($logs->url(2), 'search=kombinasi')
                    && str_contains($logs->url(2), 'role=guru')
                    && str_contains($logs->url(2), 'tipe='.ActivityLog::TIPE_INPUT_NILAI)
                    && str_contains($logs->url(2), 'dari=2026-11-15')
                    && str_contains($logs->url(2), 'sampai=2026-11-15');
            });
    }

    public function test_reset_tanggal_mempertahankan_filter_lain(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('dashboard.admin.log-aktivitas.index', [
                'search' => 'fisika',
                'role' => 'guru',
                'tipe' => ActivityLog::TIPE_INPUT_NILAI,
                'dari' => '2026-11-01',
                'sampai' => '2026-11-15',
            ]))
            ->assertOk()
            ->assertSee('Reset Tanggal')
            ->assertSee(route('dashboard.admin.log-aktivitas.index', [
                'search' => 'fisika',
                'role' => 'guru',
                'tipe' => ActivityLog::TIPE_INPUT_NILAI,
            ]));
    }

    public function test_halaman_membatasi_sepuluh_data_per_halaman(): void
    {
        $admin = $this->admin();

        foreach (range(1, 12) as $i) {
            $this->log(
                'admin',
                'Admin '.$i,
                ActivityLog::TIPE_MANAJEMEN_AKUN,
                'Aktivitas nomor '.$i,
                now()->subMinutes(12 - $i)
            );
        }

        $this->actingAs($admin)
            ->get(route('dashboard.admin.log-aktivitas.index'))
            ->assertOk()
            ->assertViewHas('logs', function ($logs) {
                return $logs->total() === 12
                    && $logs->count() === 10
                    && $logs->lastPage() === 2
                    && $logs->first()->description === 'Aktivitas nomor 12';
            });

        $this->actingAs($admin)
            ->get(route('dashboard.admin.log-aktivitas.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('logs', function ($logs) {
                return $logs->count() === 2
                    && $logs->first()->description === 'Aktivitas nomor 2';
            });
    }

    public function test_aksi_admin_tercatat_dan_dashboard_menautkan_ke_log_aktivitas(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson(route('dashboard.admin.data-guru.store'), [
                'nis' => '2001',
                'nama' => 'Ahmad Fauzi',
                'email' => 'ahmad.log@example.com',
                'mapel' => 'Matematika',
                'status' => 'Aktif',
                'password' => '',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $log = ActivityLog::query()
            ->where('activity_type', ActivityLog::TIPE_DATA_GURU)
            ->firstOrFail();

        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($admin->name, $log->user_name);
        $this->assertSame('admin', $log->role);
        $this->assertStringContainsString('Ahmad Fauzi', $log->description);

        $this->actingAs($admin)
            ->get(route('dashboard.admin.index'))
            ->assertOk()
            ->assertSee('Ahmad Fauzi')
            ->assertSee(route('dashboard.admin.log-aktivitas.index'), false);
    }

    public function test_login_dan_logout_tercatat_sebagai_aktivitas(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'login.log@example.com',
            'password' => 'admin123',
            'must_change_password' => false,
        ]);

        $this->post(route('login.store'), [
            'role' => 'admin',
            'email' => 'login.log@example.com',
            'password' => 'admin123',
        ])->assertRedirect(route('dashboard.admin.index'));

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'role' => 'admin',
            'activity_type' => ActivityLog::TIPE_AUTH,
            'description' => 'Berhasil login ke sistem.',
        ]);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'role' => 'admin',
            'activity_type' => ActivityLog::TIPE_AUTH,
            'description' => 'Keluar dari sistem.',
        ]);
    }

    public function test_aktivitas_guru_dan_siswa_juga_tercatat(): void
    {
        $guru = User::factory()->guru()->create([
            'name' => 'Guru Log',
            'mata_pelajaran' => 'Matematika',
            'must_change_password' => false,
        ]);
        $mapel = MataPelajaran::factory()->nama('Matematika')->create();
        $siswa = User::factory()->siswa('12-1')->create([
            'name' => 'Siswa Log',
            'nis' => '99001',
            'email' => 'siswa.log@example.com',
            'status' => User::STATUS_AKTIF,
        ]);
        $periode = Periode::periodeSekarang();

        $this->actingAs($guru, 'guru')
            ->postJson(route('dashboard.guru.input-nilai.store'), [
                'mata_pelajaran_id' => $mapel->id,
                'tahun_ajaran' => $periode['tahun_ajaran'],
                'semester' => $periode['semester'],
                'kelas' => '12-1',
                'jurusan' => $siswa->jurusan,
                'nilai' => [[
                    'user_id' => $siswa->id,
                    'nilai' => 88,
                    'kkm' => 75,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $guru->id,
            'role' => 'guru',
            'activity_type' => ActivityLog::TIPE_INPUT_NILAI,
        ]);

        $this->postJson(route('landing.hubungi-tim-it'), [
            'role' => 'siswa',
            'nis' => '99001',
            'email' => 'siswa.log@example.com',
            'pesan' => 'Saya tidak bisa masuk memakai NIS saya.',
        ])->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $siswa->id,
            'role' => 'siswa',
            'activity_type' => ActivityLog::TIPE_KENDALA_AKUN,
        ]);
    }
}
