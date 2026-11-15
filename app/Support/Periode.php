<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Periode belajar yang dipakai bersama oleh tabel nilai dan Laporan Akademik.
 *
 * Dua hal yang ditegaskan di sini:
 *
 * - Tahun ajaran dimulai bulan Juli, jadi 1 Juli 2026 sampai 30 Juni 2027
 *   sama-sama masuk tahun ajaran 2026/2027. Semester Ganjil berjalan Juli
 *   sampai Desember, Semester Genap Januari sampai Juni.
 * - Periode tidak boleh ditebak dari isian filter. Nilai yang tidak dikenal
 *   harus mengembalikan null supaya pemanggil bisa memakai periode berjalan,
 *   bukan diam-diam menampilkan angka dari periode yang salah.
 */
class Periode
{
    public const SEMESTER_GANJIL = 'Ganjil';

    public const SEMESTER_GENAP = 'Genap';

    /** Semester dalam satu tahun ajaran, urut dari yang paling awal. */
    public const SEMESTERS = [self::SEMESTER_GANJIL, self::SEMESTER_GENAP];

    public const CONTOH_TAHUN_AJARAN = '2025/2026';

    /** Group 1 tahun mulai, group 2 tahun berakhir: "2025/2026". */
    public const POLA_TAHUN_AJARAN = '#^(\d{4})\s*/\s*(\d{4})$#';

    /** Bulan pertama tahun ajaran baru. */
    private const BULAN_AWAL_TAHUN_AJARAN = 7;

    /** Tahun ajaran yang sedang berjalan, contoh "2026/2027". */
    public static function tahunAjaranSekarang(?Carbon $pada = null): string
    {
        $tanggal = Waktu::zona($pada ?? now());
        $tahun = (int) $tanggal->format('Y');
        $mulai = ((int) $tanggal->format('n')) >= self::BULAN_AWAL_TAHUN_AJARAN
            ? $tahun
            : $tahun - 1;

        return $mulai.'/'.($mulai + 1);
    }

    /** Semester yang sedang berjalan. */
    public static function semesterSekarang(?Carbon $pada = null): string
    {
        $tanggal = Waktu::zona($pada ?? now());

        return ((int) $tanggal->format('n')) >= self::BULAN_AWAL_TAHUN_AJARAN
            ? self::SEMESTER_GANJIL
            : self::SEMESTER_GENAP;
    }

    public static function semesterValid(mixed $semester): bool
    {
        return is_string($semester) && in_array($semester, self::SEMESTERS, true);
    }

    /**
     * Periode berjalan sekaligus, dipakai form dan laporan sebagai nilai
     * bawaan supaya tidak ada tempat yang menebak sendiri.
     *
     * @return array{tahun_ajaran: string, semester: string}
     */
    public static function periodeSekarang(?Carbon $pada = null): array
    {
        return [
            'tahun_ajaran' => self::tahunAjaranSekarang($pada),
            'semester' => self::semesterSekarang($pada),
        ];
    }

    public static function semesterLabel(mixed $semester): string
    {
        return self::semesterValid($semester) ? 'Semester '.$semester : '-';
    }

    /**
     * Tahun ajaran sah kalau kedua angkanya berurutan, jadi "2025/2026" ya.
     * "2025/2027" ditolak karena akan mengembalikan angka tahun yang tidak
     * pernah ada pada tabel nilai.
     */
    public static function tahunAjaranValid(mixed $tahunAjaran): bool
    {
        if (! is_string($tahunAjaran) || ! preg_match(self::POLA_TAHUN_AJARAN, trim($tahunAjaran), $match)) {
            return false;
        }

        return ((int) $match[2]) === ((int) $match[1]) + 1;
    }

    public static function tahunAjaranLabel(mixed $tahunAjaran): string
    {
        return self::tahunAjaranValid($tahunAjaran) ? 'Tahun Ajaran '.trim((string) $tahunAjaran) : '-';
    }

    /** Tahun awal dari "2025/2026" menjadi 2025, atau null kalau tidak sah. */
    public static function tahunAwal(mixed $tahunAjaran): ?int
    {
        if (! is_string($tahunAjaran) || ! preg_match(self::POLA_TAHUN_AJARAN, trim($tahunAjaran), $match)) {
            return null;
        }

        return (int) $match[1];
    }

    /**
     * Kurutan kronologis satu periode, dipakai untuk mengurutkan garis
     * perkembangan nilai. Semester Ganjil selalu lebih dulu karena berjalan
     * lebih awal pada tahun ajaran yang sama.
     */
    public static function urutan(string $tahunAjaran, string $semester): int
    {
        $tahun = self::tahunAwal($tahunAjaran) ?? 0;

        return ($tahun * 2) + ($semester === self::SEMESTER_GENAP ? 1 : 0);
    }

    /** Label siap pakai untuk grafik, contoh "Ganjil 2025/2026". */
    public static function label(string $tahunAjaran, string $semester): string
    {
        return self::semesterValid($semester) && self::tahunAjaranValid($tahunAjaran)
            ? $semester.' '.trim($tahunAjaran)
            : '-';
    }

    public static function formatMessage(): string
    {
        return 'Tahun ajaran harus ditulis '.self::CONTOH_TAHUN_AJARAN.' dan semester harus '
            .implode(' atau ', self::SEMESTERS).'.';
    }
}
