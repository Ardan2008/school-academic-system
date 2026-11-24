<?php

namespace Tests\Feature;

use App\Models\Jurusan;
use App\Models\User;
use App\Support\Kelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithSpreadsheet;
use Tests\TestCase;

class SiswaManagementTest extends TestCase
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

    protected function jurusanId(string $nama = 'Rekayasa Perangkat Lunak'): int
    {
        return (int) Jurusan::firstOrCreate(['nama' => $nama])->id;
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    protected function payload(array $override = []): array
    {
        if (isset($override['jurusan']) && ! is_numeric($override['jurusan'])) {
            $override['jurusan'] = $this->jurusanId((string) $override['jurusan']);
        }

        return array_merge([
            'nis' => '2025009999',
            'nama' => 'Alya Rahma',
            'email' => 'alya@saskansa.sch.id',
            'kelas' => '12-1',
            'jurusan' => $this->jurusanId(),
            'status' => 'Aktif',
            'password' => '',
        ], $override);
    }

    /**
     * @param  array<int, string>  $kelas
     */
    protected function siswaRombel(string $jurusan, array $kelas): void
    {
        foreach ($kelas as $nomor => $value) {
            User::factory()->siswa($value)->create([
                'name' => "Siswa {$jurusan} {$value} {$nomor}",
                'jurusan' => $jurusan,
            ]);
        }
    }

    /**
     * Ambil data yang benar-benar dikirim ke browser, supaya yang diuji adalah
     * isi filter yang dilihat admin, bukan hanya nilai di dalam controller.
     *
     * @return array<string, mixed>
     */
    protected function siswaData(): array
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-siswa.index'));

        $response->assertOk();

        $this->assertSame(1, preg_match(
            '/^\s*const data = (.+);$/m',
            $response->getContent(),
            $match
        ), 'Payload siswa tidak ditemukan di halaman Data Siswa.');

        return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function strukturJurusan(string $jurusan): array
    {
        $struktur = $this->siswaData()['struktur_kelas'];

        foreach ($struktur as $item) {
            if ($item['nama'] === $jurusan) {
                return $item['tingkat'];
            }
        }

        $this->fail("Jurusan {$jurusan} tidak ada di pilihan filter.");
    }

    /**
     * @param  array<int, array<string, mixed>>  $tingkat
     * @return array<string, mixed>
     */
    protected function tingkat(array $tingkat, string $nilai): array
    {
        foreach ($tingkat as $item) {
            if ($item['nilai'] === $nilai) {
                return $item;
            }
        }

        $this->fail("Tingkat {$nilai} tidak ada di pilihan filter.");
    }

    /**
     * @param  array<int, array<string, mixed>>  $tingkat
     * @return array<int, string>
     */
    protected function nilaiRombel(array $tingkat, string $nilai): array
    {
        return array_column($this->tingkat($tingkat, $nilai)['rombel'], 'nilai');
    }

    public function test_halaman_data_siswa_hanya_untuk_admin(): void
    {
        $this->get(route('dashboard.admin.data-siswa.index'))->assertRedirect(route('login'));

        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)
            ->get(route('dashboard.admin.data-siswa.index'))
            ->assertForbidden();
    }

    /**
     * Export memuat password awal, jadi harus dikunci ketat: hanya admin.
     * Kalau route ini bocor, semua password siswa bisa diunduh siapa saja.
     */
    public function test_export_xlsx_hanya_bisa_diakses_admin(): void
    {
        User::factory()->siswa('12-1')->create([
            'email' => 'siswa-contoh@example.com',
            'initial_password' => Crypt::encryptString('PasswordRahasia1'),
            'must_change_password' => true,
        ]);

        $this->get(route('dashboard.admin.data-siswa.export'))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->guru()->create())
            ->get(route('dashboard.admin.data-siswa.export'))
            ->assertForbidden();

        $this->actingAs(User::factory()->siswa('12-1')->create())
            ->get(route('dashboard.admin.data-siswa.export'))
            ->assertForbidden();
    }

    public function test_halaman_data_siswa_menampilkan_statistik_dari_database(): void
    {
        $admin = $this->admin();

        User::factory()->siswa('12-1')->create(['name' => 'Budi Santoso', 'status' => User::STATUS_AKTIF]);
        User::factory()->siswa('11-1')->create(['name' => 'Citra Lestari', 'status' => User::STATUS_AKTIF]);
        User::factory()->siswa('12-1')->create(['name' => 'Dimas Adi', 'status' => User::STATUS_NONAKTIF]);
        User::factory()->siswa('12-1')->create(['name' => 'Nabila Putri', 'status' => User::STATUS_LULUS]);
        User::factory()->guru()->create(['name' => 'Guru fission']);

        $this->actingAs($admin)
            ->get(route('dashboard.admin.data-siswa.index'))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('Citra Lestari')
            ->assertSee('Dimas Adi')
            ->assertSee('Nabila Putri')
            ->assertDontSee('Guru fission')
            ->assertSee('Total Siswa')
            ->assertSee('Siswa Aktif')
            ->assertSee('Siswa Nonaktif')
            ->assertSee('Siswa Lulus')
            ->assertSee('data-stat="lulus" data-count="1"', false);
    }

    public function test_kelas_tingkat_diekstrak_dari_format_kelas(): void
    {
        $this->assertSame('12', Kelas::tingkat('12-1'));
        $this->assertSame('12', Kelas::tingkat('12'));
        $this->assertSame('11', Kelas::tingkat('11-2'));
        $this->assertSame('10', Kelas::tingkat('10'));
        $this->assertNull(Kelas::tingkat('12A'));
        $this->assertNull(Kelas::tingkat(null));
    }

    public function test_nomor_rombel_diekstrak_dari_format_kelas(): void
    {
        $this->assertSame('1', Kelas::rombel('12-1'));
        $this->assertSame('10', Kelas::rombel('12-10'));
        $this->assertSame('2', Kelas::rombel(' 11 - 2 '));
        // Kelas yang ditulis tingkat saja tidak punya nomor rombel.
        $this->assertNull(Kelas::rombel('12'));
        $this->assertNull(Kelas::rombel('12A'));
        $this->assertNull(Kelas::rombel(null));
    }

    public function test_kelas_hanya_diterima_bila_angka(): void
    {
        foreach (['12', '12-1', '10-2'] as $kelas) {
            $this->assertTrue(Kelas::isValid($kelas), $kelas.' seharusnya valid');
        }

        // Kelas ditulis angka saja. Selain karena bisa diurutkan dan difilter,
        // "XII" dan "12" yang bercampur akan jadi dua kelas yang berbeda.
        foreach (['XII', 'xii', 'XII-1', 'xi-1', 'X', 'XIII', '12A', '12-1-1', '12-', '12 1'] as $kelas) {
            $this->assertFalse(Kelas::isValid($kelas), $kelas.' seharusnya tidak valid');
        }
    }

    public function test_kelas_tidak_perlu_disimpan_dalam_bentuk_lain(): void
    {
        $admin = $this->admin();

        User::factory()->create([
            'role' => 'siswa',
            'email' => 'angka@example.com',
            'kelas' => '12-1',
            'status' => User::STATUS_AKTIF,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.admin.data-siswa.index'))
            ->assertOk()
            ->assertSee('"kelas":"12-1"', false)
            // Tidak ada representasi lain yang perlu dikirim ke frontend: yang
            // tampil di layar sama persis dengan yang tersimpan.
            ->assertDontSee('kelas_tampil', false);

        $this->actingAs($admin)
            ->postJson(route('dashboard.admin.data-siswa.store'), $this->payload(['kelas' => 'XII-1']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('kelas');
    }

    public function test_kelas_boleh_tingkat_saja_saat_satu_tingkat_satu_rombel(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('dashboard.admin.data-siswa.store'), $this->payload(['kelas' => '12']))
            ->assertOk()
            ->assertJsonPath('data.siswa.kelas', '12')
            ->assertJsonPath('data.siswa.tingkat', '12');
    }

    public function test_kelas_rombel_tetap_diterima(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('dashboard.admin.data-siswa.store'), $this->payload(['kelas' => '12-1']))
            ->assertOk()
            ->assertJsonPath('data.siswa.kelas', '12-1')
            ->assertJsonPath('data.siswa.tingkat', '12');
    }

    public function test_filter_tingkat_selalu_menampilkan_kelas_sampai_kelas_10(): void
    {
        $this->assertSame(['12', '11', '10'], Kelas::tingkatUmum());

        $admin = $this->admin();

        User::factory()->siswa('12-1')->create(['name' => 'Budi Santoso']);
        User::factory()->siswa('11-2')->create(['name' => 'Citra Lestari']);

        // Tingkat 10 belum punya siswa, tapi opsinya tetap harus muncul.
        $this->actingAs($admin)
            ->get(route('dashboard.admin.data-siswa.index'))
            ->assertOk()
            ->assertSee('"tingkat_umum":["12","11","10"]', false)
            ->assertSee('"tingkat":"12"', false)
            ->assertSee('"tingkat":"11"', false);
    }

    public function test_pilihan_rombel_mengikuti_jumlah_kelas_di_setiap_tingkat(): void
    {
        $admin = $this->admin();

        $this->siswaRombel('Rekayasa Perangkat Lunak', ['10-1', '10-2']);
        $this->siswaRombel('Rekayasa Perangkat Lunak', ['11']);
        $this->siswaRombel('Rekayasa Perangkat Lunak', ['12-1', '12-2', '12-3']);

        $rpl = $this->strukturJurusan('Rekayasa Perangkat Lunak');

        // Tingkat dengan lebih dari satu kelas tampilkan nomor rombel.
        $this->assertSame(['12-1', '12-2', '12-3'], $this->nilaiRombel($rpl, '12'));
        $this->assertSame(['10-1', '10-2'], $this->nilaiRombel($rpl, '10'));

        // Tingkat dengan satu kelas saja cukup tampilkan nomor tingkatnya.
        $this->assertSame(['11'], $this->nilaiRombel($rpl, '11'));
        $this->assertFalse($this->tingkat($rpl, '11')['berrombel']);
        $this->assertTrue($this->tingkat($rpl, '12')['berrombel']);
    }

    public function test_pilihan_kelas_berbeda_untuk_tiap_jurusan(): void
    {
        $admin = $this->admin();

        $this->siswaRombel('Rekayasa Perangkat Lunak', ['10-1', '10-2']);
        $this->siswaRombel('Teknik Komputer Jaringan', ['10', '11-1', '12-1', '12-2']);

        $rpl = $this->strukturJurusan('Rekayasa Perangkat Lunak');
        $tkj = $this->strukturJurusan('Teknik Komputer Jaringan');

        $this->assertSame(['12', '11', '10'], array_column($rpl, 'nilai'));
        $this->assertSame(['12', '11', '10'], array_column($tkj, 'nilai'));

        $this->assertSame(['10-1', '10-2'], $this->nilaiRombel($rpl, '10'));
        $this->assertSame(['10'], $this->nilaiRombel($tkj, '10'));

        // Tingkat 11 TKJ cuma punya satu kelas, jadi tampilnya "11" walau
        // kelas itu tersimpan sebagai "11-1".
        $this->assertSame(['11'], $this->nilaiRombel($tkj, '11'));
        $this->assertSame(['12-1', '12-2'], $this->nilaiRombel($tkj, '12'));
    }

    public function test_siswa_tanpa_jurusan_tidak_muncul_sebagai_pilihan_filter(): void
    {
        $admin = $this->admin();

        $this->siswaRombel('Rekayasa Perangkat Lunak', ['12-1']);
        User::factory()->siswa('12-1')->create(['name' => 'Tanpa Jurusan', 'jurusan' => null]);

        $data = $this->siswaData();

        $this->assertSame(
            ['Rekayasa Perangkat Lunak'],
            array_column($data['struktur_kelas'], 'nama')
        );
    }

    public function test_halaman_data_siswa_menampilkan_pilihan_jurusan_tingkat_dan_rombel(): void
    {
        $admin = $this->admin();

        $this->siswaRombel('Rekayasa Perangkat Lunak', ['10-1', '10-2']);

        $this->actingAs($admin)
            ->get(route('dashboard.admin.data-siswa.index'))
            ->assertOk()
            ->assertSee('id="siswa-filter-jurusan"', false)
            ->assertSee('id="siswa-filter-tingkat"', false)
            ->assertSee('id="siswa-filter-rombel"', false)
            ->assertSee('Tingkat / Kelas', false)
            ->assertSee('Kelas / Rombel', false)
            // Tingkat dan rombel terkunci sampai jurusan dipilih.
            ->assertSee('id="siswa-filter-tingkat" data-filter-tingkat disabled', false)
            ->assertSee('id="siswa-filter-rombel" data-filter-rombel disabled', false)
            ->assertSee('data-filter-summary', false);
    }

    public function test_form_siswa_mengambil_pilihan_jurusan_dari_database(): void
    {
        $rpl = Jurusan::create(['nama' => 'Rekayasa Perangkat Lunak']);
        $tkj = Jurusan::create(['nama' => 'Teknik Komputer Jaringan']);

        $this->assertSame([
            ['id' => $rpl->id, 'nama' => 'Rekayasa Perangkat Lunak'],
            ['id' => $tkj->id, 'nama' => 'Teknik Komputer Jaringan'],
        ], $this->siswaData()['jurusan_opsi']);

        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-siswa.index'))
            ->assertOk()
            ->assertSee('id="sm-jurusan"', false)
            ->assertSee('value="'.$rpl->id.'">Rekayasa Perangkat Lunak</option>', false)
            ->assertSee('Pilih jurusan yang sudah terdaftar.', false);
    }

    public function test_form_siswa_menampilkan_pesan_ketika_belum_ada_jurusan(): void
    {
        $this->assertSame([], $this->siswaData()['jurusan_opsi']);

        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-siswa.index'))
            ->assertOk()
            ->assertSee('id="sm-jurusan" name="jurusan" required disabled', false)
            ->assertSee('Data jurusan belum tersedia', false)
            ->assertSee('Belum ada jurusan terdaftar.', false);
    }

    public function test_penyimpanan_siswa_menolak_jurusan_yang_tidak_terdaftar(): void
    {
        Jurusan::create(['nama' => 'Rekayasa Perangkat Lunak']);

        $this->actingAs($this->admin())
            ->postJson(route('dashboard.admin.data-siswa.store'), $this->payload(['jurusan' => 999999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('jurusan');

        $this->assertDatabaseMissing('users', ['email' => 'alya@saskansa.sch.id']);
    }

    public function test_status_lulus_menonaktifkan_akun(): void
    {
        $siswa = User::factory()->siswa()->create(['status' => User::STATUS_LULUS]);

        $this->assertFalse($siswa->fresh()->is_active);
    }

    public function test_status_nonaktif_tetap_menonaktifkan_akun(): void
    {
        $siswa = User::factory()->siswa()->create(['is_active' => false]);

        $this->assertSame(User::STATUS_NONAKTIF, $siswa->fresh()->statusLabel());
    }

    public function test_halaman_data_siswa_menyiapkan_token_dan_url_api_yang_benar(): void
    {
        $admin = $this->admin();
        User::factory()->siswa('12-1')->create();

        $baseUrl = str_replace('/', '\/', route('dashboard.admin.data-siswa.index'));

        $this->actingAs($admin)
            ->get(route('dashboard.admin.data-siswa.index'))
            ->assertOk()
            ->assertSee('name="_token"', false)
            ->assertSee('const baseUrl = "'.$baseUrl.'";', false)
            ->assertDontSee($baseUrl.$baseUrl, false);
    }

    public function test_admin_dapat_menambah_siswa_dengan_password_otomatis(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->postJson(route('dashboard.admin.data-siswa.store'), $this->payload());

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.siswa.nis', '2025009999')
            ->assertJsonPath('data.siswa.nama', 'Alya Rahma')
            ->assertJsonPath('data.siswa.kelas', '12-1')
            ->assertJsonPath('data.siswa.status', 'Aktif');

        $password = $response->json('data.password');
        $this->assertNotEmpty($password);

        $siswa = User::where('email', 'alya@saskansa.sch.id')->firstOrFail();

        $this->assertSame('siswa', $siswa->role);
        $this->assertTrue($siswa->is_active);
        $this->assertTrue($siswa->must_change_password);
        $this->assertTrue(Hash::check($password, $siswa->password));
        $this->assertSame($password, Crypt::decryptString($siswa->initial_password));
        $this->assertNull($siswa->nip);
        $this->assertSame('Rekayasa Perangkat Lunak', $siswa->jurusan);
    }

    public function test_admin_dapat_menambah_siswa_dengan_password_tertentu(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson(route('dashboard.admin.data-siswa.store'), $this->payload([
                'password' => 'Rahma1234',
            ]))
            ->assertOk()
            ->assertJsonPath('data.password', 'Rahma1234');

        $this->assertTrue(Hash::check('Rahma1234', User::where('email', 'alya@saskansa.sch.id')->firstOrFail()->password));
    }

    public function test_penyimpanan_menolak_nis_dan_email_duplikat(): void
    {
        $admin = $this->admin();

        User::factory()->siswa()->create([
            'nis' => '2025009999',
            'email' => 'alya@saskansa.sch.id',
        ]);

        $this->actingAs($admin)
            ->postJson(route('dashboard.admin.data-siswa.store'), $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nis', 'email']);
    }

    public function test_penyimpanan_menolak_password_lemah(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('dashboard.admin.data-siswa.store'), $this->payload(['password' => 'lemah']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_password_otomatis_berbeda_antar_siswa(): void
    {
        $admin = $this->admin();

        $passwordPertama = $this->actingAs($admin)
            ->postJson(route('dashboard.admin.data-siswa.store'), $this->payload())
            ->assertOk()
            ->json('data.password');

        $passwordKedua = $this->actingAs($admin)
            ->postJson(route('dashboard.admin.data-siswa.store'), $this->payload([
                'nis' => '2025001001',
                'nama' => 'Budi Santoso',
                'email' => 'budi@saskansa.sch.id',
            ]))
            ->assertOk()
            ->json('data.password');

        $this->assertNotSame($passwordPertama, $passwordKedua);
    }

    public function test_password_penentu_yang_bentrok_diganti_otomatis(): void
    {
        User::factory()->siswa()->withPassword('Rahma1234')->create([
            'nis' => '2025000001',
            'email' => 'sudah-ada@saskansa.sch.id',
        ]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('dashboard.admin.data-siswa.store'), $this->payload([
                // Nilai ini sama dengan password awal siswa lain, jadi harus
                // ditolak dan diganti oleh sistem.
                'password' => 'Rahma1234',
            ]))
            ->assertOk();

        $this->assertNotSame('Rahma1234', $response->json('data.password'));
        $this->assertSame($response->json('data.password'), Crypt::decryptString(
            User::where('email', 'alya@saskansa.sch.id')->firstOrFail()->initial_password
        ));
    }

    public function test_kelas_harus_pakai_format_kelas_yang_dikenal(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson(route('dashboard.admin.data-siswa.store'), $this->payload(['kelas' => '12A']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('kelas');

        $this->actingAs($admin)
            ->postJson(route('dashboard.admin.data-siswa.store'), $this->payload(['kelas' => 'XII-1']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('kelas');
    }

    public function test_kelas_dinormalisasi(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('dashboard.admin.data-siswa.store'), $this->payload(['kelas' => ' 12 - 1 ']))
            ->assertOk()
            ->assertJsonPath('data.siswa.kelas', '12-1');
    }

    public function test_admin_dapat_mengubah_siswa_dengan_metode_put(): void
    {
        $admin = $this->admin();
        $siswa = User::factory()->siswa('12-1')->create([
            'nis' => '2025001111',
            'name' => 'Nama Lama',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->putJson(route('dashboard.admin.data-siswa.update', $siswa), $this->payload([
                'nis' => '2025002222',
                'nama' => 'Nama Baru',
                'status' => 'Nonaktif',
            ]))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.siswa.nis', '2025002222')
            ->assertJsonPath('data.siswa.nama', 'Nama Baru')
            ->assertJsonPath('data.siswa.status', 'Nonaktif');

        $siswa->refresh();

        $this->assertSame('2025002222', $siswa->nis);
        $this->assertSame('Nama Baru', $siswa->name);
        $this->assertSame(User::STATUS_NONAKTIF, $siswa->status);
        $this->assertFalse($siswa->is_active);
    }

    public function test_admin_dapat_menandai_siswa_sebagai_lulus(): void
    {
        $admin = $this->admin();
        $siswa = User::factory()->siswa()->create(['status' => User::STATUS_AKTIF]);

        $this->actingAs($admin)
            ->putJson(route('dashboard.admin.data-siswa.update', $siswa), $this->payload([
                'nis' => $siswa->nis,
                'email' => $siswa->email,
                'status' => User::STATUS_LULUS,
            ]))
            ->assertOk()
            ->assertJsonPath('data.siswa.status', User::STATUS_LULUS);

        $this->assertSame(User::STATUS_LULUS, $siswa->fresh()->status);
        $this->assertFalse($siswa->fresh()->is_active);
    }

    public function test_status_tidak_dikenal_ditolak(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('dashboard.admin.data-siswa.store'), $this->payload(['status' => 'Pindah']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_password_tidak_diubah_bila_kolom_kosong(): void
    {
        $admin = $this->admin();
        $siswa = User::factory()->siswa()->create(['password' => 'Awal1234']);

        $this->actingAs($admin)
            ->putJson(route('dashboard.admin.data-siswa.update', $siswa), $this->payload([
                'nis' => $siswa->nis,
                'email' => $siswa->email,
            ]))
            ->assertOk()
            ->assertJsonPath('data.password', null);

        $this->assertTrue(Hash::check('Awal1234', $siswa->fresh()->password));
    }

    public function test_admin_dapat_menghapus_siswa_dengan_metode_delete(): void
    {
        $admin = $this->admin();
        $siswa = User::factory()->siswa()->create();

        $this->actingAs($admin)
            ->deleteJson(route('dashboard.admin.data-siswa.destroy', $siswa))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $siswa->id);

        $this->assertDatabaseMissing('users', ['id' => $siswa->id]);
    }

    public function test_akun_bukan_siswa_tidak_bisa_diubah_atau_dihapus(): void
    {
        $admin = $this->admin();
        $guru = User::factory()->guru()->create();

        $this->actingAs($admin)
            ->putJson(route('dashboard.admin.data-siswa.update', $guru), $this->payload())
            ->assertNotFound();

        $this->actingAs($admin)
            ->deleteJson(route('dashboard.admin.data-siswa.destroy', $guru))
            ->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $guru->id]);
    }

    /**
     * Password awal dibuat sistem, dan nilainya perlu diserahkan ke siswa,
     * jadi harus ikut terunduh. Password yang sudah diganti siswa tidak lagi
     * ditampilkan karena nilai aslinya sudah tidak tersimpan.
     */
    public function test_export_xlsx_memuat_password_awal_siswa(): void
    {
        $admin = $this->admin();

        $siswa = User::factory()->siswa('12-1')->create([
            'name' => 'Ahmad Fauzi',
            'nis' => '2201',
            'email' => 'ahmad@example.com',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'initial_password' => Crypt::encryptString('SiswaA1'),
            'must_change_password' => true,
        ]);

        $sudahDiubah = User::factory()->siswa('12-2')->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'initial_password' => Crypt::encryptString('SiswaB1'),
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('dashboard.admin.data-siswa.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('Pragma', 'no-cache');

        // Framework menormalkan urutan direktifnya, jadi yang dicek adalah
        // no-store/no-cache-nya, bukan urutan persisnya.
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));

        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));

        $bukuKerja = $this->gabungBukuKerja($this->bacaBukuKerja($response));

        $this->assertStringContainsString('Password Awal', $bukuKerja);
        $this->assertStringContainsString('Status Password', $bukuKerja);
        $this->assertStringContainsString('SiswaA1', $bukuKerja);
        $this->assertStringContainsString('Belum ubah password', $bukuKerja);

        // Password yang sudah diganti tidak boleh muncul lagi.
        $this->assertStringNotContainsString('SiswaB1', $bukuKerja);
        $this->assertStringContainsString('Sudah ubah password', $bukuKerja);
        $this->assertStringContainsString($sudahDiubah->email, $bukuKerja);
        $this->assertStringContainsString($siswa->email, $bukuKerja);

        // Kelas tidak boleh berubah menjadi bentuk tanggal seperti "12-Jan".
        $this->assertStringContainsString('12-1', $bukuKerja);
        $this->assertStringContainsString('12-2', $bukuKerja);
    }

    /**
     * Password tidak boleh ikut terkirim ke halaman Data Siswa, karena kalau
     * begitu seluruh password siswa bisa dibaca dari View Source.
     */
    public function test_halaman_data_siswa_tidak_mengirim_password(): void
    {
        $admin = $this->admin();

        User::factory()->siswa('12-1')->create([
            'initial_password' => Crypt::encryptString('RahasiaSiswa1'),
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('dashboard.admin.data-siswa.index'))
            ->assertOk();

        $this->assertStringNotContainsString('RahasiaSiswa1', $response->getContent());
        $this->assertStringNotContainsString('initial_password', $response->getContent());
    }

    public function test_export_xlsx_hanya_mengunduh_siswa_yang_lolos_filter(): void
    {
        $admin = $this->admin();

        User::factory()->siswa('12-1')->create([
            'name' => 'Ahmad Fauzi',
            'email' => 'ahmad@example.com',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'initial_password' => Crypt::encryptString('SiswaA1'),
            'must_change_password' => true,
        ]);

        User::factory()->siswa('11-1')->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'jurusan' => 'Teknik Komputer Jaringan',
            'initial_password' => Crypt::encryptString('SiswaB1'),
            'must_change_password' => true,
        ]);

        $csv = $this->gabungBukuKerja($this->bacaBukuKerja($this->actingAs($admin)
            ->get(route('dashboard.admin.data-siswa.export', ['jurusan' => 'Rekayasa Perangkat Lunak']))
            ->assertOk()));

        $this->assertStringContainsString('SiswaA1', $csv);
        $this->assertStringContainsString('ahmad@example.com', $csv);
        $this->assertStringNotContainsString('SiswaB1', $csv);
        $this->assertStringNotContainsString('budi@example.com', $csv);
    }

    /**
     * Tingkat dan rombel harus disaring sama seperti di browser. Tingkat yang
     * hanya punya satu kelas tidak punya nomor rombel, jadi memilih rombel
     * berarti memilih semua kelas di tingkat itu.
     */
    public function test_export_xlsx_mengikuti_filter_tingkat_dan_rombel(): void
    {
        $admin = $this->admin();

        foreach ([['Ahmad', '12-1'], ['Budi', '12-2'], ['Citra', '11-1']] as [$nama, $kelas]) {
            User::factory()->siswa($kelas)->create([
                'name' => $nama,
                'email' => strtolower($nama).'@example.com',
                'jurusan' => 'Rekayasa Perangkat Lunak',
                'initial_password' => Crypt::encryptString('Pw'.$nama),
                'must_change_password' => true,
            ]);
        }

        $unduh = fn (array $param) => $this->gabungBukuKerja($this->bacaBukuKerja($this->actingAs($admin)
            ->get(route('dashboard.admin.data-siswa.export', $param))
            ->assertOk()));

        // Rombel sungguhan: hanya 12-1.
        $rombel = $unduh(['jurusan' => 'Rekayasa Perangkat Lunak', 'tingkat' => '12', 'rombel' => '12-1', 'rombel_match' => 'kelas']);
        $this->assertStringContainsString('PwAhmad', $rombel);
        $this->assertStringNotContainsString('PwBudi', $rombel);

        // Tingkat 12 tanpa rombel: keduanya ikut.
        $tingkat = $unduh(['jurusan' => 'Rekayasa Perangkat Lunak', 'tingkat' => '12']);
        $this->assertStringContainsString('PwAhmad', $tingkat);
        $this->assertStringContainsString('PwBudi', $tingkat);
        $this->assertStringNotContainsString('PwCitra', $tingkat);

        // Tingkat yang hanya punya satu kelas: rombelnya sama dengan tingkat.
        $tunggal = $unduh(['jurusan' => 'Rekayasa Perangkat Lunak', 'tingkat' => '11', 'rombel' => '11', 'rombel_match' => 'tingkat']);
        $this->assertStringContainsString('PwCitra', $tunggal);
        $this->assertStringNotContainsString('PwAhmad', $tunggal);
    }

    /**
     * Jumlah baris dikirim lewat header supaya tombol export bisa menampilkan
     * "berapa siswa yang tersimpan" tanpa mengurai file di browser.
     */
    public function test_export_xlsx_mengirim_jumlah_baris(): void
    {
        $admin = $this->admin();

        foreach (range(1, 3) as $nomor) {
            User::factory()->siswa('12-1')->create([
                'name' => "Siswa Number {$nomor}",
                'email' => "siswa{$nomor}@example.com",
            ]);
        }

        $semua = $this->actingAs($admin)
            ->get(route('dashboard.admin.data-siswa.export'))
            ->assertOk();

        $this->assertSame('3', $semua->headers->get('X-Total-Siswa'));

        $difilter = $this->actingAs($admin)
            ->get(route('dashboard.admin.data-siswa.export', ['q' => 'tidak-ada']))
            ->assertOk();

        $this->assertSame('0', $difilter->headers->get('X-Total-Siswa'));
    }

    public function test_export_xlsx_mengikuti_pencarian_dan_status(): void
    {
        $admin = $this->admin();

        User::factory()->siswa('12-1')->create([
            'name' => 'Ahmad Fauzi',
            'email' => 'ahmad@example.com',
            'initial_password' => Crypt::encryptString('SiswaA1'),
            'must_change_password' => true,
        ]);

        $nonaktif = User::factory()->siswa('12-1')->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'status' => User::STATUS_NONAKTIF,
            'initial_password' => Crypt::encryptString('SiswaB1'),
            'must_change_password' => true,
        ]);

        $unduh = fn (array $param) => $this->gabungBukuKerja($this->bacaBukuKerja($this->actingAs($admin)
            ->get(route('dashboard.admin.data-siswa.export', $param))
            ->assertOk()));

        $cari = $unduh(['q' => 'ahmad']);
        $this->assertStringContainsString('SiswaA1', $cari);
        $this->assertStringNotContainsString('SiswaB1', $cari);

        $aktif = $unduh(['status' => User::STATUS_AKTIF]);
        $this->assertStringContainsString('SiswaA1', $aktif);
        $this->assertStringNotContainsString('SiswaB1', $aktif);

        $nonaktifCsv = $unduh(['status' => User::STATUS_NONAKTIF]);
        $this->assertStringContainsString('SiswaB1', $nonaktifCsv);
        $this->assertStringNotContainsString('SiswaA1', $nonaktifCsv);
        $this->assertStringContainsString($nonaktif->email, $nonaktifCsv);
    }
}
