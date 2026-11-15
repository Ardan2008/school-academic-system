<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\Materi;
use App\Models\PengumpulanTugas;
use App\Models\Tugas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuruTugasMateriTest extends TestCase
{
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

    private function mapel(string $nama = 'Matematika'): MataPelajaran
    {
        return MataPelajaran::factory()->nama($nama)->create();
    }

    private function siswa(string $kelas = '12-1'): User
    {
        return User::factory()->siswa($kelas)->create([
            'jurusan' => self::JURUSAN,
            'name' => 'Siswa Uji',
            'status' => User::STATUS_AKTIF,
        ]);
    }

    private function jadwal(User $guru, MataPelajaran $mapel, string $kelas = '12-1'): JadwalPelajaran
    {
        return JadwalPelajaran::create([
            'kelas' => $kelas,
            'jurusan' => self::JURUSAN,
            'hari' => 'Senin',
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:30',
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'ruang' => 'R.101',
        ]);
    }

    private function tugas(User $guru, MataPelajaran $mapel, string $judul = 'Tugas Uji'): Tugas
    {
        return Tugas::create([
            'kelas' => '12-1',
            'jurusan' => self::JURUSAN,
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'judul' => $judul,
            'deskripsi' => 'Kerjakan dengan rapi.',
            'deadline' => now()->addDays(2),
        ]);
    }

    public function test_guru_membuat_dan_melihat_tugas_pada_kelas_yang_diajar(): void
    {
        $guru = $this->guru();
        $mapel = $this->mapel();
        $this->siswa();
        $this->jadwal($guru, $mapel);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.index'))
            ->assertOk()
            ->assertSee('Tambah Tugas');

        $this->actingAs($guru, 'guru')
            ->post(route('dashboard.guru.tugas-materi.tugas.store'), [
                'mata_pelajaran_id' => $mapel->id,
                'rombel' => '12-1|'.self::JURUSAN,
                'judul' => 'PR Aljabar',
                'deskripsi' => 'Kerjakan nomor 1 sampai 10.',
                'deadline' => now()->addDays(3)->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('dashboard.guru.tugas-materi.index', ['tab' => 'tugas']));

        $this->assertDatabaseHas('tugas', [
            'guru_id' => $guru->id,
            'mata_pelajaran_id' => $mapel->id,
            'kelas' => '12-1',
            'jurusan' => self::JURUSAN,
            'judul' => 'PR Aljabar',
        ]);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.index'))
            ->assertOk()
            ->assertSee('PR Aljabar');
    }

    public function test_guru_tidak_bisa_membuat_tugas_di_kelas_lain(): void
    {
        $guru = $this->guru();
        $mapel = $this->mapel();
        $this->siswa();
        $this->jadwal($guru, $mapel);

        $this->actingAs($guru, 'guru')
            ->post(route('dashboard.guru.tugas-materi.tugas.store'), [
                'mata_pelajaran_id' => $mapel->id,
                'rombel' => '11-1|'.self::JURUSAN,
                'judul' => 'Tugas Kelas Lain',
                'deadline' => null,
            ])
            ->assertSessionHasErrors('rombel');

        $this->assertDatabaseCount('tugas', 0);
    }

    public function test_guru_tidak_bisa_mengubah_atau_menghapus_tugas_guru_lain(): void
    {
        $guru = $this->guru();
        $guruLain = $this->guru();
        $mapel = $this->mapel();
        $this->jadwal($guru, $mapel);
        $tugas = $this->tugas($guruLain, $mapel, 'Tugas Guru Lain');

        $this->actingAs($guru, 'guru')
            ->put(route('dashboard.guru.tugas-materi.tugas.update', $tugas), [
                'mata_pelajaran_id' => $mapel->id,
                'rombel' => '12-1|'.self::JURUSAN,
                'judul' => 'Diubah',
            ])
            ->assertForbidden();

        $this->actingAs($guru, 'guru')
            ->delete(route('dashboard.guru.tugas-materi.tugas.destroy', $tugas))
            ->assertForbidden();

        $this->assertDatabaseHas('tugas', ['id' => $tugas->id, 'judul' => 'Tugas Guru Lain']);
    }

    public function test_guru_dapat_mengelola_materi(): void
    {
        $guru = $this->guru();
        $mapel = $this->mapel();
        $this->siswa();
        $this->jadwal($guru, $mapel);

        $this->actingAs($guru, 'guru')
            ->post(route('dashboard.guru.tugas-materi.materi.store'), [
                'mata_pelajaran_id' => $mapel->id,
                'rombel' => '12-1|'.self::JURUSAN,
                'judul' => 'Materi Matriks',
                'deskripsi' => 'Rangkuman matriks.',
                'tanggal' => now()->toDateString(),
            ])
            ->assertRedirect(route('dashboard.guru.tugas-materi.index', ['tab' => 'materi']));

        $materi = Materi::query()->firstOrFail();

        $this->assertSame($guru->id, $materi->guru_id);
        $this->assertSame('Materi Matriks', $materi->judul);

        $this->actingAs($guru, 'guru')
            ->put(route('dashboard.guru.tugas-materi.materi.update', $materi), [
                'mata_pelajaran_id' => $mapel->id,
                'rombel' => '12-1|'.self::JURUSAN,
                'judul' => 'Materi Matriks Revisi',
                'tanggal' => now()->toDateString(),
            ])
            ->assertRedirect(route('dashboard.guru.tugas-materi.index', ['tab' => 'materi']));

        $this->assertDatabaseHas('materi', [
            'id' => $materi->id,
            'judul' => 'Materi Matriks Revisi',
        ]);

        $this->actingAs($guru, 'guru')
            ->delete(route('dashboard.guru.tugas-materi.materi.destroy', $materi))
            ->assertRedirect(route('dashboard.guru.tugas-materi.index', ['tab' => 'materi']));

        $this->assertDatabaseMissing('materi', ['id' => $materi->id]);
    }

    public function test_guru_melihat_pengumpulan_dan_menyimpan_nilai(): void
    {
        $guru = $this->guru();
        $mapel = $this->mapel();
        $siswa = $this->siswa();
        $this->jadwal($guru, $mapel);
        $tugas = $this->tugas($guru, $mapel);

        $pengumpulan = PengumpulanTugas::create([
            'tugas_id' => $tugas->id,
            'user_id' => $siswa->id,
            'dikumpulkan_at' => now()->subHour(),
            'status' => PengumpulanTugas::STATUS_DIKUMPULKAN,
        ]);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.pengumpulan', $tugas))
            ->assertOk()
            ->assertSee('Siswa Uji')
            ->assertSee('Dikumpulkan');

        $this->actingAs($guru, 'guru')
            ->put(route('dashboard.guru.tugas-materi.pengumpulan.update', $pengumpulan), [
                'nilai' => 92.5,
            ])
            ->assertRedirect(route('dashboard.guru.tugas-materi.pengumpulan', $tugas));

        $this->assertDatabaseHas('pengumpulan_tugas', [
            'id' => $pengumpulan->id,
            'nilai' => 92.5,
            'status' => PengumpulanTugas::STATUS_DINILAI,
        ]);
    }

    public function test_guru_tidak_bisa_membuka_pengumpulan_tugas_guru_lain(): void
    {
        $guru = $this->guru();
        $guruLain = $this->guru();
        $mapel = $this->mapel();
        $tugas = $this->tugas($guruLain, $mapel);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.pengumpulan', $tugas))
            ->assertForbidden();
    }

    public function test_guru_tanpa_mapel_melihat_peringatan_bukan_form(): void
    {
        $guru = User::factory()->guru()->create([
            'mata_pelajaran' => null,
            'must_change_password' => false,
        ]);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.index'))
            ->assertOk()
            ->assertSee('Mata Pelajaran Belum Ditugaskan');
    }
}
