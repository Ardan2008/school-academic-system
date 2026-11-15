<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Penyesuaian zona waktu untuk waktu yang ditampilkan ke admin.
 *
 * Aplikasi menyimpan seluruh waktu dalam UTC (config/app.php) supaya basis
 * data tidak ikut berubah kalau zona waktu server diganti. Konsekuensinya,
 * waktu yang mentah dari model masih dalam UTC, sedangkan yang dibaca admin
 * seharusnya WIB.
 *
 * Tanpa konversi ini, waktu yang tampil meleset tujuh jam lebih awal, dan
 * setiap jam yang diberi label "WIB" sebenarnya jam UTC.
 */
class Waktu
{
    /** Zona waktu yang dipakai untuk seluruh tampilan waktu di aplikasi. */
    public const ZONA = 'Asia/Jakarta';

    /** Singkatan zona waktu yang ditampilkan di sebelah jam. */
    public const LABEL = 'WIB';

    /**
     * Salinan waktu yang sudah dipindahkan ke zona waktu tampilan.
     *
     * Menerima null karena banyak kolom tanggal boleh kosong, dan pemanggil
     * tidak boleh dipaksa memeriksa dua kali.
     */
    public static function zona(?Carbon $waktu): ?Carbon
    {
        return $waktu?->copy()->timezone(self::ZONA);
    }

    /**
     * Tanggal saja, contoh "01 Okt 2026".
     */
    public static function tanggal(?Carbon $waktu): string
    {
        return self::zona($waktu)?->translatedFormat('d M Y') ?? '-';
    }

    /**
     * Tanggal dan jam, contoh "01 Okt 2026 17:24".
     */
    public static function tanggalJam(?Carbon $waktu): ?string
    {
        return self::zona($waktu)?->translatedFormat('d M Y H:i');
    }

    /**
     * Jam saja, contoh "17:24".
     */
    public static function jam(?Carbon $waktu): ?string
    {
        return self::zona($waktu)?->format('H:i');
    }

    /**
     * Jam dan tanggal singkat untuk tampilan sempit, contoh "01 Okt 17:24".
     */
    public static function bulanJam(?Carbon $waktu): ?string
    {
        return self::zona($waktu)?->translatedFormat('d M H:i');
    }

    /**
     * Jam, label zona waktu, dan jarak waktu relatif dalam satu string.
     *
     * Contoh: "17:24 WIB (7 menit lalu)".
     *
     * Jarak dihitung dari waktu aslinya, bukan hasil konversi, karena jarak
     * antara dua titik waktu tidak berubah oleh zona waktu.
     */
    public static function jamRelatif(?Carbon $waktu): ?string
    {
        if (! $waktu) {
            return null;
        }

        return self::jam($waktu).' '.self::LABEL.' ('.$waktu->diffForHumans().')';
    }

    /**
     * Tanggal dan jam dengan label zona waktu, contoh "01 Okt 2026 17:24 WIB".
     */
    public static function lengkap(?Carbon $waktu): ?string
    {
        $tanggalJam = self::tanggalJam($waktu);

        return $tanggalJam ? $tanggalJam.' '.self::LABEL : null;
    }
}
