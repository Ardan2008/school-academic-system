<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\GuruAkademik;
use App\Services\NotifikasiAkademikService;
use App\Support\Kelas;
use App\Support\PerbandinganData;
use App\Support\Periode;
use App\Support\StrukturKelas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Input Nilai Guru.
 *
 * Guru hanya boleh menilai mapel dan rombel yang menjadi penugasannya.
 * Tiap baris menyimpan kelas/jurusan apa adanya
 * saat itu agar laporan lama tidak berubah saat siswa naik kelas. Simpan memakai
 * updateOrCreate pada kunci unik (user + mapel + tahun_ajaran + semester),
 * sehingga menekan Simpan dua kali tidak membuat baris ganda.
 */
class InputNilaiController extends Controller
{
    /** KKM yang dipakai kalau guru tidak mengisinya, mengikuti nilai umum rapor. */
    public const KKM_BAWAAN = 75.0;

    /** Batas rapor, dipakai server dan ditampilkan sebagai bantuan di layar. */
    public const NILAI_MINIMUM = 0.0;

    public const NILAI_MAKSIMUM = 100.0;

    public function __construct(
        private readonly NotifikasiAkademikService $notifikasi,
        private readonly GuruAkademik $akademik
    ) {}

    public function index(Request $request): View
    {
        $guru = $this->guru($request);
        $periode = $this->periodeDariRequest($request);

        $mapel = $this->akademik->mapelDiampu($guru);
        $siswa = $this->siswa($guru);
        $siswaRows = $siswa->map(fn (User $murid) => $this->barisSiswa($murid))->values();
        $kelas = $this->kelasOptionsUntukMapel($guru, $mapel, $siswaRows);
        $mapelAwalId = (int) $request->query('mapel_id', 0);
        $mapelTerpilih = null;
        if ($mapelAwalId > 0) {
            $mapelTerpilih = $mapel->first(fn (MataPelajaran $m) => (int) $m->id === $mapelAwalId);
            if (! $mapelTerpilih) {
                abort(403, 'Anda tidak memiliki akses untuk menginput nilai pada mata pelajaran ini.');
            }
        }

        return view('dashboard.guru.input-nilai.index', [
            'inputNilaiData' => [
                'periode' => $periode,
                'semesters' => Periode::SEMESTERS,
                'tahun_ajarans' => $this->tahunAjaranOptions(),
                'mapel' => $mapel->map(fn (MataPelajaran $m) => [
                    'id' => $m->id,
                    'kode' => $m->kode,
                    'nama' => $m->nama,
                ])->values(),
                'mapel_terpilih_id' => $mapelTerpilih?->id,
                'kelas' => $kelas,
                'kelas_semua' => $this->ratakanKelas($mapel, $kelas),
                'jurusan' => $siswa
                    ->pluck('jurusan')
                    ->filter(fn ($j) => $j !== null && trim((string) $j) !== '')
                    ->unique()
                    ->sort()
                    ->values(),
                'siswa' => $siswaRows,
                'nilai' => $this->nilaiUntuk($guru, $mapel),
                'kkm_bawaan' => self::KKM_BAWAAN,
                'batas' => [
                    'min' => self::NILAI_MINIMUM,
                    'maks' => self::NILAI_MAKSIMUM,
                ],
                'mapel_saya' => $guru->mata_pelajaran,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $guru = $this->guru($request);

        $data = $request->validate([
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajaran,id'],
            'tahun_ajaran' => ['required', 'string'],
            'semester' => ['required', Rule::in(Periode::SEMESTERS)],
            'kelas' => ['required', 'string', 'max:30'],
            'jurusan' => ['nullable', 'string', 'max:120'],
            'nilai' => ['required', 'array', 'min:1'],
            'nilai.*.user_id' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'nilai.*.nilai' => ['nullable', 'numeric', 'between:0,100'],
            'nilai.*.kkm' => ['nullable', 'numeric', 'between:0,100'],
        ], [
            'mata_pelajaran_id.required' => 'Mata pelajaran wajib dipilih.',
            'mata_pelajaran_id.exists' => 'Mata pelajaran tidak ditemukan.',
            'tahun_ajaran.required' => 'Tahun ajaran wajib dipilih.',
            'semester.required' => 'Semester wajib dipilih.',
            'semester.in' => 'Semester tidak dikenal.',
            'kelas.required' => 'Kelas wajib dipilih.',
            'kelas.max' => 'Kelas terlalu panjang.',
            'nilai.required' => 'Belum ada data siswa yang dikirim.',
            'nilai.min' => 'Belum ada siswa pada kelas yang dipilih.',
            'nilai.*.user_id.required' => 'Ada siswa yang tidak lengkap datanya.',
            'nilai.*.user_id.exists' => 'Ada siswa yang tidak ditemukan.',
            'nilai.*.user_id.distinct' => 'Ada siswa yang terkirim lebih dari sekali.',
            'nilai.*.nilai.numeric' => 'Nilai harus berupa angka.',
            'nilai.*.nilai.between' => 'Nilai harus berada di antara 0 sampai 100.',
            'nilai.*.kkm.numeric' => 'KKM harus berupa angka.',
            'nilai.*.kkm.between' => 'KKM harus berada di antara 0 sampai 100.',
        ]);

        $mapel = MataPelajaran::query()->findOrFail($data['mata_pelajaran_id']);

        if (! $this->diampu($guru, $mapel)) {
            throw ValidationException::withMessages([
                'mata_pelajaran_id' => 'Anda tidak mengampu mata pelajaran ini.',
            ]);
        }

        if (! Periode::tahunAjaranValid($data['tahun_ajaran'])) {
            throw ValidationException::withMessages([
                'tahun_ajaran' => 'Format tahun ajaran tidak dikenal (contoh 2025/2026).',
            ]);
        }

        $kelas = Kelas::normalize((string) $data['kelas']);

        if (! Kelas::isValid($kelas)) {
            throw ValidationException::withMessages(['kelas' => Kelas::formatMessage()]);
        }

        $jurusan = trim((string) ($data['jurusan'] ?? ''));

        if ($this->akademik->sudahDiatur($guru)
            && ! $this->akademik->bolehMengajarEksplisit($guru, $mapel->id, $kelas, $jurusan)) {
            throw ValidationException::withMessages([
                'kelas' => 'Anda tidak mengajar mata pelajaran ini di kelas tersebut.',
            ]);
        }

        $tahunAjaran = (string) $data['tahun_ajaran'];
        $semester = (string) $data['semester'];

        $idSiswa = collect($data['nilai'])->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $siswa = User::query()->whereIn('id', $idSiswa)->get()->keyBy('id');

        $disimpan = 0;
        $dihapus = 0;

        $nilaiBerubah = [];

        DB::transaction(function () use (
            $data,
            $siswa,
            $mapel,
            $kelas,
            $jurusan,
            $tahunAjaran,
            $semester,
            &$disimpan,
            &$dihapus,
            &$nilaiBerubah
        ): void {
            foreach ($data['nilai'] as $baris) {
                $murid = $siswa->get((int) $baris['user_id']);

                if (! $murid instanceof User || $murid->role !== 'siswa') {
                    throw ValidationException::withMessages([
                        'nilai' => 'Ada siswa yang tidak valid pada kelas ini.',
                    ]);
                }

                if (Kelas::normalize((string) $murid->kelas) !== $kelas) {
                    throw ValidationException::withMessages([
                        'nilai' => 'Ada siswa yang bukan anggota kelas yang dipilih.',
                    ]);
                }

                if ($jurusan !== '' && trim((string) $murid->jurusan) !== $jurusan) {
                    throw ValidationException::withMessages([
                        'nilai' => 'Ada siswa yang bukan anggota kelas yang dipilih.',
                    ]);
                }

                $kunci = [
                    'user_id' => $murid->id,
                    'mata_pelajaran_id' => $mapel->id,
                    'tahun_ajaran' => $tahunAjaran,
                    'semester' => $semester,
                ];

                $nilai = $baris['nilai'] ?? null;
                $lama = Nilai::query()->where($kunci)->first(['id', 'kelas', 'jurusan', 'nilai', 'kkm']);

                // Nilai kosong berarti guru mengosongkan baris ini, jadi catatan
                // lama (kalau ada) dihapus supaya tidak ada nilai tertinggal.
                if ($nilai === null || $nilai === '') {
                    if ($lama !== null) {
                        $dihapus += Nilai::query()->where($kunci)->delete();
                    }

                    continue;
                }

                $kkm = $baris['kkm'] ?? null;

                if ($kkm === null || $kkm === '') {
                    $kkm = self::KKM_BAWAAN;
                }

                $nilaiBaru = round((float) $nilai, 2);
                $kkmBaru = round((float) $kkm, 2);

                $perubahan = [
                    'kelas' => Kelas::normalize((string) $murid->kelas),
                    'jurusan' => trim((string) $murid->jurusan),
                    'nilai' => $nilaiBaru,
                    'kkm' => $kkmBaru,
                ];

                if ($lama !== null && PerbandinganData::identik($lama, $perubahan, [
                    'nilai' => fn ($nilai, Nilai $model) => $model->nilai === null ? null : (float) $model->nilai,
                    'kkm' => fn ($kkm, Nilai $model) => $model->kkm === null ? null : (float) $model->kkm,
                ])) {
                    continue;
                }

                Nilai::query()->updateOrCreate($kunci, $perubahan);

                // Baris baru atau nilai/kkm yang benar-benar bergeser layak
                // diberitahukan; menyimpan angka yang sama bukan perubahan.
                if ($lama === null
                    || $lama->nilai === null
                    || $lama->kkm === null
                    || ! PerbandinganData::sama((float) $lama->nilai, $nilaiBaru)
                    || ! PerbandinganData::sama((float) $lama->kkm, $kkmBaru)) {
                    $nilaiBerubah[] = [$murid, $nilaiBaru, $kkmBaru];
                }

                $disimpan++;
            }
        });

        // Dikirim setelah transaksi berhasil supaya siswa tidak menerima
        // notifikasi untuk nilai yang gagal tersimpan.
        $notifikasiTerkirim = 0;

        foreach ($nilaiBerubah as [$murid, $nilaiBaru, $kkmBaru]) {
            if ($this->notifikasi->nilaiDiperbarui(
                $murid,
                $mapel->id,
                $nilaiBaru,
                $kkmBaru,
                $tahunAjaran,
                $semester
            ) !== null) {
                $notifikasiTerkirim++;
            }
        }

        if ($disimpan > 0 || $dihapus > 0) {
            app(ActivityLogger::class)->record(
                ActivityLog::TIPE_INPUT_NILAI,
                sprintf(
                    'Menyimpan nilai %s kelas %s (%s, %s): %d nilai disimpan, %d nilai dikosongkan.',
                    $mapel->nama,
                    $kelas,
                    $tahunAjaran,
                    $semester,
                    $disimpan,
                    $dihapus
                ),
                $guru
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => $this->pesanSimpan($mapel, $disimpan, $dihapus),
            'data' => [
                'mata_pelajaran' => [
                    'id' => $mapel->id,
                    'nama' => $mapel->nama,
                ],
                'disimpan' => $disimpan,
                'dihapus' => $dihapus,
                'notifikasi_nilai' => $notifikasiTerkirim,
            ],
        ]);
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
     * @return array{tahun_ajaran: string, semester: string}
     */
    protected function periodeDariRequest(Request $request): array
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

    protected function diampu(User $guru, MataPelajaran $mapel): bool
    {
        return $this->akademik->mapelDiampu($guru)->contains(fn (MataPelajaran $m) => $m->id === $mapel->id);
    }

    /**
     * Siswa aktif yang berada di rombel yang diampu guru. Siswa lulus atau
     * nonaktif tidak lagi dinilai, dan siswa di luar cakupan jadwal/nilai guru
     * tidak ikut muncul di layar input.
     *
     * @return Collection<int, User>
     */
    protected function siswa(User $guru): Collection
    {
        $kelasList = $this->akademik->kelasDiampu($guru);

        return User::query()
            ->where('role', 'siswa')
            ->where('status', User::STATUS_AKTIF)
            ->whereNotNull('kelas')
            ->where('kelas', '!=', '')
            ->orderBy('kelas')
            ->orderBy('name')
            ->get(['id', 'name', 'nis', 'kelas', 'jurusan'])
            ->filter(fn (User $siswa) => $kelasList->isEmpty() || $this->akademik->cocokRombel($siswa, $kelasList))
            ->values();
    }

    /**
     * @return array{id: int, nama: string, nis: string|null, kelas: string|null, kelas_normal: string, tingkat: string|null, jurusan: string|null}
     */
    protected function barisSiswa(User $user): array
    {
        return [
            'id' => $user->id,
            'nama' => $user->name,
            'nis' => $user->nis,
            'kelas' => $user->kelas,
            'kelas_normal' => Kelas::normalize((string) $user->kelas),
            'tingkat' => Kelas::tingkat($user->kelas),
            'jurusan' => $user->jurusan,
        ];
    }

    /**
     * Nilai untuk mapel dan rombel yang masih menjadi cakupan guru. Semua
     * periode ikut dikirim supaya layar bisa berpindah semester tanpa memuat ulang halaman.
     *
     * @param  Collection<int, MataPelajaran>  $mapel
     * @return Collection<int, array<string, mixed>>
     */
    protected function nilaiUntuk(User $guru, Collection $mapel): Collection
    {
        $ids = $mapel->pluck('id')->all();

        if ($ids === []) {
            return collect();
        }

        $ditugaskan = $this->akademik->sudahDiatur($guru);
        $pasangan = $ditugaskan ? $this->akademik->pasanganEksplisit($guru) : collect();

        return Nilai::query()
            ->whereIn('mata_pelajaran_id', $ids)
            ->get()
            ->filter(function (Nilai $nilai) use ($ditugaskan, $pasangan): bool {
                if (! $ditugaskan) {
                    return true;
                }

                return $this->akademik->cocokPasangan(
                    $pasangan,
                    (int) $nilai->mata_pelajaran_id,
                    (string) $nilai->kelas,
                    $nilai->jurusan
                );
            })
            ->map(fn (Nilai $nilai) => [
                'id' => $nilai->id,
                'user_id' => $nilai->user_id,
                'mata_pelajaran_id' => $nilai->mata_pelajaran_id,
                'kelas' => $nilai->kelas,
                'jurusan' => $nilai->jurusan,
                'tahun_ajaran' => $nilai->tahun_ajaran,
                'semester' => $nilai->semester,
                'nilai' => $nilai->nilai,
                'kkm' => $nilai->kkm,
            ])
            ->values();
    }

    /**
     * Tahun ajaran yang bisa dipilih: beberapa tahun terakhir ditambah tahun
     * yang benar-benar ada di tabel nilai, supaya periode lama tidak hilang.
     *
     * @return array<int, string>
     */
    protected function tahunAjaranOptions(): array
    {
        $sekarang = Periode::tahunAjaranSekarang();
        $awalSekarang = Periode::tahunAwal($sekarang) ?? (int) now()->format('Y');

        $daftar = [];

        for ($i = 0; $i < 4; $i++) {
            $awal = $awalSekarang - $i;
            $daftar[] = $awal.'/'.($awal + 1);
        }

        $dariNilai = Nilai::query()
            ->distinct()
            ->pluck('tahun_ajaran')
            ->filter(fn ($tahun) => Periode::tahunAjaranValid($tahun))
            ->all();

        return collect($daftar)
            ->merge($dariNilai)
            ->unique()
            ->sortByDesc(fn ($tahun) => Periode::urutan((string) $tahun, Periode::SEMESTER_GANJIL))
            ->values()
            ->all();
    }

    protected function kelasOptionsUntukMapel(User $guru, Collection $mapel, Collection $siswaRows): array
    {
        $hasil = [];

        foreach ($mapel as $item) {
            $rombels = $this->akademik->pasanganUntukMapel($guru, (int) $item->id)
                ->map(fn (array $pasangan) => [
                    'kelas' => $pasangan['kelas'],
                    'jurusan' => $pasangan['jurusan'],
                ]);

            $tersedia = $siswaRows->filter(function (array $baris) use ($rombels): bool {
                return $rombels->contains(fn (array $rombel): bool => $rombel['kelas'] === $baris['kelas_normal']
                    && ($rombel['jurusan'] === '' || $rombel['jurusan'] === (string) ($baris['jurusan'] ?? '')));
            });

            $hasil[(int) $item->id] = $this->kelasOptions($tersedia);
        }

        return $hasil;
    }

    /**
     * @param  array<int, array<int, array{kelas: string, jurusan: string}>>  $kelasPerMapel
     * @return array<int, array{mata_pelajaran_id: int, mata_pelajaran_nama: string, kelas: string, jurusan: string}>
     */
    protected function ratakanKelas(Collection $mapel, array $kelasPerMapel): array
    {
        return $mapel
            ->flatMap(fn (MataPelajaran $item) => collect($kelasPerMapel[(int) $item->id] ?? [])
                ->map(fn (array $kelas) => [
                    'mata_pelajaran_id' => (int) $item->id,
                    'mata_pelajaran_nama' => (string) $item->nama,
                    'kelas' => $kelas['kelas'],
                    'jurusan' => $kelas['jurusan'],
                ]))
            ->unique(fn (array $baris) => $baris['mata_pelajaran_id'].'|'.$baris['jurusan'].'|'.$baris['kelas'])
            ->sort(fn (array $a, array $b) => strcasecmp($a['jurusan'], $b['jurusan'])
                ?: StrukturKelas::bandingkanKelas($a['kelas'], $b['kelas'])
                ?: strcasecmp($a['mata_pelajaran_nama'], $b['mata_pelajaran_nama']))
            ->values()
            ->all();
    }

    /**
     * Pilihan kelas beserta jurusannya, dibangun dari siswa yang benar-benar
     * ada supaya tidak pernah menampilkan kelas kosong. Urutannya sama dengan
     * Data Kelas: jurusan dulu, lalu kelas secara angka.
     *
     * @param  Collection<int, array<string, mixed>>  $siswaRows
     * @return array<int, array{kelas: string, jurusan: string}>
     */
    protected function kelasOptions(Collection $siswaRows): array
    {
        return $siswaRows
            ->filter(fn (array $baris) => Kelas::isValid((string) $baris['kelas']))
            ->map(fn (array $baris) => [
                'kelas' => Kelas::normalize((string) $baris['kelas']),
                'jurusan' => (string) ($baris['jurusan'] ?? ''),
            ])
            ->unique(fn (array $kelas) => $kelas['jurusan'].'|'.$kelas['kelas'])
            ->sort(fn (array $a, array $b) => strcasecmp($a['jurusan'], $b['jurusan'])
                ?: StrukturKelas::bandingkanKelas($a['kelas'], $b['kelas']))
            ->values()
            ->all();
    }

    protected function pesanSimpan(MataPelajaran $mapel, int $disimpan, int $dihapus): string
    {
        if ($disimpan === 0 && $dihapus === 0) {
            return 'Tidak ada perubahan nilai yang perlu disimpan.';
        }

        $bagian = [];

        if ($disimpan > 0) {
            $bagian[] = "Nilai {$mapel->nama} untuk {$disimpan} siswa berhasil disimpan.";
        }

        if ($dihapus > 0) {
            $bagian[] = "{$dihapus} nilai dikosongkan.";
        }

        return implode(' ', $bagian);
    }
}
