<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\XlsxExportService;
use App\Support\Credentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Tests\Concerns\InteractsWithSpreadsheet;
use Tests\TestCase;

class AkunManagerTest extends TestCase
{
    use InteractsWithSpreadsheet;
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create([
            'email' => 'administrator@example.com',
            'role' => 'admin',
            'must_change_password' => false,
        ]);
    }

    protected function userWithInitialPassword(string $email, string $role, string $kelas, string $password, ?string $jurusan = null): User
    {
        return User::factory()->create([
            'email' => $email,
            'role' => $role,
            'kelas' => $kelas,
            'jurusan' => $jurusan,
            'password' => 'Lama123',
            'initial_password' => Crypt::encryptString($password),
            'must_change_password' => true,
        ]);
    }

    protected function siswa(string $email, string $jurusan, string $kelas, string $password = 'SiswaA1'): User
    {
        return $this->userWithInitialPassword($email, 'siswa', $kelas, $password, $jurusan);
    }

    public function test_akun_page_requires_authentication(): void
    {
        $this->get(route('dashboard.admin.manajemen-akun.index'))->assertRedirect(route('login'));
    }

    public function test_akun_page_is_admin_only(): void
    {
        $guru = User::factory()->create(['email' => 'guru@example.com', 'role' => 'guru']);

        $this->actingAs($guru)->get(route('dashboard.admin.manajemen-akun.index'))->assertForbidden();
        $this->actingAs($guru)->get(route('dashboard.admin.manajemen-akun.slips'))->assertForbidden();
        $this->actingAs($guru)->get(route('dashboard.admin.manajemen-akun.export-csv'))->assertForbidden();
    }

    public function test_akun_index_renders_and_filters_users(): void
    {
        $admin = $this->admin();
        $this->userWithInitialPassword('siswa-a@example.com', 'siswa', '12-1', 'SiswaA1');
        $this->userWithInitialPassword('guru-a@example.com', 'guru', '11-1', 'GuruA1');

        $this->actingAs($admin)
            ->get(route('dashboard.admin.manajemen-akun.index'))
            ->assertOk()
            ->assertSee('Manajemen Akun')
            ->assertSee('siswa-a@example.com')
            ->assertSee('guru-a@example.com');

        $this->actingAs($admin)
            ->get(route('dashboard.admin.manajemen-akun.index', ['kelas' => '12-1']))
            ->assertOk()
            ->assertSee('siswa-a@example.com')
            ->assertDontSee('guru-a@example.com');
    }

    public function test_akun_halaman_menampilkan_dropdown_jurusan(): void
    {
        $admin = $this->admin();
        $this->siswa('rpl@example.com', 'Rekayasa Perangkat Lunak', '12-1');
        $this->siswa('tkj@example.com', 'Teknik Komputer Jaringan', '12-1');

        $this->actingAs($admin)
            ->get(route('dashboard.admin.manajemen-akun.index'))
            ->assertOk()
            ->assertSee('Semua Jurusan')
            ->assertSee('Rekayasa Perangkat Lunak')
            ->assertSee('Teknik Komputer Jaringan');
    }

    public function test_akun_filter_jurusan_hanya_menampilkan_akun_jurusan_tersebut(): void
    {
        $admin = $this->admin();
        $this->siswa('rpl@example.com', 'Rekayasa Perangkat Lunak', '12-1');
        $this->siswa('tkj@example.com', 'Teknik Komputer Jaringan', '12-1');

        $this->actingAs($admin)
            ->get(route('dashboard.admin.manajemen-akun.index', ['jurusan' => 'Rekayasa Perangkat Lunak']))
            ->assertOk()
            ->assertSee('rpl@example.com')
            ->assertDontSee('tkj@example.com');
    }

    public function test_akun_filter_jurusan_tingkat_dan_kelas_berantai(): void
    {
        $admin = $this->admin();
        $this->siswa('rpl1@example.com', 'Rekayasa Perangkat Lunak', '12-1');
        $this->siswa('rpl2@example.com', 'Rekayasa Perangkat Lunak', '12-2');
        $this->siswa('tkj1@example.com', 'Teknik Komputer Jaringan', '12-1');

        $this->actingAs($admin)
            ->get(route('dashboard.admin.manajemen-akun.index', [
                'jurusan' => 'Rekayasa Perangkat Lunak',
                'tingkat' => '12',
                'kelas' => '12-2',
            ]))
            ->assertOk()
            ->assertSee('rpl2@example.com')
            ->assertDontSee('rpl1@example.com')
            ->assertDontSee('tkj1@example.com');
    }

    public function test_akun_filter_tingkat_mencakup_kelas_tunggal_dan_ber_rombel(): void
    {
        $admin = $this->admin();
        // Satu kelas satu tingkat ("10") dan kelas ber-rombel ("10-1") sama-sama
        // tingkat 10, jadi filter tingkat 10 ikut menampilkan keduanya.
        $this->siswa('satu@example.com', 'Multimedia', '10');
        $this->siswa('sepuluh@example.com', 'Multimedia', '10-1');
        $this->siswa('sebelas@example.com', 'Multimedia', '11');

        $this->actingAs($admin)
            ->get(route('dashboard.admin.manajemen-akun.index', ['tingkat' => '10']))
            ->assertOk()
            ->assertSee('satu@example.com')
            ->assertSee('sepuluh@example.com')
            ->assertDontSee('sebelas@example.com');
    }

    public function test_akun_export_mengikuti_filter_jurusan(): void
    {
        $admin = $this->admin();
        $this->siswa('rpl@example.com', 'Rekayasa Perangkat Lunak', '12-1');
        $this->siswa('tkj@example.com', 'Teknik Komputer Jaringan', '12-1');

        $response = $this->actingAs($admin)
            ->get(route('dashboard.admin.manajemen-akun.export-csv', ['jurusan' => 'Rekayasa Perangkat Lunak']))
            ->assertOk();

        $content = $this->gabungBukuKerja($this->bacaBukuKerja($response));

        $this->assertStringContainsString('rpl@example.com', $content);
        $this->assertStringNotContainsString('tkj@example.com', $content);
    }

    public function test_akun_export_memberi_border_pada_area_tabel(): void
    {
        $admin = $this->admin();
        $this->siswa('border@example.com', 'Multimedia', '10-1');

        $ws = $this->bukaLembar($this->actingAs($admin)
            ->get(route('dashboard.admin.manajemen-akun.export-csv'))
            ->assertOk());

        // Ada subjudul, jadi judul ada di baris 1, subjudul baris 2, header baris 3.
        $header = 3;

        $this->assertSame('Role', $ws->getCell('A'.$header)->getValue());
        $this->assertSame('Status', $ws->getCell('F'.$header)->getValue());

        $barisTerakhir = $ws->getHighestRow();

        // Tepi luar tabel ditebalkan (primary gelap) di keempat sisi, mulai
        // dari header sampai baris data terakhir.
        foreach ([
            'A'.$header.' atas' => ['top', $header],
            'F'.$header.' kanan' => ['right', $header],
            'A'.$barisTerakhir.' kiri' => ['left', $barisTerakhir],
            'F'.$barisTerakhir.' bawah' => ['bottom', $barisTerakhir],
        ] as $keterangan => [$sisi, $baris]) {
            $garis = $sisi === 'top' || $sisi === 'left'
                ? $ws->getStyle('A'.$baris)->getBorders()->{'get'.ucfirst($sisi)}()
                : $ws->getStyle('F'.$baris)->getBorders()->{'get'.ucfirst($sisi)}();

            $this->assertNotSame(
                Border::BORDER_NONE,
                $garis->getBorderStyle(),
                "Tepi tabel ($sisi) harus bergaris."
            );
            $this->assertSame(
                XlsxExportService::WARNA_GARIS_TEBAL,
                $garis->getColor()->getArgb(),
                "Tepi tabel ($sisi) harus memakai warna garis tebal."
            );
            unset($keterangan);
        }

        // Pemisah antar sel tetap ada, jadi isi tiap kolom tidak menyatu.
        $dalam = $ws->getStyle('B'.($header + 1))->getBorders()->getRight();

        $this->assertSame(Border::BORDER_THIN, $dalam->getBorderStyle());
        $this->assertSame(XlsxExportService::WARNA_GARIS, $dalam->getColor()->getArgb());
    }

    public function test_akun_export_mengubah_sel_kosong_menjadi_tanda_hubung(): void
    {
        $admin = $this->admin();
        // Guru tidak punya kelas, jadi sel Kelas-nya kosong di export.
        // Kosong itu harus kelihatan jelas, bukan dibiarkan blank.
        $guru = User::factory()->create([
            'email' => 'guru-kosong@example.com',
            'role' => 'guru',
            'kelas' => null,
            'jurusan' => null,
            'must_change_password' => true,
            'password' => 'Lama123',
            'initial_password' => Crypt::encryptString('GuruA1'),
        ]);

        $rows = $this->bacaBukuKerja(
            $this->actingAs($admin)
                ->get(route('dashboard.admin.manajemen-akun.export-csv'))
                ->assertOk()
        );

        $baris = collect($rows)->first(fn (array $r) => ($r[1] ?? null) === $guru->name);

        $this->assertNotNull($baris, 'Baris guru tidak ditemukan di export.');
        // Kolom Kelas (indeks 3) yang kosong diubah menjadi tanda "-".
        $this->assertSame('-', $baris[3] ?? null);
    }

    public function test_akun_update_changes_data(): void
    {
        $admin = $this->admin();
        $siswa = $this->userWithInitialPassword('siswa-lama@example.com', 'siswa', '10-1', 'SiswaA1');

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->put(route('dashboard.admin.manajemen-akun.update', $siswa), [
                'name' => 'Nama Baru',
                'email' => 'siswa-baru@example.com',
                'kelas' => '12-1',
                'role' => 'siswa',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.email', 'siswa-baru@example.com')
            ->assertJsonPath('data.kelas', '12-1');

        $this->assertSame('12-1', $siswa->fresh()->kelas);
    }

    public function test_akun_update_merapikan_nama_dan_email(): void
    {
        $admin = $this->admin();
        $siswa = $this->userWithInitialPassword('siswa-lama@example.com', 'siswa', '10-1', 'SiswaA1');

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->put(route('dashboard.admin.manajemen-akun.update', $siswa), [
                'name' => '  Nama Baru  ',
                'email' => '  Siswa-Baru@Example.COM  ',
                'kelas' => '12-1',
                'role' => 'siswa',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nama Baru')
            ->assertJsonPath('data.email', 'siswa-baru@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $siswa->id,
            'name' => 'Nama Baru',
            'email' => 'siswa-baru@example.com',
        ]);
    }

    public function test_akun_tidak_bisa_ubah_role_tanpa_identitas_login_baru(): void
    {
        $admin = $this->admin();
        $siswa = $this->userWithInitialPassword('siswa-lama@example.com', 'siswa', '10-1', 'SiswaA1');

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->put(route('dashboard.admin.manajemen-akun.update', $siswa), [
                'name' => 'Nama Baru',
                'email' => 'siswa-baru@example.com',
                'kelas' => '12-1',
                'role' => 'guru',
            ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'NIP'));

        $siswa->refresh();

        $this->assertSame('siswa', $siswa->role);
        $this->assertSame('SiswaA1', $siswa->visiblePassword());
    }

    public function test_akun_ubah_role_membersihkan_data_role_lama(): void
    {
        $admin = $this->admin();
        $akun = User::factory()->create([
            'email' => 'akun-ganda@example.com',
            'role' => 'siswa',
            'kelas' => '10-1',
            'jurusan' => 'Multimedia',
            'nis' => '3001',
            'nip' => '9001',
            'mata_pelajaran' => 'Fisika',
            'must_change_password' => false,
        ]);

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->put(route('dashboard.admin.manajemen-akun.update', $akun), [
                'name' => 'Akun Guru',
                'email' => 'akun-guru@example.com',
                'kelas' => '10-1',
                'role' => 'guru',
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'guru');

        $akun->refresh();

        $this->assertSame('guru', $akun->role);
        $this->assertSame('9001', $akun->nip);
        $this->assertNull($akun->nis);
    }

    public function test_akun_ubah_role_ke_admin_membersihkan_data_sekolah(): void
    {
        $admin = $this->admin();
        $akun = User::factory()->create([
            'email' => 'calon-admin@example.com',
            'role' => 'guru',
            'nip' => '9002',
            'nis' => '3002',
            'kelas' => '11-1',
            'jurusan' => 'Multimedia',
            'mata_pelajaran' => 'Fisika',
            'must_change_password' => false,
        ]);

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->put(route('dashboard.admin.manajemen-akun.update', $akun), [
                'name' => 'Admin Baru',
                'email' => 'admin-baru@example.com',
                'kelas' => null,
                'role' => 'admin',
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');

        $akun->refresh();

        $this->assertSame('admin', $akun->role);
        $this->assertNull($akun->nip);
        $this->assertNull($akun->nis);
        $this->assertNull($akun->kelas);
        $this->assertNull($akun->jurusan);
        $this->assertNull($akun->mata_pelajaran);
    }

    public function test_akun_cannot_demote_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->put(route('dashboard.admin.manajemen-akun.update', $admin), [
                'name' => 'Administrator',
                'email' => 'administrator@example.com',
                'kelas' => null,
                'role' => 'guru',
            ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_akun_reset_password_generates_new_credentials(): void
    {
        $admin = $this->admin();
        $guru = $this->userWithInitialPassword('guru-1@example.com', 'guru', '10-1', 'GuruA1');

        $response = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('dashboard.admin.manajemen-akun.reset-password', $guru))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.email', 'guru-1@example.com');

        $password = $response->json('data.password');

        $this->assertNotSame('Lama123', $password);
        $this->assertTrue(Hash::check($password, $guru->fresh()->password));
        $this->assertTrue($guru->fresh()->must_change_password);
        $this->assertSame($password, Crypt::decryptString($guru->fresh()->initial_password));
    }

    public function test_akun_reset_password_unik_dari_akun_lain(): void
    {
        $admin = $this->admin();
        $this->userWithInitialPassword('guru-1@example.com', 'guru', '10-1', 'GuruA1');
        $this->userWithInitialPassword('siswa-1@example.com', 'siswa', '11-1', 'SiswaA1');
        $target = $this->userWithInitialPassword('target@example.com', 'siswa', '12-1', 'TargetA1');

        $response = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('dashboard.admin.manajemen-akun.reset-password', $target))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $diset = $response->json('data.password');

        $milikAkunLain = User::where('id', '!=', $target->id)
            ->whereNotNull('initial_password')
            ->pluck('initial_password')
            ->map(fn (string $cipher) => Crypt::decryptString($cipher))
            ->all();

        $this->assertNotContains($diset, $milikAkunLain);
        $this->assertNotSame('TargetA1', $diset);
    }

    public function test_akun_cannot_reset_own_password(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('dashboard.admin.manajemen-akun.reset-password', $admin))
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_akun_generated_password_meets_policy(): void
    {
        foreach (range(1, 20) as $_i) {
            $password = Credentials::generatePassword();

            $this->assertGreaterThanOrEqual(8, strlen($password));
            $this->assertMatchesRegularExpression('/[A-Z]/', $password);
            $this->assertMatchesRegularExpression('/[0-9]/', $password);
        }
    }

    public function test_akun_export_includes_initial_passwords(): void
    {
        $admin = $this->admin();
        $this->userWithInitialPassword('siswa-a@example.com', 'siswa', '12-1', 'SiswaA1');
        $this->userWithInitialPassword('guru-a@example.com', 'guru', '11-1', 'GuruA1');

        $response = $this->actingAs($admin)
            ->get(route('dashboard.admin.manajemen-akun.export-csv', ['kelas' => '12-1']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));

        $content = $this->gabungBukuKerja($this->bacaBukuKerja($response));

        $this->assertStringContainsString('SiswaA1', $content);
        $this->assertStringContainsString('siswa-a@example.com', $content);
        $this->assertStringNotContainsString('guru-a@example.com', $content);
        $this->assertStringContainsString('Email', $content);

        // Kelas tetap berbentuk "12-1", bukan tanggal "12-Jan".
        $this->assertStringContainsString('12-1', $content);
    }

    public function test_akun_slips_prints_guru_and_siswa_credentials(): void
    {
        $admin = $this->admin();
        $this->userWithInitialPassword('siswa-a@example.com', 'siswa', '12-1', 'SiswaA1');
        $this->userWithInitialPassword('guru-a@example.com', 'guru', '11-1', 'GuruA1');

        $this->actingAs($admin)
            ->get(route('dashboard.admin.manajemen-akun.slips', ['kelas' => '12-1']))
            ->assertOk()
            ->assertSee('siswa-a@example.com')
            ->assertSee('SiswaA1')
            ->assertSee('Slip Password Akun')
            // Slip cetak memakai kelas yang tersimpan, tanpa bentuk lain.
            ->assertSee('12-1')
            ->assertDontSee('XII-1')
            ->assertDontSee('guru-a@example.com')
            ->assertDontSee($admin->email);
    }

    public function test_akun_slips_hides_password_when_already_changed(): void
    {
        $admin = $this->admin();
        $changed = User::factory()->create([
            'email' => 'siswa-done@example.com',
            'role' => 'siswa',
            'must_change_password' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.admin.manajemen-akun.slips'))
            ->assertOk()
            ->assertSee('siswa-done@example.com')
            ->assertSee('sudah diubah');
    }
}
