<?php

namespace App\Services;

use App\Exceptions\BackupGagalException;
use App\Models\BackupDatabase;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Membuat file .sql dari seluruh isi database yang sedang dipakai aplikasi.
 *
 * Dump ditulis murni dengan PHP, bukan memanggil mysqldump dari sistem.
 * Alasannya: mysqldump sering tidak ikut terpasang di shared hosting maupun
 * di kontainer, sedangkan dump ini selalu bisa jalan selama PDO untuk database
 * itu aktif.
 *
 * Yang ditulis berurutan dari atas ke bawah:
 *   1. Header komentar, lengkap dengan perintah session agar nilainya tidak
 *      berubah artinya saat dipulihkan.
 *   2. Perintah CREATE TABLE, CREATE VIEW, dan CREATE TRIGGER dari definisi
 *      asli di server database.
 *   3. Perintah INSERT per beberapa baris sekaligus.
 *   4. Perintah penutup yang mengembalikan pengaturan session.
 *
 * Catatan penting: definisi view dan trigger diambil apa adanya dari server
 * karena sintaksnya sangat spesifik per database, sehingga tidak bisa
 * disusun ulang dari daftar kolom.
 */
class DatabaseBackupService
{
    /**
     * Nama tabel yang tidak perlu dicadangkan.
     *
     * Isinya file sementara atau data sementara yang bukan master data
     * sekolah, jadi menyalinnya hanya menambah ukuran file tanpa manfaat.
     *
     * @var array<int, string>
     */
    protected const TABEL_DILEWATI = [
        'backup_database',
        'cache',
        'cache_locks',
        'jobs',
        'failed_jobs',
        'job_batches',
        'sessions',
    ];

    /**
     * Buat satu file backup baru.
     *
     * @param  array{catatan?: string|null, dibuat_oleh?: int|null}  $opsi
     * @return array{backup: BackupDatabase, dihapus: array<int, BackupDatabase>}
     *
     * @throws Throwable
     */
    public function jalankan(array $opsi = []): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            throw BackupGagalException::gagal(
                "Backup untuk driver \"{$driver}\" belum didukung."
            );
        }

        // Backup yang sedang berjalan dikunci supaya dua permintaan yang
        // kebetulan datang bersamaan tidak menulis file ke nama yang sama
        // dan tidak membebani database dengan dua pembacaan penuh.
        $kunci = Cache::lock(
            'backup-database:'.$driver.':'.$connection->getDatabaseName(),
            max(1, (int) config('backup.timeout', 600))
        );

        try {
            return $kunci->block(
                max(0, (int) config('backup.lock_wait', 5)),
                fn () => $this->buat($connection, $driver, $opsi)
            );
        } catch (LockTimeoutException $e) {
            // Pesan khusus supaya admin tahu masalahnya bukan datanya, tapi
            // ada proses backup lain yang belum selesai.
            throw BackupGagalException::sedangBerjalan($e);
        }
    }

    /**
     * Hapus backup beserta filenya.
     *
     * Aman dipanggil untuk backup yang filenya sudah hilang, karena yang
     * tersisa tinggal menghapus baris riwayatnya.
     */
    public function hapus(BackupDatabase $backup): void
    {
        Storage::disk($this->disk())->delete($backup->nama_file);

        $backup->delete();
    }

    /**
     * Potong riwayat lama supaya hanya menyisakan "backup.keep" file terbaru.
     *
     * @return array<int, BackupDatabase>
     */
    public function rotasi(): array
    {
        $maks = (int) config('backup.keep');

        if ($maks <= 0) {
            return [];
        }

        $lebih = BackupDatabase::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->slice($maks)
            ->values();

        foreach ($lebih as $backup) {
            $this->hapus($backup);
        }

        return $lebih->all();
    }

    /**
     * Inti dari pembuatan backup: menulis file lalu mendaftarkannya.
     *
     * Dipisah dari jalankan() supaya seluruh proses berada di dalam satu
     * kunci, dari pembacaan database sampai file selesai ditulis.
     *
     * @param  array{catatan?: string|null, dibuat_oleh?: int|null}  $opsi
     * @return array{backup: BackupDatabase, dihapus: array<int, BackupDatabase>}
     */
    protected function buat(ConnectionInterface $connection, string $driver, array $opsi): array
    {
        $mulai = microtime(true);

        $database = $this->namaDatabase($connection, $driver);
        $path = config('backup.folder').'/'.$this->namaFile($database);

        $disk = Storage::disk($this->disk());

        // Nama file sudah dipastikan unik di atas, tapi file sisa dari proses
        // yang gagal sebelumnya tetap dihapus agar dump baru tidak menempel
        // di belakang isi lama.
        $disk->delete($path);

        $jumlahTabel = 0;
        $zonaWaktu = $this->zonaWaktuSesi($connection, $driver);

        try {
            $this->tulis($disk, $path, $connection, $driver, $database, $zonaWaktu, $jumlahTabel);
        } catch (Throwable $e) {
            // File yang gagal dibuat tetap dihapus supaya tidak memenuhi disk
            // dan tidak pernah terbaca sebagai backup yang utuh.
            $disk->delete($path);

            throw $e;
        }

        if (! $disk->exists($path) || $disk->size($path) === 0) {
            $disk->delete($path);

            throw BackupGagalException::gagal('File backup tidak berhasil dibuat di penyimpanan.');
        }

        $durasi = (int) round(microtime(true) - $mulai);

        try {
            $backup = BackupDatabase::create([
                'nama_file' => $path,
                'driver' => $driver,
                'database' => $database,
                // Ukuran dibaca dari file di disk, bukan dari penjumlahan panjang
                // teks, karena angka yang tampil di halaman harus sama dengan
                // file yang benar-benar diunduh.
                'ukuran_bytes' => (int) $disk->size($path),
                'jumlah_tabel' => $jumlahTabel,
                'durasi_detik' => $durasi,
                'catatan' => $this->rapikanCatatan($opsi['catatan'] ?? null),
                'dibuat_oleh' => $opsi['dibuat_oleh'] ?? null,
            ]);
        } catch (Throwable $e) {
            // Baris riwayat gagal disimpan, jadi file-nya ikut dibuang. Kalau
            // dibiarkan, file itu tidak akan pernah terlihat di daftar dan
            // juga tidak pernah ikut rotasi, karena rotasi hanya menghitung
            // baris riwayat.
            $disk->delete($path);

            throw $e;
        }

        // Rotasi dijalankan setelah baris riwayat tersimpan, supaya file
        // yang baru dijamin ikut terdaftar sebelum yang lama dihapus.
        $dihapus = $this->rotasi();

        return ['backup' => $backup, 'dihapus' => $dihapus];
    }

    /**
     * Tulis seluruh isi database ke satu file backup.
     *
     * Urutannya penting dan tidak boleh diacak:
     *   - View ditulis setelah tabel, karena view bisa mengacu ke tabel yang
     *     secara alfabet muncul belakangan.
     *   - Trigger ditulis paling akhir, sesuai urutan yang dipakai mysqldump.
     */
    protected function tulis(
        Filesystem $disk,
        string $path,
        ConnectionInterface $connection,
        string $driver,
        string $database,
        ?string $zonaWaktu,
        int &$jumlahTabel
    ): void {
        // File dibuat lebih dulu supaya foldernya ikut terbentuk, lalu baru
        // dibuka untuk ditulisi.
        $disk->put($path, '');

        [$tulis, $tutup] = $this->penulis($disk, $path);

        try {
            $tulis($this->header($database, $driver, $zonaWaktu));

            $objek = $this->daftarObjek($connection, $driver);

            foreach ($objek['tabel'] as $nama) {
                $jumlahTabel++;

                $this->tulisObjek($tulis, $connection, $driver, $nama, 'table');
            }

            foreach ($objek['view'] as $nama) {
                $this->tulisObjek($tulis, $connection, $driver, $nama, 'view');
            }

            foreach ($objek['trigger'] as $nama) {
                $this->tulisObjek($tulis, $connection, $driver, $nama, 'trigger');
            }

            $tulis($this->footer($driver, $zonaWaktu));
        } finally {
            $tutup();
        }
    }

    /**
     * Tulis definisi satu objek, lalu isi datanya kalau itu tabel.
     *
     * @param  callable(string): void  $tulis
     */
    protected function tulisObjek(callable $tulis, ConnectionInterface $connection, string $driver, string $nama, string $jenis): void
    {
        $definisi = $this->definisi($connection, $driver, $nama, $jenis);

        if ($definisi === null || trim($definisi) === '') {
            return;
        }

        $tulis(sprintf("\n-- %s %s\n", ucfirst($jenis), $nama));
        $tulis(rtrim(trim($definisi)).";\n");

        // View dan trigger tidak punya baris sendiri. Isi view dihitung dari
        // tabel dasar, dan trigger baru berjalan kalau ada peristiwa.
        if ($jenis !== 'table') {
            return;
        }

        if ($this->tulisBaris($tulis, $connection, $nama) > 0) {
            $tulis("\n");
        }
    }

    /**
     * Buat dua callable untuk menulis dan menutup satu file backup.
     *
     * Disk lokal ditulis lewat stream PHP dengan mode append. Method put()
     * milik Filesystem menimpa file dari awal, jadi kalau dipakai untuk
     * pengiriman berkala, isinya akan terpotong setiap kali potongan baru
     * dikirim dan file akhirnya hanya berisi potongan terakhir.
     *
     * Disk tanpa path lokal (misalnya S3) tidak bisa dialiri, jadi teksnya
     * ditahan di memori lalu dikirim satu kali di akhir.
     *
     * @return array{0: callable(string): void, 1: callable(): void}
     */
    protected function penulis(Filesystem $disk, string $path): array
    {
        $stream = @fopen((string) $this->pathLokal($disk, $path), 'ab');

        if (is_resource($stream)) {
            return [
                function (string $teks) use ($stream): void {
                    fwrite($stream, $teks);
                },
                function () use ($stream): void {
                    fclose($stream);
                },
            ];
        }

        $buffer = '';

        return [
            function (string $teks) use (&$buffer): void {
                $buffer .= $teks;
            },
            function () use (&$buffer, $disk, $path): void {
                if ($buffer !== '') {
                    $disk->put($path, $buffer);
                    $buffer = '';
                }
            },
        ];
    }

    /**
     * Path file di sistem berkas lokal, atau null kalau disk-nya tidak punya.
     *
     * Path dari disk non-lokal bisa berupa URL, yang tidak bisa dibuka sebagai
     * stream PHP, jadi hasilnya ikut ditolak.
     */
    protected function pathLokal(Filesystem $disk, string $path): ?string
    {
        if (! method_exists($disk, 'path')) {
            return null;
        }

        try {
            $lokasi = $disk->path($path);
        } catch (Throwable) {
            return null;
        }

        if (! is_string($lokasi) || $lokasi === '' || str_contains($lokasi, '://')) {
            return null;
        }

        return $lokasi;
    }

    /**
     * Daftar objek yang perlu dicadangkan, dipisah per jenis.
     *
     * @return array{tabel: array<int, string>, view: array<int, string>, trigger: array<int, string>}
     */
    protected function daftarObjek(ConnectionInterface $connection, string $driver): array
    {
        if ($driver === 'sqlite') {
            $baris = $connection->select(
                "SELECT name, type, tbl_name FROM sqlite_master WHERE type IN ('table', 'view', 'trigger') AND name NOT LIKE 'sqlite_%'"
            );

            $tabel = [];
            $view = [];
            $trigger = [];

            foreach ($baris as $b) {
                $nama = (string) $b->name;

                // Trigger ikut dilewati kalau tabelnya tidak dicadangkan,
                // supaya tidak ada trigger yang menunjuk tabel yang hilang.
                if (in_array((string) $b->tbl_name, self::TABEL_DILEWATI, true)) {
                    continue;
                }

                match ((string) $b->type) {
                    'view' => $view[] = $nama,
                    'trigger' => $trigger[] = $nama,
                    default => $tabel[] = $nama,
                };
            }
        } else {
            // Placeholder tidak boleh dipakai di perintah SHOW, jadi jenis
            // objeknya dibedakan di PHP.
            $baris = $connection->select('SHOW FULL TABLES');

            $tabel = [];
            $view = [];

            foreach ($baris as $b) {
                $nilai = array_values((array) $b);
                $nama = (string) ($nilai[0] ?? '');
                $jenis = (string) ($nilai[1] ?? '');

                if ($jenis === 'VIEW') {
                    $view[] = $nama;
                } else {
                    $tabel[] = $nama;
                }
            }

            // SHOW FULL TABLES tidak menyebut trigger, jadi dibaca terpisah
            // dari katalog MySQL.
            $baris = $connection->select(
                'SELECT TRIGGER_NAME, EVENT_OBJECT_TABLE FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()'
            );

            $trigger = [];

            foreach ($baris as $b) {
                $nilai = (array) $b;

                if (in_array((string) ($nilai['EVENT_OBJECT_TABLE'] ?? ''), self::TABEL_DILEWATI, true)) {
                    continue;
                }

                $trigger[] = (string) ($nilai['TRIGGER_NAME'] ?? '');
            }
        }

        return [
            'tabel' => $this->bersihkan($tabel),
            'view' => $this->bersihkan($view),
            'trigger' => $this->bersihkan($trigger),
        ];
    }

    /**
     * Buang nama yang tidak diizinkan dan urutkan supaya urutannya stabil.
     *
     * @param  array<int, string>  $nama
     * @return array<int, string>
     */
    protected function bersihkan(array $nama): array
    {
        $nama = array_values(array_filter(
            $nama,
            fn (string $n) => $n !== '' && ! in_array($n, self::TABEL_DILEWATI, true)
        ));

        sort($nama, SORT_STRING);

        return $nama;
    }

    /**
     * Perintah CREATE untuk tabel, view, atau trigger, diambil langsung dari
     * definisi aslinya di server database.
     *
     * Definisi asli jauh lebih akurat daripada disusun ulang dari daftar
     * kolom, karena tipe khusus seperti ENUM, unsigned, kolom virtual, dan
     * trigger dengan beberapa statement tidak selalu bisa ditiru.
     */
    protected function definisi(ConnectionInterface $connection, string $driver, string $nama, string $jenis): ?string
    {
        if ($driver === 'sqlite') {
            // Nilai "type" di sqlite_master sama persis dengan nama jenis di
            // sini: table, view, atau trigger.
            $baris = $connection->select(
                'SELECT sql FROM sqlite_master WHERE type = ? AND name = ?',
                [$jenis, $nama]
            );

            return isset($baris[0]) ? (string) ($baris[0]->sql ?? '') : null;
        }

        $baris = $connection->select(sprintf(
            'SHOW CREATE %s %s',
            strtoupper($jenis),
            $this->quoteNama($connection, $nama)
        ));

        if (! isset($baris[0])) {
            return null;
        }

        // Hasil SHOW CREATE punya beberapa kolom, dan yang berisi perintah
        // SQL bisa berada di posisi berbeda tiap jenis objek. Jadi kolomnya
        // dicari, bukan diambil dari indeks tetap.
        foreach ((array) $baris[0] as $nilai) {
            if (str_starts_with(strtoupper(ltrim((string) $nilai)), 'CREATE')) {
                return (string) $nilai;
            }
        }

        return null;
    }

    /**
     * Tulis isi tabel sebagai perintah INSERT.
     *
     * Baris dibaca per bagian (chunk) supaya database yang besar tidak
     * membuat seluruh tabel masuk ke memori sekaligus.
     *
     * @param  callable(string): void  $tulis
     */
    protected function tulisBaris(callable $tulis, ConnectionInterface $connection, string $tabel): int
    {
        $perBagian = max(1, (int) config('backup.rows_per_insert'));
        $namaTabel = $this->quoteNama($connection, $tabel);

        $total = 0;
        $buffer = [];

        // Perintah INSERT digabung beberapa baris sekaligus supaya jumlah
        // perintah tidak meledak kalau tabelnya berisi ribuan baris.
        $kirim = function () use (&$buffer, &$total, $tulis, $namaTabel): void {
            if ($buffer === []) {
                return;
            }

            $tulis(sprintf(
                'INSERT INTO %s VALUES %s;'."\n",
                $namaTabel,
                implode(',', $buffer)
            ));

            $total += count($buffer);
            $buffer = [];
        };

        $connection->table($tabel)
            ->orderBy($this->kunciUrut($connection, $tabel))
            ->chunk($perBagian, function ($rows) use (&$buffer, $kirim, $connection, $perBagian): void {
                foreach ($rows as $row) {
                    $buffer[] = '('.$this->barisNilai($connection, (array) $row).')';

                    if (count($buffer) >= $perBagian) {
                        $kirim();
                    }
                }
            });

        // Sisa baris yang belum memenuhi batas perBagian masih ada di buffer.
        $kirim();

        return $total;
    }

    /**
     * Kolom untuk ORDER BY saat membaca baris.
     *
     * Diperlukan karena driver MySQL hanya boleh memakai kursor pada hasil
     * query yang sudah terurut, dan hasil pengembalian chunk harus selalu
     * sama supaya tidak ada baris yang terlewat atau ditulis dua kali.
     * Kalau tabel tidak punya kolom sama sekali, urut berdasarkan rowid
     * tetap aman karena SQLite selalu menyediakan kolom tersebut.
     */
    protected function kunciUrut(ConnectionInterface $connection, string $tabel): string
    {
        $kolom = $this->kolomTabel($connection, $tabel);

        // Kolom id lebih dipilih dulu supaya urutannya sama dengan urutan
        // tabel saat dibaca di phpMyAdmin, dan dianggap stabil oleh MySQL.
        foreach (['id', 'uuid', 'kode', 'nama', 'created_at'] as $kandidat) {
            if (in_array($kandidat, $kolom, true)) {
                return $kandidat;
            }
        }

        return $kolom[0] ?? 'rowid';
    }

    /**
     * Daftar nama kolom sebuah tabel.
     *
     * @return array<int, string>
     */
    protected function kolomTabel(ConnectionInterface $connection, string $tabel): array
    {
        $kolom = $connection->getSchemaBuilder()->getColumns($tabel);

        return array_values(array_filter(array_map(
            fn ($k) => (string) ($k['name'] ?? ''),
            $kolom
        )));
    }

    /**
     * Satu baris menjadi daftar nilai yang sudah di-escape dan diapit kurung.
     *
     * @param  array<string, mixed>  $row
     */
    protected function barisNilai(ConnectionInterface $connection, array $row): string
    {
        $nilai = [];

        foreach ($row as $value) {
            if ($value === null) {
                $nilai[] = 'NULL';
            } elseif (is_bool($value)) {
                $nilai[] = $value ? '1' : '0';
            } elseif (is_int($value) || is_float($value)) {
                $nilai[] = (string) $value;
            } else {
                $nilai[] = $this->quoteNilai($connection, (string) $value);
            }
        }

        return implode(',', $nilai);
    }

    /**
     * Escape nilai teks memakai quoting bawaan driver.
     *
     * Cara ini otomatis aman untuk karakter null byte dan tanda kutip, dan
     * tetap benar ketika MySQL memakai mode NO_BACKSLASH_ESCAPES karena
     * quoting dilakukan oleh driver, bukan oleh kode di sini.
     */
    protected function quoteNilai(ConnectionInterface $connection, string $value): string
    {
        $quoted = $connection->getPdo()->quote($value);

        return $quoted === false ? "'".str_replace("'", "''", $value)."'" : $quoted;
    }

    /**
     * Bungkus nama tabel dengan tanda kutip yang benar untuk driver.
     *
     * Nama tabel tidak bisa di-parameterisasi, jadi harus di-escape manual.
     * Tanda kutip di dalam nama dibalik dua kali supaya tidak menutupi
     * tanda kutip pembuka.
     */
    protected function quoteNama(ConnectionInterface $connection, string $nama): string
    {
        if ($connection->getDriverName() === 'sqlite') {
            return '"'.str_replace('"', '""', $nama).'"';
        }

        return '`'.str_replace('`', '``', $nama).'`';
    }

    /**
     * Zona waktu session database, supaya nilai timestamp punya arti sama
     * ketika dipulihkan.
     */
    protected function zonaWaktuSesi(ConnectionInterface $connection, string $driver): ?string
    {
        if ($driver === 'sqlite') {
            return null;
        }

        try {
            $baris = $connection->select('SELECT @@session.time_zone AS zona');
        } catch (Throwable) {
            // Beberapa versi MySQL/mariaDB menolak pembacaan ini, dan dump
            // tetap bisa dipakai tanpa itu.
            return null;
        }

        $nilai = isset($baris[0]) ? (string) (array_values((array) $baris[0])[0] ?? '') : '';

        return $nilai === '' ? null : $nilai;
    }

    protected function namaDatabase(ConnectionInterface $connection, string $driver): string
    {
        if ($driver === 'sqlite') {
            $path = (string) ($connection->getDatabaseName() ?: 'sqlite');

            return Str::of(basename($path))->before('.sqlite')->limit(50, '')->value();
        }

        return Str::of((string) $connection->getDatabaseName())->limit(50, '')->value();
    }

    /**
     * Nama file unik per detik, dengan angka urut kalau nama yang sama sudah
     * dipakai (misal dua admin menekan tombol backup pada detik yang sama).
     */
    protected function namaFile(string $database): string
    {
        $dasar = sprintf(
            'backup-%s-%s.sql',
            Str::slug($database !== '' ? $database : 'database'),
            now()->format('Ymd-His')
        );

        $nama = $dasar;
        $urut = 1;

        while (Storage::disk($this->disk())->exists(config('backup.folder').'/'.$nama)) {
            $nama = Str::replaceLast('.sql', '-'.$urut.'.sql', $dasar);
            $urut++;
        }

        return $nama;
    }

    protected function rapikanCatatan(?string $catatan): ?string
    {
        $catatan = trim((string) $catatan);

        return $catatan === '' ? null : Str::limit($catatan, 191, '');
    }

    protected function disk(): string
    {
        return (string) config('backup.disk', 'local');
    }

    /**
     * Bagian atas file: catatan pembuatan dan pengaturan session.
     *
     * Perintahnya dibedakan per driver karena sintaks MySQL tidak berlaku di
     * SQLite, dan sebaliknya.
     */
    protected function header(string $database, string $driver, ?string $zonaWaktu): string
    {
        $baris = [
            '-- '.$this->namaAplikasi().' - backup database',
            '-- Database: '.$database.' ('.$driver.')',
            '-- Dibuat: '.now()->toDateTimeString(),
            '',
        ];

        if ($driver === 'sqlite') {
            $baris[] = 'PRAGMA foreign_keys = OFF;';
        } else {
            $baris[] = 'SET NAMES utf8mb4;';
            $baris[] = 'SET FOREIGN_KEY_CHECKS = 0;';
            $baris[] = "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';";
            // Disamakan dengan session saat pembacaan, supaya kolom TIMESTAMP
            // tidak bergeser satu jam atau lebih saat dipulihkan.
            $baris[] = "SET time_zone = '".$this->quoteLiteral($zonaWaktu ?? '+00:00')."';";
        }

        $baris[] = '';

        return implode("\n", $baris)."\n";
    }

    protected function footer(string $driver, ?string $zonaWaktu): string
    {
        $baris = ['', '-- Selesai: '.now()->toDateTimeString(), ''];

        if ($driver === 'sqlite') {
            $baris[] = 'PRAGMA foreign_keys = ON;';
        } else {
            $baris[] = 'SET FOREIGN_KEY_CHECKS = 1;';
            $baris[] = "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';";
            $baris[] = "SET time_zone = '".$this->quoteLiteral($zonaWaktu ?? '+00:00')."';";
        }

        $baris[] = '';

        return implode("\n", $baris)."\n";
    }

    /** Lindungi tanda kutip tunggal di dalam literal SQL. */
    protected function quoteLiteral(string $nilai): string
    {
        return str_replace("'", "''", $nilai);
    }

    protected function namaAplikasi(): string
    {
        return (string) config('backup.application', 'Laravel');
    }
}
