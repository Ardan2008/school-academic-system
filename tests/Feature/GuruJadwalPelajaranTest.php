<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\JamBelajarKonfigurasi;
use App\Models\MataPelajaran;
use App\Models\SesiJamBelajar;
use App\Models\User;
use App\Support\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithSpreadsheet;
use Tests\TestCase;

class GuruJadwalPelajaranTest extends TestCase
{
    use InteractsWithSpreadsheet;
    use RefreshDatabase;

    private const JURUSAN = 'Rekayasa Perangkat Lunak';

    private function guru(string $mapel = 'Matematika'): User
    {
        return User::factory()->guru()->create([
            'name' => 'Guru Pengampu',
            'mata_pelajaran' => $mapel,
            'must_change_password' => false,
        ]);
    }

    private function siswa(string $kelas = '12-1', ?string $jurusan = self::JURUSAN): User
    {
        return User::factory()->siswa($kelas)->create([
            'name' => 'Siswa Rombel',
            'jurusan' => $jurusan,
        ]);
    }

    private function sesi(
        string $mulai = '07:00',
        string $selesai = '08:30',
        string $nama = 'Jam Uji',
    ): SesiJamBelajar {
        $konfigurasi = JamBelajarKonfigurasi::query()->firstOrFail();
        $ada = SesiJamBelajar::query()
            ->where('jam_mulai', $mulai)
            ->where('jam_selesai', $selesai)
            ->first();

        if ($ada !== null) {
            return $ada;
        }

        return SesiJamBelajar::create([
            'jam_belajar_konfigurasi_id' => $konfigurasi->id,
            'nama' => $nama,
            'jenis' => SesiJamBelajar::JENIS_PELAJARAN,
            'urutan' => (int) SesiJamBelajar::query()->max('urutan') + 1,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
        ]);
    }

    private function jadwal(
        User $guru,
        string $namaMapel,
        string $hari = 'Senin',
        string $ruang = 'R.101',
        string $kelas = '12-1',
        ?string $jurusan = self::JURUSAN,
    ): JadwalPelajaran {
        $mapel = MataPelajaran::factory()->nama($namaMapel)->create();
        $sesi = $this->sesi();

        return JadwalPelajaran::create([
            'kelas' => $kelas,
            'jurusan' => $jurusan,
            'hari' => $hari,
            'sesi_id' => $sesi->id,
            'jam_mulai' => substr((string) $sesi->jam_mulai, 0, 5),
            'jam_selesai' => substr((string) $sesi->jam_selesai, 0, 5),
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'ruang' => $ruang,
        ]);
    }

    public function test_halaman_guru_bersifat_read_only(): void
    {
        $guru = $this->guru();
        $this->siswa();
        $this->jadwal($guru, 'Matematika');

        $html = (string) $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.jadwal-pelajaran.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-jadwal-page', $html);
        $this->assertStringContainsString('data-role="guru"', $html);
        $this->assertStringNotContainsString('data-store-url=', $html);
        $this->assertStringNotContainsString('data-intent-url=', $html);
        $this->assertStringNotContainsString('id="jadwal-form"', $html);
        $this->assertStringNotContainsString('data-edit-jadwal-trigger', $html);
        $this->assertStringNotContainsString('data-buka-penyesuaian', $html);
        $this->assertStringNotContainsString('Tambah Jadwal', $html);
    }

    public function test_rute_mutasi_guru_tidak_tersedia_dan_diblokir_backend(): void
    {
        $guru = $this->guru();
        $jadwal = $this->jadwal($guru, 'Matematika');

        $this->assertFalse(Route::has('dashboard.guru.jadwal-pelajaran.store'));
        $this->assertFalse(Route::has('dashboard.guru.jadwal-pelajaran.update'));
        $this->assertFalse(Route::has('dashboard.guru.jadwal-pelajaran.destroy'));

        $responses = [
            $this->actingAs($guru, 'guru')->post('/dashboard/guru/jadwal-pelajaran', []),
            $this->actingAs($guru, 'guru')->put('/dashboard/guru/jadwal-pelajaran/'.$jadwal->id, []),
            $this->actingAs($guru, 'guru')->delete('/dashboard/guru/jadwal-pelajaran/'.$jadwal->id),
        ];

        foreach ($responses as $response) {
            $this->assertContains($response->status(), [404, 405]);
        }

        $this->assertDatabaseCount('jadwal', 1);
    }

    public function test_guru_hanya_melihat_jadwal_miliknya_sendiri(): void
    {
        $guru = $this->guru();
        $guruLain = $this->guru('Fisika');

        $this->jadwal($guru, 'Matematika', 'Senin', 'R.101');
        $this->jadwal($guruLain, 'Fisika', 'Senin', 'R.202');

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.jadwal-pelajaran.index'))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertSee('R.101')
            ->assertDontSee('Fisika')
            ->assertDontSee('R.202');
    }

    public function test_filter_hari_hanya_menampilkan_hari_yang_dipilih(): void
    {
        $guru = $this->guru();
        $this->siswa();
        $this->jadwal($guru, 'Matematika', 'Senin');
        $this->jadwal($guru, 'Fisika', 'Selasa', 'R.102');

        $html = (string) $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.jadwal-pelajaran.index', ['hari' => 'Selasa']))
            ->assertOk()
            ->assertSee('Fisika')
            ->getContent();

        $this->assertStringContainsString('data-hari-awal="Selasa"', $html);
    }

    public function test_guru_tanpa_jadwal_melihat_keterangan_belum_ada_jadwal(): void
    {
        $guru = $this->guru();
        $this->siswa();

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.jadwal-pelajaran.index'))
            ->assertOk()
            ->assertSee('Belum Ada Jadwal Mengajar');
    }

    public function test_hari_di_luar_daftar_diabaikan(): void
    {
        $guru = $this->guru();
        $this->siswa();
        $this->jadwal($guru, 'Matematika', 'Minggu');

        $html = (string) $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.jadwal-pelajaran.index', ['hari' => 'Minggu']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Matematika', $html);
    }

    public function test_periode_berjalan_tampil_di_halaman_guru(): void
    {
        $guru = $this->guru();
        $this->siswa();
        $periode = Periode::periodeSekarang();

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.jadwal-pelajaran.index'))
            ->assertOk()
            ->assertSee(Periode::label($periode['tahun_ajaran'], $periode['semester']));
    }

    public function test_hari_nonaktif_tidak_tersedia_pada_halaman_guru(): void
    {
        $guru = $this->guru();
        $this->siswa();

        $html = (string) $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.jadwal-pelajaran.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('data-hari-pilih="Minggu"', $html);
    }

    public function test_layout_samping_dan_agenda_mobile_terpakai(): void
    {
        $guru = $this->guru();
        $this->siswa();
        $this->jadwal($guru, 'Matematika', JadwalPelajaran::hariIni() ?? 'Senin');

        $html = (string) $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.jadwal-pelajaran.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('jg-side-grid', $html);
        $this->assertStringContainsString('Agenda hari ini', $html);
        $this->assertStringContainsString('data-agenda-list', $html);
    }

    public function test_mini_timeline_mengikuti_jadwal_berikutnya(): void
    {
        $guru = $this->guru();
        $this->siswa();
        $this->jadwal($guru, 'Matematika', JadwalPelajaran::hariIni() ?? 'Senin');

        $html = (string) $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.jadwal-pelajaran.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-mini-timeline', $html);
        $this->assertStringContainsString('data-timeline-now', $html);
        $this->assertStringContainsString('data-live-sisa', $html);
    }

    public function test_mini_timeline_tidak_tampil_tanpa_jadwal(): void
    {
        $guru = $this->guru();
        $this->siswa();

        $html = (string) $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.jadwal-pelajaran.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('data-mini-timeline', $html);
    }

    public function test_halaman_renders_saat_sesi_sedang_berjalan(): void
    {
        Carbon::setTestNow('2026-11-20 00:15:00');

        try {
            $guru = $this->guru();
            $this->siswa();
            $this->jadwal($guru, 'Matematika', 'Jumat');

            $this->actingAs($guru, 'guru')
                ->get(route('dashboard.guru.jadwal-pelajaran.index'))
                ->assertOk()
                ->assertSee('Sedang berlangsung');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_tombol_ekspor_jadwal_menggunakan_gaya_outlined_seperti_admin(): void
    {
        $guru = $this->guru();
        $this->siswa();

        $html = (string) $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.jadwal-pelajaran.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Export Excel', $html);
        $this->assertStringContainsString(
            'h-10 shrink-0 items-center justify-center gap-2 overflow-hidden whitespace-nowrap rounded-lg border border-blue-300 bg-white px-4 text-xs font-medium text-primary-60',
            $html
        );
        $this->assertStringContainsString('data-export-url=', $html);
    }

    public function test_url_ekspor_guru_mengikuti_hari_terpilih(): void
    {
        $guru = $this->guru();
        $this->siswa();

        $html = (string) $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.jadwal-pelajaran.index', ['hari' => 'Selasa']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-export-url="'.route('dashboard.guru.jadwal-pelajaran.export', ['hari' => 'Selasa']).'"', $html);
    }

    public function test_live_guru_menampilkan_jadwal_berikutnya_pada_hari_lain(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-23 18:00:00', 'Asia/Jakarta'));

        try {
            $guru = $this->guru();
            $this->siswa();
            $this->jadwal($guru, 'Matematika', 'Selasa');

            $html = (string) $this->actingAs($guru, 'guru')
                ->get(route('dashboard.guru.jadwal-pelajaran.index'))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('Berikutnya · Selasa', $html);
            $this->assertStringContainsString('Mulai dalam', $html);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_jadwal_di_luar_sesi_mendapat_status_luar(): void
    {
        $guru = $this->guru();
        $this->siswa();
        $jadwal = $this->jadwal($guru, 'Matematika', 'Senin');

        JadwalPelajaran::query()->whereKey($jadwal->id)->update([
            'jam_mulai' => '06:00',
            'jam_selesai' => '06:30',
        ]);

        $html = (string) $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.jadwal-pelajaran.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Di luar sesi', $html);
    }

    public function test_export_xlsx_jadwal_hanya_memuat_jadwal_guru_berjalan(): void
    {
        $guru = $this->guru();
        $guruLain = $this->guru('Fisika');
        $this->siswa();
        $this->jadwal($guru, 'Matematika', 'Senin');
        $this->jadwal($guruLain, 'Fisika', 'Senin', 'R.102', '11-1');

        $respons = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.jadwal-pelajaran.export'))
            ->assertOk()
            ->assertHeader('X-Total-Jadwal', '1')
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($respons));
        $periode = Periode::periodeSekarang();

        $this->assertStringContainsString('Jadwal Mengajar', $isi);
        $this->assertStringContainsString('Periode '.Periode::label($periode['tahun_ajaran'], $periode['semester']), $isi);
        $this->assertStringContainsString('Matematika', $isi);
        $this->assertStringNotContainsString('Fisika', $isi);
    }

    public function test_export_xlsx_jadwal_mengikuti_filter_hari(): void
    {
        $guru = $this->guru();
        $this->siswa();
        $this->jadwal($guru, 'Matematika', 'Senin');
        $this->jadwal($guru, 'Fisika', 'Selasa', 'R.102', '11-1');

        $respons = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.jadwal-pelajaran.export', ['hari' => 'Selasa']))
            ->assertOk()
            ->assertHeader('X-Total-Jadwal', '1');

        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($respons));

        $this->assertStringContainsString('Selasa', $isi);
        $this->assertStringContainsString('Fisika', $isi);
        $this->assertStringNotContainsString('Matematika', $isi);
    }

    public function test_non_guru_tidak_dapat_mengekspor_jadwal(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard.guru.jadwal-pelajaran.export'))
            ->assertForbidden();
    }
}
