<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_authentication(): void
    {
        $this->get(route('profile'))->assertRedirect(route('login'));
    }

    public function test_profile_page_is_rendered(): void
    {
        $user = User::factory()->create([
            'email' => 'guru@example.com',
            'name' => 'Guru Pengajar',
            'role' => 'guru',
        ]);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Profil Saya')
            ->assertSee('Guru Pengajar')
            ->assertSee('Ubah Password');
    }

    public function test_profil_siswa_read_only_dan_menampilkan_kelas(): void
    {
        $siswa = User::factory()->siswa('12-1')->create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'must_change_password' => false,
        ]);

        $this->actingAs($siswa)
            ->get(route('dashboard.siswa.profile.index'))
            ->assertOk()
            ->assertSee('Kelas')
            ->assertSee('12-1')
            ->assertSee('Rekayasa Perangkat Lunak')
            ->assertDontSee('id="profile-edit-btn"', false)
            ->assertSee('Perubahan data profil dilakukan oleh administrator sekolah.')
            ->assertSee('Ubah Password');
    }

    public function test_profile_photo_upload_replace_and_delete_for_each_role(): void
    {
        Storage::fake('public');

        foreach (['admin' => 'admin', 'guru' => 'guru', 'siswa' => 'siswa'] as $role => $factory) {
            $user = User::factory()->{$factory}()->create([
                'password' => 'Lama123',
                'must_change_password' => false,
            ]);

            $first = UploadedFile::fake()->image('foto.jpg', 300, 300)->size(500);
            $this->actingAs($user)
                ->withHeader('Accept', 'application/json')
                ->post(route('profile.photo.update', ['role' => $role]), ['photo' => $first])
                ->assertOk()
                ->assertJsonPath('status', 'success')
                ->assertJsonStructure(['data' => ['photo_url']]);

            $firstPath = $user->fresh()->profile_photo_path;
            $this->assertNotNull($firstPath);
            $this->assertStringStartsWith("profile-photos/{$role}/", $firstPath);
            Storage::disk('public')->assertExists($firstPath);

            $second = UploadedFile::fake()->image('baru.png', 300, 300)->size(500);
            $this->actingAs($user)
                ->withHeader('Accept', 'application/json')
                ->post(route('profile.photo.update', ['role' => $role]), ['photo' => $second])
                ->assertOk()
                ->assertJsonPath('status', 'success');

            $secondPath = $user->fresh()->profile_photo_path;
            $this->assertNotNull($secondPath);
            $this->assertNotSame($firstPath, $secondPath);
            Storage::disk('public')->assertMissing($firstPath);
            Storage::disk('public')->assertExists($secondPath);

            $this->actingAs($user)
                ->get(route('dashboard.'.$role.'.profile.index'))
                ->assertOk()
                ->assertSee('data-profile-avatar-img', false)
                ->assertSee('profile-photos/'.$role, false)
                ->assertSee('onerror="window.saskansaAvatarFallback?.(this)"', false)
                ->assertDontSee('alt="Foto profil', false);

            $this->actingAs($user)
                ->withHeader('Accept', 'application/json')
                ->delete(route('profile.photo.delete', ['role' => $role]))
                ->assertOk()
                ->assertJsonPath('status', 'success')
                ->assertJsonPath('data.photo_url', null);

            $this->assertNull($user->fresh()->profile_photo_path);
            Storage::disk('public')->assertMissing($secondPath);

            $this->actingAs($user)
                ->get(route('dashboard.'.$role.'.profile.index'))
                ->assertOk()
                ->assertSee('data-profile-avatar-initial', false);
        }
    }

    public function test_profile_photo_does_not_affect_other_users(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create(['must_change_password' => false]);
        $guru = User::factory()->guru()->create(['must_change_password' => false]);
        $siswa = User::factory()->siswa()->create(['must_change_password' => false]);

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('profile.photo.update', ['role' => 'admin']), [
                'photo' => UploadedFile::fake()->image('admin.jpg', 200, 200),
            ])
            ->assertOk();

        $adminPath = $admin->fresh()->profile_photo_path;
        $this->assertNotNull($adminPath);
        Storage::disk('public')->assertExists($adminPath);
        $this->assertNull($guru->fresh()->profile_photo_path);
        $this->assertNull($siswa->fresh()->profile_photo_path);
    }

    public function test_profile_photo_validation_rejects_invalid_format_and_size(): void
    {
        Storage::fake('public');
        $user = User::factory()->guru()->create(['must_change_password' => false]);

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('profile.photo.update', ['role' => 'guru']), [
                'photo' => UploadedFile::fake()->create('foto.gif', 100, 'image/gif'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('profile.photo.update', ['role' => 'guru']), [
                'photo' => UploadedFile::fake()->image('besar.jpg', 100, 100)->size(2049),
            ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertNull($user->fresh()->profile_photo_path);
    }

    public function test_profile_photo_rejects_url_role_mismatch(): void
    {
        Storage::fake('public');
        $siswa = User::factory()->siswa()->create(['must_change_password' => false]);

        $this->actingAs($siswa)
            ->withHeader('Accept', 'application/json')
            ->post(route('profile.photo.update', ['role' => 'admin']), [
                'photo' => UploadedFile::fake()->image('foto.jpg', 100, 100),
            ])
            ->assertForbidden();

        $this->actingAs($siswa)
            ->withHeader('Accept', 'application/json')
            ->delete(route('profile.photo.delete', ['role' => 'admin']))
            ->assertForbidden();
    }

    public function test_change_password_requires_valid_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'Lama123',
        ]);

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('profile.password', ['role' => $user->role]), [
                'current_password' => 'Salah123',
                'password' => 'Baru4567',
                'password_confirmation' => 'Baru4567',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Password saat ini tidak sesuai.');
    }

    public function test_change_password_success(): void
    {
        $user = User::factory()->create([
            'email' => 'guru@example.com',
            'password' => 'Lama123',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('profile.password', ['role' => $user->role]), [
                'current_password' => 'Lama123',
                'password' => 'Baru4567',
                'password_confirmation' => 'Baru4567',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertTrue(Hash::check('Baru4567', $user->fresh()->password));
        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_change_password_targets_correct_role_in_shared_session(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'AdminLama123',
            'must_change_password' => false,
        ]);

        $siswa = User::factory()->siswa()->create([
            'nis' => '2400112233',
            'password' => 'SiswaLama123',
            'must_change_password' => false,
        ]);

        // Admin login pada sesi browser ini.
        $adminRes = $this->post(route('login.store'), [
            'role' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'AdminLama123',
        ]);
        $cookie = $adminRes->getCookie(config('session.cookie'))->getValue();

        // Sesi yang sama dipakai login siswa (login terakhir = siswa).
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->defaultCookies = [config('session.cookie') => $cookie];

        $siswaRes = $this->post(route('login.store'), [
            'role' => 'siswa',
            'nis' => '2400112233',
            'password' => 'SiswaLama123',
        ]);
        $cookie = $siswaRes->getCookie(config('session.cookie'))->getValue();

        // Tab admin mengganti password lewat URL khusus role admin.
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->defaultCookies = [config('session.cookie') => $cookie];

        $this->post(route('profile.password', ['role' => 'admin']), [
            'current_password' => 'AdminLama123',
            'password' => 'AdminBaru123',
            'password_confirmation' => 'AdminBaru123',
        ])->assertRedirect(route('dashboard.admin.profile.index'));

        // Hanya akun admin yang berubah; akun siswa tidak tersentuh.
        $this->assertTrue(Hash::check('AdminBaru123', $admin->fresh()->password));
        $this->assertTrue(Hash::check('SiswaLama123', $siswa->fresh()->password));
    }

    public function test_change_password_rejects_url_role_mismatch(): void
    {
        $siswa = User::factory()->siswa()->create([
            'nis' => '2400112233',
            'password' => 'SiswaLama123',
            'must_change_password' => false,
        ]);

        // Hanya siswa yang login, tapi mencoba menyasar endpoint role admin.
        $this->actingAs($siswa)
            ->post(route('profile.password', ['role' => 'admin']), [
                'current_password' => 'SiswaLama123',
                'password' => 'SiswaBaru123',
                'password_confirmation' => 'SiswaBaru123',
            ])
            ->assertForbidden();

        $this->assertTrue(Hash::check('SiswaLama123', $siswa->fresh()->password));
    }
}
