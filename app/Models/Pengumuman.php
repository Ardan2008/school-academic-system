<?php

namespace App\Models;

use App\Services\NotifikasiAkademikService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengumuman sekolah. Kelas / jurusan kosong berarti berlaku untuk semua.
 *
 * Sasaran role juga bisa diatur: null atau "semua" berlaku untuk semua role,
 * "guru" hanya guru, dan "siswa" hanya siswa. Penyaringan kelas / jurusan
 * hanya berlaku untuk siswa karena guru tidak terikat rombel.
 */
class Pengumuman extends Model
{
    use HasFactory;

    public const TARGET_SEMUA = 'semua';

    public const TARGET_GURU = 'guru';

    public const TARGET_SISWA = 'siswa';

    public const TARGETS = [self::TARGET_SEMUA, self::TARGET_GURU, self::TARGET_SISWA];

    protected $table = 'pengumuman';

    protected $fillable = [
        'judul',
        'isi',
        'kelas',
        'jurusan',
        'target_role',
        'publish_at',
    ];

    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Pengumuman $pengumuman) {
            app(NotifikasiAkademikService::class)->kirimPengumuman($pengumuman);
        });

        static::updated(function (Pengumuman $pengumuman) {
            app(NotifikasiAkademikService::class)->kirimPengumuman($pengumuman);
        });
    }

    /** Sasaran yang sudah dinormalkan; selalu salah satu dari TARGETS. */
    public function sasaranRole(): string
    {
        $target = strtolower(trim((string) ($this->target_role ?? '')));

        return in_array($target, [self::TARGET_GURU, self::TARGET_SISWA], true)
            ? $target
            : self::TARGET_SEMUA;
    }

    public function untukGuru(): bool
    {
        return in_array($this->sasaranRole(), [self::TARGET_SEMUA, self::TARGET_GURU], true);
    }

    public function untukSiswaRole(): bool
    {
        return in_array($this->sasaranRole(), [self::TARGET_SEMUA, self::TARGET_SISWA], true);
    }

    /** Sudah terbit: waktu terbit kosong atau sudah lewat. */
    public function scopeTerbit(Builder $query): Builder
    {
        return $query->where(function (Builder $sub): void {
            $sub->whereNull('publish_at')->orWhere('publish_at', '<=', now());
        });
    }

    /** Pengumuman yang boleh dibaca guru (semua sasaran kecuali khusus siswa). */
    public function scopeUntukGuru(Builder $query): Builder
    {
        return $query->terbit()->where(function (Builder $sub): void {
            $sub->whereNull('target_role')
                ->orWhere('target_role', '')
                ->orWhere('target_role', self::TARGET_SEMUA)
                ->orWhere('target_role', self::TARGET_GURU);
        });
    }

    /** Pengumuman yang relevan untuk seorang siswa. */
    public function scopeUntukSiswa(Builder $query, User $siswa): Builder
    {
        return $query->terbit()
            ->where(function (Builder $sub): void {
                $sub->whereNull('target_role')
                    ->orWhere('target_role', '')
                    ->orWhere('target_role', self::TARGET_SEMUA)
                    ->orWhere('target_role', self::TARGET_SISWA);
            })
            ->where(function (Builder $sub) use ($siswa): void {
                $sub->where(function (Builder $umum): void {
                    $umum->whereNull('kelas')->orWhere('kelas', '');
                })->orWhere(function (Builder $rombel) use ($siswa): void {
                    $rombel->where('kelas', $siswa->kelas);
                    $rombel->where(function (Builder $jurusan) use ($siswa): void {
                        $jurusan->whereNull('jurusan')->orWhere('jurusan', '')->orWhere('jurusan', $siswa->jurusan);
                    });
                });
            });
    }
}
