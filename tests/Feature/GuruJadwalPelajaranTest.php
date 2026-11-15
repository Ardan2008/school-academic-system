<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuruJadwalPelajaranTest extends TestCase
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

    private function jadwal(
        User $guru,
        string $namaMapel,
        string $hari = 'Senin',
        string $ruang = 'R.101',
    ): JadwalPelajaran {
        $mapel = MataPelajaran::factory()->nama($namaMapel)->create();

        return JadwalPelajaran::create([
            'kelas' => '12-1',
            'jurusan' => self::JURUSAN,
            'hari' => $hari,
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:30',
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'ruang' => $ruang,
        ]);
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

        $this->jadwal($guru, 'Matematika', 'Senin');
        $this->jadwal($guru, 'Fisika', 'Selasa');

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.jadwal-pelajaran.index', ['hari' => 'Senin']))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertDontSee('Fisika');
    }

    public function test_guru_tanpa_jadwal_melihat_keterangan_belum_ada_jadwal(): void
    {
        $guru = $this->guru();

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.jadwal-pelajaran.index'))
            ->assertOk()
            ->assertSee('Belum Ada Jadwal Mengajar');
    }

    public function test_hari_di_luar_daftar_diabaikan(): void
    {
        $guru = $this->guru();
        $this->jadwal($guru, 'Matematika', 'Senin');

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.jadwal-pelajaran.index', ['hari' => 'Minggu']))
            ->assertOk()
            ->assertSee('Matematika');
    }
}
