<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\JadwalPelajaran;
use App\Models\JamBelajarKonfigurasi;
use App\Models\SesiJamBelajar;
use App\Models\User;
use App\Support\Kelas;
use App\Support\StrukturKelas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Analisis jadwal khusus Guru.
 *
 * Seluruh perhitungan (statistik, status, bentrok, saran sesi kosong, dan
 * riwayat penyesuaian) berasal dari database. Service ini sengaja tidak
 * mengubah skema tabel: riwayat disimpan di activity_logs yang sudah ada.
 */
class JadwalGuruService
{
    private const RIWAYAT_PREFIX = 'JADWAL_REVISI_V1|';

    private const WARNA_JURUSAN = [
        'RPL' => ['bg' => '#E8F1FF', 'text' => '#1B4F9E'],
        'TKJ' => ['bg' => '#DCF4EE', 'text' => '#0B6554'],
    ];

    public function __construct(
        private readonly JamBelajar $jamBelajar,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array{tahun_ajaran: string, semester: string}  $periode
     * @return array<string, mixed>
     */
    public function analisis(User $guru, array $periode, ?JamBelajarKonfigurasi $konfigurasi = null): array
    {
        $konfigurasi ??= $this->jamBelajar->konfigurasi();
        $semua = $this->queryJadwal($periode)->get();
        $milik = $this->urutanJadwal($semua->where('guru_id', $guru->id), $konfigurasi);

        return $this->bangunAnalisis($milik, $semua, $konfigurasi, $guru);
    }

    /**
     * Analisis seluruh jadwal sekolah untuk halaman Admin. Pakai source data
     * dan perhitungan yang sama persis dengan analisis Guru.
     *
     * @param  array{tahun_ajaran: string, semester: string}  $periode
     * @return array<string, mixed>
     */
    public function analisisSemua(array $periode, ?JamBelajarKonfigurasi $konfigurasi = null): array
    {
        $konfigurasi ??= $this->jamBelajar->konfigurasi();
        $semua = $this->queryJadwal($periode)->get();
        $semuaUrut = $this->urutanJadwal($semua, $konfigurasi);

        return $this->bangunAnalisis($semuaUrut, $semua, $konfigurasi);
    }

    /**
     * @param  array{tahun_ajaran: string, semester: string}  $periode
     */
    private function queryJadwal(array $periode): Builder
    {
        return JadwalPelajaran::query()
            ->with(['mataPelajaran', 'sesi', 'guru'])
            ->where('tahun_ajaran', $periode['tahun_ajaran'])
            ->where('semester', $periode['semester']);
    }

    private function urutanJadwal(Collection $jadwal, JamBelajarKonfigurasi $konfigurasi): Collection
    {
        return $jadwal
            ->sort(function (JadwalPelajaran $a, JadwalPelajaran $b) use ($konfigurasi): int {
                $urutanHari = array_search($a->hari, JadwalPelajaran::HARI, true) <=> array_search($b->hari, JadwalPelajaran::HARI, true);

                if ($urutanHari !== 0) {
                    return $urutanHari;
                }

                $awal = $this->waktu($a, $konfigurasi)['mulai'] <=> $this->waktu($b, $konfigurasi)['mulai'];

                return $awal !== 0 ? $awal : StrukturKelas::bandingkanKelas((string) $a->kelas, (string) $b->kelas);
            })
            ->values();
    }

    /**
     * @param  Collection<int, JadwalPelajaran>  $milik
     * @param  Collection<int, JadwalPelajaran>  $semua
     * @return array<string, mixed>
     */
    private function bangunAnalisis(
        Collection $milik,
        Collection $semua,
        JamBelajarKonfigurasi $konfigurasi,
        ?User $guru = null,
    ): array {
        $hariAktif = $this->jamBelajar->hariAktif($konfigurasi);

        $sesiPelajaran = $this->jamBelajar->opsiPelajaran()
            ->map(fn (array $sesi) => [
                'mulai' => $sesi['mulai'],
                'selesai' => $sesi['selesai'],
            ])
            ->values();

        $items = $milik
            ->map(fn (JadwalPelajaran $item) => $this->itemData($item, $semua, $konfigurasi))
            ->map(function (array $item) use ($sesiPelajaran): array {
                $item['di_luar_sesi'] = ! $sesiPelajaran->contains(
                    fn (array $sesi) => $item['mulai'] >= $sesi['mulai']
                        && $item['selesai'] <= $sesi['selesai']
                );

                return $item;
            })
            ->values();
        $bentrok = $this->grupBentrok($milik, $items, $semua, $konfigurasi);
        $bentrokIds = $items->where('bentrok', true)->pluck('id')->unique();

        $items = $items->map(function (array $item) use ($bentrokIds): array {
            if ($bentrokIds->contains($item['id'])) {
                $item['status'] = 'bentrok';
                $item['status_label'] = 'Bentrok';
            } elseif ($item['di_luar_sesi'] ?? false) {
                $item['status'] = 'luar';
                $item['status_label'] = 'Di luar sesi';
            }

            return $item;
        })->values();

        $perHari = collect($hariAktif)
            ->mapWithKeys(fn (string $hari) => [
                $hari => $items->where('hari', $hari)->values()->all(),
            ]);

        $saran = $items
            ->where('perlu_disesuaikan', true)
            ->map(fn (array $item) => [
                'id' => $item['id'],
                'guru_id' => $item['guru_id'],
                'guru' => $item['guru'],
                'hari' => $item['hari'],
                'kelas' => $item['kelas'].($item['jurusan'] !== '' ? ' '.$item['jurusan'] : ''),
                'mapel' => $item['mapel'],
                'sesi_nomor' => $item['sesi_nomor'],
                'sesi_label' => $item['sesi_label'],
                'lama' => $item['tersimpan'],
                'baru' => $item['label'],
                'sesi_id' => $item['sesi_id'],
            ])
            ->values()
            ->all();

        return [
            'items' => $items->all(),
            'perHari' => $perHari->all(),
            'saran' => $saran,
            'bentrok' => $bentrok->all(),
            'perlu' => $items->where('perlu_disesuaikan', true)->values()->all(),
            'bentrokJumlah' => $bentrokIds->count(),
            'catatan' => $items
                ->flatMap(fn (array $item) => array_merge($item['catatan'], $item['konflik']))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'statistik' => [
                'total' => $items->count(),
                'kelas' => $items->pluck('rombel_key')->unique()->count(),
                'mapel' => $items->pluck('mapel_id')->filter()->unique()->count(),
            ],
            'riwayat' => $guru === null ? $this->riwayatSemua() : $this->riwayat($guru),
        ];
    }

    /**
     * Cek bentrok langsung untuk form tambah/ubah. Tidak menulis database.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function cekBentrok(array $data, ?User $guru, int|array|null $kecualiId = null): array
    {
        $periode = data_get($data, 'periode', []);
        $hari = (string) ($data['hari'] ?? '');
        $mulai = substr((string) ($data['jam_mulai'] ?? ''), 0, 5);
        $selesai = substr((string) ($data['jam_selesai'] ?? ''), 0, 5);
        [$kelas, $jurusan] = $this->rombel((string) ($data['rombel'] ?? ''));
        $ruang = trim((string) ($data['ruang'] ?? ''));

        $query = JadwalPelajaran::query()
            ->with(['mataPelajaran', 'sesi', 'guru'])
            ->where('tahun_ajaran', $periode['tahun_ajaran'] ?? '')
            ->where('semester', $periode['semester'] ?? '')
            ->where('hari', $hari)
            ->when($kecualiId !== null, function ($query) use ($kecualiId): void {
                $query->whereNotIn('id', is_array($kecualiId) ? $kecualiId : [$kecualiId]);
            });

        $calon = $query->get();
        $konfigurasi = $this->jamBelajar->konfigurasi();

        $guruBentrok = $guru === null
            ? collect()
            : $calon->filter(function (JadwalPelajaran $item) use ($guru, $konfigurasi, $mulai, $selesai): bool {
                return (int) $item->guru_id === (int) $guru->id
                    && $this->beririsan($this->waktu($item, $konfigurasi), $mulai, $selesai);
            })->values();

        $ruangBentrok = $ruang === ''
            ? collect()
            : $calon->filter(function (JadwalPelajaran $item) use ($konfigurasi, $ruang, $mulai, $selesai): bool {
                return strcasecmp(trim((string) $item->ruang), $ruang) === 0
                    && $this->beririsan($this->waktu($item, $konfigurasi), $mulai, $selesai);
            })->values();

        $kelasBentrok = $calon->filter(function (JadwalPelajaran $item) use ($konfigurasi, $kelas, $jurusan, $mulai, $selesai): bool {
            if (Str::upper(Kelas::normalize((string) $item->kelas)) !== Str::upper($kelas)) {
                return false;
            }

            $jurusanItem = trim((string) $item->jurusan);

            return ($jurusan === '' || $jurusanItem === '' || $jurusanItem === $jurusan)
                && $this->beririsan($this->waktu($item, $konfigurasi), $mulai, $selesai);
        })->values();

        $pemeriksaan = [
            ['label' => 'Guru bebas', 'aman' => $guruBentrok->isEmpty()],
            ['label' => 'Ruang kosong', 'aman' => $ruangBentrok->isEmpty()],
            ['label' => 'Kelas tidak punya pelajaran lain', 'aman' => $kelasBentrok->isEmpty()],
        ];

        $aman = collect($pemeriksaan)->every(fn (array $item) => $item['aman']);
        $sesiLabel = (string) ($data['sesi_label'] ?? '');
        $durasi = $this->durasi($mulai, $selesai);
        $detailKonflik = $this->detailKonflikCek($guruBentrok, $ruangBentrok, $kelasBentrok, $konfigurasi);

        $rekomendasi = $aman ? [] : $this->rekomendasiCekBentrok(
            $periode,
            $hari,
            $mulai,
            $selesai,
            $guru,
            $kelas,
            $jurusan,
            $ruang,
            $kecualiId,
            $konfigurasi,
            $guruBentrok->isNotEmpty(),
            $kelasBentrok->isNotEmpty(),
            $ruangBentrok->isNotEmpty(),
        );

        return [
            'aman' => $aman,
            'ringkasan' => trim($hari.' · '.$sesiLabel.' · '.$mulai.'–'.$selesai.' · '.$durasi, ' ·'),
            'pemeriksaan' => $pemeriksaan,
            'konflik' => $detailKonflik,
            'rekomendasi' => $rekomendasi,
            'pesan' => $aman
                ? 'Tidak ada bentrok. Jadwal siap disimpan.'
                : $this->ringkasanPesanKonflik($detailKonflik),
        ];
    }

    /** @return Collection<int, JadwalPelajaran> */
    public function bentrokPerubahanSesi(SesiJamBelajar $sesi, string $mulai, string $selesai): Collection
    {
        $nomor = $this->jamBelajar->nomorPelajaran($sesi);

        $jadwalSesi = JadwalPelajaran::query()
            ->where('sesi_id', $sesi->id)
            ->with('guru')
            ->get();
        $kecuali = $jadwalSesi->modelKeys();

        return $jadwalSesi
            ->filter(function (JadwalPelajaran $jadwal) use ($mulai, $selesai, $nomor, $kecuali): bool {
                $cek = $this->cekBentrok([
                    'periode' => [
                        'tahun_ajaran' => $jadwal->tahun_ajaran,
                        'semester' => $jadwal->semester,
                    ],
                    'hari' => (string) $jadwal->hari,
                    'jam_mulai' => $mulai,
                    'jam_selesai' => $selesai,
                    'rombel' => $jadwal->kelas.'|'.$jadwal->jurusan,
                    'ruang' => (string) $jadwal->ruang,
                    'sesi_label' => 'Sesi '.$nomor,
                ], $jadwal->guru, $kecuali);

                return ! $cek['aman'];
            })
            ->values();
    }

    /**
     * Terapkan semua perubahan dalam satu transaksi dan simpan snapshot
     * supaya bisa dibatalkan dari riwayat.
     *
     * @param  array<int, array{id: int, sesi_id: int}>  $perubahan
     * @param  array{tahun_ajaran: string, semester: string}  $periode
     * @return array{batch: string, jumlah: int}
     */
    public function terapkanPerubahan(array $perubahan, User $guru, array $periode): array
    {
        return $this->terapkanPerubahanInt($perubahan, $guru, $periode, $guru);
    }

    /**
     * @param  array<int, array{id: int, sesi_id: int}>  $perubahan
     * @param  array{tahun_ajaran: string, semester: string}  $periode
     * @return array{batch: string, jumlah: int}
     */
    public function terapkanPerubahanAdmin(array $perubahan, array $periode, User $actor): array
    {
        return $this->terapkanPerubahanInt($perubahan, null, $periode, $actor);
    }

    /**
     * @param  array<int, array{id: int, sesi_id: int}>  $perubahan
     * @param  array{tahun_ajaran: string, semester: string}  $periode
     * @return array{batch: string, jumlah: int}
     */
    private function terapkanPerubahanInt(
        array $perubahan,
        ?User $pemilik,
        array $periode,
        User $actor,
    ): array {
        if ($perubahan === []) {
            throw ValidationException::withMessages(['perubahan' => 'Pilih minimal satu perubahan.']);
        }

        $ids = [];
        $sesiIds = [];

        foreach ($perubahan as $perubahanItem) {
            $id = (int) ($perubahanItem['id'] ?? 0);
            $sesiId = (int) ($perubahanItem['sesi_id'] ?? 0);

            if ($id <= 0 || $sesiId <= 0) {
                throw ValidationException::withMessages(['perubahan' => 'Perubahan tidak valid.']);
            }

            if (isset($ids[$id])) {
                throw ValidationException::withMessages(['perubahan' => 'Satu jadwal tidak boleh disesuaikan dua kali.']);
            }

            $ids[$id] = true;
            $sesiIds[$sesiId] = true;
        }

        $hasil = DB::transaction(function () use ($perubahan, $pemilik, $periode, $actor, $sesiIds): array {
            $konfigurasi = $this->jamBelajar->konfigurasi();

            JadwalPelajaran::query()
                ->where('tahun_ajaran', $periode['tahun_ajaran'])
                ->where('semester', $periode['semester'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $semua = JadwalPelajaran::query()
                ->with(['mataPelajaran', 'sesi', 'guru'])
                ->where('tahun_ajaran', $periode['tahun_ajaran'])
                ->where('semester', $periode['semester'])
                ->get()
                ->keyBy('id');

            $sesiTersedia = SesiJamBelajar::query()
                ->whereIn('id', array_keys($sesiIds))
                ->get()
                ->keyBy('id');

            $rencana = [];

            foreach ($perubahan as $perubahanItem) {
                $id = (int) $perubahanItem['id'];
                $sesiId = (int) $perubahanItem['sesi_id'];
                $jadwal = $semua->get($id);
                $sesi = $sesiTersedia->get($sesiId);

                if ($jadwal === null || ($pemilik !== null && (int) $jadwal->guru_id !== (int) $pemilik->id)) {
                    abort(404);
                }

                if ($sesi === null
                    || $sesi->jenis !== SesiJamBelajar::JENIS_PELAJARAN
                    || (int) $sesi->jam_belajar_konfigurasi_id !== (int) $konfigurasi->id) {
                    throw ValidationException::withMessages(['sesi_id' => 'Pilih sesi jam pelajaran yang tersedia.']);
                }

                $mulai = substr((string) $sesi->jam_mulai, 0, 5);
                $selesai = substr((string) $sesi->jam_selesai, 0, 5);

                if ($mulai >= $selesai || ! $this->jamBelajar->dalamRentang($mulai, $selesai)) {
                    throw ValidationException::withMessages(['sesi_id' => 'Waktu sesi yang dipilih tidak valid.']);
                }

                $rencana[$id] = [
                    'jadwal' => $jadwal,
                    'sesi' => $sesi,
                    'mulai' => $mulai,
                    'selesai' => $selesai,
                ];
            }

            $konflikBaru = $this->konflikBaru($semua, $rencana, $konfigurasi, array_keys($rencana));

            if ($konflikBaru !== []) {
                throw ValidationException::withMessages([
                    'perubahan' => 'Perubahan menimbulkan bentrok: '.implode(' ', array_unique($konflikBaru)),
                ]);
            }

            $batch = (string) Str::uuid();
            $snapshots = [];

            foreach ($rencana as $id => $itemRencana) {
                /** @var JadwalPelajaran $jadwal */
                $jadwal = $itemRencana['jadwal'];
                /** @var SesiJamBelajar $sesi */
                $sesi = $itemRencana['sesi'];

                $snapshots[] = [
                    'id' => (int) $id,
                    'sesi_id' => $jadwal->sesi_id === null ? null : (int) $jadwal->sesi_id,
                    'jam_mulai' => substr((string) $jadwal->jam_mulai, 0, 5),
                    'jam_selesai' => substr((string) $jadwal->jam_selesai, 0, 5),
                ];

                $jadwal->update([
                    'sesi_id' => (int) $sesi->id,
                    'jam_mulai' => $itemRencana['mulai'],
                    'jam_selesai' => $itemRencana['selesai'],
                ]);
            }

            $this->catatRiwayat($actor, [
                'batch' => $batch,
                'aksi' => 'sesuaikan',
                'jumlah' => count($snapshots),
                'snapshots' => $snapshots,
                'dibatalkan' => false,
            ]);

            return ['batch' => $batch, 'jumlah' => count($snapshots)];
        });

        return $hasil;

    }

    /**
     * Kembalikan jadwal ke snapshot sebelum penyesuaian.
     *
     * @return array{batch: string, jumlah: int}
     */
    public function batalkanPerubahan(string $batch, User $guru): array
    {
        return $this->batalkanPerubahanInt($batch, $guru, $guru);
    }

    /**
     * @return array{batch: string, jumlah: int}
     */
    public function batalkanPerubahanAdmin(string $batch, User $actor): array
    {
        return $this->batalkanPerubahanInt($batch, null, $actor);
    }

    /**
     * @return array{batch: string, jumlah: int}
     */
    private function batalkanPerubahanInt(string $batch, ?User $pemilik, User $actor): array
    {
        return DB::transaction(function () use ($batch, $pemilik, $actor): array {
            $log = ActivityLog::query()
                ->where('activity_type', 'jadwal_penyesuaian')
                ->when($pemilik !== null, fn ($query) => $query->where('user_id', $pemilik->id))
                ->where('description', 'like', self::RIWAYAT_PREFIX.'%')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get()
                ->first(function (ActivityLog $item) use ($batch): bool {
                    $data = $this->decodeRiwayat($item);

                    return $data !== null
                        && ($data['batch'] ?? '') === $batch
                        && ($data['aksi'] ?? '') === 'sesuaikan';
                });

            if ($log === null) {
                throw ValidationException::withMessages(['batch' => 'Riwayat perubahan tidak ditemukan.']);
            }

            $data = $this->decodeRiwayat($log);

            if (($data['dibatalkan'] ?? false) === true) {
                throw ValidationException::withMessages(['batch' => 'Perubahan ini sudah dibatalkan sebelumnya.']);
            }

            $snapshots = collect($data['snapshots'] ?? [])->values()->all();
            $ids = collect($snapshots)
                ->map(fn ($snapshot) => (int) ($snapshot['id'] ?? 0))
                ->filter(fn ($id) => $id > 0)
                ->values();

            $jadwalTarget = JadwalPelajaran::query()
                ->when($ids->isNotEmpty(), fn ($query) => $query->whereIn('id', $ids->all()))
                ->get(['id', 'tahun_ajaran', 'semester'])
                ->keyBy('id');

            foreach ($ids as $id) {
                if (! $jadwalTarget->has($id)) {
                    throw ValidationException::withMessages([
                        'batch' => 'Sebagian jadwal pada riwayat ini sudah tidak ada. Batalkan perubahan yang masih tersedia secara manual.',
                    ]);
                }
            }

            foreach ($jadwalTarget->groupBy(fn ($item) => $item->tahun_ajaran.'|'.$item->semester) as $kunci => $grup) {
                [$tahunAjaran, $semester] = explode('|', (string) $kunci, 2);
                JadwalPelajaran::query()
                    ->where('tahun_ajaran', $tahunAjaran)
                    ->where('semester', $semester)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get(['id']);
            }

            $dipulihkan = 0;

            foreach ($snapshots as $snapshot) {
                $jadwal = JadwalPelajaran::query()->find((int) ($snapshot['id'] ?? 0));

                if ($jadwal === null || ($pemilik !== null && (int) $jadwal->guru_id !== (int) $pemilik->id)) {
                    throw ValidationException::withMessages([
                        'batch' => 'Sebagian jadwal pada riwayat ini tidak dapat dipulihkan karena akses atau data tidak valid.',
                    ]);
                }

                $sesiId = (int) ($snapshot['sesi_id'] ?? 0);
                if ($sesiId > 0) {
                    $sesi = SesiJamBelajar::query()->find($sesiId);
                    if ($sesi === null || $sesi->jenis !== SesiJamBelajar::JENIS_PELAJARAN) {
                        throw ValidationException::withMessages([
                            'batch' => 'Sesi asli pada riwayat ini tidak tersedia, sehingga pembatalan tidak dapat dilakukan.',
                        ]);
                    }
                }

                $jadwal->update([
                    'sesi_id' => $sesiId > 0 ? $sesiId : null,
                    'jam_mulai' => $snapshot['jam_mulai'] ?? $jadwal->jam_mulai,
                    'jam_selesai' => $snapshot['jam_selesai'] ?? $jadwal->jam_selesai,
                ]);

                $dipulihkan++;
            }

            $data['dibatalkan'] = true;
            $log->update([
                'description' => self::RIWAYAT_PREFIX.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $this->catatRiwayat($actor, [
                'batch' => $batch,
                'aksi' => 'batalkan',
                'jumlah' => $dipulihkan,
                'snapshots' => [],
            ]);

            return ['batch' => $batch, 'jumlah' => $dipulihkan];
        });
    }

    /**
     * @return array<int, array{batch: string, aksi: string, jumlah: int, waktu: string, bisa_dibatalkan: bool}>
     */
    public function riwayat(User $guru): array
    {
        return ActivityLog::query()
            ->where('activity_type', 'jadwal_penyesuaian')
            ->where('user_id', $guru->id)
            ->where('description', 'like', self::RIWAYAT_PREFIX.'%')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function (ActivityLog $log): ?array {
                $data = $this->decodeRiwayat($log);

                if ($data === null) {
                    return null;
                }

                return [
                    'batch' => (string) ($data['batch'] ?? ''),
                    'aksi' => (string) ($data['aksi'] ?? ''),
                    'jumlah' => (int) ($data['jumlah'] ?? 0),
                    'waktu' => $log->created_at?->timezone('Asia/Jakarta')->translatedFormat('d M H:i') ?? '-',
                    'bisa_dibatalkan' => ($data['aksi'] ?? '') === 'sesuaikan'
                        && ($data['dibatalkan'] ?? false) !== true,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{batch: string, aksi: string, jumlah: int, waktu: string, actor: string, guru: string, bisa_dibatalkan: bool}>
     */
    public function riwayatSemua(int $limit = 8): array
    {
        return ActivityLog::query()
            ->where('activity_type', 'jadwal_penyesuaian')
            ->where('description', 'like', self::RIWAYAT_PREFIX.'%')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (ActivityLog $log): ?array {
                $data = $this->decodeRiwayat($log);

                if ($data === null) {
                    return null;
                }

                return [
                    'batch' => (string) ($data['batch'] ?? ''),
                    'aksi' => (string) ($data['aksi'] ?? ''),
                    'jumlah' => (int) ($data['jumlah'] ?? 0),
                    'waktu' => $log->created_at?->timezone('Asia/Jakarta')->translatedFormat('d M H:i') ?? '-',
                    'actor' => (string) ($log->user_name ?? ''),
                    'guru' => (string) ($data['guru'] ?? '-'),
                    'bisa_dibatalkan' => ($data['aksi'] ?? '') === 'sesuaikan'
                        && ($data['dibatalkan'] ?? false) !== true,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Catat tambah/ubah/hapus jadwal dengan snapshot sebelum dan sesudah.
     *
     * @param  array<string, mixed>  $sebelum
     * @param  array<string, mixed>  $sesudah
     */
    public function catatPerubahanCrud(
        User $actor,
        string $aksi,
        array $sebelum,
        array $sesudah,
        ?JadwalPelajaran $jadwal = null,
    ): void {
        $this->catatRiwayat($actor, [
            'batch' => (string) Str::uuid(),
            'aksi' => $aksi,
            'jumlah' => 1,
            'guru' => $jadwal?->guru?->name ?? ($sesudah['guru'] ?? ($sebelum['guru'] ?? '-')),
            'jadwal_id' => $jadwal?->id,
            'sebelum' => $sebelum,
            'sesudah' => $sesudah,
        ]);
    }

    /**
     * @param  array<string, mixed>  $isi
     */
    private function catatRiwayat(User $guru, array $isi): void
    {
        $this->logger->record(
            'jadwal_penyesuaian',
            self::RIWAYAT_PREFIX.json_encode($isi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $guru,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeRiwayat(ActivityLog $log): ?array
    {
        $deskripsi = (string) $log->description;

        if (! str_starts_with($deskripsi, self::RIWAYAT_PREFIX)) {
            return null;
        }

        $data = json_decode(substr($deskripsi, strlen(self::RIWAYAT_PREFIX)), true);

        return is_array($data) ? $data : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function itemData(
        JadwalPelajaran $item,
        Collection $semua,
        JamBelajarKonfigurasi $konfigurasi,
    ): array {
        $waktu = $this->waktu($item, $konfigurasi);
        $perluDisesuaikan = $waktu['label'] !== $waktu['tersimpan'];
        $jurusan = trim((string) $item->jurusan);
        $singkatan = $this->singkatanJurusan($jurusan);
        $warna = self::WARNA_JURUSAN[$singkatan] ?? ['bg' => '#EEF2F8', 'text' => '#334155'];
        $konflik = $this->konflikUntuk($item, $semua, $konfigurasi);

        return [
            'id' => (int) $item->id,
            'guru_id' => (int) $item->guru_id,
            'guru' => $item->guru?->name ?? 'Guru tidak tersedia',
            'hari' => (string) $item->hari,
            'sesi_id' => $item->sesi_id === null ? null : (int) $item->sesi_id,
            'sesi_nomor' => $this->nomorSesi($item->sesi),
            'sesi_label' => $item->sesi ? 'Sesi '.$this->nomorSesi($item->sesi) : 'Sesi —',
            'mulai' => $waktu['mulai'],
            'selesai' => $waktu['selesai'],
            'label' => $waktu['label'],
            'tersimpan' => $waktu['tersimpan'],
            'mapel' => $item->mataPelajaran?->nama ?? 'Mata Pelajaran',
            'mapel_id' => (int) $item->mata_pelajaran_id,
            'kelas' => Kelas::normalize((string) $item->kelas),
            'jurusan' => $jurusan,
            'jurusan_singkat' => $singkatan,
            'rombel_key' => $jurusan.'|'.Kelas::normalize((string) $item->kelas),
            'ruang' => trim((string) $item->ruang),
            'warna_bg' => $warna['bg'],
            'warna_teks' => $warna['text'],
            'perlu_disesuaikan' => $perluDisesuaikan,
            'bentrok' => $konflik !== [],
            'konflik' => $konflik,
            'status' => $perluDisesuaikan ? 'perlu_disesuaikan' : 'sesuai',
            'status_label' => $perluDisesuaikan ? 'Perlu disesuaikan' : 'Sesuai',
            'catatan' => $waktu['catatan'],
        ];
    }

    /**
     * Grup bentrok antar jadwal milik guru yang sama pada sesi/jam yang sama.
     * Data guru lain tidak pernah dimasukkan ke grup tampilan.
     *
     * @param  Collection<int, JadwalPelajaran>  $milik
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  Collection<int, JadwalPelajaran>  $semua
     * @return Collection<int, array<string, mixed>>
     */
    private function grupBentrok(
        Collection $milik,
        Collection $items,
        Collection $semua,
        JamBelajarKonfigurasi $konfigurasi,
    ): Collection {
        $pasangan = [];

        foreach ($milik as $a) {
            foreach ($milik as $b) {
                if ((int) $a->id >= (int) $b->id || $a->hari !== $b->hari) {
                    continue;
                }

                $waktuA = $this->waktu($a, $konfigurasi);
                $waktuB = $this->waktu($b, $konfigurasi);

                if (! $this->beririsan($waktuA, $waktuB['mulai'], $waktuB['selesai'])) {
                    continue;
                }

                $pasangan[(int) $a->id][] = (int) $b->id;
                $pasangan[(int) $b->id][] = (int) $a->id;
            }
        }

        $grup = [];
        $dikunjungi = [];

        foreach ($pasangan as $id => $tetangga) {
            if (isset($dikunjungi[$id])) {
                continue;
            }

            $anggota = [];
            $tumpukan = [$id];

            while ($tumpukan !== []) {
                $saatIni = array_pop($tumpukan);

                if (isset($dikunjungi[$saatIni])) {
                    continue;
                }

                $dikunjungi[$saatIni] = true;
                $anggota[] = $saatIni;

                foreach ($pasangan[$saatIni] ?? [] as $next) {
                    if (! isset($dikunjungi[$next])) {
                        $tumpukan[] = $next;
                    }
                }
            }

            if (count($anggota) < 2) {
                continue;
            }

            $grupItems = $items->filter(fn (array $item) => in_array($item['id'], $anggota, true))
                ->sortBy(fn (array $item) => $item['mulai'].$item['kelas'])
                ->values();
            $pertama = $grupItems->first();
            $kandidat = [];

            foreach ($grupItems->slice(1) as $item) {
                $model = $milik->firstWhere('id', $item['id']);

                if ($model === null) {
                    continue;
                }

                $kandidat[] = [
                    'id' => $item['id'],
                    'kelas' => $item['kelas'].($item['jurusan'] !== '' ? ' '.$item['jurusan'] : ''),
                    'sesi' => $this->sesiKosong($model, $milik, $semua, $konfigurasi),
                ];
            }

            $grup[] = [
                'id' => $pertama['hari'].'-'.$pertama['mulai'],
                'hari' => $pertama['hari'],
                'sesi_label' => $pertama['sesi_label'],
                'jam' => $pertama['label'],
                'jumlah' => $grupItems->count(),
                'items' => $grupItems->all(),
                'keep_id' => $pertama['id'],
                'keep_label' => $pertama['kelas'].($pertama['jurusan'] !== '' ? ' '.$pertama['jurusan'] : ''),
                'alternatif' => $kandidat,
            ];
        }

        return collect($grup)->sortBy(fn (array $grup) => $grup['hari'].$grup['jam'])->values();
    }

    /**
     * Sesi yang benar-benar kosong untuk guru, ruang, dan kelas.
     *
     * @param  Collection<int, JadwalPelajaran>  $milik
     * @param  Collection<int, JadwalPelajaran>  $semua
     * @return array<int, array{id: int, label: string, waktu: string}>
     */
    private function sesiKosong(
        JadwalPelajaran $jadwal,
        Collection $milik,
        Collection $semua,
        JamBelajarKonfigurasi $konfigurasi,
    ): array {
        $kelas = Kelas::normalize((string) $jadwal->kelas);
        $jurusan = trim((string) $jadwal->jurusan);
        $ruang = trim((string) $jadwal->ruang);

        return $this->jamBelajar->opsiPelajaran()
            ->reject(fn (array $sesi) => (int) $sesi['id'] === (int) $jadwal->sesi_id)
            ->filter(function (array $sesi) use ($jadwal, $kelas, $jurusan, $ruang, $semua, $konfigurasi): bool {
                $mulai = $sesi['mulai'];
                $selesai = $sesi['selesai'];

                foreach ($semua as $calon) {
                    if ((int) $calon->id === (int) $jadwal->id || $calon->hari !== $jadwal->hari) {
                        continue;
                    }

                    if (! $this->beririsan($this->waktu($calon, $konfigurasi), $mulai, $selesai)) {
                        continue;
                    }

                    $samaGuru = (int) $calon->guru_id === (int) $jadwal->guru_id;
                    $samaRuang = $ruang !== ''
                        && strcasecmp(trim((string) $calon->ruang), $ruang) === 0;
                    $samaKelas = Kelas::normalize((string) $calon->kelas) === $kelas
                        && $this->jurusanCocok($jurusan, trim((string) $calon->jurusan));

                    if ($samaGuru || $samaRuang || $samaKelas) {
                        return false;
                    }
                }

                return true;
            })
            ->take(3)
            ->map(fn (array $sesi) => [
                'id' => (int) $sesi['id'],
                'label' => $sesi['nama'],
                'waktu' => $sesi['mulai'].'–'.$sesi['selesai'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, JadwalPelajaran>  $semua
     * @param  array<int, array{mulai: string, selesai: string}>  $rencana
     * @param  array<int, int>  $diubah
     * @return array<int, string>
     */
    private function konflikBaru(
        Collection $semua,
        array $rencana,
        JamBelajarKonfigurasi $konfigurasi,
        array $diubah,
    ): array {
        $waktuSekarang = [];
        $waktuRencana = [];

        foreach ($semua as $jadwal) {
            $id = (int) $jadwal->id;
            $waktuSekarang[$id] = $this->waktu($jadwal, $konfigurasi);
            $waktuRencana[$id] = isset($rencana[$id])
                ? ['mulai' => $rencana[$id]['mulai'], 'selesai' => $rencana[$id]['selesai']]
                : $waktuSekarang[$id];
        }

        $sekarang = $this->pasanganKonflik($semua, $waktuSekarang);
        $rencanaKonflik = $this->pasanganKonflik($semua, $waktuRencana);
        $diubah = array_fill_keys($diubah, true);
        $hasil = [];

        foreach ($rencanaKonflik as $kunci => $detail) {
            $terlibat = collect($detail['ids'])->contains(fn (int $id) => isset($diubah[$id]));

            if ($terlibat) {
                $hasil[] = $detail['pesan'];
            }
        }

        return array_values(array_unique($hasil));
    }

    /**
     * @param  Collection<int, JadwalPelajaran>  $semua
     * @param  array<int, array{mulai: string, selesai: string}>  $waktu
     * @return array<string, array{ids: array<int, int>, pesan: string}>
     */
    private function pasanganKonflik(Collection $semua, array $waktu): array
    {
        $daftar = $semua->values();
        $hasil = [];

        for ($i = 0; $i < $daftar->count(); $i++) {
            for ($j = $i + 1; $j < $daftar->count(); $j++) {
                /** @var JadwalPelajaran $a */
                $a = $daftar->get($i);
                /** @var JadwalPelajaran $b */
                $b = $daftar->get($j);

                if ($a->hari !== $b->hari) {
                    continue;
                }

                $wa = $waktu[(int) $a->id];
                $wb = $waktu[(int) $b->id];

                if (! ($wa['mulai'] < $wb['selesai'] && $wa['selesai'] > $wb['mulai'])) {
                    continue;
                }

                $namaA = $a->mataPelajaran?->nama ?? 'Mata Pelajaran';
                $namaB = $b->mataPelajaran?->nama ?? 'Mata Pelajaran';
                $alasan = [];

                if ((int) $a->guru_id === (int) $b->guru_id) {
                    $alasan['guru'] = 'guru mengajar '.$namaA.' kelas '.$a->kelas.' dan '.$namaB.' kelas '.$b->kelas;
                }

                $ruangA = trim((string) $a->ruang);
                $ruangB = trim((string) $b->ruang);
                if ($ruangA !== '' && $ruangB !== '' && strcasecmp($ruangA, $ruangB) === 0) {
                    $alasan['ruang'] = 'ruang '.$ruangA.' dipakai dua kelas';
                }

                if (Kelas::normalize((string) $a->kelas) === Kelas::normalize((string) $b->kelas)
                    && $this->jurusanCocok(trim((string) $a->jurusan), trim((string) $b->jurusan))) {
                    $alasan['kelas'] = 'kelas '.$a->kelas.' menerima '.$namaA.' dan '.$namaB;
                }

                foreach ($alasan as $jenis => $pesan) {
                    $idA = (int) $a->id;
                    $idB = (int) $b->id;
                    $kunci = $jenis.':'.min($idA, $idB).':'.max($idA, $idB);
                    $hasil[$kunci] = ['ids' => [$idA, $idB], 'pesan' => $pesan];
                }
            }
        }

        return $hasil;
    }

    /**
     * @return array<int, string>
     */
    private function konflikUntuk(
        JadwalPelajaran $jadwal,
        Collection $semua,
        JamBelajarKonfigurasi $konfigurasi,
    ): array {
        $waktu = $this->waktu($jadwal, $konfigurasi);
        $kelas = Kelas::normalize((string) $jadwal->kelas);
        $jurusan = trim((string) $jadwal->jurusan);
        $ruang = trim((string) $jadwal->ruang);
        $hasil = [];

        foreach ($semua as $calon) {
            if ((int) $calon->id === (int) $jadwal->id || $calon->hari !== $jadwal->hari) {
                continue;
            }

            if (! $this->beririsan($this->waktu($calon, $konfigurasi), $waktu['mulai'], $waktu['selesai'])) {
                continue;
            }

            if ((int) $calon->guru_id === (int) $jadwal->guru_id) {
                $hasil[] = 'Bentrok guru dengan '.$calon->mataPelajaran?->nama.' kelas '.$calon->kelas;
            } elseif ($ruang !== '' && strcasecmp(trim((string) $calon->ruang), $ruang) === 0) {
                $hasil[] = 'Bentrok ruang '.$ruang;
            } elseif (Kelas::normalize((string) $calon->kelas) === $kelas
                && $this->jurusanCocok($jurusan, trim((string) $calon->jurusan))) {
                $hasil[] = 'Bentrok kelas '.$kelas;
            }
        }

        return array_values(array_unique($hasil));
    }

    /**
     * @param  array{mulai: string, selesai: string}  $waktu
     */
    private function beririsan(array $waktu, string $mulai, string $selesai): bool
    {
        return $waktu['mulai'] < $selesai && $waktu['selesai'] > $mulai;
    }

    private function jurusanCocok(string $jurusan, string $calon): bool
    {
        if ($jurusan === '' || $calon === '') {
            return true;
        }

        return strcasecmp($jurusan, $calon) === 0;
    }

    /**
     * @return array{mulai: string, selesai: string, label: string, tersimpan: string, catatan: array<int, string>}
     */
    private function waktu(JadwalPelajaran $jadwal, JamBelajarKonfigurasi $konfigurasi): array
    {
        return $jadwal->infoWaktu($konfigurasi);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function rombel(string $rombel): array
    {
        $bagian = explode('|', $rombel, 2);

        return [
            Kelas::normalize(trim((string) ($bagian[0] ?? ''))),
            trim((string) ($bagian[1] ?? '')),
        ];
    }

    private function nomorSesi(?SesiJamBelajar $sesi): int
    {
        if ($sesi === null) {
            return 0;
        }

        return $this->jamBelajar->nomorPelajaran($sesi);
    }

    private function singkatanJurusan(string $jurusan): string
    {
        $atas = Str::upper($jurusan);

        return match (true) {
            str_contains($atas, 'REKAYASA PERANGKAT LUNAK') || $atas === 'RPL' => 'RPL',
            str_contains($atas, 'TEKNIK KOMPUTER JARINGAN') || $atas === 'TKJ' => 'TKJ',
            default => Str::limit($atas, 12, ''),
        };
    }

    private function durasi(string $mulai, string $selesai): string
    {
        try {
            $menit = Carbon::createFromFormat('H:i', $mulai)->diffInMinutes(Carbon::createFromFormat('H:i', $selesai));
        } catch (\Throwable) {
            return '0 menit';
        }

        return $menit.' menit';
    }

    /**
     * @param  Collection<int, JadwalPelajaran>  $guruBentrok
     * @param  Collection<int, JadwalPelajaran>  $ruangBentrok
     * @param  Collection<int, JadwalPelajaran>  $kelasBentrok
     * @return array<int, array<string, mixed>>
     */
    private function detailKonflikCek(Collection $guruBentrok, Collection $ruangBentrok, Collection $kelasBentrok, JamBelajarKonfigurasi $konfigurasi): array
    {
        $detail = [];
        $tambah = function (string $jenis, JadwalPelajaran $item, string $judul, string $pesan) use (&$detail, $konfigurasi): void {
            $id = $jenis.':'.(int) $item->id;
            $waktu = $this->waktu($item, $konfigurasi);
            $detail[$id] = [
                'jenis' => $jenis,
                'judul' => $judul,
                'pesan' => $pesan,
                'jadwal' => [
                    'guru' => $item->guru?->name ?? 'Guru tidak tersedia',
                    'mapel' => $item->mataPelajaran?->nama ?? 'Mata Pelajaran',
                    'kelas' => trim((string) $item->kelas.' '.trim((string) $item->jurusan)),
                    'ruang' => trim((string) $item->ruang) !== '' ? (string) $item->ruang : '-',
                    'hari' => (string) $item->hari,
                    'jam' => $waktu['mulai'].'–'.$waktu['selesai'],
                ],
            ];
        };

        foreach ($guruBentrok as $item) {
            $tambah(
                'guru',
                $item,
                'Bentrok Guru',
                'Guru yang dipilih sudah memiliki jadwal mengajar pada hari dan waktu tersebut.',
            );
        }

        foreach ($kelasBentrok as $item) {
            $tambah(
                'kelas',
                $item,
                'Bentrok Kelas',
                'Kelas yang dipilih sudah memiliki mata pelajaran lain pada waktu yang sama.',
            );
        }

        foreach ($ruangBentrok as $item) {
            $tambah(
                'ruang',
                $item,
                'Bentrok Ruang',
                'Ruang yang dipilih sedang digunakan oleh kelas lain pada waktu tersebut.',
            );
        }

        return array_values($detail);
    }

    /**
     * @param  array<int, array<string, mixed>>  $detailKonflik
     */
    private function ringkasanPesanKonflik(array $detailKonflik): string
    {
        $jenis = collect($detailKonflik)->pluck('jenis')->unique()->values();
        if ($jenis->count() === 1) {
            return match ($jenis->first()) {
                'guru' => 'Guru yang dipilih sudah memiliki jadwal mengajar pada hari dan waktu tersebut.',
                'kelas' => 'Kelas yang dipilih sudah memiliki mata pelajaran lain pada waktu yang sama.',
                'ruang' => 'Ruang yang dipilih sedang digunakan oleh kelas lain pada waktu tersebut.',
                default => 'Ada bentrok pada jadwal yang dipilih.',
            };
        }

        return 'Ditemukan '.count($detailKonflik).' konflik jadwal. Periksa detail bentrok dan pilih rekomendasi yang tersedia.';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rekomendasiCekBentrok(
        array $periode,
        string $hari,
        string $mulai,
        string $selesai,
        ?User $guru,
        string $kelas,
        string $jurusan,
        string $ruang,
        int|array|null $kecualiId,
        JamBelajarKonfigurasi $konfigurasi,
        bool $guruBentrok,
        bool $kelasBentrok,
        bool $ruangBentrok,
    ): array {
        $rekomendasi = [];

        if ($ruangBentrok) {
            foreach ($this->ruangAlternatifTersedia($periode, $hari, $mulai, $selesai, $ruang, $kecualiId, $konfigurasi) as $ruangAlternatif) {
                if (! $this->slotAman($periode, $hari, $mulai, $selesai, $guru, $kelas, $jurusan, $ruangAlternatif, $kecualiId, $konfigurasi)) {
                    continue;
                }

                $rekomendasi[] = [
                    'jenis' => 'ruang',
                    'label' => 'Gunakan ruang '.$ruangAlternatif,
                    'deskripsi' => 'Ruang tersedia pada sesi yang sama tanpa bentrok guru atau kelas.',
                    'nilai' => ['ruang' => $ruangAlternatif],
                ];

                if (count($rekomendasi) >= 3) {
                    return $rekomendasi;
                }
            }
        }

        foreach ($this->sesiAlternatifTersedia($periode, $hari, $mulai, $selesai, $guru, $kelas, $jurusan, $ruang, $kecualiId, $konfigurasi) as $sesiAlternatif) {
            $rekomendasi[] = [
                'jenis' => 'sesi',
                'label' => $sesiAlternatif['label'],
                'deskripsi' => $sesiAlternatif['deskripsi'],
                'nilai' => ['sesi_id' => $sesiAlternatif['sesi_id']],
            ];

            if (count($rekomendasi) >= 5) {
                break;
            }
        }

        return array_values($rekomendasi);
    }

    /**
     * @return array<int, string>
     */
    private function ruangAlternatifTersedia(array $periode, string $hari, string $mulai, string $selesai, string $ruangSaatIni, int|array|null $kecualiId, JamBelajarKonfigurasi $konfigurasi): array
    {
        $query = JadwalPelajaran::query()
            ->where('tahun_ajaran', $periode['tahun_ajaran'] ?? '')
            ->where('semester', $periode['semester'] ?? '')
            ->whereNotNull('ruang')
            ->where('ruang', '!=', '')
            ->when($kecualiId !== null, fn ($query) => $query->whereNotIn('id', is_array($kecualiId) ? $kecualiId : [$kecualiId]));

        $ruangKandidat = $query->pluck('ruang')
            ->map(fn ($ruang) => trim((string) $ruang))
            ->filter()
            ->unique()
            ->reject(fn ($ruang) => strcasecmp($ruang, $ruangSaatIni) === 0)
            ->values();

        $ruangTerpakai = JadwalPelajaran::query()
            ->where('tahun_ajaran', $periode['tahun_ajaran'] ?? '')
            ->where('semester', $periode['semester'] ?? '')
            ->where('hari', $hari)
            ->when($kecualiId !== null, fn ($query) => $query->whereNotIn('id', is_array($kecualiId) ? $kecualiId : [$kecualiId]))
            ->get()
            ->filter(fn (JadwalPelajaran $item) => $this->beririsan($this->waktu($item, $konfigurasi), $mulai, $selesai))
            ->map(fn (JadwalPelajaran $item) => Str::lower(trim((string) $item->ruang)))
            ->filter()
            ->unique()
            ->values();

        return $ruangKandidat
            ->reject(fn ($ruang) => $ruangTerpakai->contains(Str::lower($ruang)))
            ->take(5)
            ->all();
    }

    /**
     * @return array<int, array{sesi_id: int, label: string, deskripsi: string}>
     */
    private function sesiAlternatifTersedia(
        array $periode,
        string $hari,
        string $mulai,
        string $selesai,
        ?User $guru,
        string $kelas,
        string $jurusan,
        string $ruang,
        int|array|null $kecualiId,
        JamBelajarKonfigurasi $konfigurasi,
    ): array {
        return $this->jamBelajar->opsiPelajaran()
            ->reject(fn (array $sesi) => $sesi['mulai'] === $mulai && $sesi['selesai'] === $selesai)
            ->filter(fn (array $sesi) => $this->slotAman($periode, $hari, $sesi['mulai'], $sesi['selesai'], $guru, $kelas, $jurusan, $ruang, $kecualiId, $konfigurasi))
            ->take(4)
            ->map(fn (array $sesi) => [
                'sesi_id' => (int) $sesi['id'],
                'label' => $sesi['nama'].' · '.$sesi['mulai'].'–'.$sesi['selesai'],
                'deskripsi' => 'Pindahkan ke sesi ini. Guru, kelas, dan ruang tersedia.',
            ])
            ->values()
            ->all();
    }

    private function slotAman(
        array $periode,
        string $hari,
        string $mulai,
        string $selesai,
        ?User $guru,
        string $kelas,
        string $jurusan,
        string $ruang,
        int|array|null $kecualiId,
        JamBelajarKonfigurasi $konfigurasi,
    ): bool {
        $calon = JadwalPelajaran::query()
            ->with(['mataPelajaran', 'sesi', 'guru'])
            ->where('tahun_ajaran', $periode['tahun_ajaran'] ?? '')
            ->where('semester', $periode['semester'] ?? '')
            ->where('hari', $hari)
            ->when($kecualiId !== null, fn ($query) => $query->whereNotIn('id', is_array($kecualiId) ? $kecualiId : [$kecualiId]))
            ->get()
            ->filter(fn (JadwalPelajaran $item) => $this->beririsan($this->waktu($item, $konfigurasi), $mulai, $selesai));

        foreach ($calon as $item) {
            if ($guru !== null && (int) $item->guru_id === (int) $guru->id) {
                return false;
            }

            $ruangItem = trim((string) $item->ruang);
            if ($ruang !== '' && $ruangItem !== '' && strcasecmp($ruangItem, $ruang) === 0) {
                return false;
            }

            if (Str::upper(Kelas::normalize((string) $item->kelas)) === Str::upper($kelas)) {
                $jurusanItem = trim((string) $item->jurusan);
                if ($jurusan === '' || $jurusanItem === '' || $jurusanItem === $jurusan) {
                    return false;
                }
            }
        }

        return true;
    }

    private function pesanBentrok(?JadwalPelajaran $bentrok, bool $guru): string
    {
        if ($bentrok === null) {
            return 'Ada bentrok pada jadwal yang dipilih.';
        }

        if ($guru) {
            return 'Guru sudah mengajar '.$bentrok->mataPelajaran?->nama.' kelas '.$bentrok->kelas.' pada jam ini.';
        }

        if (filled($bentrok->ruang)) {
            return 'Ruang '.$bentrok->ruang.' sedang dipakai pada jam ini.';
        }

        return 'Kelas '.$bentrok->kelas.' sudah memiliki pelajaran pada jam ini.';
    }
}
