<?php

namespace App\Models;

use App\Services\NotifikasiAkademikService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan pengumpulan tugas oleh satu siswa.
 */
class PengumpulanTugas extends Model
{
    use HasFactory;

    public const STATUS_DIKUMPULKAN = 'dikumpulkan';

    public const STATUS_DINILAI = 'dinilai';

    protected $table = 'pengumpulan_tugas';

    protected $fillable = [
        'tugas_id',
        'user_id',
        'dikumpulkan_at',
        'jawaban',
        'status',
        'nilai',
        'umpan_balik',
    ];

    protected function casts(): array
    {
        return [
            'tugas_id' => 'integer',
            'user_id' => 'integer',
            'dikumpulkan_at' => 'datetime',
            'nilai' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (PengumpulanTugas $pengumpulan) {
            app(NotifikasiAkademikService::class)->pengumpulanTugas($pengumpulan, true);
        });

        static::updated(function (PengumpulanTugas $pengumpulan) {
            if ($pengumpulan->wasChanged('dikumpulkan_at')) {
                app(NotifikasiAkademikService::class)->pengumpulanTugas($pengumpulan, false);
            }

            $penilaianBerubah = $pengumpulan->wasChanged('status')
                || $pengumpulan->wasChanged('nilai')
                || $pengumpulan->wasChanged('umpan_balik');

            if ($penilaianBerubah && $pengumpulan->status === self::STATUS_DINILAI) {
                app(NotifikasiAkademikService::class)->penilaianTugas($pengumpulan);
            }
        });
    }

    public function tugas(): BelongsTo
    {
        return $this->belongsTo(Tugas::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
