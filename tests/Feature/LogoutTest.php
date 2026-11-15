<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public static function roleProvider(): array
    {
        return [
            'admin' => ['admin'],
            'guru' => ['guru'],
            'siswa' => ['siswa'],
        ];
    }

    protected function makeUser(string $role): User
    {
        $attributes = [
            'email' => "{$role}.logout@example.com",
            'password' => 'Password123',
            'must_change_password' => false,
        ];

        return match ($role) {
            'admin' => User::factory()->admin()->create($attributes),
            'guru' => User::factory()->guru()->create($attributes + [
                'nip' => '198501012010011001',
            ]),
            'siswa' => User::factory()->siswa('12-1')->create($attributes + [
                'nis' => '1234567890',
            ]),
        };
    }

    protected function credentials(string $role, User $user): array
    {
        return match ($role) {
            'admin' => [
                'role' => 'admin',
                'email' => $user->email,
                'password' => 'Password123',
            ],
            'guru' => [
                'role' => 'guru',
                'nip' => $user->nip,
                'password' => 'Password123',
            ],
            'siswa' => [
                'role' => 'siswa',
                'nis' => $user->nis,
                'password' => 'Password123',
            ],
        };
    }

    protected function dashboardRoute(string $role): string
    {
        return match ($role) {
            'admin' => route('dashboard.admin.index'),
            'guru' => route('dashboard.guru.index'),
            'siswa' => route('dashboard.siswa.index'),
        };
    }

    #[DataProvider('roleProvider')]
    public function test_logout_menghapus_sesi_dan_mengarahkan_ke_login_untuk_setiap_role(string $role): void
    {
        $user = $this->makeUser($role);
        $dashboard = $this->dashboardRoute($role);

        $this->post(route('login.store'), $this->credentials($role, $user))
            ->assertRedirect($dashboard);

        $this->assertNotNull(session("jwt_token_{$role}"));

        $dashboardResponse = $this->get($dashboard);

        $dashboardResponse->assertOk()
            ->assertSee('id="logout-modal"', false)
            ->assertSee('data-logout-no', false)
            ->assertSee('data-logout-yes', false)
            ->assertSee('name="role" value="'.$role.'"', false)
            ->assertSee('id="logout-form-sidebar"', false)
            ->assertSee("noBtn.addEventListener('click', closeModal)", false)
            ->assertSee('activeLogoutForm.submit()', false)
            ->assertSee('event.persisted', false);

        $cacheControl = (string) $dashboardResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);

        $this->post(route('logout'), ['role' => $role])
            ->assertRedirect(route('login'))
            ->assertSessionHas('logged_out', true);

        $this->assertGuest();
        $this->assertGuest('web');
        $this->assertGuest('portal');
        $this->assertGuest('admin');
        $this->assertGuest('guru');
        $this->assertGuest('siswa');
        $this->assertFalse(session()->has("jwt_token_{$role}"));
        $this->assertFalse(session()->has('jwt_token'));

        $this->get($dashboard)->assertRedirect(route('login'));
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    #[DataProvider('roleProvider')]
    public function test_logout_mencatat_aktivitas_role_yang_benar(string $role): void
    {
        $user = $this->makeUser($role);

        $this->post(route('login.store'), $this->credentials($role, $user))
            ->assertRedirect($this->dashboardRoute($role));

        $this->post(route('logout'), ['role' => $role])
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'role' => $role,
            'activity_type' => ActivityLog::TIPE_AUTH,
            'description' => 'Keluar dari sistem.',
        ]);
    }

    public function test_logout_tanpa_field_role_tetap_bekerja(): void
    {
        $user = $this->makeUser('admin');

        $this->post(route('login.store'), $this->credentials('admin', $user))
            ->assertRedirect(route('dashboard.admin.index'));

        $this->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->get(route('dashboard.admin.index'))->assertRedirect(route('login'));
    }

    public function test_logout_memakai_role_dari_form_untuk_aktivitas_yang_benar(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin.multi@example.com',
            'password' => 'Password123',
            'must_change_password' => false,
        ]);
        $guru = User::factory()->guru()->create([
            'email' => 'guru.multi@example.com',
            'nip' => '198501012010011002',
            'password' => 'Password123',
            'must_change_password' => false,
        ]);

        $this->post(route('login.store'), [
            'role' => 'admin',
            'email' => $admin->email,
            'password' => 'Password123',
        ])->assertRedirect(route('dashboard.admin.index'));

        $this->post(route('login.store'), [
            'role' => 'guru',
            'nip' => $guru->nip,
            'password' => 'Password123',
        ])->assertRedirect(route('dashboard.guru.index'));

        $this->post(route('logout'), ['role' => 'admin'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'role' => 'admin',
            'description' => 'Keluar dari sistem.',
        ]);

        $this->assertDatabaseMissing('activity_logs', [
            'user_id' => $guru->id,
            'description' => 'Keluar dari sistem.',
        ]);
    }

    public function test_halaman_login_membersihkan_token_lokal_setelah_logout(): void
    {
        $user = $this->makeUser('siswa');

        $this->post(route('login.store'), $this->credentials('siswa', $user))
            ->assertRedirect(route('dashboard.siswa.index'));

        $this->post(route('logout'), ['role' => 'siswa'])
            ->assertRedirect(route('login'));

        $this->get(route('login'))
            ->assertOk()
            ->assertSee("localStorage.removeItem('jwt_token')", false)
            ->assertSee("sessionStorage.removeItem('jwt_token')", false);
    }
}
