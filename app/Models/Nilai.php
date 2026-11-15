<?php

namespace App\Models;

use App\Support\Kelas;
use App\Support\Periode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu nilai siswa untuk satu mata pelajaran pada satu periode belajar.
 *
 * Kelas dan jurusan disimpan ulang di baris ini, bukan diambil dari users,
 * supaya laporan tahun lalu tidak ikut berubah ketika siswa naik kelas. Format
 * kelas selalu dinormalkan lewat App\Support\Kelas supaya "12 - 1" dan "12-1"
 * tidak menghasilkan dua baris untuk kelas yang sama.
 *
 * Nilai dan KKM sengaja ditempel di baris yang sama: KKM dihitung guru per
 * kelas per mata pelajaran, sehingga menyimpan ambang yang dipakai saat menilai
 * membuat ketuntasan historis tetap bisa dibaca tanpa menebak ambang mana yang
 * berlaku waktu itu.
 */
class Nilai extends Model
{
    use HasFactory;

    protected $table = 'nilai';

    protected $fillable = [
        'user_id',
        'mata_pelajaran_id',
        'kelas',
        'jurusan',
        'tahun_ajaran',
        'semester',
        'nilai',
        'kkm',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'mata_pelajaran_id' => 'integer',
            'nilai' => 'float',
            'kkm' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $nilai): void {
            $nilai->kelas = Kelas::normalize((string) $nilai->kelas);
            $nilai->jurusan = trim((string) $nilai->jurusan);
            $nilai->tahun_ajaran = trim((string) $nilai->tahun_ajaran);
            $nilai->semester = trim((string) $nilai->semester);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    /** Nilai ini sudah melewati KKM yang dipakai saat menilai. */
    public function tuntas(): bool
    {
        return (float) $this->nilai >= (float) $this->kkm;
    }

    public function scopeUntukPeriode(Builder $query, string $tahunAjaran, string $semester): Builder
    {
        return $query->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester);
    }

    public function scopeUntukJurusan(Builder $query, string $jurusan): Builder
    {
        return $query->where('jurusan', $jurusan);
    }

    /**
     * Kelas dipakai bersama lebih dari satu jurusan, jadi memotong kelas dan
     * jurusan berpasangan supaya kelas "12-1" RPL tidak ikut menyertai kelas
     * "12-1" TKJ.
     */
    public function scopeUntukKelas(Builder $query, string $kelas, ?string $jurusan = null): Builder
    {
        $query->where('kelas', $kelas);

        if ($jurusan !== null && $jurusan !== '') {
            $query->where('jurusan', $jurusan);
        }

        return $query;
    }

    /**
     * Disaring lewat nama, bukan id, supaya nama yang tidak dikenal tetap
     * menghasilkan laporan kosong yang jujur, bukan diam-diam berarti
     * "semua mata pelajaran".
     */
    public function scopeUntukMataPelajaran(Builder $query, string $nama): Builder
    {
        return $query->whereIn('mata_pelajaran_id', function ($sub) use ($nama) {
            $sub->select('id')->from('mata_pelajaran')->where('nama', $nama);
        });
    }

    /** Terapkan filter Laporan Akademik sekaligus, agar semua bagian memakai aturan yang sama. */
    public function scopeFilter(Builder $query, array $filter): Builder
    {
        $query->untukPeriode($filter['tahun_ajaran'], $filter['semester']);

        if (($filter['jurusan'] ?? '') !== '') {
            $query->untukJurusan($filter['jurusan']);
        }

        if (($filter['kelas'] ?? '') !== '') {
            $query->untukKelas($filter['kelas'], $filter['jurusan'] ?? '');
        }

        if (($filter['mata_pelajaran'] ?? '') !== '') {
            $query->untukMataPelajaran($filter['mata_pelajaran']);
        }

        return $query;
    }

    /** Periode berjalan, dipakai form dan laporan sebagai nilai bawaan. */
    public static function periodeSekarang(): array
    {
        return Periode::periodeSekarang();
    }
}
