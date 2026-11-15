<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Kegagalan backup yang pesannya aman ditampilkan ke admin.
 *
 * Kegagalan lain (misalnya kolom tidak dikenal atau koneksi terputus) tetap
 * dilempar sebagai exception biasa. Pesan exception itu bisa membocorkan
 * struktur database, jadi untuk kasus tersebut pesan aslinya hanya ditulis ke
 * log, sementara admin diberi tahu dengan kalimat umum.
 */
class BackupGagalException extends RuntimeException
{
    public function __construct(
        string $message,
        protected int $status = 500,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** Ada proses backup lain yang belum selesai. */
    public static function sedangBerjalan(?Throwable $previous = null): self
    {
        return new self(
            'Backup lain sedang berjalan. Tunggu sampai selesai, lalu coba lagi.',
            409,
            $previous
        );
    }

    /** Kegagalan yang pesannya memang boleh dilihat admin. */
    public static function gagal(string $message, int $status = 500, ?Throwable $previous = null): self
    {
        return new self($message, $status, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }
}
