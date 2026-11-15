<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Jurusan;
use App\Models\KelasRombel;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\XlsxExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Data Jurusan.
 *
 * Jurusan adalah master data untuk paket keahlian yang tersedia di sekolah.
 * Pemakaiannya dihitung dari jurusan yang benar-benar dipakai kelas maupun
 * siswa, jadi jurusan yang namanya berubah tidak membuat rombongan belajar
 * atau akun siswa ikut berubah (jurusan lama tetap terpakai).
 */
class JurusanController extends Controller
{
    public function index(): View
    {
        $rows = $this->daftar();

        $stats = [
            [
                'key' => 'total',
                'label' => 'Total Jurusan',
                'value' => $rows->count(),
                'icon' => 'lucide-layers',
            ],
            [
                'key' => 'aktif',
                'label' => 'Jurusan Aktif',
                'value' => $rows->where('status', 'aktif')->count(),
                'icon' => 'lucide-badge-check',
            ],
            [
                'key' => 'kelas',
                'label' => 'Total Kelas',
                'value' => (int) $rows->sum('jumlah_kelas'),
                'icon' => 'lucide-school',
            ],
            [
                'key' => 'siswa',
                'label' => 'Total Siswa',
                'value' => (int) $rows->sum('jumlah_siswa'),
                'icon' => 'lucide-graduation-cap',
            ],
        ];

        // Warna ikon per kartu supaya tiap angka mudah dibedakan.
        $statTone = [
            'total' => ['bg-blue-100 text-primary-60', '#1E86FF'],
            'aktif' => ['bg-emerald-100 text-emerald-600', '#059669'],
            'kelas' => ['bg-indigo-100 text-indigo-600', '#4F46E5'],
            'siswa' => ['bg-amber-100 text-amber-600', '#D97706'],
        ];

        $jurusanData = [
            'jurusan' => $rows,
            'status' => collect(Jurusan::STATUS_LABELS)
                ->map(fn (string $label, string $status) => ['nilai' => $status, 'teks' => $label])
                ->values()
                ->all(),
        ];

        return view('dashboard.admin.data-jurusan.index', [
            'stats' => $stats,
            'statTone' => $statTone,
            'jurusanData' => $jurusanData,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $jurusan = Jurusan::create($data);

        app(ActivityLogger::class)->record(
            ActivityLog::TIPE_DATA_JURUSAN,
            "Menambahkan jurusan {$jurusan->nama}.",
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => "Jurusan {$jurusan->nama} berhasil ditambahkan.",
            'data' => ['jurusan' => $this->baris($jurusan)],
        ], 201);
    }

    public function update(Request $request, Jurusan $jurusan): JsonResponse
    {
        $data = $this->validated($request, $jurusan);

        // Nama jurusan yang masih dipakai tidak boleh diganti, karena mengubah
        // nama di sini tidak akan mengikuti ke kelas atau siswa yang memakainya.
        if ($jurusan->nama !== $data['nama'] && $this->dipakai($jurusan)) {
            return response()->json([
                'status' => 'error',
                'message' => "Jurusan {$jurusan->nama} masih dipakai oleh kelas maupun siswa, jadi namanya tidak bisa diganti. Statusnya masih bisa diubah.",
            ], 422);
        }

        $jurusan->update($data);

        app(ActivityLogger::class)->record(
            ActivityLog::TIPE_DATA_JURUSAN,
            "Memperbarui jurusan {$jurusan->nama}.",
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => "Jurusan {$jurusan->nama} berhasil diperbarui.",
            'data' => ['jurusan' => $this->baris($jurusan)],
        ]);
    }

    public function destroy(Jurusan $jurusan): JsonResponse
    {
        if ($this->dipakai($jurusan)) {
            return response()->json([
                'status' => 'error',
                'message' => "Jurusan {$jurusan->nama} masih dipakai oleh kelas maupun siswa, jadi tidak bisa dihapus.",
            ], 422);
        }

        $jurusan->delete();

        app(ActivityLogger::class)->record(ActivityLog::TIPE_DATA_JURUSAN, "Menghapus jurusan {$jurusan->nama}.");

        return response()->json([
            'status' => 'success',
            'message' => "Jurusan {$jurusan->nama} berhasil dihapus.",
            'data' => ['id' => $jurusan->id],
        ]);
    }

    /**
     * Unduh daftar jurusan sebagai file Excel, mengikuti filter yang sedang
     * aktif di halaman (status, kondisi pemakaian, dan pencarian).
     */
    public function export(Request $request): Response
    {
        $rows = $this->daftar()
            ->filter(fn (array $j) => $this->lolosFilter($j, $request))
            ->values();

        $headers = ['No', 'Jurusan', 'Status', 'Jumlah Kelas', 'Jumlah Siswa'];

        $baris = [];

        foreach ($rows as $i => $j) {
            $baris[] = [
                $i + 1,
                $j['nama'],
                $j['status_label'],
                $j['jumlah_kelas'],
                $j['jumlah_siswa'],
            ];
        }

        $response = app(XlsxExportService::class)->download(
            'data-jurusan-'.now()->format('Ymd-His').'.xlsx',
            'Data Jurusan',
            $headers,
            $baris,
            [
                'subjudul' => 'Diunduh '.now()->format('d M Y H:i').' • '.$rows->count().' jurusan',
            ]
        );

        $response->headers->set('Cache-Control', 'no-store, no-cache');
        $response->headers->set('Pragma', 'no-cache');

        // Dibaca tombol export untuk menampilkan berapa jurusan yang tersimpan.
        $response->headers->set('X-Total-Jurusan', (string) $rows->count());

        return $response;
    }

    /**
     * Filter yang sama seperti di browser supaya hasil export sama dengan
     * yang terlihat di tabel.
     *
     * @param  array{id: int, nama: string, status: string, status_label: string, jumlah_kelas: int, jumlah_siswa: int, dipakai: bool, boleh_dihapus: bool}  $j
     */
    protected function lolosFilter(array $j, Request $request): bool
    {
        $status = trim((string) $request->input('status'));
        $isi = trim((string) $request->input('isi'));
        $q = trim((string) $request->input('q'));

        if ($status !== '' && $j['status'] !== $status) {
            return false;
        }

        if ($isi === 'dipakai' && ! $j['dipakai']) {
            return false;
        }

        if ($isi === 'kosong' && $j['dipakai']) {
            return false;
        }

        if ($q !== '') {
            $q = mb_strtolower($q);

            $cocok = str_contains(mb_strtolower($j['nama']), $q)
                || str_contains(mb_strtolower($j['status_label']), $q);

            if (! $cocok) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return Collection<int, array{id: int, nama: string, status: string, status_label: string, jumlah_kelas: int, jumlah_siswa: int, dipakai: bool, boleh_dihapus: bool}>
     */
    protected function daftar(): Collection
    {
        $jumlahKelas = $this->jumlahKelasPerJurusan();
        $jumlahSiswa = $this->jumlahSiswaPerJurusan();

        return Jurusan::query()
            ->orderBy('nama')
            ->get()
            ->map(fn (Jurusan $jurusan) => $this->baris($jurusan, $jumlahKelas, $jumlahSiswa))
            ->values();
    }

    /**
     * Satu baris tampilan untuk satu jurusan.
     *
     * @param  Collection<string, int>  $jumlahKelas
     * @param  Collection<string, int>  $jumlahSiswa
     * @return array{id: int, nama: string, status: string, status_label: string, jumlah_kelas: int, jumlah_siswa: int, dipakai: bool, boleh_dihapus: bool}
     */
    protected function baris(Jurusan $jurusan, ?Collection $jumlahKelas = null, ?Collection $jumlahSiswa = null): array
    {
        $jumlahKelas ??= $this->jumlahKelasPerJurusan();
        $jumlahSiswa ??= $this->jumlahSiswaPerJurusan();

        $kelas = (int) ($jumlahKelas[$jurusan->nama] ?? 0);
        $siswa = (int) ($jumlahSiswa[$jurusan->nama] ?? 0);

        return [
            'id' => $jurusan->id,
            'nama' => $jurusan->nama,
            'status' => $jurusan->status,
            'status_label' => Jurusan::STATUS_LABELS[$jurusan->status] ?? $jurusan->status,
            'jumlah_kelas' => $kelas,
            'jumlah_siswa' => $siswa,
            'dipakai' => $kelas + $siswa > 0,
            'boleh_dihapus' => $kelas + $siswa === 0,
        ];
    }

    /**
     * Apakah jurusan ini benar-benar dipakai oleh kelas atau siswa.
     */
    protected function dipakai(Jurusan $jurusan): bool
    {
        return $this->jumlahKelasPerJurusan()->has($jurusan->nama)
            || $this->jumlahSiswaPerJurusan()->has($jurusan->nama);
    }

    /** @return Collection<string, int> */
    protected function jumlahKelasPerJurusan(): Collection
    {
        return KelasRombel::query()
            ->whereNotNull('jurusan')
            ->where('jurusan', '!=', '')
            ->get(['jurusan'])
            ->countBy(fn (KelasRombel $kelas) => trim((string) $kelas->jurusan));
    }

    /** @return Collection<string, int> */
    protected function jumlahSiswaPerJurusan(): Collection
    {
        return User::query()
            ->where('role', 'siswa')
            ->whereNotNull('jurusan')
            ->where('jurusan', '!=', '')
            ->get(['jurusan'])
            ->countBy(fn (User $siswa) => trim((string) $siswa->jurusan));
    }

    /**
     * @return array{nama: string, status: string}
     */
    protected function validated(Request $request, ?Jurusan $jurusan = null): array
    {
        $validated = $request->validate(
            [
                'nama' => [
                    'required',
                    'string',
                    'max:120',
                    Rule::unique('jurusan', 'nama')->ignore($jurusan?->id),
                ],
                'status' => ['required', Rule::in(Jurusan::STATUSES)],
            ],
            [
                'nama.required' => 'Nama jurusan wajib diisi.',
                'nama.max' => 'Nama jurusan maksimal 120 karakter.',
                'nama.unique' => 'Jurusan ini sudah terdaftar.',
                'status.required' => 'Status jurusan wajib diisi.',
                'status.in' => 'Status jurusan tidak valid.',
            ]
        );

        return [
            'nama' => trim((string) $validated['nama']),
            'status' => $validated['status'],
        ];
    }
}
