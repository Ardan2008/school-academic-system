<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nilai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();

            // Kelas dan jurusan disimpan ulang saat nilai dicatat, bukan diambil
            // dari users. Nilai adalah riwayat, jadi kelas yang sudah kosong
            // tahun lalu tetap harus bisa dibaca apa adanya ketika siswa sudah
            // naik kelas sekarang.
            $table->string('kelas', 30);
            $table->string('jurusan', 120);

            $table->string('tahun_ajaran', 9);
            $table->string('semester', 10);

            $table->decimal('nilai', 5, 2);
            $table->decimal('kkm', 5, 2);

            $table->timestamps();

            // Satu siswa satu nilai per mata pelajaran per periode, supaya
            // input ulang dari guru memperbarui baris yang sama, bukan
            // menambah baris ganda.
            $table->unique(
                ['user_id', 'mata_pelajaran_id', 'tahun_ajaran', 'semester'],
                'nilai_siswa_mapel_periode_unik'
            );

            // Filter Laporan Akademik selalu memotong periode, dan pengelompokan
            // per kelas memakai pasangan kelas + jurusan karena kelas "12-1"
            // dipakai di lebih dari satu jurusan.
            $table->index(['tahun_ajaran', 'semester'], 'nilai_periode_index');
            $table->index(['kelas', 'jurusan'], 'nilai_kelas_jurusan_index');
            $table->index('mata_pelajaran_id', 'nilai_mata_pelajaran_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai');
    }
};
