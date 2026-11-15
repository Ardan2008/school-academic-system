<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\Materi;
use App\Models\Nilai;
use App\Models\Pengumuman;
use App\Models\Tugas;
use App\Models\User;
use App\Support\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Riwayat Aktivitas menggabungkan sumber akademik yang sudah ada. Test ini
 * memastikan batas rombel/akun tetap berlaku, filter bekerja, dan paginasi
 * tidak membocorkan aktivitas di luar halaman yang diminta.
 */
class SiswaRiwayatAktivitasTest extends TestCase
{
    use RefreshDatabase;

    private function mapel(string $kode, string $nama): MataPelajaran
    {
        return MataPelajaran::factory()->create(['kode' => $kode, 'nama' => $nama]);
    }

    private function tugas(
        MataPelajaran $mapel,
        ?User $guru,
        string $kelas,
        ?string $jurusan,
        string $judul,
        string $deskripsi = 'Kerjakan dengan rapi.',
    ): Tugas {
        return Tugas::create([
            'kelas' => $kelas,
            'jurusan' => $jurusan,
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru?->id,
            'judul' => $judul,
            'deskripsi' => $deskripsi,
            'deadline' => now()->addDays(3),
        ]);
    }

    private function materi(
        MataPelajaran $mapel,
        ?User $guru,
        string $kelas,
        ?string $jurusan,
        string $judul,
        string $deskripsi = 'Rangkuman materi.',
    ): Materi {
        return Materi::create([
            'kelas' => $kelas,
            'jurusan' => $jurusan,
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru?->id,
            'judul' => $judul,
            'deskripsi' => $deskripsi,
            'tanggal' => now()->subDay(),
        ]);
    }

    public function test_siswa_hanya_melihat_aktivitas_milik_akun_dan_rombelnya(): void
    {
        $matematika = $this->mapel('MTK', 'Matematika');
        $fisika = $this->mapel('FIS', 'Fisika');
        $guru = User::factory()->guru()->create(['name' => 'Budi Guru']);

        $siswa = User::factory()->siswa('12-1')->create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'name' => 'Ahmad Fauzi',
        ]);
        $siswaSekelas = User::factory()->siswa('12-1')->create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'name' => 'Siti Aminah',
        ]);

        $this->tugas($matematika, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Tugas Aljabar');
        $this->tugas($fisika, $guru, '11-2', 'Multimedia', 'Tugas Rahasia Kelas Lain');

        $this->materi($matematika, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Materi Matriks');
        $this->materi($fisika, $guru, '11-2', 'Multimedia', 'Materi Optik Kelas Lain');

        JadwalPelajaran::create([
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'hari' => 'Senin',
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:30',
            'mata_pelajaran_id' => $matematika->id,
            'guru_id' => $guru->id,
            'ruang' => 'R.101',
        ]);
        JadwalPelajaran::create([
            'kelas' => '11-2',
            'jurusan' => 'Multimedia',
            'hari' => 'Selasa',
            'jam_mulai' => '09:00',
            'jam_selesai' => '10:30',
            'mata_pelajaran_id' => $fisika->id,
            'guru_id' => $guru->id,
            'ruang' => 'R.202',
        ]);

        Nilai::create([
            'user_id' => $siswa->id,
            'mata_pelajaran_id' => $matematika->id,
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'tahun_ajaran' => Periode::tahunAjaranSekarang(),
            'semester' => Periode::semesterSekarang(),
            'nilai' => 88,
            'kkm' => 75,
        ]);
        Nilai::create([
            'user_id' => $siswaSekelas->id,
            'mata_pelajaran_id' => $fisika->id,
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'tahun_ajaran' => Periode::tahunAjaranSekarang(),
            'semester' => Periode::semesterSekarang(),
            'nilai' => 99,
            'kkm' => 75,
        ]);

        Pengumuman::create([
            'judul' => 'Pengumuman Sekolah Umum',
            'isi' => 'Berlaku untuk semua siswa.',
            'kelas' => null,
            'jurusan' => null,
            'publish_at' => now()->subHour(),
        ]);
        Pengumuman::create([
            'judul' => 'Pengumuman Kelas Lain',
            'isi' => 'Hanya untuk kelas lain.',
            'kelas' => '11-2',
            'jurusan' => 'Multimedia',
            'publish_at' => now()->subHour(),
        ]);
        Pengumuman::create([
            'judul' => 'Pengumuman Khusus Guru',
            'isi' => 'Bukan untuk siswa.',
            'kelas' => null,
            'jurusan' => null,
            'target_role' => Pengumuman::TARGET_GURU,
            'publish_at' => now()->subHour(),
        ]);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.riwayat-aktivitas.index'));

        $res->assertOk();
        $res->assertSee('Tugas Aljabar');
        $res->assertSee('Materi Matriks');
        $res->assertSee('Jadwal Matematika');
        $res->assertSee('Nilai Matematika');
        $res->assertSee('Pengumuman Sekolah Umum');

        $res->assertDontSee('Tugas Rahasia Kelas Lain');
        $res->assertDontSee('Materi Optik Kelas Lain');
        $res->assertDontSee('Jadwal Fisika');
        $res->assertDontSee('Nilai Fisika');
        $res->assertDontSee('Pengumuman Kelas Lain');
        $res->assertDontSee('Pengumuman Khusus Guru');
    }

    public function test_filter_kategori_hanya_menampilkan_jenis_yang_dipilih(): void
    {
        $mapel = $this->mapel('MTK', 'Matematika');
        $guru = User::factory()->guru()->create();
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $this->tugas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Tugas Aljabar');
        $this->materi($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Materi Matriks');
        JadwalPelajaran::create([
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'hari' => 'Senin',
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:30',
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'ruang' => 'R.101',
        ]);
        Nilai::create([
            'user_id' => $siswa->id,
            'mata_pelajaran_id' => $mapel->id,
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'tahun_ajaran' => Periode::tahunAjaranSekarang(),
            'semester' => Periode::semesterSekarang(),
            'nilai' => 88,
            'kkm' => 75,
        ]);
        Pengumuman::create([
            'judul' => 'Pengumuman Sekolah Umum',
            'isi' => 'Berlaku untuk semua siswa.',
            'publish_at' => now()->subHour(),
        ]);

        $judulPerKategori = [
            'tugas' => 'Tugas Aljabar',
            'nilai' => 'Nilai Matematika',
            'jadwal' => 'Jadwal Matematika',
            'pengumuman' => 'Pengumuman Sekolah Umum',
            'materi' => 'Materi Matriks',
        ];

        foreach ($judulPerKategori as $kategori => $judul) {
            $res = $this->actingAs($siswa)->get(route('dashboard.siswa.riwayat-aktivitas.index', [
                'kategori' => $kategori,
            ]));

            $res->assertOk();
            $res->assertSee($judul);

            foreach ($judulPerKategori as $kategoriLain => $judulLain) {
                if ($kategoriLain !== $kategori) {
                    $res->assertDontSee($judulLain);
                }
            }
        }
    }

    public function test_pencarian_mencocokkan_judul_dan_deskripsi(): void
    {
        $mapel = $this->mapel('MTK', 'Matematika');
        $guru = User::factory()->guru()->create();
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $this->tugas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Tugas Aljabar Linear');
        $this->tugas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Tugas Geometri');
        $this->materi($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Materi Integral', 'Rangkuman integral lipat.');

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.riwayat-aktivitas.index', [
            'search' => 'aljabar',
        ]));
        $res->assertOk();
        $res->assertSee('Tugas Aljabar Linear');
        $res->assertDontSee('Tugas Geometri');
        $res->assertDontSee('Materi Integral');

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.riwayat-aktivitas.index', [
            'search' => 'integral lipat',
        ]));
        $res->assertOk();
        $res->assertSee('Materi Integral');
        $res->assertDontSee('Tugas Aljabar Linear');
        $res->assertDontSee('Tugas Geometri');
    }

    public function test_filter_tanggal_memakai_zona_wib(): void
    {
        $mapel = $this->mapel('MTK', 'Matematika');
        $guru = User::factory()->guru()->create();
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $lama = $this->materi($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Materi Lama');
        DB::table('materi')->where('id', $lama->id)->update([
            'created_at' => Carbon::parse('2026-01-10 10:00:00'),
            'updated_at' => Carbon::parse('2026-01-10 10:00:00'),
        ]);

        $baru = $this->materi($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Materi Baru');
        DB::table('materi')->where('id', $baru->id)->update([
            'created_at' => Carbon::parse('2026-02-10 10:00:00'),
            'updated_at' => Carbon::parse('2026-02-10 10:00:00'),
        ]);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.riwayat-aktivitas.index', [
            'dari' => '2026-02-01',
            'sampai' => '2026-02-28',
        ]));

        $res->assertOk();
        $res->assertSee('Materi Baru');
        $res->assertDontSee('Materi Lama');
    }

    public function test_paginasi_maksimal_sepuluh_aktivitas_per_halaman(): void
    {
        $mapel = $this->mapel('MTK', 'Matematika');
        $guru = User::factory()->guru()->create();
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        foreach (range(1, 12) as $nomor) {
            $materi = $this->materi($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Materi '.str_pad((string) $nomor, 2, '0', STR_PAD_LEFT));
            DB::table('materi')->where('id', $materi->id)->update([
                'created_at' => Carbon::parse('2026-03-01 10:00:00')->addMinutes($nomor),
                'updated_at' => Carbon::parse('2026-03-01 10:00:00')->addMinutes($nomor),
            ]);
        }

        $halamanSatu = $this->actingAs($siswa)->get(route('dashboard.siswa.riwayat-aktivitas.index'));
        $halamanSatu->assertOk();
        $halamanSatu->assertSee('Materi 12');
        $halamanSatu->assertSee('Materi 03');
        $halamanSatu->assertDontSee('Materi 02');
        $halamanSatu->assertDontSee('Materi 01');

        $halamanDua = $this->actingAs($siswa)->get(route('dashboard.siswa.riwayat-aktivitas.index', [
            'page' => 2,
        ]));
        $halamanDua->assertOk();
        $halamanDua->assertSee('Materi 02');
        $halamanDua->assertSee('Materi 01');
        $halamanDua->assertDontSee('Materi 12');
    }

    public function test_siswa_tanpa_aktivitas_melihat_keadaan_kosong(): void
    {
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.riwayat-aktivitas.index'));

        $res->assertOk();
        $res->assertSee('Belum ada aktivitas akademik tercatat.');
        $res->assertSee('0 aktivitas tercatat');
    }

    public function test_filter_yang_tidak_menemukan_hasil_memberi_jalan_keluar(): void
    {
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.riwayat-aktivitas.index', [
            'search' => 'tidak-ada-yang-cocok',
        ]));

        $res->assertOk();
        $res->assertSee('Tidak ada aktivitas yang cocok dengan filter saat ini.');
        $res->assertSee('Hapus semua filter');
    }

    public function test_guru_tidak_bisa_membuka_riwayat_aktivitas_siswa(): void
    {
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)
            ->get(route('dashboard.siswa.riwayat-aktivitas.index'))
            ->assertForbidden();
    }
}
