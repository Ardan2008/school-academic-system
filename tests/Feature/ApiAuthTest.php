<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson(route('auth.login'), [])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_login_fails_with_wrong_credentials(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'Admin123',
            'role' => 'admin',
        ]);

        $this->postJson(route('auth.login'), [
            'email' => 'admin@example.com',
            'password' => 'Salah123',
        ])
            ->assertStatus(401)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Email atau password salah');
    }

    public function test_login_returns_jwt_token_and_user_data(): void
    {
        $user = User::factory()->create([
            'email' => 'guru@example.com',
            'password' => 'Guru123',
            'role' => 'guru',
            'must_change_password' => true,
        ]);

        $response = $this->postJson(route('auth.login'), [
            'email' => 'guru@example.com',
            'password' => 'Guru123',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $data = $response->json('data');

        $payload = JWTAuth::setToken($data['access_token'])->getPayload();
        $this->assertEquals($user->id, $payload->get('sub'));

        $this->assertSame('guru@example.com', $data['user']['email']);
        $this->assertSame('guru', $data['user']['role']);
        $this->assertTrue($data['user']['must_change_password']);
    }

    public function test_profile_requires_valid_token(): void
    {
        $this->getJson(route('auth.profile'))->assertStatus(401);

        $user = User::factory()->create(['email' => 'admin@example.com']);

        $token = JWTAuth::fromUser($user);

        $this->withToken($token)
            ->getJson(route('auth.profile'))
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@example.com');
    }

    public function test_change_password_requires_current_password_and_updates_user(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'Admin123',
        ]);

        $token = JWTAuth::fromUser($user);

        $this->withToken($token)
            ->postJson(route('auth.change-password'), [
                'current_password' => 'Salah123',
                'password' => 'Baru4567',
                'password_confirmation' => 'Baru4567',
            ])
            ->assertStatus(422);

        $this->withToken($token)
            ->postJson(route('auth.change-password'), [
                'current_password' => 'Admin123',
                'password' => 'Baru4567',
                'password_confirmation' => 'Baru4567',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertTrue(Hash::check('Baru4567', $user->fresh()->password));
    }

    public function test_logout_invalidates_token(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);

        $token = JWTAuth::fromUser($user);

        $this->withToken($token)
            ->postJson(route('auth.logout'))
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }
}
