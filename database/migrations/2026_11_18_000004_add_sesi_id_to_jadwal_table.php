<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('jadwal', 'sesi_id')) {
            Schema::table('jadwal', function (Blueprint $table): void {
                $table->foreignId('sesi_id')
                    ->nullable()
                    ->after('ruang')
                    ->constrained('sesi_jam_belajar')
                    ->nullOnDelete();
            });
        }

        $konfigurasiId = (int) DB::table('jam_belajar_konfigurasi')->value('id');

        if ($konfigurasiId === 0) {
            return;
        }

        DB::table('jadwal')
            ->select('jam_mulai', 'jam_selesai')
            ->distinct()
            ->orderBy('jam_mulai')
            ->get()
            ->each(function (object $baris) use ($konfigurasiId): void {
                $mulai = substr((string) $baris->jam_mulai, 0, 5);
                $selesai = substr((string) $baris->jam_selesai, 0, 5);

                $sesi = DB::table('sesi_jam_belajar')
                    ->where('jam_belajar_konfigurasi_id', $konfigurasiId)
                    ->where('jam_mulai', $mulai)
                    ->first();

                if ($sesi === null) {
                    $urutan = (int) DB::table('sesi_jam_belajar')
                        ->where('jam_belajar_konfigurasi_id', $konfigurasiId)
                        ->max('urutan') + 1;

                    $sesiId = DB::table('sesi_jam_belajar')->insertGetId([
                        'jam_belajar_konfigurasi_id' => $konfigurasiId,
                        'nama' => 'Sesi Tersimpan',
                        'jenis' => 'pelajaran',
                        'urutan' => $urutan,
                        'jam_mulai' => $mulai,
                        'jam_selesai' => $selesai,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $sesiId = (int) $sesi->id;
                }

                DB::table('jadwal')
                    ->whereRaw('substr(jam_mulai, 1, 5) = ?', [$mulai])
                    ->whereRaw('substr(jam_selesai, 1, 5) = ?', [$selesai])
                    ->update(['sesi_id' => $sesiId]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('jadwal', 'sesi_id')) {
            Schema::table('jadwal', function (Blueprint $table): void {
                $table->dropForeign(['sesi_id']);
                $table->dropColumn('sesi_id');
            });
        }
    }
};
