<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Materi;
use App\Models\PengumpulanTugas;
use App\Models\Tugas;
use App\Models\User;
use App\Services\GuruAkademik;
use App\Support\Kelas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pengelolaan Tugas & Materi oleh Guru.
 *
 * Semua data dibatasi pada mata pelajaran dan rombel yang diampu guru. Daftar
 * rombel berasal dari jadwal/nilai (lihat GuruAkademik), sehingga guru tidak
 * bisa membagikan tugas ke kelas yang bukan tanggung jawabnya.
 */
class TugasMateriController extends Controller
{
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

    public function storeTugas(Request $request): RedirectResponse
    {
        $guru = $this->guru($request);
        $data = $this->validasiTugas($request, $guru);

        Tugas::create($data + ['guru_id' => $guru->id]);

        return redirect()
            ->route('dashboard.guru.tugas-materi.index', ['tab' => 'tugas'])
            ->with('success', 'Tugas berhasil ditambahkan.');
    }

    public function updateTugas(Request $request, Tugas $tugas): RedirectResponse
    {
        $guru = $this->guru($request);
        $this->pastikanMilikGuru($tugas, $guru);

        $tugas->update($this->validasiTugas($request, $guru));

        return redirect()
            ->route('dashboard.guru.tugas-materi.index', ['tab' => 'tugas'])
            ->with('success', 'Tugas berhasil diperbarui.');
    }

    public function destroyTugas(Request $request, Tugas $tugas): RedirectResponse
    {
        $guru = $this->guru($request);
        $this->pastikanMilikGuru($tugas, $guru);

        $tugas->delete();

        return redirect()
            ->route('dashboard.guru.tugas-materi.index', ['tab' => 'tugas'])
            ->with('success', 'Tugas berhasil dihapus.');
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
        ], [
            'nilai.numeric' => 'Nilai harus berupa angka.',
            'nilai.between' => 'Nilai harus berada di antara 0 sampai 100.',
        ]);

        $nilai = filled($data['nilai'] ?? null) ? round((float) $data['nilai'], 2) : null;

        $pengumpulan->update([
            'nilai' => $nilai,
            'status' => $nilai === null
                ? PengumpulanTugas::STATUS_DIKUMPULKAN
                : PengumpulanTugas::STATUS_DINILAI,
        ]);

        return redirect()
            ->route('dashboard.guru.tugas-materi.pengumpulan', $pengumpulan->tugas_id)
            ->with('success', 'Nilai pengumpulan berhasil disimpan.');
    }

    public function storeMateri(Request $request): RedirectResponse
    {
        $guru = $this->guru($request);
        $data = $this->validasiMateri($request, $guru);

        Materi::create($data + ['guru_id' => $guru->id]);

        return redirect()
            ->route('dashboard.guru.tugas-materi.index', ['tab' => 'materi'])
            ->with('success', 'Materi berhasil ditambahkan.');
    }

    public function updateMateri(Request $request, Materi $materi): RedirectResponse
    {
        $guru = $this->guru($request);
        $this->pastikanMilikGuru($materi, $guru);

        $materi->update($this->validasiMateri($request, $guru));

        return redirect()
            ->route('dashboard.guru.tugas-materi.index', ['tab' => 'materi'])
            ->with('success', 'Materi berhasil diperbarui.');
    }

    public function destroyMateri(Request $request, Materi $materi): RedirectResponse
    {
        $guru = $this->guru($request);
        $this->pastikanMilikGuru($materi, $guru);

        $materi->delete();

        return redirect()
            ->route('dashboard.guru.tugas-materi.index', ['tab' => 'materi'])
            ->with('success', 'Materi berhasil dihapus.');
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
     * @return array{mata_pelajaran_id: int, kelas: string, jurusan: string, judul: string, deskripsi: string|null, deadline: string|null}
     */
    protected function validasiTugas(Request $request, User $guru): array
    {
        $data = $request->validate([
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajaran,id'],
            'rombel' => ['required_without:kelas', 'nullable', 'string', 'max:200'],
            'kelas' => ['required_without:rombel', 'nullable', 'string', 'max:30'],
            'jurusan' => ['nullable', 'string', 'max:120'],
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:5000'],
            'deadline' => ['nullable', 'date'],
        ], [
            'mata_pelajaran_id.required' => 'Mata pelajaran wajib dipilih.',
            'mata_pelajaran_id.exists' => 'Mata pelajaran tidak ditemukan.',
            'rombel.required_without' => 'Kelas wajib dipilih.',
            'kelas.required_without' => 'Kelas wajib dipilih.',
            'judul.required' => 'Judul tugas wajib diisi.',
            'judul.max' => 'Judul tugas terlalu panjang.',
            'deadline.date' => 'Tenggat tugas tidak valid.',
        ]);

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

    /**
     * @return array{mata_pelajaran_id: int, kelas: string, jurusan: string, judul: string, deskripsi: string|null, tanggal: string}
     */
    protected function validasiMateri(Request $request, User $guru): array
    {
        $data = $request->validate([
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajaran,id'],
            'rombel' => ['required_without:kelas', 'nullable', 'string', 'max:200'],
            'kelas' => ['required_without:rombel', 'nullable', 'string', 'max:30'],
            'jurusan' => ['nullable', 'string', 'max:120'],
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:5000'],
            'tanggal' => ['nullable', 'date'],
        ], [
            'mata_pelajaran_id.required' => 'Mata pelajaran wajib dipilih.',
            'mata_pelajaran_id.exists' => 'Mata pelajaran tidak ditemukan.',
            'rombel.required_without' => 'Kelas wajib dipilih.',
            'kelas.required_without' => 'Kelas wajib dipilih.',
            'judul.required' => 'Judul materi wajib diisi.',
            'judul.max' => 'Judul materi terlalu panjang.',
            'tanggal.date' => 'Tanggal materi tidak valid.',
        ]);

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
        return 'Kelas '.$kelas.($jurusan !== '' ? ' · '.$jurusan : '');
    }
}
