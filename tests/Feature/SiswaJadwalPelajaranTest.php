<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\JamBelajarKonfigurasi;
use App\Models\MataPelajaran;
use App\Models\SesiJamBelajar;
use App\Models\User;
use App\Support\Periode;
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
        ?SesiJamBelajar $sesi = null,
    ): JadwalPelajaran {
        return JadwalPelajaran::create([
            'kelas' => $kelas,
            'jurusan' => $jurusan,
            'hari' => $hari,
            'sesi_id' => $sesi?->id,
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

    public function test_siswa_melihat_periode_berjalan_pada_halaman_jadwal(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $guru = User::factory()->guru()->create(['name' => 'Budi Guru']);
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $this->buatJadwal($guru, $mapel, '12-1', 'Rekayasa Perangkat Lunak', 'Senin');
        $periode = Periode::periodeSekarang();

        $this->actingAs($siswa)
            ->get(route('dashboard.siswa.jadwal-pelajaran.index'))
            ->assertOk()
            ->assertSee(Periode::label($periode['tahun_ajaran'], $periode['semester']));
    }

    public function test_siswa_melihat_waktu_sesi_terbaru_tanpa_mengubah_jadwal(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $guru = User::factory()->guru()->create(['name' => 'Budi Guru']);
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);
        $konfigurasi = JamBelajarKonfigurasi::query()->firstOrFail();
        $sesi = SesiJamBelajar::create([
            'jam_belajar_konfigurasi_id' => $konfigurasi->id,
            'nama' => 'Sesi Uji',
            'jenis' => SesiJamBelajar::JENIS_PELAJARAN,
            'urutan' => (int) SesiJamBelajar::query()->max('urutan') + 1,
            'jam_mulai' => '11:45',
            'jam_selesai' => '12:30',
        ]);
        $jadwal = $this->buatJadwal(
            $guru,
            $mapel,
            '12-1',
            'Rekayasa Perangkat Lunak',
            'Senin',
            '11:45',
            '12:30',
            'R.101',
            $sesi
        );

        $sesi->update(['jam_selesai' => '13:00']);

        $this->actingAs($siswa)
            ->get(route('dashboard.siswa.jadwal-pelajaran.index'))
            ->assertOk()
            ->assertSee('11:45–13:00')
            ->assertSee('Jadwal perlu disesuaikan.');

        $this->assertDatabaseHas('jadwal', [
            'id' => $jadwal->id,
            'jam_mulai' => '11:45',
            'jam_selesai' => '12:30',
        ]);
    }

    public function test_hari_nonaktif_tidak_tersedia_pada_halaman_siswa(): void
    {
        JamBelajarKonfigurasi::query()->firstOrFail()->update([
            'hari_aktif' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Sabtu'],
        ]);
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $guru = User::factory()->guru()->create();
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);
        $this->buatJadwal($guru, $mapel, '12-1', 'Rekayasa Perangkat Lunak', 'Jumat');

        $this->actingAs($siswa)
            ->get(route('dashboard.siswa.jadwal-pelajaran.index'))
            ->assertOk()
            ->assertDontSee('hari=Jumat')
            ->assertSee('Hari Jumat sedang dinonaktifkan.');
    }

    public function test_jadwal_periode_lama_tidak_tampil_untuk_siswa(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $guru = User::factory()->guru()->create(['name' => 'Budi Guru']);
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $jadwal = $this->buatJadwal($guru, $mapel, '12-1', 'Rekayasa Perangkat Lunak', 'Senin');
        JadwalPelajaran::whereKey($jadwal->id)->update([
            'tahun_ajaran' => '2020/2021',
            'semester' => Periode::SEMESTER_GANJIL,
        ]);

        $this->actingAs($siswa)
            ->get(route('dashboard.siswa.jadwal-pelajaran.index'))
            ->assertOk()
            ->assertDontSee('Matematika')
            ->assertSee('Jadwal Pelajaran Belum Tersedia');
    }
}
