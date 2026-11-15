<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Master Data Jurusan.
 *
 * Jurusan adalah paket keahlian yang tersedia di sekolah. Pemakaiannya dihitung
 * dari tabel yang mencatat jurusan pada kolom bebas (users.jurusan dan
 * kelas.jurusan), karena jurusan ini adalah sumber nilai untuk kolom tersebut.
 */
class Jurusan extends Model
{
    /** Status yang tersedia untuk sebuah jurusan. */
    public const STATUSES = ['aktif', 'nonaktif'];

    public const STATUS_LABELS = [
        'aktif' => 'Aktif',
        'nonaktif' => 'Nonaktif',
    ];

    protected $table = 'jurusan';

    protected $fillable = ['nama', 'status'];

    protected function casts(): array
    {
        return ['status' => 'string'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $jurusan): void {
            $jurusan->nama = trim((string) $jurusan->nama);
            $jurusan->status = in_array($jurusan->status, self::STATUSES, true)
                ? $jurusan->status
                : 'aktif';
        });
    }
}
