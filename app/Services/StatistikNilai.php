<?php

namespace App\Services;

use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\User;
use App\Support\Kelas;
use App\Support\Periode;
use App\Support\StrukturKelas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * Sumber tunggal semua angka akademik yang tampil di dashboard, rekap, dan
 * laporan.
 *
 * Rata-rata dihitung per baris nilai (bukan rata-rata antarkelas) supaya kelas
 * dengan sedikit nilai tidak berbobot sama dengan kelas yang penuh. Ketuntasan
 * dibandingkan dengan KKM pada baris yang sama, dan periode kosong berarti
 * seluruh periode ikut dihitung untuk grafik perkembangan.
 */
class StatistikNilai
{
    /** Batas titik pada garis perkembangan, supaya tabel nilai yang panjang tidak membuat garis penuh. */
    private const MAKS_PERIODE_PERKEMBANGAN = 12;

    // Mata pelajaran yang diampu guru

    /**
     * Mata pelajaran yang diampu guru ini. Pencocokan memakai nama, karena itu
     * satu-satunya penghubung guru ke mata pelajaran di sistem ini.
     *
     * @return Collection<int, MataPelajaran>
     */
    public function mapelDiampu(User $guru): Collection
    {
        $nama = trim((string) $guru->mata_pelajaran);

        if ($nama === '') {
            return collect();
        }

        return MataPelajaran::query()
            ->orderBy('nama')
            ->get()
            ->filter(fn (MataPelajaran $mapel) => strcasecmp(trim((string) $mapel->nama), $nama) === 0)
            ->values();
    }

    /**
     * @return array<int, int>
     */
    public function mapelIdsDiampu(User $guru): array
    {
        return $this->mapelDiampu($guru)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    // Query dasar

    /**
     * Query nilai dengan filter yang semuanya opsional. Filter yang dikirim
     * kosong (null/'') berarti "jangan saring", bukan "saring nilai kosong".
     *
     * @param  array<int, int>|null  $mapelIds
     */
    public function queryNilai(
        ?string $tahunAjaran = null,
        ?string $semester = null,
        ?string $kelas = null,
        ?string $jurusan = null,
        ?array $mapelIds = null
    ): Builder {
        $query = Nilai::query();

        if ($tahunAjaran !== null && $tahunAjaran !== '') {
            $query->where('tahun_ajaran', $tahunAjaran);
        }

        if ($semester !== null && $semester !== '') {
            $query->where('semester', $semester);
        }

        if ($kelas !== null && $kelas !== '') {
            $query->where('kelas', Kelas::normalize($kelas));

            if ($jurusan !== null && $jurusan !== '') {
                $query->where('jurusan', $jurusan);
            }
        }

        if ($mapelIds !== null) {
            if ($mapelIds === []) {
                // Guru tanpa penugasan tidak boleh melihat nilai siapa pun.
                return $query->whereRaw('1 = 0');
            }

            $query->whereIn('mata_pelajaran_id', $mapelIds);
        }

        return $query;
    }

    // Ringkasan & rekap

    /**
     * @return array{jumlah_nilai: int, jumlah_siswa: int, jumlah_mapel: int, rata_rata: float|null, tuntas: int, belum_tuntas: int, persen_tuntas: float|null}
     */
    public function ringkasan(
        ?string $tahunAjaran = null,
        ?string $semester = null,
        ?string $kelas = null,
        ?string $jurusan = null,
        ?array $mapelIds = null
    ): array {
        $baris = $this->queryNilai($tahunAjaran, $semester, $kelas, $jurusan, $mapelIds)
            ->selectRaw('count(*) as jumlah_nilai')
            ->selectRaw('count(distinct user_id) as jumlah_siswa')
            ->selectRaw('count(distinct mata_pelajaran_id) as jumlah_mapel')
            ->selectRaw('avg(nilai) as rata_rata')
            ->selectRaw('sum(case when nilai >= kkm then 1 else 0 end) as tuntas')
            ->first();

        $jumlahNilai = (int) ($baris?->jumlah_nilai ?? 0);
        $tuntas = (int) ($baris?->tuntas ?? 0);

        return [
            'jumlah_nilai' => $jumlahNilai,
            'jumlah_siswa' => (int) ($baris?->jumlah_siswa ?? 0),
            'jumlah_mapel' => (int) ($baris?->jumlah_mapel ?? 0),
            'rata_rata' => $jumlahNilai > 0 ? round((float) $baris->rata_rata, 1) : null,
            'tuntas' => $tuntas,
            'belum_tuntas' => $jumlahNilai - $tuntas,
            'persen_tuntas' => $jumlahNilai > 0 ? round($tuntas / $jumlahNilai * 100) : null,
        ];
    }

    /**
     * Rata-rata tiap kelas pada satu periode. Dipakai grafik Dashboard Guru.
     *
     * @param  array<int, int>|null  $mapelIds
     * @return Collection<int, array{label: string, jurusan: string, rata_rata: float, jumlah_nilai: int}>
     */
    public function rataPerKelas(
        ?string $tahunAjaran = null,
        ?string $semester = null,
        ?array $mapelIds = null,
        ?string $jurusan = null
    ): Collection {
        return $this->queryNilai($tahunAjaran, $semester, null, $jurusan, $mapelIds)
            ->selectRaw('kelas, jurusan')
            ->selectRaw('count(*) as jumlah_nilai')
            ->selectRaw('avg(nilai) as rata_rata')
            ->groupBy('kelas', 'jurusan')
            ->get()
            ->sort(fn ($a, $b) => $this->bandingkanKelasJurusan((string) $a->jurusan, (string) $a->kelas, (string) $b->jurusan, (string) $b->kelas))
            ->values()
            ->map(fn ($row) => [
                'label' => (string) $row->kelas,
                'jurusan' => (string) $row->jurusan,
                'rata_rata' => round((float) $row->rata_rata, 1),
                'jumlah_nilai' => (int) $row->jumlah_nilai,
            ]);
    }

    /**
     * Rekap lengkap tiap kelas pada satu periode: rata-rata, jumlah nilai,
     * jumlah siswa, tuntas, belum tuntas, dan persentase tuntas.
     *
     * @param  array<int, int>|null  $mapelIds
     * @return Collection<int, array{no: int, kode: string, nama: string, jumlah_siswa: int, jumlah_nilai: int, rata_rata: float, tuntas: int, belum_tuntas: int, persen_tuntas: int}>
     */
    public function rekapPerKelas(
        ?string $tahunAjaran = null,
        ?string $semester = null,
        ?string $kelas = null,
        ?string $jurusan = null,
        ?array $mapelIds = null
    ): Collection {
        return $this->queryNilai($tahunAjaran, $semester, $kelas, $jurusan, $mapelIds)
            ->selectRaw('kelas, jurusan')
            ->selectRaw('count(*) as jumlah_nilai')
            ->selectRaw('count(distinct user_id) as jumlah_siswa')
            ->selectRaw('avg(nilai) as rata_rata')
            ->selectRaw('sum(case when nilai >= kkm then 1 else 0 end) as tuntas')
            ->groupBy('kelas', 'jurusan')
            ->get()
            ->sort(fn ($a, $b) => $this->bandingkanKelasJurusan((string) $a->jurusan, (string) $a->kelas, (string) $b->jurusan, (string) $b->kelas))
            ->values()
            ->map(fn ($row, $i) => $this->barisRekap($i + 1, $row, (string) $row->kelas, (string) $row->jurusan));
    }

    /**
     * Versi berhalaman dari rekap per kelas. Pengelompokan tetap dihitung penuh
     * lebih dulu karena dilakukan di memori, lalu hasilnya dipotong per halaman.
     * Nama parameter halaman sengaja dibedakan dari rekap per siswa supaya dua
     * tabel di halaman yang sama tidak saling memindahkan posisi.
     *
     * @param  array<int, int>|null  $mapelIds
     * @return LengthAwarePaginator<int, array{no: int, kode: string, nama: string, jumlah_siswa: int, jumlah_nilai: int, rata_rata: float, tuntas: int, belum_tuntas: int, persen_tuntas: int}>
     */
    public function rekapPerKelasPaginated(
        ?string $tahunAjaran = null,
        ?string $semester = null,
        ?string $kelas = null,
        ?string $jurusan = null,
        ?array $mapelIds = null,
        int $perHalaman = 5,
        string $namaHalaman = 'kelas_page'
    ): LengthAwarePaginator {
        return $this->bagiHalaman(
            $this->rekapPerKelas($tahunAjaran, $semester, $kelas, $jurusan, $mapelIds),
            $perHalaman,
            $namaHalaman
        );
    }

    /**
     * Versi berhalaman dari rekap per mata pelajaran. Sama seperti rekap per
     * kelas: dikelompokkan penuh lebih dulu, lalu dipotong per halaman. Nama
     * parameter halaman dibedakan supaya tidak bentrok dengan tabel lain.
     *
     * @param  array<int, int>|null  $mapelIds
     * @return LengthAwarePaginator<int, array{no: int, kode: string, nama: string, guru: string, jumlah_siswa: int, jumlah_nilai: int, rata_rata: float, tuntas: int, belum_tuntas: int, persen_tuntas: int}>
     */
    public function rekapPerMataPelajaranPaginated(
        ?string $tahunAjaran = null,
        ?string $semester = null,
        ?string $kelas = null,
        ?string $jurusan = null,
        ?array $mapelIds = null,
        int $perHalaman = 5,
        string $namaHalaman = 'mapel_page'
    ): LengthAwarePaginator {
        return $this->bagiHalaman(
            $this->rekapPerMataPelajaran($tahunAjaran, $semester, $kelas, $jurusan, $mapelIds),
            $perHalaman,
            $namaHalaman
        );
    }

    /**
     * Bagi koleksi yang sudah dihitung penuh menjadi halaman. Dipakai halaman
     * yang tetap butuh seluruh baris di memori (mis. untuk ringkasan atau
     * sebaran) tetapi menampilkan datanya berhalaman. Nama parameter halaman
     * dibedakan supaya beberapa tabel di satu halaman tidak saling
     * memindahkan posisi.
     *
     * @param  Collection<int, mixed>  $semua
     * @return LengthAwarePaginator<int, mixed>
     */
    public function bagiHalaman(Collection $semua, int $perHalaman = 5, string $namaHalaman = 'page'): LengthAwarePaginator
    {
        $perHalaman = max($perHalaman, 1);
        $halaman = Paginator::resolveCurrentPage($namaHalaman);

        return new LengthAwarePaginator(
            $semua->forPage($halaman, $perHalaman)->values(),
            $semua->count(),
            $perHalaman,
            $halaman,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $namaHalaman,
                'query' => request()->query(),
            ]
        );
    }

    /**
     * Rata-rata per periode (lintas kelas) untuk garis perkembangan. Periode
     * tanpa nilai ikut dikirim sebagai null supaya jarak titik tidak dipadatkan.
     *
     * @param  array<int, int>|null  $mapelIds
     * @return array{label: array<int, string>, rata_rata: array<int, float|null>}
     */
    public function perkembanganPeriode(
        ?string $kelas = null,
        ?string $jurusan = null,
        ?array $mapelIds = null
    ): array {
        $baris = $this->queryNilai(null, null, $kelas, $jurusan, $mapelIds)
            ->selectRaw('tahun_ajaran, semester')
            ->selectRaw('avg(nilai) as rata_rata')
            ->groupBy('tahun_ajaran', 'semester')
            ->get()
            ->filter(fn ($row) => Periode::semesterValid($row->semester) && Periode::tahunAjaranValid($row->tahun_ajaran))
            ->sortBy(fn ($row) => Periode::urutan((string) $row->tahun_ajaran, (string) $row->semester))
            ->values();

        $perPeriode = [];
        $urutan = [];

        foreach ($baris as $row) {
            $perPeriode[Periode::label((string) $row->tahun_ajaran, (string) $row->semester)] = $row;
            $urutan[] = [(string) $row->tahun_ajaran, (string) $row->semester];
        }

        $urutan = array_slice($urutan, -self::MAKS_PERIODE_PERKEMBANGAN);

        $label = [];
        $rataRata = [];

        foreach ($this->rentangPeriode($urutan) as [$tahun, $semester]) {
            $kunci = Periode::label($tahun, $semester);
            $label[] = $kunci;
            $rataRata[] = isset($perPeriode[$kunci]) ? round((float) $perPeriode[$kunci]->rata_rata, 1) : null;
        }

        return ['label' => $label, 'rata_rata' => $rataRata];
    }

    /**
     * Rata-rata tiap kelas per periode untuk grafik Dashboard Admin. Satu baris
     * per kombinasi (kelas, jurusan, tahun ajaran, semester) supaya filter
     * tahun/semester di grafik bisa menyaring titik yang benar.
     *
     * @return Collection<int, array{label: string, jurusan: string, tahun: string, semester: string, nilai: float}>
     */
    public function rataPerKelasPerPeriode(?array $mapelIds = null): Collection
    {
        return $this->queryNilai(null, null, null, null, $mapelIds)
            ->selectRaw('kelas, jurusan, tahun_ajaran, semester')
            ->selectRaw('avg(nilai) as rata_rata')
            ->groupBy('kelas', 'jurusan', 'tahun_ajaran', 'semester')
            ->get()
            ->filter(fn ($row) => Periode::tahunAjaranValid($row->tahun_ajaran) && Periode::semesterValid($row->semester))
            ->sort(fn ($a, $b) => $this->bandingkanKelasJurusan((string) $a->jurusan, (string) $a->kelas, (string) $b->jurusan, (string) $b->kelas)
                ?: (Periode::urutan((string) $a->tahun_ajaran, (string) $a->semester) <=> Periode::urutan((string) $b->tahun_ajaran, (string) $b->semester)))
            ->values()
            ->map(fn ($row) => [
                'label' => (string) $row->kelas,
                'jurusan' => (string) $row->jurusan,
                'tahun' => (string) $row->tahun_ajaran,
                'semester' => (string) $row->semester,
                'nilai' => round((float) $row->rata_rata, 1),
            ]);
    }

    /**
     * Kelas dengan rata-rata tertinggi. Kalau periode tidak diisi, dihitung
     * dari seluruh periode.
     *
     * @param  array<int, int>|null  $mapelIds
     * @return Collection<int, array{kelas: string, jurusan: string, nilai: float, jumlah_nilai: int}>
     */
    public function kelasTertinggi(
        ?string $tahunAjaran = null,
        ?string $semester = null,
        ?array $mapelIds = null,
        ?string $jurusan = null
    ): Collection {
        return $this->rataPerKelas($tahunAjaran, $semester, $mapelIds, $jurusan)
            ->sortByDesc('rata_rata')
            ->values()
            ->map(fn (array $row) => [
                'kelas' => $row['label'],
                'jurusan' => $row['jurusan'],
                'nilai' => $row['rata_rata'],
                'jumlah_nilai' => $row['jumlah_nilai'],
            ]);
    }

    /**
     * @param  array<int, int>|null  $mapelIds
     * @return Collection<int, array{no: int, kode: string, nama: string, guru: string, jumlah_siswa: int, jumlah_nilai: int, rata_rata: float, tuntas: int, belum_tuntas: int, persen_tuntas: int}>
     */
    public function rekapPerMataPelajaran(
        ?string $tahunAjaran = null,
        ?string $semester = null,
        ?string $kelas = null,
        ?string $jurusan = null,
        ?array $mapelIds = null
    ): Collection {
        $baris = $this->queryNilai($tahunAjaran, $semester, $kelas, $jurusan, $mapelIds)
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

        return $baris->values()->map(fn ($row, $i) => $this->barisRekap($i + 1, $row, (string) $row->kode, (string) $row->nama)
            + ['guru' => $pengampu[$row->nama] ?? 'Belum ada guru pengampu']);
    }

    /**
     * Nama guru pengampu per mata pelajaran. Kalau belum ada, dikatakan
     * terbuka daripada menampilkan nama orang yang tidak relevan.
     *
     * @return array<string, string>
     */
    public function guruPengampu(): array
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

    // Guru

    /**
     * Kelas yang benar-benar diampu guru ini, diturunkan dari nilai yang pernah
     * ia input untuk mata pelajarannya. Sistem ini tidak punya tabel pemetaan
     * guru-ke-kelas, jadi nilai adalah satu-satunya bukti kelas yang diampu.
     *
     * @return Collection<int, array{kelas: string, jurusan: string}>
     */
    public function kelasDiampu(User $guru): Collection
    {
        return $this->queryNilai(null, null, null, null, $this->mapelIdsDiampu($guru))
            ->selectRaw('kelas, jurusan')
            ->distinct()
            ->get()
            ->sort(fn ($a, $b) => $this->bandingkanKelasJurusan((string) $a->jurusan, (string) $a->kelas, (string) $b->jurusan, (string) $b->kelas))
            ->values()
            ->map(fn ($row) => [
                'kelas' => Kelas::normalize((string) $row->kelas),
                'jurusan' => trim((string) $row->jurusan),
            ]);
    }

    /**
     * Rekap nilai per siswa pada satu periode, dengan pencarian nama/NIS dan
     * pagination. Hanya siswa yang benar-benar punya nilai pada lingkup filter
     * yang muncul, jadi daftar ini tidak pernah memuat siswa di luar mapel guru.
     *
     * @param  array<int, int>|null  $mapelIds
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function rekapPerSiswa(
        ?string $tahunAjaran = null,
        ?string $semester = null,
        ?string $kelas = null,
        ?string $jurusan = null,
        ?array $mapelIds = null,
        string $cari = '',
        int $perHalaman = 10
    ): LengthAwarePaginator {
        $halaman = $this->siswaPunyaNilai($tahunAjaran, $semester, $kelas, $jurusan, $mapelIds, $cari)
            ->paginate(max($perHalaman, 1))
            ->withQueryString();

        $nilai = $this->nilaiPerSiswa(
            $halaman->pluck('id')->all(),
            $tahunAjaran,
            $semester,
            $kelas,
            $jurusan,
            $mapelIds
        );

        return $halaman->through(
            fn (User $siswa) => $this->barisRekapSiswa($siswa, $nilai->get($siswa->id, collect()))
        );
    }

    /**
     * Versi tanpa pagination dari rekap per siswa. Dipakai ekspor supaya
     * seluruh baris ikut terunduh, bukan hanya satu halaman yang sedang tampil.
     *
     * @param  array<int, int>|null  $mapelIds
     * @return Collection<int, array<string, mixed>>
     */
    public function rekapPerSiswaSemua(
        ?string $tahunAjaran = null,
        ?string $semester = null,
        ?string $kelas = null,
        ?string $jurusan = null,
        ?array $mapelIds = null,
        string $cari = ''
    ): Collection {
        $siswa = $this->siswaPunyaNilai($tahunAjaran, $semester, $kelas, $jurusan, $mapelIds, $cari)->get();

        $nilai = $this->nilaiPerSiswa(
            $siswa->pluck('id')->all(),
            $tahunAjaran,
            $semester,
            $kelas,
            $jurusan,
            $mapelIds
        );

        return $siswa->values()
            ->map(fn (User $murid) => $this->barisRekapSiswa($murid, $nilai->get($murid->id, collect())));
    }

    /**
     * Siswa aktif yang punya nilai pada lingkup filter. Kata kunci mencocokkan
     * nama atau NIS, dan dibandingkan sebagai teks supaya tidak bergantung pada
     * jenis kolom di tiap basis data.
     *
     * @param  array<int, int>|null  $mapelIds
     * @return Builder<User>
     */
    protected function siswaPunyaNilai(
        ?string $tahunAjaran,
        ?string $semester,
        ?string $kelas,
        ?string $jurusan,
        ?array $mapelIds,
        string $cari
    ): Builder {
        $idSiswa = $this->queryNilai($tahunAjaran, $semester, $kelas, $jurusan, $mapelIds)
            ->select('user_id')
            ->distinct();

        return User::query()
            ->where('role', 'siswa')
            ->whereIn('id', $idSiswa)
            ->when($cari !== '', function (Builder $query) use ($cari) {
                $query->where(function (Builder $dalam) use ($cari) {
                    $dalam->where('name', 'like', '%'.$cari.'%')
                        ->orWhere('nis', 'like', '%'.$cari.'%');
                });
            })
            ->orderBy('kelas')
            ->orderBy('name');
    }

    /**
     * Baris nilai untuk sekumpulan siswa, dikelompokkan per siswa, memakai
     * filter periode/kelas/mapel yang sama supaya angka pada tiap baris konsisten
     * dengan tabel rekap lain di halaman yang sama.
     *
     * @param  array<int, int>  $idSiswa
     * @param  array<int, int>|null  $mapelIds
     * @return Collection<int, Collection<int, Nilai>>
     */
    protected function nilaiPerSiswa(
        array $idSiswa,
        ?string $tahunAjaran,
        ?string $semester,
        ?string $kelas,
        ?string $jurusan,
        ?array $mapelIds
    ): Collection {
        if ($idSiswa === []) {
            return collect();
        }

        return $this->queryNilai($tahunAjaran, $semester, $kelas, $jurusan, $mapelIds)
            ->whereIn('user_id', $idSiswa)
            ->get()
            ->groupBy('user_id');
    }

    /**
     * Bentuk satu baris rekap siswa: data identitas, nilai per mata pelajaran
     * (kunci = id mata pelajaran), lalu rata-rata dan ketuntasan.
     *
     * @param  Collection<int, Nilai>  $nilai
     * @return array<string, mixed>
     */
    protected function barisRekapSiswa(User $siswa, Collection $nilai): array
    {
        $perMapel = [];
        $jumlah = 0.0;
        $tuntas = 0;

        foreach ($nilai as $satu) {
            $perMapel[(int) $satu->mata_pelajaran_id] = $satu;
            $jumlah += (float) $satu->nilai;

            if ($satu->tuntas()) {
                $tuntas++;
            }
        }

        $banyak = $nilai->count();

        return [
            'id' => $siswa->id,
            'nis' => (string) $siswa->nis,
            'nama' => (string) $siswa->name,
            'kelas' => Kelas::normalize((string) $siswa->kelas),
            'jurusan' => trim((string) $siswa->jurusan),
            'nilai' => $perMapel,
            'jumlah_nilai' => $banyak,
            'rata_rata' => $banyak > 0 ? round($jumlah / $banyak, 1) : null,
            'tuntas' => $tuntas,
            'belum_tuntas' => $banyak - $tuntas,
        ];
    }

    /**
     * Siswa aktif yang berada di salah satu kelas yang diampu. Dipakai kartu
     * statistik Dashboard Guru.
     *
     * @param  Collection<int, array{kelas: string, jurusan: string}>  $kelasList
     * @return Collection<int, User>
     */
    public function siswaDiKelas(Collection $kelasList): Collection
    {
        if ($kelasList->isEmpty()) {
            return collect();
        }

        return User::query()
            ->where('role', 'siswa')
            ->get(['id', 'name', 'nis', 'kelas', 'jurusan', 'status', 'is_active'])
            ->filter(function (User $siswa) use ($kelasList) {
                $kelas = Kelas::normalize((string) $siswa->kelas);

                return $kelasList->contains(fn (array $ampu) => $ampu['kelas'] === $kelas
                    && ($ampu['jurusan'] === '' || $ampu['jurusan'] === trim((string) $siswa->jurusan)));
            })
            ->values();
    }

    // Siswa

    /**
     * Seluruh baris nilai milik satu siswa, diurutkan dari periode terbaru.
     *
     * @return Collection<int, Nilai>
     */
    public function nilaiSiswa(User $siswa): Collection
    {
        return Nilai::query()
            ->with('mataPelajaran')
            ->where('user_id', $siswa->id)
            ->get()
            ->sortByDesc(fn (Nilai $nilai) => Periode::urutan((string) $nilai->tahun_ajaran, (string) $nilai->semester))
            ->values();
    }

    /**
     * Ringkasan nilai siswa pada satu periode, beserta selisih dari periode
     * sebelumnya supaya kartu "Rekap Nilai" bisa menunjukkan arah.
     *
     * @return array{rata_rata: float|null, selisih: float|null, jumlah_mapel: int, tuntas: int, belum_tuntas: int}
     */
    public function ringkasanSiswa(User $siswa, ?string $tahunAjaran, ?string $semester): array
    {
        $periodeIni = $this->nilaiUntukPeriode($siswa, $tahunAjaran, $semester);

        if ($periodeIni->isEmpty()) {
            return [
                'rata_rata' => null,
                'selisih' => null,
                'jumlah_mapel' => 0,
                'tuntas' => 0,
                'belum_tuntas' => 0,
            ];
        }

        $rataRata = round((float) $periodeIni->avg(fn (Nilai $n) => (float) $n->nilai), 2);
        $tuntas = $periodeIni->filter(fn (Nilai $n) => $n->tuntas())->count();

        $sebelumnya = $this->periodeSebelumnya($tahunAjaran, $semester);
        $rataSebelumnya = null;

        if ($sebelumnya !== null) {
            $nilaiSebelumnya = $this->nilaiUntukPeriode($siswa, $sebelumnya[0], $sebelumnya[1]);

            if ($nilaiSebelumnya->isNotEmpty()) {
                $rataSebelumnya = round((float) $nilaiSebelumnya->avg(fn (Nilai $n) => (float) $n->nilai), 2);
            }
        }

        return [
            'rata_rata' => $rataRata,
            'selisih' => $rataSebelumnya === null ? null : round($rataRata - $rataSebelumnya, 2),
            'jumlah_mapel' => $periodeIni->unique('mata_pelajaran_id')->count(),
            'tuntas' => $tuntas,
            'belum_tuntas' => $periodeIni->count() - $tuntas,
        ];
    }

    /**
     * Garis perkembangan rata-rata nilai siswa lintas periode. Periode tanpa
     * nilai tetap dikirim sebagai null supaya jarak titik tidak dipadatkan.
     *
     * @return array{label: array<int, string>, rata_rata: array<int, float|null>, jumlah: array<int, int>}
     */
    public function perkembanganSiswa(User $siswa): array
    {
        $baris = Nilai::query()
            ->where('user_id', $siswa->id)
            ->selectRaw('tahun_ajaran, semester')
            ->selectRaw('count(*) as jumlah_nilai')
            ->selectRaw('avg(nilai) as rata_rata')
            ->groupBy('tahun_ajaran', 'semester')
            ->get()
            ->filter(fn ($row) => Periode::semesterValid($row->semester) && Periode::tahunAjaranValid($row->tahun_ajaran))
            ->sortBy(fn ($row) => Periode::urutan((string) $row->tahun_ajaran, (string) $row->semester))
            ->values();

        $perPeriode = [];
        $urutan = [];

        foreach ($baris as $row) {
            $perPeriode[Periode::label((string) $row->tahun_ajaran, (string) $row->semester)] = $row;
            $urutan[] = [(string) $row->tahun_ajaran, (string) $row->semester];
        }

        $urutan = array_slice($urutan, -self::MAKS_PERIODE_PERKEMBANGAN);

        $label = [];
        $rataRata = [];
        $jumlah = [];

        foreach ($this->rentangPeriode($urutan) as [$tahun, $semester]) {
            $kunci = Periode::label($tahun, $semester);
            $label[] = $kunci;
            $jumlah[] = (int) ($perPeriode[$kunci]->jumlah_nilai ?? 0);
            $rataRata[] = isset($perPeriode[$kunci]) ? round((float) $perPeriode[$kunci]->rata_rata, 1) : null;
        }

        return ['label' => $label, 'rata_rata' => $rataRata, 'jumlah' => $jumlah];
    }

    /**
     * Rekap nilai siswa per periode untuk halaman Riwayat Akademik.
     *
     * @return Collection<int, array{tahun_ajaran: string, semester: string, label: string, nilai: array<int, array{nama: string, nilai: float, kkm: float}>, rata_rata: float}>
     */
    public function rekapSiswaPerPeriode(User $siswa): Collection
    {
        return $this->nilaiSiswa($siswa)
            ->groupBy(fn (Nilai $nilai) => $nilai->tahun_ajaran.'|'.$nilai->semester)
            ->map(function (Collection $grup) {
                $pertama = $grup->first();

                return [
                    'tahun_ajaran' => (string) $pertama->tahun_ajaran,
                    'semester' => (string) $pertama->semester,
                    'label' => Periode::label((string) $pertama->tahun_ajaran, (string) $pertama->semester),
                    'nilai' => $grup->map(fn (Nilai $nilai) => [
                        'nama' => (string) ($nilai->mataPelajaran?->nama ?? '-'),
                        'nilai' => (float) $nilai->nilai,
                        'kkm' => (float) $nilai->kkm,
                        'tuntas' => $nilai->tuntas(),
                    ])->values()->all(),
                    'rata_rata' => round((float) $grup->avg(fn (Nilai $n) => (float) $n->nilai), 1),
                ];
            })
            ->sortByDesc(fn (array $row) => Periode::urutan($row['tahun_ajaran'], $row['semester']))
            ->values();
    }

    // Helper

    /**
     * @return Collection<int, Nilai>
     */
    protected function nilaiUntukPeriode(User $siswa, ?string $tahunAjaran, ?string $semester): Collection
    {
        return Nilai::query()
            ->where('user_id', $siswa->id)
            ->when($tahunAjaran !== null && $tahunAjaran !== '', fn (Builder $q) => $q->where('tahun_ajaran', $tahunAjaran))
            ->when($semester !== null && $semester !== '', fn (Builder $q) => $q->where('semester', $semester))
            ->get();
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    protected function periodeSebelumnya(?string $tahunAjaran, ?string $semester): ?array
    {
        if (! Periode::tahunAjaranValid($tahunAjaran) || ! Periode::semesterValid($semester)) {
            return null;
        }

        $urutan = Periode::urutan((string) $tahunAjaran, (string) $semester) - 1;
        $tahun = intdiv($urutan, 2);

        return [
            $tahun.'/'.($tahun + 1),
            $urutan % 2 === 0 ? Periode::SEMESTER_GANJIL : Periode::SEMESTER_GENAP,
        ];
    }

    /**
     * Isi celah di antara periode pertama dan terakhir yang punya nilai, tanpa
     * menambah periode di luar keduanya. Kalau tidak dibatasi seperti ini,
     * tahun ajaran terakhir ikut memunculkan semester Genap yang belum berjalan
     * sehingga grafik menyisakan satu kolom kosong di ujung kanan.
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
     * @return array{no: int, kode: string, nama: string, jumlah_siswa: int, jumlah_nilai: int, rata_rata: float, tuntas: int, belum_tuntas: int, persen_tuntas: int}
     */
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
     * Tahun ajaran yang benar-benar punya nilai, ditambah tahun berjalan,
     * diurutkan dari yang terbaru.
     *
     * @param  array<int, int>|null  $mapelIds
     * @return array<int, string>
     */
    public function tahunAjaranTersedia(?array $mapelIds = null): array
    {
        return Nilai::query()
            ->when($mapelIds !== null, function (Builder $query) use ($mapelIds) {
                if ($mapelIds === []) {
                    return $query->whereRaw('1 = 0');
                }

                return $query->whereIn('mata_pelajaran_id', $mapelIds);
            })
            ->distinct()
            ->pluck('tahun_ajaran')
            ->push(Periode::tahunAjaranSekarang())
            ->filter(fn ($tahun) => Periode::tahunAjaranValid($tahun))
            ->unique()
            ->sortByDesc(fn ($tahun) => Periode::urutan((string) $tahun, Periode::SEMESTER_GANJIL))
            ->values()
            ->all();
    }

    /** Sama dengan urutan Data Kelas: jurusan dulu, lalu kelas secara angka. */
    protected function bandingkanKelasJurusan(string $jurusanA, string $kelasA, string $jurusanB, string $kelasB): int
    {
        $jurusan = strcasecmp($jurusanA, $jurusanB);

        return $jurusan !== 0 ? $jurusan : StrukturKelas::bandingkanKelas($kelasA, $kelasB);
    }
}
