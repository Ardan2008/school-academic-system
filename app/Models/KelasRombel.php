<?php

namespace App\Models;

use App\Support\Kelas;
use App\Support\StrukturKelas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu baris di Data Kelas: satu rombel pada satu jurusan, misalnya
 * "RPL / 12-1". Kolom kelas memakai format yang sama dengan users.kelas
 * supaya siswa bisa langsung dicocokkan ke kelasnya.
 */
class KelasRombel extends Model
{
    protected $table = 'kelas';

    protected $fillable = [
        'jurusan',
        'kelas',
        'wali_kelas_id',
    ];

    protected function casts(): array
    {
        return [
            'wali_kelas_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Rapikan sebelum disimpan supaya "12 - 1" dan "12-1" tidak bisa
        // terdaftar dua kali sebagai kelas yang sama.
        static::saving(function (self $kelasRombel): void {
            if ($kelasRombel->jurusan !== null) {
                $kelasRombel->jurusan = trim((string) $kelasRombel->jurusan);
            }

            if ($kelasRombel->kelas !== null) {
                $kelasRombel->kelas = Kelas::normalize((string) $kelasRombel->kelas);
            }
        });
    }

    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wali_kelas_id');
    }

    /**
     * Siswa yang berada di kelas ini. Dicek pada jurusan dan kelas sekaligus
     * karena kelas "12-1" bisa dipakai di lebih dari satu jurusan.
     */
    public function siswa(): HasMany
    {
        return $this->hasMany(User::class, 'kelas', 'kelas')
            ->where('jurusan', $this->jurusan)
            ->where('role', 'siswa');
    }

    public function tingkat(): ?string
    {
        return Kelas::tingkat($this->kelas);
    }

    public function rombel(): ?string
    {
        return Kelas::rombel($this->kelas);
    }

    /** Kelas ini boleh dihapus kalau tidak ada siswa yang masih menunjuknya. */
    public function bolehDihapus(): bool
    {
        return $this->siswa()->doesntExist();
    }

    public function jumlahSiswa(): int
    {
        return $this->siswa()->count();
    }

    /**
     * Urutan baca kelas, dipakai Data Kelas dan filter Data Siswa.
     * Dilakukan di PHP karena kolom kelas berupa teks: kelas "12-2" harus
     * urut sebelum "12-10", sedangkan diurutkan sebagai teks urut sebaliknya.
     *
     * @return array<int, static>
     */
    public static function urutkan(array $kelasRombel): array
    {
        usort($kelasRombel, function (self $a, self $b): int {
            $jurusan = strcasecmp((string) $a->jurusan, (string) $b->jurusan);

            if ($jurusan !== 0) {
                return $jurusan;
            }

            return StrukturKelas::bandingkanKelas($a->kelas, $b->kelas);
        });

        return array_values($kelasRombel);
    }
}
