<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\JadwalPelajaran;
use App\Models\SesiJamBelajar;
use App\Services\ActivityLogger;
use App\Services\JadwalGuruService;
use App\Services\JamBelajar;
use App\Support\PerbandinganData;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JamBelajarController extends Controller
{
    public function __construct(
        private readonly JamBelajar $jamBelajar,
        private readonly JadwalGuruService $jadwalGuru,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(): View
    {
        return view('dashboard.admin.jam-belajar.index', [
            'jamBelajarData' => $this->jamBelajar->dataAdmin(),
        ]);
    }

    public function updateOperasional(Request $request): JsonResponse
    {
        $data = $request->validate([
            'jam_masuk' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'hari_aktif' => ['sometimes', 'array', 'min:1'],
            'hari_aktif.*' => ['string', Rule::in(JadwalPelajaran::HARI)],
            'konfirmasi_hari_nonaktif' => ['sometimes', 'boolean'],
        ]);

        $masuk = substr((string) $data['jam_masuk'], 0, 5);
        $selesai = substr((string) $data['jam_selesai'], 0, 5);

        if ($masuk >= $selesai) {
            $this->gagal('jam_selesai', 'Jam selesai sekolah harus lebih lambat dari jam masuk sekolah.');
        }

        $sesiLuar = $this->jamBelajar->sesiDiLuarRentang($masuk, $selesai);
        $jadwalLuar = $this->jamBelajar->jadwalDiLuarRentang($masuk, $selesai);

        if ($sesiLuar->isNotEmpty() || $jadwalLuar->isNotEmpty()) {
            $pesan = [];

            if ($sesiLuar->isNotEmpty()) {
                $pesan[] = $sesiLuar->count().' sesi masih berada di luar rentang '.$masuk.'–'.$selesai.'.';
            }

            if ($jadwalLuar->isNotEmpty()) {
                $pesan[] = $jadwalLuar->count().' jadwal tersimpan berada di luar rentang '.$masuk.'–'.$selesai.'.';
            }

            $this->gagal('jam_selesai', implode(' ', $pesan).' Sesuaikan terlebih dahulu sebelum mengubah jam operasional.');
        }

        $konfigurasi = $this->jamBelajar->konfigurasi();
        $hariSekarang = $this->jamBelajar->hariAktif($konfigurasi);
        $hariBaru = array_key_exists('hari_aktif', $data)
            ? $this->jamBelajar->normalisasiHari($data['hari_aktif'])
            : $hariSekarang;
        $hariNonaktif = array_values(array_diff($hariSekarang, $hariBaru));
        $jumlahPerHari = $this->jamBelajar->jumlahJadwalPerHari();
        $jadwalTerdampak = collect($hariNonaktif)
            ->mapWithKeys(fn (string $hari) => [$hari => (int) $jumlahPerHari->get($hari, 0)])
            ->filter(fn (int $jumlah) => $jumlah > 0);

        if ($jadwalTerdampak->isNotEmpty() && ! $request->boolean('konfirmasi_hari_nonaktif')) {
            $rincian = $jadwalTerdampak
                ->map(fn (int $jumlah, string $hari) => $hari.' ('.$jumlah.' jadwal)')
                ->implode(', ');

            $this->gagal(
                'hari_aktif',
                'Ada jadwal pada '.$rincian.'. Jadwal tidak akan dihapus, tetapi tidak tampil pada hari yang dinonaktifkan. Konfirmasi perubahan untuk tetap melanjutkan.'
            );
        }

        $pembaruan = [
            'jam_masuk' => $masuk,
            'jam_selesai' => $selesai,
            'keterangan' => filled($data['keterangan'] ?? null) ? trim((string) $data['keterangan']) : null,
            'hari_aktif' => $hariBaru,
        ];

        if (PerbandinganData::identik($konfigurasi, $pembaruan, [
            'jam_masuk' => fn ($nilai, $model) => substr((string) $model->jam_masuk, 0, 5),
            'jam_selesai' => fn ($nilai, $model) => substr((string) $model->jam_selesai, 0, 5),
            'hari_aktif' => fn ($nilai, $model) => $this->jamBelajar->hariAktif($model),
        ])) {
            return response()->json([
                'status' => 'success',
                'message' => 'Jam operasional sekolah berhasil diperbarui.',
                'data' => [
                    'konfigurasi' => [
                        'id' => $konfigurasi->id,
                        'jam_masuk' => $konfigurasi->jamMasukLabel(),
                        'jam_selesai' => $konfigurasi->jamSelesaiLabel(),
                        'keterangan' => $konfigurasi->keterangan,
                        'hari_aktif' => $this->jamBelajar->hariAktif($konfigurasi),
                        'jadwal_per_hari' => $this->jamBelajar->jumlahJadwalPerHari(),
                    ],
                ],
            ]);
        }

        $konfigurasi->update($pembaruan);

        $this->catat(
            'Memperbarui jam operasional sekolah menjadi '.$masuk.'–'.$selesai
                .' dan hari belajar '.implode(', ', $this->jamBelajar->hariAktif($konfigurasi)).'.',
            $request
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Jam operasional sekolah berhasil diperbarui.',
            'data' => [
                'konfigurasi' => [
                    'id' => $konfigurasi->id,
                    'jam_masuk' => $konfigurasi->jamMasukLabel(),
                    'jam_selesai' => $konfigurasi->jamSelesaiLabel(),
                    'keterangan' => $konfigurasi->keterangan,
                    'hari_aktif' => $this->jamBelajar->hariAktif($konfigurasi),
                    'jadwal_per_hari' => $this->jamBelajar->jumlahJadwalPerHari(),
                ],
            ],
        ]);
    }

    public function storeSesi(Request $request): JsonResponse
    {
        $data = $this->validasiSesi($request);
        $konfigurasi = $this->jamBelajar->konfigurasi();

        try {
            $sesi = DB::transaction(function () use ($data, $konfigurasi): SesiJamBelajar {
                $urutanAwal = (int) $konfigurasi->sesi()->max('urutan') + 1;
                $sesi = SesiJamBelajar::create([
                    'jam_belajar_konfigurasi_id' => $konfigurasi->id,
                    'nama' => $data['nama'],
                    'jenis' => $data['jenis'],
                    'urutan' => $urutanAwal,
                    'jam_mulai' => $data['jam_mulai'],
                    'jam_selesai' => $data['jam_selesai'],
                ]);

                $this->jamBelajar->susunUlangUrutan($konfigurasi, (int) $sesi->id, $data['urutan']);

                return $sesi->fresh();
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'sesi_jam_urutan_unik')
                || str_contains($exception->getMessage(), 'sesi_jam_mulai_unik')) {
                $this->gagal(
                    'urutan',
                    'Urutan atau waktu sesi bentrok dengan sesi lain. Muat ulang halaman lalu coba lagi.'
                );
            }

            throw $exception;
        }

        $this->jamBelajar->kosongkanCache();
        $this->catat('Menambahkan sesi jam belajar '.$sesi->nama.' ('.$sesi->waktuLabel().').', $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Sesi jam pelajaran berhasil ditambahkan.',
            'data' => [
                'sesi' => $this->barisSesi($sesi),
                'daftar_sesi' => $this->daftarSesi(),
            ],
        ], 201);
    }

    public function updateSesi(Request $request, SesiJamBelajar $sesi): JsonResponse
    {
        $data = $this->validasiSesi($request, $sesi);
        $konfigurasi = $this->jamBelajar->konfigurasi();
        $jumlahJadwal = $this->jamBelajar->jumlahJadwalSesi($sesi);
        $waktuBerubah = substr((string) $sesi->jam_mulai, 0, 5) !== $data['jam_mulai']
            || substr((string) $sesi->jam_selesai, 0, 5) !== $data['jam_selesai'];
        $jenisBerubah = $sesi->jenis !== $data['jenis'];

        if ($jumlahJadwal > 0 && $sesi->jenis === SesiJamBelajar::JENIS_PELAJARAN
            && $data['jenis'] === SesiJamBelajar::JENIS_ISTIRAHAT) {
            $this->gagal(
                'jenis',
                'Sesi ini masih dipakai '.$jumlahJadwal.' jadwal pelajaran. Sesuaikan jadwal terkait sebelum mengubahnya menjadi Istirahat.'
            );
        }

        if ($jumlahJadwal > 0 && ($waktuBerubah || $jenisBerubah)
            && ! $request->boolean('konfirmasi_perubahan')) {
            $this->gagal(
                'jam_mulai',
                'Sesi ini dipakai '.$jumlahJadwal.' jadwal. Perubahan waktu atau jenis akan diterapkan ke seluruh jadwal terkait. Konfirmasi perubahan untuk melanjutkan.'
            );
        }

        if ($waktuBerubah && $jumlahJadwal > 0) {
            $bentrok = $this->jadwalGuru->bentrokPerubahanSesi($sesi, $data['jam_mulai'], $data['jam_selesai']);

            if ($bentrok->isNotEmpty()) {
                $this->gagal(
                    'jam_mulai',
                    $bentrok->count().' jadwal terkait akan mengalami bentrok pada waktu baru. Sesuaikan jadwal tersebut terlebih dahulu.'
                );
            }
        }

        $jadwalAkanBerubah = false;

        if ($waktuBerubah && $jumlahJadwal > 0) {
            $jadwalAkanBerubah = JadwalPelajaran::query()
                ->where('sesi_id', $sesi->id)
                ->get(['jam_mulai', 'jam_selesai'])
                ->contains(fn ($jadwal) => substr((string) $jadwal->jam_mulai, 0, 5) !== $data['jam_mulai']
                    || substr((string) $jadwal->jam_selesai, 0, 5) !== $data['jam_selesai']);
        }

        if (! $jadwalAkanBerubah && PerbandinganData::identik(
            $sesi,
            [
                'nama' => $data['nama'],
                'jenis' => $data['jenis'],
                'urutan' => $data['urutan'],
                'jam_mulai' => $data['jam_mulai'],
                'jam_selesai' => $data['jam_selesai'],
            ],
            [
                'jam_mulai' => fn ($nilai, $model) => substr((string) $model->jam_mulai, 0, 5),
                'jam_selesai' => fn ($nilai, $model) => substr((string) $model->jam_selesai, 0, 5),
            ]
        )) {
            return response()->json([
                'status' => 'success',
                'message' => 'Sesi jam pelajaran berhasil diperbarui.',
                'data' => [
                    'sesi' => $this->barisSesi($sesi),
                    'daftar_sesi' => $this->daftarSesi(),
                ],
            ]);
        }

        $jumlahTerkait = 0;
        $sesi = DB::transaction(function () use ($sesi, $data, $konfigurasi, $waktuBerubah, &$jumlahTerkait): SesiJamBelajar {
            $sesi->update([
                'nama' => $data['nama'],
                'jenis' => $data['jenis'],
                'urutan' => (int) $sesi->urutan,
                'jam_mulai' => $data['jam_mulai'],
                'jam_selesai' => $data['jam_selesai'],
            ]);

            if ($waktuBerubah) {
                $jumlahTerkait = JadwalPelajaran::query()
                    ->where('sesi_id', $sesi->id)
                    ->update([
                        'jam_mulai' => $data['jam_mulai'],
                        'jam_selesai' => $data['jam_selesai'],
                    ]);
            }

            $this->jamBelajar->susunUlangUrutan($konfigurasi, (int) $sesi->id, $data['urutan']);

            return $sesi->fresh();
        });

        $this->jamBelajar->kosongkanCache();
        $this->catat('Memperbarui sesi jam belajar '.$sesi->nama.' ('.$sesi->waktuLabel().').', $request);

        if ($jumlahTerkait > 0) {
            $this->activityLogger->record(
                ActivityLog::TIPE_JADWAL_PENYESUAIAN,
                'Sinkronisasi '.$jumlahTerkait.' jadwal mengajar dengan waktu sesi '.$sesi->nama.'.',
                $request->user()
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sesi jam pelajaran berhasil diperbarui.',
            'data' => [
                'sesi' => $this->barisSesi($sesi),
                'daftar_sesi' => $this->daftarSesi(),
            ],
        ]);
    }

    public function destroySesi(Request $request, SesiJamBelajar $sesi): JsonResponse
    {
        $jumlahJadwal = $this->jamBelajar->jumlahJadwalSesi($sesi);

        if ($jumlahJadwal > 0) {
            $this->gagal(
                'sesi',
                'Sesi '.$sesi->nama.' masih terhubung dengan '.$jumlahJadwal.' jadwal. Sesuaikan atau hapus jadwal terkait terlebih dahulu; sesi tidak dihapus.'
            );
        }

        $nama = $sesi->nama;
        $konfigurasi = $this->jamBelajar->konfigurasi();

        DB::transaction(function () use ($sesi, $konfigurasi): void {
            $sesi->delete();
            $this->jamBelajar->susunUlangUrutan($konfigurasi);
        });

        $this->jamBelajar->kosongkanCache();
        $this->catat('Menghapus sesi jam belajar '.$nama.'.', $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Sesi berhasil dihapus.',
            'data' => [
                'id' => $sesi->id,
                'daftar_sesi' => $this->daftarSesi(),
            ],
        ]);
    }

    private function validasiSesi(Request $request, ?SesiJamBelajar $sesi = null): array
    {
        $konfigurasi = $this->jamBelajar->konfigurasi();
        $jumlahSesi = (int) $konfigurasi->sesi()->count() + ($sesi === null ? 1 : 0);

        $request->merge([
            'nama' => trim((string) $request->input('nama', '')),
        ]);

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:60'],
            'jenis' => ['required', Rule::in(SesiJamBelajar::JENIS)],
            'urutan' => ['required', 'integer', 'min:1', 'max:'.min(55, $jumlahSesi)],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i'],
            'konfirmasi_perubahan' => ['sometimes', 'boolean'],
        ]);

        $data['urutan'] = (int) $data['urutan'];
        $data['jam_mulai'] = substr((string) $data['jam_mulai'], 0, 5);
        $data['jam_selesai'] = substr((string) $data['jam_selesai'], 0, 5);

        if ($data['jam_mulai'] >= $data['jam_selesai']) {
            $this->gagal('jam_selesai', 'Jam selesai sesi harus lebih lambat dari jam mulai sesi.');
        }

        if (! $this->jamBelajar->dalamRentang($data['jam_mulai'], $data['jam_selesai'])) {
            $this->gagal(
                'jam_mulai',
                'Sesi harus berada di dalam jam operasional '
                    .$konfigurasi->jamMasukLabel().'–'.$konfigurasi->jamSelesaiLabel().'.'
            );
        }

        $bentrok = $this->jamBelajar->bentrok(
            $konfigurasi,
            $data['jam_mulai'],
            $data['jam_selesai'],
            $sesi?->id
        );

        if ($bentrok !== null) {
            $this->gagal(
                'jam_mulai',
                'Waktu bertabrakan dengan '.$bentrok->nama.' ('.$bentrok->waktuLabel().').'
            );
        }

        $waktuTersusun = $konfigurasi->sesi()
            ->when($sesi !== null, fn ($query) => $query->whereKeyNot($sesi->id))
            ->pluck('jam_mulai')
            ->map(fn ($jam) => substr((string) $jam, 0, 5))
            ->push($data['jam_mulai'])
            ->sort()
            ->values();
        $urutanSeharusnya = (int) $waktuTersusun->search($data['jam_mulai'], true) + 1;

        if ($data['urutan'] !== $urutanSeharusnya) {
            $this->gagal(
                'urutan',
                'Urutan harus mengikuti posisi waktu. Untuk jam '.$data['jam_mulai'].', gunakan urutan '.$urutanSeharusnya.'.'
            );
        }

        return $data;
    }

    /** @return array<int, array<string, mixed>> */
    private function daftarSesi(): array
    {
        return $this->jamBelajar->sesi()
            ->map(fn (SesiJamBelajar $item): array => $this->barisSesi($item))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function barisSesi(SesiJamBelajar $sesi): array
    {
        return [
            'id' => $sesi->id,
            'nama' => $sesi->nama,
            'jenis' => $sesi->jenis,
            'jenis_label' => $sesi->jenisLabel(),
            'urutan' => (int) $sesi->urutan,
            'nomor_pelajaran' => $this->jamBelajar->nomorPelajaran($sesi),
            'jumlah_jadwal' => $this->jamBelajar->jumlahJadwalSesi($sesi),
            'jam_mulai' => substr((string) $sesi->jam_mulai, 0, 5),
            'jam_selesai' => substr((string) $sesi->jam_selesai, 0, 5),
            'waktu' => $sesi->waktuLabel(),
        ];
    }

    private function gagal(string $field, string $pesan): never
    {
        throw ValidationException::withMessages([$field => $pesan]);
    }

    private function catat(string $deskripsi, Request $request): void
    {
        $this->activityLogger->record(
            ActivityLog::TIPE_DATA_KELAS,
            $deskripsi,
            $request->user()
        );
    }
}
