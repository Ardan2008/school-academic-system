<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Permintaan bantuan dari pop-up "Hubungi Tim IT Sekolah" di landing page.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $role
 * @property string $identifier
 * @property string $email
 * @property string $pesan
 * @property string $status
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ItHelpRequest extends Model
{
    public const STATUS_BARU = 'baru';

    public const STATUS_DIPROSES = 'diproses';

    public const STATUS_SUDAH_DIBALAS = 'sudah_dibalas';

    public const STATUS_SELESAI = 'selesai';

    protected $fillable = [
        'user_id',
        'role',
        'identifier',
        'email',
        'pesan',
        'status',
        'ip_address',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Riwayat tindak lanjut Admin (balasan yang pernah dikirim ke
     * email pengaju). Tidak pernah dihapus manual: menghapus permintaan
     * ikut menghapus riwayatnya (cascade).
     */
    public function replies()
    {
        return $this->hasMany(ItHelpReply::class);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeBaru(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_BARU);
    }

    public function statusLabel(): string
    {
        return [
            self::STATUS_BARU => 'Baru',
            self::STATUS_DIPROSES => 'Diproses',
            self::STATUS_SUDAH_DIBALAS => 'Sudah Dibalas',
            self::STATUS_SELESAI => 'Selesai',
        ][$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }
}
