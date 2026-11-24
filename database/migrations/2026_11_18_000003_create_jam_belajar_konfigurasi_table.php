<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jam_belajar_konfigurasi', function (Blueprint $table): void {
            $table->id();
            $table->time('jam_masuk');
            $table->time('jam_selesai');
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('sesi_jam_belajar', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('jam_belajar_konfigurasi_id')
                ->constrained('jam_belajar_konfigurasi')
                ->cascadeOnDelete();
            $table->string('nama', 60);
            $table->string('jenis', 20)->default('pelajaran');
            $table->unsignedTinyInteger('urutan');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->timestamps();

            $table->unique(['jam_belajar_konfigurasi_id', 'urutan'], 'sesi_jam_urutan_unik');
            $table->unique(['jam_belajar_konfigurasi_id', 'jam_mulai'], 'sesi_jam_mulai_unik');
            $table->index(['jam_belajar_konfigurasi_id', 'jenis', 'urutan'], 'sesi_jam_jenis_urutan_index');
        });

        DB::table('jam_belajar_konfigurasi')->insert([
            'jam_masuk' => '06:45',
            'jam_selesai' => '15:00',
            'keterangan' => 'Jam operasional belajar mengajar sekolah.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $konfigurasiId = (int) DB::table('jam_belajar_konfigurasi')->value('id');
        $waktu = now();

        $sesi = [
            ['Jam Pelajaran 1', 'pelajaran', 1, '06:45', '07:30'],
            ['Jam Pelajaran 2', 'pelajaran', 2, '07:30', '08:15'],
            ['Jam Pelajaran 3', 'pelajaran', 3, '08:15', '09:00'],
            ['Istirahat', 'istirahat', 4, '09:00', '09:30'],
            ['Jam Pelajaran 4', 'pelajaran', 5, '09:30', '10:15'],
            ['Jam Pelajaran 5', 'pelajaran', 6, '10:15', '11:00'],
        ];

        foreach ($sesi as [$nama, $jenis, $urutan, $mulai, $selesai]) {
            DB::table('sesi_jam_belajar')->insert([
                'jam_belajar_konfigurasi_id' => $konfigurasiId,
                'nama' => $nama,
                'jenis' => $jenis,
                'urutan' => $urutan,
                'jam_mulai' => $mulai,
                'jam_selesai' => $selesai,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sesi_jam_belajar');
        Schema::dropIfExists('jam_belajar_konfigurasi');
    }
};
