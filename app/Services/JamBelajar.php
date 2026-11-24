<?php

namespace App\Services;

use App\Models\JadwalPelajaran;
use App\Models\JamBelajarKonfigurasi;
use App\Models\SesiJamBelajar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class JamBelajar
{
    private ?Collection $semuaSesi = null;

    public function konfigurasi(): JamBelajarKonfigurasi
    {
        return JamBelajarKonfigurasi::query()->orderBy('id')->firstOrFail();
    }

    public function sesi(): Collection
    {
        if ($this->semuaSesi === null) {
            $this->semuaSesi = $this->konfigurasi()->sesi()->diurutkan()->get()->values();
            $nomorPelajaran = 0;

            $this->semuaSesi->each(function (SesiJamBelajar $sesi) use (&$nomorPelajaran): void {
                $sesi->nomor_pelajaran = $sesi->jenis === SesiJamBelajar::JENIS_PELAJARAN
                    ? ++$nomorPelajaran
                    : null;
            });
        }

        return $this->semuaSesi;
    }

    public function kosongkanCache(): void
    {
        $this->semuaSesi = null;
    }

    public function nomorPelajaran(?SesiJamBelajar $sesi): int
    {
        if ($sesi === null) {
            return 0;
        }

        $tersimpan = $this->sesi()->first(
            fn (SesiJamBelajar $item) => (int) $item->id === (int) $sesi->id
        );

        if ($tersimpan === null) {
            return (int) $sesi->urutan;
        }

        return (int) ($tersimpan->nomor_pelajaran ?? 0);
    }

    public function jumlahJadwalSesi(SesiJamBelajar $sesi): int
    {
        return JadwalPelajaran::query()->where('sesi_id', $sesi->id)->count();
    }

    /** @return Collection<string, int> */
    public function jumlahJadwalPerHari(): Collection
    {
        return JadwalPelajaran::query()
            ->selectRaw('hari, COUNT(*) AS jumlah')
            ->groupBy('hari')
            ->pluck('jumlah', 'hari')
            ->map(fn ($jumlah) => (int) $jumlah);
    }

    public function normalisasiHari(mixed $hari): array
    {
        if (! is_array($hari)) {
            return JadwalPelajaran::HARI_AKTIF_UTAMA;
        }

        $dipilih = array_values(array_unique(array_map('strval', $hari)));
        $hasil = array_values(array_filter(
            JadwalPelajaran::HARI,
            fn (string $nama) => in_array($nama, $dipilih, true)
        ));

        return $hasil === [] ? JadwalPelajaran::HARI_AKTIF_UTAMA : $hasil;
    }

    public function hariAktif(?JamBelajarKonfigurasi $konfigurasi = null): array
    {
        return $this->normalisasiHari(($konfigurasi ?? $this->konfigurasi())->hari_aktif);
    }

    public function opsiPelajaran(): Collection
    {
        return $this->konfigurasi()
            ->sesi()
            ->pelajaran()
            ->diurutkan()
            ->get()
            ->map(fn (SesiJamBelajar $sesi) => [
                'id' => $sesi->id,
                'nama' => $sesi->nama,
                'nomor' => $this->nomorPelajaran($sesi),
                'label' => $sesi->nama.' ('.$sesi->waktuLabel().')',
                'mulai' => substr((string) $sesi->jam_mulai, 0, 5),
                'selesai' => substr((string) $sesi->jam_selesai, 0, 5),
            ])
            ->values();
    }

    public function dataAdmin(): array
    {
        $konfigurasi = $this->konfigurasi();

        return [
            'konfigurasi' => [
                'id' => $konfigurasi->id,
                'jam_masuk' => $konfigurasi->jamMasukLabel(),
                'jam_selesai' => $konfigurasi->jamSelesaiLabel(),
                'keterangan' => $konfigurasi->keterangan,
                'hari_aktif' => $this->hariAktif($konfigurasi),
                'jadwal_per_hari' => $this->jumlahJadwalPerHari(),
            ],
            'sesi' => $this->sesi()->map(fn (SesiJamBelajar $sesi) => [
                'id' => $sesi->id,
                'nama' => $sesi->nama,
                'jenis' => $sesi->jenis,
                'jenis_label' => $sesi->jenisLabel(),
                'urutan' => $sesi->urutan,
                'nomor_pelajaran' => (int) ($sesi->nomor_pelajaran ?? 0),
                'jumlah_jadwal' => $this->jumlahJadwalSesi($sesi),
                'jam_mulai' => substr((string) $sesi->jam_mulai, 0, 5),
                'jam_selesai' => substr((string) $sesi->jam_selesai, 0, 5),
                'waktu' => $sesi->waktuLabel(),
            ])->values()->all(),
        ];
    }

    public function dalamRentang(
        string $mulai,
        string $selesai,
        ?string $batasMulai = null,
        ?string $batasSelesai = null,
    ): bool {
        $konfigurasi = ($batasMulai === null || $batasSelesai === null) ? $this->konfigurasi() : null;
        $batasMulai ??= $konfigurasi->jamMasukLabel();
        $batasSelesai ??= $konfigurasi->jamSelesaiLabel();
        $mulai = substr($mulai, 0, 5);
        $selesai = substr($selesai, 0, 5);

        return $mulai >= substr($batasMulai, 0, 5)
            && $selesai <= substr($batasSelesai, 0, 5);
    }

    public function sesiDiLuarRentang(string $batasMulai, string $batasSelesai): Collection
    {
        return $this->sesi()->filter(
            fn (SesiJamBelajar $sesi) => ! $this->dalamRentang(
                (string) $sesi->jam_mulai,
                (string) $sesi->jam_selesai,
                $batasMulai,
                $batasSelesai
            )
        );
    }

    public function jadwalDiLuarRentang(string $batasMulai, string $batasSelesai): Collection
    {
        return JadwalPelajaran::query()
            ->with('sesi')
            ->get()
            ->filter(function (JadwalPelajaran $jadwal) use ($batasMulai, $batasSelesai): bool {
                $waktu = $jadwal->infoWaktu();

                return ! $this->dalamRentang(
                    $waktu['mulai'],
                    $waktu['selesai'],
                    $batasMulai,
                    $batasSelesai
                );
            })
            ->values();
    }

    public function susunUlangUrutan(
        JamBelajarKonfigurasi $konfigurasi,
        ?int $sesiId = null,
        ?int $urutanDiinginkan = null,
    ): void {
        $this->kosongkanCache();

        $daftar = $this->sesi()
            ->map(function (SesiJamBelajar $sesi) use ($sesiId, $urutanDiinginkan): SesiJamBelajar {
                if ($sesiId !== null && (int) $sesi->id === $sesiId && $urutanDiinginkan !== null) {
                    $sesi->urutan = $urutanDiinginkan;
                }

                return $sesi;
            })
            ->sortBy('jam_mulai')
            ->sortBy('urutan')
            ->values();

        if ($daftar->count() > 55) {
            throw new \RuntimeException('Jumlah sesi terlalu banyak untuk disusun ulang otomatis.');
        }

        DB::transaction(function () use ($daftar, $konfigurasi): void {
            DB::table('sesi_jam_belajar')
                ->where('jam_belajar_konfigurasi_id', $konfigurasi->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            DB::table('sesi_jam_belajar')
                ->where('jam_belajar_konfigurasi_id', $konfigurasi->id)
                ->update(['urutan' => DB::raw('urutan + 100')]);

            foreach ($daftar as $indeks => $sesi) {
                DB::table('sesi_jam_belajar')
                    ->where('id', $sesi->id)
                    ->update(['urutan' => $indeks + 1]);
            }
        });

        $this->kosongkanCache();
    }

    public function bentrok(
        JamBelajarKonfigurasi $konfigurasi,
        string $mulai,
        string $selesai,
        ?int $kecualiId = null,
    ): ?SesiJamBelajar {
        return SesiJamBelajar::query()
            ->where('jam_belajar_konfigurasi_id', $konfigurasi->id)
            ->when($kecualiId !== null, fn ($query) => $query->whereKeyNot($kecualiId))
            ->where('jam_mulai', '<', substr($selesai, 0, 5))
            ->where('jam_selesai', '>', substr($mulai, 0, 5))
            ->orderBy('jam_mulai')
            ->first();
    }
}
