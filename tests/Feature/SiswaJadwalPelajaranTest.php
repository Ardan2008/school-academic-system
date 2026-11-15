<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman Jadwal Pelajaran siswa harus membaca tabel `jadwal` apa adanya:
 * hanya jadwal rombel siswa yang login, terkelompok per hari, dan tetap
 * memberi keterangan yang jelas saat jadwal belum diatur.
 */
class SiswaJadwalPelajaranTest extends TestCase
{
    use RefreshDatabase;

    private function buatJadwal(
        User $guru,
        MataPelajaran $mapel,
        string $kelas,
        ?string $jurusan,
        string $hari,
        string $mulai = '07:00',
        string $selesai = '08:30',
        ?string $ruang = 'R.101',
    ): JadwalPelajaran {
        return JadwalPelajaran::create([
            'kelas' => $kelas,
            'jurusan' => $jurusan,
            'hari' => $hari,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'ruang' => $ruang,
        ]);
    }

    public function test_siswa_hanya_melihat_jadwal_kelasnya_sendiri(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $mapelLain = MataPelajaran::factory()->create(['kode' => 'FIS', 'nama' => 'Fisika']);
        $guru = User::factory()->guru()->create(['name' => 'Budi Guru']);

        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $this->buatJadwal($guru, $mapel, '12-1', 'Rekayasa Perangkat Lunak', 'Senin', '07:00', '08:30', 'R.101');
        // Rombel lain tidak boleh ikut tampil.
        $this->buatJadwal($guru, $mapelLain, '11-2', 'Multimedia', 'Senin', '07:00', '08:30', 'R.202');

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.jadwal-pelajaran.index'));

        $res->assertOk();
        $res->assertSee('Matematika');
        $res->assertSee('Budi Guru');
        $res->assertSee('R.101');
        $res->assertSee('Senin');
        $res->assertDontSee('Fisika');
        $res->assertDontSee('R.202');
    }

    public function test_filter_hari_hanya_menampilkan_hari_yang_dipilih(): void
    {
        $senin = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $selasa = MataPelajaran::factory()->create(['kode' => 'FIS', 'nama' => 'Fisika']);
        $guru = User::factory()->guru()->create();

        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $this->buatJadwal($guru, $senin, '12-1', 'Rekayasa Perangkat Lunak', 'Senin');
        $this->buatJadwal($guru, $selasa, '12-1', 'Rekayasa Perangkat Lunak', 'Selasa');

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.jadwal-pelajaran.index', ['hari' => 'Senin']));

        $res->assertOk();
        $res->assertSee('Matematika');
        $res->assertDontSee('Fisika');
    }

    public function test_hari_di_luar_daftar_diabaikan(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $guru = User::factory()->guru()->create();

        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);
        $this->buatJadwal($guru, $mapel, '12-1', 'Rekayasa Perangkat Lunak', 'Senin');

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.jadwal-pelajaran.index', ['hari' => 'Minggu']));

        $res->assertOk();
        $res->assertSee('Matematika');
    }

    public function test_jadwal_tanpa_jurusan_berlaku_untuk_semua_jurusan(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $guru = User::factory()->guru()->create(['name' => 'Budi Guru']);

        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        // Baris tanpa jurusan berlaku untuk seluruh jurusan di kelas tersebut.
        $this->buatJadwal($guru, $mapel, '12-1', null, 'Senin');

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.jadwal-pelajaran.index'));

        $res->assertOk();
        $res->assertSee('Matematika');
    }

    public function test_siswa_tanpa_jadwal_melihat_keterangan_belum_tersedia(): void
    {
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.jadwal-pelajaran.index'));

        $res->assertOk();
        $res->assertSee('Jadwal Pelajaran Belum Tersedia');
    }

    public function test_siswa_tanpa_kelas_melihat_peringatan_data_kelas(): void
    {
        $siswa = User::factory()->siswa()->create(['kelas' => null, 'jurusan' => null]);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.jadwal-pelajaran.index'));

        $res->assertOk();
        $res->assertSee('Data Kelas Belum Diatur');
    }
}
