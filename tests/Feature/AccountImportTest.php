<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AccountImportTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create([
            'email' => 'administrator@example.com',
            'role' => 'admin',
            'must_change_password' => false,
        ]);
    }

    protected function import(array $files, ?object $actor = null)
    {
        $actor = $actor ?? $this->admin();

        return $this->actingAs($actor)
            ->withHeader('Accept', 'application/json')
            ->post(route('dashboard.admin.manajemen-akun.import'), $files);
    }

    public function test_import_requires_admin(): void
    {
        $guru = User::factory()->create(['email' => 'guru@example.com', 'role' => 'guru']);

        $this->actingAs($guru)
            ->post(route('dashboard.admin.manajemen-akun.import'), ['file' => UploadedFile::fake()->createWithContent('akun.csv', 'x')])
            ->assertForbidden();
    }

    public function test_import_requires_file(): void
    {
        $this->import([])->assertStatus(422)->assertJsonPath('message', 'Pilih file terlebih dahulu.');
    }

    public function test_import_rejects_unsupported_format(): void
    {
        $response = $this->import(['file' => UploadedFile::fake()->createWithContent('akun.pdf', 'x')])
            ->assertStatus(422);

        $this->assertStringContainsString('Format file tidak didukung', $response->json('message'));
    }

    public function test_import_csv_with_header_creates_accounts(): void
    {
        $csv = "Role;Nama;Email;Kelas\n"
            ."Guru;Ahmad Fauzi;;10-1\n"
            ."Guru;Ahmad Fauzi;;10-1\n"
            ."Siswa;Budi Santoso;;10-2\n";

        $response = $this->import(['file' => UploadedFile::fake()->createWithContent('akun.csv', $csv)]);

        $this->assertSame(3, $response->json('data.created'));
        $this->assertSame(0, $response->json('data.skipped'));
        $this->assertSame(0, $response->json('data.errors'));

        $this->assertDatabaseHas('users', ['email' => 'ahmad-fauzi@saskansa.sch.id']);
        $this->assertDatabaseHas('users', ['email' => 'ahmad-fauzi2@saskansa.sch.id']);
        $this->assertDatabaseHas('users', ['email' => 'budi-santoso@saskansa.sch.id']);

        $ahmad = User::where('email', 'ahmad-fauzi@saskansa.sch.id')->first();

        $this->assertSame('guru', $ahmad->role);
        $this->assertSame('10-1', $ahmad->kelas);
        $this->assertTrue($ahmad->must_change_password);
        $this->assertNotSame('', Crypt::decryptString($ahmad->initial_password));
    }

    public function test_import_csv_without_header_uses_positional_order(): void
    {
        $csv = "Siswa;Devi Rara;;12-3\n";

        $this->import(['file' => UploadedFile::fake()->createWithContent('akun.csv', $csv)])
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        $this->assertDatabaseHas('users', ['email' => 'devi-rara@saskansa.sch.id']);
    }

    public function test_import_menormalkan_dan_menolak_format_kelas_siswa(): void
    {
        $csv = "Role;Nama;Email;Kelas\n"
            ."Siswa;Siti Aminah;;12 - 2\n"
            ."Siswa;Dewi Lestari;;11\n"
            ."Siswa;Rudi Hartono;;12A\n";

        $response = $this->import(['file' => UploadedFile::fake()->createWithContent('akun.csv', $csv)])
            ->assertOk();

        $this->assertSame(2, $response->json('data.created'));
        $this->assertSame(1, $response->json('data.errors'));
        $this->assertSame(
            'Format kelas harus tingkat saja (contoh 12) atau tingkat-nomor rombel (contoh 12-1).',
            $response->json('data.error_list.0.reason')
        );

        $this->assertDatabaseHas('users', ['email' => 'siti-aminah@saskansa.sch.id', 'kelas' => '12-2']);
        $this->assertDatabaseHas('users', ['email' => 'dewi-lestari@saskansa.sch.id', 'kelas' => '11']);
        $this->assertDatabaseMissing('users', ['email' => 'rudi-hartono@saskansa.sch.id']);
    }

    public function test_import_skips_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken-01@example.com']);

        $csv = "Role;Nama;Email;Kelas\n"
            ."Siswa;Udin Petot;taken-01@example.com;10-1\n"
            ."Siswa;Udin Petok;taken-02@example.com;10-1\n";

        $response = $this->import(['file' => UploadedFile::fake()->createWithContent('akun.csv', $csv)])
            ->assertOk();

        $this->assertSame(1, $response->json('data.created'));
        $this->assertSame(1, $response->json('data.skipped'));
        $this->assertSame('Email sudah dipakai.', $response->json('data.skipped_list.0.reason'));
        $this->assertDatabaseHas('users', ['email' => 'taken-02@example.com']);
    }

    public function test_import_collects_row_errors(): void
    {
        $csv = "Role;Nama;Email\n"
            ."Kepala Sekolah;Orang Gede;;\n"
            ."Guru;;;\n"
            ."Guru;Valid Name;;\n";

        $response = $this->import(['file' => UploadedFile::fake()->createWithContent('akun.csv', $csv)])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.errors', 2);

        $errors = collect($response->json('data.error_list'))->pluck('reason')->implode(' | ');

        $this->assertStringContainsString('tidak dikenali', $errors);
        $this->assertStringContainsString('Nama wajib diisi', $errors);
        $this->assertDatabaseHas('users', ['email' => 'valid-name@saskansa.sch.id']);
    }

    public function test_import_xlsx_file_creates_accounts(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['Role', 'Nama', 'Email', 'Kelas'],
            ['Guru', 'Xyz Abc', '', '12-1'],
            ['Siswa', 'Qwe Rst', '', '12-1'],
        ], null, 'A1');

        $path = sys_get_temp_dir().'/imp-'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $uploaded = new UploadedFile($path, 'akun.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->import(['file' => $uploaded])
            ->assertOk()
            ->assertJsonPath('data.created', 2);

        $this->assertSame(2, $response->json('data.created'));
        $this->assertDatabaseHas('users', ['email' => 'xyz-abc@saskansa.sch.id']);
        $this->assertDatabaseHas('users', ['email' => 'qwe-rst@saskansa.sch.id', 'kelas' => '12-1']);
    }

    public function test_import_membuat_password_awal_yang_unik_semua_akun(): void
    {
        // Akun lama dengan password awal tetap; hasil impor harus menghindarinya.
        User::factory()->create([
            'email' => 'lama@example.com',
            'role' => 'guru',
            'password' => 'Lama123',
            'initial_password' => Crypt::encryptString('Lama123'),
            'must_change_password' => true,
        ]);

        $csv = "Role;Nama;Email;Kelas\n"
            ."Siswa;Siswa Satu;;10-1\n"
            ."Siswa;Siswa Dua;;10-1\n"
            ."Siswa;Siswa Tiga;;10-1\n"
            ."Siswa;Siswa Empat;;10-1\n"
            ."Siswa;Siswa Lima;;10-1\n";

        $this->import(['file' => UploadedFile::fake()->createWithContent('akun.csv', $csv)])->assertOk();

        $passwords = User::query()
            ->whereNotNull('initial_password')
            ->pluck('initial_password')
            ->map(fn (string $cipher) => Crypt::decryptString($cipher))
            ->all();

        // Tidak ada dua akun (baru maupun lama) dengan password awal sama.
        $this->assertSame(count($passwords), count(array_unique($passwords)));
    }

    public function test_kelas_dengan_pembungkus_kutip_ekspor_bisa_diimpor(): void
    {
        $admin = $this->admin();

        // Export CSV menulis kelas sebagai ="10-1" supaya Excel tidak mengubahnya
        // menjadi tanggal. Impor harus membuka pembungkus itu kembali.
        $csv = "Role;Nama;Email;Kelas\r\n"
            ."Siswa;Siswa Satu;;=\"10-1\"\r\n";

        $this->import(['file' => UploadedFile::fake()->createWithContent('akun.csv', $csv)], $admin)
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'name' => 'Siswa Satu',
            'kelas' => '10-1',
        ]);
    }
}
