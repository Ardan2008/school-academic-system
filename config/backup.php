<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disk Penyimpanan
    |--------------------------------------------------------------------------
    |
    | Backup disimpan di disk "local" (storage/app/private) supaya file .sql
    | tidak bisa diakses lewat URL publik. Jangan diarahkan ke disk "public"
    | karena isinya seluruh isi database sekolah.
    |
    */

    'disk' => env('BACKUP_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Folder Penyimpanan
    |--------------------------------------------------------------------------
    |
    | Folder di dalam disk di atas. Dipisah supaya file backup mudah dicari
    | dan tidak bercampur dengan berkas lain yang disimpan di disk lokal.
    |
    */

    'folder' => env('BACKUP_FOLDER', 'backups'),

    /*
    |--------------------------------------------------------------------------
    | Jumlah Backup yang Dipertahankan
    |--------------------------------------------------------------------------
    |
    | Saat backup baru berhasil dibuat, file dan riwayat yang paling lama
    | akan dihapus sampai jumlahnya tersisa sebanyak nilai ini. Nilai 0 berarti
    | tidak ada pembersihan otomatis, tapi ruang disk bisa cepat penuh.
    |
    */

    'keep' => (int) env('BACKUP_KEEP', 10),

    /*
    |--------------------------------------------------------------------------
    | Jumlah Baris per INSERT
    |--------------------------------------------------------------------------
    |
    | Dump ditulis per beberapa baris sekaligus supaya file tidak terlalu
    | banyak perintah INSERT. Makin besar makin cepat, tapi perintahnya jadi
    | lebih panjang.
    |
    */

    'rows_per_insert' => (int) env('BACKUP_ROWS_PER_INSERT', 200),

    /*
    |--------------------------------------------------------------------------
    | Batas Waktu Proses
    |--------------------------------------------------------------------------
    |
    | Lama kunci backup ditahan, dalam detik. Nilai ini jadi pagar proses
    | backup yang sedang berjalan, jadi harus lebih besar dari perkiraan durasi
    | backup paling lambat di server ini. Kalau terlalu kecil, kunci lepas
    | di tengah proses dan dua backup bisa berjalan bersamaan.
    |
    */

    'timeout' => (int) env('BACKUP_TIMEOUT', 600),

    /*
    |--------------------------------------------------------------------------
    | Lama Menunggu Kunci
    |--------------------------------------------------------------------------
    |
    | Berapa detik permintaan menunggu backup lain yang sedang berjalan sebelum
    | ditolak dengan pesan "backup lain sedang berjalan". Nilai 0 berarti
    | langsung ditolak tanpa menunggu.
    |
    */

    'lock_wait' => (int) env('BACKUP_LOCK_WAIT', 5),

    /*
    |--------------------------------------------------------------------------
    | Nama Aplikasi di Dalam File Backup
    |--------------------------------------------------------------------------
    */

    'application' => env('APP_NAME', 'Laravel'),

];
