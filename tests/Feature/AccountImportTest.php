<?php

namespace Tests\Feature;

use App\Models\Jurusan;
use App\Models\KelasRombel;
use App\Models\MataPelajaran;
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
        $guru = User::factory()->create(['email' => 'guru@example.com', 'role' => 'guru', 'nip' => 'G001']);

        $this->actingAs($guru)
            ->post(route('dashboard.admin.manajemen-akun.import'), ['file' => UploadedFile::fake()->createWithContent('data.csv', 'x')])
            ->assertForbidden();
    }

    public function test_import_requires_file(): void
    {
        $this->import([])->assertStatus(422)->assertJsonPath('message', 'Pilih file terlebih dahulu.');
    }

    public function test_import_rejects_unsupported_format(): void
    {
        $response = $this->import(['file' => UploadedFile::fake()->createWithContent('data.pdf', 'x')])
            ->assertStatus(422);

        $this->assertStringContainsString('Format file tidak didukung', $response->json('message'));
    }

    public function test_import_rejects_unrecognized_header(): void
    {
        $csv = "Foo;Bar\n1;2\n";

        $response = $this->import(['file' => UploadedFile::fake()->createWithContent('data.csv', $csv)])
            ->assertStatus(422);

        $this->assertStringContainsString('Header file tidak dikenali', $response->json('message'));
    }

    public function test_import_guru_auto_detect_and_creates_account(): void
    {
        MataPelajaran::create(['kode' => 'MAT', 'nama' => 'Matematika']);

        $csv = "NIP;Nama;Email;Mata Pelajaran;Status\n"
            ."198001;Ahmad Fauzi;ahmad@example.com;Matematika;Aktif\n"
            ."198002;Budi Santoso;;;\n";

        $response = $this->import(['file' => UploadedFile::fake()->createWithContent('guru.csv', $csv)]);

        $response->assertOk()
            ->assertJsonPath('data.type', 'guru')
            ->assertJsonPath('data.type_label', 'Guru')
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.skipped', 0)
            ->assertJsonPath('data.errors', 0);

        $guru = User::where('nip', '198001')->first();
        $this->assertNotNull($guru);
        $this->assertSame('guru', $guru->role);
        $this->assertSame('Ahmad Fauzi', $guru->name);
        $this->assertSame('ahmad@example.com', $guru->email);
        $this->assertSame('Matematika', $guru->mata_pelajaran);
        $this->assertTrue($guru->must_change_password);
        $this->assertNotNull($guru->initial_password);
        $this->assertNotSame('', Crypt::decryptString($guru->initial_password));

        $guru2 = User::where('nip', '198002')->first();
        $this->assertNotNull($guru2);
        $this->assertStringEndsWith('@saskansa.sch.id', $guru2->email);
    }

    public function test_import_guru_rejects_unknown_mapel_and_duplicate_nip(): void
    {
        MataPelajaran::create(['kode' => 'MAT', 'nama' => 'Matematika']);
        User::factory()->create(['role' => 'guru', 'nip' => '1001', 'email' => 'existing@example.com']);

        $csv = "NIP;Nama;Email;Mata Pelajaran\n"
            ."1001;Duplikat;;Matematika\n"
            ."1002;Mapel Asing;;Fisika Quantum\n";

        $response = $this->import(['file' => UploadedFile::fake()->createWithContent('guru.csv', $csv)])
            ->assertOk();

        $this->assertSame(0, $response->json('data.created'));
        $this->assertSame(1, $response->json('data.skipped'));
        $this->assertSame(1, $response->json('data.errors'));
        $this->assertStringContainsString('belum terdaftar', collect($response->json('data.error_list'))->pluck('reason')->implode('|'));
    }

    public function test_import_siswa_auto_detect_and_creates_account(): void
    {
        Jurusan::create(['nama' => 'RPL', 'status' => 'aktif']);
        KelasRombel::create(['jurusan' => 'RPL', 'kelas' => '12-1']);

        $csv = "NIS;Nama;Email;Kelas;Jurusan;Status\n"
            ."10001;Siti Aminah;;12 - 1;RPL;Aktif\n";

        $response = $this->import(['file' => UploadedFile::fake()->createWithContent('siswa.csv', $csv)])
            ->assertOk();

        $response->assertJsonPath('data.type', 'siswa')
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.errors', 0);

        $siswa = User::where('nis', '10001')->first();
        $this->assertNotNull($siswa);
        $this->assertSame('siswa', $siswa->role);
        $this->assertSame('Siti Aminah', $siswa->name);
        $this->assertSame('12-1', $siswa->kelas);
        $this->assertSame('RPL', $siswa->jurusan);
        $this->assertTrue($siswa->must_change_password);
        $this->assertNotSame('', Crypt::decryptString($siswa->initial_password));
    }

    public function test_import_siswa_validates_relations_format_and_duplicates(): void
    {
        Jurusan::create(['nama' => 'RPL', 'status' => 'aktif']);
        KelasRombel::create(['jurusan' => 'RPL', 'kelas' => '12-1']);

        $csv = "NIS;Nama;Email;Kelas;Jurusan\n"
            ."20001;Valid;;12-1;RPL\n"
            ."20001;Duplikat File;;12-1;RPL\n"
            ."20002;Kelas Invalid;;12A;RPL\n"
            ."20003;Kelas Belum Ada;;11-1;RPL\n"
            ."20004;Jurusan Invalid;;12-1;TKJ\n";

        $response = $this->import(['file' => UploadedFile::fake()->createWithContent('siswa.csv', $csv)])
            ->assertOk();

        $this->assertSame('siswa', $response->json('data.type'));
        $this->assertSame(1, $response->json('data.created'));
        $this->assertSame(1, $response->json('data.skipped'));
        $this->assertSame(3, $response->json('data.errors'));
        $this->assertDatabaseHas('users', ['nis' => '20001']);
        $this->assertDatabaseMissing('users', ['nis' => '20002']);
        $this->assertDatabaseMissing('users', ['nis' => '20003']);
        $this->assertDatabaseMissing('users', ['nis' => '20004']);
    }

    public function test_import_kelas_auto_detect_and_creates_record(): void
    {
        Jurusan::create(['nama' => 'RPL', 'status' => 'aktif']);
        User::factory()->create(['role' => 'guru', 'name' => 'Pak Wali', 'nip' => '9001', 'email' => 'wali@example.com']);

        $csv = "Kelas;Jurusan;Wali Kelas\n"
            ."10-1;RPL;Pak Wali\n"
            ."10-2;RPL;\n";

        $response = $this->import(['file' => UploadedFile::fake()->createWithContent('kelas.csv', $csv)])
            ->assertOk();

        $response->assertJsonPath('data.type', 'kelas')->assertJsonPath('data.created', 2);
        $this->assertDatabaseHas('kelas', ['kelas' => '10-1', 'jurusan' => 'RPL']);
        $this->assertDatabaseHas('kelas', ['kelas' => '10-2', 'jurusan' => 'RPL', 'wali_kelas_id' => null]);
    }

    public function test_import_kelas_validates_jurusan_and_duplicate(): void
    {
        Jurusan::create(['nama' => 'RPL', 'status' => 'aktif']);
        KelasRombel::create(['jurusan' => 'RPL', 'kelas' => '10-1']);

        $csv = "Kelas;Jurusan\n10-1;RPL\n11-1;TKJ\n";

        $response = $this->import(['file' => UploadedFile::fake()->createWithContent('kelas.csv', $csv)])
            ->assertOk();

        $this->assertSame(0, $response->json('data.created'));
        $this->assertSame(1, $response->json('data.skipped'));
        $this->assertSame(1, $response->json('data.errors'));
    }

    public function test_import_jurusan_auto_detect_and_creates_record(): void
    {
        $csv = "Nama Jurusan;Status\nRekayasa Perangkat Lunak;Aktif\nTeknik Komputer Jaringan;nonaktif\n";

        $response = $this->import(['file' => UploadedFile::fake()->createWithContent('jurusan.csv', $csv)])
            ->assertOk();

        $response->assertJsonPath('data.type', 'jurusan')->assertJsonPath('data.created', 2);
        $this->assertDatabaseHas('jurusan', ['nama' => 'Rekayasa Perangkat Lunak', 'status' => 'aktif']);
        $this->assertDatabaseHas('jurusan', ['nama' => 'Teknik Komputer Jaringan', 'status' => 'nonaktif']);
    }

    public function test_import_jurusan_skips_duplicate(): void
    {
        Jurusan::create(['nama' => 'RPL', 'status' => 'aktif']);

        $csv = "Nama Jurusan;Status\nRPL;Aktif\n";

        $response = $this->import(['file' => UploadedFile::fake()->createWithContent('jurusan.csv', $csv)])
            ->assertOk();

        $this->assertSame(0, $response->json('data.created'));
        $this->assertSame(1, $response->json('data.skipped'));
    }

    public function test_import_mata_pelajaran_auto_detect_and_creates_record(): void
    {
        $csv = "Kode;Nama Mata Pelajaran\nMAT;Matematika\nBIN;Bahasa Indonesia\n";

        $response = $this->import(['file' => UploadedFile::fake()->createWithContent('mapel.csv', $csv)])
            ->assertOk();

        $response->assertJsonPath('data.type', 'mata_pelajaran')->assertJsonPath('data.created', 2);
        $this->assertDatabaseHas('mata_pelajaran', ['kode' => 'MAT', 'nama' => 'Matematika']);
        $this->assertDatabaseHas('mata_pelajaran', ['kode' => 'BIN', 'nama' => 'Bahasa Indonesia']);
    }

    public function test_import_mata_pelajaran_skips_duplicate_code_or_name(): void
    {
        MataPelajaran::create(['kode' => 'MAT', 'nama' => 'Matematika']);

        $csv = "Kode;Nama Mata Pelajaran\nMAT;Matematika Lanjut\nFIS;Matematika\n";

        $response = $this->import(['file' => UploadedFile::fake()->createWithContent('mapel.csv', $csv)])
            ->assertOk();

        $this->assertSame(0, $response->json('data.created'));
        $this->assertSame(2, $response->json('data.skipped'));
    }

    public function test_import_xlsx_guru_creates_accounts(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['NIP', 'Nama', 'Email'],
            ['55501', 'Guru Xlsx', ''],
        ], null, 'A1');

        $path = sys_get_temp_dir().'/imp-'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $uploaded = new UploadedFile($path, 'guru.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->import(['file' => $uploaded])->assertOk();

        $this->assertSame('guru', $response->json('data.type'));
        $this->assertSame(1, $response->json('data.created'));
        $this->assertDatabaseHas('users', ['nip' => '55501', 'role' => 'guru']);
    }

    public function test_import_guru_passwords_unique_in_batch_and_existing(): void
    {
        User::factory()->create([
            'email' => 'lama@example.com',
            'role' => 'guru',
            'nip' => 'OLD1',
            'password' => 'Lama1234',
            'initial_password' => Crypt::encryptString('Lama1234'),
            'must_change_password' => true,
        ]);

        $csv = "NIP;Nama\n9001;Guru A\n9002;Guru B\n9003;Guru C\n9004;Guru D\n";

        $this->import(['file' => UploadedFile::fake()->createWithContent('guru.csv', $csv)])->assertOk();

        $passwords = User::query()
            ->whereNotNull('initial_password')
            ->pluck('initial_password')
            ->map(fn (string $cipher) => Crypt::decryptString($cipher))
            ->all();

        $this->assertSame(count($passwords), count(array_unique($passwords)));
    }

    public function test_import_kelas_with_quoted_export_format(): void
    {
        Jurusan::create(['nama' => 'RPL', 'status' => 'aktif']);

        $csv = "Kelas;Jurusan\r\n=\"10-1\";RPL\r\n";

        $this->import(['file' => UploadedFile::fake()->createWithContent('kelas.csv', $csv)])
            ->assertOk();

        $this->assertDatabaseHas('kelas', ['kelas' => '10-1', 'jurusan' => 'RPL']);
    }
}
