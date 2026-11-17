<?php

namespace Tests\Feature;

use App\Models\Jurusan;
use App\Models\KelasRombel;
use App\Models\MataPelajaran;
use App\Models\User;
use App\Services\AccountImportService;
use App\Services\TemplateFormatDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\InteractsWithSpreadsheet;
use Tests\TestCase;
use ZipArchive;

class TemplateFormatDataTest extends TestCase
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

    protected function unduh(array $jenis, ?User $actor = null): TestResponse
    {
        $actor = $actor ?? $this->admin();

        return $this->actingAs($actor)->get(route('dashboard.admin.manajemen-akun.templates', [
            'jenis' => $jenis,
        ]));
    }

    protected function simpanSementara(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl-');
        file_put_contents($path, $content);

        return $path;
    }

    protected function imporTemplate(string $type): array
    {
        $path = $this->simpanSementara(app(TemplateFormatDataService::class)->build($type));

        try {
            return app(AccountImportService::class)->import($path, 'xlsx');
        } finally {
            @unlink($path);
        }
    }

    public function test_template_requires_admin(): void
    {
        $guru = User::factory()->create(['email' => 'guru@example.com', 'role' => 'guru']);

        $this->actingAs($guru)
            ->get(route('dashboard.admin.manajemen-akun.templates', ['jenis' => ['guru']]))
            ->assertForbidden();
    }

    public function test_template_requires_at_least_one_valid_type(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('dashboard.admin.manajemen-akun.templates'))
            ->assertStatus(422);

        $this->actingAs($admin)
            ->get(route('dashboard.admin.manajemen-akun.templates', ['jenis' => ['guru', 'tidak-ada']]))
            ->assertStatus(422);
    }

    public function test_akun_page_menampilkan_modal_template_format_data(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.manajemen-akun.index'))
            ->assertOk()
            ->assertSee('Template Format Data')
            ->assertSee('Unduh Template Format Data')
            ->assertSee('Pilih Semua Template')
            ->assertSee('Data Guru')
            ->assertSee('Data Siswa')
            ->assertSee('Data Kelas')
            ->assertSee('Data Jurusan')
            ->assertSee('Data Mata Pelajaran');
    }

    public function test_satu_template_menghasilkan_xlsx_dengan_header_contoh_dan_petunjuk(): void
    {
        $response = $this->unduh(['guru'])->assertOk();

        $this->assertStringContainsString(
            'attachment; filename="Template_Data_Guru.xlsx"',
            (string) $response->headers->get('content-disposition')
        );
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type')
        );

        $path = $this->simpanSementara($this->kontenRespons($response));

        try {
            $workbook = IOFactory::load($path);

            $this->assertSame(['Data Guru', 'Petunjuk'], $workbook->getSheetNames());

            $rows = $workbook->getSheet(0)->toArray();
            $this->assertSame(['NIP', 'Nama', 'Email', 'Mata Pelajaran', 'Status'], $rows[0]);
            $this->assertSame('Contoh Guru Pertama', $rows[1][1]);
            $this->assertSame('Aktif', $rows[1][4]);

            $petunjuk = $workbook->getSheet(1)->toArray();
            $this->assertStringContainsString(
                'Semua kolom wajib diisi',
                $this->gabungBukuKerja($petunjuk)
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_beberapa_template_menghasilkan_zip_berisi_semua_file(): void
    {
        $response = $this->unduh([
            'guru',
            'siswa',
            'kelas',
            'jurusan',
            'mata_pelajaran',
        ])->assertOk();

        $this->assertStringContainsString(
            'attachment; filename="Template_Format_Data_SASKANSA.zip"',
            (string) $response->headers->get('content-disposition')
        );
        $this->assertStringContainsString('application/zip', (string) $response->headers->get('content-type'));

        $path = $this->simpanSementara($this->kontenRespons($response));

        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $this->assertSame(5, $zip->numFiles);

            foreach ([
                'Template_Data_Guru.xlsx',
                'Template_Data_Siswa.xlsx',
                'Template_Data_Kelas.xlsx',
                'Template_Data_Jurusan.xlsx',
                'Template_Data_Mata_Pelajaran.xlsx',
            ] as $filename) {
                $this->assertNotFalse($zip->locateName($filename), "File {$filename} tidak ada di ZIP.");
            }

            $zip->close();
        } finally {
            @unlink($path);
        }
    }

    public function test_template_guru_dapat_diimpor_ulang(): void
    {
        MataPelajaran::create(['kode' => 'MAT', 'nama' => 'Matematika']);

        $result = $this->imporTemplate('guru');

        $this->assertSame('guru', $result['type']);
        $this->assertCount(1, $result['created']);
        $this->assertCount(0, $result['errors']);
        $this->assertDatabaseHas('users', [
            'nip' => '198001012005011001',
            'name' => 'Contoh Guru Pertama',
            'mata_pelajaran' => 'Matematika',
        ]);
    }

    public function test_template_siswa_dapat_diimpor_ulang(): void
    {
        Jurusan::create(['nama' => 'Rekayasa Perangkat Lunak', 'status' => 'aktif']);
        KelasRombel::create(['kelas' => '12-1', 'jurusan' => 'Rekayasa Perangkat Lunak']);

        $result = $this->imporTemplate('siswa');

        $this->assertSame('siswa', $result['type']);
        $this->assertCount(1, $result['created']);
        $this->assertCount(0, $result['errors']);
        $this->assertDatabaseHas('users', [
            'nis' => '2024001001',
            'name' => 'Contoh Siswa Pertama',
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
        ]);
    }

    public function test_template_kelas_dapat_diimpor_ulang(): void
    {
        Jurusan::create(['nama' => 'Rekayasa Perangkat Lunak', 'status' => 'aktif']);

        $result = $this->imporTemplate('kelas');

        $this->assertSame('kelas', $result['type']);
        $this->assertCount(1, $result['created']);
        $this->assertCount(0, $result['errors']);
        $this->assertDatabaseHas('kelas', [
            'kelas' => '10-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
        ]);
    }

    public function test_template_jurusan_dapat_diimpor_ulang(): void
    {
        $result = $this->imporTemplate('jurusan');

        $this->assertSame('jurusan', $result['type']);
        $this->assertCount(1, $result['created']);
        $this->assertCount(0, $result['errors']);
        $this->assertDatabaseHas('jurusan', [
            'nama' => 'Teknik Komputer dan Jaringan',
            'status' => 'aktif',
        ]);
    }

    public function test_template_mata_pelajaran_dapat_diimpor_ulang(): void
    {
        $result = $this->imporTemplate('mata_pelajaran');

        $this->assertSame('mata_pelajaran', $result['type']);
        $this->assertCount(1, $result['created']);
        $this->assertCount(0, $result['errors']);
        $this->assertDatabaseHas('mata_pelajaran', [
            'kode' => 'MAT',
            'nama' => 'Matematika',
        ]);
    }
}
