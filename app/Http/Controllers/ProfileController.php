<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidationValidator;

class ProfileController extends Controller
{
    public function changePassword(Request $request)
    {
        $user = Auth::user();

        // Role disematkan pada URL agar request selalu menyasar akun yang benar
        // walau sesi browser yang sama dipakai login beberapa role sekaligus.
        // Tanpa ini, guard default bisa menunjuk role terakhir login dan
        // mengganti password akun yang salah.
        $role = (string) $request->route('role');

        if (! $user instanceof User || $user->role !== $role) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
            ],
            'password_confirmation' => 'required|string',
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password minimal terdiri dari 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.regex' => 'Password harus mengandung huruf besar dan angka.',
            'password_confirmation.required' => 'Konfirmasi password wajib diisi.',
        ]);

        if ($validator->fails()) {
            return $this->validationFailed($validator, $request);
        }

        if (! Hash::check($request->current_password, $user->password)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Password saat ini tidak sesuai.',
                ], 422);
            }

            throw ValidationException::withMessages([
                'current_password' => 'Password saat ini tidak sesuai.',
            ]);
        }

        $validated = $validator->validated();

        $user->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        app(ActivityLogger::class)->record(ActivityLog::TIPE_PASSWORD, 'Mengubah password akun.', $user);

        $redirect = route('dashboard.'.$user->role.'.profile.index');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Password berhasil diperbarui.',
                'data' => [
                    'redirect' => $redirect,
                ],
            ]);
        }

        return redirect()->to($redirect)->with('success', 'Password berhasil diperbarui.');
    }

    protected function validationFailed(ValidationValidator $validator, Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        throw new ValidationException($validator);
    }
}
