<?php

namespace App\Models;

use App\Models\Concerns\MilikRombel;
use App\Services\NotifikasiAkademikService;
use App\Support\Waktu;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tugas yang diberikan guru kepada satu rombel.
 */
class Tugas extends Model
{
    use HasFactory;
    use MilikRombel;

    protected $table = 'tugas';

    protected $fillable = [
        'kelas',
        'jurusan',
        'mata_pelajaran_id',
        'guru_id',
        'judul',
        'deskripsi',
        'deadline',
    ];

    protected function casts(): array
    {
        return [
            'mata_pelajaran_id' => 'integer',
            'guru_id' => 'integer',
            'deadline' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Tugas $tugas) {
            app(NotifikasiAkademikService::class)->tugasBaru($tugas);
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

    public function pengumpulan(): HasMany
    {
        return $this->hasMany(PengumpulanTugas::class);
    }

    public function sudahLewatDeadline(): bool
    {
        return $this->deadline !== null && $this->deadline->isPast();
    }

    /** "3 hari lagi" / "Terlambat 2 hari" / "Hari ini" untuk badge di view. */
    public function labelDeadline(): string
    {
        if ($this->deadline === null) {
            return 'Tanpa tenggat';
        }

        $hari = (int) Waktu::zona(now())->startOfDay()
            ->diffInDays(Waktu::zona($this->deadline)->startOfDay(), false);

        return match (true) {
            $hari < 0 => 'Terlambat '.abs($hari).' hari',
            $hari === 0 => 'Hari ini',
            $hari === 1 => 'Besok',
            default => $hari.' hari lagi',
        };
    }
}
