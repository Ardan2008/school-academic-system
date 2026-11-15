<?php

namespace App\Support;

/**
 * Aturan password yang dipakai bersama oleh first-login (PasswordSetupController)
 * dan atur ulang password via token, agar keduanya tidak berbeda standar.
 */
class PasswordPolicy
{
    public const MIN_LENGTH = 8;

    /**
     * @return array<int, string>
     */
    public static function rules(): array
    {
        return [
            'required',
            'string',
            'min:'.self::MIN_LENGTH,
            'confirmed',
            'regex:/[A-Z]/',
            'regex:/[0-9]/',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password minimal terdiri dari '.self::MIN_LENGTH.' karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.regex' => 'Password harus mengandung huruf besar dan angka.',
            'password_confirmation.required' => 'Konfirmasi password wajib diisi.',
        ];
    }
}
