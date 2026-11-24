<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class FileMateri
{
    public const MAX_UKURAN_KB = 20480;

    public const MIME = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'rar' => ['application/vnd.rar', 'application/x-rar-compressed', 'application/octet-stream'],
    ];

    public static function ekstensiDiizinkan(): array
    {
        return array_keys(self::MIME);
    }

    public static function mimeDiizinkan(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::MIME))));
    }

    public static function aturan(bool $wajib): array
    {
        return [
            $wajib ? 'required' : 'nullable',
            'file',
            'max:'.self::MAX_UKURAN_KB,
            'mimes:'.implode(',', self::ekstensiDiizinkan()),
            'mimetypes:'.implode(',', self::mimeDiizinkan()),
        ];
    }

    public static function pesanValidasi(): array
    {
        return [
            'file.required' => 'File materi wajib diunggah.',
            'file.file' => 'File materi tidak valid.',
            'file.max' => 'Ukuran file maksimal 20 MB.',
            'file.mimes' => 'Format file tidak didukung.',
            'file.mimetypes' => 'Format file tidak didukung.',
        ];
    }

    public static function ekstensi(UploadedFile $file): string
    {
        return strtolower((string) $file->getClientOriginalExtension());
    }

    public static function mime(UploadedFile $file): string
    {
        return strtolower((string) $file->getMimeType());
    }

    public static function valid(UploadedFile $file): bool
    {
        $ekstensi = self::ekstensi($file);
        $mime = self::mime($file);

        return isset(self::MIME[$ekstensi]) && in_array($mime, self::MIME[$ekstensi], true);
    }

    public static function namaAman(string $nama): string
    {
        $ekstensi = strtolower((string) pathinfo($nama, PATHINFO_EXTENSION));
        $dasar = (string) pathinfo($nama, PATHINFO_FILENAME);
        $dasar = (string) preg_replace('/[^A-Za-z0-9._ -]+/u', '_', $dasar);
        $dasar = trim((string) preg_replace('/[\s_]+/', ' ', $dasar));

        if ($dasar === '') {
            $dasar = 'materi';
        }

        $maksimal = max(1, 200 - strlen($ekstensi) - 1);

        return mb_substr($dasar, 0, $maksimal).'.'.$ekstensi;
    }

    public static function ukuran(?int $byte): string
    {
        if ($byte === null) {
            return '—';
        }

        if ($byte < 1024) {
            return $byte.' B';
        }

        $satuan = ['KB', 'MB', 'GB', 'TB'];
        $nilai = $byte / 1024;
        $indeks = 0;

        while ($nilai >= 1024 && $indeks < count($satuan) - 1) {
            $nilai /= 1024;
            $indeks++;
        }

        return number_format($nilai, $nilai >= 100 ? 0 : 1, ',', '.').' '.$satuan[$indeks];
    }

    public static function format(?string $ekstensi): string
    {
        return $ekstensi === null || $ekstensi === '' ? '—' : strtoupper($ekstensi);
    }

    public static function bisaDipratinjau(?string $mime): bool
    {
        return in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true);
    }

    public static function namaAcak(string $ekstensi): string
    {
        return date('Ymd').'_'.Str::random(24).'.'.$ekstensi;
    }
}
