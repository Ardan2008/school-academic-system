<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengumpulan_tugas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tugas_id')->constrained('tugas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->dateTime('dikumpulkan_at')->nullable();
            $table->string('status', 20)->default('dikumpulkan');
            $table->decimal('nilai', 5, 2)->nullable();

            $table->timestamps();

            // Satu siswa satu pengumpulan per tugas, supaya mengumpulkan ulang
            // memperbarui baris yang sama.
            $table->unique(['tugas_id', 'user_id'], 'pengumpulan_tugas_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengumpulan_tugas');
    }
};
