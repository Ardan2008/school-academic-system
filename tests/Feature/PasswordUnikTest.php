<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Password awal guru & siswa harus unik (tidak boleh ada dua akun dengan
 * password sama), baik dari seeder, dari tambah data, maupun saat data lama
 * dibereskan lewat perintah `akademik:password-unik`.
 */
class PasswordUnikTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_membuat_password_awal_unik_untuk_guru_dan_siswa(): void
    {
        $this->seed();

        $accounts = User::query()
            ->whereIn('role', ['guru', 'siswa'])
            ->get();

        // Jumlah akun sengaja tidak dipatok: menambah guru atau siswa di
        // seeder tidak boleh membuat test ini gagal. Yang dijaga adalah
        // sifatnya, yaitu kedua role terisi dan tidak ada password yang kembar.
        $this->assertSame(
            ['guru', 'siswa'],
            $accounts->pluck('role')->unique()->sort()->values()->all()
        );

        $passwords = $accounts->map(fn (User $user) => $user->visiblePassword())->all();

        $this->assertNotEmpty($passwords);
        // Semua akun dapat password awal yang berbeda satu sama lain.
        $this->assertSame($passwords, array_values(array_unique($passwords)));

        // Format password tetap mengikuti kebijakan sistem (contoh "4fvKdTuU").
        foreach ($passwords as $password) {
            $this->assertSame(8, strlen($password));
            $this->assertMatchesRegularExpression('/[A-Z]/', $password);
            $this->assertMatchesRegularExpression('/[a-z]/', $password);
            $this->assertMatchesRegularExpression('/[0-9]/', $password);
        }
    }

    public function test_perintah_membereskan_password_awal_duplikat(): void
    {
        User::factory()->guru()->withPassword('Guru1234')->create([
            'name' => 'Guru Satu',
            'nip' => '1001',
            'email' => 'guru1@example.com',
        ]);
        User::factory()->guru()->withPassword('Guru1234')->create([
            'name' => 'Guru Dua',
            'nip' => '1002',
            'email' => 'guru2@example.com',
        ]);
        User::factory()->siswa()->withPassword('Siswa1234')->create([
            'name' => 'Siswa Satu',
            'nis' => '2025001001',
            'email' => 'siswa1@example.com',
        ]);
        User::factory()->siswa()->withPassword('Siswa1234')->create([
            'name' => 'Siswa Dua',
            'nis' => '2025001002',
            'email' => 'siswa2@example.com',
        ]);

        $this->artisan('akademik:password-unik')->assertSuccessful();

        $accounts = User::query()->whereIn('role', ['guru', 'siswa'])->get();

        $passwords = $accounts->map(fn (User $user) => Crypt::decryptString($user->initial_password))->all();

        $this->assertCount(4, $passwords);
        $this->assertCount(4, array_unique($passwords));

        // Yang belum pernah mengganti password tetap bisa login dengan
        // password awal barunya.
        foreach ($accounts as $user) {
            $this->assertTrue(Hash::check(Crypt::decryptString($user->initial_password), $user->password));
        }
    }

    public function test_perintah_tidak_mengubah_password_yang_sudah_unik(): void
    {
        $guru = User::factory()->guru()->withPassword('Guru7891')->create([
            'name' => 'Guru Satu',
            'nip' => '1001',
            'email' => 'guru1@example.com',
        ]);
        $siswa = User::factory()->siswa()->withPassword('Siswa7891')->create([
            'name' => 'Siswa Satu',
            'nis' => '2025001001',
            'email' => 'siswa1@example.com',
        ]);

        $this->artisan('akademik:password-unik')->assertSuccessful();

        $this->assertSame('Guru7891', $guru->fresh()->visiblePassword());
        $this->assertSame('Siswa7891', $siswa->fresh()->visiblePassword());
    }

    public function test_seeder_menjaga_password_saat_dijalankan_ulang(): void
    {
        $this->seed();

        $pertama = User::query()
            ->whereIn('role', ['guru', 'siswa'])
            ->get()
            ->map(fn (User $user) => Crypt::decryptString($user->initial_password))
            ->all();

        // Jalankan lagi: password tidak boleh diputar ulang.
        $this->seed();

        $kedua = User::query()
            ->whereIn('role', ['guru', 'siswa'])
            ->get()
            ->map(fn (User $user) => Crypt::decryptString($user->initial_password))
            ->all();

        $this->assertSame($pertama, $kedua);
    }
}
