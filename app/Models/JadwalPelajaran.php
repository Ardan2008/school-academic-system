<?php

namespace App\Models;

use App\Models\Concerns\MilikRombel;
use App\Services\JamBelajar;
use App\Services\NotifikasiAkademikService;
use App\Support\Periode;
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

    /** Hari efektif yang dipakai sewaktu konfigurasi belum menentukan hari aktif. */
    public const HARI_AKTIF_UTAMA = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

    protected $fillable = [
        'kelas',
        'jurusan',
        'tahun_ajaran',
        'semester',
        'hari',
        'jam_mulai',
        'jam_selesai',
        'mata_pelajaran_id',
        'guru_id',
        'ruang',
        'sesi_id',
    ];

    protected function casts(): array
    {
        return [
            'mata_pelajaran_id' => 'integer',
            'guru_id' => 'integer',
            'sesi_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (JadwalPelajaran $jadwal): void {
            $periode = Periode::periodeSekarang();
            $jadwal->tahun_ajaran ??= $periode['tahun_ajaran'];
            $jadwal->semester ??= $periode['semester'];
        });

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

    public function sesi(): BelongsTo
    {
        return $this->belongsTo(SesiJamBelajar::class, 'sesi_id');
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

    public function infoWaktu(?JamBelajarKonfigurasi $konfigurasi = null): array
    {
        $jamBelajar = app(JamBelajar::class);
        $sesi = $this->relationLoaded('sesi') ? $this->sesi : $this->sesi()->first();
        $tersimpanMulai = substr((string) $this->jam_mulai, 0, 5);
        $tersimpanSelesai = substr((string) $this->jam_selesai, 0, 5);
        $mulai = $sesi === null ? $tersimpanMulai : substr((string) $sesi->jam_mulai, 0, 5);
        $selesai = $sesi === null ? $tersimpanSelesai : substr((string) $sesi->jam_selesai, 0, 5);
        $catatan = [];

        if ($sesi === null) {
            $catatan[] = 'Sesi jam belajar tidak tersedia; menampilkan jam yang tersimpan.';
        } elseif ($mulai !== $tersimpanMulai || $selesai !== $tersimpanSelesai) {
            $catatan[] = 'Waktu sesi terbaru '.$mulai.'–'.$selesai.'. Jadwal perlu disesuaikan.';
        }

        if ($konfigurasi !== null) {
            if (! in_array($this->hari, $jamBelajar->hariAktif($konfigurasi), true)) {
                $catatan[] = 'Hari '.$this->hari.' sedang dinonaktifkan.';
            }

            if (! $jamBelajar->dalamRentang(
                $mulai,
                $selesai,
                $konfigurasi->jamMasukLabel(),
                $konfigurasi->jamSelesaiLabel()
            )) {
                $catatan[] = 'Waktu ini berada di luar jam operasional sekolah.';
            }
        }

        return [
            'mulai' => $mulai,
            'selesai' => $selesai,
            'label' => $mulai.'–'.$selesai,
            'tersimpan' => $tersimpanMulai.'–'.$tersimpanSelesai,
            'catatan' => $catatan,
        ];
    }

    public function periodeLabel(): string
    {
        return Periode::label((string) $this->tahun_ajaran, (string) $this->semester);
    }
}
