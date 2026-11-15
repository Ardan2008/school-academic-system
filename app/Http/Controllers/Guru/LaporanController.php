<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StatistikNilai;
use App\Services\XlsxExportService;
use App\Support\Periode;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Laporan Nilai Guru.
 *
 * Menyusun laporan nilai untuk diunduh dan dicetak, berbeda dari Rekap Nilai
 * yang menonjolkan penelusuran angka di layar. Angkanya dihitung lewat
 * StatistikNilai agar konsisten dengan Input dan Rekap Nilai. Guru hanya melihat
 * nilai mapel yang namanya cocok dengan users.mata_pelajaran (seperti Input dan
 * Rekap Nilai), sehingga laporan tidak pernah memuat nilai guru lain.
 */
class LaporanController extends Controller
{
    /** Batas panjang kata kunci, supaya isian yang sangat panjang tidak membebani query. */
    private const MAKS_CARI = 100;

    private const PER_HALAMAN = 5;

    /** Label dan ambang tiap kelompok nilai pada ringkasan sebaran. */
    private const SEBARAN = [
        ['label' => 'Sangat Baik', 'keterangan' => '90 – 100', 'min' => 90, 'maks' => null],
        ['label' => 'Baik', 'keterangan' => '80 – 89', 'min' => 80, 'maks' => 89.99],
        ['label' => 'Cukup', 'keterangan' => '70 – 79', 'min' => 70, 'maks' => 79.99],
        ['label' => 'Perlu Perbaikan', 'keterangan' => '< 70', 'min' => null, 'maks' => 69.99],
    ];

    public function __construct(private readonly StatistikNilai $statistik) {}

    public function index(Request $request): View
    {
        $guru = $this->guru($request);
        $filter = $this->filter($request);
        $mapelIds = $this->statistik->mapelIdsDiampu($guru);

        $perSiswaSemua = $this->statistik->rekapPerSiswaSemua(
            $filter['tahun_ajaran'],
            $filter['semester'],
            $filter['kelas'],
            null,
            $mapelIds,
            $filter['cari']
        );

        // Rekap per kelas dihitung penuh lebih dulu agar cetak dan kartu ringkasan
        // tetap membutuhkan seluruh baris, meski layar hanya menampilkan sebagian.
        $perKelasSemua = $this->statistik->rekapPerKelas(
            $filter['tahun_ajaran'],
            $filter['semester'],
            $filter['kelas'],
            null,
            $mapelIds
        );

        return view('dashboard.guru.laporan.index', [
            'filter' => $filter,
            'semesters' => Periode::SEMESTERS,
            'tahunAjarans' => $this->statistik->tahunAjaranTersedia($mapelIds),
            'kelasOptions' => $this->statistik->kelasDiampu($guru),
            'mapelDiampu' => $this->statistik->mapelDiampu($guru),
            'guru' => $guru,

            'ringkasan' => $this->statistik->ringkasan(
                $filter['tahun_ajaran'],
                $filter['semester'],
                $filter['kelas'],
                null,
                $mapelIds
            ),
            // Tabel di halaman ini ditampilkan berhalaman agar laporan tetap
            // ringkas di layar. Salinan cetak dan export tetap memakai seluruh
            // baris sesuai filter aktif.
            'perSiswa' => $this->statistik->bagiHalaman($perSiswaSemua, self::PER_HALAMAN, 'siswa_page'),
            'perSiswaSemua' => $perSiswaSemua,
            'perKelas' => $this->statistik->bagiHalaman($perKelasSemua, self::PER_HALAMAN, 'kelas_page'),
            'perKelasSemua' => $perKelasSemua,
            'sebaran' => $this->sebaran($perSiswaSemua),
        ]);
    }

    public function export(Request $request): Response
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
            $satuBaris = [$i + 1, $row['nis'], $row['nama'], $row['kelas'], $row['jurusan']];

            foreach ($mapelDiampu as $mapel) {
                $nilai = $row['nilai'][(int) $mapel->id] ?? null;
                $satuBaris[] = $nilai !== null ? (float) $nilai->nilai : '-';
            }

            $satuBaris[] = $row['rata_rata'] ?? '-';
            $satuBaris[] = $row['jumlah_nilai'];
            $satuBaris[] = $row['tuntas'];
            $satuBaris[] = $row['belum_tuntas'];

            $baris[] = $satuBaris;
        }

        $response = app(XlsxExportService::class)->download(
            'laporan-nilai-'.now()->format('Ymd-His').'.xlsx',
            'Laporan Nilai Siswa',
            $judulSeri,
            $baris,
            [
                'sheet' => 'Laporan Nilai',
                'subjudul' => $this->deskripsiLaporan($filter, $guru).' '.count($baris).' siswa.',
                // NIS dan kelas dipaksa teks supaya "12-1" tidak diubah Excel jadi tanggal.
                'text_columns' => [2, 4],
            ]
        );

        $response->headers->set('Cache-Control', 'no-store, no-cache');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Total-Baris', (string) count($baris));

        return $response;
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
     * periode berjalan supaya laporan tidak pernah menampilkan angka dari
     * periode yang salah hanya karena satu isian salah ketik.
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
     * Sebaran nilai pada lingkup filter, dipakai ringkasan supaya guru bisa
     * melihat persebaran hasil belajar, bukan hanya rata-ratanya.
     *
     * @param  Collection<int, array<string, mixed>>  $perSiswa
     * @return array<int, array{label: string, keterangan: string, jumlah: int, persen: int}>
     */
    protected function sebaran(Collection $perSiswa): array
    {
        $semua = [];

        foreach ($perSiswa as $row) {
            foreach (($row['nilai'] ?? []) as $nilai) {
                $semua[] = (float) $nilai->nilai;
            }
        }

        $total = count($semua);

        return array_map(function (array $kelompok) use ($semua, $total): array {
            $jumlah = count(array_filter($semua, function (float $nilai) use ($kelompok): bool {
                $atasOk = $kelompok['maks'] === null || $nilai <= $kelompok['maks'];
                $bawahOk = $kelompok['min'] === null || $nilai >= $kelompok['min'];

                return $atasOk && $bawahOk;
            }));

            return [
                'label' => $kelompok['label'],
                'keterangan' => $kelompok['keterangan'],
                'jumlah' => $jumlah,
                'persen' => $total > 0 ? (int) round($jumlah / $total * 100) : 0,
            ];
        }, self::SEBARAN);
    }

    /** Ringkas periode dan filter aktif untuk subjudul file export. */
    protected function deskripsiLaporan(array $filter, User $guru): string
    {
        $bagian = [
            Periode::semesterLabel($filter['semester']),
            Periode::tahunAjaranLabel($filter['tahun_ajaran']),
        ];

        $namaMapel = trim((string) $guru->mata_pelajaran);

        if ($namaMapel !== '') {
            $bagian[] = 'Mata Pelajaran '.$namaMapel;
        }

        if ($filter['kelas'] !== '') {
            $bagian[] = 'Kelas '.$filter['kelas'];
        }

        if ($filter['cari'] !== '') {
            $bagian[] = 'Pencarian "'.$filter['cari'].'"';
        }

        return implode(' · ', $bagian).'.';
    }
}
