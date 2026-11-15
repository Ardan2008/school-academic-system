<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\JadwalPelajaran;
use App\Models\Materi;
use App\Models\Nilai;
use App\Models\Tugas;
use App\Models\User;
use App\Services\InfoSiswa;
use App\Services\StatistikNilai;
use App\Support\Periode;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Halaman akademik siswa: Jadwal Pelajaran, Tugas & Materi, Rapor, dan Riwayat
 * Akademik.
 *
 * Semua dibaca dari data milik siswa yang login (jadwal dari rombel, nilai dari
 * tabel nilai), jadi yang tampil selalu sama dengan yang diatur admin/guru.
 * Siswa tanpa data melihat halaman kosong dengan keterangan, bukan data contoh.
 */
class AkademikController extends Controller
{
    public function __construct(
        private readonly StatistikNilai $statistik,
        private readonly InfoSiswa $infoSiswa,
    ) {}

    public function jadwal(Request $request): View
    {
        $siswa = $this->siswa($request);

        // Filter hari lewat query string; hanya nama hari sekolah yang diterima.
        $hariDipilih = trim((string) $request->query('hari', ''));
        $hariDipilih = in_array($hariDipilih, JadwalPelajaran::HARI, true) ? $hariDipilih : null;

        // Ringkasan & daftar hari selalu dari sepekan penuh, supaya kartu statistik
        // dan chip filter tidak ikut berubah saat satu hari sedang disaring.
        $semuaJadwal = $this->infoSiswa->jadwalMingguan($siswa);
        $semuaBaris = $semuaJadwal->flatten();

        $jamMulai = $semuaBaris->min(fn (JadwalPelajaran $j) => substr((string) $j->jam_mulai, 0, 5));
        $jamSelesai = $semuaBaris->max(fn (JadwalPelajaran $j) => substr((string) $j->jam_selesai, 0, 5));

        return view('dashboard.siswa.jadwal-pelajaran.index', [
            'siswa' => $siswa,
            'jadwal' => $hariDipilih !== null ? $semuaJadwal->only($hariDipilih) : $semuaJadwal,
            'hariDipilih' => $hariDipilih,
            'hariTersedia' => $semuaJadwal->keys(),
            'hariIni' => JadwalPelajaran::hariIni(),
            'ringkasan' => [
                'total_jam' => $semuaBaris->count(),
                'total_mapel' => $semuaBaris->pluck('mata_pelajaran_id')->filter()->unique()->count(),
                'hari_aktif' => $semuaJadwal->count(),
                'jam_belajar' => ($jamMulai !== null && $jamSelesai !== null) ? $jamMulai.'–'.$jamSelesai : null,
            ],
        ]);
    }

    public function tugasMateri(Request $request): View
    {
        $siswa = $this->siswa($request);

        // Tab dan filter status lewat query string; nilai tak dikenal diabaikan
        // supaya filter tidak bisa dikira-kirakan dari sisi klien.
        $tab = $request->query('tab') === 'materi' ? 'materi' : 'tugas';

        $status = trim((string) $request->query('status', ''));
        $status = in_array($status, ['menunggu', 'terlambat', 'dikumpulkan'], true) ? $status : null;

        // Tugas rombel siswa, masing-masing membawa status pengerjaannya sendiri.
        $semuaTugas = $this->infoSiswa->tugas($siswa);
        $statusTugas = $semuaTugas->mapWithKeys(function (Tugas $tugas): array {
            $status = $this->infoSiswa->statusTugas($tugas);
            $status['icon'] = match ($status['kode']) {
                'selesai' => 'lucide-circle-check',
                'terlambat' => 'lucide-alert-circle',
                default => 'lucide-clock',
            };

            return [$tugas->id => $status];
        });

        $semuaMateri = $this->infoSiswa->materi($siswa);

        $q = trim((string) $request->query('q', $request->query('search', '')));
        if ($q !== '') {
            $qLower = mb_strtolower($q);
            $semuaTugas = $semuaTugas->filter(function (Tugas $t) use ($qLower) {
                return str_contains(mb_strtolower($t->judul), $qLower)
                    || str_contains(mb_strtolower((string) $t->deskripsi), $qLower)
                    || str_contains(mb_strtolower((string) ($t->mataPelajaran?->nama ?? '')), $qLower);
            })->values();

            $semuaMateri = $semuaMateri->filter(function (Materi $m) use ($qLower) {
                return str_contains(mb_strtolower($m->judul), $qLower)
                    || str_contains(mb_strtolower((string) $m->deskripsi), $qLower)
                    || str_contains(mb_strtolower((string) ($m->mataPelajaran?->nama ?? '')), $qLower);
            })->values();
        }

        $hitungStatus = fn (string $kode): int => $semuaTugas
            ->filter(fn (Tugas $tugas): bool => ($statusTugas[$tugas->id]['kode'] ?? '') === $kode)
            ->count();

        return view('dashboard.siswa.tugas-materi.index', [
            'siswa' => $siswa,
            'tab' => $tab,
            'status' => $status,
            'q' => $q,
            'tugas' => $status !== null
                ? $semuaTugas->filter(fn (Tugas $tugas): bool => $statusTugas[$tugas->id]['kode'] === $status)->values()
                : $semuaTugas,
            'statusTugas' => $statusTugas,
            'materi' => $semuaMateri,
            'ringkasan' => [
                'total_tugas' => $semuaTugas->count(),
                'dikumpulkan' => $hitungStatus('selesai'),
                'menunggu' => $hitungStatus('menunggu'),
                'terlambat' => $hitungStatus('terlambat'),
                'total_materi' => $semuaMateri->count(),
            ],
        ]);
    }

    public function rapor(Request $request): View
    {
        $siswa = $this->siswa($request);
        $periode = $this->periode($request);

        $nilai = $this->statistik->nilaiSiswa($siswa)
            ->filter(fn (Nilai $n) => $n->tahun_ajaran === $periode['tahun_ajaran'] && $n->semester === $periode['semester'])
            ->values();

        $total = $nilai->count();
        $tuntas = $nilai->filter(fn (Nilai $n) => $n->tuntas())->count();

        // Periode yang diminta lewat URL tetap harus muncul di select, walau
        // belum pernah dipakai pada data nilai mana pun. Tanpa ini, halaman
        // menampilkan periode lain sementara pilihan form menunjuk periode lain.
        $tahunAjarans = $this->statistik->tahunAjaranTersedia();

        if (! in_array($periode['tahun_ajaran'], $tahunAjarans, true)) {
            $tahunAjarans[] = $periode['tahun_ajaran'];
            usort(
                $tahunAjarans,
                fn (string $a, string $b): int => Periode::urutan($b, Periode::SEMESTER_GANJIL)
                    <=> Periode::urutan($a, Periode::SEMESTER_GANJIL)
            );
        }

        return view('dashboard.siswa.rapor.index', [
            'siswa' => $siswa,
            'periode' => $periode,
            'periodeLabel' => Periode::label($periode['tahun_ajaran'], $periode['semester']),
            'semesters' => Periode::SEMESTERS,
            'tahunAjarans' => $tahunAjarans,
            'nilai' => $nilai,
            'ringkasan' => [
                'jumlah_mapel' => $total,
                'rata_rata' => $total > 0 ? round((float) $nilai->avg(fn (Nilai $n) => (float) $n->nilai), 2) : null,
                'tuntas' => $tuntas,
                'belum_tuntas' => $total - $tuntas,
                'persen_tuntas' => $total > 0 ? (int) round($tuntas / $total * 100) : null,
            ],
        ]);
    }

    public function riwayat(Request $request): View
    {
        $siswa = $this->siswa($request);

        $semuaPeriode = $this->statistik->rekapSiswaPerPeriode($siswa);

        // Filter periode lewat query string; hanya label yang benar-benar dimiliki
        // siswa yang diterima, supaya filter tidak bisa dikira-kirakan dari klien.
        $labelTersedia = $semuaPeriode->pluck('label')->all();
        $labelDipilih = trim((string) $request->query('periode', ''));
        $labelDipilih = in_array($labelDipilih, $labelTersedia, true) ? $labelDipilih : null;

        // Statistik ringkas selalu dari seluruh periode, supaya kartu dan chip
        // tidak ikut berubah saat satu periode sedang disaring.
        $semuaNilai = $semuaPeriode->flatMap(fn (array $periode) => $periode['nilai']);
        $totalNilai = $semuaNilai->count();
        $totalTuntas = $semuaNilai->filter(fn (array $baris) => $baris['tuntas'])->count();

        // Periode kronologis (lama ke baru) untuk menghitung selisih rata-rata
        // terhadap periode sebelumnya sekaligus menyusun grafik perkembangan.
        $sebelumnya = null;

        $periodeLengkap = $semuaPeriode->reverse()->values()->map(function (array $periode) use (&$sebelumnya): array {
            $periode['jumlah_mapel'] = count($periode['nilai']);
            $periode['tuntas'] = count(array_filter($periode['nilai'], fn (array $baris) => $baris['tuntas']));
            $periode['belum_tuntas'] = $periode['jumlah_mapel'] - $periode['tuntas'];
            $periode['selisih'] = $sebelumnya === null ? null : round($periode['rata_rata'] - $sebelumnya, 1);
            $sebelumnya = $periode['rata_rata'];

            return $periode;
        });

        $batasBawah = $this->batasBawahGrafik($periodeLengkap->pluck('rata_rata'));

        $ditampilkan = $labelDipilih === null
            ? $periodeLengkap
            : $periodeLengkap->filter(fn (array $periode) => $periode['label'] === $labelDipilih);

        return view('dashboard.siswa.riwayat-akademik.index', [
            'siswa' => $siswa,
            'riwayat' => $ditampilkan->reverse()->values(),
            'periodeDipilih' => $labelDipilih,
            'periodeTersedia' => $labelTersedia,
            'grafik' => [
                'bars' => $periodeLengkap->map(fn (array $periode): array => [
                    'label' => $periode['label'],
                    'value' => $periode['rata_rata'],
                    'sub' => $periode['jumlah_mapel'].' mapel · '.$periode['tuntas'].' tuntas',
                    'details' => collect($periode['nilai'])
                        ->map(fn (array $baris): array => [
                            'label' => $baris['nama'],
                            'value' => (string) $baris['nilai'],
                            'weight' => $baris['nilai'],
                        ])
                        ->all(),
                ])->all(),
                'min' => $batasBawah,
                'ticks' => range(100, $batasBawah, -10),
            ],
            'ringkasan' => [
                'total_periode' => $semuaPeriode->count(),
                'rata_rata' => $totalNilai > 0
                    ? round((float) $semuaNilai->avg(fn (array $baris) => (float) $baris['nilai']), 1)
                    : null,
                'total_mapel' => $semuaNilai->pluck('nama')->unique()->count(),
                'total_nilai' => $totalNilai,
                'tuntas' => $totalTuntas,
                'persen_tuntas' => $totalNilai > 0 ? (int) round($totalTuntas / $totalNilai * 100) : null,
            ],
        ]);
    }

    /**
     * Batas bawah sumbu Y grafik riwayat: dibulatkan ke puluhan di bawah nilai
     * rata-rata terendah, sehingga batang terendah tetap terlihat (tidak 0%) dan
     * batang tertinggi tidak melebihi 100.
     *
     * @param  Collection<int, float>  $rataRata
     */
    protected function batasBawahGrafik(Collection $rataRata): int
    {
        $terendah = $rataRata->filter(fn ($nilai) => $nilai !== null)->min();

        if ($terendah === null) {
            return 60;
        }

        return (int) max(0, min(90, (int) (floor((float) $terendah / 10) * 10) - 10));
    }

    protected function siswa(Request $request): User
    {
        $siswa = $request->user();

        if (! $siswa instanceof User || $siswa->role !== 'siswa') {
            abort(403);
        }

        return $siswa;
    }

    /**
     * @return array{tahun_ajaran: string, semester: string}
     */
    protected function periode(Request $request): array
    {
        $semester = trim((string) $request->query('semester', ''));
        $tahunAjaran = trim((string) $request->query('tahun_ajaran', ''));

        if (! Periode::semesterValid($semester)) {
            $semester = Periode::semesterSekarang();
        }

        if (! Periode::tahunAjaranValid($tahunAjaran)) {
            $tahunAjaran = Periode::tahunAjaranSekarang();
        }

        return [
            'tahun_ajaran' => $tahunAjaran,
            'semester' => $semester,
        ];
    }
}
