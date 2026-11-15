<?php

namespace App\Services;

use App\Models\ItHelpRequest;
use App\Models\Notification;
use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Support\LoginIdentifier;
use Illuminate\Support\Str;

/**
 * Notifikasi ke seluruh Admin aktif ketika ada pengajuan baru dari
 * halaman publik: pop-up "Atur Ulang Password" di halaman login dan
 * pop-up "Hubungi Tim IT Sekolah" di landing page.
 *
 * Setiap admin mendapat baris notifikasi masing-masing (bukan satu
 * baris bersama) supaya status "sudah dibaca" satu admin tidak menutup
 * notifikasi milik admin lain.
 */
class AdminNotificationService
{
    /**
     * Notifikasi saat Guru/Siswa mengajukan bantuan password dari
     * halaman login.
     *
     * Form pengajuan hanya meminta role dan NIP/NIS, jadi pesan
     * notifikasi menyesuaikan data yang tersedia pada pengajuan.
     */
    public function passwordResetDiajukan(PasswordResetRequest $pengajuan): void
    {
        $role = (string) $pengajuan->role;

        $this->notifyAdmins(
            type: 'pengajuan_password',
            title: 'Pengajuan Bantuan Password',
            message: sprintf(
                '%s · %s · %s %s',
                $pengajuan->user?->name ?? 'Akun tidak dikenal',
                ucfirst($role),
                LoginIdentifier::label($role),
                $pengajuan->identifier,
            ),
            icon: 'inbox',
            url: route('dashboard.admin.pengajuan-reset-password.index'),
        );
    }

    /**
     * Notifikasi saat pengunjung mengirim form "Hubungi Tim IT Sekolah"
     * dari landing page.
     */
    public function itHelpDiajukan(ItHelpRequest $permintaan): void
    {
        $role = (string) $permintaan->role;

        $this->notifyAdmins(
            type: 'kendala_akun',
            title: 'Kendala Akun · Hubungi Tim IT',
            message: sprintf(
                '%s · %s · %s %s · %s — %s',
                $permintaan->user?->name ?? 'Nama tidak tersedia',
                ucfirst($role),
                LoginIdentifier::label($role),
                $permintaan->identifier,
                $permintaan->email,
                Str::limit($permintaan->pesan, 120),
            ),
            icon: 'megaphone',
            url: route('dashboard.admin.kendala-akun.show', $permintaan),
        );
    }

    /**
     * Buat satu baris notifikasi untuk tiap admin aktif.
     *
     * Admin nonaktif dilewati agar tidak menumpuk notifikasi yang tidak
     * akan pernah dibaca.
     */
    protected function notifyAdmins(string $type, string $title, string $message, string $icon, string $url): void
    {
        $adminIds = User::query()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->pluck('id');

        foreach ($adminIds as $adminId) {
            Notification::create([
                'user_id' => $adminId,
                'role' => 'admin',
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'icon' => $icon,
                'url' => $url,
            ]);
        }
    }
}
