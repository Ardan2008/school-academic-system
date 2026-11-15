<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas', function (Blueprint $table) {
            $table->id();

            // Jurusan disimpan sebagai teks, sama seperti kolom users.jurusan,
            // supaya kelas bisa langsung dibandingkan dengan kelas siswa.
            $table->string('jurusan', 120);

            // Bentuknya sama dengan kolom users.kelas: "10" untuk satu tingkat
            // satu rombel, "10-1" untuk tingkat yang dibagi rombel.
            $table->string('kelas', 30);

            $table->foreignId('wali_kelas_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Satu kelas hanya boleh terdaftar sekali dalam satu jurusan.
            $table->unique(['jurusan', 'kelas']);
            $table->index('wali_kelas_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};
