<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal', function (Blueprint $table) {
            $table->id();

            // Kelas & jurusan disimpan apa adanya seperti di users dan nilai,
            // supaya jadwal tahun ini tidak ikut berubah saat jurusan ditutup.
            $table->string('kelas', 30);
            $table->string('jurusan', 120)->nullable();

            $table->string('hari', 10);
            $table->time('jam_mulai');
            $table->time('jam_selesai');

            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->foreignId('guru_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('ruang', 60)->nullable();
            $table->timestamps();

            $table->index(['kelas', 'jurusan'], 'jadwal_kelas_jurusan_index');
            $table->index('hari', 'jadwal_hari_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal');
    }
};
