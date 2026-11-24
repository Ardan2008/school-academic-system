<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\PengumpulanTugas;
use App\Models\Pengumuman;
use App\Models\Tugas;
use App\Models\User;
use App\Support\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard Siswa tidak boleh lagi memajang angka contoh yang ditulis tangan.
 * Test ini memastikan angka yang tampil benar-benar dihitung dari tabel nilai,
 * jadwal, tugas, dan pengumuman.
 */
class DashboardSiswaTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_siswa_menampilkan_data_dari_database(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);

        $guru = User::factory()->guru()->create([
            'name' => 'Budi Guru',
            'mata_pelajaran' => 'Matematika',
        ]);

        $siswa = User::factory()->siswa('12-1')->create([
            'name' => 'Ahmad Fauzi',
            'jurusan' => 'Rekayasa Perangkat Lunak',
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

        $hari = JadwalPelajaran::hariIni();

        if ($hari !== null) {
            JadwalPelajaran::create([
                'kelas' => '12-1',
                'jurusan' => 'Rekayasa Perangkat Lunak',
                'hari' => $hari,
                'jam_mulai' => '07:00',
                'jam_selesai' => '08:30',
                'mata_pelajaran_id' => $mapel->id,
                'guru_id' => $guru->id,
                'ruang' => 'R.999',
            ]);
        }

        $tugas = Tugas::create([
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'judul' => 'PR Matematika',
            'deskripsi' => 'Kerjakan soal berikut.',
            'deadline' => now()->addDays(3),
        ]);

        PengumpulanTugas::create([
            'tugas_id' => $tugas->id,
            'user_id' => $siswa->id,
            'dikumpulkan_at' => now()->subHour(),
            'status' => PengumpulanTugas::STATUS_DIKUMPULKAN,
        ]);

        Pengumuman::create([
            'judul' => 'Ujian Sekolah',
            'isi' => 'Ujian dimulai pekan depan.',
            'kelas' => null,
            'jurusan' => null,
            'publish_at' => now()->subHours(2),
        ]);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.index'));

        $res->assertOk();
        $res->assertSee('88.00');           // rata-rata nilai dari tabel nilai
        $res->assertSee('Matematika');      // jadwal dan tugas
        $res->assertSee('Ujian Sekolah');   // pengumuman menjadi notifikasi
        $res->assertSee('Budi Guru');       // guru pengampu tugas
        $res->assertSee(route('dashboard.siswa.riwayat-aktivitas.index'), false);
        $res->assertSee('Lihat Semua');

        if ($hari !== null) {
            $res->assertSee('R.999');       // jadwal hari ini
        }

        // Angka dan nama contoh yang dulu ditulis tangan tidak boleh muncul lagi.
        $res->assertDontSee('82.45');
        $res->assertDontSee('Alif Hamzah');
    }

    public function test_notifikasi_pengumuman_menampilkan_isi_penuh(): void
    {
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        // Lebih dari 80 karakter supaya potongan lama ("...") sampai akhir teks.
        $isi = str_repeat('Informasi penting sekolah. ', 10).'PENUTUP_PENGUMUMAN';

        Pengumuman::create([
            'judul' => 'Pengumuman Panjang',
            'isi' => $isi,
            'kelas' => null,
            'jurusan' => null,
            'publish_at' => now()->subHour(),
        ]);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.index'));

        $res->assertOk();
        // Isi lengkap tetap ada di HTML (dipotong hanya secara visual via clamp).
        $res->assertSee('PENUTUP_PENGUMUMAN');
        $res->assertSee('Lihat selengkapnya');
    }

    public function test_tugas_belum_dikumpulkan_menampilkan_daftar_ringkas_dan_tautan(): void
    {
        $mapel = MataPelajaran::factory()->create(['nama' => 'Matematika']);
        $guru = User::factory()->guru()->create(['name' => 'Budi Guru']);
        $siswa = User::factory()->siswa('12-1')->create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
        ]);

        $data = [
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
        ];

        Tugas::create($data + [
            'judul' => 'PR Menunggu',
            'deadline' => now()->addDays(2),
        ]);

        Tugas::create($data + [
            'judul' => 'PR Terlambat',
            'deadline' => now()->subDays(4),
        ]);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.index'));

        $res->assertOk()
            ->assertSee('data-region="tugas"', false)
            ->assertSee('PR Menunggu')
            ->assertSee('PR Terlambat')
            ->assertSee('Budi Guru')
            ->assertSee('Terlambat 4 hari')
            ->assertSee('Lihat Tugas')
            ->assertSee('status=menunggu', false)
            ->assertSee('status=terlambat', false);
    }
}
