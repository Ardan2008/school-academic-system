<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat file backup database.
 *
 * Isi file .sql-nya disimpan di disk, sedangkan tabel ini hanya mencatat
 * metadata-nya: nama file, ukuran, jumlah tabel, dan siapa yang membuat.
 * Karena itu file dan baris riwayat harus selalu dihapus berpasangan.
 */
class BackupDatabase extends Model
{
    protected $table = 'backup_database';

    protected $fillable = [
        'nama_file',
        'driver',
        'database',
        'ukuran_bytes',
        'jumlah_tabel',
        'durasi_detik',
        'catatan',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'ukuran_bytes' => 'integer',
            'jumlah_tabel' => 'integer',
            'durasi_detik' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /** Nama file tanpa folder, dipakai saat mengirim unduhan ke browser. */
    public function namaSederhana(): string
    {
        return basename(str_replace('\\', '/', $this->nama_file));
    }
}
