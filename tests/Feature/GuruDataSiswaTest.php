<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSpreadsheet;
use Tests\TestCase;

class GuruDataSiswaTest extends TestCase
{
    use InteractsWithSpreadsheet;
    use RefreshDatabase;

    /**
     * Enam siswa supaya tabelnya melewati satu halaman (5 baris per halaman),
     * sehingga pagination benar-benar teruji.
     */
    protected function siswaBanyak(): void
    {
        foreach (['12-1', '12-1', '12-1', '11-1', '11-1', '10'] as $i => $kelas) {
            User::factory()->siswa($kelas)->create([
                'name' => 'Siswa Uji '.$i,
                'status' => $i === 3 ? User::STATUS_NONAKTIF : User::STATUS_AKTIF,
            ]);
        }
    }

    protected function guru(): User
    {
        return User::factory()->guru()->create(['must_change_password' => false]);
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create(['must_change_password' => false]);
    }

    /**
     * Payload yang benar-benar dikirim ke browser.
     *
     * @return array<string, mixed>
     */
    protected function payloadHalaman(User $akun, string $guard, string $route, string $variabel): array
    {
        $response = $this->actingAs($akun, $guard)->get(route($route));

        $response->assertOk();

        $this->assertSame(1, preg_match(
            '/^\s*const '.$variabel.' = (.+);$/m',
            $response->getContent(),
            $match
        ), 'Payload '.$variabel.' tidak ditemukan di halaman.');

        return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_pagination_dan_panel_filter_tampil_di_halaman_data_siswa_guru(): void
    {
        $this->siswaBanyak();

        $response = $this->actingAs($this->guru(), 'guru')
            ->get(route('dashboard.guru.data-siswa.index'));

        $response->assertOk();

        // Teks "Menampilkan x–y dari z data" ditulis JavaScript, jadi yang bisa
        // dipastikan di server adalah komponen pagination dan pasangannya.
        $response->assertSee('data-pagination');
        $response->assertSee('const perPage = 5;');
        $response->assertSee('Menampilkan ${from}–${to} dari ${total} ${label}');
        $response->assertSee('siswa-filter-panel');
    }

    public function test_status_di_halaman_data_siswa_guru_memakai_label_yang_sama_dengan_admin(): void
    {
        $this->siswaBanyak();

        $response = $this->actingAs($this->guru(), 'guru')
            ->get(route('dashboard.guru.data-siswa.index'));

        $response->assertOk();

        // Label lama "Tidak Aktif" tidak pernah dihasilkan statusLabel(),
        // jadi kalau masih dipakai, filter dan statistiknya akan selalu 0.
        $response->assertDontSee('Tidak Aktif');
        $response->assertSee('Siswa Nonaktif');

        $payload = $this->payloadHalaman($this->guru(), 'guru', 'dashboard.guru.data-siswa.index', 'siswaData');

        $this->assertSame(User::STATUSES, $payload['statuses']);
    }

    public function test_daftar_siswa_guru_sama_dengan_daftar_admin(): void
    {
        $this->siswaBanyak();

        $admin = $this->payloadHalaman($this->admin(), 'admin', 'dashboard.admin.data-siswa.index', 'data');
        $guru = $this->payloadHalaman($this->guru(), 'guru', 'dashboard.guru.data-siswa.index', 'siswaData');

        $this->assertSame(
            array_column($admin['siswa'], 'id'),
            array_column($guru['rows'], 'id'),
            'Urutan dan isi data siswa di halaman guru berbeda dengan halaman admin.'
        );
    }

    public function test_export_excel_guru_mengembalikan_file_xlsx_dengan_data_yang_sama(): void
    {
        $this->siswaBanyak();

        $response = $this->actingAs($this->guru(), 'guru')
            ->get(route('dashboard.guru.data-siswa.export'));

        $response->assertOk();
        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $this->assertStringContainsString(
            'attachment; filename=data-siswa-guru-',
            (string) $response->headers->get('Content-Disposition')
        );
        $this->assertSame('6', $response->headers->get('X-Total-Siswa'));
        $this->assertSame('5', $response->headers->get('X-Total-Aktif'));
        $this->assertSame('1', $response->headers->get('X-Total-Nonaktif'));
        $this->assertSame('0', $response->headers->get('X-Total-Lulus'));

        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($response));

        $this->assertStringContainsString('Data Siswa', $isi);
        $this->assertStringContainsString('Diunduh pada', $isi);
        $this->assertStringContainsString('Siswa Uji 0', $isi);
        $this->assertStringContainsString('Siswa Uji 5', $isi);
    }

    public function test_export_excel_guru_mengikuti_filter_status(): void
    {
        $this->siswaBanyak();

        $response = $this->actingAs($this->guru(), 'guru')->get(
            route('dashboard.guru.data-siswa.export', ['status' => User::STATUS_NONAKTIF])
        );

        $response->assertOk();
        $this->assertSame('1', $response->headers->get('X-Total-Siswa'));

        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($response));

        $this->assertStringContainsString('Siswa Uji 3', $isi);
        $this->assertStringNotContainsString('Siswa Uji 0', $isi);
    }

    public function test_kolom_password_export_guru_memakai_data_akun_sama_dengan_admin(): void
    {
        User::factory()->siswa('12-1')->withPassword('Rahasia123')
            ->create(['name' => 'Siswa Password']);
        User::factory()->siswa('12-2')->create([
            'name' => 'Siswa Sudah Ubah',
            'must_change_password' => false,
            'initial_password' => null,
        ]);

        $isi = $this->gabungBukuKerja($this->bacaBukuKerja(
            $this->actingAs($this->guru(), 'guru')->get(route('dashboard.guru.data-siswa.export'))
        ));

        $this->assertStringContainsString('Rahasia123', $isi, 'Password awal tidak ikut terexport.');
        $this->assertStringContainsString('Belum ubah password', $isi);
        $this->assertStringContainsString('Sudah ubah password', $isi);
    }
}
