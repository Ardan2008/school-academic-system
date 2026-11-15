<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    /**
     * Kategori notifikasi yang benar-benar dihasilkan sistem. Setiap nilai
     * dipakai oleh NotifikasiAkademikService dan tabel-notifikasi tiap role;
     * nilai lain tetap sah karena kolom type bebas string.
     */
    public const TIPE_TUGAS_BARU = 'tugas_baru';

    public const TIPE_DEADLINE_TUGAS = 'deadline_tugas';

    public const TIPE_NILAI = 'nilai';

    public const TIPE_JADWAL = 'jadwal';

    public const TIPE_PENGUMUMAN = 'pengumuman';

    public const TIPE_PENGUMPULAN_TUGAS = 'pengumpulan_tugas';

    public const TIPE_INPUT_NILAI = 'input_nilai';

    public const TIPE_TUGAS_AKADEMIK = 'tugas_akademik';

    protected $fillable = [
        'user_id',
        'role',
        'type',
        'title',
        'message',
        'icon',
        'url',
        'reference_key',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => now()]);
        }
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }
}
