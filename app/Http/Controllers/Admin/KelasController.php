<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Jurusan;
use App\Models\KelasRombel;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\XlsxExportService;
use App\Support\Kelas;
use App\Support\PerbandinganData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Data Kelas: daftar rombel yang boleh dipakai siswa.
 *
 * Kelas di sini adalah master data, jadi kelas bisa dibuat lebih dulu walau
 * siswanya belum ada. Sebaliknya kelas yang masih dipakai siswa tidak boleh
 * dihapus atau diganti namanya, karena kolom kelas pada siswa tidak ikut berubah
 * dan kelas siswa jadi tidak ketemu dengan master kelasnya.
 */
class KelasController extends Controller
{
    public function index(): View
    {
        $jumlahSiswa = $this->jumlahSiswaPerKelas();

        $rows = $this->kelasRombel()->map(
            fn (KelasRombel $kelas) => $this->baris($kelas, $jumlahSiswa)
        )->values();

        $jurusan = $rows->pluck('jurusan')->unique()->sort()->values();

        return view('dashboard.admin.data-kelas.index', [
            'stats' => [
                [
                    'key' => 'total',
                    'label' => 'Total Kelas',
                    'value' => $rows->count(),
                    'icon' => 'lucide-school',
                ],
                [
                    'key' => 'jurusan',
                    'label' => 'Jurusan',
                    'value' => $jurusan->count(),
                    'icon' => 'lucide-layers',
                ],
                [
                    'key' => 'dengan_wali',
                    'label' => 'Sudah Ada Wali',
                    'value' => $rows->whereNotNull('wali_kelas_id')->count(),
                    'icon' => 'lucide-user-check',
                ],
                [
                    'key' => 'tanpa_wali',
                    'label' => 'Belum Ada Wali',
                    'value' => $rows->whereNull('wali_kelas_id')->count(),
                    'icon' => 'lucide-user-x',
                ],
            ],
            'kelasData' => [
                'kelas' => $rows,
                'jurusan' => $jurusan,
                // Dipakai dropdown Jurusan di form Tambah/Ubah Kelas.
                'jurusan_opsi' => Jurusan::opsiForm()->all(),
                'tingkat' => $this->pilihanTingkat($rows),
                'wali' => User::query()
                    ->where('role', 'guru')
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (User $guru) => ['id' => $guru->id, 'name' => $guru->name])
                    ->values(),
                'tingkat_umum' => Kelas::tingkatUmum(),
                'kelas_contoh' => Kelas::CONTOH,
                'kelas_contoh_rombel' => Kelas::CONTOH_ROMBEL,
                // Pola dikirim dari sini, bukan ditulis ulang di JavaScript, supaya
                // pengecekan di browser dan di server tidak bisa berbeda.
                'kelas_pattern' => trim(Kelas::PATTERN, '/'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $kelas = KelasRombel::create([
            'jurusan' => $data['jurusan'],
            'kelas' => $data['kelas'],
            'wali_kelas_id' => $data['wali_kelas_id'],
        ]);

        app(ActivityLogger::class)->record(
            ActivityLog::TIPE_DATA_KELAS,
            "Menambahkan kelas {$kelas->kelas} {$kelas->jurusan}.",
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => "Kelas {$kelas->kelas} {$kelas->jurusan} berhasil ditambahkan.",
            'data' => [
                'kelas' => $this->baris($kelas, $this->jumlahSiswaPerKelas()),
            ],
        ], 201);
    }

    public function update(Request $request, KelasRombel $kelas): JsonResponse
    {
        $data = $this->validated($request, $kelas);

        $kelas->load('waliKelas');

        if ($this->kelasBerubah($kelas, $data) && ! $kelas->bolehDihapus()) {
            return response()->json([
                'status' => 'error',
                'message' => "Kelas {$kelas->kelas} {$kelas->jurusan} masih dipakai siswa, jadi nama kelasnya tidak bisa diubah. "
                    .'Pindahkan siswa ke kelas lain dulu, atau biarkan nama kelasnya tetap.',
            ], 422);
        }

        if (PerbandinganData::identik(
            $kelas,
            [
                'jurusan' => $request->input('jurusan'),
                'kelas' => $data['kelas'],
                'wali_kelas_id' => $data['wali_kelas_id'],
            ],
            [
                'jurusan' => fn ($nilai) => Jurusan::namaDariId((int) $nilai),
            ]
        )) {
            return response()->json([
                'status' => 'success',
                'message' => "Kelas {$kelas->kelas} {$kelas->jurusan} berhasil diperbarui.",
                'data' => [
                    'kelas' => $this->baris($kelas, $this->jumlahSiswaPerKelas()),
                ],
            ]);
        }

        $kelas->update([
            'jurusan' => $data['jurusan'],
            'kelas' => $data['kelas'],
            'wali_kelas_id' => $data['wali_kelas_id'],
        ]);

        $kelas->refresh()->load('waliKelas');

        app(ActivityLogger::class)->record(
            ActivityLog::TIPE_DATA_KELAS,
            "Memperbarui kelas {$kelas->kelas} {$kelas->jurusan}.",
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => "Kelas {$kelas->kelas} {$kelas->jurusan} berhasil diperbarui.",
            'data' => [
                'kelas' => $this->baris($kelas, $this->jumlahSiswaPerKelas()),
            ],
        ]);
    }

    public function destroy(KelasRombel $kelas): JsonResponse
    {
        if (! $kelas->bolehDihapus()) {
            return response()->json([
                'status' => 'error',
                'message' => "Kelas {$kelas->kelas} {$kelas->jurusan} masih punya siswa, jadi belum bisa dihapus. "
                    .'Pindahkan siswanya dulu.',
            ], 422);
        }

        $kelas->delete();

        app(ActivityLogger::class)->record(
            ActivityLog::TIPE_DATA_KELAS,
            "Menghapus kelas {$kelas->kelas} {$kelas->jurusan}."
        );

        return response()->json([
            'status' => 'success',
            'message' => "Kelas {$kelas->kelas} {$kelas->jurusan} berhasil dihapus.",
            'data' => [
                'id' => $kelas->id,
            ],
        ]);
    }

    /**
     * Unduh daftar kelas sebagai file Excel, mengikuti filter yang sedang
     * aktif di halaman (jurusan, tingkat, wali, isi, dan pencarian).
     */
    public function export(Request $request): Response
    {
        $jumlahSiswa = $this->jumlahSiswaPerKelas();

        $rows = $this->kelasRombel()
            ->map(fn (KelasRombel $kelas) => $this->baris($kelas, $jumlahSiswa))
            ->filter(fn (array $kelas) => $this->lolosFilter($kelas, $request))
            ->values();

        $headers = ['No', 'Kelas', 'Tingkat', 'Rombel', 'Jurusan', 'Wali Kelas', 'Jumlah Siswa'];

        $baris = [];
        foreach ($rows as $i => $kelas) {
            $baris[] = [
                $i + 1,
                $kelas['kelas'],
                $kelas['tingkat'] ?? '',
                $kelas['rombel'] ?? '',
                $kelas['jurusan'],
                $kelas['wali_kelas'] ?? '',
                $kelas['jumlah_siswa'],
            ];
        }

        $response = app(XlsxExportService::class)->download(
            'data-kelas-'.now()->format('Ymd-His').'.xlsx',
            'Data Kelas',
            $headers,
            $baris,
            [
                'subjudul' => 'Diunduh '.now()->format('d M Y H:i').' • '.$rows->count().' kelas',
                // Kelas, tingkat, dan rombel tetap teks supaya "12-1" tidak
                // berubah jadi tanggal saat file dibuka di Excel.
                'text_columns' => [2, 3, 4],
            ]
        );

        $response->headers->set('Cache-Control', 'no-store, no-cache');
        $response->headers->set('Pragma', 'no-cache');

        // Dibaca tombol export untuk menampilkan berapa kelas yang tersimpan.
        $response->headers->set('X-Total-Kelas', (string) $rows->count());

        return $response;
    }

    /**
     * Filter kelas yang sama seperti di browser, supaya hasil export sama
     * dengan yang terlihat di tabel.
     *
     * @param  array{kelas: string, tingkat: ?string, rombel: ?string, jurusan: string, wali_kelas: ?string, wali_kelas_id: ?int, jumlah_siswa: int}  $kelas
     */
    protected function lolosFilter(array $kelas, Request $request): bool
    {
        $jurusan = trim((string) $request->input('jurusan'));
        $tingkat = trim((string) $request->input('tingkat'));
        $wali = $request->input('wali');
        $isi = $request->input('isi');
        $q = trim((string) $request->input('q'));

        if ($jurusan !== '' && $kelas['jurusan'] !== $jurusan) {
            return false;
        }

        if ($tingkat !== '' && (string) ($kelas['tingkat'] ?? '') !== $tingkat) {
            return false;
        }

        if ($wali !== null && $wali !== '' && (string) ($kelas['wali_kelas_id'] ?? '') !== (string) $wali) {
            return false;
        }

        if ($isi === 'kosong' && $kelas['jumlah_siswa'] > 0) {
            return false;
        }

        if ($isi === 'ada' && $kelas['jumlah_siswa'] === 0) {
            return false;
        }

        if ($q !== '') {
            $haystack = implode(' ', array_filter([
                $kelas['kelas'],
                $kelas['tingkat'],
                $kelas['rombel'],
                $kelas['jurusan'],
                $kelas['wali_kelas'],
            ], fn ($nilai) => $nilai !== null && $nilai !== ''));

            if (! str_contains(mb_strtolower($haystack), mb_strtolower($q))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Semua kelas diambil sekali lalu diurutkan lewat model supaya urutannya
     * sama dengan filter Data Siswa: "12-2" tetap di atas "12-10".
     *
     * @return Collection<int, KelasRombel>
     */
    protected function kelasRombel(): Collection
    {
        return collect(KelasRombel::urutkan(
            KelasRombel::query()->with('waliKelas')->get()->all()
        ));
    }

    /**
     * Jumlah siswa per "jurusan|kelas" diambil dengan satu agregasi, bukan satu
     * query per baris, supaya halaman ini tetap ringan walau kelasnya banyak.
     *
     * @return Collection<string, int>
     */
    protected function jumlahSiswaPerKelas(): Collection
    {
        return User::query()
            ->selectRaw('jurusan, kelas, COUNT(*) as jumlah')
            ->where('role', 'siswa')
            ->whereNotNull('jurusan')
            ->whereNotNull('kelas')
            ->groupBy('jurusan', 'kelas')
            ->get()
            // Kuncinya gabungan jurusan + kelas, karena kelas "12-1" bisa dipakai
            // di lebih dari satu jurusan.
            ->mapWithKeys(fn (User $siswa) => [
                $siswa->jurusan.'|'.$siswa->kelas => (int) $siswa->jumlah,
            ]);
    }

    /**
     * Bentuk satu baris untuk tabel, kartu, dan modal. Frontend memakai bentuk
     * yang sama persis setelah menyimpan, supaya tidak perlu memuat ulang
     * halaman untuk melihat hasil perubahan.
     *
     * @param  Collection<string, int>  $jumlahSiswa
     * @return array{id: int, kelas: string, tingkat: ?string, rombel: ?string, jurusan: string, wali_kelas_id: ?int, wali_kelas: ?string, jumlah_siswa: int, boleh_dihapus: bool}
     */
    protected function baris(KelasRombel $kelas, Collection $jumlahSiswa): array
    {
        // Kurungannya penting: tanpa itu operator ?? tidak melindungi akses
        // array dan kelas tanpa siswa akan memicu error.
        $jumlah = (int) ($jumlahSiswa[$kelas->jurusan.'|'.$kelas->kelas] ?? 0);

        return [
            'id' => $kelas->id,
            'kelas' => $kelas->kelas,
            'tingkat' => $kelas->tingkat(),
            'rombel' => $kelas->rombel(),
            'jurusan' => $kelas->jurusan,
            'wali_kelas_id' => $kelas->wali_kelas_id,
            'wali_kelas' => $kelas->waliKelas?->name,
            'jumlah_siswa' => $jumlah,
            'boleh_dihapus' => $jumlah === 0,
        ];
    }

    /**
     * Tingkat yang dipakai sekolah selalu ada di pilihan filter, lalu diikuti
     * tingkat lain yang muncul di data supaya pilihannya tidak lebih sempit
     * daripada isinya. Urutannya dari yang tertinggi.
     *
     * @param  Collection<int, array{kelas: string, tingkat: ?string}>  $rows
     * @return array<int, string>
     */
    protected function pilihanTingkat(Collection $rows): array
    {
        $dipakai = $rows->pluck('tingkat')->filter()->map(fn ($tingkat) => (string) $tingkat);

        $daftar = array_values(array_unique([...Kelas::tingkatUmum(), ...$dipakai]));

        usort($daftar, fn (string $a, string $b) => ((int) $b) <=> ((int) $a));

        return $daftar;
    }

    /**
     * @param  array{jurusan: string, kelas: string}  $data
     */
    protected function kelasBerubah(KelasRombel $kelas, array $data): bool
    {
        return $kelas->kelas !== Kelas::normalize((string) $data['kelas'])
            || $kelas->jurusan !== $data['jurusan'];
    }

    protected function validated(Request $request, ?KelasRombel $kelas = null): array
    {
        // Bentuk kelas sudah jadi satu kolom, jadi rapikan lebih dulu supaya
        // "12 - 1" tetap dianggap sama dengan "12-1".
        if ($request->has('kelas')) {
            $request->merge(['kelas' => Kelas::normalize((string) $request->input('kelas'))]);
        }

        // Form mengirim ID jurusan, sedangkan kolom kelas.jurusan menyimpan nama.
        $idJurusan = $request->input('jurusan');
        $namaJurusan = is_numeric($idJurusan)
            ? Jurusan::namaDariId((int) $idJurusan)
            : null;

        $validated = $request->validate([
            'jurusan' => ['required', 'integer', Rule::exists('jurusan', 'id')],
            'kelas' => [
                'required',
                'string',
                'max:30',
                'regex:'.Kelas::PATTERN,
                // Kombinasi jurusan + kelas harus unik: kelas yang sama boleh
                // dipakai di jurusan lain, jadi jurusannya ikut diperiksa.
                Rule::unique('kelas', 'kelas')
                    ->where(fn ($query) => $query->where('jurusan', $namaJurusan))
                    ->ignore($kelas?->id),
            ],
            // Wali kelas harus benar-benar akun guru, supaya kolom ini tidak
            // bisa diisi akun siswa atau admin.
            'wali_kelas_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'guru')),
            ],
        ], [
            'jurusan.required' => 'Jurusan wajib dipilih.',
            'jurusan.integer' => 'Jurusan yang dipilih tidak valid.',
            'jurusan.exists' => 'Jurusan yang dipilih tidak terdaftar.',
            'kelas.required' => 'Kelas wajib diisi.',
            'kelas.regex' => Kelas::formatMessage(),
            'kelas.unique' => 'Kelas ini sudah terdaftar di jurusan tersebut.',
            'wali_kelas_id.exists' => 'Wali kelas yang dipilih tidak valid.',
        ]);

        return array_merge($validated, ['jurusan' => $namaJurusan ?? '']);
    }
}
