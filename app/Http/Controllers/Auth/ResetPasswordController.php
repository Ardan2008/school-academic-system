<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\PasswordResetException;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use App\Services\PasswordResetService;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Halaman atur ulang password lewat tautan bertoken hasil persetujuan admin.
 *
 * Token hanya disimpan sebagai hash di database, single-use, dan berlaku
 * 30 menit. Password tidak pernah dikirim lewat email.
 */
class ResetPasswordController extends Controller
{
    public function show(string $token, PasswordResetService $passwordReset)
    {
        $record = $passwordReset->findToken($token);

        if (! $record || ! $record->isUsable()) {
            return $this->expiredView($token);
        }

        return view('auth.reset-password', [
            'token' => $token,
            'tokenRecord' => $record,
            'email' => $record->user?->email,
        ]);
    }

    public function store(Request $request, string $token, PasswordResetService $passwordReset)
    {
        $record = $passwordReset->findToken($token);

        $validator = Validator::make(
            $request->all(),
            [
                'password' => PasswordPolicy::rules(),
                'password_confirmation' => ['required', 'string'],
            ],
            PasswordPolicy::messages()
        );

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first(),
                    'errors' => [
                        'password' => $validator->errors()->get('password'),
                    ],
                ], 422);
            }

            return back()
                ->withErrors(['password' => $validator->errors()->first()])
                ->withInput();
        }

        if (! $record || ! $record->isUsable()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => PasswordResetException::tokenInvalid()->getMessage(),
                ], 410);
            }

            return $this->expiredView($token);
        }

        try {
            $user = $passwordReset->consume($record, (string) $validator->validated()['password'], $request->ip());
        } catch (PasswordResetException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ], 410);
            }

            return $this->expiredView($token);
        }

        app(ActivityLogger::class)->record(
            ActivityLog::TIPE_PASSWORD,
            'Mengatur ulang password melalui tautan reset.',
            $user
        );

        // Sesi lama (bila ada) tidak boleh tetap aktif setelah password diganti.
        try {
            Auth::guard('web')->logout();
        } catch (\Throwable $e) {
        }
        try {
            Auth::logout();
        } catch (\Throwable $e) {
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = 'Password berhasil diperbarui. Silakan masuk dengan password baru.';

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => [
                    'redirect' => route('login'),
                ],
            ]);
        }

        return redirect()->route('login')->with('status', $message);
    }

    /**
     * Halaman token invalid / kedaluwarsa. Tautan tetap diberikan supaya user
     * bisa langsung mengajukan pengajuan baru.
     */
    protected function expiredView(string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'tokenRecord' => null,
            'email' => null,
        ]);
    }
}
