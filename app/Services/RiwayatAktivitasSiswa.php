<?php

namespace App\Services;

use App\Models\JadwalPelajaran;
use App\Models\PengumpulanTugas;
use App\Models\Pengumuman;
use App\Models\User;
use App\Support\Waktu;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Riwayat aktivitas akademik satu siswa.
 *
 * Sumbernya baris data nyata yang sudah dipakai halaman lain: tugas dari
 * rombel, pengumpulan milik siswa, nilai milik siswa, jadwal rombel,
 * pengumuman yang menyasar siswa, dan materi rombel. Tidak ada tabel atau
 * pencatatan baru supaya riwayat tidak menduplikasi data yang sudah ada.
 */
class RiwayatAktivitasSiswa
{
    public const KATEGORI = [
        'tugas' => 'Tugas',
        'nilai' => 'Nilai',
        'jadwal' => 'Jadwal',
        'pengumuman' => 'Pengumuman',
        'materi' => 'Materi',
    ];

    public function __construct(
        private readonly InfoSiswa $infoSiswa,
        private readonly StatistikNilai $statistik,
    ) {}

    /**
     * Kumpulkan seluruh aktivitas siswa dari semua kategori.
     *
     * @return Collection<int, array{judul: string, deskripsi: string, kategori: string, waktu: Carbon}>
     */
    public function kumpulkan(User $siswa): Collection
    {
        $aktivitas = collect();

        foreach ($this->infoSiswa->tugas($siswa) as $tugas) {
            $mapel = $tugas->mataPelajaran?->nama ?? 'Mata pelajaran';
            $tenggat = $tugas->deadline !== null
                ? 'Tenggat '.Waktu::lengkap($tugas->deadline)
                : 'Tanpa tenggat';

            $aktivitas->push([
                'judul' => 'Tugas Baru: '.$tugas->judul,
                'deskripsi' => $this->gabung($mapel, $tenggat, (string) $tugas->deskripsi),
                'kategori' => 'tugas',
                'waktu' => $this->waktu($tugas->created_at),
            ]);
        }

        foreach ($this->infoSiswa->pengumpulan($siswa) as $pengumpulan) {
            $tugas = $pengumpulan->tugas;

            if ($tugas === null) {
                continue;
            }

            $mapel = $tugas->mataPelajaran?->nama ?? 'Mata pelajaran';
            $status = $pengumpulan->status === PengumpulanTugas::STATUS_DINILAI
                ? 'sudah dinilai'
                : 'menunggu penilaian';

            $aktivitas->push([
                'judul' => 'Mengumpulkan: '.$tugas->judul,
                'deskripsi' => 'Anda mengumpulkan tugas '.$mapel.' · '.$status,
                'kategori' => 'tugas',
                'waktu' => $this->waktu($pengumpulan->dikumpulkan_at ?? $pengumpulan->created_at),
            ]);
        }

        foreach ($this->statistik->nilaiSiswa($siswa) as $nilai) {
            $mapel = $nilai->mataPelajaran?->nama ?? 'Mata pelajaran';

            $aktivitas->push([
                'judul' => 'Nilai '.$mapel,
                'deskripsi' => 'Nilai '.$this->angka($nilai->nilai)
                    .' (KKM '.$this->angka($nilai->kkm).')'
                    .' · Semester '.$nilai->semester.' '.$nilai->tahun_ajaran,
                'kategori' => 'nilai',
                'waktu' => $this->waktu($nilai->updated_at ?? $nilai->created_at),
            ]);
        }

        $jadwal = JadwalPelajaran::query()
            ->untukRombel($siswa->kelas, $siswa->jurusan)
            ->with(['mataPelajaran', 'guru'])
            ->get();

        foreach ($jadwal as $baris) {
            $mapel = $baris->mataPelajaran?->nama ?? 'Mata pelajaran';
            $deskripsi = $baris->hari.' '.$baris->jamLabel();

            if (filled($baris->ruang)) {
                $deskripsi .= ' · Ruang '.$baris->ruang;
            }

            if ($baris->guru !== null) {
                $deskripsi .= ' · '.$baris->guru->name;
            }

            $aktivitas->push([
                'judul' => 'Jadwal '.$mapel,
                'deskripsi' => $deskripsi,
                'kategori' => 'jadwal',
                'waktu' => $this->waktu($baris->updated_at ?? $baris->created_at),
            ]);
        }

        foreach (Pengumuman::query()->untukSiswa($siswa)->get() as $pengumuman) {
            $isi = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $pengumuman->isi)));

            $aktivitas->push([
                'judul' => $pengumuman->judul,
                'deskripsi' => $isi !== '' ? $isi : 'Pengumuman sekolah.',
                'kategori' => 'pengumuman',
                'waktu' => $this->waktu($pengumuman->publish_at ?? $pengumuman->created_at),
            ]);
        }

        foreach ($this->infoSiswa->materi($siswa) as $materi) {
            $mapel = $materi->mataPelajaran?->nama ?? 'Mata pelajaran';

            $aktivitas->push([
                'judul' => 'Materi: '.$materi->judul,
                'deskripsi' => $this->gabung($mapel, (string) $materi->deskripsi, $materi->tanggal !== null ? Waktu::tanggal($materi->tanggal) : ''),
                'kategori' => 'materi',
                'waktu' => $this->waktu($materi->created_at),
            ]);
        }

        return $aktivitas;
    }

    /**
     * Terapkan filter kategori, pencarian judul/deskripsi, dan rentang tanggal.
     * Hasil selalu diurutkan dari waktu kejadian terbaru.
     *
     * @param  Collection<int, array{judul: string, deskripsi: string, kategori: string, waktu: Carbon}>  $aktivitas
     * @return Collection<int, array{judul: string, deskripsi: string, kategori: string, waktu: Carbon}>
     */
    public function saring(
        Collection $aktivitas,
        string $kategori = '',
        string $search = '',
        ?string $dari = null,
        ?string $sampai = null,
    ): Collection {
        if ($kategori !== '' && array_key_exists($kategori, self::KATEGORI)) {
            $aktivitas = $aktivitas->where('kategori', $kategori);
        }

        $search = trim($search);

        if ($search !== '') {
            $needle = mb_strtolower($search);

            $aktivitas = $aktivitas->filter(function (array $item) use ($needle): bool {
                return str_contains(mb_strtolower($item['judul']), $needle)
                    || str_contains(mb_strtolower($item['deskripsi']), $needle);
            });
        }

        if ($dari !== null || $sampai !== null) {
            $aktivitas = $aktivitas->filter(function (array $item) use ($dari, $sampai): bool {
                $tanggal = Waktu::zona($item['waktu'])?->toDateString();

                if ($tanggal === null) {
                    return false;
                }

                if ($dari !== null && $tanggal < $dari) {
                    return false;
                }

                if ($sampai !== null && $tanggal > $sampai) {
                    return false;
                }

                return true;
            });
        }

        return $aktivitas
            ->sortByDesc(fn (array $item): int => $item['waktu']->getTimestamp())
            ->values();
    }

    protected function waktu(?Carbon $waktu): Carbon
    {
        return $waktu ?? now();
    }

    protected function gabung(string ...$bagian): string
    {
        return collect($bagian)
            ->map(fn (string $teks): string => trim($teks))
            ->filter(fn (string $teks): bool => $teks !== '')
            ->implode(' · ');
    }

    protected function angka(float|int|string|null $angka): string
    {
        return rtrim(rtrim(number_format((float) $angka, 2, '.', ''), '0'), '.');
    }
}
