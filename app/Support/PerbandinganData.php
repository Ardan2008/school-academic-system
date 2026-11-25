<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class PerbandinganData
{
    public static function sama($a, $b): bool
    {
        if ($a instanceof UploadedFile || $b instanceof UploadedFile) {
            return $a === $b;
        }

        if ($a instanceof \DateTimeInterface || $b instanceof \DateTimeInterface) {
            try {
                return Carbon::parse($a)->toDateTimeString() === Carbon::parse($b)->toDateTimeString();
            } catch (\Throwable) {
                return false;
            }
        }

        if (is_array($a) || is_array($b)) {
            if (! is_array($a) || ! is_array($b) || count($a) !== count($b)) {
                return false;
            }

            $na = array_map([self::class, 'kunci'], $a);
            $nb = array_map([self::class, 'kunci'], $b);
            sort($na);
            sort($nb);

            return $na === $nb;
        }

        return self::kunci($a) === self::kunci($b);
    }

    public static function identik($model, array $data, array $peta = [], array $abaikan = []): bool
    {
        foreach ($data as $kunci => $nilai) {
            if (in_array($kunci, $abaikan, true)) {
                continue;
            }

            if ($nilai instanceof UploadedFile && ! array_key_exists($kunci, $peta)) {
                continue;
            }

            $penentu = $peta[$kunci] ?? null;

            if (is_callable($penentu)) {
                $sekarang = $penentu($nilai, $model);
            } else {
                $atribut = is_string($penentu) ? $penentu : $kunci;
                $sekarang = $model->getAttribute($atribut);
            }

            if (! self::sama($nilai, $sekarang)) {
                return false;
            }
        }

        return true;
    }

    public static function hashFileUnggahan(UploadedFile $file): string
    {
        return hash_file('sha256', $file->getRealPath() ?: $file->getPathname());
    }

    public static function hashFileTersimpan(?string $path, string $disk = 'public'): ?string
    {
        if (! $path || ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        return hash('sha256', (string) Storage::disk($disk)->get($path));
    }

    private static function kunci($nilai): string
    {
        if ($nilai === null) {
            return '';
        }

        if (is_bool($nilai)) {
            return $nilai ? '1' : '';
        }

        if (is_float($nilai)) {
            return rtrim(rtrim(sprintf('%.6F', $nilai), '0'), '.');
        }

        if (is_int($nilai)) {
            return (string) $nilai;
        }

        if (is_string($nilai)) {
            return trim($nilai);
        }

        if (is_object($nilai) && method_exists($nilai, '__toString')) {
            return trim((string) $nilai);
        }

        return trim((string) json_encode($nilai));
    }
}
