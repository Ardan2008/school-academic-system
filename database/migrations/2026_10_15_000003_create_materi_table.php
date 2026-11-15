<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materi', function (Blueprint $table) {
            $table->id();

            $table->string('kelas', 30);
            $table->string('jurusan', 120)->nullable();

            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->foreignId('guru_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->date('tanggal')->nullable();

            $table->timestamps();

            $table->index(['kelas', 'jurusan'], 'materi_kelas_jurusan_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materi');
    }
};
