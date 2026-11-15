<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class Credentials
{
    public const EMAIL_DOMAIN = 'saskansa.sch.id';

    private const UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const LOWER = 'abcdefghijkmnpqrstuvwxyz';

    private const DIGITS = '23456789';

    /** Panjang password yang dibuat otomatis. */
    public const PASSWORD_LENGTH = 8;

    /**
     * Set karakter yang dipakai untuk membuat password, dibagi per jenis
     * supaya JavaScript di halaman bisa menyalin pola yang sama persis.
     *
     * @return array<int, string> huruf besar, huruf kecil, angka
     */
    public static function passwordCharsets(): array
    {
        return [self::UPPER, self::LOWER, self::DIGITS];
    }

    public static function passwordPool(): string
    {
        return implode('', self::passwordCharsets());
    }

    /**
     * Membuat password acak yang pasti memenuhi kebijakan sistem
     * (minimal 8 karakter, huruf besar, huruf kecil, dan angka).
     */
    public static function generatePassword(int $length = self::PASSWORD_LENGTH): string
    {
        $length = max(self::PASSWORD_LENGTH, $length);

        $pool = self::passwordPool();
        $required = self::passwordCharsets();
        $chars = [];

        foreach ($required as $set) {
            $chars[] = $set[random_int(0, strlen($set) - 1)];
        }

        while (count($chars) < $length) {
            $chars[] = $pool[random_int(0, strlen($pool) - 1)];
        }

        return str_shuffle(implode('', $chars));
    }

    /**
     * Kumpulan password awal yang sudah terpakai di semua akun. Dipakai untuk
     * memastikan guru dan siswa yang satu tidak menerima password awal yang
     * sama dengan yang lain, baik saat ditambahkan maupun saat data lama
     * dibereskan.
     *
     * @return array<string, true>
     */
    public static function usedPasswords(): array
    {
        $used = [];

        User::query()
            ->whereNotNull('initial_password')
            ->pluck('initial_password')
            ->each(function (string $cipher) use (&$used) {
                try {
                    $used[Crypt::decryptString($cipher)] = true;
                } catch (\Throwable $e) {
                    // Enkripsi lama yang tidak bisa dibuka lagi: lewati saja.
                }
            });

        return $used;
    }

    /**
     * Password acak yang pasti berbeda dari semua password awal yang sudah
     * dipakai akun lain.
     */
    public static function generateUniquePassword(int $length = self::PASSWORD_LENGTH, ?array $used = null): string
    {
        $used ??= self::usedPasswords();

        $attempts = 100;

        do {
            $password = self::generatePassword($length);
            $attempts--;
        } while ($attempts > 0 && isset($used[$password]));

        return $password;
    }

    /**
     * Password dari form bila belum dipakai akun lain; kalau kosong atau
     * kebetulan bentrok dengan password awal akun lain, diganti password
     * acak yang unik supaya tidak ada dua akun dengan password sama.
     */
    public static function ensureUniquePassword(?string $password, int $length = self::PASSWORD_LENGTH): string
    {
        $used = self::usedPasswords();

        if ($password !== null && $password !== '' && ! isset($used[$password])) {
            return $password;
        }

        return self::generateUniquePassword($length, $used);
    }

    /**
     * Membuat email otomatis dari nama (contoh: "Guru Pengajar" -> "guru-pengajar@saskansa.sch.id").
     * Ditambah suffix angka bila email sudah dipakai.
     */
    public static function generateEmail(string $name): string
    {
        $base = Str::lower(Str::slug(Str::ascii($name)));
        $base = $base !== '' ? $base : 'user';

        $email = $base.'@'.self::EMAIL_DOMAIN;
        $i = 1;

        while (User::where('email', $email)->exists()) {
            $i++;
            $email = $base.$i.'@'.self::EMAIL_DOMAIN;
        }

        return $email;
    }
}
