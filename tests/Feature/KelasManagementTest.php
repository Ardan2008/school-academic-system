<?php

namespace Tests\Feature;

use App\Models\KelasRombel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Concerns\InteractsWithSpreadsheet;
use Tests\TestCase;

class KelasManagementTest extends TestCase
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

    protected function guru(string $nama = 'Budi Santoso, S.Pd.'): User
    {
        return User::factory()->guru()->create(['name' => $nama]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(array $override = []): array
    {
        return array_merge([
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'wali_kelas_id' => null,
        ], $override);
    }

    protected function siswa(string $jurusan, string $kelas, int $jumlah = 1): void
    {
        foreach (range(1, $jumlah) as $nomor) {
            User::factory()->siswa($kelas)->create([
                'name' => "Siswa {$kelas} {$jurusan} {$nomor}",
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
    protected function kelasData(): array
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-kelas.index'));

        $response->assertOk();

        $this->assertSame(1, preg_match(
            '/^\s*const data = (.+);$/m',
            $response->getContent(),
            $match
        ), 'Payload kelas tidak ditemukan di halaman Data Kelas.');

        $data = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($data);

        return $data;
    }

    /** @return array<int, array<string, mixed>> */
    protected function barisKelas(array $data): array
    {
        $this->assertIsArray($data['kelas'] ?? null);

        return $data['kelas'];
    }

    /**
     * Struktur pilihan filter yang benar-benar dikirim ke browser Data Siswa,
     * dikunci berdasarkan nama jurusan.
     *
     * @return Collection<string, array<string, mixed>>
     */
    protected function strukturFilterSiswa(): Collection
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-siswa.index'));

        $response->assertOk();

        $this->assertSame(1, preg_match(
            '/^\s*const data = (.+);$/m',
            $response->getContent(),
            $match
        ), 'Payload siswa tidak ditemukan di halaman Data Siswa.');

        $data = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($data['struktur_kelas'] ?? null);

        return collect($data['struktur_kelas'])->keyBy('nama');
    }

    public function test_halaman_data_kelas_bisa_diakses_admin(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-kelas.index'));

        $response->assertOk();
        $response->assertSee('Data Kelas');
        $response->assertSee('Tambah Kelas');
    }

    public function test_halaman_data_kelas_menampilkan_kelas_beserta_jumlah_siswanya(): void
    {
        $this->siswa('Rekayasa Perangkat Lunak', '12-1', 3);
        $this->siswa('Teknik Komputer Jaringan', '10', 2);

        KelasRombel::create(['jurusan' => 'Rekayasa Perangkat Lunak', 'kelas' => '12-1']);
        KelasRombel::create(['jurusan' => 'Teknik Komputer Jaringan', 'kelas' => '10']);

        $baris = $this->barisKelas($this->kelasData());

        $this->assertCount(2, $baris);

        $rpl = collect($baris)->firstWhere('jurusan', 'Rekayasa Perangkat Lunak');
        $this->assertSame('12-1', $rpl['kelas']);
        $this->assertSame('12', $rpl['tingkat']);
        $this->assertSame('1', $rpl['rombel']);
        $this->assertSame(3, $rpl['jumlah_siswa']);
        $this->assertFalse($rpl['boleh_dihapus']);

        $tkj = collect($baris)->firstWhere('jurusan', 'Teknik Komputer Jaringan');
        $this->assertSame('10', $tkj['kelas']);
        $this->assertSame('10', $tkj['tingkat']);
        $this->assertNull($tkj['rombel']);
        $this->assertSame(2, $tkj['jumlah_siswa']);
    }

    public function test_kelas_kosong_tetap_ditampilkan_dan_bisa_dihapus(): void
    {
        $kelas = KelasRombel::create([
            'jurusan' => 'Multimedia',
            'kelas' => '11',
        ]);

        $baris = $this->barisKelas($this->kelasData());

        $this->assertCount(1, $baris);
        $this->assertSame(0, $baris[0]['jumlah_siswa']);
        $this->assertTrue($baris[0]['boleh_dihapus']);

        $this->actingAs($this->admin())
            ->deleteJson(route('dashboard.admin.data-kelas.destroy', $kelas))
            ->assertOk();

        $this->assertDatabaseMissing('kelas', ['id' => $kelas->id]);
    }

    public function test_admin_bisa_menambah_kelas_lengkap_dengan_wali_kelas(): void
    {
        $guru = $this->guru();

        $response = $this->actingAs($this->admin())->postJson(
            route('dashboard.admin.data-kelas.store'),
            $this->payload([
                'kelas' => '12 - 2',
                'wali_kelas_id' => $guru->id,
            ])
        );

        $response->assertCreated();

        // Spasi di sekitar tanda hubung dirapikan, jadi "12 - 2" jadi "12-2".
        $this->assertDatabaseHas('kelas', [
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'kelas' => '12-2',
            'wali_kelas_id' => $guru->id,
        ]);

        $tersimpan = $response->json('data.kelas');
        $this->assertSame('12-2', $tersimpan['kelas']);
        $this->assertSame('12', $tersimpan['tingkat']);
        $this->assertSame('2', $tersimpan['rombel']);
        $this->assertSame($guru->name, $tersimpan['wali_kelas']);
    }

    public function test_kelas_sama_boleh_terdaftar_di_jurusan_berbeda(): void
    {
        KelasRombel::create(['jurusan' => 'Rekayasa Perangkat Lunak', 'kelas' => '12-1']);

        $this->actingAs($this->admin())->postJson(
            route('dashboard.admin.data-kelas.store'),
            $this->payload([
                'jurusan' => 'Teknik Komputer Jaringan',
                'kelas' => '12-1',
            ])
        )->assertCreated();

        $this->assertDatabaseHas('kelas', [
            'jurusan' => 'Teknik Komputer Jaringan',
            'kelas' => '12-1',
        ]);
    }

    public function test_kelas_sama_tidak_boleh_terdaftar_dua_kali_dalam_satu_jurusan(): void
    {
        KelasRombel::create(['jurusan' => 'Rekayasa Perangkat Lunak', 'kelas' => '12-1']);

        $this->actingAs($this->admin())->postJson(
            route('dashboard.admin.data-kelas.store'),
            $this->payload()
        )->assertStatus(422)->assertJsonValidationErrors('kelas');
    }

    public function test_format_kelas_tidak_valid_ditolak(): void
    {
        $this->actingAs($this->admin())->postJson(
            route('dashboard.admin.data-kelas.store'),
            $this->payload(['kelas' => 'XII-1'])
        )->assertStatus(422)->assertJsonValidationErrors('kelas');
    }

    public function test_wali_kelas_harus_akun_guru(): void
    {
        $siswa = User::factory()->siswa('12-1')->create();

        $this->actingAs($this->admin())->postJson(
            route('dashboard.admin.data-kelas.store'),
            $this->payload(['wali_kelas_id' => $siswa->id])
        )->assertStatus(422)->assertJsonValidationErrors('wali_kelas_id');
    }

    public function test_admin_bisa_mengubah_wali_kelas_kelas_yang_masih_ada_siswanya(): void
    {
        $kelas = KelasRombel::create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'kelas' => '12-1',
        ]);

        $this->siswa('Rekayasa Perangkat Lunak', '12-1', 2);
        $guru = $this->guru('Siti Rahma, S.Pd.');

        $this->actingAs($this->admin())->putJson(
            route('dashboard.admin.data-kelas.update', $kelas),
            $this->payload(['wali_kelas_id' => $guru->id])
        )->assertOk();

        $this->assertDatabaseHas('kelas', [
            'id' => $kelas->id,
            'wali_kelas_id' => $guru->id,
        ]);
    }

    public function test_nama_kelas_yang_masih_ada_siswanya_tidak_bisa_diubah(): void
    {
        $kelas = KelasRombel::create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'kelas' => '12-1',
        ]);

        $this->siswa('Rekayasa Perangkat Lunak', '12-1');

        $this->actingAs($this->admin())->putJson(
            route('dashboard.admin.data-kelas.update', $kelas),
            $this->payload(['kelas' => '12-3'])
        )->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseHas('kelas', [
            'id' => $kelas->id,
            'kelas' => '12-1',
        ]);
    }

    public function test_kelas_yang_masih_ada_siswanya_tidak_bisa_dihapus(): void
    {
        $kelas = KelasRombel::create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'kelas' => '12-1',
        ]);

        $this->siswa('Rekayasa Perangkat Lunak', '12-1', 3);

        $this->actingAs($this->admin())->deleteJson(
            route('dashboard.admin.data-kelas.destroy', $kelas)
        )->assertStatus(422)->assertJsonPath('status', 'error');

        $this->assertDatabaseHas('kelas', ['id' => $kelas->id]);
    }

    public function test_siswa_dari_jurusan_lain_tidak_menghitung_kelas_yang_sama(): void
    {
        $kelas = KelasRombel::create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'kelas' => '12-1',
        ]);

        // Kelas "12-1" yang sama di jurusan lain tidak boleh ikut terhitung,
        // dan tidak ikut membuat kelas ini terkunci.
        $this->siswa('Teknik Komputer Jaringan', '12-1', 4);

        $this->assertTrue($kelas->bolehDihapus());

        $baris = $this->barisKelas($this->kelasData());
        $this->assertSame(0, $baris[0]['jumlah_siswa']);
    }

    public function test_kelas_terdaftar_belum_ada_siswanya_masuk_ke_filter_data_siswa(): void
    {
        // Dua rombel supaya tingkat 11 ini ber-rombel; kalau hanya ada satu
        // rombel, filter menampilkannya sebagai tingkat saja (aturan yang
        // sama dipakai di Data Siswa).
        KelasRombel::create(['jurusan' => 'Multimedia', 'kelas' => '11-1']);
        KelasRombel::create(['jurusan' => 'Multimedia', 'kelas' => '11-2']);

        $struktur = $this->strukturFilterSiswa();

        // Jurusan ini belum punya siswa sama sekali, tapi kelasnya harus tetap
        // muncul supaya admin bisa menyaring kelas yang baru disiapkan.
        $this->assertTrue($struktur->has('Multimedia'));

        $tingkat = collect($struktur->get('Multimedia')['tingkat'])->firstWhere('nilai', '11');
        $this->assertNotNull($tingkat);
        $this->assertTrue($tingkat['berrombel']);
        $this->assertSame(0, $tingkat['jumlah']);

        foreach (['11-1', '11-2'] as $rombel) {
            $item = collect($tingkat['rombel'])->firstWhere('nilai', $rombel);

            $this->assertNotNull($item, "Rombel {$rombel} tidak muncul di filter.");
            $this->assertSame(0, $item['jumlah']);
            $this->assertTrue($item['terdaftar']);
        }
    }

    public function test_kelas_dipakai_siswa_tetap_ditandai_belum_terdaftar(): void
    {
        // Siswa masuk ke kelas yang belum didaftarkan di Data Kelas, jadi
        // kelas itu harus tetap muncul di filter, ditandai belum terdaftar.
        $this->siswa('Rekayasa Perangkat Lunak', '12-1');
        $this->siswa('Rekayasa Perangkat Lunak', '12-2');

        $struktur = $this->strukturFilterSiswa();

        $jurusan = $struktur->get('Rekayasa Perangkat Lunak');
        $this->assertNotNull($jurusan);

        $tingkat = collect($jurusan['tingkat'])->firstWhere('nilai', '12');
        $this->assertNotNull($tingkat);
        $this->assertSame(2, $tingkat['jumlah']);

        foreach (['12-1', '12-2'] as $rombel) {
            $item = collect($tingkat['rombel'])->firstWhere('nilai', $rombel);

            $this->assertNotNull($item, "Rombel {$rombel} tidak muncul di filter.");
            $this->assertSame(1, $item['jumlah']);
            $this->assertFalse($item['terdaftar']);
        }
    }

    public function test_urutan_kelas_mengikuti_jurusan_lalu_tingkat_tertinggi(): void
    {
        foreach ([['RPL', '12-1'], ['RPL', '12-10'], ['RPL', '11'], ['TKJ', '12-2']] as [$jurusan, $kelas]) {
            KelasRombel::create(['jurusan' => $jurusan, 'kelas' => $kelas]);
        }

        $baris = $this->barisKelas($this->kelasData());

        // Jurusan dulu, lalu tingkat dari yang tertinggi, lalu nomor rombel
        // sebagai angka: "12-1" harus di atas "12-10".
        $this->assertSame(
            [
                'RPL|11',
                'RPL|12-1',
                'RPL|12-10',
                'TKJ|12-2',
            ],
            array_map(fn (array $b) => $b['jurusan'].'|'.$b['kelas'], $baris)
        );
    }

    public function test_export_mengunduh_xlsx_berisi_kelas_dan_jurusan(): void
    {
        $admin = $this->admin();
        KelasRombel::create(['jurusan' => 'Rekayasa Perangkat Lunak', 'kelas' => '12-1']);
        KelasRombel::create(['jurusan' => 'Rekayasa Perangkat Lunak', 'kelas' => '11-1']);

        $response = $this->actingAs($admin)
            ->get(route('dashboard.admin.data-kelas.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));

        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($response));

        $this->assertStringContainsString('Kelas', $isi);
        $this->assertStringContainsString('Jurusan', $isi);
        $this->assertStringContainsString('Rekayasa Perangkat Lunak', $isi);
        // Kelas tetap "12-1", bukan tanggal "12-Jan".
        $this->assertStringContainsString('12-1', $isi);
        $this->assertStringContainsString('11-1', $isi);
    }

    public function test_export_mengikuti_filter_yang_dipakai_di_browser(): void
    {
        $admin = $this->admin();
        KelasRombel::create(['jurusan' => 'RPL', 'kelas' => '12-1']);
        KelasRombel::create(['jurusan' => 'TKJ', 'kelas' => '12-2']);

        $response = $this->actingAs($admin)
            ->get(route('dashboard.admin.data-kelas.export', ['jurusan' => 'RPL']))
            ->assertOk();

        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($response));

        $this->assertStringContainsString('RPL', $isi);
        $this->assertStringNotContainsString('TKJ', $isi);
    }
}
