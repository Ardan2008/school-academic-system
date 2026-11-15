<?php

namespace App\Models\Concerns;

use App\Support\Kelas;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dipakai model yang menempel ke satu rombel (kelas + jurusan opsional):
 * jadwal, tugas, dan materi.
 *
 * Kelas selalu dinormalkan lewat App\Support\Kelas supaya "12 - 1" dan "12-1"
 * tidak menjadi dua rombel berbeda. Jurusan boleh kosong; baris tanpa jurusan
 * berarti berlaku untuk semua jurusan di kelas tersebut.
 */
trait MilikRombel
{
    protected static function bootMilikRombel(): void
    {
        static::saving(function (self $model): void {
            $model->kelas = Kelas::normalize((string) $model->kelas);
            $model->jurusan = trim((string) $model->jurusan) ?: null;
        });
    }

    /** Rombel persis: kelas dan jurusan harus sama. */
    public function scopeUntukKelas(Builder $query, string $kelas, ?string $jurusan = null): Builder
    {
        $query->where('kelas', Kelas::normalize($kelas));

        if ($jurusan !== null && $jurusan !== '') {
            $query->where('jurusan', $jurusan);
        }

        return $query;
    }

    /**
     * Rombel yang berhak melihat: kelas sama, lalu jurusan sama atau baris
     * tanpa jurusan (berlaku untuk semua jurusan di kelas itu).
     *
     * Siswa tanpa kelas tidak berhak melihat baris apa pun; dikembalikan
     * kueri kosong supaya halaman tetap tampil, bukan error.
     */
    public function scopeUntukRombel(Builder $query, ?string $kelas, ?string $jurusan = null): Builder
    {
        $kelas = Kelas::normalize((string) $kelas);

        if ($kelas === '') {
            return $query->whereRaw('1 = 0');
        }

        $query->where('kelas', $kelas);

        $jurusan = trim((string) $jurusan);

        if ($jurusan !== '') {
            $query->where(function (Builder $sub) use ($jurusan): void {
                $sub->whereNull('jurusan')
                    ->orWhere('jurusan', '')
                    ->orWhere('jurusan', $jurusan);
            });
        }

        return $query;
    }
}
