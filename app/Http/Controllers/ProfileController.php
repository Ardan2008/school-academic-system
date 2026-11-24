<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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

    public function updatePhoto(Request $request)
    {
        $user = $this->resolveRoleUser($request);

        $validator = Validator::make($request->all(), [
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'photo.required' => 'Foto profil wajib dipilih.',
            'photo.file' => 'File foto tidak valid.',
            'photo.mimes' => 'Format foto harus JPG, JPEG, PNG, atau WEBP.',
            'photo.max' => 'Ukuran foto maksimal 2 MB.',
        ]);

        if ($validator->fails()) {
            return $this->validationFailed($validator, $request);
        }

        $file = $request->file('photo');
        $mime = (string) $file?->getMimeType();
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (! in_array($mime, $allowedMimes, true)) {
            $msg = 'Format foto tidak didukung. Gunakan JPG, JPEG, PNG, atau WEBP.';
            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['photo' => [$msg]]], 422);
            }
            throw ValidationException::withMessages(['photo' => $msg]);
        }

        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $dir = 'profile-photos/'.$user->role;
        $name = $user->id.'_'.now()->format('YmdHis').'_'.Str::lower(Str::random(12)).'.'.$ext;
        $path = $file->storeAs($dir, $name, 'public');
        if ($path === false) {
            $msg = 'Foto tidak dapat disimpan. Coba lagi.';
            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg], 500);
            }
            abort(500, $msg);
        }

        $old = trim((string) ($user->profile_photo_path ?? ''));
        try {
            $user->forceFill(['profile_photo_path' => $path])->save();
        } catch (\Throwable $e) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }

        $this->deleteStoredPhoto($old, $path);
        $this->recordProfileActivity('Memperbarui foto profil.', $user);

        $payload = [
            'status' => 'success',
            'message' => 'Foto profil berhasil diperbarui.',
            'data' => [
                'photo_url' => $user->fresh()->profilePhotoUrl(),
            ],
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return redirect()->route('dashboard.'.$user->role.'.profile.index')->with('success', $payload['message']);
    }

    public function deletePhoto(Request $request)
    {
        $user = $this->resolveRoleUser($request);
        $old = trim((string) ($user->profile_photo_path ?? ''));

        $user->forceFill(['profile_photo_path' => null])->save();
        $this->deleteStoredPhoto($old);
        $this->recordProfileActivity('Menghapus foto profil.', $user);

        $payload = [
            'status' => 'success',
            'message' => 'Foto profil berhasil dihapus.',
            'data' => [
                'photo_url' => null,
            ],
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return redirect()->route('dashboard.'.$user->role.'.profile.index')->with('success', $payload['message']);
    }

    protected function deleteStoredPhoto(?string $path, ?string $currentPath = null): void
    {
        $path = trim((string) $path);
        if ($path === '' || ($currentPath !== null && $path === $currentPath)) {
            return;
        }

        $publicPrefix = rtrim((string) config('filesystems.disks.public.url', '/storage'), '/').'/';
        $relative = $path;
        if (str_starts_with($relative, $publicPrefix)) {
            $relative = substr($relative, strlen($publicPrefix));
        } elseif (str_starts_with($relative, '/storage/')) {
            $relative = substr($relative, strlen('/storage/'));
        }
        $relative = ltrim($relative, '/');

        if ($relative === '' || str_starts_with($relative, 'http://') || str_starts_with($relative, 'https://')) {
            return;
        }

        try {
            if (Storage::disk('public')->exists($relative)) {
                Storage::disk('public')->delete($relative);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function recordProfileActivity(string $message, User $user): void
    {
        try {
            app(ActivityLogger::class)->record(ActivityLog::TIPE_PROFIL, $message, $user);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function resolveRoleUser(Request $request): User
    {
        $user = Auth::user();
        $role = (string) $request->route('role');

        if (! $user instanceof User || $user->role !== $role) {
            abort(403);
        }

        return $user;
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
