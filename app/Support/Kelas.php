<?php

namespace App\Support;

/**
 * Format kelas yang dipakai bersama oleh Data Siswa Admin dan impor CSV akun,
 * supaya keduanya tidak menghasilkan dua gaya penulisan yang berbeda.
 *
 * Kelas SELALU ditulis dengan angka, dan itu berlaku mulai dari input sampai
 * dokumen cetak. Alasannya angka bisa diurutkan dan difilter dengan benar,
 * sedangkan "XII" dan "12" yang bercampur akan merusak keduanya.
 *
 * - "12"    -> satu tingkat satu rombel
 * - "12-1"  -> satu tingkat dibagi ke beberapa rombel
 *
 * Nomor rombel juga ditulis angka supaya kelas 12-1 tidak tertukar dengan 12-11.
 * Jurusan tetap disimpan terpisah sehingga tidak perlu diulang di dalam kelas.
 */
class Kelas
{
    /** Contoh paling sederhana: satu tingkat satu rombel. */
    public const CONTOH = '12';

    /** Contoh ketika satu tingkat dibagi ke beberapa rombel. */
    public const CONTOH_ROMBEL = '12-1';

    /**
     * Pola nilai yang sudah dinormalkan. Group tangkapan ke-1 berisi tingkat
     * dan ke-2 berisi nomor rombel, jadi Kelas::tingkat() dan Kelas::rombel()
     * bisa membacanya dari pola yang sama.
     */
    public const PATTERN = '/^(\d{1,2})(?:-(\d{1,2}))?$/';

    /**
     * Rapikan penulisan kelas: buang spasi di pinggir dan rapikan spasi di
     * sekitar tanda hubung, sehingga "12 - 1" menjadi "12-1".
     * Nilai yang tidak dikenali dikembalikan apa adanya supaya pesan
     * validasinya bisa menunjuk isinya.
     */
    public static function normalize(string $kelas): string
    {
        return self::rapikan($kelas);
    }

    public static function rapikan(string $kelas): string
    {
        $kelas = trim($kelas);

        // Buka pembungkus ="..." yang dipakai export CSV supaya Excel tidak
        // mengubah "12-1" menjadi tanggal, supaya file export tetap bisa diimpor
        // ulang tanpa perlu dirapikan tangan.
        if (preg_match('/^="(.+)"$/', $kelas, $match)) {
            $kelas = $match[1];
        }

        return (string) preg_replace('/\s*-\s*/', '-', $kelas);
    }

    public static function isValid(string $kelas): bool
    {
        return (bool) preg_match(self::PATTERN, self::rapikan($kelas));
    }

    /**
     * Tingkat yang dipakai sekolah. Filter tingkat selalu menampilkannya walau
     * belum ada siswa pada tingkat tersebut, supaya opsinya tidak hilang-ganti
     * setiap kali data siswa bertambah atau berkurang.
     *
     * @return array<int, string>
     */
    public static function tingkatUmum(): array
    {
        return ['12', '11', '10'];
    }

    /**
     * Ambil tingkat dari kelas, contoh "12-1" menjadi "12" dan "12" tetap "12".
     * Dipakai untuk mengisi filter tingkat supaya admin tidak perlu memilih
     * kelas per-rombel, cukup memilih tingkatnya saja.
     */
    public static function tingkat(?string $kelas): ?string
    {
        return self::cocok($kelas)[1] ?? null;
    }

    /**
     * Ambil nomor rombel dari kelas, contoh "12-1" menjadi "1". Kelas yang
     * ditulis tingkat saja ("12") tidak punya rombel, jadi hasilnya null.
     * Filter rombel memakai ini untuk membedakan kelas "12" dari "12-1".
     */
    public static function rombel(?string $kelas): ?string
    {
        return self::cocok($kelas)[2] ?? null;
    }

    /**
     * @return array<int, string> group tangkapan dari pola kelas
     */
    protected static function cocok(?string $kelas): array
    {
        if ($kelas === null) {
            return [];
        }

        return preg_match(self::PATTERN, self::normalize($kelas), $match) ? $match : [];
    }

    public static function formatMessage(): string
    {
        return 'Format kelas harus tingkat saja (contoh '.self::CONTOH.')'
            .' atau tingkat-nomor rombel (contoh '.self::CONTOH_ROMBEL.').';
    }
}
