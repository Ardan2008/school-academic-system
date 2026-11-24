<?php

use App\Support\Periode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal', function (Blueprint $table): void {
            $table->string('tahun_ajaran', 9)->nullable()->after('jurusan');
            $table->string('semester', 10)->nullable()->after('tahun_ajaran');
            $table->index(['tahun_ajaran', 'semester', 'hari', 'jam_mulai'], 'jadwal_periode_hari_jam_index');
        });

        $periode = Periode::periodeSekarang();

        DB::table('jadwal')->whereNull('tahun_ajaran')->update(['tahun_ajaran' => $periode['tahun_ajaran']]);
        DB::table('jadwal')->whereNull('semester')->update(['semester' => $periode['semester']]);
    }

    public function down(): void
    {
        Schema::table('jadwal', function (Blueprint $table): void {
            $table->dropIndex('jadwal_periode_hari_jam_index');
            $table->dropColumn(['tahun_ajaran', 'semester']);
        });
    }
};
