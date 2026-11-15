<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jurusan;
use App\Models\KelasRombel;
use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\User;
use App\Services\XlsxExportService;
use App\Support\Kelas;
use App\Support\Periode;
use App\Support\StrukturKelas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Laporan Akademik Admin.
 *
 * Seluruh angka dihitung dari tabel nilai, bukan data master. Bila tabel nilai
 * kosong, halaman sengaja menampilkan kosong dengan keterangan jelas agar
 * angka perkiraan tidak terlihat sahih dan dipakai mengambil keputusan.
 *
 * Rata-rata dihitung per baris nilai, ketuntasan terhadap KKM pada baris yang
 * sama, dan grafik perkembangan sengaja mengabaikan filter periode supaya
 * garisnya punya lebih dari satu titik.
 */
class LaporanAkademikController extends Controller
{
    /** Perkembangan dibatasi, supaya tabel nilai yang sangat panjang tidak membuat garis penuh. */
    private const MAKS_PERIODE_PERKEMBANGAN = 12;

    public function index(Request $request)
    {
        $filter = $this->filterDariRequest($request);

        $ringkasan = $this->ringkasan($filter);
        $perKelas = $this->rekapPerKelas($filter);
        $perMapel = $this->rekapPerMataPelajaran($filter);
        $perkembangan = $this->perkembangan($filter);
        $pilihan = $this->pilihanFilter();

        return view('dashboard.admin.laporan-akademik.index', [
            'filter' => $filter,

            'semesters' => Periode::SEMESTERS,
            'tahunAjarans' => $pilihan['tahun_ajaran'],
            'jurusanOptions' => $pilihan['jurusan'],
            'kelasOptions' => $pilihan['kelas'],
            'mapelOptions' => $pilihan['mata_pelajaran'],

            'ringkasan' => $ringkasan,
            'adaNilai' => $ringkasan['jumlah_nilai'] > 0,

            'perKelas' => $perKelas['baris'],
            'totalKelas' => count($perKelas['baris']),
            'bestKelas' => $perKelas['terbaik'],
            'worstKelas' => $perKelas['terburuk'],

            'perMapel' => $perMapel['baris'],
            'totalMapel' => count($perMapel['baris']),
            'bestMapel' => $perMapel['terbaik'],
            'worstMapel' => $perMapel['terburuk'],

            'perkembanganLabel' => $perkembangan['label'],
            'perkembanganNilai' => $perkembangan['rata_rata'],
            'perkembanganJumlah' => $perkembangan['jumlah_nilai'],

            'jumlahSiswaTerfilter' => $this->siswaTerfilter($filter),
        ]);
    }

    public function exportKelas(Request $request): Response
    {
        $filter = $this->filterDariRequest($request);
        $rekap = $this->rekapPerKelas($filter);

        $baris = [];

        foreach ($rekap['baris'] as $row) {
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
            // Kolom kelas dipaksa teks supaya "12-1" tidak diubah Excel jadi tanggal.
            [2]
        );
    }

    public function exportMataPelajaran(Request $request): Response
    {
        $filter = $this->filterDariRequest($request);
        $rekap = $this->rekapPerMataPelajaran($filter);

        $baris = [];

        foreach ($rekap['baris'] as $row) {
            $baris[] = [
                $row['no'],
                $row['kode'] ?: '-',
                $row['nama'],
                $row['guru'],
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
            ['No', 'Kode', 'Mata Pelajaran', 'Guru Pengampu', 'Siswa', 'Nilai Tercatat', 'Rata-Rata', 'Tuntas', 'Belum Tuntas', 'Persentase Tuntas'],
            $baris,
            $filter,
            count($baris).' mata pelajaran',
            'Rekap Mata Pelajaran',
            []
        );
    }

    // Filter

    /**
     * Bersihkan filter dari request. Nilai yang tidak dikenal mengembalikan
     * periode berjalan supaya halaman tidak pernah menampilkan angka dari
     * periode yang salah hanya karena satu isian ketik salah.
     *
     * @return array{tahun_ajaran: string, semester: string, jurusan: string, kelas: string, mata_pelajaran: string}
     */
    protected function filterDariRequest(Request $request): array
    {
        $semester = trim((string) $request->query('semester', ''));
        $tahunAjaran = trim((string) $request->query('tahun_ajaran', ''));

        if (! Periode::semesterValid($semester)) {
            $semester = Periode::semesterSekarang();
        }

        if (! Periode::tahunAjaranValid($tahunAjaran)) {
            $tahunAjaran = Periode::tahunAjaranSekarang();
        }

        $kelas = trim((string) $request->query('kelas', ''));

        return [
            'tahun_ajaran' => $tahunAjaran,
            'semester' => $semester,
            'jurusan' => trim((string) $request->query('jurusan', '')),
            // "12 - 1" harus tetap cocok dengan kelas "12-1" yang tersimpan.
            'kelas' => $kelas === '' ? '' : Kelas::normalize($kelas),
            'mata_pelajaran' => trim((string) $request->query('mata_pelajaran', '')),
        ];
    }

    /**
     * Opsi dropdown. Tahun ajaran dan kelas diambil dari nilai yang benar-benar
     * ada, ditambah periode berjalan, supaya pilihan tidak pernah mengarah ke
     * kombinasi yang kosong tanpa sebab.
     *
     * @return array{tahun_ajaran: array<int, string>, jurusan: array<int, string>, kelas: array<int, string>, mata_pelajaran: array<int, string>}
     */
    protected function pilihanFilter(): array
    {
        $tahunAjaran = Nilai::query()
            ->distinct()
            ->pluck('tahun_ajaran')
            ->push(Periode::tahunAjaranSekarang())
            ->filter(fn ($tahun) => Periode::tahunAjaranValid($tahun))
            ->unique()
            ->sortByDesc(fn ($tahun) => Periode::urutan($tahun, Periode::SEMESTER_GANJIL))
            ->values()
            ->all();

        // Jurusan nonaktif tetap bisa dipilih supaya nilai tahun lalu saat
        // jurusan itu masih aktif tidak ikut hilang dari laporan.
        $jurusan = Jurusan::query()
            ->orderByRaw("case when status = 'aktif' then 0 else 1 end")
            ->orderBy('nama')
            ->pluck('nama')
            ->all();

        // Kelas bisa berasal dari Data Kelas maupun dari nilai yang sudah
        // tercatat, karena kelas yang pernah dihapus tidak hilang dari riwayat nilai.
        $kelas = collect(
            KelasRombel::query()->distinct()->pluck('kelas')
                ->merge(Nilai::query()->distinct()->pluck('kelas'))
                ->filter(fn ($nilai) => Kelas::isValid((string) $nilai))
                ->unique()
                ->map(fn ($nilai) => (string) $nilai)
                ->values()
                ->all()
        );

        $kelas = $kelas->sort(fn (string $a, string $b) => StrukturKelas::bandingkanKelas($a, $b))
            ->values()
            ->all();

        return [
            'tahun_ajaran' => $tahunAjaran,
            'jurusan' => $jurusan,
            'kelas' => $kelas,
            'mata_pelajaran' => MataPelajaran::query()->orderBy('nama')->pluck('nama')->all(),
        ];
    }

    /** Query nilai yang sudah ikut semua filter, dipakai semua bagian laporan. */
    protected function nilaiFiltered(array $filter, bool $abaikanPeriode = false): Builder
    {
        $query = Nilai::query();

        if ($abaikanPeriode) {
            if ($filter['jurusan'] !== '') {
                $query->untukJurusan($filter['jurusan']);
            }

            if ($filter['kelas'] !== '') {
                $query->untukKelas($filter['kelas'], $filter['jurusan']);
            }

            if ($filter['mata_pelajaran'] !== '') {
                $query->untukMataPelajaran($filter['mata_pelajaran']);
            }

            return $query;
        }

        return $query->filter($filter);
    }

    // Agregasi

    /**
     * @return array{jumlah_nilai: int, jumlah_siswa: int, jumlah_mapel: int, rata_rata: float|null, rata_kkm: float|null, tuntas: int, belum_tuntas: int, persen_tuntas: float|null}
     */
    protected function ringkasan(array $filter): array
    {
        $baris = $this->nilaiFiltered($filter)
            ->selectRaw('count(*) as jumlah_nilai')
            ->selectRaw('count(distinct user_id) as jumlah_siswa')
            ->selectRaw('count(distinct mata_pelajaran_id) as jumlah_mapel')
            ->selectRaw('avg(nilai) as rata_rata')
            ->selectRaw('avg(kkm) as rata_kkm')
            ->selectRaw('sum(case when nilai >= kkm then 1 else 0 end) as tuntas')
            ->first();

        $jumlahNilai = (int) ($baris?->jumlah_nilai ?? 0);
        $tuntas = (int) ($baris?->tuntas ?? 0);

        return [
            'jumlah_nilai' => $jumlahNilai,
            'jumlah_siswa' => (int) ($baris?->jumlah_siswa ?? 0),
            'jumlah_mapel' => (int) ($baris?->jumlah_mapel ?? 0),
            'rata_rata' => $jumlahNilai > 0 ? round((float) $baris->rata_rata, 1) : null,
            'rata_kkm' => $jumlahNilai > 0 ? round((float) $baris->rata_kkm, 1) : null,
            'tuntas' => $tuntas,
            'belum_tuntas' => $jumlahNilai - $tuntas,
            'persen_tuntas' => $jumlahNilai > 0 ? round($tuntas / $jumlahNilai * 100) : null,
        ];
    }

    /**
     * Rekap per kelas. Diurutkan dengan pembanding kelas yang sama dengan Data
     * Kelas supaya "12-2" selalu muncul sebelum "12-10".
     *
     * @return array{baris: array<int, array<string, mixed>>, terbaik: array<string, mixed>|null, terburuk: array<string, mixed>|null}
     */
    protected function rekapPerKelas(array $filter): array
    {
        $baris = $this->nilaiFiltered($filter)
            ->selectRaw('kelas, jurusan')
            ->selectRaw('count(*) as jumlah_nilai')
            ->selectRaw('count(distinct user_id) as jumlah_siswa')
            ->selectRaw('avg(nilai) as rata_rata')
            ->selectRaw('sum(case when nilai >= kkm then 1 else 0 end) as tuntas')
            ->groupBy('kelas', 'jurusan')
            ->get()
            ->sort(fn ($a, $b) => $this->bandingkanKelasJurusan($a->jurusan, $a->kelas, $b->jurusan, $b->kelas))
            ->values();

        $hasil = [];
        $no = 1;

        foreach ($baris as $row) {
            $hasil[] = $this->barisRekap($no++, $row, $row->kelas, $row->jurusan);
        }

        return [
            'baris' => $hasil,
            'terbaik' => $this->palingBagus($hasil),
            'terburuk' => $this->palingJelek($hasil),
        ];
    }

    /**
     * Rekap per mata pelajaran. Guru pengampu diambil dari akun guru yang
     * mata pelajarannya sama, bukan pasangan berurutan, supaya kolom nama guru
     * benar-benar menyatakan siapa yang mengampu.
     *
     * @return array{baris: array<int, array<string, mixed>>, terbaik: array<string, mixed>|null, terburuk: array<string, mixed>|null}
     */
    protected function rekapPerMataPelajaran(array $filter): array
    {
        $baris = $this->nilaiFiltered($filter)
            ->join('mata_pelajaran', 'mata_pelajaran.id', '=', 'nilai.mata_pelajaran_id')
            ->selectRaw('mata_pelajaran.kode as kode')
            ->selectRaw('mata_pelajaran.nama as nama')
            ->selectRaw('count(*) as jumlah_nilai')
            ->selectRaw('count(distinct nilai.user_id) as jumlah_siswa')
            ->selectRaw('avg(nilai.nilai) as rata_rata')
            ->selectRaw('sum(case when nilai.nilai >= nilai.kkm then 1 else 0 end) as tuntas')
            ->groupBy('mata_pelajaran.id', 'mata_pelajaran.kode', 'mata_pelajaran.nama')
            ->orderBy('mata_pelajaran.nama')
            ->get();

        $pengampu = $this->guruPengampu();

        $hasil = [];
        $no = 1;

        foreach ($baris as $row) {
            $hasil[] = $this->barisRekap($no++, $row, $row->kode, $row->nama)
                + ['guru' => $pengampu[$row->nama] ?? 'Belum ada guru pengampu'];
        }

        return [
            'baris' => $hasil,
            'terbaik' => $this->palingBagus($hasil),
            'terburuk' => $this->palingJelek($hasil),
        ];
    }

    /** Bentuk satu baris rekap dari hasil agregasi SQL. */
    protected function barisRekap(int $no, $row, string $kode, string $nama): array
    {
        $jumlahNilai = (int) $row->jumlah_nilai;
        $tuntas = (int) $row->tuntas;

        return [
            'no' => $no,
            'kode' => $kode,
            'nama' => $nama,
            'jumlah_siswa' => (int) $row->jumlah_siswa,
            'jumlah_nilai' => $jumlahNilai,
            'rata_rata' => round((float) $row->rata_rata, 1),
            'tuntas' => $tuntas,
            'belum_tuntas' => $jumlahNilai - $tuntas,
            'persen_tuntas' => $jumlahNilai > 0 ? round($tuntas / $jumlahNilai * 100) : 0,
        ];
    }

    /**
     * Garis perkembangan lintas periode. Filter periode sengaja dilepas supaya
     * grafik benar-benar menunjukkan perkembangan, dan periode tanpa nilai
     * tetap ikut sebagai titik kosong supaya jaraknya tidak dipadatkan.
     *
     * @return array{label: array<int, string>, rata_rata: array<int, float|null>, jumlah_nilai: array<int, int>}
     */
    protected function perkembangan(array $filter): array
    {
        $baris = $this->nilaiFiltered($filter, true)
            ->selectRaw('tahun_ajaran, semester')
            ->selectRaw('count(*) as jumlah_nilai')
            ->selectRaw('avg(nilai) as rata_rata')
            ->groupBy('tahun_ajaran', 'semester')
            ->get()
            ->filter(fn ($row) => Periode::semesterValid($row->semester) && Periode::tahunAjaranValid($row->tahun_ajaran))
            ->sortBy(fn ($row) => Periode::urutan($row->tahun_ajaran, $row->semester))
            ->values();

        $perPeriode = [];
        $urutan = [];

        foreach ($baris as $row) {
            $perPeriode[Periode::label($row->tahun_ajaran, $row->semester)] = $row;
            $urutan[] = [$row->tahun_ajaran, $row->semester];
        }

        $urutan = array_slice($urutan, -self::MAKS_PERIODE_PERKEMBANGAN);

        $label = [];
        $rataRata = [];
        $jumlah = [];

        foreach ($this->rentangPeriode($urutan) as [$tahunAjaran, $semester]) {
            $kunci = Periode::label($tahunAjaran, $semester);
            $label[] = $kunci;
            $jumlah[] = (int) ($perPeriode[$kunci]->jumlah_nilai ?? 0);
            // null, bukan 0: periode tanpa nilai bukan berarti nilai nol.
            $rataRata[] = isset($perPeriode[$kunci])
                ? round((float) $perPeriode[$kunci]->rata_rata, 1)
                : null;
        }

        return [
            'label' => $label,
            'rata_rata' => $rataRata,
            'jumlah_nilai' => $jumlah,
        ];
    }

    /**
     * Isi celah di antara periode pertama dan terakhir yang punya nilai, tanpa
     * menambah periode di luar keduanya. Kalau tidak dibatasi, tahun ajaran
     * terakhir ikut memunculkan semester Genap yang belum berjalan sehingga
     * grafik menyisakan satu kolom kosong di ujung kanan.
     *
     * @param  array<int, array{0: string, 1: string}>  $urutan
     * @return array<int, array{0: string, 1: string}>
     */
    protected function rentangPeriode(array $urutan): array
    {
        if ($urutan === []) {
            return [];
        }

        $awal = Periode::urutan($urutan[0][0], $urutan[0][1]);
        $akhir = Periode::urutan($urutan[count($urutan) - 1][0], $urutan[count($urutan) - 1][1]);

        if ($awal <= 0 || $akhir < $awal) {
            return $urutan;
        }

        $hasil = [];

        for ($indeks = $awal; $indeks <= $akhir; $indeks++) {
            $tahun = intdiv($indeks, 2);

            $hasil[] = [
                $tahun.'/'.($tahun + 1),
                $indeks % 2 === 0 ? Periode::SEMESTER_GANJIL : Periode::SEMESTER_GENAP,
            ];
        }

        return $hasil;
    }

    /**
     * Nama guru pengampu per mata pelajaran, diambil dari akun guru yang
     * mata pelajarannya diisi. Kalau belum ada, laporan mengatakannya secara
     * terbuka daripada menampilkan nama orang yang tidak relevan.
     *
     * @return array<string, string>
     */
    protected function guruPengampu(): array
    {
        return User::query()
            ->where('role', 'guru')
            ->whereNotNull('mata_pelajaran')
            ->where('mata_pelajaran', '!=', '')
            ->get(['name', 'mata_pelajaran'])
            ->groupBy(fn ($guru) => trim((string) $guru->mata_pelajaran))
            ->map(fn (Collection $guru) => $guru->pluck('name')->sort()->implode(', '))
            ->all();
    }

    /** Siswa yang berada di lingkup kelas/jurusan, dipakai untuk keterangan saat laporan kosong. */
    protected function siswaTerfilter(array $filter): int
    {
        $query = User::query()->where('role', 'siswa');

        if ($filter['jurusan'] !== '') {
            $query->where('jurusan', $filter['jurusan']);
        }

        if ($filter['kelas'] !== '') {
            $query->where('kelas', $filter['kelas']);
        }

        return $query->count();
    }

    // Helper rekap

    protected function palingBagus(array $baris): ?array
    {
        $tertinggi = null;

        foreach ($baris as $row) {
            if ($tertinggi === null || $row['rata_rata'] > $tertinggi['rata_rata']) {
                $tertinggi = $row;
            }
        }

        return $tertinggi;
    }

    protected function palingJelek(array $baris): ?array
    {
        $terendah = null;

        foreach ($baris as $row) {
            if ($terendah === null || $row['rata_rata'] < $terendah['rata_rata']) {
                $terendah = $row;
            }
        }

        return $terendah;
    }

    /** Sama dengan urutan Data Kelas: jurusan dulu, lalu kelas secara angka. */
    protected function bandingkanKelasJurusan(string $jurusanA, string $kelasA, string $jurusanB, string $kelasB): int
    {
        $jurusan = strcasecmp($jurusanA, $jurusanB);

        return $jurusan !== 0 ? $jurusan : StrukturKelas::bandingkanKelas($kelasA, $kelasB);
    }

    // Export

    /**
     * @param  array<int, string>  $judulSeri
     * @param  array<int, array<int, mixed>>  $baris
     * @param  array<int, int>  $textColumns  nomor kolom (1-based) yang dipaksa tetap teks
     */
    protected function unduh(
        string $namaFile,
        string $judul,
        array $judulSeri,
        array $baris,
        array $filter,
        string $ketertiaan,
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
                'subjudul' => $this->deskripsiPeriode($filter).' '.$ketertiaan,
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

        if ($filter['jurusan'] !== '') {
            $bagian[] = $filter['jurusan'];
        }

        if ($filter['mata_pelajaran'] !== '') {
            $bagian[] = $filter['mata_pelajaran'];
        }

        return implode(' · ', $bagian).'.';
    }
}
