<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Concerns\MelayaniFileMateri;
use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Models\Materi;
use App\Models\PengumpulanTugas;
use App\Models\Tugas;
use App\Models\User;
use App\Services\GuruAkademik;
use App\Support\FileMateri;
use App\Support\Kelas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Pengelolaan Tugas & Materi oleh Guru.
 *
 * Semua data dibatasi pada mata pelajaran dan rombel yang diampu guru. Daftar
 * rombel berasal dari jadwal/nilai (lihat GuruAkademik), sehingga guru tidak
 * bisa membagikan tugas ke kelas yang bukan tanggung jawabnya.
 */
class TugasMateriController extends Controller
{
    use MelayaniFileMateri;

    public function __construct(private readonly GuruAkademik $akademik) {}

    public function index(Request $request): View
    {
        $guru = $this->guru($request);
        $mapel = $this->akademik->mapelDiampu($guru);
        $mapelIds = $mapel->pluck('id')->map(fn ($id) => (int) $id)->all();
        $pasangan = $this->akademik->pasanganMapelRombel($guru);

        $tab = $request->query('tab') === 'materi' ? 'materi' : 'tugas';
        $q = trim((string) $request->query('q', ''));
        $mapelId = $this->mapelFilter($request, $mapelIds);
        $rombel = $this->rombelFilter($request);
        $status = $this->statusFilter($request);

        $tugas = $this->queryTugas($guru, $q, $mapelId, $rombel, $status)
            ->paginate(8, ['*'], 'tugas_page')
            ->withQueryString();

        $materi = $this->queryMateri($guru, $q, $mapelId, $rombel)
            ->paginate(8, ['*'], 'materi_page')
            ->withQueryString();

        $editTugas = null;
        $editMateri = null;

        if ($tab === 'tugas' && $request->filled('edit')) {
            $editTugas = Tugas::query()
                ->where('guru_id', $guru->id)
                ->find($request->integer('edit'));
        }

        if ($tab === 'materi' && $request->filled('edit')) {
            $editMateri = Materi::query()
                ->where('guru_id', $guru->id)
                ->find($request->integer('edit'));
        }

        $mapelById = $mapel->keyBy(fn (MataPelajaran $item) => (int) $item->id);
        $mapelDariFilter = $mapelId !== null ? $mapelById->get($mapelId) : null;
        $mapelDariRombel = null;

        if ($mapelDariFilter === null && $rombel !== null) {
            $kandidatMapel = $pasangan
                ->filter(fn (array $item) => $item['kelas'] === $rombel['kelas']
                    && ($item['jurusan'] === '' || $item['jurusan'] === $rombel['jurusan']))
                ->pluck('mata_pelajaran_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($kandidatMapel->count() === 1) {
                $mapelDariRombel = $mapelById->get($kandidatMapel->first());
            }
        }

        $mapelKonteks = $mapelDariFilter
            ?? $mapelDariRombel
            ?? $mapel->first();

        $mapelTugas = $editTugas !== null
            ? $mapelById->get((int) $editTugas->mata_pelajaran_id)
            : $mapelKonteks;
        $mapelMateri = $editMateri !== null
            ? $mapelById->get((int) $editMateri->mata_pelajaran_id)
            : $mapelKonteks;
        $bisaTugas = $mapel->isNotEmpty() && $pasangan->isNotEmpty() && $mapelTugas !== null;
        $bisaMateri = $mapel->isNotEmpty() && $pasangan->isNotEmpty() && $mapelMateri !== null;

        $rombelByMapel = $pasangan
            ->groupBy('mata_pelajaran_id')
            ->map(fn (Collection $rows) => $rows->map(fn (array $item) => [
                'value' => $this->nilaiRombel($item['kelas'], $item['jurusan']),
                'label' => $this->labelRombel($item['kelas'], $item['jurusan']),
            ])->values()->all())
            ->all();

        $rombelOptions = $pasangan
            ->map(fn (array $item) => [
                'value' => $this->nilaiRombel($item['kelas'], $item['jurusan']),
                'label' => $this->labelRombel($item['kelas'], $item['jurusan']),
            ])
            ->unique('value')
            ->values();

        $totalPengumpulan = PengumpulanTugas::query()
            ->whereHas('tugas', fn ($query) => $query->where('guru_id', $guru->id))
            ->count();

        $belumDinilai = PengumpulanTugas::query()
            ->whereHas('tugas', fn ($query) => $query->where('guru_id', $guru->id))
            ->where(function ($query) {
                $query->whereNull('nilai')
                    ->orWhere('status', '!=', PengumpulanTugas::STATUS_DINILAI);
            })
            ->count();

        return view('dashboard.guru.tugas-materi.index', [
            'tab' => $tab,
            'mapel' => $mapel,
            'mapelIds' => $mapelIds,
            'pasangan' => $pasangan,
            'rombelByMapel' => $rombelByMapel,
            'rombelOptions' => $rombelOptions,
            'tugas' => $tugas,
            'materi' => $materi,
            'editTugas' => $editTugas,
            'editMateri' => $editMateri,
            'mapelFormTugas' => $mapelTugas,
            'mapelFormMateri' => $mapelMateri,
            'bisaTugas' => $bisaTugas,
            'bisaMateri' => $bisaMateri,
            'filters' => [
                'q' => $q,
                'mapel_id' => $mapelId,
                'rombel' => $rombel,
                'status' => $status,
            ],
            'stats' => [
                [
                    'key' => 'tugas',
                    'label' => 'Total Tugas',
                    'value' => Tugas::query()->where('guru_id', $guru->id)->count(),
                    'icon' => 'lucide-clipboard-list',
                ],
                [
                    'key' => 'materi',
                    'label' => 'Materi Ajar',
                    'value' => Materi::query()->where('guru_id', $guru->id)->count(),
                    'icon' => 'lucide-book-open',
                ],
                [
                    'key' => 'pengumpulan',
                    'label' => 'Pengumpulan',
                    'value' => $totalPengumpulan,
                    'icon' => 'lucide-inbox',
                ],
                [
                    'key' => 'belum_dinilai',
                    'label' => 'Belum Dinilai',
                    'value' => $belumDinilai,
                    'icon' => 'lucide-clock',
                ],
            ],
        ]);
    }

    public function storeTugas(Request $request): RedirectResponse|JsonResponse
    {
        $guru = $this->guru($request);
        $data = $this->validasiTugas($request, $guru, false);
        $file = $request->file('file');
        $path = null;
        $redirect = route('dashboard.guru.tugas-materi.index', ['tab' => 'tugas']);
        $message = 'Tugas berhasil ditambahkan.';

        if ($file !== null) {
            $path = $this->simpanFileLampiran($file, 'tugas');
            $data += $this->dataFileLampiran($file, $path);
        }

        try {
            DB::transaction(fn () => Tugas::create($data + ['guru_id' => $guru->id]));
        } catch (Throwable $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'redirect' => $redirect,
            ]);
        }

        return redirect()
            ->to($redirect)
            ->with('success', $message);
    }

    public function updateTugas(Request $request, Tugas $tugas): RedirectResponse|JsonResponse
    {
        $guru = $this->guru($request);
        $this->pastikanMilikGuru($tugas, $guru);

        $data = $this->validasiTugas($request, $guru, true);
        $hapusLampiran = $request->boolean('hapus_lampiran');
        $file = $request->file('file');
        $pathLama = $tugas->file_path;
        $lampiranDihapus = false;
        $redirect = route('dashboard.guru.tugas-materi.index', ['tab' => 'tugas']);
        $message = 'Tugas berhasil diperbarui.';

        if ($file !== null) {
            $pathBaru = $this->simpanFileLampiran($file, 'tugas');
            $data += $this->dataFileLampiran($file, $pathBaru);

            try {
                DB::transaction(fn () => $tugas->update($data));
            } catch (Throwable $exception) {
                Storage::disk('local')->delete($pathBaru);

                throw $exception;
            }

            if ($pathLama !== null && $pathLama !== $pathBaru) {
                Storage::disk('local')->delete($pathLama);
            }
        } else {
            if ($hapusLampiran) {
                $data += $this->dataFileLampiranKosong();
            }

            DB::transaction(fn () => $tugas->update($data));

            if ($hapusLampiran && $pathLama !== null) {
                Storage::disk('local')->delete($pathLama);
            }

            $lampiranDihapus = $hapusLampiran;
        }

        $pesanHapusLampiran = $lampiranDihapus ? 'Lampiran tugas berhasil dihapus.' : null;

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'redirect' => $redirect,
                'delete_success' => $pesanHapusLampiran,
            ]);
        }

        $respons = redirect()
            ->to($redirect)
            ->with('success', $message);

        if ($pesanHapusLampiran !== null) {
            $respons->with('delete_success', $pesanHapusLampiran);
        }

        return $respons;
    }

    public function destroyTugas(Request $request, Tugas $tugas): RedirectResponse|JsonResponse
    {
        $guru = $this->guru($request);
        $this->pastikanMilikGuru($tugas, $guru);

        $id = $tugas->id;
        $path = $tugas->file_path;

        DB::transaction(fn () => $tugas->delete());

        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }

        $pesan = 'Tugas berhasil dihapus.';

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $pesan,
                'data' => ['id' => $id],
            ]);
        }

        return redirect()
            ->route('dashboard.guru.tugas-materi.index', ['tab' => 'tugas'])
            ->with('success', $pesan);
    }

    public function previewTugas(Request $request, Tugas $tugas)
    {
        $guru = $this->guru($request);
        $this->pastikanMilikGuru($tugas, $guru);

        return $this->responseFileTugas($tugas, false);
    }

    public function unduhTugas(Request $request, Tugas $tugas)
    {
        $guru = $this->guru($request);
        $this->pastikanMilikGuru($tugas, $guru);

        return $this->responseFileTugas($tugas, true);
    }

    public function pengumpulan(Request $request, Tugas $tugas): View
    {
        $guru = $this->guru($request);
        $this->pastikanMilikGuru($tugas, $guru);

        $tugas->load(['mataPelajaran', 'pengumpulan.user']);

        $siswa = User::query()
            ->where('role', 'siswa')
            ->where('kelas', Kelas::normalize((string) $tugas->kelas))
            ->when(trim((string) $tugas->jurusan) !== '', fn ($query) => $query->where('jurusan', trim((string) $tugas->jurusan)))
            ->orderBy('name')
            ->get(['id', 'name', 'nis', 'kelas', 'jurusan', 'status']);

        $pengumpulan = $tugas->pengumpulan->keyBy('user_id');

        return view('dashboard.guru.tugas-materi.pengumpulan', [
            'tugas' => $tugas,
            'siswa' => $siswa,
            'pengumpulan' => $pengumpulan,
        ]);
    }

    public function updatePengumpulan(Request $request, PengumpulanTugas $pengumpulan): RedirectResponse
    {
        $guru = $this->guru($request);
        $pengumpulan->loadMissing('tugas');

        if (! $pengumpulan->tugas instanceof Tugas) {
            abort(404);
        }

        $this->pastikanMilikGuru($pengumpulan->tugas, $guru);

        $data = $request->validate([
            'nilai' => ['nullable', 'numeric', 'between:0,100'],
            'umpan_balik' => ['nullable', 'string', 'max:2000'],
        ], [
            'nilai.numeric' => 'Nilai harus berupa angka.',
            'nilai.between' => 'Nilai harus berada di antara 0 sampai 100.',
            'umpan_balik.max' => 'Umpan balik maksimal 2000 karakter.',
        ]);

        $nilai = filled($data['nilai'] ?? null) ? round((float) $data['nilai'], 2) : null;
        $umpanBalik = filled($data['umpan_balik'] ?? null) ? trim($data['umpan_balik']) : null;

        $pengumpulan->update([
            'nilai' => $nilai,
            'umpan_balik' => $umpanBalik,
            'status' => $nilai === null
                ? PengumpulanTugas::STATUS_DIKUMPULKAN
                : PengumpulanTugas::STATUS_DINILAI,
        ]);

        return redirect()
            ->route('dashboard.guru.tugas-materi.pengumpulan', $pengumpulan->tugas_id)
            ->with('success', 'Nilai pengumpulan berhasil disimpan.');
    }

    public function storeMateri(Request $request): RedirectResponse|JsonResponse
    {
        $guru = $this->guru($request);
        $data = $this->validasiMateri($request, $guru, true);
        $file = $request->file('file');
        $path = $this->simpanFileLampiran($file, 'materi');

        try {
            DB::transaction(fn () => Materi::create($data + [
                'guru_id' => $guru->id,
                'file_path' => $path,
                'nama_file' => FileMateri::namaAman($file->getClientOriginalName()),
                'mime_type' => FileMateri::mime($file),
                'ekstensi' => FileMateri::ekstensi($file),
                'ukuran_bytes' => $file->getSize(),
            ]));
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        $redirect = route('dashboard.guru.tugas-materi.index', ['tab' => 'materi']);
        $message = 'Materi berhasil ditambahkan.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'redirect' => $redirect,
            ]);
        }

        return redirect()
            ->to($redirect)
            ->with('success', $message);
    }

    public function updateMateri(Request $request, Materi $materi): RedirectResponse|JsonResponse
    {
        $guru = $this->guru($request);
        $this->pastikanMilikGuru($materi, $guru);

        $data = $this->validasiMateri($request, $guru, false);
        $file = $request->file('file');
        $pathLama = $materi->file_path;
        $redirect = route('dashboard.guru.tugas-materi.index', ['tab' => 'materi']);
        $message = 'Materi berhasil diperbarui.';

        if ($file === null) {
            DB::transaction(fn () => $materi->update($data));

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'message' => $message,
                    'redirect' => $redirect,
                ]);
            }

            return redirect()
                ->to($redirect)
                ->with('success', $message);
        }

        $pathBaru = $this->simpanFileLampiran($file, 'materi');
        $data += [
            'file_path' => $pathBaru,
            'nama_file' => FileMateri::namaAman($file->getClientOriginalName()),
            'mime_type' => FileMateri::mime($file),
            'ekstensi' => FileMateri::ekstensi($file),
            'ukuran_bytes' => $file->getSize(),
        ];

        try {
            DB::transaction(fn () => $materi->update($data));
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($pathBaru);

            throw $exception;
        }

        if ($pathLama !== null && $pathLama !== $pathBaru) {
            Storage::disk('local')->delete($pathLama);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'redirect' => $redirect,
            ]);
        }

        return redirect()
            ->to($redirect)
            ->with('success', $message);
    }

    public function destroyMateri(Request $request, Materi $materi): RedirectResponse|JsonResponse
    {
        $guru = $this->guru($request);
        $this->pastikanMilikGuru($materi, $guru);

        $id = $materi->id;
        $path = $materi->file_path;

        DB::transaction(fn () => $materi->delete());

        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }

        $pesan = 'Materi berhasil dihapus.';

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $pesan,
                'data' => ['id' => $id],
            ]);
        }

        return redirect()
            ->route('dashboard.guru.tugas-materi.index', ['tab' => 'materi'])
            ->with('success', $pesan);
    }

    public function detailMateri(Request $request, Materi $materi): JsonResponse
    {
        $guru = $this->guru($request);
        $this->pastikanMilikGuru($materi, $guru);

        return response()->json(['detail' => $this->dataDetailMateri($materi)]);
    }

    public function previewMateri(Request $request, Materi $materi)
    {
        $guru = $this->guru($request);
        $this->pastikanMilikGuru($materi, $guru);

        return $this->responseFileMateri($materi, false);
    }

    public function unduhMateri(Request $request, Materi $materi)
    {
        $guru = $this->guru($request);
        $this->pastikanMilikGuru($materi, $guru);

        return $this->responseFileMateri($materi, true);
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

    protected function queryTugas(
        User $guru,
        string $q,
        ?int $mapelId,
        ?array $rombel,
        ?string $status
    ) {
        return Tugas::query()
            ->with(['mataPelajaran', 'pengumpulan'])
            ->withCount([
                'pengumpulan',
                'pengumpulan as pengumpulan_dinilai_count' => fn ($query) => $query
                    ->where('status', PengumpulanTugas::STATUS_DINILAI),
            ])
            ->where('guru_id', $guru->id)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('judul', 'like', '%'.$q.'%')
                        ->orWhere('deskripsi', 'like', '%'.$q.'%');
                });
            })
            ->when($mapelId !== null, fn ($query) => $query->where('mata_pelajaran_id', $mapelId))
            ->when($rombel !== null, function ($query) use ($rombel) {
                $query->where('kelas', $rombel['kelas'])
                    ->when($rombel['jurusan'] !== '', fn ($sub) => $sub->where('jurusan', $rombel['jurusan']));
            })
            ->when($status === 'lewat', fn ($query) => $query->whereNotNull('deadline')->where('deadline', '<', now()))
            ->when($status === 'aktif', fn ($query) => $query->where(function ($sub) {
                $sub->whereNull('deadline')->orWhere('deadline', '>=', now());
            }))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    protected function queryMateri(
        User $guru,
        string $q,
        ?int $mapelId,
        ?array $rombel
    ) {
        return Materi::query()
            ->with('mataPelajaran')
            ->where('guru_id', $guru->id)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('judul', 'like', '%'.$q.'%')
                        ->orWhere('deskripsi', 'like', '%'.$q.'%');
                });
            })
            ->when($mapelId !== null, fn ($query) => $query->where('mata_pelajaran_id', $mapelId))
            ->when($rombel !== null, function ($query) use ($rombel) {
                $query->where('kelas', $rombel['kelas'])
                    ->when($rombel['jurusan'] !== '', fn ($sub) => $sub->where('jurusan', $rombel['jurusan']));
            })
            ->orderByDesc('tanggal')
            ->orderByDesc('id');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validasiTugas(Request $request, User $guru, bool $untukPerbarui = false): array
    {
        $data = $request->validate([
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajaran,id'],
            'rombel' => ['required_without:kelas', 'nullable', 'string', 'max:200'],
            'kelas' => ['required_without:rombel', 'nullable', 'string', 'max:30'],
            'jurusan' => ['nullable', 'string', 'max:120'],
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:5000'],
            'deadline' => ['nullable', 'date'],
            'file' => [
                'nullable',
                'file',
                'max:'.FileMateri::MAX_UKURAN_KB,
                'mimes:'.implode(',', FileMateri::ekstensiDiizinkan()),
                'mimetypes:'.implode(',', FileMateri::mimeDiizinkan()),
            ],
            'hapus_lampiran' => ['sometimes', 'boolean'],
        ], array_merge([
            'mata_pelajaran_id.required' => 'Mata pelajaran wajib dipilih.',
            'mata_pelajaran_id.exists' => 'Mata pelajaran tidak ditemukan.',
            'rombel.required_without' => 'Kelas wajib dipilih.',
            'kelas.required_without' => 'Kelas wajib dipilih.',
            'judul.required' => 'Judul tugas wajib diisi.',
            'judul.max' => 'Judul tugas terlalu panjang.',
            'deadline.date' => 'Tenggat tugas tidak valid.',
        ], [
            'file.file' => 'Lampiran tugas tidak dapat diproses. Silakan pilih file lain.',
            'file.max' => 'Ukuran lampiran tugas melebihi batas maksimal 20 MB.',
            'file.mimes' => 'Format lampiran tugas tidak didukung. Gunakan PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, JPG, PNG, atau ZIP.',
            'file.mimetypes' => 'Format lampiran tugas tidak didukung. Gunakan PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, JPG, PNG, atau ZIP.',
        ]));

        $file = $request->file('file');

        if ($file !== null && ! FileMateri::valid($file)) {
            throw ValidationException::withMessages([
                'file' => 'Format file tidak didukung.',
            ]);
        }

        $rombel = $this->rombelDariInput($data);

        $this->pastikanCakupan(
            $guru,
            (int) $data['mata_pelajaran_id'],
            $rombel['kelas'],
            $rombel['jurusan']
        );

        return [
            'mata_pelajaran_id' => (int) $data['mata_pelajaran_id'],
            'kelas' => $rombel['kelas'],
            'jurusan' => $rombel['jurusan'],
            'judul' => trim($data['judul']),
            'deskripsi' => filled($data['deskripsi'] ?? null) ? trim($data['deskripsi']) : null,
            'deadline' => filled($data['deadline'] ?? null) ? $data['deadline'] : null,
        ];
    }

    private function simpanFileLampiran(UploadedFile $file, string $tipe): string
    {
        $direktori = $tipe.'/'.date('Y').'/'.date('m');
        $nama = FileMateri::namaAcak(FileMateri::ekstensi($file));
        $path = Storage::disk('local')->putFileAs($direktori, $file, $nama);

        if ($path === false) {
            throw new \RuntimeException('Gagal menyimpan lampiran file.');
        }

        return $path;
    }

    /**
     * @return array{file_path: string, nama_file: string, mime_type: string, ekstensi: string, ukuran_bytes: int|false|null}
     */
    private function dataFileLampiran(UploadedFile $file, string $path): array
    {
        return [
            'file_path' => $path,
            'nama_file' => FileMateri::namaAman($file->getClientOriginalName()),
            'mime_type' => FileMateri::mime($file),
            'ekstensi' => FileMateri::ekstensi($file),
            'ukuran_bytes' => $file->getSize(),
        ];
    }

    /**
     * @return array{file_path: null, nama_file: null, mime_type: null, ekstensi: null, ukuran_bytes: null}
     */
    private function dataFileLampiranKosong(): array
    {
        return [
            'file_path' => null,
            'nama_file' => null,
            'mime_type' => null,
            'ekstensi' => null,
            'ukuran_bytes' => null,
        ];
    }

    private function simpanFileMateri(UploadedFile $file): string
    {
        $direktori = 'materi/'.date('Y').'/'.date('m');
        $nama = FileMateri::namaAcak(FileMateri::ekstensi($file));
        $path = Storage::disk('local')->putFileAs($direktori, $file, $nama);

        if ($path === false) {
            throw new \RuntimeException('Gagal menyimpan file materi.');
        }

        return $path;
    }

    private function dataDetailMateri(Materi $materi): array
    {
        $memilikiFile = $materi->file_path !== null;

        return [
            'id' => $materi->id,
            'judul' => $materi->judul,
            'mata_pelajaran' => $materi->mataPelajaran?->nama,
            'guru' => $materi->guru?->name,
            'kelas' => $materi->kelas,
            'jurusan' => $materi->jurusan,
            'deskripsi' => $materi->deskripsi,
            'tanggal' => $materi->tanggal?->translatedFormat('d F Y'),
            'tanggal_diperbarui' => $materi->updated_at?->translatedFormat('d F Y H:i'),
            'memiliki_file' => $memilikiFile,
            'nama_file' => $materi->nama_file,
            'format' => $materi->format_file,
            'ukuran' => $materi->ukuran_file,
            'bisa_dipratinjau' => $memilikiFile && $materi->bisa_dipratinjau,
            'preview_url' => $memilikiFile
                ? route('dashboard.guru.tugas-materi.materi.preview', $materi)
                : null,
            'unduh_url' => $memilikiFile
                ? route('dashboard.guru.tugas-materi.materi.unduh', $materi)
                : null,
        ];
    }

    protected function validasiMateri(Request $request, User $guru, bool $wajibFile): array
    {
        $data = $request->validate([
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajaran,id'],
            'rombel' => ['required_without:kelas', 'nullable', 'string', 'max:200'],
            'kelas' => ['required_without:rombel', 'nullable', 'string', 'max:30'],
            'jurusan' => ['nullable', 'string', 'max:120'],
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:5000'],
            'tanggal' => ['nullable', 'date'],
            'file' => FileMateri::aturan($wajibFile),
        ], array_merge([
            'mata_pelajaran_id.required' => 'Mata pelajaran wajib dipilih.',
            'mata_pelajaran_id.exists' => 'Mata pelajaran tidak ditemukan.',
            'rombel.required_without' => 'Kelas wajib dipilih.',
            'kelas.required_without' => 'Kelas wajib dipilih.',
            'judul.required' => 'Judul materi wajib diisi.',
            'judul.max' => 'Judul materi terlalu panjang.',
            'tanggal.date' => 'Tanggal materi tidak valid.',
        ], FileMateri::pesanValidasi()));

        $file = $request->file('file');

        if ($file !== null && ! FileMateri::valid($file)) {
            throw ValidationException::withMessages([
                'file' => 'Format file tidak didukung.',
            ]);
        }

        $rombel = $this->rombelDariInput($data);

        $this->pastikanCakupan(
            $guru,
            (int) $data['mata_pelajaran_id'],
            $rombel['kelas'],
            $rombel['jurusan']
        );

        return [
            'mata_pelajaran_id' => (int) $data['mata_pelajaran_id'],
            'kelas' => $rombel['kelas'],
            'jurusan' => $rombel['jurusan'],
            'judul' => trim($data['judul']),
            'deskripsi' => filled($data['deskripsi'] ?? null) ? trim($data['deskripsi']) : null,
            'tanggal' => filled($data['tanggal'] ?? null) ? $data['tanggal'] : now()->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{kelas: string, jurusan: string}
     */
    protected function rombelDariInput(array $data): array
    {
        $rombel = trim((string) ($data['rombel'] ?? ''));

        if ($rombel !== '') {
            [$kelas, $jurusan] = array_pad(explode('|', $rombel, 2), 2, '');
        } else {
            $kelas = (string) ($data['kelas'] ?? '');
            $jurusan = (string) ($data['jurusan'] ?? '');
        }

        $kelas = Kelas::normalize($kelas);
        $jurusan = trim($jurusan);

        if (! Kelas::isValid($kelas)) {
            throw ValidationException::withMessages(['rombel' => Kelas::formatMessage()]);
        }

        return ['kelas' => $kelas, 'jurusan' => $jurusan];
    }

    protected function pastikanCakupan(User $guru, int $mapelId, string $kelas, string $jurusan): void
    {
        $diampu = $this->akademik->mapelDiampu($guru)
            ->contains(fn (MataPelajaran $mapel) => (int) $mapel->id === $mapelId);

        if (! $diampu) {
            throw ValidationException::withMessages([
                'mata_pelajaran_id' => 'Anda tidak mengampu mata pelajaran ini.',
            ]);
        }

        if (! $this->akademik->bolehMengajar($guru, $mapelId, $kelas, $jurusan)) {
            throw ValidationException::withMessages([
                'rombel' => 'Anda tidak mengajar mata pelajaran ini di kelas tersebut.',
            ]);
        }
    }

    protected function pastikanMilikGuru(Tugas|Materi $model, User $guru): void
    {
        if ((int) $model->guru_id !== (int) $guru->id) {
            abort(403);
        }
    }

    protected function mapelFilter(Request $request, array $mapelIds): ?int
    {
        $id = $request->integer('mapel_id');

        return in_array($id, $mapelIds, true) ? $id : null;
    }

    /**
     * @return array{kelas: string, jurusan: string}|null
     */
    protected function rombelFilter(Request $request): ?array
    {
        $rombel = trim((string) $request->query('rombel', ''));

        if ($rombel === '') {
            return null;
        }

        [$kelas, $jurusan] = array_pad(explode('|', $rombel, 2), 2, '');
        $kelas = Kelas::normalize($kelas);

        if (! Kelas::isValid($kelas)) {
            return null;
        }

        return ['kelas' => $kelas, 'jurusan' => trim($jurusan)];
    }

    protected function statusFilter(Request $request): ?string
    {
        $status = trim((string) $request->query('status', ''));

        return in_array($status, ['aktif', 'lewat'], true) ? $status : null;
    }

    protected function nilaiRombel(string $kelas, string $jurusan): string
    {
        return $kelas.'|'.$jurusan;
    }

    protected function labelRombel(string $kelas, string $jurusan): string
    {
        return 'Kelas '.$kelas.($jurusan !== '' ? ' - '.$jurusan : '');
    }
}
