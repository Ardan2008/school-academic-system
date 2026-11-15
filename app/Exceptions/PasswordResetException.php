<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Kegagalan pada alur atur ulang password yang perlu diterjemah
 * menjadi pesan ramah pengguna (mis. token sudah dipakai / kedaluwarsa,
 * pengajuan sudah diproses admin, atau email gagal terkirim).
 */
class PasswordResetException extends RuntimeException
{
    public static function tokenInvalid(): self
    {
        return new self('Token tidak valid atau sudah kedaluwarsa. Silakan ajukan ulang permintaan.');
    }

    public static function alreadyProcessed(): self
    {
        return new self('Pengajuan ini sudah diproses oleh admin.');
    }

    public static function mailFailed(): self
    {
        return new self('Email pengingat gagal dikirim. Silakan coba lagi.');
    }

    public static function accountUnavailable(): self
    {
        return new self('Akun sedang tidak aktif. Hubungi admin sekolah.');
    }

    public static function emailMissing(): self
    {
        return new self('Akun ini belum memiliki email. Minta admin memperbarui data email terlebih dahulu.');
    }
}
