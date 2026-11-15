<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sasaran penerima pengumuman: null berlaku untuk semua role, "guru" hanya
     * untuk guru, "siswa" hanya untuk siswa (masih dapat disaring per kelas dan
     * jurusan seperti sebelumnya).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('pengumuman', 'target_role')) {
            Schema::table('pengumuman', function (Blueprint $table) {
                $table->string('target_role', 20)->nullable()->after('jurusan');
            });
        }
    }

    public function down(): void
    {
        Schema::table('pengumuman', function (Blueprint $table) {
            $table->dropColumn('target_role');
        });
    }
};
