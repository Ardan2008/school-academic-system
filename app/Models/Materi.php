<?php

namespace App\Models;

use App\Models\Concerns\MilikRombel;
use App\Services\NotifikasiAkademikService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bahan ajar yang diunggah guru untuk satu rombel.
 */
class Materi extends Model
{
    use HasFactory;
    use MilikRombel;

    protected $table = 'materi';

    protected $fillable = [
        'kelas',
        'jurusan',
        'mata_pelajaran_id',
        'guru_id',
        'judul',
        'deskripsi',
        'tanggal',
    ];

    protected function casts(): array
    {
        return [
            'mata_pelajaran_id' => 'integer',
            'guru_id' => 'integer',
            'tanggal' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Materi $materi) {
            app(NotifikasiAkademikService::class)->materiBaru($materi);
        });
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }
}
