<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\Materi;
use App\Models\PengumpulanTugas;
use App\Models\Tugas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman Tugas & Materi siswa harus membaca tabel `tugas`, `materi`, dan
 * `pengumpulan_tugas` apa adanya: hanya untuk rombel siswa yang login, status
 * pengerjaan dihitung dari pengumpulan, dan tetap memberi keterangan yang jelas
 * saat data belum ada.
 */
class SiswaTugasMateriTest extends TestCase
{
    use RefreshDatabase;

    private function buatTugas(
        MataPelajaran $mapel,
        ?User $guru,
        string $kelas,
        ?string $jurusan,
        string $judul,
        ?string $deadline = null,
    ): Tugas {
        return Tugas::create([
            'kelas' => $kelas,
            'jurusan' => $jurusan,
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru?->id,
            'judul' => $judul,
            'deskripsi' => 'Kerjakan dengan rapi.',
            'deadline' => $deadline,
        ]);
    }

    public function test_siswa_hanya_melihat_tugas_dan_materi_kelasnya_sendiri(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $mapelLain = MataPelajaran::factory()->create(['kode' => 'FIS', 'nama' => 'Fisika']);
        $guru = User::factory()->guru()->create(['name' => 'Budi Guru']);

        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $this->buatTugas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'PR Matematika', now()->addDays(3)->toDateTimeString());
        // Rombel lain tidak boleh ikut tampil.
        $this->buatTugas($mapelLain, $guru, '11-2', 'Multimedia', 'PR Fisika', now()->addDays(3)->toDateTimeString());

        Materi::create([
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'judul' => 'Materi Matriks',
            'deskripsi' => 'Rangkuman matriks.',
            'tanggal' => now()->subDay(),
        ]);
        Materi::create([
            'kelas' => '11-2',
            'jurusan' => 'Multimedia',
            'mata_pelajaran_id' => $mapelLain->id,
            'guru_id' => $guru->id,
            'judul' => 'Materi Optik',
            'deskripsi' => 'Rangkuman optik.',
            'tanggal' => now()->subDay(),
        ]);

        // Tab tugas (default).
        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index'));
        $res->assertOk();
        $res->assertSee('PR Matematika');
        $res->assertSee('Budi Guru');
        $res->assertDontSee('PR Fisika');

        // Tab materi.
        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index', ['tab' => 'materi']));
        $res->assertOk();
        $res->assertSee('Materi Matriks');
        $res->assertDontSee('Materi Optik');
        $res->assertDontSee('PR Matematika');
    }

    public function test_status_tugas_dihitung_dari_pengumpulan_dan_tenggat(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $guru = User::factory()->guru()->create();
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $dikumpulkan = $this->buatTugas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Tugas Sudah Kumpul', now()->addDays(2)->toDateTimeString());
        $this->buatTugas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Tugas Menunggu', now()->addDays(4)->toDateTimeString());
        $this->buatTugas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Tugas Terlambat', now()->subDays(2)->toDateTimeString());

        PengumpulanTugas::create([
            'tugas_id' => $dikumpulkan->id,
            'user_id' => $siswa->id,
            'dikumpulkan_at' => now()->subHour(),
            'status' => PengumpulanTugas::STATUS_DIKUMPULKAN,
        ]);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index'));

        $res->assertOk();
        $res->assertSee('Tugas Sudah Kumpul');
        $res->assertSee('Tugas Menunggu');
        $res->assertSee('Tugas Terlambat');
        $res->assertSee('Dikumpulkan');
        $res->assertSee('Menunggu');
        $res->assertSee('Terlambat');
    }

    public function test_filter_status_hanya_menampilkan_tugas_yang_sesuai(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $guru = User::factory()->guru()->create();
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $this->buatTugas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Tugas Menunggu', now()->addDays(4)->toDateTimeString());
        $this->buatTugas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Tugas Terlambat', now()->subDays(2)->toDateTimeString());

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index', [
            'tab' => 'tugas',
            'status' => 'terlambat',
        ]));

        $res->assertOk();
        $res->assertSee('Tugas Terlambat');
        $res->assertDontSee('Tugas Menunggu');
    }

    public function test_status_di_luar_daftar_diabaikan(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $this->buatTugas($mapel, null, '12-1', 'Rekayasa Perangkat Lunak', 'Tugas Menunggu', now()->addDays(4)->toDateTimeString());

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index', [
            'tab' => 'tugas',
            'status' => 'ngawur',
        ]));

        $res->assertOk();
        $res->assertSee('Tugas Menunggu');
    }

    public function test_tugas_tanpa_jurusan_berlaku_untuk_semua_jurusan(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $this->buatTugas($mapel, null, '12-1', null, 'Tugas Umum', now()->addDays(3)->toDateTimeString());

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index'));

        $res->assertOk();
        $res->assertSee('Tugas Umum');
    }

    public function test_siswa_tanpa_data_melihat_keterangan_belum_ada(): void
    {
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index'));

        $res->assertOk();
        $res->assertSee('Belum Ada Tugas & Materi');
    }

    public function test_siswa_tanpa_kelas_melihat_peringatan_data_kelas(): void
    {
        $siswa = User::factory()->siswa()->create(['kelas' => null, 'jurusan' => null]);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index'));

        $res->assertOk();
        $res->assertSee('Data Kelas Belum Diatur');
    }

    public function test_pencarian_tugas_dan_materi_mempertahankan_query_di_tab_dan_status(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $guru = User::factory()->guru()->create();
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $this->buatTugas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'PR Aljabar', now()->addDays(3)->toDateTimeString());
        $this->buatTugas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'PR Geometri', now()->addDays(3)->toDateTimeString());
        $this->buatTugas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Latihan Aljabar', now()->subDay()->toDateTimeString());

        Materi::create([
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'judul' => 'Materi Aljabar',
            'deskripsi' => 'Rangkuman aljabar.',
            'tanggal' => now()->subDay(),
        ]);
        Materi::create([
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'judul' => 'Materi Optik',
            'deskripsi' => 'Rangkuman optik.',
            'tanggal' => now()->subDay(),
        ]);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index', [
            'tab' => 'tugas',
            'q' => 'aljabar',
        ]));

        $res->assertOk();
        $res->assertSee('PR Aljabar');
        $res->assertDontSee('PR Geometri');
        $res->assertSee('value="aljabar"', false);
        $res->assertSee('q=aljabar');

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index', [
            'tab' => 'materi',
            'q' => 'aljabar',
        ]));

        $res->assertOk();
        $res->assertSee('Materi Aljabar');
        $res->assertDontSee('Materi Optik');
        $res->assertSee('value="aljabar"', false);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index', [
            'tab' => 'tugas',
            'status' => 'menunggu',
            'q' => 'aljabar',
        ]));

        $res->assertOk();
        $res->assertSee('PR Aljabar');
        $res->assertDontSee('Latihan Aljabar');
        $res->assertSee('q=aljabar');
        $res->assertSee('status=menunggu');

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index', [
            'tab' => 'materi',
            'q' => 'tidakada',
        ]));

        $res->assertOk();
        $res->assertSee('Materi Tidak Ditemukan');
        $res->assertSee('Tidak ada materi yang cocok dengan pencarian', false);
        $res->assertSee('Tampilkan Semua Materi');
    }

    public function test_deskripsi_panjang_tidak_dipotong_di_server(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        // Lebih dari 80 karakter supaya potongan lama ("...") sampai akhir teks.
        $deskripsi = str_repeat('Kerjakan dengan teliti. ', 12).'PENUTUP_UNIK';

        Tugas::create([
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'mata_pelajaran_id' => $mapel->id,
            'judul' => 'Tugas Deskripsi Panjang',
            'deskripsi' => $deskripsi,
        ]);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index'));

        $res->assertOk();
        // Teks lengkap tetap ada di HTML (hanya dipotong secara visual via clamp).
        $res->assertSee('PENUTUP_UNIK');
        $res->assertSee('Lihat selengkapnya');
    }
}
