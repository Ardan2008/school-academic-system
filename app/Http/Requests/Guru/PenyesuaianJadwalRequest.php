<?php

namespace App\Http\Requests\Guru;

use Illuminate\Foundation\Http\FormRequest;

class PenyesuaianJadwalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan dipakai controller untuk aksi cek bentrok, penyesuaian massal,
     * dan pembatalan. Route tidak diubah: ketiganya masuk lewat endpoint
     * jadwal yang sudah ada dengan parameter `intent`.
     *
     * @return array<string, mixed>
     */
    public static function aturan(): array
    {
        return [
            'intent' => ['required', 'string', 'in:cek-bentrok,sesuaikan-massal,batalkan-penyesuaian'],
            'perubahan' => ['required_if:intent,sesuaikan-massal', 'array', 'min:1', 'max:100'],
            'perubahan.*.id' => ['required_with:perubahan', 'integer', 'min:1'],
            'perubahan.*.sesi_id' => ['required_with:perubahan', 'integer', 'min:1'],
            'batch' => ['required_if:intent,batalkan-penyesuaian', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function pesan(): array
    {
        return [
            'perubahan.required_if' => 'Pilih minimal satu perubahan.',
            'perubahan.min' => 'Pilih minimal satu perubahan.',
            'perubahan.*.id.required_with' => 'Jadwal yang disesuaikan tidak valid.',
            'perubahan.*.sesi_id.required_with' => 'Sesi tujuan tidak valid.',
            'batch.required_if' => 'Riwayat perubahan tidak valid.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::aturan();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::pesan();
    }
}
