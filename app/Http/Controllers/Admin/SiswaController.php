<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\KelasRombel;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\XlsxExportService;
use App\Support\Credentials;
use App\Support\Kelas;
use App\Support\PasswordPolicy;
use App\Support\StrukturKelas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

class SiswaController extends Controller
{
    public function index()
    {
        $siswa = $this->query()->get()->map(fn (User $user) => $this->row($user));

        $count = fn (string $status) => $siswa->where('status', $status)->count();

        return view('dashboard.admin.data-siswa.index', [
            'stats' => [
                [
                    'key' => 'total',
                    'label' => 'Total Siswa',
                    'value' => $siswa->count(),
                    'icon' => 'lucide-users',
                ],
                [
                    'key' => 'aktif',
                    'label' => 'Siswa Aktif',
                    'value' => $count(User::STATUS_AKTIF),
                    'icon' => 'lucide-user-check',
                ],
                [
                    'key' => 'nonaktif',
                    'label' => 'Siswa Nonaktif',
                    'value' => $count(User::STATUS_NONAKTIF),
                    'icon' => 'lucide-user-x',
                ],
                [
                    'key' => 'lulus',
                    'label' => 'Siswa Lulus',
                    'value' => $count(User::STATUS_LULUS),
                    'icon' => 'lucide-graduation-cap',
                ],
            ],
            'siswaData' => [
                'siswa' => $siswa->values(),
                'statuses' => User::STATUSES,
                'kelas_contoh' => Kelas::CONTOH,
                'kelas_contoh_rombel' => Kelas::CONTOH_ROMBEL,
                // Pola dikirim dari sini, bukan ditulis ulang di JavaScript, supaya
                // pengecekan di browser dan di server tidak bisa berbeda.
                'kelas_pattern' => trim(Kelas::PATTERN, '/'),
                'tingkat_umum' => Kelas::tingkatUmum(),
                // Set karakter password diambil dari sumber yang sama dengan yang
                // dipakai server, supaya tombol "buat acak" menghasilkan password
                // dengan format yang benar-benar sama (misal "n6STpguX").
                'password_charsets' => Credentials::passwordCharsets(),
                'password_length' => Credentials::PASSWORD_LENGTH,
                // Pilihan filter jurusan -> tingkat -> rombel ikut dikirim dari
                // server supaya rombel yang tampil di browser sama persis dengan
                // kelas yang benar-benar dipakai siswa. Kelas yang sudah
                // terdaftar di Data Kelas ikut serta walau belum ada siswanya.
                'struktur_kelas' => StrukturKelas::dariSiswa(
                    $siswa,
                    KelasRombel::query()->get(['jurusan', 'kelas'])
                ),
            ],
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
            'role' => 'siswa',
            'kelas' => $data['kelas'],
            'jurusan' => $data['jurusan'],
            'nis' => $data['nis'],
            'nip' => null,
            'mata_pelajaran' => null,
            'status' => $data['status'],
            'initial_password' => Crypt::encryptString($password),
            'must_change_password' => true,
        ]);

        app(ActivityLogger::class)->record(
            ActivityLog::TIPE_DATA_SISWA,
            "Menambahkan data siswa {$user->name} (NIS {$user->nis}).",
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => "Data siswa {$data['nama']} berhasil ditambahkan.",
            'data' => [
                'siswa' => $this->row($user),
                'password' => $password,
            ],
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->ensureSiswa($user);

        $data = $this->validated($request, $user);

        $values = [
            'name' => $data['nama'],
            'email' => $data['email'],
            'kelas' => $data['kelas'],
            'jurusan' => $data['jurusan'],
            'nis' => $data['nis'],
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
            ActivityLog::TIPE_DATA_SISWA,
            "Memperbarui data siswa {$user->name}.",
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => "Data siswa {$data['nama']} berhasil diperbarui.",
            'data' => [
                'siswa' => $this->row($user),
                'password' => $password,
            ],
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->ensureSiswa($user);

        $id = $user->id;
        $name = $user->name;

        $user->delete();

        app(ActivityLogger::class)->record(ActivityLog::TIPE_DATA_SISWA, "Menghapus data siswa {$name}.");

        return response()->json([
            'status' => 'success',
            'message' => "Data siswa {$name} berhasil dihapus.",
            'data' => [
                'id' => $id,
            ],
        ]);
    }

    /**
     * Unduh data siswa yang sedang difilter sebagai file Excel, lengkap dengan
     * password awal supaya bisa langsung diserahkan ke siswa.
     *
     * Password tidak ikut dikirim ke halaman Data Siswa. Kalau ikut, seluruh
     * password siswa ada di sumber HTML dan bisa dibaca siapa pun yang membuka
     * View Source. Di sini password baru dibuka di server, hanya di file yang
     * diunduh, dan jawabannya diberi header no-store supaya tidak disimpan
     * di cache browser atau proxy.
     */
    public function export(Request $request)
    {
        $filter = $this->validatedFilter($request);

        $siswa = $this->queryFiltered($filter);

        $headers = ['No', 'NIS', 'Nama Siswa', 'Email', 'Kelas', 'Jurusan', 'Status', 'Password Awal', 'Status Password'];

        $baris = [];
        foreach ($siswa as $number => $user) {
            $baris[] = [
                $number + 1,
                $user->nis,
                $user->name,
                $user->email,
                $user->kelas,
                $user->jurusan,
                $user->statusLabel(),
                $user->visiblePassword() ?: 'sudah diubah',
                $user->must_change_password ? 'Belum ubah password' : 'Sudah ubah password',
            ];
        }

        $response = app(XlsxExportService::class)->download(
            'data-siswa-'.now()->format('Ymd-His').'.xlsx',
            'Data Siswa',
            $headers,
            $baris,
            [
                'subjudul' => 'Diunduh '.now()->format('d M Y H:i').' • '.$siswa->count().' siswa',
                // NIS & Kelas sebagai teks supaya "12-1" tidak berubah jadi tanggal.
                'text_columns' => [2, 5],
            ]
        );

        $response->headers->set('Cache-Control', 'no-store, no-cache');
        $response->headers->set('Pragma', 'no-cache');

        // Dibaca tombol export untuk menampilkan "berapa siswa yang
        // tersimpan", tanpa perlu mengurai isi file di browser.
        $response->headers->set('X-Total-Siswa', (string) $siswa->count());

        return $response;
    }

    /**
     * Bentuk data yang dikirim ke frontend (satu sumber untuk tabel & modal).
     * Kelas dikirim apa adanya sesuai yang tersimpan di database, supaya
     * tampilan, modal edit, dan export Excel semuanya sama.
     *
     * @return array{id: int, nis: string|null, nama: string, email: string|null, kelas: string|null, tingkat: string|null, jurusan: string|null, status: string}
     */
    protected function row(User $user): array
    {
        return [
            'id' => $user->id,
            'nis' => $user->nis,
            'nama' => $user->name,
            'email' => $user->email,
            'kelas' => $user->kelas,
            'tingkat' => Kelas::tingkat($user->kelas),
            'jurusan' => $user->jurusan,
            'status' => $user->statusLabel(),
        ];
    }

    protected function query()
    {
        return User::query()
            ->where('role', 'siswa')
            ->orderBy('kelas')
            ->orderBy('name');
    }

    /**
     * Baca parameter filter dari request dan buang yang tidak dikenal, supaya
     * query yang dipakai export sama persis dengan yang dipakai filter di
     * browser (lihat fungsi filtered() di view Data Siswa).
     *
     * @return array{q: string, status: string|null, jurusan: string|null, tingkat: string|null, rombel: string|null, rombel_match: string|null}
     */
    protected function validatedFilter(Request $request): array
    {
        $text = fn (?string $value): ?string => ($value === null || $value === '') ? null : $value;

        return [
            'q' => trim((string) $request->query('q', '')),
            'status' => $text($request->query('status')),
            'jurusan' => $text($request->query('jurusan')),
            'tingkat' => $text($request->query('tingkat')),
            'rombel' => $text($request->query('rombel')),
            // "kelas" berarti rombel adalah kelas sungguhan ("12-1"), sedangkan
            // "tingkat" berarti tingkat itu cuma punya satu kelas sehingga yang
            // ditampilkan sebagai rombel sebenarnya nomor tingkat ("12").
            'rombel_match' => $text($request->query('rombel_match')),
        ];
    }

    /**
     * Siswa yang lolos filter, sama seperti yang tampil di tabel.
     *
     * Disaring di PHP, bukan di SQL, supaya hasilnya identik dengan filter di
     * browser. tingkat dan rombel bergantung pada kelas yang sudah dirapikan,
     * dan halaman ini memang sudah mengirim semua siswa ke browser, jadi
     * tidak ada biaya tambahan yang berarti.
     *
     * @param  array{q: string, status: string|null, jurusan: string|null, tingkat: string|null, rombel: string|null, rombel_match: string|null}  $filter
     * @return Collection<int, User>
     */
    protected function queryFiltered(array $filter)
    {
        $q = mb_strtolower($filter['q']);

        return $this->query()
            ->get()
            ->filter(function (User $user) use ($filter, $q) {
                if ($filter['status'] !== null && $user->statusLabel() !== $filter['status']) {
                    return false;
                }

                if ($filter['jurusan'] !== null && $user->jurusan !== $filter['jurusan']) {
                    return false;
                }

                if ($filter['tingkat'] !== null && Kelas::tingkat($user->kelas) !== $filter['tingkat']) {
                    return false;
                }

                // Tingkat yang hanya punya satu kelas tidak punya nomor rombel,
                // jadi memilih rombel untuk tingkat itu berarti memilih semua
                // kelas di tingkat tersebut.
                if ($filter['rombel'] !== null
                    && $filter['rombel_match'] === 'kelas'
                    && Kelas::normalize((string) $user->kelas) !== $filter['rombel']) {
                    return false;
                }

                if ($q === '') {
                    return true;
                }

                return str_contains(mb_strtolower((string) $user->name), $q)
                    || str_contains((string) $user->nis, $q)
                    || str_contains(mb_strtolower((string) $user->email), $q);
            })
            ->values();
    }

    /**
     * @return array{nama: string, nis: string, email: string, kelas: string, jurusan: string, status: string, password: string|null}
     */
    protected function validated(Request $request, ?User $user = null): array
    {
        // Rapikan penulisan kelas lebih dulu supaya "12 - 1" tetap lolos validasi.
        if ($request->has('kelas')) {
            $request->merge(['kelas' => Kelas::normalize((string) $request->input('kelas'))]);
        }

        $validated = $request->validate([
            'nis' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Za-z0-9\-\/]+$/',
                Rule::unique('users', 'nis')->ignore($user?->id),
            ],
            'nama' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'string',
                'email',
                'max:120',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'kelas' => ['required', 'string', 'max:30', 'regex:'.Kelas::PATTERN],
            'jurusan' => ['required', 'string', 'max:120'],
            'status' => ['required', Rule::in(User::STATUSES)],
            'password' => [
                'nullable',
                'string',
                'min:'.PasswordPolicy::MIN_LENGTH,
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
            ],
        ], [
            'nis.required' => 'NIS wajib diisi.',
            'nis.regex' => 'NIS hanya boleh berisi huruf, angka, tanda hubung, atau garis miring.',
            'nis.unique' => 'NIS sudah dipakai siswa lain.',
            'nama.required' => 'Nama siswa wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah dipakai akun lain.',
            'kelas.required' => 'Kelas wajib diisi.',
            'kelas.regex' => Kelas::formatMessage(),
            'jurusan.required' => 'Jurusan wajib diisi.',
            'status.in' => 'Status akun tidak valid.',
            'password.min' => 'Password minimal terdiri dari '.PasswordPolicy::MIN_LENGTH.' karakter.',
            'password.regex' => 'Password harus mengandung huruf besar dan angka.',
        ]);

        return [
            'nama' => trim($validated['nama']),
            'nis' => trim($validated['nis']),
            'email' => mb_strtolower(trim($validated['email'])),
            'kelas' => $validated['kelas'],
            'jurusan' => trim($validated['jurusan']),
            'status' => $validated['status'],
            'password' => $validated['password'] ?? null,
        ];
    }

    protected function ensureSiswa(User $user): void
    {
        if ($user->role !== 'siswa') {
            abort(404);
        }
    }
}
