<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use App\Support\LoginIdentifier;
use App\Support\LoginLockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class LoginController extends Controller
{
    public function showLoginForm(Request $request)
    {
        // Form login sengaja tidak di-redirect otomatis ke dashboard bila user
        // sudah login, supaya bisa memilih role lain dan login dengan kredensial berbeda.
        $role = $request->session()->get('login.role', 'admin');

        if (! LoginIdentifier::supports($role)) {
            $role = 'admin';
        }

        // Latih countdown di frontend bila akun masih lockout; nilainya selalu
        // dihitung ulang dari database agar akurat walau halaman di-refresh.
        $lock = null;
        $lockKey = $request->session()->get('login.lock_key');

        if (is_string($lockKey) && $lockKey !== '' && ($remaining = LoginLockout::remaining($lockKey))) {
            $lock = [
                'key' => $lockKey,
                'retry_after' => $remaining,
            ];
        }

        return view('auth.login', [
            'lock' => $lock,
            'roles' => LoginIdentifier::frontendPayload(),
            'selectedRole' => $role,
        ]);
    }

    public function login(Request $request)
    {
        $role = (string) $request->input('role', '');

        if (! LoginIdentifier::supports($role)) {
            return $this->sendLoginFailed(['role' => 'Pilih role yang valid.'], $request);
        }

        $field = LoginIdentifier::field($role);

        $validator = Validator::make(
            $request->all(),
            [
                'role' => ['required', Rule::in(LoginIdentifier::roles())],
                $field => LoginIdentifier::rules($role),
                'password' => ['required', 'string'],
            ],
            LoginIdentifier::messages($role)
        );

        $request->session()->put('login.role', $role);

        if ($validator->fails()) {
            return $this->sendLoginFailed(
                $this->relabelErrors($validator->errors()->toArray(), $role),
                $request
            );
        }

        $identifier = LoginIdentifier::normalize($role, (string) $validator->validated()[$field]);
        $password = (string) $validator->validated()['password'];
        $lockKey = LoginIdentifier::lockKey($role, $identifier);

        // 1) Akun masih terkunci -> tolak percobaan login (termasuk password benar).
        if ($remaining = LoginLockout::remaining($lockKey)) {
            $request->session()->put('login.lock_key', $lockKey);

            return $this->sendLocked(
                'Terlalu banyak percobaan login. Akun sementara dikunci.',
                $field,
                $lockKey,
                $remaining,
                $request
            );
        }

        // Catat kegagalan login. Lockout aktif mulai kegagalan ke-5.
        $loginFailed = function (string $message) use ($request, $field, $lockKey) {
            $state = LoginLockout::recordFailure($lockKey);

            // Belum mencapai batas kegagalan -> cukup tampilkan error login biasa.
            if ($state['retry_after'] === null) {
                return $this->sendLoginFailed([$field => $message], $request);
            }

            // Kegagalan ke-5 ke atas -> akun terkunci, tampilkan countdown.
            $request->session()->put('login.lock_key', $lockKey);

            return $this->sendLocked($message, $field, $lockKey, $state['retry_after'], $request);
        };

        $user = LoginIdentifier::findUser($role, $identifier);

        if (! $user) {
            return $loginFailed(LoginIdentifier::label($role).' atau password salah');
        }

        if (! $user->is_active) {
            return $this->sendLoginFailed(
                [$field => 'Akun sedang tidak aktif. Hubungi admin sekolah.'],
                $request
            );
        }

        // Cocokkan password pada akun hasil lookup role + identifier, lalu
        // terbitkan JWT. Password dicek langsung terhadap hash milik user
        // sehingga identifier NIP/NIS tetap bisa dipakai walau email kosong.
        if (! Hash::check($password, (string) $user->password)) {
            return $loginFailed(LoginIdentifier::label($role).' atau password salah');
        }

        try {
            $token = JWTAuth::fromUser($user);
        } catch (JWTException $e) {
            return $loginFailed('Terjadi kesalahan saat autentikasi, coba lagi');
        }

        LoginLockout::reset($lockKey);

        $guard = $user->role;

        // Bersihkan hanya state milik role yang sedang login. State role lain
        // pada sesi/browser yang sama dibiarkan utuh agar tiap tab tetap
        // terisolasi dan login role baru tidak menendang role yang sedang aktif.
        foreach (array_keys($request->session()->all()) as $key) {
            if (str_starts_with($key, "login_{$guard}_")
                || str_starts_with($key, "remember_{$guard}_")
                || $key === "jwt_token_{$guard}") {
                $request->session()->forget($key);
            }
        }
        $request->session()->forget(['login.lock_key', 'login.role']);

        // Buang seluruh instance guard yang ter-cache agar tidak ada sisa
        // referensi user dari sesi sebelumnya (pengganti setUser(null) yang
        // tidak menerima null secara type-safe).
        Auth::forgetGuards();

        // Rotasi & penghancuran ID sesi lama ditangani oleh guard login()
        // (updateSession() memanggil regenerate(true)), sehingga tidak perlu
        // regenerate() manual yang justru meninggalkan ID sesi lama valid.
        Auth::guard('web')->login($user);
        if (in_array($guard, ['admin', 'guru', 'siswa'], true)) {
            try {
                Auth::guard($guard)->login($user);
            } catch (\Throwable $e) {
            }
        }
        $request->session()->put("jwt_token_{$guard}", $token);
        $request->session()->put('jwt_token', $token);
        $request->session()->put('login.role', $user->role);

        app(ActivityLogger::class)->record(ActivityLog::TIPE_AUTH, 'Berhasil login ke sistem.', $user);

        $needsPasswordSetup = $user->must_change_password && $user->role !== 'admin';

        // Arahkan ke dashboard khusus role agar URL menempelkan identitas role
        // pada tab. Dengan begitu refresh tab tidak bergantung pada guard `web`
        // (yang berisi role terakhir login) dan tetap menampilkan role yang benar
        // walau ada role lain yang login di tab lain pada browser yang sama.
        $redirect = $needsPasswordSetup
            ? route('password.setup')
            : match ($user->role) {
                'admin' => route('dashboard.admin.index'),
                'guru' => route('dashboard.guru.index'),
                default => route('dashboard.siswa.index'),
            };

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Login berhasil, silakan lanjutkan.',
                'data' => [
                    'token' => $token,
                    'role' => $user->role,
                    'must_change_password' => $needsPasswordSetup,
                    'redirect' => $redirect,
                ],
            ]);
        }

        // `intended` dapat menunjuk ke "/dashboard" generik (mis. saat tamu
        // mencoba membuka dashboard lalu diarahkan ke login). Dashboard generik
        // menentukan role dari guard `web` yang berisi role terakhir login,
        // sehingga berpotensi menampilkan role yang salah bila ada role lain
        // yang login di tab lain pada browser yang sama. Buang intended generik
        // itu agar tujuan jatuh ke dashboard khusus role.
        $intended = $request->session()->pull('url.intended');
        $intendedPath = is_string($intended) ? rtrim((string) parse_url($intended, PHP_URL_PATH), '/') : null;

        if ($intendedPath === '/dashboard') {
            $intended = null;
        }

        return ($intended !== null && $intended !== '')
            ? redirect()->to($intended)
            : redirect()->to($redirect);
    }

    public function logout(Request $request)
    {
        $requestedRole = (string) $request->input('role', '');
        $roleGuards = ['admin', 'guru', 'siswa'];

        $guard = in_array($requestedRole, $roleGuards, true) ? $requestedRole : null;
        $user = null;

        if ($guard !== null) {
            try {
                $user = Auth::guard($guard)->user();
            } catch (\Throwable $e) {
            }
        }

        if ($guard === null) {
            $user = Auth::user();
            $guard = $user?->role;
        }

        $guard ??= 'web';

        $roleToken = $request->session()->get("jwt_token_{$guard}");
        $legacyToken = $request->session()->get('jwt_token');

        $tokens = [];
        if (is_string($roleToken) && $roleToken !== '') {
            $tokens[] = $roleToken;
        }

        $ownsLegacyToken = is_string($legacyToken)
            && $legacyToken !== ''
            && in_array($legacyToken, $tokens, true);

        foreach (array_unique($tokens) as $token) {
            try {
                JWTAuth::setToken($token)->invalidate();
            } catch (JWTException $e) {
                // Token mungkin sudah tidak valid atau sudah masuk blacklist.
            }
        }

        if ($user) {
            app(ActivityLogger::class)->record(ActivityLog::TIPE_AUTH, 'Keluar dari sistem.', $user);
        }

        // Keluarkan hanya guard milik role yang sedang logout. Guard role lain
        // pada sesi browser yang sama tetap utuh.
        try {
            Auth::guard($guard)->logout();
        } catch (\Throwable $e) {
        }

        Auth::forgetGuards();

        // Guard `web` hanya cerminan login terakhir. Hapus bila memang berisi
        // user yang sama, tetapi jangan sentuh bila berisi role lain.
        try {
            $webUser = Auth::guard('web')->user();

            if ($user && $webUser && $webUser->getAuthIdentifier() === $user->getAuthIdentifier()) {
                Auth::guard('web')->logout();
                Auth::forgetGuards();
            }
        } catch (\Throwable $e) {
        }

        foreach (array_keys($request->session()->all()) as $key) {
            if (str_starts_with($key, "login_{$guard}_") || str_starts_with($key, "remember_{$guard}_")) {
                $request->session()->forget($key);
            }
        }

        $request->session()->forget("jwt_token_{$guard}");

        if ($ownsLegacyToken) {
            $request->session()->forget('jwt_token');
        }

        $request->session()->forget(['login.lock_key', 'login.role']);

        return redirect()->route('login')
            ->with('logged_out', true)
            ->with('logged_out_role', in_array($guard, $roleGuards, true) ? $guard : null)
            ->with('success', 'Berhasil keluar dari akun.');
    }

    /**
     * Samakan nama key error dengan kolom yang dipakai frontend per role
     * (identitas aktif hanya punya satu field: email / nip / nis).
     *
     * @param  array<string, array<int, string>>  $errors
     * @return array<string, array<int, string>>
     */
    protected function relabelErrors(array $errors, string $role): array
    {
        $field = LoginIdentifier::field($role);
        $relabeled = [];

        foreach ($errors as $key => $messages) {
            $target = in_array($key, ['role', 'password'], true) ? $key : $field;
            $relabeled[$target] = $messages;
        }

        return $relabeled;
    }

    protected function sendLoginFailed(array $errors, Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'message' => reset($errors),
                'errors' => $errors,
            ], 422);
        }

        throw ValidationException::withMessages($errors);
    }

    /**
     * Respons ketika login terkunci (setelah percobaan gagal atau masih dalam masa lockout).
     * Selalu disertai retry_after (detik) yang dihitung server untuk countdown di frontend.
     */
    protected function sendLocked(string $message, string $field, string $lockKey, int $retryAfter, Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'message' => $message,
                'data' => [
                    'field' => $field,
                    'lock_key' => $lockKey,
                    'attempts' => LoginLockout::attempts($lockKey),
                    'retry_after' => $retryAfter,
                ],
            ], 429);
        }

        throw ValidationException::withMessages([$field => $message]);
    }
}
