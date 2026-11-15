<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengumuman', function (Blueprint $table) {
            $table->id();

            $table->string('judul');
            $table->text('isi');

            // Kosong berarti berlaku untuk semua kelas / semua jurusan.
            $table->string('kelas', 30)->nullable();
            $table->string('jurusan', 120)->nullable();

            $table->dateTime('publish_at')->nullable();
            $table->timestamps();

            $table->index('publish_at', 'pengumuman_publish_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengumuman');
    }
};
