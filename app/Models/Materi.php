<?php

namespace App\Models;

use App\Models\Concerns\MilikRombel;
use App\Services\NotifikasiAkademikService;
use App\Support\FileMateri;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bahan ajar yang diunggah guru untuk satu rombel.
 */
class Materi extends Model
{
    use HasFactory;
    use MilikRombel;

    protected $table = 'materi';

    protected $fillable = [
        'kelas',
        'jurusan',
        'mata_pelajaran_id',
        'guru_id',
        'judul',
        'deskripsi',
        'tanggal',
        'file_path',
        'nama_file',
        'mime_type',
        'ekstensi',
        'ukuran_bytes',
    ];

    protected function casts(): array
    {
        return [
            'mata_pelajaran_id' => 'integer',
            'guru_id' => 'integer',
            'tanggal' => 'date',
            'ukuran_bytes' => 'integer',
        ];
    }

    protected function ukuranFile(): Attribute
    {
        return Attribute::get(fn () => FileMateri::ukuran($this->ukuran_bytes));
    }

    protected function formatFile(): Attribute
    {
        return Attribute::get(fn () => FileMateri::format($this->ekstensi));
    }

    protected function bisaDipratinjau(): Attribute
    {
        return Attribute::get(fn () => FileMateri::bisaDipratinjau($this->mime_type));
    }

    protected static function booted(): void
    {
        static::created(function (Materi $materi) {
            app(NotifikasiAkademikService::class)->materiBaru($materi);
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
}
