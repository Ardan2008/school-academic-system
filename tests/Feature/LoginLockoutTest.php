<?php

namespace Tests\Feature;

use App\Models\LoginAttempt;
use App\Models\User;
use App\Support\LoginLockout;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Route login memakai throttle:10,1 dan rate limiter memakai cache, jadi
     * cache harus dibersihkan tiap test agar hitungan tidak bocor antar test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        cache()->clear();
        app(RateLimiter::class)->clear('guru:gp-1000');
    }

    /**
     * Guru dengan NIP tetap, dipakai hampir semua test di bawah.
     */
    protected function guru(): User
    {
        return User::factory()->guru()->create([
            'nip' => 'GP-1000',
            'password' => 'benar123',
            'must_change_password' => false,
        ]);
    }

    /**
     * Lockout kini memakai key "role:identifier" supaya NIP dan NIS yang
     * kebetulan sama tidak saling mengunci. Key dinormalisasi ke huruf kecil,
     * sama seperti LoginLockout::normalizeEmail().
     */
    protected function key(string $role, string $identifier): string
    {
        return LoginLockout::normalizeEmail($role.':'.$identifier);
    }

    public function test_failed_login_without_threshold_is_recorded_without_lock(): void
    {
        $this->guru();

        $this->post(route('login.store'), [
            'role' => 'guru',
            'nip' => 'GP-1000',
            'password' => 'salah',
        ])->assertSessionHasErrors('nip');

        $this->assertDatabaseHas('login_attempts', [
            'email' => $this->key('guru', 'GP-1000'),
            'attempts' => 1,
        ]);

        $record = LoginAttempt::where('email', $this->key('guru', 'GP-1000'))->first();

        $this->assertNull(LoginLockout::remaining($this->key('guru', 'GP-1000')));
        $this->assertNull($record->locked_until);
    }

    public function test_lockout_starts_on_fifth_attempt_with_progressive_durations(): void
    {
        $this->guru();

        Carbon::setTestNow(Carbon::create(2026, 1, 1, 8, 0, 0));

        $key = $this->key('guru', 'GP-1000');

        // Kegagalan ke-1 sampai ke-4: hanya dicatat, tanpa penguncian.
        for ($i = 1; $i <= 4; $i++) {
            $this->post(route('login.store'), [
                'role' => 'guru',
                'nip' => 'GP-1000',
                'password' => 'salah',
            ])->assertSessionHasErrors('nip');

            $record = LoginAttempt::where('email', $key)->first();

            $this->assertSame($i, $record->attempts);
            $this->assertNull($record->locked_until);
            $this->assertNull(LoginLockout::remaining($key));
        }

        // Kegagalan ke-5 ke atas: terkunci dengan durasi bertahap.
        $durations = [30, 60, 120, 300, 600];

        foreach ($durations as $i => $duration) {
            $attempts = $i + 5;
            $now = now();

            $this->post(route('login.store'), [
                'role' => 'guru',
                'nip' => 'GP-1000',
                'password' => 'salah',
            ])->assertSessionHasErrors('nip');

            $record = LoginAttempt::where('email', $key)->first();

            $this->assertSame($attempts, $record->attempts);
            $this->assertSame(
                $now->copy()->addSeconds($duration)->timestamp,
                $record->locked_until->timestamp
            );

            // lewati masa lockout sebelum percobaan berikutnya
            Carbon::setTestNow($now->addSeconds($duration + 5));
        }
    }

    public function test_lockout_duration_keeps_doubling_after_ninth_attempt(): void
    {
        $this->guru();

        Carbon::setTestNow(Carbon::create(2026, 1, 1, 8, 0, 0));

        $key = $this->key('guru', 'GP-1000');

        // sembilan kegagalan sebelumnya, dan lockout sudah berakhir
        LoginAttempt::create([
            'email' => $key,
            'attempts' => 9,
            'locked_until' => now()->subSecond(1),
        ]);

        $this->post(route('login.store'), [
            'role' => 'guru',
            'nip' => 'GP-1000',
            'password' => 'salah',
        ])->assertSessionHasErrors('nip');

        $record = LoginAttempt::where('email', $key)->first();

        // kegagalan ke-10 => 600 * 2^(10-9) = 1200 detik (20 menit)
        $this->assertSame(10, $record->attempts);
        $this->assertEquals(1200, $record->locked_until->diffInSeconds(now(), true));
    }

    public function test_locked_account_is_rejected_even_with_correct_password_web(): void
    {
        $this->guru();

        Carbon::setTestNow(Carbon::create(2026, 1, 1, 8, 0, 0));

        LoginAttempt::create([
            'email' => $this->key('guru', 'GP-1000'),
            'attempts' => 5,
            'locked_until' => now()->addSeconds(60),
        ]);

        $this->withHeader('Accept', 'application/json')
            ->post(route('login.store'), [
                'role' => 'guru',
                'nip' => 'GP-1000',
                'password' => 'benar123',
            ])
            ->assertStatus(429)
            ->assertJsonPath('data.retry_after', 60)
            ->assertJsonPath('data.attempts', 5)
            ->assertJsonPath('data.field', 'nip')
            ->assertJsonStructure(['status', 'message', 'data' => ['field', 'lock_key', 'attempts', 'retry_after']]);

        $this->assertGuest();

        // attempts tidak bertambah saat masih terkunci
        $this->assertSame(5, LoginAttempt::where('email', $this->key('guru', 'GP-1000'))->first()->attempts);
    }

    public function test_successful_login_resets_all_attempts(): void
    {
        $this->guru();

        $key = $this->key('guru', 'GP-1000');

        LoginAttempt::create([
            'email' => $key,
            'attempts' => 4,
            'locked_until' => null,
        ]);

        $this->post(route('login.store'), [
            'role' => 'guru',
            'nip' => 'GP-1000',
            'password' => 'benar123',
        ])->assertRedirect(route('dashboard.guru.index'));

        $this->assertDatabaseMissing('login_attempts', ['email' => $key]);
    }

    public function test_login_page_starts_countdown_when_session_still_locked(): void
    {
        $this->withSession(['login.lock_key' => $this->key('guru', 'GP-1000')]);

        Carbon::setTestNow(Carbon::create(2026, 1, 1, 8, 0, 0));

        LoginAttempt::create([
            'email' => $this->key('guru', 'GP-1000'),
            'attempts' => 5,
            'locked_until' => now()->addSeconds(30),
        ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Terlalu banyak percobaan login')
            ->assertSee('&quot;retry_after&quot;:30', false);
    }

    public function test_login_page_shows_no_countdown_when_not_locked(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('&quot;retry_after&quot;', false);
    }

    public function test_api_login_before_threshold_is_plain_401(): void
    {
        User::factory()->admin()->create([
            'email' => 'target@example.com',
            'password' => 'benar123',
        ]);

        for ($i = 1; $i <= 4; $i++) {
            $this->postJson(route('auth.login'), [
                'email' => 'target@example.com',
                'password' => 'salah',
            ])
                ->assertStatus(401)
                ->assertJsonPath('message', 'Email atau password salah')
                ->assertJsonMissingPath('data');
        }

        $this->assertSame(4, LoginLockout::attempts('target@example.com'));
        $this->assertNull(LoginLockout::remaining('target@example.com'));
    }

    public function test_api_login_returns_429_with_lockout_info_on_fifth_attempt(): void
    {
        User::factory()->admin()->create([
            'email' => 'target@example.com',
            'password' => 'benar123',
        ]);

        for ($i = 1; $i <= 4; $i++) {
            $this->postJson(route('auth.login'), ['email' => 'target@example.com', 'password' => 'salah']);
        }

        $this->postJson(route('auth.login'), [
            'email' => 'target@example.com',
            'password' => 'salah',
        ])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Email atau password salah')
            ->assertJsonPath('data.attempts', 5)
            ->assertJsonPath('data.retry_after', 30);

        $this->assertDatabaseHas('login_attempts', [
            'email' => 'target@example.com',
            'attempts' => 5,
        ]);
    }

    public function test_api_login_returns_429_when_account_is_locked(): void
    {
        User::factory()->admin()->create([
            'email' => 'target@example.com',
            'password' => 'benar123',
        ]);

        Carbon::setTestNow(Carbon::create(2026, 1, 1, 8, 0, 0));

        LoginAttempt::create([
            'email' => 'target@example.com',
            'attempts' => 5,
            'locked_until' => now()->addSeconds(120),
        ]);

        $this->postJson(route('auth.login'), [
            'email' => 'target@example.com',
            'password' => 'benar123',
        ])
            ->assertStatus(429)
            ->assertJsonPath('data.retry_after', 120);
    }
}
