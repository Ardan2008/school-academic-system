<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_menampilkan_statistik_siswa_dan_guru_aktif_dari_database(): void
    {
        User::factory()->admin()->create();

        User::factory()->siswa()->create(['status' => User::STATUS_AKTIF]);
        User::factory()->siswa()->create(['status' => User::STATUS_NONAKTIF]);
        User::factory()->siswa()->create(['status' => User::STATUS_LULUS]);

        User::factory()->guru()->create(['status' => User::STATUS_AKTIF]);
        User::factory()->guru()->create(['status' => User::STATUS_AKTIF]);
        User::factory()->guru()->create(['status' => User::STATUS_NONAKTIF]);
        User::factory()->guru()->create(['status' => 'Cuti']);

        $this->get(route('beranda'))
            ->assertOk()
            ->assertViewHas('totalSiswa', 3)
            ->assertViewHas('guruAktif', 2)
            ->assertSee('data-count="3"', false)
            ->assertSee('data-count="2"', false)
            ->assertDontSee('data-count="2000"', false);
    }

    public function test_angka_statistik_berubah_saat_data_ditambah_diubah_dan_dihapus(): void
    {
        $siswa = User::factory()->siswa()->create(['status' => User::STATUS_AKTIF]);
        $guru = User::factory()->guru()->create(['status' => User::STATUS_AKTIF]);

        $this->get(route('beranda'))
            ->assertViewHas('totalSiswa', 1)
            ->assertViewHas('guruAktif', 1);

        $siswaBaru = User::factory()->siswa()->create(['status' => User::STATUS_AKTIF]);

        $this->get(route('beranda'))
            ->assertViewHas('totalSiswa', 2);

        $guru->update(['is_active' => false]);

        $this->get(route('beranda'))
            ->assertViewHas('guruAktif', 0);

        $guru->update(['status' => User::STATUS_AKTIF]);

        $this->get(route('beranda'))
            ->assertViewHas('guruAktif', 1);

        $siswaBaru->delete();

        $this->get(route('beranda'))
            ->assertViewHas('totalSiswa', 1);

        $siswa->delete();

        $this->get(route('beranda'))
            ->assertViewHas('totalSiswa', 0);
    }
}
