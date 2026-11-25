<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Concerns\MelayaniFileMateri;
use App\Http\Controllers\Controller;
use App\Models\JadwalPelajaran;
use App\Models\Materi;
use App\Models\Nilai;
use App\Models\PengumpulanTugas;
use App\Models\Tugas;
use App\Models\User;
use App\Services\InfoSiswa;
use App\Services\JamBelajar;
use App\Services\StatistikNilai;
use App\Support\PerbandinganData;
use App\Support\Periode;
use App\Support\Waktu;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
    use MelayaniFileMateri;

    public function __construct(
        private readonly StatistikNilai $statistik,
        private readonly InfoSiswa $infoSiswa,
        private readonly JamBelajar $jamBelajar,
    ) {}

    public function jadwal(Request $request): View
    {
        $siswa = $this->siswa($request);
        $konfigurasiJam = $this->jamBelajar->konfigurasi();
        $hariAktif = $this->jamBelajar->hariAktif($konfigurasiJam);

        // Filter hari lewat query string; hanya nama hari sekolah yang diterima.
        $hariDipilih = trim((string) $request->query('hari', ''));
        $hariDipilih = in_array($hariDipilih, $hariAktif, true) ? $hariDipilih : null;

        // Ringkasan & daftar hari selalu dari sepekan penuh, supaya kartu statistik
        // dan chip filter tidak ikut berubah saat satu hari sedang disaring.
        $semuaJadwalMentah = $this->infoSiswa->jadwalMingguan($siswa);
        $jadwalNonaktif = $semuaJadwalMentah->filter(
            fn ($items, string $namaHari) => ! in_array($namaHari, $hariAktif, true)
        );
        $semuaJadwal = $semuaJadwalMentah->filter(
            fn ($items, string $namaHari) => in_array($namaHari, $hariAktif, true)
        );
        $semuaBaris = $semuaJadwal->flatten();
        $jadwalPerluPerhatian = $semuaJadwalMentah->flatten()
            ->filter(fn (JadwalPelajaran $baris) => $baris->infoWaktu($konfigurasiJam)['catatan'] !== [])
            ->values();

        return view('dashboard.siswa.jadwal-pelajaran.index', [
            'siswa' => $siswa,
            'jadwal' => $hariDipilih !== null ? $semuaJadwal->only($hariDipilih) : $semuaJadwal,
            'hariDipilih' => $hariDipilih,
            'hariTersedia' => $semuaJadwal->keys(),
            'hariIni' => JadwalPelajaran::hariIni(),
            'periode' => Periode::periodeSekarang(),
            'konfigurasiJam' => $konfigurasiJam,
            'hariAktif' => $hariAktif,
            'jadwalNonaktif' => $jadwalNonaktif,
            'jadwalPerluPerhatian' => $jadwalPerluPerhatian,
            'ringkasan' => [
                'total_jam' => $semuaBaris->count(),
                'total_mapel' => $semuaBaris->pluck('mata_pelajaran_id')->filter()->unique()->count(),
                'hari_aktif' => $semuaJadwal->count(),
                'jam_belajar' => $konfigurasiJam->jamMasukLabel().'–'.$konfigurasiJam->jamSelesaiLabel(),
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
                'dikumpulkan' => 'lucide-circle-check',
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
                'dikumpulkan' => $hitungStatus('dikumpulkan'),
                'menunggu' => $hitungStatus('menunggu'),
                'terlambat' => $hitungStatus('terlambat'),
                'total_materi' => $semuaMateri->count(),
            ],
        ]);
    }

    public function detailTugas(Request $request, Tugas $tugas): JsonResponse
    {
        $siswa = $this->siswa($request);
        $this->pastikanTugasUntukSiswa($tugas, $siswa);

        $tugas->load([
            'mataPelajaran',
            'guru',
            'pengumpulan' => fn ($query) => $query->where('user_id', $siswa->id),
        ]);

        $pengumpulan = $tugas->pengumpulan->first();
        $status = $this->infoSiswa->statusTugas($tugas);
        $jurusan = trim((string) $tugas->jurusan);
        $memilikiFile = $tugas->file_path !== null;

        return response()->json([
            'detail' => [
                'id' => $tugas->id,
                'judul' => $tugas->judul,
                'action' => route('dashboard.siswa.tugas-materi.pengumpulan.store', $tugas),
                'mata_pelajaran' => $tugas->mataPelajaran?->nama ?? '-',
                'guru' => $tugas->guru?->name ?? 'Belum ada guru pengampu',
                'kelas' => 'Kelas '.$tugas->kelas.($jurusan !== '' ? ' - '.$jurusan : ''),
                'deskripsi' => $tugas->deskripsi,
                'tanggal_diberikan' => $tugas->created_at !== null ? Waktu::tanggalJam($tugas->created_at) : '-',
                'tenggat' => $tugas->deadline !== null ? Waktu::tanggalJam($tugas->deadline) : 'Tanpa tenggat',
                'label_tenggat' => $tugas->labelDeadline(),
                'status' => $status['label'],
                'status_kode' => $status['kode'],
                'status_class' => $status['class'],
                'sudah_dikumpulkan' => $pengumpulan !== null,
                'waktu_pengumpulan' => $pengumpulan?->dikumpulkan_at !== null
                    ? Waktu::tanggalJam($pengumpulan->dikumpulkan_at)
                    : null,
                'jawaban' => $pengumpulan?->jawaban,
                'nilai' => $pengumpulan?->nilai !== null
                    ? rtrim(rtrim(number_format((float) $pengumpulan->nilai, 2, ',', '.'), '0'), ',')
                    : null,
                'umpan_balik' => $pengumpulan?->umpan_balik,
                'lampiran' => $tugas->nama_file,
                'lampiran_format' => $tugas->format_file,
                'lampiran_ukuran' => $tugas->ukuran_file,
                'lampiran_url' => $memilikiFile
                    ? route('dashboard.siswa.tugas-materi.tugas.unduh', $tugas)
                    : null,
                'lampiran_preview_url' => $memilikiFile && $tugas->bisa_dipratinjau
                    ? route('dashboard.siswa.tugas-materi.tugas.preview', $tugas)
                    : null,
            ],
        ]);
    }

    public function detailMateri(Request $request, Materi $materi): JsonResponse
    {
        $siswa = $this->siswa($request);
        $this->pastikanMateriUntukSiswa($materi, $siswa);

        $materi->load(['mataPelajaran', 'guru']);
        $memilikiFile = $materi->file_path !== null;

        return response()->json([
            'detail' => [
                'id' => $materi->id,
                'judul' => $materi->judul,
                'mata_pelajaran' => $materi->mataPelajaran?->nama ?? '-',
                'guru' => $materi->guru?->name ?? 'Belum ada guru pengampu',
                'kelas' => 'Kelas '.$materi->kelas.($materi->jurusan ? ' - '.$materi->jurusan : ''),
                'deskripsi' => $materi->deskripsi,
                'tanggal' => $materi->tanggal?->translatedFormat('d F Y') ?? '-',
                'tanggal_diperbarui' => $materi->updated_at?->translatedFormat('d F Y H:i'),
                'memiliki_file' => $memilikiFile,
                'nama_file' => $materi->nama_file,
                'format' => $materi->format_file,
                'ukuran' => $materi->ukuran_file,
                'bisa_dipratinjau' => $memilikiFile && $materi->bisa_dipratinjau,
                'preview_url' => $memilikiFile
                    ? route('dashboard.siswa.tugas-materi.materi.preview', $materi)
                    : null,
                'unduh_url' => $memilikiFile
                    ? route('dashboard.siswa.tugas-materi.materi.unduh', $materi)
                    : null,
            ],
        ]);
    }

    public function previewMateri(Request $request, Materi $materi)
    {
        $siswa = $this->siswa($request);
        $this->pastikanMateriUntukSiswa($materi, $siswa);

        return $this->responseFileMateri($materi, false);
    }

    public function unduhMateri(Request $request, Materi $materi)
    {
        $siswa = $this->siswa($request);
        $this->pastikanMateriUntukSiswa($materi, $siswa);

        return $this->responseFileMateri($materi, true);
    }

    public function previewTugas(Request $request, Tugas $tugas)
    {
        $siswa = $this->siswa($request);
        $this->pastikanTugasUntukSiswa($tugas, $siswa);

        return $this->responseFileTugas($tugas, false);
    }

    public function unduhTugas(Request $request, Tugas $tugas)
    {
        $siswa = $this->siswa($request);
        $this->pastikanTugasUntukSiswa($tugas, $siswa);

        return $this->responseFileTugas($tugas, true);
    }

    public function kumpulkanTugas(Request $request, Tugas $tugas): RedirectResponse
    {
        $siswa = $this->siswa($request);
        $this->pastikanTugasUntukSiswa($tugas, $siswa);

        $data = $request->validate([
            'jawaban' => ['required', 'string', 'max:5000'],
        ], [
            'jawaban.required' => 'Jawaban atau catatan pengumpulan wajib diisi.',
            'jawaban.max' => 'Jawaban atau catatan pengumpulan maksimal 5000 karakter.',
        ]);

        $jawaban = trim($data['jawaban']);
        $pengumpulan = PengumpulanTugas::query()
            ->where('tugas_id', $tugas->id)
            ->where('user_id', $siswa->id)
            ->first(['jawaban']);

        if ($pengumpulan === null || ! PerbandinganData::sama($pengumpulan->jawaban, $jawaban)) {
            PengumpulanTugas::updateOrCreate(
                ['tugas_id' => $tugas->id, 'user_id' => $siswa->id],
                [
                    'dikumpulkan_at' => now(),
                    'jawaban' => $jawaban,
                ]
            );
        }

        return redirect()
            ->route('dashboard.siswa.tugas-materi.index', ['tab' => 'tugas'])
            ->with('success', 'Tugas berhasil dikumpulkan.');
    }

    public function rapor(Request $request): View
    {
        $siswa = $this->siswa($request);
        $periode = $this->periode($request);

        return view('dashboard.siswa.rapor.index', $this->dataRapor($siswa, $periode));
    }

    public function exportRapor(Request $request): Response|JsonResponse
    {
        $siswa = $this->siswa($request);
        $periode = $this->periode($request);
        $data = $this->dataRapor($siswa, $periode);

        if ($data['nilai']->isEmpty()) {
            $pesan = 'Rapor belum tersedia untuk '.$data['periodeLabel'].'.';

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $pesan], 422);
            }

            return redirect()
                ->route('dashboard.siswa.rapor.index', $data['periode'])
                ->with('error', $pesan);
        }

        $pdf = Pdf::loadView('dashboard.siswa.rapor.pdf', $data)
            ->setPaper('a4', 'portrait');

        $namaSiswa = $this->segmenNamaFile((string) $siswa->name, 'Siswa');
        $kelas = $this->segmenNamaFile((string) ($siswa->kelas ?? 'Kelas'), 'Kelas');
        if (filled($siswa->jurusan)) {
            $kelas .= '-'.$this->segmenNamaFile((string) $siswa->jurusan, '');
        }
        $kelas = trim($kelas, '-');
        $tahun = str_replace('/', '-', $data['periode']['tahun_ajaran']);
        $semester = $this->segmenNamaFile($data['periode']['semester'], 'Semester');
        $namaFile = 'Rapor_'.$namaSiswa.'_'.$kelas.'_'.$tahun.'_'.$semester.'.pdf';

        return $pdf->download($namaFile);
    }

    /**
     * @return array<string, mixed>
     */
    protected function dataRapor(User $siswa, array $periode): array
    {
        $nilai = $this->statistik->nilaiSiswa($siswa)
            ->filter(fn (Nilai $n) => $n->tahun_ajaran === $periode['tahun_ajaran'] && $n->semester === $periode['semester'])
            ->values();

        $total = $nilai->count();
        $tuntas = $nilai->filter(fn (Nilai $n) => $n->tuntas())->count();

        $tahunAjarans = $this->statistik->tahunAjaranTersedia();

        if (! in_array($periode['tahun_ajaran'], $tahunAjarans, true)) {
            $tahunAjarans[] = $periode['tahun_ajaran'];
            usort(
                $tahunAjarans,
                fn (string $a, string $b): int => Periode::urutan($b, Periode::SEMESTER_GANJIL)
                    <=> Periode::urutan($a, Periode::SEMESTER_GANJIL)
            );
        }

        return [
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
        ];
    }

    protected function segmenNamaFile(string $nilai, string $fallback): string
    {
        $nilai = Str::ascii($nilai);
        $nilai = preg_replace('/[^\pL\pN]+/u', '-', $nilai) ?? '';
        $nilai = trim($nilai, '-');
        $nilai = preg_replace('/-+/', '-', $nilai) ?? $nilai;

        return $nilai !== '' ? $nilai : $fallback;
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

    protected function pastikanTugasUntukSiswa(Tugas $tugas, User $siswa): void
    {
        $boleh = $this->infoSiswa->tugas($siswa)
            ->contains(fn (Tugas $item): bool => (int) $item->id === (int) $tugas->id);

        if (! $boleh) {
            abort(404);
        }
    }

    protected function pastikanMateriUntukSiswa(Materi $materi, User $siswa): void
    {
        $boleh = $this->infoSiswa->materi($siswa)
            ->contains(fn (Materi $item): bool => (int) $item->id === (int) $materi->id);

        if (! $boleh) {
            abort(404);
        }
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
