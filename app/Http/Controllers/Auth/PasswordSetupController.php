<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PasswordSetupController extends Controller
{
    public function showSetupForm()
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Admin tidak melalui ubah password; Guru/Siswa hanya pada kunjungan
        // pertama (must_change_password). Sudah pernah ubah -> langsung dashboard.
        if ($user->role === 'admin' || ! $user->must_change_password) {
            return redirect()->to($this->dashboardByRole($user->role));
        }

        return view('auth.buat-password');
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if (! $user) {
            abort(401);
        }

        if ($user->role === 'admin' || ! $user->must_change_password) {
            return $this->notNeeded($request);
        }

        $validator = Validator::make($request->all(), [
            'password' => PasswordPolicy::rules(),
            'password_confirmation' => 'required|string',
        ], PasswordPolicy::messages());

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        $user->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        app(ActivityLogger::class)->record(ActivityLog::TIPE_PASSWORD, 'Mengubah password pertama kali.', $user);

        $redirect = $this->dashboardByRole($user->role);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Password berhasil diperbarui, silakan lanjutkan.',
                'data' => [
                    'redirect' => $redirect,
                ],
            ]);
        }

        return redirect()->route('password.setup')->with('success', 'Password berhasil diperbarui.');
    }

    protected function dashboardByRole(?string $role): string
    {
        return match ($role) {
            'admin' => route('dashboard.admin.index'),
            'guru' => route('dashboard.guru.index'),
            default => route('dashboard.siswa.index'),
        };
    }

    protected function notNeeded(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Password tidak perlu diubah.',
            ], 403);
        }

        return redirect()->to($this->dashboardByRole(auth()->user()?->role));
    }
}
