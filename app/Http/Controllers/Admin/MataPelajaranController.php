<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\Materi;
use App\Models\Nilai;
use App\Models\Tugas;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\XlsxExportService;
use App\Support\PerbandinganData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Data Mata Pelajaran.
 *
 * Mata pelajaran adalah master data untuk daftar pelajaran yang diajarkan di
 * sekolah. Pemakaian dihitung dari guru yang mencatatkannya pada kolom
 * mata_pelajaran (users.mata_pelajaran) dan dari data akademik yang terhubung
 * (nilai, tugas, materi, jadwal), jadi data yang terlanjur dipakai tidak bisa
 * sembarangan diganti atau dihapus.
 */
class MataPelajaranController extends Controller
{
    public function index(): View
    {
        $rows = $this->daftar();

        $stats = [
            [
                'key' => 'total',
                'label' => 'Total Mapel',
                'value' => $rows->count(),
                'icon' => 'lucide-book-open',
            ],
            [
                'key' => 'dipakai',
                'label' => 'Mapel Dipakai',
                'value' => $rows->where('dipakai', true)->count(),
                'icon' => 'lucide-users',
            ],
            [
                'key' => 'kosong',
                'label' => 'Mapel Belum Dipakai',
                'value' => $rows->where('dipakai', false)->count(),
                'icon' => 'lucide-hourglass',
            ],
            [
                'key' => 'pengampu',
                'label' => 'Total Pengampu',
                'value' => (int) $rows->sum('jumlah_pengampu'),
                'icon' => 'lucide-user-check',
            ],
        ];

        // Warna ikon per kartu supaya tiap angka mudah dibedakan.
        $statTone = [
            'total' => ['bg-blue-100 text-primary-60', '#1E86FF'],
            'dipakai' => ['bg-emerald-100 text-emerald-600', '#059669'],
            'kosong' => ['bg-slate-100 text-slate-500', '#64748B'],
            'pengampu' => ['bg-amber-100 text-amber-600', '#D97706'],
        ];

        $mapelData = [
            'mapel' => $rows,
        ];

        return view('dashboard.admin.data-mata-pelajaran.index', [
            'stats' => $stats,
            'statTone' => $statTone,
            'mapelData' => $mapelData,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $mapel = MataPelajaran::create([
            'kode' => $data['kode'],
            'nama' => $data['nama'],
        ]);

        app(ActivityLogger::class)->record(
            ActivityLog::TIPE_DATA_MAPEL,
            "Menambahkan mata pelajaran {$mapel->nama}.",
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => "Mata pelajaran {$mapel->nama} berhasil ditambahkan.",
            'data' => ['mapel' => $this->baris($mapel)],
        ], 201);
    }

    public function update(Request $request, MataPelajaran $mapel): JsonResponse
    {
        $data = $this->validated($request, $mapel);

        // Nama yang sudah dipakai tidak boleh diganti, karena mengubah nama
        // di sini tidak mengikuti guru yang mencatatnya dan dapat membuat
        // riwayat nilai/tugas/materi/jadwal terbaca dengan nama baru.
        if ($mapel->nama !== $data['nama'] && $this->dipakai($mapel)) {
            return response()->json([
                'status' => 'error',
                'message' => "Mata pelajaran {$mapel->nama} masih dipakai oleh guru atau data akademik, jadi namanya tidak bisa diganti. Kodenya masih bisa diubah.",
            ], 422);
        }

        if (PerbandinganData::identik($mapel, $data)) {
            return response()->json([
                'status' => 'success',
                'message' => "Mata pelajaran {$mapel->nama} berhasil diperbarui.",
                'data' => ['mapel' => $this->baris($mapel)],
            ]);
        }

        $mapel->update($data);

        app(ActivityLogger::class)->record(
            ActivityLog::TIPE_DATA_MAPEL,
            "Memperbarui mata pelajaran {$mapel->nama}.",
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => "Mata pelajaran {$mapel->nama} berhasil diperbarui.",
            'data' => ['mapel' => $this->baris($mapel)],
        ]);
    }

    public function destroy(MataPelajaran $mapel): JsonResponse
    {
        if ($this->dipakai($mapel)) {
            return response()->json([
                'status' => 'error',
                'message' => "Mata pelajaran {$mapel->nama} masih dipakai oleh guru atau data akademik (nilai/tugas/materi/jadwal), jadi tidak bisa dihapus.",
            ], 422);
        }

        $id = $mapel->id;
        $nama = $mapel->nama;

        $mapel->delete();

        app(ActivityLogger::class)->record(ActivityLog::TIPE_DATA_MAPEL, "Menghapus mata pelajaran {$nama}.");

        return response()->json([
            'status' => 'success',
            'message' => "Mata pelajaran {$nama} berhasil dihapus.",
            'data' => ['id' => $id],
        ]);
    }

    /**
     * Unduh daftar mata pelajaran sebagai file Excel, mengikuti filter yang
     * sedang aktif di halaman (kondisi pemakaian dan pencarian).
     */
    public function export(Request $request): Response
    {
        $rows = $this->daftar()
            ->filter(fn (array $m) => $this->lolosFilter($m, $request))
            ->values();

        $headers = ['No', 'Kode', 'Mata Pelajaran', 'Jumlah Pengampu'];

        $baris = [];

        foreach ($rows as $i => $m) {
            $baris[] = [
                $i + 1,
                $m['kode'],
                $m['nama'],
                $m['jumlah_pengampu'],
            ];
        }

        $response = app(XlsxExportService::class)->download(
            'data-mata-pelajaran-'.now()->format('Ymd-His').'.xlsx',
            'Data Mata Pelajaran',
            $headers,
            $baris,
            [
                'subjudul' => 'Diunduh '.now()->format('d M Y H:i').' • '.$rows->count().' mata pelajaran',
                // Kode tetap teks supaya angka awalnya tidak hilang.
                'text_columns' => [2],
            ]
        );

        $response->headers->set('Cache-Control', 'no-store, no-cache');
        $response->headers->set('Pragma', 'no-cache');

        // Dibaca tombol export untuk menampilkan berapa mapel yang tersimpan.
        $response->headers->set('X-Total-Mapel', (string) $rows->count());

        return $response;
    }

    /**
     * Filter yang sama seperti di browser supaya hasil export sama dengan
     * yang terlihat di tabel.
     *
     * @param  array{id: int, kode: string|null, nama: string, jumlah_pengampu: int, dipakai: bool, boleh_dihapus: bool}  $m
     */
    protected function lolosFilter(array $m, Request $request): bool
    {
        $isi = trim((string) $request->input('isi'));
        $q = trim((string) $request->input('q'));

        if ($isi === 'dipakai' && ! $m['dipakai']) {
            return false;
        }

        if ($isi === 'kosong' && $m['dipakai']) {
            return false;
        }

        if ($q !== '') {
            $q = mb_strtolower($q);

            $cocok = str_contains(mb_strtolower($m['nama']), $q)
                || str_contains(mb_strtolower((string) $m['kode']), $q);

            if (! $cocok) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return Collection<int, array{id: int, kode: string|null, nama: string, jumlah_pengampu: int, dipakai: bool, boleh_dihapus: bool}>
     */
    protected function daftar(): Collection
    {
        $pengampu = $this->pengampuPerMapel();
        $idAkademik = $this->idDipakaiAkademik();

        return MataPelajaran::query()
            ->orderBy('nama')
            ->get()
            ->map(fn (MataPelajaran $mapel) => $this->baris($mapel, $pengampu, $idAkademik))
            ->values();
    }

    /**
     * Satu baris tampilan untuk satu mata pelajaran.
     *
     * @param  Collection<string, int>  $pengampu
     * @param  array<int, int>|null  $idAkademik
     * @return array{id: int, kode: string|null, nama: string, jumlah_pengampu: int, dipakai: bool, boleh_dihapus: bool}
     */
    protected function baris(MataPelajaran $mapel, ?Collection $pengampu = null, ?array $idAkademik = null): array
    {
        $pengampu ??= $this->pengampuPerMapel();
        $idAkademik ??= $this->idDipakaiAkademik();

        $jumlah = (int) ($pengampu[$mapel->nama] ?? 0);
        $dipakaiAkademik = in_array($mapel->id, $idAkademik, true);
        $dipakai = $jumlah > 0 || $dipakaiAkademik;

        return [
            'id' => $mapel->id,
            'kode' => $mapel->kode,
            'nama' => $mapel->nama,
            'jumlah_pengampu' => $jumlah,
            'dipakai' => $dipakai,
            'boleh_dihapus' => ! $dipakai,
        ];
    }

    /**
     * Apakah mata pelajaran ini masih dipakai, baik oleh guru pengampu
     * (users.mata_pelajaran) maupun oleh data akademik yang terhubung
     * lewat mata_pelajaran_id. Data akademik ikut dihitung karena foreign
     * key-nya cascade: menghapus mapel akan menghapus nilai, tugas,
     * materi, dan jadwal yang sudah tercatat.
     */
    protected function dipakai(MataPelajaran $mapel): bool
    {
        return $this->pengampuPerMapel()->has($mapel->nama)
            || in_array($mapel->id, $this->idDipakaiAkademik(), true);
    }

    /**
     * ID mata pelajaran yang sudah dipakai nilai, tugas, materi, atau jadwal.
     *
     * @return array<int, int>
     */
    protected function idDipakaiAkademik(): array
    {
        return Nilai::query()->distinct()->pluck('mata_pelajaran_id')
            ->merge(Tugas::query()->distinct()->pluck('mata_pelajaran_id'))
            ->merge(Materi::query()->distinct()->pluck('mata_pelajaran_id'))
            ->merge(JadwalPelajaran::query()->distinct()->pluck('mata_pelajaran_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<string, int>
     */
    protected function pengampuPerMapel(): Collection
    {
        return User::query()
            ->where('role', 'guru')
            ->whereNotNull('mata_pelajaran')
            ->where('mata_pelajaran', '!=', '')
            ->get(['mata_pelajaran'])
            ->countBy(fn (User $user) => trim((string) $user->mata_pelajaran));
    }

    /**
     * @return array{kode: string|null, nama: string}
     */
    protected function validated(Request $request, ?MataPelajaran $mapel = null): array
    {
        $validated = $request->validate(
            [
                'kode' => [
                    'nullable',
                    'string',
                    'max:20',
                    'regex:/^[A-Za-z0-9\-\.\/]+$/',
                ],
                'nama' => [
                    'required',
                    'string',
                    'max:120',
                    Rule::unique('mata_pelajaran', 'nama')->ignore($mapel?->id),
                ],
            ],
            [
                'kode.max' => 'Kode maksimal 20 karakter.',
                'kode.regex' => 'Kode hanya boleh berisi huruf, angka, tanda hubung, titik, atau garis miring.',
                'nama.required' => 'Nama mata pelajaran wajib diisi.',
                'nama.max' => 'Nama mata pelajaran maksimal 120 karakter.',
                'nama.unique' => 'Mata pelajaran ini sudah terdaftar.',
            ]
        );

        $kode = trim((string) ($validated['kode'] ?? ''));

        return [
            'kode' => $kode !== '' ? mb_strtoupper($kode) : null,
            'nama' => trim((string) $validated['nama']),
        ];
    }
}
