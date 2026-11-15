<?php

namespace App\Support;

/**
 * Struktur pilihan filter Data Siswa: Jurusan -> Tingkat -> Rombel.
 *
 * Rombel adalah milik satu tingkat dalam satu jurusan, jadi pilihan kelas di
 * bawah tiap jurusan tidak bisa dibuat dari daftar tetap. Struktur ini disusun
 * dari kelas yang benar-benar dipakai siswa, supaya yang tampil di layar
 * selalu sama dengan isi tabel.
 *
 * Cara menampilkan kelas mengikuti cara sekolah menulisnya (lihat Kelas):
 * - satu tingkat satu rombel  -> tampilkan tingkatnya saja, contoh "11"
 * - satu tingkat banyak rombel -> tampilkan nomor rombel, contoh "10-1", "10-2"
 *
 * Tingkat 10-12 tetap selalu ada di setiap jurusan walau belum ada siswanya,
 * supaya pilihan filter tidak hilang-ganti setiap kali ada siswa baru atau
 * siswa dihapus.
 */
class StrukturKelas
{
    /**
     * Saringan dipasang pada tingkat, bukan pada kelas. Dipakai ketika satu
     * tingkat hanya punya satu kelas, supaya kelas yang tersimpan sebagai
     * "11-1" tetap ikut terambil walau yang ditampilkan cuma "11".
     */
    public const MATCH_TINGKAT = 'tingkat';

    /** Saringan dipasang persis pada kelas, dipakai saat tingkat ber-rombel. */
    public const MATCH_KELAS = 'kelas';

    /**
     * Gabungkan kelas yang sudah terdaftar di Data Kelas dengan kelas yang
     * dipakai siswa. Kelas terdaftar ikut ditampilkan walau siswanya belum ada,
     * supaya kelas yang baru dibuat admin tidak hilang dari pilihan filter.
     *
     * @param  iterable<int, array{jurusan?: string|null, kelas?: string|null}>  $rows
     * @param  iterable<int, array{jurusan?: string|null, kelas?: string|null}>  $kelasTerdaftar
     * @return array<int, array{nama: string, jumlah: int, tingkat: array<int, array{nilai: string, jumlah: int, berrombel: bool, rombel: array<int, array{nilai: string, jumlah: int, match: string, terdaftar: bool}>}>}>
     */
    public static function dariSiswa(iterable $rows, iterable $kelasTerdaftar = []): array
    {
        /** @var array<string, array{total: int, kelas: array<string, array<string, array{jumlah: int, terdaftar: bool}>>}> $kelompok */
        $kelompok = [];

        // Kelas terdaftar lebih dulu, dihitung 0 siswa, supaya kelas kosong
        // tetap punya tempat di struktur. Data Siswa menimpa jumlahnya.
        foreach ($kelasTerdaftar as $row) {
            $jurusan = (string) ($row['jurusan'] ?? '');
            $kelas = Kelas::normalize((string) ($row['kelas'] ?? ''));
            $tingkat = Kelas::tingkat($kelas);

            if (trim($jurusan) === '' || $tingkat === null) {
                continue;
            }

            self::siapkanJurusan($kelompok, $jurusan);

            $kelompok[$jurusan]['kelas'][$tingkat][$kelas] ??= ['jumlah' => 0, 'terdaftar' => true];
        }

        foreach ($rows as $row) {
            $jurusan = (string) ($row['jurusan'] ?? '');
            if (trim($jurusan) === '') {
                continue;
            }

            self::siapkanJurusan($kelompok, $jurusan);

            $kelompok[$jurusan]['total']++;

            $kelas = Kelas::normalize((string) ($row['kelas'] ?? ''));
            $tingkat = Kelas::tingkat($kelas);

            if ($tingkat === null) {
                continue;
            }

            // Siswa bisa saja masuk kelas yang belum terdaftar di Data Kelas,
            // jadi kelasnya ikut ditambahkan, ditandai belum terdaftar.
            $kelompok[$jurusan]['kelas'][$tingkat][$kelas] ??= ['jumlah' => 0, 'terdaftar' => false];
            $kelompok[$jurusan]['kelas'][$tingkat][$kelas]['jumlah']++;
        }

        $struktur = [];

        foreach ($kelompok as $jurusan => $data) {
            $struktur[] = [
                'nama' => $jurusan,
                'jumlah' => $data['total'],
                'tingkat' => self::susunTingkat($data['kelas']),
            ];
        }

        usort($struktur, fn (array $a, array $b) => strcasecmp($a['nama'], $b['nama']));

        return $struktur;
    }

    /**
     * @param  array<string, array{total: int, kelas: array<string, array<string, array{jumlah: int, terdaftar: bool}>>}>  $kelompok
     */
    protected static function siapkanJurusan(array &$kelompok, string $jurusan): void
    {
        $kelompok[$jurusan] ??= ['total' => 0, 'kelas' => []];
    }

    /**
     * @param  array<string, array{jumlah: int, terdaftar: bool}>  $kelasPerTingkat  kelas => rekap siswa
     * @return array<int, array{nilai: string, jumlah: int, berrombel: bool, rombel: array<int, array{nilai: string, jumlah: int, match: string, terdaftar: bool}>}>
     */
    protected static function susunTingkat(array $kelasPerTingkat): array
    {
        $daftar = array_values(array_unique([
            ...Kelas::tingkatUmum(),
            ...array_map(strval(...), array_keys($kelasPerTingkat)),
        ]));

        // 12 di atas 11 di atas 10, sama seperti yang dipakai di tabel.
        usort($daftar, fn (string $a, string $b) => ((int) $b) <=> ((int) $a));

        $hasil = [];

        foreach ($daftar as $tingkat) {
            // PHP mengubah kunci array yang isinya angka menjadi integer, jadi
            // dikembalikan ke teks supaya nilainya sama persis dengan kolom
            // tingkat yang dikirim ke frontend.
            $tingkat = (string) $tingkat;

            $kelas = $kelasPerTingkat[$tingkat] ?? [];
            $berrombel = count($kelas) > 1;
            $jumlah = self::jumlahSiswa($kelas);

            $hasil[] = [
                'nilai' => $tingkat,
                'jumlah' => $jumlah,
                'berrombel' => $berrombel,
                'rombel' => $berrombel
                    ? self::susunRombel($kelas)
                    : self::susunKelasTunggal($tingkat, $jumlah, $kelas),
            ];
        }

        return $hasil;
    }

    /**
     * Tingkat yang hanya punya satu kelas ditampilkan sebagai nomor tingkat,
     * contoh kelas "10-1" menjadi "10".
     *
     * Status "terdaftar" diambil dari kelas tunggal itu sendiri, bukan dari
     * kunci sebesar tingkat: kalau kelasnya "10-1" sementara tingkatnya "10",
     * keduanya beda kunci dan statusnya akan selalu terbaca belum terdaftar.
     *
     * @param  array<string, array{jumlah: int, terdaftar: bool}>  $kelas
     * @return array<int, array{nilai: string, jumlah: int, match: string, terdaftar: bool}>
     */
    protected static function susunKelasTunggal(string $tingkat, int $jumlah, array $kelas): array
    {
        $satu = reset($kelas) ?: ['jumlah' => 0, 'terdaftar' => false];

        return [[
            'nilai' => $tingkat,
            'jumlah' => $jumlah,
            'match' => self::MATCH_TINGKAT,
            'terdaftar' => (bool) $satu['terdaftar'],
        ]];
    }

    /**
     * @param  array<string, array{jumlah: int, terdaftar: bool}>  $kelas
     * @return array<int, array{nilai: string, jumlah: int, match: string, terdaftar: bool}>
     */
    protected static function susunRombel(array $kelas): array
    {
        $daftar = array_map(strval(...), array_keys($kelas));

        // Nomor rombel dibandingkan sebagai angka, jadi 12-2 tetap mendahului
        // 12-10. Kalau diurutkan sebagai teks, 12-10 akan muncul lebih dulu.
        usort($daftar, self::bandingkanKelas(...));

        return array_map(
            fn (string $nilai) => [
                'nilai' => $nilai,
                'jumlah' => $kelas[$nilai]['jumlah'],
                'match' => self::MATCH_KELAS,
                'terdaftar' => $kelas[$nilai]['terdaftar'],
            ],
            $daftar
        );
    }

    /**
     * @param  array<string, array{jumlah: int, terdaftar: bool}>  $kelas
     */
    protected static function jumlahSiswa(array $kelas): int
    {
        return array_sum(array_column($kelas, 'jumlah'));
    }

    /**
     * Bandingkan dua kelas untuk pengurutan. Dipakai juga oleh Data Kelas,
     * jadi urutannya sama antara daftar kelas dan filter Data Siswa.
     */
    public static function bandingkanKelas(string $a, string $b): int
    {
        $tingkat = ((int) Kelas::tingkat($a)) <=> ((int) Kelas::tingkat($b));

        if ($tingkat !== 0) {
            return $tingkat;
        }

        // Tanpa nomor rombel (0) diletakkan paling awal.
        return ((int) Kelas::rombel($a)) <=> ((int) Kelas::rombel($b));
    }
}
