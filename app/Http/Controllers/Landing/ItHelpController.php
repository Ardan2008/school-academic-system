<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Mail\ItHelpRequestMail;
use App\Models\ActivityLog;
use App\Models\ItHelpRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\AdminNotificationService;
use App\Support\LoginIdentifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Permintaan bantuan dari tombol "Hubungi Tim IT Sekolah" di landing page.
 *
 * Alurnya meniru pop-up lupa password: pilih role Guru/Siswa, isi NIP/NIS
 * sesuai role, kirim AJAX dengan CSRF, dan jawab dengan pesan generik agar
 * tidak bisa dipakai menebak identitas yang terdaftar.
 */
class ItHelpController extends Controller
{
    /**
     * Pesan yang sama untuk semua kondisi, termasuk NIP/NIS tidak ditemukan,
     * email tidak valid, atau pengiriman notifikasi gagal.
     */
    public const GENERIC_MESSAGE = 'Permintaan bantuan sudah kami terima. Tim IT Sekolah akan menghubungi kamu melalui email tersebut pada hari dan jam kerja.';

    public function __construct(
        private readonly AdminNotificationService $adminNotifications,
    ) {}

    public function store(Request $request)
    {
        // Honeypot: field disembunyikan dari pengguna asli. Bot yang
        // mengisi form otomatis akan mengisinya juga, lalu diabaikan diam-diam.
        if (filled($request->input('website'))) {
            return $this->respond($request, self::GENERIC_MESSAGE);
        }

        $role = (string) $request->input('role', '');

        // Role menentukan field identitas, jadi harus valid sebelum lanjut.
        if (! in_array($role, LoginIdentifier::RESETTABLE_ROLES, true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Role tidak valid. Pilih Guru atau Siswa.',
                    'errors' => ['role' => ['Role tidak valid. Pilih Guru atau Siswa.']],
                ], 422);
            }

            return back()->withErrors(['role' => 'Role tidak valid. Pilih Guru atau Siswa.']);
        }

        $field = LoginIdentifier::field($role);

        $validator = validator(
            $request->all(),
            [
                'role' => ['required', Rule::in(LoginIdentifier::RESETTABLE_ROLES)],
                $field => LoginIdentifier::rules($role),
                'email' => ['required', 'string', 'email', 'max:120'],
                'pesan' => ['required', 'string', 'min:10', 'max:1000'],
            ],
            array_merge(LoginIdentifier::messages($role), [
                'role.in' => 'Role tidak valid.',
                'role.required' => 'Pilih role terlebih dahulu.',
                'email.required' => 'Email wajib diisi.',
                'email.email' => 'Format email tidak valid.',
                'email.max' => 'Email terlalu panjang.',
                'pesan.required' => 'Tuliskan kendala yang kamu alami.',
                'pesan.min' => 'Kendala minimal 10 karakter.',
                'pesan.max' => 'Kendala maksimal 1000 karakter.',
            ])
        );

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()->toArray(),
                ], 422);
            }

            return back()->withErrors($validator)->withInput();
        }

        $email = mb_strtolower(trim((string) $validator->validated()['email']));
        $identifier = (string) $validator->validated()[$field];
        $pengguna = LoginIdentifier::findUser($role, $identifier)
            ?? User::query()->where('email', $email)->first();

        $helpRequest = ItHelpRequest::create([
            // Dicocokkan lewat role + identifier dulu (itu yang dipakai user),
            // lalu fallback ke email kalau NIP/NIS tidak terdaftar.
            'user_id' => $pengguna?->id,
            'role' => $role,
            'identifier' => LoginIdentifier::normalize($role, $identifier),
            'email' => $email,
            'pesan' => $validator->validated()['pesan'],
            'status' => ItHelpRequest::STATUS_BARU,
            'ip_address' => $request->ip(),
        ]);

        if ($pengguna instanceof User) {
            app(ActivityLogger::class)->record(
                ActivityLog::TIPE_KENDALA_AKUN,
                'Mengajukan bantuan kendala akun.',
                $pengguna
            );
        }

        // Selain email ke Tim IT, pengajuan juga muncul di Notifikasi
        // Sistem pada dashboard admin agar tetap terlihat meski email
        // gagal terkirim.
        $this->adminNotifications->itHelpDiajukan($helpRequest);

        $this->notifyItTeam($helpRequest);

        return $this->respond($request, self::GENERIC_MESSAGE);
    }

    /**
     * Kegagalan email tidak menggagalkan permintaan: datanya sudah tersimpan
     * di database, jadi cukup dicatat di log.
     */
    protected function notifyItTeam(ItHelpRequest $helpRequest): void
    {
        $recipient = config('services.it_support.email');

        if (blank($recipient)) {
            return;
        }

        try {
            Mail::to($recipient)->send(new ItHelpRequestMail($helpRequest));
        } catch (\Throwable $e) {
            Log::warning('Notifikasi permintaan bantuan Tim IT gagal dikirim.', [
                'it_help_request_id' => $helpRequest->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function respond(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
            ]);
        }

        return back()->with('status', $message);
    }
}
