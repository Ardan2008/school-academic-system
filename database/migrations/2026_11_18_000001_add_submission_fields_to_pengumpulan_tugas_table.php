<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengumpulan_tugas', function (Blueprint $table) {
            $table->text('jawaban')->nullable()->after('dikumpulkan_at');
            $table->text('umpan_balik')->nullable()->after('nilai');
        });
    }

    public function down(): void
    {
        Schema::table('pengumpulan_tugas', function (Blueprint $table) {
            $table->dropColumn(['jawaban', 'umpan_balik']);
        });
    }
};
