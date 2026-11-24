<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Materi;
use App\Models\Tugas;
use App\Support\FileMateri;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait MelayaniFileMateri
{
    private function responseFileLampiran(Materi|Tugas $model, bool $unduh, string $namaFallback = 'lampiran'): StreamedResponse
    {
        abort_if($model->file_path === null, 404);

        $disk = Storage::disk('local');

        abort_if(! $disk->exists($model->file_path), 404);

        $headers = [
            'Content-Type' => $model->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ];

        $nama = FileMateri::namaAman($model->nama_file ?: $namaFallback);

        if ($unduh || ! FileMateri::bisaDipratinjau($model->mime_type)) {
            return $disk->download($model->file_path, $nama, $headers);
        }

        return $disk->response($model->file_path, $nama, $headers);
    }

    private function responseFileMateri(Materi $materi, bool $unduh): StreamedResponse
    {
        return $this->responseFileLampiran($materi, $unduh, 'materi');
    }

    private function responseFileTugas(Tugas $tugas, bool $unduh): StreamedResponse
    {
        return $this->responseFileLampiran($tugas, $unduh, 'lampiran-tugas');
    }
}
