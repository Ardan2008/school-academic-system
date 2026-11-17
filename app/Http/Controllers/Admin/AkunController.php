<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\KelasRombel;
use App\Models\User;
use App\Services\AccountImportService;
use App\Services\ActivityLogger;
use App\Services\TemplateFormatDataService;
use App\Services\XlsxExportService;
use App\Support\Credentials;
use App\Support\Kelas;
use App\Support\LoginIdentifier;
use App\Support\StrukturKelas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

class AkunController extends Controller
{
    protected const ROLE_LABELS = [
        'admin' => 'Admin',
        'guru' => 'Guru',
        'siswa' => 'Siswa',
    ];

    protected ?array $kaskadeCache = null;

    public function index(Request $request)
    {
        $filters = array_merge([
            'role' => '',
            'jurusan' => '',
            'tingkat' => '',
            'kelas' => '',
            'search' => '',
        ], $request->only(['role', 'jurusan', 'tingkat', 'kelas', 'search']));

        $kaskade = $this->kaskade();

        // Seluruh akun (sesuai filter URL) dimuat sekaligus, lalu pencarian,
        // filter, dan pagination dijalankan di sisi klien memakai komponen yang
        // sama dengan halaman Data lainnya (mini-pagination, 5 baris/halaman).
        $users = $this->filteredQuery($request)
            ->orderBy('role')
            ->orderBy('kelas')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'kelas', 'jurusan', 'role', 'must_change_password'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'kelas' => $user->kelas,
                'jurusan' => $user->jurusan,
                'role' => $user->role,
                'must_change_password' => (bool) $user->must_change_password,
            ])
            ->values();

        return view('dashboard.admin.manajemen-akun.index', [
            'payload' => [
                'akun' => $users,
                'kaskade' => $kaskade,
                'filters' => $filters,
                'currentUserId' => auth()->id(),
            ],
            'jurusanOptions' => array_map(fn (array $j) => $j['nama'], $kaskade),
            'tingkatOptions' => $this->pilihanTingkat($kaskade, $filters['jurusan']),
            'kelasOptions' => $this->pilihanRombel($kaskade, $filters['jurusan'], $filters['tingkat']),
            'filters' => $filters,
            'templateOptions' => TemplateFormatDataService::options(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        // Rapikan dulu sebelum validasi supaya spasi di tepi dan huruf besar
        // pada email tidak ditolak sebagai format yang salah.
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'string',
                'email',
                'max:120',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'kelas' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(['admin', 'guru', 'siswa'])],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan.',
            'role.in' => 'Peran tidak valid.',
        ]);

        if ($user->id === auth()->id() && $data['role'] !== 'admin') {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tidak dapat mengubah peran akun yang sedang kamu gunakan.',
                ], 422);
            }

            return back()->withErrors(['role' => 'Tidak dapat mengubah peran akun yang sedang kamu gunakan.']);
        }

        $data['name'] = trim($data['name']);
        $data['email'] = mb_strtolower(trim($data['email']));
        $data['kelas'] = filled($data['kelas'] ?? null) ? trim((string) $data['kelas']) : null;

        if ($data['role'] !== $user->role) {
            $identifier = LoginIdentifier::field($data['role']);

            // Login Guru memakai NIP dan Siswa memakai NIS. Perubahan peran
            // tanpa identifier baru akan membuat akun tidak bisa login sama
            // sekali, jadi ditolak dengan pesan yang bisa ditindaklanjuti.
            if (blank($user->{$identifier})) {
                $message = 'Peran tidak dapat diubah karena akun belum memiliki '
                    .LoginIdentifier::label($data['role']).' untuk login. '
                    .'Lengkapi data melalui menu Data '.ucfirst($data['role']).'.';

                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $message,
                    ], 422);
                }

                return back()->withErrors(['role' => $message]);
            }

            if ($data['role'] === 'siswa') {
                $data['kelas'] ??= $user->kelas;

                if (blank($data['kelas'])) {
                    $message = 'Kelas wajib diisi sebelum akun diubah menjadi siswa.';

                    if ($request->expectsJson()) {
                        return response()->json([
                            'status' => 'error',
                            'message' => $message,
                        ], 422);
                    }

                    return back()->withErrors(['kelas' => $message]);
                }
            }

            $data = array_merge($data, $this->fieldPerPeran($data['role']));
        }

        $user->update($data);

        app(ActivityLogger::class)->record(
            ActivityLog::TIPE_MANAJEMEN_AKUN,
            "Memperbarui akun {$user->name} ({$user->role}).",
            $request->user()
        );

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Data akun berhasil diperbarui.',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'kelas' => $user->kelas,
                    'role' => $user->role,
                ],
            ]);
        }

        return redirect()->route('dashboard.admin.manajemen-akun.index')->with('success', 'Data akun berhasil diperbarui.');
    }

    public function resetPassword(Request $request, User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak dapat mereset password akun yang sedang kamu gunakan.',
            ], 422);
        }

        // Password hasil reset harus berbeda dari password awal akun lain,
        // supaya tidak ada dua akun dengan password sama.
        $password = Credentials::generateUniquePassword();

        $user->update([
            'password' => $password,
            'initial_password' => Crypt::encryptString($password),
            'must_change_password' => true,
        ]);

        app(ActivityLogger::class)->record(
            ActivityLog::TIPE_MANAJEMEN_AKUN,
            "Mereset password akun {$user->name} ({$user->role}).",
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Password berhasil direset.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'password' => $password,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $users = $this->filteredQuery($request)
            ->orderBy('role')
            ->orderBy('kelas')
            ->orderBy('name')
            ->get();

        $headers = ['Role', 'Nama', 'Email', 'Kelas', 'Password Awal', 'Status'];

        $baris = [];
        foreach ($users as $user) {
            $baris[] = [
                self::ROLE_LABELS[$user->role] ?? ucfirst($user->role),
                $user->name,
                $user->email,
                $user->kelas ?? '',
                $this->initialPasswordOf($user),
                $user->must_change_password ? 'Belum ubah password' : 'Sudah ubah password',
            ];
        }

        $response = app(XlsxExportService::class)->download(
            'akun-'.now()->format('Ymd-His').'.xlsx',
            'Manajemen Akun',
            $headers,
            $baris,
            [
                'subjudul' => 'Diunduh '.now()->format('d M Y H:i').' • '.$users->count().' akun',
                // Kelas sebagai teks supaya "12-1" tidak berubah jadi tanggal.
                'text_columns' => [4],
            ]
        );

        $response->headers->set('Cache-Control', 'no-store, no-cache');
        $response->headers->set('Pragma', 'no-cache');

        // Dibaca tombol export untuk menampilkan berapa akun yang tersimpan.
        $response->headers->set('X-Total-Akun', (string) $users->count());

        return $response;
    }

    public function templates(Request $request)
    {
        $selected = $request->query('jenis', []);

        if (! is_array($selected)) {
            $selected = [$selected];
        }

        $selected = array_values(array_unique(array_filter(
            array_map(fn ($value) => is_string($value) ? trim($value) : '', $selected),
            fn (string $value) => $value !== ''
        )));

        if ($selected === [] || array_diff($selected, array_keys(TemplateFormatDataService::TYPES)) !== []) {
            abort(422, 'Pilih minimal satu template yang valid.');
        }

        $unduhan = app(TemplateFormatDataService::class)->download($selected);

        $response = response($unduhan['content'], 200, [
            'Content-Type' => $unduhan['mime'],
            'Content-Disposition' => 'attachment; filename="'.$unduhan['filename'].'"',
        ]);

        $response->headers->set('Cache-Control', 'no-store, no-cache');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Template-Count', (string) count($selected));

        return $response;
    }

    public function slips(Request $request)
    {
        $items = $this->slipUsers($request)->map(fn (User $user) => [
            'user' => $user,
            'initial_password' => $this->initialPasswordOf($user),
        ]);

        return view('dashboard.admin.manajemen-akun.slips', [
            'items' => $items,
            'filters' => $request->only(['role', 'jurusan', 'tingkat', 'kelas', 'search']),
        ]);
    }

    public function exportSlips(Request $request)
    {
        $users = $this->slipUsers($request);

        $headers = ['Role', 'Nama', 'Email', 'NIP/NIS', 'Kelas', 'Jurusan', 'Password'];

        $baris = [];
        foreach ($users as $user) {
            $password = $this->initialPasswordOf($user);

            $baris[] = [
                self::ROLE_LABELS[$user->role] ?? ucfirst($user->role),
                $user->name,
                $user->email,
                $user->role === 'guru' ? $user->nip : $user->nis,
                $user->kelas ?: '',
                $user->jurusan ?: '',
                $password !== '' ? $password : 'Password Sudah Diubah',
            ];
        }

        $response = app(XlsxExportService::class)->download(
            'Slip_Password_Akun.xlsx',
            'Slip Password Akun',
            $headers,
            $baris,
            [
                'subjudul' => 'Diunduh '.now()->format('d M Y H:i').' - '.$users->count().' akun',
                // NIP/NIS, kelas, dan password dipaksa teks agar tidak berubah format.
                'text_columns' => [4, 5, 7],
                'sheet' => 'Slip Password',
            ]
        );

        $response->headers->set('Cache-Control', 'no-store, no-cache');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Total-Slip', (string) $users->count());

        return $response;
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ], [
            'file.required' => 'Pilih file terlebih dahulu.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['csv', 'txt', 'xlsx', 'xls'], true)) {
            $message = 'Format file tidak didukung. Gunakan .xlsx, .xls, .csv, atau .txt.';

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $message], 422);
            }

            return back()->withErrors(['file' => $message]);
        }

        try {
            $result = app(AccountImportService::class)->import($file->getRealPath(), $extension);
        } catch (\RuntimeException $e) {
            $message = $e->getMessage() !== '' ? $e->getMessage() : 'File tidak dapat diproses.';

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $message], 422);
            }

            return back()->withErrors(['file' => $message]);
        } catch (\Throwable $e) {
            $message = 'Gagal membaca file. Pastikan file tidak rusak dan sesuai format.';

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $message], 422);
            }

            return back()->withErrors(['file' => $message]);
        }

        session()->flash('import_result', $result);

        $typeLabel = $result['type_label'] ?? 'Data';

        app(ActivityLogger::class)->record(
            ActivityLog::TIPE_MANAJEMEN_AKUN,
            sprintf(
                'Mengimpor %s: %d berhasil, %d dilewati, %d gagal.',
                $typeLabel,
                count($result['created']),
                count($result['skipped']),
                count($result['errors'])
            ),
            $request->user()
        );

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Impor '.$typeLabel.' selesai.',
                'data' => [
                    'type' => $result['type'] ?? null,
                    'type_label' => $typeLabel,
                    'created' => count($result['created']),
                    'skipped' => count($result['skipped']),
                    'errors' => count($result['errors']),
                    'truncated' => $result['truncated'] ?? false,
                    'max_rows' => $result['max_rows'] ?? AccountImportService::MAX_ROWS,
                    'created_list' => $result['created'],
                    'skipped_list' => $result['skipped'],
                    'error_list' => $result['errors'],
                ],
            ]);
        }

        return redirect()->route('dashboard.admin.manajemen-akun.index');
    }

    /**
     * Akun guru dan siswa yang tampil pada Slip Password, memakai filter
     * halaman Manajemen Akun yang sama dengan daftar dan cetak slip.
     */
    protected function slipUsers(Request $request)
    {
        return $this->filteredQuery($request)
            ->whereIn('role', ['guru', 'siswa'])
            ->orderBy('kelas')
            ->orderBy('name')
            ->get();
    }

    protected function filteredQuery(Request $request)
    {
        $query = User::query();

        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        if ($jurusan = trim((string) $request->input('jurusan'))) {
            $query->where('jurusan', $jurusan);
        }

        if ($tingkat = trim((string) $request->input('tingkat'))) {
            // Filter tingkat mencakup kelas yang ditulis tingkat saja ("12")
            // sekaligus yang ber-rombel ("12-1", "12-2").
            $query->where(function ($q) use ($tingkat) {
                $q->where('kelas', $tingkat)->orWhere('kelas', 'like', $tingkat.'-%');
            });
        }

        if ($kelas = trim((string) $request->input('kelas'))) {
            $kelas = Kelas::normalize($kelas);

            if (str_contains($kelas, '-')) {
                // Kelas ber-rombel: cocokkan persis, contoh "12-2".
                $query->where('kelas', $kelas);
            } else {
                // Satu tingkat satu rombel: layar menampilkan nomor tingkatnya
                // saja, jadi kelas "12" ikut menjaring "12-1" dan kawan-kawan.
                $query->where(function ($q) use ($kelas) {
                    $q->where('kelas', $kelas)->orWhere('kelas', 'like', $kelas.'-%');
                });
            }
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        return $query;
    }

    /**
     * Struktur pilihan filter Jurusan -> Tingkat -> Kelas/Rombel, sama seperti
     * yang dipakai Data Siswa. Dibangun dari akun siswa yang benar-benar ada
     * beserta kelas yang sudah terdaftar di Data Kelas, supaya pilihan filter
     * selalu sama dengan isi tabel.
     *
     * @return array<int, array{nama: string, jumlah: int, tingkat: array<int, array{nilai: string, jumlah: int, berrombel: bool, rombel: array<int, array{nilai: string, jumlah: int, match: string, terdaftar: bool}>}>}>
     */
    protected function kaskade(): array
    {
        return $this->kaskadeCache ??= StrukturKelas::dariSiswa(
            User::query()
                ->where('role', 'siswa')
                ->whereNotNull('jurusan')
                ->where('jurusan', '!=', '')
                ->get(['jurusan', 'kelas']),
            KelasRombel::query()->get(['jurusan', 'kelas'])
        );
    }

    /**
     * Pilihan tingkat untuk satu jurusan (atau semua jurusan saat jurusan
     * belum dipilih), diurutkan dari tingkat tertinggi.
     *
     * @param  array<int, array{nama: string, tingkat: array<int, array{nilai: string, ...}>}>  $kaskade
     * @return array<int, string>
     */
    protected function pilihanTingkat(array $kaskade, ?string $jurusan): array
    {
        $ada = [];

        foreach ($kaskade as $j) {
            if ($jurusan !== null && $jurusan !== '' && $j['nama'] !== $jurusan) {
                continue;
            }

            foreach ($j['tingkat'] as $t) {
                $ada[(string) $t['nilai']] = true;
            }
        }

        $daftar = array_map('strval', array_keys($ada));

        usort($daftar, fn (string $a, string $b) => ((int) $b) <=> ((int) $a));

        return $daftar;
    }

    /**
     * Pilihan kelas/rombel untuk kombinasi jurusan dan tingkat.
     *
     * @param  array<int, array{nama: string, tingkat: array<int, array{nilai: string, rombel: array<int, array{nilai: string, ...}>}>}>  $kaskade
     * @return array<int, string>
     */
    protected function pilihanRombel(array $kaskade, ?string $jurusan, ?string $tingkat): array
    {
        $ada = [];

        foreach ($kaskade as $j) {
            if ($jurusan !== null && $jurusan !== '' && $j['nama'] !== $jurusan) {
                continue;
            }

            foreach ($j['tingkat'] as $t) {
                if ($tingkat !== null && $tingkat !== '' && (string) $t['nilai'] !== $tingkat) {
                    continue;
                }

                foreach ($t['rombel'] as $r) {
                    $ada[(string) $r['nilai']] = true;
                }
            }
        }

        $daftar = array_map('strval', array_keys($ada));

        usort($daftar, StrukturKelas::bandingkanKelas(...));

        return $daftar;
    }

    /**
     * Kolom yang harus dikosongkan saat peran akun berubah, supaya data lama
     * tidak tertinggal dan terbaca sebagai data peran yang baru.
     *
     * @return array<string, null>
     */
    protected function fieldPerPeran(string $role): array
    {
        return match ($role) {
            'admin' => [
                'nip' => null,
                'nis' => null,
                'kelas' => null,
                'jurusan' => null,
                'mata_pelajaran' => null,
            ],
            'guru' => ['nis' => null],
            'siswa' => ['nip' => null, 'mata_pelajaran' => null],
        };
    }

    protected function initialPasswordOf(User $user): string
    {
        return $user->visiblePassword();
    }
}
