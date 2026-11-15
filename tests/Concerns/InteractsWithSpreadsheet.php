<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait InteractsWithSpreadsheet
{
    /**
     * Isi respon export. Respon stream (response()->streamDownload) tidak
     * punya getContent(), jadi isi streamnya ditangkap lewat streamedContent().
     */
    protected function kontenRespons(TestResponse $respons): string
    {
        $dasar = $respons->baseResponse;

        if ($dasar instanceof StreamedResponse
            || $dasar instanceof BinaryFileResponse) {
            return (string) $respons->streamedContent();
        }

        return (string) $respons->getContent();
    }

    /**
     * Baca file .xlsx hasil export menjadi array baris (nilai sel mentah).
     *
     * @return array<int, array<int, mixed>>
     */
    protected function bacaBukuKerja(TestResponse $respons): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx-');
        file_put_contents($path, $this->kontenRespons($respons));

        try {
            return IOFactory::load($path)->getActiveSheet()->toArray();
        } finally {
            unlink($path);
        }
    }

    /**
     * Buka lembar kerja dari file .xlsx hasil export supaya gaya selnya bisa
     * diperiksa, bukan cuma isinya.
     */
    protected function bukaLembar(TestResponse $respons): Worksheet
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx-');
        file_put_contents($path, $this->kontenRespons($respons));

        try {
            return IOFactory::load($path)->getActiveSheet();
        } finally {
            unlink($path);
        }
    }

    /**
     * Gabungkan semua sel jadi satu string, supaya isi workbook mudah
     * ditebak dengan assertStringContainsString.
     *
     * @param  array<int, array<int, mixed>>  $rows
     */
    protected function gabungBukuKerja(array $rows): string
    {
        return implode("\n", array_map(fn (array $baris) => implode('|', $baris), $rows));
    }
}
