<?php

namespace Database\Seeders;

use App\Models\PengumpulanTugas;
use App\Models\Tugas;
use App\Models\User;
use Database\Seeders\Concerns\Mengundi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Catatan pengumpulan tugas tiap siswa, supaya kolom status pada halaman
 * Tugas & Materi tidak selalu menunjukkan "Menunggu" untuk semua baris.
 *
 * Pengumpulan dibuat tetap: siswa mana yang sudah mengumpulkan ditentukan
 * undian berbasis NIS dan id tugas, jadi `db:seed` dua kali menghasilkan pola
 * yang sama. Tugas yang tenggatnya sudah lewat dianggap sudah dikumpulkan dan
 * dinilai sekaligus.
 *
 * Harus dijalankan setelah TugasSeeder dan SiswaSeeder.
 */
class PengumpulanTugasSeeder extends Seeder
{
    use Mengundi;

    /** Persentase siswa yang mengumpulkan tugas. */
    protected const PERSEN_KUMPUL = 65;

    /** Selisih nilai terhadap KKM mata pelajaran, supaya tidak semua seragam. */
    protected const SELISIH_NILAI = [5, 15];

    public function run(): void
    {
        $tugas = Tugas::query()->orderBy('id')->get();

        if ($tugas->isEmpty()) {
            $this->command?->warn('Pengumpulan dilewati: tugas belum ada.');

            return;
        }

        $total = 0;
        $baru = 0;

        foreach ($tugas as $baris) {
            foreach ($this->siswaTugas($baris) as $siswa) {
                if ($this->undian('pengumpulan|'.$siswa->nis.'|'.$baris->id, 0, 100) >= self::PERSEN_KUMPUL) {
                    continue;
                }

                $lewat = $baris->deadline !== null && $baris->deadline->isPast();

                $pengumpulan = PengumpulanTugas::updateOrCreate(
                    ['tugas_id' => $baris->id, 'user_id' => $siswa->id],
                    [
                        'dikumpulkan_at' => $this->waktuKumpul($baris),
                        'status' => $lewat ? PengumpulanTugas::STATUS_DINILAI : PengumpulanTugas::STATUS_DIKUMPULKAN,
                        'nilai' => $lewat ? $this->nilai($siswa, $baris) : null,
                    ]
                );

                if ($pengumpulan->wasRecentlyCreated) {
                    $baru++;
                }

                $total++;
            }
        }

        $this->command?->newLine();
        $this->command?->info('Pengumpulan tugas: '.$total.' baris.');
    }

    /** Siswa yang relevan dengan satu tugas: satu rombel kalau tugas bernuansa jurusan. */
    protected function siswaTugas(Tugas $tugas)
    {
        return User::query()
            ->where('role', 'siswa')
            ->where('status', User::STATUS_AKTIF)
            ->where('kelas', $tugas->kelas)
            ->when($tugas->jurusan !== null && $tugas->jurusan !== '', fn ($query) => $query->where('jurusan', $tugas->jurusan))
            ->whereNotNull('nis')
            ->orderBy('nis')
            ->get();
    }

    /** Waktu pengumpulan: menjelang tenggat kalau ada, satu hari lalu kalau tidak. */
    protected function waktuKumpul(Tugas $tugas): Carbon
    {
        if ($tugas->deadline === null) {
            return now()->subDay();
        }

        $mendekat = $tugas->deadline->copy()->subHours($this->undianInt('kumpul|'.$tugas->id, 1, 20));

        // Tugas yang sudah lewat jangan dikumpulkan sesudah tenggatnya.
        return $mendekat->isFuture() ? $mendekat->subDays(1) : $mendekat;
    }

    protected function nilai(User $siswa, Tugas $tugas): float
    {
        $dasar = 78.0 + $this->undian('nilai-tugas|'.$siswa->nis.'|'.$tugas->id, self::SELISIH_NILAI[0], self::SELISIH_NILAI[1]);

        return round(min(100.0, $dasar), 1);
    }
}
