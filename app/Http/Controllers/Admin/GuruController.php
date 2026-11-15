<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\MataPelajaran;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\XlsxExportService;
use App\Support\Credentials;
use App\Support\PasswordPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
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
            'statuses' => self::STATUSES,
            // Dipakai tombol "buat password acak" supaya formatnya sama persis
            // dengan password yang dibuat sistem, contoh "n6STpguX".
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
        ]);

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

        $values = [
            'name' => $data['nama'],
            'email' => $data['email'],
            'nip' => $data['nis'],
            'mata_pelajaran' => $data['mapel'],
            'status' => $data['status'],
        ];

        // Password hanya diganti bila admin mengisinya ulang. Kalau nilai yang
        // dikirim bentrok dengan password awal akun lain, sistem membuatkan
        // pengganti yang unik.
        $password = null;

        if ($data['password'] !== null && $data['password'] !== '') {
            $password = Credentials::ensureUniquePassword($data['password']);
            $values['password'] = $password;
            $values['initial_password'] = Crypt::encryptString($password);
            $values['must_change_password'] = true;
        }

        $user->update($values);
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
                $user->mata_pelajaran ?? '',
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
     * @return array{id: int, nip: string|null, nama: string, email: string|null, mapel: string|null, status: string}
     */
    protected function row(User $user): array
    {
        return [
            'id' => $user->id,
            'nip' => $user->nip,
            'nama' => $user->name,
            'email' => $user->email,
            'mapel' => $user->mata_pelajaran,
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
     * @return array{nama: string, nis: string, email: string, mapel: string, status: string, password: string|null}
     */
    protected function validated(Request $request, ?User $user = null): array
    {
        $validated = $request->validate([
            // Formulir memakai nama kolom "nis" (label: NIP) supaya sama dengan
            // halaman Data Siswa; nilai ini disimpan ke kolom `nip` guru.
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
            'mapel' => ['required', 'string', 'max:120'],
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
            'mapel.required' => 'Mata pelajaran wajib diisi.',
            'status.in' => 'Status akun tidak valid.',
            'password.min' => 'Password minimal terdiri dari '.PasswordPolicy::MIN_LENGTH.' karakter.',
            'password.regex' => 'Password harus mengandung huruf besar dan angka.',
        ]);

        return [
            'nama' => trim($validated['nama']),
            'nis' => trim($validated['nis']),
            'email' => mb_strtolower(trim($validated['email'])),
            'mapel' => trim($validated['mapel']),
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

        if ($mapel !== '' && $user->mata_pelajaran !== $mapel) {
            return false;
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
