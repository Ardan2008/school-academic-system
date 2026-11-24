<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guru\PenyesuaianJadwalRequest;
use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\SesiJamBelajar;
use App\Models\User;
use App\Services\GuruAkademik;
use App\Services\JadwalGuruService;
use App\Services\JadwalManajemenService;
use App\Services\JamBelajar;
use App\Services\PenugasanMengajarService;
use App\Services\XlsxExportService;
use App\Support\Kelas;
use App\Support\Periode;
use App\Support\StrukturKelas;
use App\Support\Waktu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JadwalController extends Controller
{
    public function __construct(
        private readonly GuruAkademik $akademik,
        private readonly JamBelajar $jamBelajar,
        private readonly JadwalGuruService $jadwalGuru,
        private readonly JadwalManajemenService $manajemen,
        private readonly PenugasanMengajarService $penugasan,
    ) {}

    public function index(Request $request): View
    {
        $admin = $this->admin($request);
        $periode = $this->periode($request);
        $konfigurasiJam = $this->jamBelajar->konfigurasi();
        $hariAktif = $this->jamBelajar->hariAktif($konfigurasiJam);
        $analysis = $this->jadwalGuru->analisisSemua($periode, $konfigurasiJam);

        $filters = $this->filters($request);
        $filterTampil = $filters;
        $filterTampil['cari'] = '';
        $items = $this->filterItems($analysis['items'], $filterTampil, $hariAktif);
        $itemsTampil = $items;
        $kelas = collect($items)
            ->map(fn (array $item): array => [
                'kelas' => Kelas::normalize((string) $item['kelas']),
                'jurusan' => trim((string) $item['jurusan']),
            ])
            ->unique(fn (array $item): string => $item['jurusan'].'|'.$item['kelas'])
            ->sort(fn (array $a, array $b): int => strcasecmp($a['jurusan'], $b['jurusan'])
                ?: StrukturKelas::bandingkanKelas($a['kelas'], $b['kelas']))
            ->values();
        $perHariTampil = collect($hariAktif)
            ->mapWithKeys(fn (string $nama) => [
                $nama => collect($itemsTampil)->where('hari', $nama)->values()->all(),
            ]);

        $saran = $this->filterSaran($analysis['saran'], $filters);
        $bentrokGroups = $this->filterBentrok($analysis['bentrok'], $filters);
        $catatanPerhatian = $analysis['catatan'];

        $guruOptions = User::query()
            ->where('role', 'guru')
            ->where('status', 'Aktif')
            ->orderBy('name')
            ->get(['id', 'name']);

        $mapelOptions = MataPelajaran::query()->orderBy('nama')->get(['id', 'nama', 'kode']);
        $mapelById = $mapelOptions->keyBy(fn (MataPelajaran $item) => (int) $item->id);

        $rombelOptions = collect($analysis['items'])
            ->map(fn (array $item) => [
                'value' => $item['rombel_key'],
                'label' => $item['kelas'].($item['jurusan'] !== '' ? ' · '.$item['jurusan'] : ''),
            ])
            ->unique('value')
            ->sortBy('label')
            ->values();

        $penugasanOptions = $guruOptions
            ->map(function (User $guru) use ($mapelById): array {
                $pasangan = $guru->penugasan_diatur
                    ? $this->penugasan->pasangan($guru)
                    : $this->akademik->pasanganMapelRombel($guru);

                return [
                    'guru_id' => (int) $guru->id,
                    'guru' => (string) $guru->name,
                    'pairs' => $pasangan
                        ->map(function (array $item) use ($mapelById): array {
                            $namaMapel = $mapelById->get((int) $item['mata_pelajaran_id'])?->nama ?? 'Mata Pelajaran';

                            return [
                                'mapel_id' => (int) $item['mata_pelajaran_id'],
                                'mapel' => $namaMapel,
                                'rombel' => $item['kelas'].'|'.$item['jurusan'],
                                'label' => $namaMapel.' — '.$item['kelas']
                                    .($item['jurusan'] !== '' ? ' · '.$item['jurusan'] : ' · Semua Jurusan'),
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values();

        $editId = trim((string) $request->query('edit', ''));
        $formJadwal = ctype_digit($editId)
            ? JadwalPelajaran::with(['guru', 'mataPelajaran'])->find((int) $editId)
            : null;
        $mapelForm = $mapelById->get((int) ($formJadwal?->mata_pelajaran_id ?? 0));

        $hari = in_array($filters['hari'], $hariAktif, true) ? $filters['hari'] : null;
        $hariIni = JadwalPelajaran::hariIni();
        $sesiOptions = $this->jamBelajar->opsiPelajaran();
        $sesiGrid = $this->jamBelajar->sesi()->values()->all();

        $sekarang = Waktu::zona(now())->locale('id');
        $senin = $sekarang->copy()->startOfWeek(Carbon::MONDAY);
        $tanggalPekan = collect($hariAktif)
            ->mapWithKeys(function (string $nama) use ($senin): array {
                $posisi = (int) array_search($nama, JadwalPelajaran::HARI, true);

                return [$nama => $senin->copy()->addDays($posisi)->translatedFormat('d M')];
            })
            ->all();

        $stats = [
            [
                'key' => 'total',
                'label' => 'Total Jadwal',
                'value' => count($itemsTampil),
                'icon' => 'calendar-days',
                'warna' => '#1A6BDB',
                'latar' => '#E8F1FF',
            ],
            [
                'key' => 'kelas',
                'label' => 'Rombel',
                'value' => collect($itemsTampil)->pluck('rombel_key')->filter()->unique()->count(),
                'icon' => 'school',
                'warna' => '#0B6554',
                'latar' => '#DCF4EE',
            ],
            [
                'key' => 'mapel',
                'label' => 'Mata Pelajaran',
                'value' => collect($itemsTampil)->pluck('mapel_id')->filter()->unique()->count(),
                'icon' => 'book-open',
                'warna' => '#1B4F9E',
                'latar' => '#E8F1FF',
            ],
            [
                'key' => 'guru',
                'label' => 'Guru Diampu',
                'value' => collect($itemsTampil)->pluck('guru_id')->filter()->unique()->count(),
                'icon' => 'users',
                'warna' => '#7A4300',
                'latar' => '#FFF1D6',
            ],
        ];

        return view('dashboard.admin.jadwal-mengajar.index', [
            'admin' => $admin,
            'periode' => $periode,
            'periodeOptions' => $this->periodeOptions(),
            'filters' => $filters,
            'mode' => 'admin',
            'bisaKelola' => true,
            'items' => $items,
            'kelas' => $kelas,
            'analysis' => $analysis,
            'saran' => $saran,
            'bentrokGroups' => $bentrokGroups,
            'catatanPerhatian' => $catatanPerhatian,
            'hariAktif' => $hariAktif,
            'hari' => $hari,
            'hariIni' => $hariIni,
            'konfigurasiJam' => $konfigurasiJam,
            'guruOptions' => $guruOptions,
            'mapel' => $mapelOptions,
            'mapelForm' => $mapelForm,
            'mapelOptions' => $mapelOptions,
            'rombelOptions' => $rombelOptions,
            'penugasanOptions' => $penugasanOptions,
            'formJadwal' => $formJadwal,
            'sesiOptions' => $sesiOptions,
            'sesiGrid' => $sesiGrid,
            'jamOperasional' => [
                'mulai' => $konfigurasiJam->jamMasukLabel(),
                'selesai' => $konfigurasiJam->jamSelesaiLabel(),
            ],
            'pekanLabel' => $this->labelPekan($senin, $hariAktif),
            'tanggalPekan' => $tanggalPekan,
            'todayIso' => $sekarang->toDateString(),
            'pekanStartIso' => $senin->toDateString(),
            'nowLabel' => $sekarang->format('H:i'),
            'stats' => $stats,
            'bukaModal' => $formJadwal !== null || $request->query('tambah') === '1',
            'routeNames' => [
                'index' => 'dashboard.admin.jadwal-mengajar.index',
                'export' => 'dashboard.admin.jadwal-mengajar.export',
                'store' => 'dashboard.admin.jadwal-mengajar.store',
                'update' => 'dashboard.admin.jadwal-mengajar.update',
                'destroy' => 'dashboard.admin.jadwal-mengajar.destroy',
                'intent' => 'dashboard.admin.jadwal-mengajar.intent',
            ],
        ]);
    }

    public function export(Request $request): Response
    {
        $this->admin($request);
        $periode = $this->periode($request);
        $konfigurasiJam = $this->jamBelajar->konfigurasi();
        $hariAktif = $this->jamBelajar->hariAktif($konfigurasiJam);
        $filters = $this->filters($request);

        $analysis = $this->jadwalGuru->analisisSemua($periode, $konfigurasiJam);
        $items = collect($this->filterItems($analysis['items'], $filters, $hariAktif))
            ->unique('id')
            ->values();

        $sekarang = Waktu::zona(now())->locale('id');
        $senin = $sekarang->copy()->startOfWeek(Carbon::MONDAY);
        $tanggalPekan = collect($hariAktif)
            ->mapWithKeys(function (string $nama) use ($senin): array {
                $posisi = (int) array_search($nama, JadwalPelajaran::HARI, true);

                return [$nama => $senin->copy()->addDays($posisi)->translatedFormat('d M')];
            })
            ->all();

        $baris = $items
            ->map(fn (array $item, int $indeks): array => [
                $indeks + 1,
                (string) $item['guru'],
                (string) $item['hari'],
                (string) ($tanggalPekan[$item['hari']] ?? '-'),
                (string) $item['sesi_label'],
                (string) $item['mulai'],
                (string) $item['selesai'],
                (string) $item['mapel'],
                (string) $item['kelas'],
                (string) $item['jurusan'],
                (string) $item['ruang'],
                (string) $item['status_label'],
            ])
            ->all();

        $judulSeri = [
            'No',
            'Guru',
            'Hari',
            'Tanggal',
            'Sesi',
            'Jam Mulai',
            'Jam Selesai',
            'Mata Pelajaran',
            'Kelas',
            'Jurusan',
            'Ruang',
            'Status',
        ];

        $keterangan = 'Periode '.Periode::label($periode['tahun_ajaran'], $periode['semester'])
            .' · Diunduh '.$sekarang->format('d M Y H:i')
            .' · '.$items->count().' jadwal'
            .($filters['guru_id'] > 0 ? ' · Guru dipilih' : '')
            .($filters['hari'] !== '' ? ' · '.$filters['hari'] : '');

        $response = app(XlsxExportService::class)->download(
            'jadwal-mengajar-admin-'.now()->format('Ymd-His').'.xlsx',
            'Jadwal Mengajar',
            $judulSeri,
            $baris,
            [
                'subjudul' => $keterangan,
                'sheet' => 'Jadwal Mengajar',
                'text_columns' => [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
            ]
        );
        $response->headers->set('Cache-Control', 'no-store, no-cache');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Total-Jadwal', (string) $items->count());

        return $response;
    }

    public function store(Request $request): RedirectResponse
    {
        $admin = $this->admin($request);
        $guru = $this->guruTarget($request);
        $periode = $this->periodeMutasi($request);

        $validator = DB::transaction(function () use ($request, $guru, $periode, $admin) {
            $this->manajemen->kunciHari($periode, (string) $request->input('hari', ''));
            $validator = $this->manajemen->validator($request, $guru, null, $periode);

            if ($validator->fails()) {
                return $validator;
            }

            $this->manajemen->simpan($request, $guru, $admin, $periode);

            return $validator;
        });

        if ($validator->errors()->isNotEmpty()) {
            return redirect()->route('dashboard.admin.jadwal-mengajar.index', array_filter([
                'tambah' => 1,
                'tahun_ajaran' => $periode['tahun_ajaran'],
                'semester' => $periode['semester'],
            ]))
                ->withErrors($validator->errors())
                ->withInput();
        }

        return redirect()->route('dashboard.admin.jadwal-mengajar.index')
            ->with('success', 'Jadwal mengajar berhasil ditambahkan.');
    }

    public function update(Request $request, JadwalPelajaran $jadwal): RedirectResponse
    {
        $admin = $this->admin($request);
        $guru = $this->guruTarget($request, $jadwal);
        $periode = $this->periodeMutasi($request);

        $validator = DB::transaction(function () use ($request, $guru, $jadwal, $periode, $admin) {
            $this->manajemen->kunciHari($periode, (string) $request->input('hari', ''));
            $validator = $this->manajemen->validator($request, $guru, $jadwal, $periode);

            if ($validator->fails()) {
                return $validator;
            }

            $this->manajemen->ubah($request, $guru, $jadwal, $admin, $periode);

            return $validator;
        });

        if ($validator->errors()->isNotEmpty()) {
            return redirect()->route('dashboard.admin.jadwal-mengajar.index', array_filter([
                'edit' => $jadwal->id,
                'tahun_ajaran' => $periode['tahun_ajaran'],
                'semester' => $periode['semester'],
            ]))
                ->withErrors($validator->errors())
                ->withInput();
        }

        return redirect()->route('dashboard.admin.jadwal-mengajar.index')
            ->with('success', 'Jadwal mengajar berhasil diperbarui.');
    }

    public function destroy(Request $request, JadwalPelajaran $jadwal): RedirectResponse|JsonResponse
    {
        $admin = $this->admin($request);

        DB::transaction(function () use ($jadwal, $admin): void {
            $periode = [
                'tahun_ajaran' => (string) $jadwal->tahun_ajaran,
                'semester' => (string) $jadwal->semester,
            ];
            $this->manajemen->kunciHari($periode, (string) $jadwal->hari);
            $this->manajemen->hapus($jadwal, $admin);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Jadwal mengajar berhasil dihapus.',
                'data' => ['id' => $jadwal->id],
            ]);
        }

        return redirect()->route('dashboard.admin.jadwal-mengajar.index')
            ->with('success', 'Jadwal mengajar berhasil dihapus.');
    }

    public function intent(Request $request): JsonResponse
    {
        $admin = $this->admin($request);
        $validator = Validator::make(
            $request->all(),
            PenyesuaianJadwalRequest::aturan(),
            PenyesuaianJadwalRequest::pesan(),
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $intent = trim((string) $request->input('intent', ''));
        $periode = $this->periodeMutasi($request);

        if ($intent === 'cek-bentrok') {
            $guru = $this->guruTarget($request);
            $jadwalId = (int) $request->input('jadwal_id', 0);
            $jadwal = $jadwalId > 0
                ? JadwalPelajaran::find($jadwalId)
                : null;

            if ($jadwalId > 0 && $jadwal === null) {
                abort(404);
            }

            $validatorJadwal = $this->manajemen->validator($request, $guru, $jadwal, $periode);
            $sesi = SesiJamBelajar::find((int) $request->input('sesi_id', 0));
            $hasil = $this->jadwalGuru->cekBentrok([
                'periode' => $periode,
                'hari' => (string) $request->input('hari', ''),
                'rombel' => (string) $request->input('rombel', ''),
                'ruang' => (string) $request->input('ruang', ''),
                'jam_mulai' => $sesi === null ? '' : substr((string) $sesi->jam_mulai, 0, 5),
                'jam_selesai' => $sesi === null ? '' : substr((string) $sesi->jam_selesai, 0, 5),
                'sesi_label' => $sesi === null
                    ? 'Sesi —'
                    : 'Sesi '.$this->jamBelajar->nomorPelajaran($sesi).' · '.$sesi->waktuLabel(),
            ], $guru, $jadwal?->id);

            if ($validatorJadwal->fails()) {
                $hasil['aman'] = false;
                $hasil['pesan'] = $validatorJadwal->errors()->first();
            }

            return response()->json(['status' => 'success', 'data' => $hasil]);
        }

        try {
            if ($intent === 'sesuaikan-massal') {
                $hasil = $this->jadwalGuru->terapkanPerubahanAdmin(
                    $request->input('perubahan', []),
                    $periode,
                    $admin,
                );

                return response()->json([
                    'status' => 'success',
                    'message' => $hasil['jumlah'].' perubahan berhasil diterapkan.',
                    'data' => $hasil,
                ]);
            }

            $hasil = $this->jadwalGuru->batalkanPerubahanAdmin((string) $request->input('batch', ''), $admin);

            return response()->json([
                'status' => 'success',
                'message' => $hasil['jumlah'].' jadwal berhasil dikembalikan.',
                'data' => $hasil,
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->errors()[array_key_first($exception->errors())][0] ?? 'Perubahan tidak valid.',
                'errors' => $exception->errors(),
            ], 422);
        }
    }

    private function admin(Request $request): User
    {
        $admin = $request->user();

        if (! $admin instanceof User || $admin->role !== 'admin') {
            abort(403);
        }

        return $admin;
    }

    private function guruTarget(Request $request, ?JadwalPelajaran $jadwal = null): User
    {
        $id = $jadwal !== null
            ? (int) $request->input('guru_id', $jadwal->guru_id)
            : (int) $request->input('guru_id', 0);
        $guru = User::query()->findOrFail($id);

        if ($guru->role !== 'guru') {
            abort(403);
        }

        return $guru;
    }

    /** @return array{tahun_ajaran: string, semester: string} */
    private function periode(Request $request): array
    {
        $tahunAjaran = trim((string) $request->input('tahun_ajaran', ''));
        $semester = trim((string) $request->input('semester', ''));

        if (Periode::tahunAjaranValid($tahunAjaran) && Periode::semesterValid($semester)) {
            return ['tahun_ajaran' => $tahunAjaran, 'semester' => $semester];
        }

        return Periode::periodeSekarang();
    }

    /** @return array{tahun_ajaran: string, semester: string} */
    private function periodeMutasi(Request $request): array
    {
        $tahunAjaran = trim((string) $request->input('tahun_ajaran', ''));
        $semester = trim((string) $request->input('semester', ''));

        if ($tahunAjaran === '' && $semester === '') {
            return Periode::periodeSekarang();
        }

        if (Periode::tahunAjaranValid($tahunAjaran) && Periode::semesterValid($semester)) {
            return ['tahun_ajaran' => $tahunAjaran, 'semester' => $semester];
        }

        throw ValidationException::withMessages([
            'tahun_ajaran' => Periode::formatMessage(),
        ]);
    }

    /** @return array<int, array{tahun_ajaran: string, semester: string, label: string}> */
    private function periodeOptions(): array
    {
        $sekarang = Periode::periodeSekarang();
        $tahunAwal = (int) Periode::tahunAwal($sekarang['tahun_ajaran']);
        $daftar = [];

        foreach (range($tahunAwal - 2, $tahunAwal + 1) as $tahun) {
            $tahunAjaran = $tahun.'/'.($tahun + 1);

            foreach (Periode::SEMESTERS as $semester) {
                $daftar[] = [
                    'tahun_ajaran' => $tahunAjaran,
                    'semester' => $semester,
                    'label' => Periode::label($tahunAjaran, $semester),
                ];
            }
        }

        return $daftar;
    }

    /** @return array{guru_id: int, rombel: string, mapel_id: int, ruang: string, hari: string, status: string, cari: string} */
    private function filters(Request $request): array
    {
        return [
            'guru_id' => max(0, (int) $request->query('guru_id', 0)),
            'rombel' => trim((string) $request->query('rombel', '')),
            'mapel_id' => max(0, (int) $request->query('mapel_id', 0)),
            'ruang' => trim((string) $request->query('ruang', '')),
            'hari' => trim((string) $request->query('hari', '')),
            'status' => trim((string) $request->query('status', '')),
            'cari' => trim((string) $request->query('cari', '')),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array{guru_id: int, rombel: string, mapel_id: int, ruang: string, hari: string, status: string, cari: string}  $filters
     * @param  array<int, string>  $hariAktif
     * @return array<int, array<string, mixed>>
     */
    private function filterItems(array $items, array $filters, array $hariAktif): array
    {
        $cari = strtolower((string) preg_replace('/\s+/', ' ', trim($filters['cari'])));

        return array_values(array_filter($items, function (array $item) use ($filters, $hariAktif, $cari): bool {
            if ($filters['guru_id'] > 0 && (int) $item['guru_id'] !== $filters['guru_id']) {
                return false;
            }

            if ($filters['rombel'] !== '' && $item['rombel_key'] !== $filters['rombel']) {
                return false;
            }

            if ($filters['mapel_id'] > 0 && (int) $item['mapel_id'] !== $filters['mapel_id']) {
                return false;
            }

            if ($filters['ruang'] !== '' && strcasecmp((string) $item['ruang'], $filters['ruang']) !== 0) {
                return false;
            }

            if (in_array($filters['hari'], $hariAktif, true) && $item['hari'] !== $filters['hari']) {
                return false;
            }

            $status = $item['status'];
            if (($item['di_luar_sesi'] ?? false) && $status === 'sesuai') {
                $status = 'luar';
            }

            if ($filters['status'] !== '' && $status !== $filters['status']) {
                return false;
            }

            if ($cari !== '') {
                $kelas = (string) $item['kelas'];
                $jurusan = (string) $item['jurusan'];
                $jurusanSingkat = (string) ($item['jurusan_singkat'] ?? '');
                $haystack = strtolower(implode(' ', [
                    (string) $item['guru'],
                    (string) $item['mapel'],
                    $kelas,
                    $jurusan,
                    $jurusanSingkat,
                    (string) $item['ruang'],
                    str_replace('|', ' ', (string) $item['rombel_key']),
                    trim($kelas.' '.$jurusanSingkat),
                    trim($jurusanSingkat.' '.$kelas),
                    trim($kelas.' '.$jurusan),
                ]));

                if (! str_contains(preg_replace('/\s+/', ' ', $haystack), $cari)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param  array<int, array<string, mixed>>  $saran
     * @param  array{guru_id: int, rombel: string, mapel_id: int, ruang: string, hari: string, status: string, cari: string}  $filters
     * @return array<int, array<string, mixed>>
     */
    private function filterSaran(array $saran, array $filters): array
    {
        return array_values(array_filter($saran, function (array $item) use ($filters): bool {
            if ($filters['guru_id'] > 0 && (int) ($item['guru_id'] ?? 0) !== $filters['guru_id']) {
                return false;
            }

            return $filters['hari'] === '' || $item['hari'] === $filters['hari'];
        }));
    }

    /**
     * @param  array<int, array<string, mixed>>  $bentrok
     * @param  array{guru_id: int, rombel: string, mapel_id: int, ruang: string, hari: string, status: string, cari: string}  $filters
     * @return array<int, array<string, mixed>>
     */
    private function filterBentrok(array $bentrok, array $filters): array
    {
        return array_values(array_filter($bentrok, function (array $grup) use ($filters): bool {
            return $filters['hari'] === '' || $grup['hari'] === $filters['hari'];
        }));
    }

    /**
     * @param  array<int, string>  $hariAktif
     */
    private function labelPekan(Carbon $senin, array $hariAktif): string
    {
        $posisi = array_map(
            fn (string $nama) => (int) array_search($nama, JadwalPelajaran::HARI, true),
            $hariAktif,
        );
        $awal = $senin->copy()->addDays(min($posisi));
        $akhir = $senin->copy()->addDays(max($posisi));

        if ($awal->month === $akhir->month && $awal->year === $akhir->year) {
            return $awal->day.'–'.$akhir->day.' '.$akhir->translatedFormat('F Y');
        }

        if ($awal->year === $akhir->year) {
            return $awal->translatedFormat('d M').'–'.$akhir->translatedFormat('d M Y');
        }

        return $awal->translatedFormat('d M Y').'–'.$akhir->translatedFormat('d M Y');
    }
}
