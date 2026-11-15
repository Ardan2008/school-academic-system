<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Status akun siswa: Aktif / Nonaktif / Lulus.
 *
 * Sebelumnya status diturunkan dari boolean `is_active` (hanya Aktif/Nonaktif).
 * Kolom ini yang jadi sumber status untuk halaman Data Siswa.
 *
 * Kolom `is_active` tetap dipakai sistem login, jadi nilainya diselaraskan:
 * hanya akun berstatus "Aktif" yang boleh masuk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('Aktif')->after('is_active')->index();
        });

        // Akun lama: yang sebelumnya nonaktif tetap nonaktif, selain itu aktif.
        DB::table('users')
            ->where('is_active', false)
            ->update(['status' => 'Nonaktif']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
