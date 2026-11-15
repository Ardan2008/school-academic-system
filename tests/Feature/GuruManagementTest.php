<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSpreadsheet;
use Tests\TestCase;

class GuruManagementTest extends TestCase
{
    use InteractsWithSpreadsheet;
    use RefreshDatabase;

    protected ?User $adminAccount = null;

    protected function admin(): User
    {
        // Disimpan sekali per test supaya tidak bentrok dengan akun admin yang
        // sudah dibuat sebelumnya.
        return $this->adminAccount ??= User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'must_change_password' => false,
        ]);
    }

    protected function guru(array $overrides = []): User
    {
        return User::factory()->guru()->withPassword('Guru1234')->create(array_merge([
            'name' => 'Dewi Lestari',
            'email' => fake()->unique()->safeEmail(),
            'nip' => '1001',
            'mata_pelajaran' => 'Fisika',
        ], $overrides));
    }

    public function test_halaman_data_guru_tampil(): void
    {
        $guru = $this->guru();

        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-guru.index'))
            ->assertOk()
            ->assertSee($guru->name)
            ->assertSee('Data Guru');
    }

    public function test_export_mengunduh_xlsx_berisi_password_awal(): void
    {
        $guru = $this->guru();

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-guru.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));

        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($response));

        $this->assertStringContainsString('Nama Guru', $isi);
        $this->assertStringContainsString('Password Awal', $isi);
        $this->assertStringContainsString('Status Password', $isi);
        $this->assertStringContainsString($guru->name, $isi);
        $this->assertStringContainsString('Guru1234', $isi);
        $this->assertStringContainsString('Fisika', $isi);
    }

    public function test_export_mengikuti_filter_status(): void
    {
        $this->guru();
        $cuti = $this->guru(['name' => 'Budi Susanti', 'status' => 'Cuti']);

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-guru.export', ['status' => 'Cuti']))
            ->assertOk();

        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($response));

        $this->assertStringContainsString($cuti->name, $isi);
        $this->assertStringNotContainsString('Dewi Lestari', $isi);
    }

    public function test_tambah_guru_membuat_akun_dengan_password_otomatis(): void
    {
        $response = $this->actingAs($this->admin())
            ->postJson(route('dashboard.admin.data-guru.store'), [
                'nis' => '2001',
                'nama' => 'Ahmad Fauzi',
                'email' => 'ahmad.fauzi@example.com',
                'mapel' => 'Matematika',
                'status' => 'Aktif',
                'password' => '',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.guru.nip', '2001')
            ->assertJsonPath('data.guru.nama', 'Ahmad Fauzi');

        $password = $response->json('data.password');
        $this->assertNotEmpty($password);
        $this->assertSame(8, strlen($password));

        $this->assertDatabaseHas('users', [
            'nip' => '2001',
            'name' => 'Ahmad Fauzi',
            'role' => 'guru',
            'mata_pelajaran' => 'Matematika',
            'must_change_password' => true,
        ]);
    }

    public function test_password_otomatis_berbeda_antar_guru(): void
    {
        $admin = $this->admin();

        $guruAkbar = $this->actingAs($admin)
            ->postJson(route('dashboard.admin.data-guru.store'), [
                'nis' => '2001',
                'nama' => 'Akbar Pratama',
                'email' => 'akbar.pratama@example.com',
                'mapel' => 'Matematika',
                'status' => 'Aktif',
                'password' => '',
            ])
            ->assertOk()
            ->json('data.password');

        $guruRina = $this->actingAs($admin)
            ->postJson(route('dashboard.admin.data-guru.store'), [
                'nis' => '2002',
                'nama' => 'Rina Wulandari',
                'email' => 'rina.wulandari@example.com',
                'mapel' => 'Fisika',
                'status' => 'Aktif',
                'password' => '',
            ])
            ->assertOk()
            ->json('data.password');

        $this->assertNotSame($guruAkbar, $guruRina);
        $this->assertNotSame($guruAkbar, $this->guru()->fresh()->visiblePassword());
    }

    public function test_password_penentu_yang_bentrok_diganti_otomatis(): void
    {
        $this->guru(['nip' => '1001']);

        $response = $this->actingAs($this->admin())
            ->postJson(route('dashboard.admin.data-guru.store'), [
                'nis' => '2001',
                'nama' => 'Akbar Pratama',
                'email' => 'akbar.pratama@example.com',
                'mapel' => 'Matematika',
                'status' => 'Aktif',
                // Nilai ini sama dengan password awal guru pertama, jadi harus
                // ditolak dan diganti oleh sistem.
                'password' => 'Guru1234',
            ])
            ->assertOk();

        $this->assertNotSame('Guru1234', $response->json('data.password'));
    }

    public function test_ubah_guru_mengganti_data_tanpa_mengubah_password(): void
    {
        $guru = $this->guru();

        $response = $this->actingAs($this->admin())
            ->putJson(route('dashboard.admin.data-guru.update', ['user' => $guru]), [
                'nis' => '1001',
                'nama' => 'Dewi Lestari, S.Pd.',
                'email' => 'dewi.lestari@example.com',
                'mapel' => 'Kimia',
                'status' => 'Cuti',
                'password' => '',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.guru.nama', 'Dewi Lestari, S.Pd.')
            ->assertJsonPath('data.guru.mapel', 'Kimia')
            ->assertJsonPath('data.guru.status', 'Cuti')
            ->assertJsonPath('data.password', null);

        $this->assertDatabaseHas('users', [
            'id' => $guru->id,
            'name' => 'Dewi Lestari, S.Pd.',
            'status' => 'Cuti',
            'must_change_password' => true,
        ]);

        $this->assertSame('Guru1234', $guru->fresh()->visiblePassword());
    }

    public function test_hapus_guru_menghapus_akun(): void
    {
        $guru = $this->guru();

        $this->actingAs($this->admin())
            ->deleteJson(route('dashboard.admin.data-guru.destroy', ['user' => $guru]))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('users', ['id' => $guru->id]);
    }

    public function test_nip_duplikat_ditolak(): void
    {
        $this->guru(['email' => 'dewi.lestari@example.com', 'nip' => '1001']);

        $this->actingAs($this->admin())
            ->postJson(route('dashboard.admin.data-guru.store'), [
                'nis' => '1001',
                'nama' => 'Dewi Lestari',
                'email' => 'dewi.lain@example.com',
                'mapel' => 'Fisika',
                'status' => 'Aktif',
                'password' => 'Guru1234',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nis');
    }

    public function test_ubah_data_bukan_guru_ditolak(): void
    {
        $siswa = User::factory()->siswa()->create(['email' => 'siswa@example.com']);

        $this->actingAs($this->admin())
            ->putJson(route('dashboard.admin.data-guru.update', ['user' => $siswa]), [
                'nis' => '1001',
                'nama' => 'Dewi Lestari',
                'email' => 'dewi.lestari@example.com',
                'mapel' => 'Fisika',
                'status' => 'Aktif',
                'password' => '',
            ])
            ->assertNotFound();
    }
}
