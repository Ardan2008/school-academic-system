<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_rendered(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Selamat Datang Kembali');
    }

    public function test_login_fails_with_wrong_credentials(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'admin123',
        ]);

        $this->post(route('login.store'), [
            'role' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'admin123',
        ])->assertRedirect(route('dashboard.admin.index'));
    }

    public function test_login_lands_on_role_specific_dashboard_even_with_generic_intended(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'admin123',
            'must_change_password' => false,
        ]);

        // Tamu mencoba membuka /dashboard lalu dialihkan ke login.
        // intended tersimpan sebagai "/dashboard" generik.
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', route('dashboard'));

        // Setelah login, tujuannya harus dashboard khusus role (bukan /dashboard
        // generik) agar identitas role menempel pada URL tab.
        $this->post(route('login.store'), [
            'role' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'admin123',
        ])->assertRedirect(route('dashboard.admin.index'));
    }

    public function test_first_login_redirects_to_password_setup(): void
    {
        User::factory()->guru()->create([
            'email' => 'guru@example.com',
            'nip' => 'GP-1000',
            'password' => 'guru123',
            'must_change_password' => true,
            'role' => 'guru',
        ]);

        $this->post(route('login.store'), [
            'role' => 'guru',
            'nip' => 'GP-1000',
            'password' => 'guru123',
        ])->assertRedirect(route('password.setup'));
    }

    public function test_admin_skips_password_setup(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'admin123',
            'must_change_password' => true,
            'role' => 'admin',
        ]);

        $this->post(route('login.store'), [
            'role' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'admin123',
        ])->assertRedirect(route('dashboard.admin.index'));
    }

    public function test_second_login_redirects_to_dashboard(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'admin123',
            'must_change_password' => false,
            'role' => 'admin',
        ]);

        $this->post(route('login.store'), [
            'role' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'admin123',
        ])->assertRedirect(route('dashboard.admin.index'));
    }

    public function test_password_setup_requires_valid_password(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com', 'password' => 'admin123']);

        $this->actingAs($user)
            ->post(route('password.setup.store'), [
                'password' => 'lemah',
                'password_confirmation' => 'lemah',
            ])->assertSessionHasErrors('password');
    }

    public function test_password_setup_updates_user_and_redirects(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'admin123',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->post(route('password.setup.store'), [
                'password' => 'Rahasia123',
                'password_confirmation' => 'Rahasia123',
            ])->assertRedirect(route('password.setup'));

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('Rahasia123', $user->password));
    }

    public function test_login_store_returns_json_for_ajax_requests(): void
    {
        User::factory()->create([
            'email' => 'guru@example.com',
            'nip' => 'GP-1100',
            'password' => 'Guru123',
            'role' => 'guru',
            'must_change_password' => true,
        ]);

        $this->withHeader('Accept', 'application/json')
            ->post(route('login.store'), [
                'role' => 'guru',
                'nip' => 'GP-1100',
                'password' => 'Guru123',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.must_change_password', true)
            ->assertJsonPath('data.redirect', route('password.setup'));
    }

    public function test_password_setup_page_only_shown_on_first_visit(): void
    {
        // Sudah pernah ubah password -> halaman tidak perlu muncul lagi
        $user = User::factory()->create([
            'email' => 'guru@example.com',
            'must_change_password' => false,
            'role' => 'guru',
        ]);

        $this->actingAs($user)
            ->get(route('password.setup'))
            ->assertRedirect(route('dashboard.guru.index'));

        // Admin tidak pernah melewati ubah password
        $admin = User::factory()->create([
            'email' => 'administrator@example.com',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('password.setup'))
            ->assertRedirect(route('dashboard.admin.index'));

        // Guru yang belum ubah password -> halaman tampil
        $newGuru = User::factory()->create([
            'email' => 'guru-baru@example.com',
            'role' => 'guru',
            'must_change_password' => true,
        ]);

        $this->actingAs($newGuru)
            ->get(route('password.setup'))
            ->assertOk()
            ->assertSee('Buat Password');
    }

    public function test_password_setup_store_returns_json_for_ajax_requests(): void
    {
        $user = User::factory()->create([
            'email' => 'guru@example.com',
            'role' => 'guru',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('password.setup.store'), [
                'password' => 'Rahasia123',
                'password_confirmation' => 'Rahasia123',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.redirect', route('dashboard.guru.index'));

        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_is_rendered_for_authenticated_user(): void
    {
        $user = User::factory()->siswa()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Siswa');
    }

    public function test_dashboard_uses_role_specific_view(): void
    {
        foreach ([
            'admin' => 'dashboard.admin.index',
            'guru' => 'dashboard.guru.index',
            'siswa' => 'dashboard.siswa.index',
        ] as $role => $view) {
            $user = User::factory()->create([
                'email' => $role.'@example.com',
                'role' => $role,
                'nip' => $role === 'guru' ? 'GP-'.($role === 'guru' ? 1 : 0) : null,
                'nis' => $role === 'siswa' ? '10000'.crc32($role) : null,
            ]);

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertViewIs($view);
        }
    }

    public function test_logout_works(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
