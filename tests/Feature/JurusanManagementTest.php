<?php

namespace Tests\Feature;

use App\Models\Jurusan;
use App\Models\KelasRombel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSpreadsheet;
use Tests\TestCase;

class JurusanManagementTest extends TestCase
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

    /**
     * @return array<string, mixed>
     */
    protected function payload(array $override = []): array
    {
        return array_merge([
            'nama' => 'Animasi',
            'status' => 'aktif',
        ], $override);
    }

    protected function kelas(string $jurusan, string $kelas = '12-1'): void
    {
        KelasRombel::create(['jurusan' => $jurusan, 'kelas' => $kelas]);
    }

    protected function siswa(string $jurusan, int $jumlah = 1): void
    {
        foreach (range(1, $jumlah) as $nomor) {
            User::factory()->siswa('12-1')->create([
                'name' => "Siswa {$jurusan} {$nomor}",
                'jurusan' => $jurusan,
            ]);
        }
    }

    /**
     * Ambil data yang benar-benar dikirim ke browser, supaya yang diuji adalah
     * isi halaman yang dilihat admin, bukan hanya nilai di dalam controller.
     *
     * @return array<string, mixed>
     */
    protected function jurusanData(): array
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-jurusan.index'));

        $response->assertOk();

        $this->assertSame(1, preg_match(
            '/^\s*const data = (.+);$/m',
            $response->getContent(),
            $match
        ), 'Payload jurusan tidak ditemukan di halaman Data Jurusan.');

        $data = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($data);

        return $data;
    }

    /** @return array<int, array<string, mixed>> */
    protected function barisJurusan(array $data): array
    {
        $this->assertIsArray($data['jurusan'] ?? null);

        return $data['jurusan'];
    }

    public function test_halaman_data_jurusan_bisa_diakses_admin(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-jurusan.index'));

        $response->assertOk();
        $response->assertSee('Data Jurusan');
        $response->assertSee('Tambah Jurusan');
    }

    public function test_halaman_menampilkan_jurusan_beserta_pemakaiannya(): void
    {
        Jurusan::create(['nama' => 'Rekayasa Perangkat Lunak']);
        Jurusan::create(['nama' => 'Teknik Komputer Jaringan']);

        $this->kelas('Rekayasa Perangkat Lunak', '12-1');
        $this->kelas('Rekayasa Perangkat Lunak', '11');
        $this->siswa('Rekayasa Perangkat Lunak', 2);
        $this->siswa('Teknik Komputer Jaringan', 1);

        $baris = $this->barisJurusan($this->jurusanData());

        $this->assertCount(2, $baris);

        $rpl = collect($baris)->firstWhere('nama', 'Rekayasa Perangkat Lunak');
        $this->assertSame('aktif', $rpl['status']);
        $this->assertSame(2, $rpl['jumlah_kelas']);
        $this->assertSame(2, $rpl['jumlah_siswa']);
        $this->assertTrue($rpl['dipakai']);
        $this->assertFalse($rpl['boleh_dihapus']);

        $tkj = collect($baris)->firstWhere('nama', 'Teknik Komputer Jaringan');
        $this->assertSame(0, $tkj['jumlah_kelas']);
        $this->assertSame(1, $tkj['jumlah_siswa']);
    }

    public function test_jurusan_kosong_tetap_ditampilkan_dan_bisa_dihapus(): void
    {
        $jurusan = Jurusan::create(['nama' => 'Multimedia']);

        $baris = $this->barisJurusan($this->jurusanData());

        $this->assertCount(1, $baris);
        $this->assertFalse($baris[0]['dipakai']);
        $this->assertTrue($baris[0]['boleh_dihapus']);

        $this->actingAs($this->admin())
            ->deleteJson(route('dashboard.admin.data-jurusan.destroy', $jurusan))
            ->assertOk();

        $this->assertDatabaseMissing('jurusan', ['id' => $jurusan->id]);
    }

    public function test_jurusan_yang_dipakai_kelas_tidak_bisa_dihapus(): void
    {
        $jurusan = Jurusan::create(['nama' => 'Rekayasa Perangkat Lunak']);
        $this->kelas('Rekayasa Perangkat Lunak');

        $this->actingAs($this->admin())
            ->deleteJson(route('dashboard.admin.data-jurusan.destroy', $jurusan))
            ->assertStatus(422)->assertJsonPath('status', 'error');

        $this->assertDatabaseHas('jurusan', ['id' => $jurusan->id]);
    }

    public function test_jurusan_yang_dipakai_siswa_tidak_bisa_dihapus(): void
    {
        $jurusan = Jurusan::create(['nama' => 'Teknik Komputer Jaringan']);
        $this->siswa('Teknik Komputer Jaringan');

        $this->actingAs($this->admin())
            ->deleteJson(route('dashboard.admin.data-jurusan.destroy', $jurusan))
            ->assertStatus(422)->assertJsonPath('status', 'error');

        $this->assertDatabaseHas('jurusan', ['id' => $jurusan->id]);
    }

    public function test_admin_bisa_menambah_jurusan(): void
    {
        $response = $this->actingAs($this->admin())->postJson(
            route('dashboard.admin.data-jurusan.store'),
            $this->payload(['nama' => '  Desain Komunikasi Visual  '])
        );

        $response->assertCreated();

        // Spasi di kanan-kiri nama dirapikan.
        $this->assertDatabaseHas('jurusan', [
            'nama' => 'Desain Komunikasi Visual',
            'status' => 'aktif',
        ]);

        $tersimpan = $response->json('data.jurusan');
        $this->assertSame('Desain Komunikasi Visual', $tersimpan['nama']);
        $this->assertSame('aktif', $tersimpan['status']);
        $this->assertFalse($tersimpan['dipakai']);
    }

    public function test_nama_jurusan_duplikat_ditolak(): void
    {
        Jurusan::create(['nama' => 'Animasi']);

        $this->actingAs($this->admin())->postJson(
            route('dashboard.admin.data-jurusan.store'),
            $this->payload()
        )->assertStatus(422)->assertJsonValidationErrors('nama');
    }

    public function test_status_jurusan_tidak_valid_ditolak(): void
    {
        $this->actingAs($this->admin())->postJson(
            route('dashboard.admin.data-jurusan.store'),
            $this->payload(['status' => 'hibernasi'])
        )->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_admin_bisa_mengubah_status_jurusan_yang_masih_dipakai(): void
    {
        $jurusan = Jurusan::create(['nama' => 'Rekayasa Perangkat Lunak']);
        $this->kelas('Rekayasa Perangkat Lunak');

        $this->actingAs($this->admin())->putJson(
            route('dashboard.admin.data-jurusan.update', $jurusan),
            $this->payload(['nama' => 'Rekayasa Perangkat Lunak', 'status' => 'nonaktif'])
        )->assertOk();

        $this->assertDatabaseHas('jurusan', [
            'id' => $jurusan->id,
            'nama' => 'Rekayasa Perangkat Lunak',
            'status' => 'nonaktif',
        ]);
    }

    public function test_nama_jurusan_yang_masih_dipakai_tidak_bisa_diubah(): void
    {
        $jurusan = Jurusan::create(['nama' => 'Rekayasa Perangkat Lunak']);
        $this->kelas('Rekayasa Perangkat Lunak');

        $this->actingAs($this->admin())->putJson(
            route('dashboard.admin.data-jurusan.update', $jurusan),
            $this->payload(['nama' => 'Rekayasa Perangkat Lunak Pro'])
        )->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseHas('jurusan', [
            'id' => $jurusan->id,
            'nama' => 'Rekayasa Perangkat Lunak',
        ]);
    }

    public function test_halaman_mengirim_id_jurusan_untuk_tombol_ubah_dan_hapus(): void
    {
        $jurusan = Jurusan::create(['nama' => 'Animasi']);

        $baris = $this->barisJurusan($this->jurusanData());

        // Tanpa id, tombol ubah/hapus di halaman tidak tahu baris mana yang
        // harus dikirim ke server.
        $this->assertSame($jurusan->id, $baris[0]['id']);
    }

    public function test_export_mengunduh_xlsx_berisi_jurusan(): void
    {
        Jurusan::create(['nama' => 'Rekayasa Perangkat Lunak']);
        Jurusan::create(['nama' => 'Animasi', 'status' => 'nonaktif']);

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-jurusan.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));

        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($response));

        $this->assertStringContainsString('Jurusan', $isi);
        $this->assertStringContainsString('Rekayasa Perangkat Lunak', $isi);
        $this->assertStringContainsString('Animasi', $isi);
        $this->assertStringContainsString('Aktif', $isi);
        $this->assertStringContainsString('Nonaktif', $isi);
    }

    public function test_export_mengikuti_filter_status_yang_dipakai_di_browser(): void
    {
        Jurusan::create(['nama' => 'Rekayasa Perangkat Lunak']);
        Jurusan::create(['nama' => 'Animasi', 'status' => 'nonaktif']);

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-jurusan.export', ['status' => 'aktif']))
            ->assertOk();

        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($response));

        $this->assertStringContainsString('Rekayasa Perangkat Lunak', $isi);
        $this->assertStringNotContainsString('Animasi', $isi);
    }
}
