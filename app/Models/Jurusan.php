<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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

    /**
     * Pilihan jurusan untuk dropdown form siswa dan kelas.
     *
     * @return Collection<int, array{id: int, nama: string}>
     */
    public static function opsiForm(): Collection
    {
        return static::query()
            ->orderBy('nama')
            ->get(['id', 'nama'])
            ->map(fn (self $jurusan): array => [
                'id' => (int) $jurusan->id,
                'nama' => (string) $jurusan->nama,
            ])
            ->values();
    }

    public static function namaDariId(int $id): ?string
    {
        $nama = static::query()->whereKey($id)->value('nama');

        return $nama === null ? null : (string) $nama;
    }
}
