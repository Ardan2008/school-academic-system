<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SesiJamBelajar extends Model
{
    use HasFactory;

    protected $table = 'sesi_jam_belajar';

    public const JENIS_PELAJARAN = 'pelajaran';

    public const JENIS_ISTIRAHAT = 'istirahat';

    public const JENIS = [self::JENIS_PELAJARAN, self::JENIS_ISTIRAHAT];

    protected $fillable = [
        'jam_belajar_konfigurasi_id',
        'nama',
        'jenis',
        'urutan',
        'jam_mulai',
        'jam_selesai',
    ];

    protected $casts = [
        'jam_belajar_konfigurasi_id' => 'integer',
        'urutan' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (SesiJamBelajar $sesi): void {
            $sesi->jam_mulai = substr((string) $sesi->jam_mulai, 0, 5);
            $sesi->jam_selesai = substr((string) $sesi->jam_selesai, 0, 5);
        });
    }

    public function konfigurasi(): BelongsTo
    {
        return $this->belongsTo(JamBelajarKonfigurasi::class, 'jam_belajar_konfigurasi_id');
    }

    public function scopePelajaran(Builder $query): Builder
    {
        return $query->where('jenis', self::JENIS_PELAJARAN);
    }

    public function scopeDiurutkan(Builder $query): Builder
    {
        return $query->orderBy('urutan')->orderBy('jam_mulai');
    }

    public function waktuLabel(): string
    {
        return substr((string) $this->jam_mulai, 0, 5).'–'.substr((string) $this->jam_selesai, 0, 5);
    }

    public function jenisLabel(): string
    {
        return $this->jenis === self::JENIS_ISTIRAHAT ? 'Istirahat' : 'Pelajaran';
    }
}
