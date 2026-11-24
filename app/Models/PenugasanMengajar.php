<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenugasanMengajar extends Model
{
    protected $table = 'penugasan_mengajar';

    protected $fillable = [
        'guru_id',
        'mata_pelajaran_id',
        'kelas_id',
    ];

    protected function casts(): array
    {
        return [
            'guru_id' => 'integer',
            'mata_pelajaran_id' => 'integer',
            'kelas_id' => 'integer',
        ];
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(KelasRombel::class, 'kelas_id');
    }
}
