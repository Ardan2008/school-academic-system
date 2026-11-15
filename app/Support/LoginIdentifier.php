<?php

namespace App\Support;

use App\Models\User;
use InvalidArgumentException;

/**
 * Sumber tunggal pemetaan role ke kolom identifier.
 *
 * Admin login memakai Email, Guru memakai NIP, Siswa memakai NIS.
 * Seluruh pemakaian (validasi request, query user, lockout key, dan payload
 * untuk frontend) membaca definisi dari sini supaya tidak ada lagi
 * percabangan manual role -> kolom yang tersebar di banyak file.
 */
class LoginIdentifier
{
    /**
     * Definisi identifier per role.
     *
     * - field      : kolom(users) yang dipakai untuk mencari akun
     * - rules      : aturan validasi identifier
     * - inputMode  : petunjuk keyboard di mobile
     * - icon       : nama ikon lucide untuk dropdown
     */
    private const MAP = [
        'admin' => [
            'field' => 'email',
            'label' => 'Email',
            'placeholder' => 'Masukkan email anda',
            'autocomplete' => 'email',
            'input_mode' => 'email',
            'maxlength' => 120,
            'icon' => 'mail',
            'rules' => ['string', 'email', 'max:120'],
            'messages' => [
                'email' => 'Format email tidak valid.',
            ],
        ],
        'guru' => [
            'field' => 'nip',
            'label' => 'NIP',
            'placeholder' => 'Masukkan NIP anda',
            'autocomplete' => 'off',
            'input_mode' => 'numeric',
            'maxlength' => 30,
            'icon' => 'id-card',
            'rules' => ['string', 'regex:/^[A-Za-z0-9\-\/]+$/', 'max:30'],
            'messages' => [
                'regex' => 'NIP hanya boleh berisi huruf, angka, tanda hubung, atau garis miring.',
            ],
        ],
        'siswa' => [
            'field' => 'nis',
            'label' => 'NIS',
            'placeholder' => 'Masukkan NIS anda',
            'autocomplete' => 'off',
            'input_mode' => 'numeric',
            'maxlength' => 30,
            'icon' => 'id-card',
            'rules' => ['string', 'regex:/^[A-Za-z0-9\-\/]+$/', 'max:30'],
            'messages' => [
                'regex' => 'NIS hanya boleh berisi huruf, angka, tanda hubung, atau garis miring.',
            ],
        ],
    ];

    /**
     * Role yang boleh mengajukan reset password lewat halaman publik.
     */
    public const RESETTABLE_ROLES = ['guru', 'siswa'];

    public static function roles(): array
    {
        return array_keys(self::MAP);
    }

    public static function supports(?string $role): bool
    {
        return is_string($role) && isset(self::MAP[$role]);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function config(string $role): array
    {
        if (! isset(self::MAP[$role])) {
            throw new InvalidArgumentException("Role [{$role}] tidak memiliki identifier login.");
        }

        return self::MAP[$role];
    }

    public static function field(string $role): string
    {
        return self::config($role)['field'];
    }

    public static function label(string $role): string
    {
        return self::config($role)['label'];
    }

    public static function isEmail(string $role): bool
    {
        return self::field($role) === 'email';
    }

    public static function canRequestReset(?string $role): bool
    {
        return in_array($role, self::RESETTABLE_ROLES, true);
    }

    /**
     * Buang spasi di awal/akhir. Email juga di-lowercase agar
     * " Admin@X.Id " tetap cocok dengan data tersimpan.
     */
    public static function normalize(string $role, string $value): string
    {
        $value = trim($value);

        return self::isEmail($role) ? mb_strtolower($value) : $value;
    }

    /**
     * Aturan validasi lengkap untuk request login / pengajuan reset.
     *
     * @return array<int, string>
     */
    public static function rules(string $role): array
    {
        return array_merge(['required', 'string'], self::config($role)['rules']);
    }

    /**
     * Pesan error kustom untuk identifier.
     *
     * @return array<string, string>
     */
    public static function messages(string $role): array
    {
        $field = self::field($role);
        $messages = [
            $field.'.required' => self::label($role).' wajib diisi.',
            $field.'.string' => self::label($role).' harus berupa teks.',
            $field.'.max' => self::label($role).' terlalu panjang.',
        ];

        return array_merge($messages, self::config($role)['messages']);
    }

    /**
     * Key lockout per role + identifier.
     *
     * Dipisah per role supaya NIP "1001" milik guru dan NIS "1001" milik
     * siswa tidak saling mengunci. Nilai disimpan pada kolom string
     * login_attempts.email yang sudah ada.
     */
    public static function lockKey(string $role, string $value): string
    {
        $normalized = self::normalize($role, $value);

        return $role.':'.$normalized;
    }

    /**
     * Cari user berdasarkan role + identifier. Role selalu ikut dalam query
     * agar identifier yang sama di role berbeda tidak saling tertukar.
     */
    public static function findUser(string $role, string $value): ?User
    {
        $normalized = self::normalize($role, $value);

        if ($normalized === '') {
            return null;
        }

        return User::query()
            ->where('role', $role)
            ->where(self::field($role), $normalized)
            ->first();
    }

    /**
     * Ringkasan untuk dropdown role di frontend.
     */
    public static function frontendPayload(): array
    {
        $payload = [];

        foreach (self::MAP as $role => $config) {
            $payload[$role] = [
                'label' => ucfirst($role),
                'field' => $config['field'],
                'input_label' => $config['label'],
                'placeholder' => $config['placeholder'],
                'autocomplete' => $config['autocomplete'],
                'input_mode' => $config['input_mode'],
                'maxlength' => $config['maxlength'],
                'icon' => $config['icon'],
            ];
        }

        return $payload;
    }
}
