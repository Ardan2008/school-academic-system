<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithSpreadsheet;
use Tests\TestCase;

class MataPelajaranManagementTest extends TestCase
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

    protected function guru(string $mapel): void
    {
        User::factory()->guru()->create([
            'email' => fake()->unique()->safeEmail(),
            'mata_pelajaran' => $mapel,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(array $override = []): array
    {
        return array_merge([
            'kode' => 'mtk',
            'nama' => 'Matematika',
        ], $override);
    }

    /**
     * Ambil data yang benar-benar dikirim ke browser, supaya yang diuji adalah
     * isi halaman yang dilihat admin, bukan hanya nilai di dalam controller.
     *
     * @return array<string, mixed>
     */
    protected function mapelData(): array
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-mata-pelajaran.index'));

        $response->assertOk();

        $this->assertSame(1, preg_match(
            '/^\s*const data = (.+);$/m',
            $response->getContent(),
            $match
        ), 'Payload mata pelajaran tidak ditemukan di halaman Data Mata Pelajaran.');

        $data = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($data);

        return $data;
    }

    /** @return array<int, array<string, mixed>> */
    protected function barisMapel(array $data): array
    {
        $this->assertIsArray($data['mapel'] ?? null);

        return $data['mapel'];
    }

    public function test_halaman_data_mata_pelajaran_bisa_diakses_admin(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-mata-pelajaran.index'));

        $response->assertOk();
        $response->assertSee('Data Mata Pelajaran');
        $response->assertSee('Tambah Mapel');
    }

    public function test_halaman_menampilkan_mapel_beserta_jumlah_pengampunya(): void
    {
        MataPelajaran::create(['kode' => 'MTK', 'nama' => 'Matematika']);
        MataPelajaran::create(['kode' => 'FKS', 'nama' => 'Fisika']);

        $this->guru('Matematika');
        $this->guru('Matematika');
        $this->guru('Fisika');

        $baris = $this->barisMapel($this->mapelData());

        $this->assertCount(2, $baris);

        $mtk = collect($baris)->firstWhere('nama', 'Matematika');
        $this->assertSame('MTK', $mtk['kode']);
        $this->assertSame(2, $mtk['jumlah_pengampu']);
        $this->assertTrue($mtk['dipakai']);
        $this->assertFalse($mtk['boleh_dihapus']);

        $fks = collect($baris)->firstWhere('nama', 'Fisika');
        $this->assertSame('FKS', $fks['kode']);
        $this->assertSame(1, $fks['jumlah_pengampu']);
    }

    public function test_mapel_kosong_tetap_ditampilkan_dan_bisa_dihapus(): void
    {
        $mapel = MataPelajaran::create(['nama' => 'Seni Budaya']);

        $baris = $this->barisMapel($this->mapelData());

        $this->assertCount(1, $baris);
        $this->assertNull($baris[0]['kode']);
        $this->assertFalse($baris[0]['dipakai']);
        $this->assertTrue($baris[0]['boleh_dihapus']);

        $this->actingAs($this->admin())
            ->deleteJson(route('dashboard.admin.data-mata-pelajaran.destroy', $mapel))
            ->assertOk();

        $this->assertDatabaseMissing('mata_pelajaran', ['id' => $mapel->id]);
    }

    public function test_mapel_yang_diampu_guru_tidak_bisa_dihapus(): void
    {
        $mapel = MataPelajaran::create(['nama' => 'Matematika']);
        $this->guru('Matematika');

        $this->actingAs($this->admin())
            ->deleteJson(route('dashboard.admin.data-mata-pelajaran.destroy', $mapel))
            ->assertStatus(422)->assertJsonPath('status', 'error');

        $this->assertDatabaseHas('mata_pelajaran', ['id' => $mapel->id]);
    }

    public function test_mapel_yang_punya_nilai_tidak_bisa_dihapus(): void
    {
        $mapel = MataPelajaran::create(['nama' => 'Matematika']);
        Nilai::factory()->mataPelajaran($mapel)->create();

        $baris = collect($this->barisMapel($this->mapelData()))->firstWhere('id', $mapel->id);

        // Mapel ini tidak diampu guru, tetapi nilai siswa sudah terhubung
        // lewat mata_pelajaran_id sehingga tidak boleh dianggap kosong.
        $this->assertSame(0, $baris['jumlah_pengampu']);
        $this->assertTrue($baris['dipakai']);
        $this->assertFalse($baris['boleh_dihapus']);

        $this->actingAs($this->admin())
            ->deleteJson(route('dashboard.admin.data-mata-pelajaran.destroy', $mapel))
            ->assertStatus(422)->assertJsonPath('status', 'error');

        $this->assertDatabaseHas('mata_pelajaran', ['id' => $mapel->id]);
        $this->assertDatabaseCount('nilai', 1);
    }

    public function test_mapel_yang_dipakai_tugas_materi_atau_jadwal_tidak_bisa_dihapus(): void
    {
        $mapel = MataPelajaran::create(['nama' => 'Fisika']);
        $waktu = ['created_at' => now(), 'updated_at' => now()];

        DB::table('tugas')->insert([
            'kelas' => '10-1',
            'mata_pelajaran_id' => $mapel->id,
            'judul' => 'Tugas 1',
            ...$waktu,
        ]);

        $this->actingAs($this->admin())
            ->deleteJson(route('dashboard.admin.data-mata-pelajaran.destroy', $mapel))
            ->assertStatus(422)->assertJsonPath('status', 'error');

        DB::table('materi')->insert([
            'kelas' => '10-1',
            'mata_pelajaran_id' => $mapel->id,
            'judul' => 'Materi 1',
            ...$waktu,
        ]);

        $this->actingAs($this->admin())
            ->deleteJson(route('dashboard.admin.data-mata-pelajaran.destroy', $mapel))
            ->assertStatus(422)->assertJsonPath('status', 'error');

        DB::table('jadwal')->insert([
            'kelas' => '10-1',
            'hari' => 'Senin',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '08:00:00',
            'mata_pelajaran_id' => $mapel->id,
            ...$waktu,
        ]);

        $this->actingAs($this->admin())
            ->deleteJson(route('dashboard.admin.data-mata-pelajaran.destroy', $mapel))
            ->assertStatus(422)->assertJsonPath('status', 'error');

        $this->assertDatabaseHas('mata_pelajaran', ['id' => $mapel->id]);
        $this->assertDatabaseCount('tugas', 1);
        $this->assertDatabaseCount('materi', 1);
        $this->assertDatabaseCount('jadwal', 1);
    }

    public function test_nama_mapel_yang_punya_nilai_tidak_bisa_diganti(): void
    {
        $mapel = MataPelajaran::create(['nama' => 'Matematika']);
        Nilai::factory()->mataPelajaran($mapel)->create();

        $this->actingAs($this->admin())->putJson(
            route('dashboard.admin.data-mata-pelajaran.update', $mapel),
            $this->payload(['nama' => 'Matematika Lanjutan'])
        )->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseHas('mata_pelajaran', [
            'id' => $mapel->id,
            'nama' => 'Matematika',
        ]);
    }

    public function test_admin_bisa_menambah_mapel(): void
    {
        $response = $this->actingAs($this->admin())->postJson(
            route('dashboard.admin.data-mata-pelajaran.store'),
            $this->payload(['kode' => ' bing ', 'nama' => '  Bahasa Inggris  '])
        );

        $response->assertCreated();

        // Spasi di kanan-kiri dirapikan dan kode otomatis diubah menjadi huruf
        // besar supaya datanya konsisten.
        $this->assertDatabaseHas('mata_pelajaran', [
            'kode' => 'BING',
            'nama' => 'Bahasa Inggris',
        ]);

        $tersimpan = $response->json('data.mapel');
        $this->assertSame('BING', $tersimpan['kode']);
        $this->assertSame('Bahasa Inggris', $tersimpan['nama']);
        $this->assertFalse($tersimpan['dipakai']);
    }

    public function test_nama_mapel_duplikat_ditolak(): void
    {
        MataPelajaran::create(['nama' => 'Matematika']);

        $this->actingAs($this->admin())->postJson(
            route('dashboard.admin.data-mata-pelajaran.store'),
            $this->payload()
        )->assertStatus(422)->assertJsonValidationErrors('nama');
    }

    public function test_kode_mapel_tidak_valid_ditolak(): void
    {
        $this->actingAs($this->admin())->postJson(
            route('dashboard.admin.data-mata-pelajaran.store'),
            $this->payload(['kode' => 'MTK 1'])
        )->assertStatus(422)->assertJsonValidationErrors('kode');

        $this->actingAs($this->admin())->postJson(
            route('dashboard.admin.data-mata-pelajaran.store'),
            $this->payload(['kode' => 'MTK_1'])
        )->assertStatus(422)->assertJsonValidationErrors('kode');
    }

    public function test_nama_mapel_yang_diampu_guru_tidak_bisa_diganti(): void
    {
        $mapel = MataPelajaran::create(['nama' => 'Matematika']);
        $this->guru('Matematika');

        $this->actingAs($this->admin())->putJson(
            route('dashboard.admin.data-mata-pelajaran.update', $mapel),
            $this->payload(['nama' => 'Matematika Lanjutan'])
        )->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseHas('mata_pelajaran', [
            'id' => $mapel->id,
            'nama' => 'Matematika',
        ]);
    }

    public function test_kode_mapel_yang_diampu_guru_masih_bisa_diubah(): void
    {
        $mapel = MataPelajaran::create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $this->guru('Matematika');

        $this->actingAs($this->admin())->putJson(
            route('dashboard.admin.data-mata-pelajaran.update', $mapel),
            $this->payload(['kode' => 'mtk-lanjut'])
        )->assertOk()
            ->assertJsonPath('data.mapel.kode', 'MTK-LANJUT');

        $this->assertDatabaseHas('mata_pelajaran', [
            'id' => $mapel->id,
            'kode' => 'MTK-LANJUT',
            'nama' => 'Matematika',
        ]);
    }

    public function test_admin_bisa_mengubah_nama_mapel_yang_belum_dipakai(): void
    {
        $mapel = MataPelajaran::create(['nama' => 'Matematika']);

        $this->actingAs($this->admin())->putJson(
            route('dashboard.admin.data-mata-pelajaran.update', $mapel),
            $this->payload(['nama' => 'Matematika Dasar'])
        )->assertOk();

        $this->assertDatabaseHas('mata_pelajaran', [
            'id' => $mapel->id,
            'nama' => 'Matematika Dasar',
        ]);
    }

    public function test_halaman_mengirim_id_mapel_untuk_tombol_ubah_dan_hapus(): void
    {
        $mapel = MataPelajaran::create(['nama' => 'Matematika']);

        $baris = $this->barisMapel($this->mapelData());

        // Tanpa id, tombol ubah/hapus di halaman tidak tahu baris mana yang
        // harus dikirim ke server.
        $this->assertSame($mapel->id, $baris[0]['id']);
    }

    public function test_export_mengunduh_xlsx_berisi_mapel(): void
    {
        MataPelajaran::create(['kode' => 'MTK', 'nama' => 'Matematika']);
        MataPelajaran::create(['kode' => null, 'nama' => 'Seni Budaya']);

        $this->guru('Matematika');

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-mata-pelajaran.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));

        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($response));

        $this->assertStringContainsString('Mata Pelajaran', $isi);
        $this->assertStringContainsString('Kode', $isi);
        $this->assertStringContainsString('Jumlah Pengampu', $isi);
        $this->assertStringContainsString('Matematika', $isi);
        $this->assertStringContainsString('Seni Budaya', $isi);
        $this->assertStringContainsString('1', $isi);

        $this->assertSame(2, (int) $response->headers->get('X-Total-Mapel'));
    }

    public function test_export_menulis_teks_mirip_formula_sebagai_teks_biasa(): void
    {
        // Nama bisa berasal dari impor berkas, jadi teks yang diawali "=" tidak
        // boleh berubah menjadi formula saat file export dibuka di Excel.
        MataPelajaran::create(['nama' => '=1+1']);

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-mata-pelajaran.export'))
            ->assertOk();

        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($response));

        $this->assertStringContainsString('=1+1', $isi);
    }

    public function test_export_mengikuti_filter_dipakai_di_browser(): void
    {
        MataPelajaran::create(['nama' => 'Matematika']);
        MataPelajaran::create(['nama' => 'Seni Budaya']);

        $this->guru('Matematika');

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-mata-pelajaran.export', ['isi' => 'dipakai']))
            ->assertOk();

        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($response));

        $this->assertStringContainsString('Matematika', $isi);
        $this->assertStringNotContainsString('Seni Budaya', $isi);
    }

    public function test_export_mengikuti_pencarian_di_browser(): void
    {
        MataPelajaran::create(['kode' => 'MTK', 'nama' => 'Matematika']);
        MataPelajaran::create(['kode' => 'FKS', 'nama' => 'Fisika']);

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-mata-pelajaran.export', ['q' => 'fisika']))
            ->assertOk();

        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($response));

        $this->assertStringContainsString('Fisika', $isi);
        $this->assertStringNotContainsString('Matematika', $isi);
    }
}
