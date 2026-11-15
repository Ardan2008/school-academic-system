<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StatistikNilai;
use App\Services\XlsxExportService;
use App\Support\Periode;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Rekap Nilai Guru.
 *
 * Hanya menghitung nilai untuk mapel yang diampu guru yang sedang login; guru
 * tanpa penugasan melihat halaman kosong dengan keterangan, bukan angka guru
 * lain. Rekap per siswa, per kelas, dan per mapel memakai filter yang sama dan
 * dihitung lewat StatistikNilai agar angkanya tidak berbeda antar tabel.
 */
class RekapNilaiController extends Controller
{
    private const PER_HALAMAN = 5;

    /** Batas panjang kata kunci, supaya isian yang sangat panjang tidak membebani query. */
    private const MAKS_CARI = 100;

    public function __construct(private readonly StatistikNilai $statistik) {}

    public function index(Request $request): View
    {
        $guru = $this->guru($request);
        $filter = $this->filter($request);
        $mapelIds = $this->statistik->mapelIdsDiampu($guru);

        return view('dashboard.guru.rekap-nilai.index', [
            'filter' => $filter,
            'semesters' => Periode::SEMESTERS,
            'tahunAjarans' => $this->statistik->tahunAjaranTersedia($mapelIds),
            'kelasOptions' => $this->statistik->kelasDiampu($guru),
            'mapelDiampu' => $this->statistik->mapelDiampu($guru),

            'ringkasan' => $this->statistik->ringkasan(
                $filter['tahun_ajaran'],
                $filter['semester'],
                $filter['kelas'],
                null,
                $mapelIds
            ),
            'perSiswa' => $this->statistik->rekapPerSiswa(
                $filter['tahun_ajaran'],
                $filter['semester'],
                $filter['kelas'],
                null,
                $mapelIds,
                $filter['cari'],
                self::PER_HALAMAN
            ),
            'perKelas' => $this->statistik->rekapPerKelasPaginated(
                $filter['tahun_ajaran'],
                $filter['semester'],
                $filter['kelas'],
                null,
                $mapelIds,
                self::PER_HALAMAN
            ),
            'perMapel' => $this->statistik->rekapPerMataPelajaranPaginated(
                $filter['tahun_ajaran'],
                $filter['semester'],
                $filter['kelas'],
                null,
                $mapelIds,
                self::PER_HALAMAN
            ),
            'perkembangan' => $this->statistik->perkembanganPeriode($filter['kelas'], null, $mapelIds),
        ]);
    }

    public function exportSiswa(Request $request): Response
    {
        $guru = $this->guru($request);
        $filter = $this->filter($request);
        $mapelIds = $this->statistik->mapelIdsDiampu($guru);
        $mapelDiampu = $this->statistik->mapelDiampu($guru);

        $rekap = $this->statistik->rekapPerSiswaSemua(
            $filter['tahun_ajaran'],
            $filter['semester'],
            $filter['kelas'],
            null,
            $mapelIds,
            $filter['cari']
        );

        $judulSeri = ['No', 'NIS', 'Nama', 'Kelas', 'Jurusan'];

        foreach ($mapelDiampu as $mapel) {
            $judulSeri[] = $mapel->nama;
        }

        $judulSeri = array_merge($judulSeri, ['Rata-Rata', 'Nilai Tercatat', 'Tuntas', 'Belum Tuntas']);

        $baris = [];

        foreach ($rekap as $i => $row) {
            $nilaiSatu = [$i + 1, $row['nis'], $row['nama'], $row['kelas'], $row['jurusan']];

            foreach ($mapelDiampu as $mapel) {
                $nilai = $row['nilai'][(int) $mapel->id] ?? null;
                $nilaiSatu[] = $nilai !== null ? (float) $nilai->nilai : '-';
            }

            $nilaiSatu[] = $row['rata_rata'] ?? '-';
            $nilaiSatu[] = $row['jumlah_nilai'];
            $nilaiSatu[] = $row['tuntas'];
            $nilaiSatu[] = $row['belum_tuntas'];

            $baris[] = $nilaiSatu;
        }

        return $this->unduh(
            'rekap-nilai-siswa-'.now()->format('Ymd-His').'.xlsx',
            'Rekap Nilai per Siswa',
            $judulSeri,
            $baris,
            $filter,
            count($baris).' siswa',
            'Rekap Siswa',
            // NIS dan kelas dipaksa teks supaya "12-1" tidak diubah Excel jadi tanggal.
            [2, 4]
        );
    }

    public function exportKelas(Request $request): Response
    {
        $guru = $this->guru($request);
        $filter = $this->filter($request);
        $mapelIds = $this->statistik->mapelIdsDiampu($guru);

        $rekap = $this->statistik->rekapPerKelas(
            $filter['tahun_ajaran'],
            $filter['semester'],
            $filter['kelas'],
            null,
            $mapelIds
        );

        $baris = [];

        foreach ($rekap as $row) {
            $baris[] = [
                $row['no'],
                // Pada rekap per kelas, kode adalah kelas dan nama adalah jurusan.
                $row['kode'],
                $row['nama'],
                $row['jumlah_siswa'],
                $row['jumlah_nilai'],
                $row['rata_rata'],
                $row['tuntas'],
                $row['belum_tuntas'],
                $row['persen_tuntas'].'%',
            ];
        }

        return $this->unduh(
            'rekap-nilai-kelas-'.now()->format('Ymd-His').'.xlsx',
            'Rekap Nilai per Kelas',
            ['No', 'Kelas', 'Jurusan', 'Siswa', 'Nilai Tercatat', 'Rata-Rata', 'Tuntas', 'Belum Tuntas', 'Persentase Tuntas'],
            $baris,
            $filter,
            count($baris).' kelas',
            'Rekap Kelas',
            [2]
        );
    }

    public function exportMataPelajaran(Request $request): Response
    {
        $guru = $this->guru($request);
        $filter = $this->filter($request);
        $mapelIds = $this->statistik->mapelIdsDiampu($guru);

        $rekap = $this->statistik->rekapPerMataPelajaran(
            $filter['tahun_ajaran'],
            $filter['semester'],
            $filter['kelas'],
            null,
            $mapelIds
        );

        $baris = [];

        foreach ($rekap as $row) {
            $baris[] = [
                $row['no'],
                $row['kode'] ?: '-',
                $row['nama'],
                $row['jumlah_siswa'],
                $row['jumlah_nilai'],
                $row['rata_rata'],
                $row['tuntas'],
                $row['belum_tuntas'],
                $row['persen_tuntas'].'%',
            ];
        }

        return $this->unduh(
            'rekap-nilai-mata-pelajaran-'.now()->format('Ymd-His').'.xlsx',
            'Rekap Nilai per Mata Pelajaran',
            ['No', 'Kode', 'Mata Pelajaran', 'Siswa', 'Nilai Tercatat', 'Rata-Rata', 'Tuntas', 'Belum Tuntas', 'Persentase Tuntas'],
            $baris,
            $filter,
            count($baris).' mata pelajaran',
            'Rekap Mata Pelajaran',
            []
        );
    }

    // Bantuan

    protected function guru(Request $request): User
    {
        $guru = $request->user();

        if (! $guru instanceof User || $guru->role !== 'guru') {
            abort(403);
        }

        return $guru;
    }

    /**
     * Bersihkan filter dari request. Periode yang tidak dikenal dikembalikan ke
     * periode berjalan supaya halaman tidak menampilkan angka dari periode yang
     * salah hanya karena satu isian salah ketik.
     *
     * @return array{tahun_ajaran: string, semester: string, kelas: string, cari: string}
     */
    protected function filter(Request $request): array
    {
        $semester = trim((string) $request->query('semester', ''));
        $tahunAjaran = trim((string) $request->query('tahun_ajaran', ''));

        if (! Periode::semesterValid($semester)) {
            $semester = Periode::semesterSekarang();
        }

        if (! Periode::tahunAjaranValid($tahunAjaran)) {
            $tahunAjaran = Periode::tahunAjaranSekarang();
        }

        $cari = $request->query('cari', $request->query('search', $request->query('q', '')));

        return [
            'tahun_ajaran' => $tahunAjaran,
            'semester' => $semester,
            'kelas' => trim((string) $request->query('kelas', '')),
            'cari' => mb_substr(trim((string) $cari), 0, self::MAKS_CARI),
        ];
    }

    /**
     * Bungkus XlsxExportService supaya semua export di halaman ini punya header
     * dan subjudul yang seragam.
     *
     * @param  array<int, string>  $judulSeri
     * @param  array<int, array<int, mixed>>  $baris
     * @param  array{tahun_ajaran: string, semester: string, kelas: string, cari: string}  $filter
     * @param  array<int, int>  $textColumns  nomor kolom (1-based) yang dipaksa tetap teks
     */
    protected function unduh(
        string $namaFile,
        string $judul,
        array $judulSeri,
        array $baris,
        array $filter,
        string $keterangan,
        string $namaSheet,
        array $textColumns
    ): Response {
        $response = app(XlsxExportService::class)->download(
            $namaFile,
            $judul,
            $judulSeri,
            $baris,
            [
                'sheet' => $namaSheet,
                'subjudul' => $this->deskripsiPeriode($filter).' '.$keterangan,
                'text_columns' => $textColumns,
            ]
        );

        $response->headers->set('Cache-Control', 'no-store, no-cache');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Total-Baris', (string) count($baris));

        return $response;
    }

    /** Ringkas periode dan filter aktif untuk dipakai di subjudul file export. */
    protected function deskripsiPeriode(array $filter): string
    {
        $bagian = [Periode::tahunAjaranLabel($filter['tahun_ajaran']), Periode::semesterLabel($filter['semester'])];

        if ($filter['kelas'] !== '') {
            $bagian[] = 'Kelas '.$filter['kelas'];
        }

        if ($filter['cari'] !== '') {
            $bagian[] = 'Pencarian "'.$filter['cari'].'"';
        }

        return implode(' · ', $bagian).'.';
    }
}
