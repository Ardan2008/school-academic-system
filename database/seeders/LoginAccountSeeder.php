<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Credentials;
use App\Support\LoginIdentifier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;

/**
 * Base seeder untuk akun yang harus bisa langsung dipakai di form login.
 *
 * Setiap role punya identifier sendiri (lihat App\Support\LoginIdentifier):
 * Admin = email, Guru = NIP, Siswa = NIS. Identifier dibuat tetap supaya bisa
 * diketik manual, sedangkan password untuk guru & siswa dibuat unik oleh
 * sistem (berbeda antarsatuan) supaya tidak ada dua akun dengan password
 * awal yang sama. Password admin tetap memakai nilai dari accounts().
 */
abstract class LoginAccountSeeder extends Seeder
{
    public function run(): void
    {
        $field = LoginIdentifier::field($this->role());
        $rows = [];

        foreach ($this->accounts() as $account) {
            $identifier = $account[$field] ?? null;

            if (! is_string($identifier) || $identifier === '') {
                continue;
            }

            $user = $this->upsertAccount($field, $identifier, $account);

            $rows[] = [
                $user->name,
                $identifier,
                $user->visiblePassword() ?: $account['password'] ?? '••••••••',
            ];
        }

        if ($rows === []) {
            return;
        }

        $this->command?->newLine();
        $this->command?->info('Akun '.$this->role().' siap login ('.LoginIdentifier::label($this->role()).' + Password):');
        $this->command?->table(['Nama', LoginIdentifier::label($this->role()), 'Password'], $rows);
    }

    abstract protected function role(): string;

    /**
     * Daftar akun yang akan dibuat/diperbarui.
     *
     * Wajib memuat kolom identifier sesuai role (email / nip / nis).
     * Untuk admin, key "password" berisi password tetap; untuk guru & siswa
     * password dibuatkan otomatis oleh sistem (isikan "password" kosong atau
     * hilangkan key-nya).
     *
     * @return array<int, array<string, mixed>>
     */
    abstract protected function accounts(): array;

    /**
     * Membuat akun bila belum ada, atau menyelaraskan kembali bila sudah ada
     * sehingga `db:seed` bisa dijalankan berulang tanpa menduplikasi data
     * dan tanpa memutar ulang password yang pernah diberikan.
     *
     * @param  array<string, mixed>  $account
     */
    protected function upsertAccount(string $field, string $identifier, array $account): User
    {
        $existing = User::where($field, $identifier)->first();

        // Akun sudah ada: pertahankan passwordnya, hanya selaraskan data lain.
        if ($existing instanceof User) {
            $existing->update(array_merge($account, [
                'role' => $this->role(),
                'status' => User::STATUS_AKTIF,
                'is_active' => true,
                $field => $identifier,
            ]));

            return $existing;
        }

        // Akun baru. Guru & siswa selalu dibuatkan password unik oleh sistem;
        // admin memakai password tetap yang ada di accounts().
        $password = (string) ($account['password'] ?? '');

        if ($this->role() !== 'admin') {
            $password = Credentials::generateUniquePassword();
        }

        return User::create(array_merge(
            [
                'role' => $this->role(),
                'status' => User::STATUS_AKTIF,
                'is_active' => true,
                'must_change_password' => $this->role() !== 'admin',
            ],
            $account,
            [
                $field => $identifier,
                'password' => $password,
                'initial_password' => Crypt::encryptString($password),
            ],
        ));
    }
}
