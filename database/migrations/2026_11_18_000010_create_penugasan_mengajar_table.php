<?php

use App\Support\Kelas;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penugasan_mengajar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['guru_id', 'mata_pelajaran_id', 'kelas_id'], 'penugasan_guru_mapel_kelas_unik');
            $table->index(['guru_id', 'mata_pelajaran_id'], 'penugasan_guru_mapel_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('penugasan_diatur')->default(false)->after('mata_pelajaran');
        });

        $mapel = DB::table('mata_pelajaran')->get(['id', 'nama'])
            ->keyBy(fn ($item) => mb_strtolower(trim((string) $item->nama)));

        $kelas = DB::table('kelas')->get(['id', 'jurusan', 'kelas'])
            ->keyBy(fn ($item) => trim((string) $item->jurusan).'|'.Kelas::normalize((string) $item->kelas));

        DB::table('users')
            ->where('role', 'guru')
            ->whereNotNull('mata_pelajaran')
            ->orderBy('id')
            ->chunkById(100, function ($gurus) use ($mapel, $kelas) {
                foreach ($gurus as $guru) {
                    $mapelGuru = $mapel->get(mb_strtolower(trim((string) $guru->mata_pelajaran)));

                    if ($mapelGuru === null) {
                        continue;
                    }

                    $pasangan = collect();
                    $disimpan = false;

                    DB::table('jadwal')
                        ->where('guru_id', $guru->id)
                        ->where('mata_pelajaran_id', $mapelGuru->id)
                        ->get(['kelas', 'jurusan'])
                        ->each(function ($item) use ($pasangan) {
                            $pasangan->push([
                                'kelas' => $item->kelas,
                                'jurusan' => $item->jurusan,
                            ]);
                        });

                    $pasangan
                        ->unique(fn ($item) => trim((string) $item['jurusan']).'|'.Kelas::normalize((string) $item['kelas']))
                        ->each(function ($item) use ($kelas, $guru, $mapelGuru, &$disimpan) {
                            $kelasRombel = $kelas->get(
                                trim((string) $item['jurusan']).'|'.Kelas::normalize((string) $item['kelas'])
                            );

                            if ($kelasRombel === null) {
                                return;
                            }

                            if (DB::table('penugasan_mengajar')->insertOrIgnore([
                                'guru_id' => $guru->id,
                                'mata_pelajaran_id' => $mapelGuru->id,
                                'kelas_id' => $kelasRombel->id,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]) > 0) {
                                $disimpan = true;
                            }
                        });

                    if ($disimpan) {
                        DB::table('users')->where('id', $guru->id)->update(['penugasan_diatur' => true]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('penugasan_diatur');
        });

        Schema::dropIfExists('penugasan_mengajar');
    }
};
