<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $password = static::$password ??= Hash::make('password');

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => $password,
            'remember_token' => Str::random(10),
            'role' => 'siswa',
            'kelas' => fake()->randomElement(['10', '10-1', '11-1', '11-2', '12', '12-1', '12-2']),
            'jurusan' => fake()->randomElement(['Rekayasa Perangkat Lunak', 'Teknik Komputer Jaringan', 'Multimedia']),
            'nis' => strtoupper(Str::random(8)),
            'nip' => null,
            'mata_pelajaran' => null,
            'initial_password' => Crypt::encryptString('password'),
            'must_change_password' => true,
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => 'admin',
            'name' => 'Administrator',
            'kelas' => null,
            'jurusan' => null,
            'nis' => null,
            'nip' => null,
            'mata_pelajaran' => null,
            'initial_password' => null,
            'must_change_password' => false,
        ]);
    }

    public function guru(): static
    {
        return $this->state(fn () => [
            'role' => 'guru',
            'kelas' => null,
            'jurusan' => null,
            'nis' => null,
            'nip' => 'GP-'.fake()->unique()->numberBetween(1000, 9999),
            'mata_pelajaran' => fake()->randomElement(['Matematika', 'Fisika', 'Bahasa Inggris']),
        ]);
    }

    public function siswa(?string $kelas = null): static
    {
        return $this->state(fn () => [
            'role' => 'siswa',
            'nip' => null,
            'mata_pelajaran' => null,
            'kelas' => $kelas ?? fake()->randomElement(['10', '10-1', '11-1', '11-2', '12', '12-1', '12-2']),
            'jurusan' => fake()->randomElement(['Rekayasa Perangkat Lunak', 'Teknik Komputer Jaringan', 'Multimedia']),
            'nis' => fake()->unique()->numerify('##########'),
        ]);
    }

    /**
     * Akun demo yang segera dipakai (password awal = kredensial yang
     * diberikan sistem). Password tetap di-hash untuk login,
     * dan disimpan ter-encrypt agar bisa ditampilkan di profil.
     */
    public function withPassword(string $plain): static
    {
        return $this->state(fn () => [
            'password' => Hash::make($plain),
            'initial_password' => Crypt::encryptString($plain),
            'must_change_password' => true,
        ]);
    }
}
