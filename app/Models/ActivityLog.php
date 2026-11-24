<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const TIPE_AUTH = 'auth';

    public const TIPE_PASSWORD = 'password';

    public const TIPE_PROFIL = 'profil';

    public const TIPE_DATA_GURU = 'data_guru';

    public const TIPE_DATA_SISWA = 'data_siswa';

    public const TIPE_DATA_KELAS = 'data_kelas';

    public const TIPE_DATA_JURUSAN = 'data_jurusan';

    public const TIPE_DATA_MAPEL = 'data_mapel';

    public const TIPE_MANAJEMEN_AKUN = 'manajemen_akun';

    public const TIPE_PENGAJUAN_RESET_PASSWORD = 'pengajuan_reset_password';

    public const TIPE_KENDALA_AKUN = 'kendala_akun';

    public const TIPE_BACKUP_DATABASE = 'backup_database';

    public const TIPE_INPUT_NILAI = 'input_nilai';

    public const TIPE_JADWAL_PENYESUAIAN = 'jadwal_penyesuaian';

    public const LABELS = [
        self::TIPE_AUTH => 'Autentikasi',
        self::TIPE_PASSWORD => 'Password',
        self::TIPE_PROFIL => 'Profil',
        self::TIPE_DATA_GURU => 'Data Guru',
        self::TIPE_DATA_SISWA => 'Data Siswa',
        self::TIPE_DATA_KELAS => 'Data Kelas',
        self::TIPE_DATA_JURUSAN => 'Data Jurusan',
        self::TIPE_DATA_MAPEL => 'Data Mata Pelajaran',
        self::TIPE_MANAJEMEN_AKUN => 'Manajemen Akun',
        self::TIPE_PENGAJUAN_RESET_PASSWORD => 'Pengajuan Reset Password',
        self::TIPE_KENDALA_AKUN => 'Kendala Akun',
        self::TIPE_BACKUP_DATABASE => 'Backup Database',
        self::TIPE_INPUT_NILAI => 'Input Nilai',
        self::TIPE_JADWAL_PENYESUAIAN => 'Jadwal Mengajar',
    ];

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'user_name',
        'role',
        'activity_type',
        'description',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::labelFor($this->activity_type);
    }

    public static function labelFor(string $type): string
    {
        return self::LABELS[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    public static function labels(): array
    {
        return self::LABELS;
    }

    public function roleLabel(): string
    {
        return [
            'admin' => 'Admin',
            'guru' => 'Guru',
            'siswa' => 'Siswa',
        ][$this->role] ?? ucfirst((string) $this->role);
    }
}
