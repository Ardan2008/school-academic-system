<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\KelasRombel;
use App\Models\MataPelajaran;
use App\Models\PenugasanMengajar;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\PenugasanMengajarService;
use App\Services\XlsxExportService;
use App\Support\Credentials;
use App\Support\Kelas;
use App\Support\PasswordPolicy;
use App\Support\PerbandinganData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Data Guru. Memakai akun guru sungguhan dari tabel users (role "guru"),
 * seperti Data Siswa memakai akun siswa. Export Excel ikut menampilkan
 * password awal supaya bisa langsung diserahkan ke guru.
 */
class GuruController extends Controller
{
    /** Status yang boleh dipilih admin pada halaman Data Guru. */
    protected const STATUSES = ['Aktif', 'Nonaktif', 'Cuti'];

    public function __construct(private readonly PenugasanMengajarService $penugasan) {}

    public function index(): View
    {
        $guru = $this->query()->get()->map(fn (User $user) => $this->row($user));

        $count = fn (string $status) => $guru->where('status', $status)->count();

        $stats = [
            [
                'key' => 'total',
                'label' => 'Total Guru',
                'value' => $guru->count(),
                'icon' => 'lucide-users',
            ],
            [
                'key' => 'aktif',
                'label' => 'Guru Aktif',
                'value' => $count('Aktif'),
                'icon' => 'lucide-user-check',
            ],
            [
                'key' => 'nonaktif',
                'label' => 'Guru Nonaktif',
                'value' => $count('Nonaktif'),
                'icon' => 'lucide-user-x',
            ],
            [
                'key' => 'cuti',
                'label' => 'Guru Cuti',
                'value' => $count('Cuti'),
                'icon' => 'lucide-calendar-off',
            ],
        ];

        // Warna ikon per kartu supaya tiap status mudah dibedakan.
        $statTone = [
            'total' => ['bg-blue-100 text-primary-60', '#1E86FF'],
            'aktif' => ['bg-emerald-100 text-emerald-600', '#059669'],
            'nonaktif' => ['bg-red-100 text-red-500', '#EF4444'],
            'cuti' => ['bg-amber-100 text-amber-600', '#D97706'],
        ];

        $guruData = [
            'guru' => $guru->values(),
            'mapel' => $this->mapelList(),
            'mapel_options' => $this->penugasanMapelOptions(),
            'penugasan_kelas' => $this->penugasanKelasOptions(),
            'statuses' => self::STATUSES,
            'password_charsets' => Credentials::passwordCharsets(),
            'password_length' => Credentials::PASSWORD_LENGTH,
        ];

        return view('dashboard.admin.data-guru.index', [
            'guru' => $guru,
            'mapelList' => $this->mapelList(),
            'stats' => $stats,
            'statTone' => $statTone,
            'guruData' => $guruData,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $password = Credentials::ensureUniquePassword($data['password']);

        $user = DB::transaction(function () use ($data, $password) {
            $user = User::create([
                'name' => $data['nama'],
                'email' => $data['email'],
                'password' => $password,
                'role' => 'guru',
                'nip' => $data['nis'],
                'nis' => null,
                'mata_pelajaran' => $data['mapel'],
                'status' => $data['status'],
                'initial_password' => Crypt::encryptString($password),
                'must_change_password' => true,
                'penugasan_diatur' => $data['penugasan_ada'],
            ]);

            $this->syncPenugasan($user, $data['mata_pelajaran_id'], $data['kelas_ids']);

            return $user;
        });

        $user->refresh();

        app(ActivityLogger::class)->record(
            ActivityLog::TIPE_DATA_GURU,
            "Menambahkan data guru {$user->name} (NIP {$user->nip}).",
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => "Data guru {$data['nama']} berhasil ditambahkan.",
            'data' => [
                'guru' => $this->row($user),
                'password' => $password,
            ],
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->ensureGuru($user);

        $data = $this->validated($request, $user);
        $password = null;
        $passwordBerubah = $data['password'] !== null && $data['password'] !== ''
            && ! Hash::check($data['password'], (string) $user->password);

        $penugasanSekarang = $this->penugasanSekarang($user);

        $identik = PerbandinganData::identik(
            $user,
            [
                'nama' => $data['nama'],
                'email' => $data['email'],
                'nis' => $data['nis'],
                'status' => $data['status'],
                'mapel' => $data['mapel'],
                'mata_pelajaran_tersimpan' => $data['mapel'],
                'mata_pelajaran_id' => $data['mata_pelajaran_id'],
                'kelas_ids' => $data['kelas_ids'],
                'penugasan_ada' => $data['penugasan_ada'],
            ],
            [
                'nama' => 'name',
                'nis' => 'nip',
                'mata_pelajaran_tersimpan' => 'mata_pelajaran',
                'mapel' => function ($nilai, User $model) {
                    if ($this->penugasan->sudahDiatur($model)) {
                        return $this->penugasan->mapelDiampu($model)->pluck('nama')->implode(', ');
                    }

                    return trim((string) $model->mata_pelajaran);
                },
                'mata_pelajaran_id' => fn () => $penugasanSekarang['mata_pelajaran_id'],
                'kelas_ids' => fn () => $penugasanSekarang['kelas_ids'],
                'penugasan_ada' => 'penugasan_diatur',
            ],
            ['password']
        );

        if ($identik && ! $passwordBerubah) {
            return response()->json([
                'status' => 'success',
                'message' => "Data guru {$data['nama']} berhasil diperbarui.",
                'data' => [
                    'guru' => $this->row($user),
                    'password' => null,
                ],
            ]);
        }

        DB::transaction(function () use ($data, $user, &$password, $passwordBerubah): void {
            $values = [
                'name' => $data['nama'],
                'email' => $data['email'],
                'nip' => $data['nis'],
                'status' => $data['status'],
                'mata_pelajaran' => $data['mapel'],
                'penugasan_diatur' => $data['penugasan_ada'],
            ];

            if ($passwordBerubah) {
                $password = Credentials::ensureUniquePassword($data['password']);
                $values['password'] = $password;
                $values['initial_password'] = Crypt::encryptString($password);
                $values['must_change_password'] = true;
            }

            $user->update($values);
            $this->syncPenugasan($user, $data['mata_pelajaran_id'], $data['kelas_ids']);
        });

        $user->refresh();

        app(ActivityLogger::class)->record(
            ActivityLog::TIPE_DATA_GURU,
            "Memperbarui data guru {$user->name}.",
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => "Data guru {$data['nama']} berhasil diperbarui.",
            'data' => [
                'guru' => $this->row($user),
                'password' => $password,
            ],
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->ensureGuru($user);

        $id = $user->id;
        $name = $user->name;

        $user->delete();

        app(ActivityLogger::class)->record(ActivityLog::TIPE_DATA_GURU, "Menghapus data guru {$name}.");

        return response()->json([
            'status' => 'success',
            'message' => "Data guru {$name} berhasil dihapus.",
            'data' => [
                'id' => $id,
            ],
        ]);
    }

    public function penugasan(User $user): JsonResponse
    {
        $this->ensureGuru($user);

        return response()->json([
            'status' => 'success',
            'data' => [
                'guru' => $this->row($user),
                'mapel' => $this->penugasanMapelOptions(),
                'kelas' => $this->penugasanKelasOptions(),
                'penugasan' => $this->penugasan->ringkasan($user)->values(),
            ],
        ]);
    }

    public function simpanPenugasan(Request $request, User $user): JsonResponse
    {
        $this->ensureGuru($user);

        $validated = $request->validate([
            'mata_pelajaran_id' => ['nullable', 'integer', 'exists:mata_pelajaran,id'],
            'kelas_ids' => ['nullable', 'array'],
            'kelas_ids.*' => ['integer', 'exists:kelas,id'],
            'penugasan' => ['nullable', 'array'], // backward compat
            'penugasan.*.mata_pelajaran_id' => ['nullable', 'integer', 'exists:mata_pelajaran,id'],
            'penugasan.*.kelas_ids' => ['nullable', 'array'],
            'penugasan.*.kelas_ids.*' => ['integer', 'exists:kelas,id'],
        ], [
            'mata_pelajaran_id.exists' => 'Mata pelajaran tidak ditemukan.',
            'kelas_ids.*.exists' => 'Kelas tidak ditemukan.',
        ]);

        $mapelId = isset($validated['mata_pelajaran_id']) ? (int) $validated['mata_pelajaran_id'] : null;
        $kelasIds = collect($validated['kelas_ids'] ?? [])
            ->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->unique()->values()->all();

        if (! $mapelId && empty($kelasIds) && ! empty($validated['penugasan'])) {
            $first = collect($validated['penugasan'])->first(fn ($item) => is_array($item) && ! empty($item['mata_pelajaran_id']));
            if (is_array($first)) {
                $mapelId = (int) $first['mata_pelajaran_id'];
                $kelasIds = collect($first['kelas_ids'] ?? [])->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->unique()->values()->all();
            }
        }

        if ($mapelId && empty($kelasIds)) {
            throw ValidationException::withMessages(['kelas_ids' => 'Minimal satu kelas wajib dipilih.']);
        }
        if (! $mapelId && ! empty($kelasIds)) {
            throw ValidationException::withMessages(['mata_pelajaran_id' => 'Mata pelajaran wajib dipilih.']);
        }

        $mapelNama = $mapelId ? trim((string) MataPelajaran::query()->whereKey($mapelId)->value('nama')) : '';
        $penugasanSekarang = $this->penugasanSekarang($user);

        if (PerbandinganData::identik(
            $user,
            [
                'mata_pelajaran_id' => $mapelId,
                'kelas_ids' => $kelasIds,
                'penugasan_diatur' => (bool) $mapelId,
                'mata_pelajaran' => $mapelId ? $mapelNama : null,
            ],
            [
                'mata_pelajaran_id' => fn () => $penugasanSekarang['mata_pelajaran_id'],
                'kelas_ids' => fn () => $penugasanSekarang['kelas_ids'],
            ]
        )) {
            return response()->json([
                'status' => 'success',
                'message' => "Penugasan mengajar {$user->name} berhasil disimpan.",
                'data' => [
                    'guru' => $this->row($user),
                    'penugasan' => $this->penugasan->ringkasan($user)->values(),
                ],
            ]);
        }

        DB::transaction(function () use ($user, $mapelId, $kelasIds, $mapelNama): void {
            $this->syncPenugasan($user, $mapelId, $kelasIds);
            $user->forceFill([
                'penugasan_diatur' => (bool) $mapelId,
                'mata_pelajaran' => $mapelId ? $mapelNama : null,
            ])->save();
        });

        $user->refresh();

        app(ActivityLogger::class)->record(
            ActivityLog::TIPE_DATA_GURU,
            "Memperbarui penugasan mengajar {$user->name}.",
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => "Penugasan mengajar {$user->name} berhasil disimpan.",
            'data' => [
                'guru' => $this->row($user),
                'penugasan' => $this->penugasan->ringkasan($user)->values(),
            ],
        ]);
    }

    protected function penugasanSekarang(User $user): array
    {
        $baris = PenugasanMengajar::query()
            ->where('guru_id', $user->id)
            ->get(['mata_pelajaran_id', 'kelas_id']);

        $mapelIds = $baris
            ->pluck('mata_pelajaran_id')
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($mapelIds->count() !== 1) {
            return ['mata_pelajaran_id' => null, 'kelas_ids' => []];
        }

        return [
            'mata_pelajaran_id' => (int) $mapelIds->first(),
            'kelas_ids' => $baris
                ->pluck('kelas_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->sort()
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int, int>  $kelasIds
     */
    protected function syncPenugasan(User $user, ?int $mataPelajaranId, array $kelasIds): void
    {
        PenugasanMengajar::query()->where('guru_id', $user->id)->delete();

        if (! $mataPelajaranId) {
            return;
        }

        $rows = collect($kelasIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->map(fn (int $kelasId) => [
                'guru_id' => $user->id,
                'mata_pelajaran_id' => $mataPelajaranId,
                'kelas_id' => $kelasId,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

        if ($rows !== []) {
            PenugasanMengajar::query()->insert($rows);
        }
    }

    /**
     * Unduh daftar guru yang sedang difilter sebagai file Excel, lengkap
     * dengan password awal supaya bisa langsung diserahkan ke guru.
     */
    public function export(Request $request): Response
    {
        $guru = $this->query()
            ->get()
            ->filter(fn (User $user) => $this->lolosFilter($user, $request))
            ->values();

        $headers = ['No', 'NIP', 'Nama Guru', 'Email', 'Mata Pelajaran', 'Status', 'Password Awal', 'Status Password'];

        $baris = [];
        foreach ($guru as $i => $user) {
            $baris[] = [
                $i + 1,
                $user->nip,
                $user->name,
                $user->email,
                $this->labelMapel($user),
                $user->status,
                $user->visiblePassword() ?: 'sudah diubah',
                $user->must_change_password ? 'Belum ubah password' : 'Sudah ubah password',
            ];
        }

        $response = app(XlsxExportService::class)->download(
            'data-guru-'.now()->format('Ymd-His').'.xlsx',
            'Data Guru',
            $headers,
            $baris,
            [
                'subjudul' => 'Diunduh '.now()->format('d M Y H:i').' • '.$guru->count().' guru',
                // NIP tetap teks supaya angka awalnya tidak hilang.
                'text_columns' => [2],
            ]
        );

        $response->headers->set('Cache-Control', 'no-store, no-cache');
        $response->headers->set('Pragma', 'no-cache');

        // Dibaca tombol export untuk menampilkan berapa guru yang tersimpan.
        $response->headers->set('X-Total-Guru', (string) $guru->count());

        return $response;
    }

    protected function penugasanMapelOptions(): array
    {
        return MataPelajaran::query()
            ->orderBy('nama')
            ->get(['id', 'nama', 'kode'])
            ->map(fn (MataPelajaran $mapel) => [
                'id' => (int) $mapel->id,
                'nama' => (string) $mapel->nama,
                'kode' => $mapel->kode,
            ])
            ->values()
            ->all();
    }

    protected function penugasanKelasOptions(): array
    {
        $kelas = KelasRombel::query()->get(['id', 'jurusan', 'kelas'])->all();

        return collect(KelasRombel::urutkan($kelas))
            ->map(fn (KelasRombel $item) => [
                'id' => (int) $item->id,
                'jurusan' => (string) $item->jurusan,
                'kelas' => Kelas::normalize((string) $item->kelas),
                'label' => trim((string) $item->jurusan).' / '.Kelas::normalize((string) $item->kelas),
            ])
            ->values()
            ->all();
    }

    protected function labelMapel(User $user): string
    {
        if ($this->penugasan->sudahDiatur($user)) {
            return $this->penugasan->mapelDiampu($user)->pluck('nama')->implode(', ');
        }

        return trim((string) $user->mata_pelajaran);
    }

    /**
     * Pilihan mata pelajaran untuk dropdown formulir, diambil dari Data Mata
     * Pelajaran supaya guru hanya bisa diampu pada mapel yang benar-benar
     * terdaftar sebagai master data.
     *
     * @return array<int, string>
     */
    protected function mapelList(): array
    {
        return MataPelajaran::query()
            ->orderBy('nama')
            ->get(['nama'])
            ->pluck('nama')
            ->map(fn (string $nama) => trim($nama))
            ->filter(fn (string $nama) => $nama !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Bentuk data yang dikirim ke frontend (satu sumber untuk tabel & modal).
     *
     * @return array{id: int, nip: string|null, nama: string, email: string|null, mapel: string|null, mapel_list: array<int, string>, kelas_diampu: array<int, array{id: int, nama: string, jurusan: string}>, kelas_detail: array<int, array{mata_pelajaran_id: int, nama: string, kode: mixed, kelas: array<int, array{id: int, nama: string, jurusan: string}>}>, status: string}
     */
    protected function row(User $user): array
    {
        $label = $this->labelMapel($user);
        $mapelList = collect(explode(',', $label))
            ->map(fn (string $nama) => trim($nama))
            ->filter(fn (string $nama) => $nama !== '')
            ->values()
            ->all();
        $kelasDetail = $this->penugasan->detailKelas($user);
        $kelasDiampu = $kelasDetail
            ->flatMap(fn (array $grup) => collect($grup['kelas']))
            ->unique(fn (array $kelas): int => $kelas['id'])
            ->values()
            ->all();

        $ringkasan = $this->penugasan->ringkasan($user);
        $mapelId = $ringkasan->count() === 1 ? (int) ($ringkasan->first()['mata_pelajaran_id'] ?? 0) : null;
        $kelasIds = $ringkasan->count() === 1 ? array_values($ringkasan->first()['kelas_ids'] ?? []) : [];

        return [
            'id' => $user->id,
            'nip' => $user->nip,
            'nama' => $user->name,
            'email' => $user->email,
            'mapel' => $label !== '' ? $label : null,
            'mapel_list' => $mapelList,
            'mata_pelajaran_id' => $mapelId ?: null,
            'kelas_ids' => $kelasIds,
            'penugasan_ada' => $this->penugasan->sudahDiatur($user),
            'kelas_diampu' => $kelasDiampu,
            'kelas_detail' => $kelasDetail->all(),
            'status' => $user->status,
        ];
    }

    protected function query(): Builder
    {
        return User::query()
            ->where('role', 'guru')
            ->orderBy('nip')
            ->orderBy('name');
    }

    /**
     * @return array{nama: string, nis: string, email: string, mapel: string, mata_pelajaran_id: int|null, kelas_ids: array<int, int>, penugasan_ada: bool, status: string, password: string|null}
     */
    protected function validated(Request $request, ?User $user = null): array
    {
        $validated = $request->validate([
            'nis' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9\-\/]+$/',
                Rule::unique('users', 'nip')->ignore($user?->id),
            ],
            'nama' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'string',
                'email',
                'max:120',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'mapel' => ['nullable', 'string', 'max:120'],
            'mata_pelajaran_id' => ['nullable', 'integer', 'exists:mata_pelajaran,id'],
            'kelas_ids' => ['nullable', 'array'],
            'kelas_ids.*' => ['integer', 'exists:kelas,id'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'password' => [
                'nullable',
                'string',
                'min:'.PasswordPolicy::MIN_LENGTH,
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
            ],
        ], [
            'nis.required' => 'NIP wajib diisi.',
            'nis.regex' => 'NIP hanya boleh berisi huruf, angka, tanda hubung, atau garis miring.',
            'nis.unique' => 'NIP sudah dipakai guru lain.',
            'nama.required' => 'Nama guru wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah dipakai akun lain.',
            'mata_pelajaran_id.exists' => 'Mata pelajaran yang dipilih tidak terdaftar.',
            'kelas_ids.*.exists' => 'Kelas yang dipilih tidak terdaftar.',
            'status.in' => 'Status akun tidak valid.',
            'password.min' => 'Password minimal terdiri dari '.PasswordPolicy::MIN_LENGTH.' karakter.',
            'password.regex' => 'Password harus mengandung huruf besar dan angka.',
        ]);

        $mapelId = isset($validated['mata_pelajaran_id']) ? (int) $validated['mata_pelajaran_id'] : null;
        $kelasIds = collect($validated['kelas_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $legacyMapel = trim((string) ($validated['mapel'] ?? ''));
        $legacyOnly = ! $mapelId && empty($kelasIds) && $legacyMapel !== '';

        if ($legacyOnly) {
            return [
                'nama' => trim($validated['nama']),
                'nis' => trim($validated['nis']),
                'email' => mb_strtolower(trim($validated['email'])),
                'mapel' => $legacyMapel,
                'mata_pelajaran_id' => null,
                'kelas_ids' => [],
                'penugasan_ada' => false,
                'status' => $validated['status'],
                'password' => $validated['password'] ?? null,
            ];
        }

        if (! $mapelId && $legacyMapel !== '') {
            $legacy = MataPelajaran::query()->whereRaw('LOWER(nama) = LOWER(?)', [$legacyMapel])->first()
                ?? MataPelajaran::query()->where('nama', 'like', '%'.$legacyMapel.'%')->first();
            if ($legacy) {
                $mapelId = (int) $legacy->id;
            }
        }

        if ($mapelId && empty($kelasIds)) {
            throw ValidationException::withMessages([
                'kelas_ids' => 'Pilih minimal satu kelas untuk mata pelajaran yang dipilih.',
            ]);
        }
        if (! $mapelId && ! empty($kelasIds)) {
            throw ValidationException::withMessages([
                'mata_pelajaran_id' => 'Mata pelajaran wajib dipilih sebelum memilih kelas.',
            ]);
        }

        $mapelNama = '';
        if ($mapelId) {
            $mapelNama = trim((string) MataPelajaran::query()->whereKey($mapelId)->value('nama'));
            if ($mapelNama === '') {
                throw ValidationException::withMessages([
                    'mata_pelajaran_id' => 'Mata pelajaran yang dipilih tidak valid.',
                ]);
            }
        }

        return [
            'nama' => trim($validated['nama']),
            'nis' => trim($validated['nis']),
            'email' => mb_strtolower(trim($validated['email'])),
            'mapel' => $mapelId ? $mapelNama : '',
            'mata_pelajaran_id' => $mapelId ?: null,
            'kelas_ids' => $kelasIds,
            'penugasan_ada' => (bool) $mapelId,
            'status' => $validated['status'],
            'password' => $validated['password'] ?? null,
        ];
    }

    protected function ensureGuru(User $user): void
    {
        if ($user->role !== 'guru') {
            abort(404);
        }
    }

    /**
     * Filter yang sama seperti di browser supaya hasil export sama dengan
     * yang terlihat di tabel.
     */
    protected function lolosFilter(User $user, Request $request): bool
    {
        $status = trim((string) $request->input('status'));
        $mapel = trim((string) $request->input('mapel'));
        $q = trim((string) $request->input('q'));

        if ($status !== '' && $user->status !== $status) {
            return false;
        }

        if ($mapel !== '') {
            $mapelGuru = array_map('trim', explode(',', $this->labelMapel($user)));

            if (! in_array($mapel, $mapelGuru, true)) {
                return false;
            }
        }

        if ($q !== '') {
            $q = mb_strtolower($q);

            $cocok = str_contains(mb_strtolower($user->name), $q)
                || str_contains((string) $user->nip, $q)
                || str_contains(mb_strtolower((string) $user->email), $q);

            if (! $cocok) {
                return false;
            }
        }

        return true;
    }
}
