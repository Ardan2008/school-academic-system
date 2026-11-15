<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom profil yang bisa diedit dari halaman profil
     * (fungsi Edit Profil). Khusus guru: nip & mata_pelajaran;
     * khusus siswa: nis & jurusan.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nip')->nullable()->after('kelas');
            $table->string('nis')->nullable()->after('nip');
            $table->string('jurusan')->nullable()->after('nis');
            $table->string('mata_pelajaran')->nullable()->after('jurusan');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nip', 'nis', 'jurusan', 'mata_pelajaran']);
        });
    }
};
