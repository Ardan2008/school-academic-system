<?php

namespace App\Support;

use App\Models\LoginAttempt;

/**
 * Penanganan percobaan login dan lockout bertahap.
 *
 * Kegagalan ke-1 sampai dengan ke-4 hanya dicatat jumlahnya (tanpa penguncian).
 * Mulai kegagalan ke-5, akun akan dikunci dengan durasi yang naik bertahap.
 * Seluruh validasi dan perhitungan lockout dilakukan di server sehingga tidak
 * bisa dilewati hanya dengan refresh halaman atau manipulasi frontend.
 */
class LoginLockout
{
    /**
     * Jumlah kegagalan sebelum lockout aktif.
     */
    public const THRESHOLD = 5;

    /**
     * Durasi lockout (detik) untuk kegagalan ke-5 sampai ke-9.
     * Urut: 30s, 60s, 2 menit, 5 menit, 10 menit.
     */
    public const DURATIONS = [30, 60, 120, 300, 600];

    /**
     * Durasi dasar untuk kegagalan ke-10 ke atas (terus berlipat).
     */
    public const BASE_DURATION = 600;

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Jumlah percobaan gagal yang tercatat.
     */
    public static function attempts(string $email): int
    {
        $record = LoginAttempt::where('email', static::normalizeEmail($email))->first();

        return $record ? (int) $record->attempts : 0;
    }

    /**
     * Sisa waktu lockout dalam detik, atau null bila tidak sedang terkunci.
     */
    public static function remaining(string $email): ?int
    {
        $record = LoginAttempt::where('email', static::normalizeEmail($email))->first();

        if (! $record || ! $record->locked_until || $record->locked_until <= now()) {
            return null;
        }

        return (int) $record->locked_until->diffInSeconds(now(), true);
    }

    public static function isLocked(string $email): bool
    {
        return static::remaining($email) !== null;
    }

    /**
     * Catat satu percobaan login yang gagal.
     *
     * Kegagalan ke-5 ke atas memicu penguncian dengan durasi bertahap;
     * kegagalan ke-1 sampai ke-4 hanya menaikkan hitungan (retry_after null).
     * Saat masih dalam masa lockout, hitungan tidak dinaikkan.
     *
     * @return array{attempts: int, retry_after: int|null}
     */
    public static function recordFailure(string $email): array
    {
        $email = static::normalizeEmail($email);

        $record = LoginAttempt::firstOrNew(['email' => $email], ['attempts' => 0]);

        // Masih dalam masa lockout -> tidak menambah hitungan.
        if ($record->locked_until && $record->locked_until > now()) {
            return [
                'attempts' => (int) $record->attempts,
                'retry_after' => (int) $record->locked_until->diffInSeconds(now(), true),
            ];
        }

        $record->attempts = (int) $record->attempts + 1;
        $duration = $record->attempts >= static::THRESHOLD
            ? static::durationFor($record->attempts)
            : null;
        $record->locked_until = $duration ? now()->addSeconds($duration) : null;
        $record->save();

        return [
            'attempts' => (int) $record->attempts,
            'retry_after' => $duration,
        ];
    }

    /**
     * Durasi lockout (detik) untuk kegagalan ke-5 ke atas.
     * Ke-5=30s, ke-6=60s, ke-7=2m, ke-8=5m, ke-9=10m, ke-10=20m, lalu berlipat dua.
     */
    public static function durationFor(int $attempts): int
    {
        $index = $attempts - static::THRESHOLD;
        $baseCount = count(static::DURATIONS);

        if ($index < $baseCount) {
            return static::DURATIONS[$index];
        }

        return static::BASE_DURATION * (2 ** ($index - $baseCount + 1));
    }

    /**
     * Hapus seluruh catatan percobaan saat login berhasil.
     */
    public static function reset(string $email): void
    {
        LoginAttempt::where('email', static::normalizeEmail($email))->delete();
    }
}
