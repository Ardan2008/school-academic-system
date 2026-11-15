<?php

namespace App\Services;

use App\Exceptions\PasswordResetException;
use App\Mail\ResetPasswordMail;
use App\Models\PasswordResetRequest;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Support\LoginIdentifier;
use App\Support\PasswordPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Seluruh alur atur ulang password Guru/Siswa.
 *
 * Guru/Siswa hanya mengajukan (status pending) dari halaman login publik.
 * Admin yang memutuskan: menyetujui menerbitkan token 30 menit dan
 * mengirim email, menolak wajib menyertakan alasan. Token hanya sekali
 * pakai dan hanya hash-nya yang tersimpan.
 */
class PasswordResetService
{
    public function __construct(
        private readonly AdminNotificationService $adminNotifications,
    ) {}

    /**
     * Masa berlaku token (menit).
     */
    public const TOKEN_TTL_MINUTES = 30;

    /**
     * Buat pengajuan baru berstatus pending.
     *
     * Mengembalikan null bila role tidak boleh mengajukan, akun tidak ada,
     * akun nonaktif, akun tanpa email, atau sudah ada pengajuan pending.
     * Endpoint publik tetap menjawab pesan generik untuk semua kasus tersebut.
     */
    public function submit(string $role, string $identifier, ?string $ip = null): ?PasswordResetRequest
    {
        if (! LoginIdentifier::canRequestReset($role)) {
            return null;
        }

        $user = LoginIdentifier::findUser($role, $identifier);

        if (! $user || ! $user->is_active) {
            return null;
        }

        // Token dikirim lewat email, jadi akun tanpa email tidak bisa
        // menyelesaikan alur ini.
        if (blank($user->email)) {
            return null;
        }

        $normalized = LoginIdentifier::normalize($role, $identifier);

        $alreadyPending = PasswordResetRequest::query()
            ->where('user_id', $user->id)
            ->pending()
            ->exists();

        if ($alreadyPending) {
            return null;
        }

        $pengajuan = PasswordResetRequest::create([
            'user_id' => $user->id,
            'role' => $role,
            'identifier' => $normalized,
            'status' => PasswordResetRequest::STATUS_PENDING,
            'ip_address' => $ip,
        ]);

        // Pengajuan baru langsung diteruskan ke Notifikasi Sistem tiap
        // admin aktif supaya bisa ditindaklanjuti tanpa harus dicek manual.
        $this->adminNotifications->passwordResetDiajukan($pengajuan);

        return $pengajuan;
    }

    /**
     * Setujui pengajuan: terbitkan token dan kirim email.
     *
     * Password tidak diubah di sini; pengguna yang menetapkannya sendiri
     * lewat tautan bertoken.
     *
     * @throws PasswordResetException
     */
    public function approve(PasswordResetRequest $passwordResetRequest, User $admin): PasswordResetRequest
    {
        $plainToken = DB::transaction(function () use ($passwordResetRequest, $admin) {
            $request = PasswordResetRequest::query()
                ->whereKey($passwordResetRequest->getKey())
                ->lockForUpdate()
                ->first();

            if (! $request || ! $request->isPending()) {
                throw PasswordResetException::alreadyProcessed();
            }

            $user = $request->user;

            if (! $user || ! $user->is_active) {
                throw PasswordResetException::accountUnavailable();
            }

            if (blank($user->email)) {
                throw PasswordResetException::emailMissing();
            }

            // Token lama milik user ini dicabut agar hanya berlaku satu tautan terbaru.
            PasswordResetToken::query()
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->delete();

            $plain = Str::random(64);

            PasswordResetToken::create([
                'user_id' => $user->id,
                'token_hash' => PasswordResetToken::hash($plain),
                'expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
            ]);

            $request->update([
                'status' => PasswordResetRequest::STATUS_APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
                'email_sent_at' => null,
            ]);

            return $plain;
        });

        try {
            $this->sendTokenMail($passwordResetRequest->user, (string) $passwordResetRequest->identifier, $plainToken);
        } catch (\Throwable $e) {
            $this->revokeApproval($passwordResetRequest);

            throw PasswordResetException::mailFailed();
        }

        $passwordResetRequest->forceFill(['email_sent_at' => now()])->save();

        return $passwordResetRequest->fresh();
    }

    /**
     * Tolak pengajuan. Alasan wajib diisi dan hanya disimpan di sistem.
     *
     * @throws PasswordResetException
     */
    public function reject(PasswordResetRequest $passwordResetRequest, User $admin, string $reason): PasswordResetRequest
    {
        return DB::transaction(function () use ($passwordResetRequest, $admin, $reason) {
            $request = PasswordResetRequest::query()
                ->whereKey($passwordResetRequest->getKey())
                ->lockForUpdate()
                ->first();

            if (! $request || ! $request->isPending()) {
                throw PasswordResetException::alreadyProcessed();
            }

            $request->update([
                'status' => PasswordResetRequest::STATUS_REJECTED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
                'email_sent_at' => null,
            ]);

            return $request->fresh();
        });
    }

    /**
     * Cari token dari nilai mentah yang diklik pengguna.
     */
    public function findToken(string $plainToken): ?PasswordResetToken
    {
        $plainToken = trim($plainToken);

        if ($plainToken === '') {
            return null;
        }

        return PasswordResetToken::query()
            ->where('token_hash', PasswordResetToken::hash($plainToken))
            ->first();
    }

    /**
     * Pakai token untuk menetapkan password baru.
     *
     * Token langsung ditandai terpakai, seluruh token lain milik user dicabut,
     * dan catatan login dicatat ulang supaya lockout lama tidak terbawa.
     *
     * @throws PasswordResetException
     */
    public function consume(PasswordResetToken $token, string $newPassword, ?string $ip = null): User
    {
        return DB::transaction(function () use ($token, $newPassword, $ip) {
            $locked = PasswordResetToken::query()
                ->whereKey($token->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked || ! $locked->isUsable()) {
                throw PasswordResetException::tokenInvalid();
            }

            $user = $locked->user;

            if (! $user || ! $user->is_active) {
                throw PasswordResetException::accountUnavailable();
            }

            $user->forceFill([
                'password' => Hash::make($newPassword),
                'initial_password' => null,
                'must_change_password' => false,
            ])->save();

            $locked->forceFill([
                'used_at' => now(),
                'ip_address' => $ip,
            ])->save();

            PasswordResetToken::query()
                ->where('user_id', $user->id)
                ->whereKeyNot($locked->getKey())
                ->whereNull('used_at')
                ->delete();

            return $user;
        });
    }

    public function pendingCount(): int
    {
        return PasswordResetRequest::query()->pending()->count();
    }

    /**
     * Pastikan password baru memenuhi standar yang sama dengan first-login.
     *
     * @return array<int, string>
     */
    public function passwordRules(): array
    {
        return PasswordPolicy::rules();
    }

    /**
     * Kembalikan pengajuan ke status pending dan cabut token yang baru terbit
     * ketika pengiriman email gagal, supaya admin bisa mencoba lagi.
     */
    protected function revokeApproval(PasswordResetRequest $passwordResetRequest): void
    {
        DB::transaction(function () use ($passwordResetRequest) {
            PasswordResetToken::query()
                ->where('user_id', $passwordResetRequest->user_id)
                ->whereNull('used_at')
                ->delete();

            PasswordResetRequest::query()
                ->whereKey($passwordResetRequest->getKey())
                ->update([
                    'status' => PasswordResetRequest::STATUS_PENDING,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'email_sent_at' => null,
                ]);
        });
    }

    protected function sendTokenMail(User $user, string $identifier, string $plainToken): void
    {
        $resetUrl = route('password.reset', ['token' => $plainToken]);
        $expiresAt = now()->addMinutes(self::TOKEN_TTL_MINUTES);
        $role = (string) $user->role;

        Mail::to($user->email)->send(new ResetPasswordMail(
            userName: $user->name,
            roleLabel: ucfirst($role),
            identifierLabel: LoginIdentifier::label($role),
            identifier: $identifier,
            resetUrl: $resetUrl,
            expiresAtLabel: $expiresAt->translatedFormat('d M Y H:i'),
        ));
    }
}
