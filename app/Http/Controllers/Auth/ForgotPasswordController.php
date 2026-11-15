<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use App\Services\PasswordResetService;
use App\Support\LoginIdentifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Pengajuan atur ulang password dari halaman login publik.
 *
 * Hanya Guru (NIP) dan Siswa (NIS) yang boleh mengajukan. Setiap jawaban
 * selalu generik supaya tidak bisa dipakai menebak NIP/NIS yang terdaftar.
 */
class ForgotPasswordController extends Controller
{
    /**
     * Pesan yang sama untuk semua kondisi, termasuk NIP/NIS tidak ditemukan,
     * akun nonaktif, atau sudah ada pengajuan pending.
     */
    public const GENERIC_MESSAGE = 'Jika data tersebut terdaftar, pengajuan sudah kami teruskan ke admin sekolah. Mohon menunggu persetujuan.';

    public function store(Request $request, PasswordResetService $passwordReset)
    {
        $role = (string) $request->input('role', '');

        if (! in_array($role, LoginIdentifier::RESETTABLE_ROLES, true)) {
            return $this->respondInvalidRole($request, $role);
        }

        $field = LoginIdentifier::field($role);

        $validator = Validator::make(
            $request->all(),
            [
                'role' => ['required', Rule::in(LoginIdentifier::RESETTABLE_ROLES)],
                $field => LoginIdentifier::rules($role),
            ],
            array_merge(LoginIdentifier::messages($role), [
                'role.in' => 'Role tidak valid.',
            ])
        );

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first(),
                    'errors' => [$field => $validator->errors()->get($field)],
                ], 422);
            }

            return back()
                ->withErrors([$field => $validator->errors()->first()])
                ->withInput();
        }

        $pengajuan = $passwordReset->submit(
            $role,
            (string) $validator->validated()[$field],
            $request->ip()
        );

        if ($pengajuan?->user) {
            app(ActivityLogger::class)->record(
                ActivityLog::TIPE_PASSWORD,
                'Mengajukan atur ulang password.',
                $pengajuan->user
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => self::GENERIC_MESSAGE,
            ]);
        }

        return back()->with('status', self::GENERIC_MESSAGE);
    }

    protected function respondInvalidRole(Request $request, string $role)
    {
        $message = $role === 'admin'
            ? 'Akun admin tidak menggunakan alur pengajuan reset password. Silakan hubungi admin sekolah.'
            : 'Role tidak valid.';

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'message' => $message,
            ], 422);
        }

        return back()->with('status', $message);
    }
}
