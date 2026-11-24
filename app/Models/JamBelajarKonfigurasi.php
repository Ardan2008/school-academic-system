<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JamBelajarKonfigurasi extends Model
{
    use HasFactory;

    protected $table = 'jam_belajar_konfigurasi';

    protected $fillable = [
        'jam_masuk',
        'jam_selesai',
        'keterangan',
        'hari_aktif',
    ];

    protected $casts = [
        'hari_aktif' => 'array',
    ];

    public function sesi(): HasMany
    {
        return $this->hasMany(SesiJamBelajar::class, 'jam_belajar_konfigurasi_id');
    }

    public function jamMasukLabel(): string
    {
        return substr((string) $this->jam_masuk, 0, 5);
    }

    public function jamSelesaiLabel(): string
    {
        return substr((string) $this->jam_selesai, 0, 5);
    }
}
