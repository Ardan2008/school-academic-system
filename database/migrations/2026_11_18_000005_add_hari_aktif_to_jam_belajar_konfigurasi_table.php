<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('jam_belajar_konfigurasi', 'hari_aktif')) {
            Schema::table('jam_belajar_konfigurasi', function (Blueprint $table): void {
                $table->json('hari_aktif')->nullable()->after('keterangan');
            });
        }

        DB::table('jam_belajar_konfigurasi')
            ->whereNull('hari_aktif')
            ->update([
                'hari_aktif' => json_encode(
                    ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
                    JSON_UNESCAPED_UNICODE
                ),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('jam_belajar_konfigurasi', 'hari_aktif')) {
            Schema::table('jam_belajar_konfigurasi', function (Blueprint $table): void {
                $table->dropColumn('hari_aktif');
            });
        }
    }
};
