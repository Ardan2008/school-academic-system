<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SimultaneousLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_roles_can_login_and_access_dashboards_simultaneously(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@sekolah.sch.id',
            'password' => 'AdminPass123',
            'must_change_password' => false,
        ]);

        $guru = User::factory()->guru()->create([
            'email' => 'guru@sekolah.sch.id',
            'nip' => '198501012010011001',
            'password' => 'GuruPass123',
            'must_change_password' => false,
        ]);

        $siswa = User::factory()->siswa()->create([
            'email' => 'siswa@sekolah.sch.id',
            'nis' => '12345678',
            'password' => 'SiswaPass123',
            'must_change_password' => false,
        ]);

        // Session 1: Admin logs in
        $adminRes = $this->post(route('login.store'), [
            'role' => 'admin',
            'email' => 'admin@sekolah.sch.id',
            'password' => 'AdminPass123',
        ]);
        $adminRes->assertRedirect(route('dashboard.admin.index'));
        $adminCookie = $adminRes->getCookie(config('session.cookie'))->getValue();
        $this->assertNotEmpty($adminCookie);

        // Reset for session 2 (different browser)
        $this->flushHeaders();
        $this->defaultCookies = [];
        $this->app['session']->flush();
        Auth::forgetGuards();

        // Session 2: Guru logs in
        $guruRes = $this->post(route('login.store'), [
            'role' => 'guru',
            'nip' => '198501012010011001',
            'password' => 'GuruPass123',
        ]);
        $guruRes->assertRedirect(route('dashboard.guru.index'));
        $guruCookie = $guruRes->getCookie(config('session.cookie'))->getValue();
        $this->assertNotEmpty($guruCookie);

        // Reset for session 3 (different browser)
        $this->flushHeaders();
        $this->defaultCookies = [];
        $this->app['session']->flush();
        Auth::forgetGuards();

        // Session 3: Siswa logs in
        $siswaRes = $this->post(route('login.store'), [
            'role' => 'siswa',
            'nis' => '12345678',
            'password' => 'SiswaPass123',
        ]);
        $siswaRes->assertRedirect(route('dashboard.siswa.index'));
        $siswaCookie = $siswaRes->getCookie(config('session.cookie'))->getValue();
        $this->assertNotEmpty($siswaCookie);

        $switchSession = function (string $cookie) {
            $this->app['session']->flush();
            Auth::forgetGuards();
            $this->defaultCookies = [config('session.cookie') => $cookie];
        };

        // 1. Admin accesses Admin dashboard
        $switchSession($adminCookie);
        $this->get(route('dashboard.admin.index'))->assertOk();
        $this->get(route('dashboard'))->assertOk()->assertViewIs('dashboard.admin.index');

        // 2. Guru accesses Guru dashboard
        $switchSession($guruCookie);
        $this->get(route('dashboard.guru.index'))->assertOk();
        $this->get(route('dashboard'))->assertOk()->assertViewIs('dashboard.guru.index');

        // 3. Siswa accesses Siswa dashboard
        $switchSession($siswaCookie);
        $this->get(route('dashboard.siswa.index'))->assertOk();
        $this->get(route('dashboard'))->assertOk()->assertViewIs('dashboard.siswa.index');

        // 4. Role restrictions check
        // Guru cannot access Admin dashboard
        $switchSession($guruCookie);
        $this->assertSame(403, $this->get(route('dashboard.admin.index'))->status(), 'Guru accessed Admin dashboard and got 200!');

        // Siswa cannot access Admin or Guru dashboard
        $switchSession($siswaCookie);
        $this->assertSame(403, $this->get(route('dashboard.admin.index'))->status(), 'Siswa accessed Admin dashboard and got 200!');
        $this->assertSame(403, $this->get(route('dashboard.guru.index'))->status(), 'Siswa accessed Guru dashboard and got 200!');

        // Admin cannot access Guru or Siswa dashboard
        $switchSession($adminCookie);
        $this->assertSame(403, $this->get(route('dashboard.guru.index'))->status(), 'Admin accessed Guru dashboard and got 200!');
        $this->assertSame(403, $this->get(route('dashboard.siswa.index'))->status(), 'Admin accessed Siswa dashboard and got 200!');

        // Check Siswa before Guru logout
        $switchSession($siswaCookie);
        $this->get(route('dashboard.siswa.index'))->assertOk();

        // 5. Guru logs out
        $switchSession($guruCookie);
        $logoutRes = $this->post(route('logout'));
        $logoutRes->assertRedirect(route('login'));

        // Guru is now logged out
        $switchSession($guruCookie);
        $this->get(route('dashboard.guru.index'))->assertRedirect(route('login'));

        // Admin and Siswa MUST STILL BE LOGGED IN!
        $switchSession($adminCookie);
        $this->get(route('dashboard.admin.index'))->assertOk();

        $switchSession($siswaCookie);
        $this->get(route('dashboard.siswa.index'))->assertOk();
    }

    public function test_logout_satu_role_pada_sesi_browser_yang_sama_tidak_mengeluarkan_role_lain(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@sekolah.sch.id',
            'password' => 'AdminPass123',
            'must_change_password' => false,
        ]);

        User::factory()->guru()->create([
            'email' => 'guru@sekolah.sch.id',
            'nip' => '198501012010011001',
            'password' => 'GuruPass123',
            'must_change_password' => false,
        ]);

        User::factory()->siswa()->create([
            'email' => 'siswa@sekolah.sch.id',
            'nis' => '12345678',
            'password' => 'SiswaPass123',
            'must_change_password' => false,
        ]);

        $cookie = null;

        $login = function (array $credentials, string $redirect) use (&$cookie): void {
            if ($cookie !== null) {
                $this->app['session']->flush();
                Auth::forgetGuards();
                $this->defaultCookies = [config('session.cookie') => $cookie];
            }

            $response = $this->post(route('login.store'), $credentials);
            $response->assertRedirect($redirect);

            $cookie = $response->getCookie(config('session.cookie'))->getValue();
        };

        $login([
            'role' => 'admin',
            'email' => 'admin@sekolah.sch.id',
            'password' => 'AdminPass123',
        ], route('dashboard.admin.index'));

        $login([
            'role' => 'guru',
            'nip' => '198501012010011001',
            'password' => 'GuruPass123',
        ], route('dashboard.guru.index'));

        $login([
            'role' => 'siswa',
            'nis' => '12345678',
            'password' => 'SiswaPass123',
        ], route('dashboard.siswa.index'));

        $useSession = function (string $cookie): void {
            $this->app['session']->flush();
            Auth::forgetGuards();
            $this->defaultCookies = [config('session.cookie') => $cookie];
        };

        // Logout Guru: hanya guard dan token Guru yang dihapus.
        $useSession($cookie);
        $logout = $this->post(route('logout'), ['role' => 'guru']);

        $logout->assertRedirect(route('login'))
            ->assertSessionHas('logged_out', true)
            ->assertSessionHas('logged_out_role', 'guru')
            ->assertSessionHas('jwt_token_admin')
            ->assertSessionHas('jwt_token_siswa')
            ->assertSessionMissing('jwt_token_guru');

        $cookie = $logout->getCookie(config('session.cookie'))?->getValue() ?? $cookie;

        $useSession($cookie);
        $this->get(route('dashboard.admin.index'))->assertOk();
        $this->get(route('dashboard.siswa.index'))->assertOk();
        $this->get(route('dashboard.guru.index'))->assertStatus(403);

        // Logout Admin: Guru sudah keluar, Siswa harus tetap bisa masuk.
        $useSession($cookie);
        $logout = $this->post(route('logout'), ['role' => 'admin']);

        $logout->assertRedirect(route('login'))
            ->assertSessionHas('logged_out_role', 'admin')
            ->assertSessionHas('jwt_token_siswa')
            ->assertSessionHas('jwt_token')
            ->assertSessionMissing('jwt_token_admin');

        $cookie = $logout->getCookie(config('session.cookie'))?->getValue() ?? $cookie;

        $useSession($cookie);
        $this->get(route('dashboard.siswa.index'))->assertOk();
        $this->get(route('dashboard.admin.index'))->assertStatus(403);

        // Logout Siswa (login terakhir): seluruh guard yang tersisa dibersihkan
        // dan semua dashboard kembali meminta login.
        $useSession($cookie);
        $logout = $this->post(route('logout'), ['role' => 'siswa']);

        $logout->assertRedirect(route('login'))
            ->assertSessionHas('logged_out_role', 'siswa')
            ->assertSessionMissing('jwt_token_siswa')
            ->assertSessionMissing('jwt_token');

        $cookie = $logout->getCookie(config('session.cookie'))?->getValue() ?? $cookie;

        $useSession($cookie);
        $this->get(route('dashboard.admin.index'))->assertRedirect(route('login'));
        $this->get(route('dashboard.guru.index'))->assertRedirect(route('login'));
        $this->get(route('dashboard.siswa.index'))->assertRedirect(route('login'));
    }

    public function test_roles_logged_in_on_same_browser_session_stay_isolated(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@sekolah.sch.id',
            'password' => 'AdminPass123',
            'must_change_password' => false,
        ]);

        User::factory()->guru()->create([
            'email' => 'guru@sekolah.sch.id',
            'nip' => '198501012010011001',
            'password' => 'GuruPass123',
            'must_change_password' => false,
        ]);

        // Tamu membuka /dashboard lalu diarahkan ke login. intended generik
        // tersimpan dan justru harus dibuang saat login agar tab admin mendarat
        // di URL khusus role, bukan /dashboard generik.
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', route('dashboard'));

        // Login sebagai admin pada sesi browser ini.
        $adminRes = $this->post(route('login.store'), [
            'role' => 'admin',
            'email' => 'admin@sekolah.sch.id',
            'password' => 'AdminPass123',
        ]);
        $adminRes->assertRedirect(route('dashboard.admin.index'));
        $cookie = $adminRes->getCookie(config('session.cookie'))->getValue();

        // Buka tab kedua pada browser yang sama (cookie sama), login sebagai guru.
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->defaultCookies = [config('session.cookie') => $cookie];

        $guruRes = $this->post(route('login.store'), [
            'role' => 'guru',
            'nip' => '198501012010011001',
            'password' => 'GuruPass123',
        ]);
        $guruRes->assertRedirect(route('dashboard.guru.index'));
        $cookie = $guruRes->getCookie(config('session.cookie'))->getValue();

        // Refresh tab admin: dashboard admin harus tetap menampilkan admin,
        // tidak berubah menjadi guru.
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->defaultCookies = [config('session.cookie') => $cookie];

        $this->get(route('dashboard.admin.index'))
            ->assertOk()
            ->assertViewIs('dashboard.admin.index');

        // Tab guru juga tetap valid dalam sesi yang sama.
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->defaultCookies = [config('session.cookie') => $cookie];

        $this->get(route('dashboard.guru.index'))
            ->assertOk()
            ->assertViewIs('dashboard.guru.index');

        // Role yang tidak dimiliki tetap ditolak (403), bukan dianggap tertukar.
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->defaultCookies = [config('session.cookie') => $cookie];

        $this->assertSame(403, $this->get(route('dashboard.siswa.index'))->status());
    }

    public function test_admin_guru_siswa_in_one_browser_session_each_stay_on_own_dashboard(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@sekolah.sch.id',
            'password' => 'AdminPass123',
            'must_change_password' => false,
        ]);

        User::factory()->guru()->create([
            'nip' => '198501012010011001',
            'password' => 'GuruPass123',
            'must_change_password' => false,
        ]);

        User::factory()->siswa()->create([
            'nis' => '12345678',
            'password' => 'SiswaPass123',
            'must_change_password' => false,
        ]);

        // Login admin, lalu guru, lalu siswa pada SATU sesi browser (cookie sama).
        $res = $this->post(route('login.store'), [
            'role' => 'admin', 'email' => 'admin@sekolah.sch.id', 'password' => 'AdminPass123',
        ]);
        $res->assertRedirect(route('dashboard.admin.index'));
        $cookie = $res->getCookie(config('session.cookie'))->getValue();

        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->defaultCookies = [config('session.cookie') => $cookie];

        $res = $this->post(route('login.store'), [
            'role' => 'guru', 'nip' => '198501012010011001', 'password' => 'GuruPass123',
        ]);
        $res->assertRedirect(route('dashboard.guru.index'));
        $cookie = $res->getCookie(config('session.cookie'))->getValue();

        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->defaultCookies = [config('session.cookie') => $cookie];

        $res = $this->post(route('login.store'), [
            'role' => 'siswa', 'nis' => '12345678', 'password' => 'SiswaPass123',
        ]);
        $res->assertRedirect(route('dashboard.siswa.index'));
        $cookie = $res->getCookie(config('session.cookie'))->getValue();

        // Setiap tab role (berbagi cookie terbaru) menampilkan dashboard role-nya sendiri.
        foreach ([
            'dashboard.admin.index' => 'dashboard.admin.index',
            'dashboard.guru.index' => 'dashboard.guru.index',
            'dashboard.siswa.index' => 'dashboard.siswa.index',
        ] as $route => $view) {
            $this->app['session']->flush();
            Auth::forgetGuards();
            $this->defaultCookies = [config('session.cookie') => $cookie];

            $this->get(route($route))->assertOk()->assertViewIs($view);
        }
    }
}
