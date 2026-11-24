<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tugas', function (Blueprint $table) {
            $table->string('file_path', 255)->nullable()->after('deskripsi');
            $table->string('nama_file', 255)->nullable()->after('file_path');
            $table->string('mime_type', 120)->nullable()->after('nama_file');
            $table->string('ekstensi', 20)->nullable()->after('mime_type');
            $table->unsignedBigInteger('ukuran_bytes')->nullable()->after('ekstensi');
        });
    }

    public function down(): void
    {
        Schema::table('tugas', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'nama_file', 'mime_type', 'ekstensi', 'ukuran_bytes']);
        });
    }
};
