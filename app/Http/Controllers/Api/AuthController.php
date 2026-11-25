<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\LoginLockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email dan password wajib diisi',
                'errors' => $validator->errors(),
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        $email = LoginLockout::normalizeEmail((string) $request->input('email', ''));

        // Akun masih terkunci -> tolak percobaan login (termasuk password benar).
        if ($remaining = LoginLockout::remaining($email)) {
            return $this->respondLocked(
                'Terlalu banyak percobaan login. Akun sementara dikunci.',
                $email,
                $remaining
            );
        }

        $recordFailure = function (string $message) use ($email) {
            $state = LoginLockout::recordFailure($email);

            $payload = [
                'status' => 'error',
                'message' => $message,
            ];

            // Belum mencapai batas kegagalan -> error login biasa (401).
            if ($state['retry_after'] === null) {
                return response()->json($payload, 401);
            }

            // Kegagalan ke-5 ke atas -> akun terkunci, beri info retry (429).
            $payload['data'] = [
                'email' => $email,
                'attempts' => $state['attempts'],
                'retry_after' => $state['retry_after'],
            ];

            return response()->json($payload, 429);
        };

        // Beri tahu user ketika email-nya memang belum terdaftar.
        if ($email !== '' && ! User::where('email', $email)->exists()) {
            return $recordFailure('Akun dengan email ini tidak ditemukan');
        }

        try {
            if (! $token = JWTAuth::attempt($credentials)) {
                return $recordFailure('Email atau password salah');
            }
        } catch (JWTException $e) {

            return response()->json([
                'status' => 'error',
                'message' => 'Could not create token',
                'error' => $e->getMessage(),
            ], 500);
        }

        LoginLockout::reset($email);

        $user = JWTAuth::setToken($token)->authenticate();

        if ($user instanceof User) {
            app(ActivityLogger::class)->record(ActivityLog::TIPE_AUTH, 'Berhasil login ke sistem melalui API.', $user);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Login berhasil',
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'must_change_password' => (bool) $user->must_change_password,
                ],
            ],
        ]);
    }

    /**
     * Respons saat login terkunci; retry_after dihitung server agar countdown
     * di client akurat.
     */
    protected function respondLocked(string $message, string $email, int $retryAfter)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'data' => [
                'email' => $email,
                'attempts' => LoginLockout::attempts($email),
                'retry_after' => $retryAfter,
            ],
        ], 429);
    }

    public function profile()
    {
        try {

            $user = JWTAuth::parseToken()->authenticate();

            return response()->json([
                'status' => 'success',
                'message' => 'Profil pengguna berhasil diambil',
                'data' => $user,
            ]);
        } catch (JWTException $e) {
            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Token tidak valid atau telah kedaluwarsa',
                    'error' => $e->getMessage(),
                ],
                401
            );
        }
    }

    public function logout()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (JWTException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal keluar, token tidak valid',
                'error' => $e->getMessage(),
            ], 500);
        }

        if ($user instanceof User) {
            app(ActivityLogger::class)->record(ActivityLog::TIPE_AUTH, 'Keluar dari sistem melalui API.', $user);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil keluar dari sistem',
        ]);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (JWTException $e) {
            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Token tidak valid atau telah kedaluwarsa',
                    'error' => $e->getMessage(),
                ],
                401
            );
        }

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Password saat ini tidak sesuai',
            ], 422);
        }

        $validated = $validator->validated();

        if (Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'status' => 'success',
                'message' => 'Password berhasil diperbarui',
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        app(ActivityLogger::class)->record(ActivityLog::TIPE_PASSWORD, 'Mengubah password akun melalui API.', $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Password berhasil diperbarui',
        ]);
    }
}
