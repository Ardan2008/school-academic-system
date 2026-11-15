<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_database', function (Blueprint $table) {
            $table->id();

            // Nama file disimpan lengkap dengan foldernya (misal
            // "backups/backup-sistem-akademik-sekolah-20261001-143904.sql")
            // supaya tidak perlu menebak lokasi file lagi saat diunduh atau
            // dihapus.
            $table->string('nama_file');

            $table->string('driver', 20);
            $table->string('database', 100);

            $table->unsignedBigInteger('ukuran_bytes')->default(0);
            $table->unsignedInteger('jumlah_tabel')->default(0);
            $table->unsignedInteger('durasi_detik')->default(0);

            // Catatan singkat dari admin, misalnya "sebelum ubah rapor".
            $table->string('catatan', 191)->nullable();

            // Akun admin yang membuat file ini. File backup tidak ikut terhapus
            // kalau akunnya dihapus, supaya riwayat pencadangan tetap utuh.
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Halaman selalu menampilkan backup terbaru lebih dulu.
            $table->index('created_at', 'backup_database_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_database');
    }
};
