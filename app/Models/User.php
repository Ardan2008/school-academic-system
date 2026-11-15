<?php

namespace App\Models;

use App\Support\LoginIdentifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $role
 * @property string|null $kelas
 * @property string|null $nip
 * @property string|null $nis
 * @property string|null $jurusan
 * @property string|null $mata_pelajaran
 * @property string|null $initial_password
 * @property bool $must_change_password
 * @property bool $is_active
 * @property string $status
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    public const STATUS_AKTIF = 'Aktif';

    public const STATUS_NONAKTIF = 'Nonaktif';

    public const STATUS_LULUS = 'Lulus';

    /** Status yang boleh dipilih admin pada halaman Data Siswa. */
    public const STATUSES = [self::STATUS_AKTIF, self::STATUS_NONAKTIF, self::STATUS_LULUS];

    protected $attributes = [
        'role' => 'siswa',
        'must_change_password' => true,
        'status' => self::STATUS_AKTIF,
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'must_change_password',
        'kelas',
        'initial_password',
        'nip',
        'nis',
        'jurusan',
        'mata_pelajaran',
        'is_active',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Jaga `status` dan `is_active` supaya tidak berbeda makna:
     * - kalau `status` diisi (mis. "Lulus"), `is_active` mengikuti status itu;
     * - kalau hanya `is_active` yang diisi, `status` ikut diturunkan.
     *
     * Kolom `status` selalu jadi sumber kebenaran untuk halaman Data Siswa,
     * sedangkan `is_active` tetap dipakai sistem login.
     */
    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if ($user->isDirty('status')) {
                $user->is_active = $user->status === self::STATUS_AKTIF;

                return;
            }

            if ($user->isDirty('is_active')) {
                $user->status = $user->is_active ? self::STATUS_AKTIF : self::STATUS_NONAKTIF;
            }
        });
    }

    /**
     * Status akun versi Bahasa Indonesia. Nilai lama yang belum punya kolom
     * `status` tetap diturunkan dari `is_active`.
     */
    public function statusLabel(): string
    {
        $status = (string) ($this->attributes['status'] ?? '');

        if (in_array($status, self::STATUSES, true)) {
            return $status;
        }

        return $this->is_active ? self::STATUS_AKTIF : self::STATUS_NONAKTIF;
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function visiblePassword(): string
    {
        if (! $this->must_change_password || ! $this->initial_password) {
            return '';
        }

        try {
            return Crypt::decryptString($this->initial_password);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Pengajuan atur ulang password milik akun ini (dipakai Guru/Siswa).
     */
    public function passwordResetRequests()
    {
        return $this->hasMany(PasswordResetRequest::class);
    }

    public function passwordResetTokens()
    {
        return $this->hasMany(PasswordResetToken::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Identifier login sesuai role: Admin = email, Guru = NIP, Siswa = NIS.
     */
    public function loginIdentifier(): ?string
    {
        $field = LoginIdentifier::field((string) $this->role);

        return $this->{$field};
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
