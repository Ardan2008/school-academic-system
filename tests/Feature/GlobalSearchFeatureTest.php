<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_search(): void
    {
        $this->get(route('dashboard.search.suggest'))
            ->assertRedirect(route('login'));

        $this->get(route('dashboard.search', ['q' => 'test']))
            ->assertRedirect(route('login'));
    }

    public function test_admin_suggest_empty_query_returns_initial_menus(): void
    {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);

        $response = $this->actingAs($admin)
            ->getJson(route('dashboard.search.suggest', ['q' => '']))
            ->assertOk()
            ->assertJsonPath('initial', true);

        $results = $response->json('results');
        $this->assertNotEmpty($results);
        $this->assertContains('Data Guru', array_column($results, 'label'));
        $this->assertContains('Data Siswa', array_column($results, 'label'));
    }

    public function test_admin_suggest_finds_guru_and_siswa(): void
    {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);
        $guru = User::factory()->guru()->create([
            'name' => 'Budi Susanto',
            'nip' => '100122',
            'must_change_password' => false,
        ]);
        $siswa = User::factory()->siswa('10-1')->create([
            'name' => 'Ahmad Santoso',
            'nis' => '2025001',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('dashboard.search.suggest', ['q' => 'santo']))
            ->assertOk()
            ->assertJsonPath('initial', false);

        $labels = array_column($response->json('results'), 'label');
        $this->assertTrue(collect($labels)->contains(fn ($l) => str_contains($l, 'Budi Susanto')));
        $this->assertTrue(collect($labels)->contains(fn ($l) => str_contains($l, 'Ahmad Santoso')));
    }

    public function test_guru_suggest_respects_boundary(): void
    {
        $guru = User::factory()->guru()->create([
            'name' => 'Dewi Lestari',
            'mata_pelajaran' => 'Matematika',
            'must_change_password' => false,
        ]);
        $siswa = User::factory()->siswa('10-1')->create([
            'name' => 'Dewi Sartika',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($guru)
            ->getJson(route('dashboard.search.suggest', ['q' => 'dewi']))
            ->assertOk();

        $labels = array_column($response->json('results'), 'label');
        $this->assertTrue(collect($labels)->contains(fn ($l) => str_contains($l, 'Dewi Sartika')));

        // Guru cannot search backup or admin-only modules
        $responseAdminCheck = $this->actingAs($guru)
            ->getJson(route('dashboard.search.suggest', ['q' => 'backup']))
            ->assertOk();
        $this->assertEmpty($responseAdminCheck->json('results'));
    }

    public function test_siswa_suggest_finds_relevant_information(): void
    {
        $siswa = User::factory()->siswa('10-1')->create([
            'jurusan' => 'RPL',
            'must_change_password' => false,
        ]);
        $guru = User::factory()->guru()->create(['name' => 'Pak Joko', 'must_change_password' => false]);
        $mapel = MataPelajaran::factory()->nama('Fisika')->create();

        JadwalPelajaran::create([
            'kelas' => '10-1',
            'jurusan' => 'RPL',
            'hari' => 'Senin',
            'jam_mulai' => '07:30',
            'jam_selesai' => '09:00',
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'ruang' => 'Lab 1',
        ]);

        $response = $this->actingAs($siswa)
            ->getJson(route('dashboard.search.suggest', ['q' => 'fisika']))
            ->assertOk();

        $labels = array_column($response->json('results'), 'label');
        $this->assertTrue(collect($labels)->contains(fn ($l) => str_contains(strtolower($l), 'fisika')));

        // Siswa cannot access admin management
        $responseAdminCheck = $this->actingAs($siswa)
            ->getJson(route('dashboard.search.suggest', ['q' => 'data guru']))
            ->assertOk();
        $this->assertEmpty($responseAdminCheck->json('results'));
    }

    public function test_siswa_suggest_empty_query_menampilkan_menu_riwayat_aktivitas(): void
    {
        $siswa = User::factory()->siswa('10-1')->create(['must_change_password' => false]);

        $response = $this->actingAs($siswa)
            ->getJson(route('dashboard.search.suggest', ['q' => '']))
            ->assertOk()
            ->assertJsonPath('initial', true);

        $labels = array_column($response->json('results'), 'label');

        $this->assertContains('Riwayat Aktivitas', $labels);
        $this->assertContains('Riwayat Akademik', $labels);
    }

    public function test_empty_results_when_keyword_not_found(): void
    {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);

        $response = $this->actingAs($admin)
            ->getJson(route('dashboard.search.suggest', ['q' => 'keywordyangpasti789tidakada']))
            ->assertOk();

        $this->assertEmpty($response->json('results'));
    }

    public function test_search_results_page_displays_results_and_empty_message(): void
    {
        $admin = User::factory()->admin()->create(['must_change_password' => false]);
        User::factory()->guru()->create([
            'name' => 'Bambang Sudirman',
            'must_change_password' => false,
        ]);

        $responseFound = $this->actingAs($admin)
            ->get(route('dashboard.search', ['q' => 'sudirman']))
            ->assertOk()
            ->assertSee('Hasil Pencarian')
            ->assertSee('Bambang Sudirman');

        $responseNotFound = $this->actingAs($admin)
            ->get(route('dashboard.search', ['q' => 'keywordpasti999tidakada']))
            ->assertOk()
            ->assertSee('Tidak ada hasil yang ditemukan');
    }
}
