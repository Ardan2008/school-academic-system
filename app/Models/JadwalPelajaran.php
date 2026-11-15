<?php

namespace App\Models;

use App\Models\Concerns\MilikRombel;
use App\Services\NotifikasiAkademikService;
use App\Support\Waktu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu baris jadwal pelajaran untuk satu rombel: hari, jam, mata pelajaran,
 * dan guru pengampu.
 */
class JadwalPelajaran extends Model
{
    use HasFactory;
    use MilikRombel;

    protected $table = 'jadwal';

    /** Hari sekolah, Senin sampai Sabtu. Minggu tidak punya jadwal. */
    public const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    protected $fillable = [
        'kelas',
        'jurusan',
        'hari',
        'jam_mulai',
        'jam_selesai',
        'mata_pelajaran_id',
        'guru_id',
        'ruang',
    ];

    protected function casts(): array
    {
        return [
            'mata_pelajaran_id' => 'integer',
            'guru_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (JadwalPelajaran $jadwal) {
            app(NotifikasiAkademikService::class)->jadwalBerubah($jadwal, 'baru');
        });

        static::updated(function (JadwalPelajaran $jadwal) {
            app(NotifikasiAkademikService::class)->jadwalBerubah(
                $jadwal,
                'diubah',
                $jadwal->getOriginal('kelas'),
                $jadwal->getOriginal('jurusan')
            );
        });

        static::deleted(function (JadwalPelajaran $jadwal) {
            app(NotifikasiAkademikService::class)->jadwalBerubah($jadwal, 'dihapus');
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

    public function scopeHari(Builder $query, string $hari): Builder
    {
        return $query->where('hari', $hari);
    }

    /** Nama hari ini dalam bahasa Indonesia, null kalau hari Minggu. */
    public static function hariIni(?\DateTimeInterface $pada = null): ?string
    {
        $tanggal = Waktu::zona($pada === null ? now() : Carbon::instance($pada));
        $iso = (int) $tanggal->format('N'); // 1 = Senin ... 7 = Minggu

        return $iso >= 1 && $iso <= 6 ? self::HARI[$iso - 1] : null;
    }

    /** "07:30–09:00" untuk ditampilkan tanpa perlu mengolah jam di view. */
    public function jamLabel(): string
    {
        return substr((string) $this->jam_mulai, 0, 5).'–'.substr((string) $this->jam_selesai, 0, 5);
    }
}
