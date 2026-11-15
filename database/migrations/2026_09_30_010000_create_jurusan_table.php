<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurusan', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 120);
            $table->string('status', 20)->default('aktif');
            $table->timestamps();
            $table->unique('nama');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurusan');
    }
};
