<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Credentials;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

/**
 * Membereskan password awal guru & siswa yang terlanjur sama (mis. semua akun
 * memakai password yang sama) dengan memberi tiap akun password acak yang unik.
 *
 * Akun yang sudah pernah mengganti password tidak diubah hash login-nya, hanya
 * "Password Awal" cadangannya disesuaikan agar tetap unik.
 */
class KemasPasswordAwalCommand extends Command
{
    protected $signature = 'akademik:password-unik';

    protected $description = 'Buat password awal unik untuk setiap guru & siswa (yang sama dibereskan)';

    public function handle(): int
    {
        $accounts = User::query()
            ->whereIn('role', ['guru', 'siswa'])
            ->whereNotNull('initial_password')
            ->orderBy('role')
            ->orderBy('nip')
            ->orderBy('nis')
            ->get();

        if ($accounts->isEmpty()) {
            $this->info('Tidak ada akun guru/siswa yang perlu dibereskan.');

            return self::SUCCESS;
        }

        // Hitung berapa akun memakai tiap password awal; password yang muncul
        // lebih dari sekali itulah yang perlu diganti.
        $counts = [];
        foreach ($accounts as $user) {
            $password = $user->visiblePassword();

            if ($password !== '') {
                $counts[$password] = ($counts[$password] ?? 0) + 1;
            }
        }

        // Semua password awal yang sudah terpakai tidak boleh dipakai lagi.
        $taken = $counts;

        $fixed = [];
        $kept = 0;

        foreach ($accounts as $user) {
            $current = $user->visiblePassword();

            // Password unik (dan terbaca) tidak perlu diubah.
            if ($current !== '' && ($counts[$current] ?? 0) <= 1) {
                $kept++;

                continue;
            }

            $password = Credentials::generateUniquePassword(8, $taken);
            $taken[$password] = true;

            $values = [
                'initial_password' => Crypt::encryptString($password),
            ];

            // Akun yang belum pernah mengganti password ikut disesuaikan hash
            // login-nya supaya password awal baru langsung bisa dipakai masuk.
            if ($user->must_change_password) {
                $values['password'] = Hash::make($password);
            }

            $user->update($values);

            $fixed[] = [
                $user->name,
                $user->nip ?? $user->nis,
                $password,
                $user->must_change_password ? 'Belum ubah password' : 'Sudah ubah password',
            ];
        }

        if ($fixed === []) {
            $this->info('Semua password awal guru & siswa sudah unik ('.$kept.' akun diperiksa).');

            return self::SUCCESS;
        }

        $this->info(
            count($fixed).' akun dibereskan, '.$kept.' akun sudah unik sejak awal.'
        );
        $this->table(['Nama', 'NIP/NIS', 'Password Awal Baru', 'Status Password'], $fixed);

        return self::SUCCESS;
    }
}
