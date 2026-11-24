<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\User;
use App\Services\GuruAkademik;
use App\Services\PenugasanMengajarService;
use App\Services\XlsxExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MataPelajaranController extends Controller
{
    public function __construct(
        private readonly PenugasanMengajarService $penugasan,
        private readonly GuruAkademik $akademik,
    ) {}

    public function index(Request $request): View
    {
        $guru = $this->guru($request);

        $rows = $this->daftar($guru);
        $totalSiswa = $rows->pluck('total_siswa_relevan')->sum();

        $stats = [
            [
                'key' => 'total',
                'label' => 'Total Mapel',
                'value' => $rows->count(),
                'icon' => 'lucide-book-open',
            ],
            [
                'key' => 'aktif',
                'label' => 'Mapel Aktif',
                'value' => $rows->where('aktif', true)->count(),
                'icon' => 'lucide-check-circle',
            ],
            [
                'key' => 'siswa',
                'label' => 'Total Siswa',
                'value' => (int) $totalSiswa,
                'icon' => 'lucide-users',
            ],
            [
                'key' => 'nilai',
                'label' => 'Catatan Nilai',
                'value' => (int) $rows->sum('total_nilai'),
                'icon' => 'lucide-file-spreadsheet',
            ],
        ];

        $statTone = [
            'total' => ['bg-blue-100 text-primary-60', '#1E86FF'],
            'aktif' => ['bg-emerald-100 text-emerald-600', '#059669'],
            'siswa' => ['bg-indigo-100 text-indigo-600', '#4F46E5'],
            'nilai' => ['bg-amber-100 text-amber-600', '#D97706'],
        ];

        return view('dashboard.guru.mata-pelajaran.index', [
            'stats' => $stats,
            'statTone' => $statTone,
            'mapelData' => [
                'mapel' => $rows,
            ],
        ]);
    }

    public function export(Request $request): Response
    {
        $guru = $this->guru($request);

        $rows = $this->daftar($guru)
            ->filter(fn (array $m) => $this->lolosFilter($m, $request))
            ->values();

        $headers = ['No', 'Kode', 'Mata Pelajaran', 'Status', 'Siswa Relevan', 'Catatan Nilai', 'Rata-Rata'];

        $baris = [];
        foreach ($rows as $i => $m) {
            $nilaiStr = $m['total_nilai'] > 0
                ? "{$m['total_nilai']} nilai ({$m['total_siswa']} siswa)"
                : 'Belum ada nilai';

            $baris[] = [
                $i + 1,
                $m['kode'] ?? '-',
                $m['nama'],
                $m['aktif'] ? 'Aktif' : 'Nonaktif',
                (int) ($m['total_siswa_relevan'] ?? 0),
                $nilaiStr,
                $m['rata_rata'] ?? '-',
            ];
        }

        $response = app(XlsxExportService::class)->download(
            'data-mata-pelajaran-guru-'.now()->format('Ymd-His').'.xlsx',
            'Data Mata Pelajaran',
            $headers,
            $baris,
            [
                'subjudul' => 'Diunduh '.now()->format('d M Y H:i').' • '.$rows->count().' mata pelajaran',
                'text_columns' => [2],
            ]
        );

        $response->headers->set('Cache-Control', 'no-store, no-cache');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Total-Mapel', (string) $rows->count());

        return $response;
    }

    protected function lolosFilter(array $m, Request $request): bool
    {
        $status = trim((string) $request->input('status'));
        $q = trim((string) $request->input('q'));

        if ($status === 'aktif' && ! $m['aktif']) {
            return false;
        }

        if ($status === 'nonaktif' && $m['aktif']) {
            return false;
        }

        if ($q !== '') {
            $qLower = mb_strtolower($q);
            $cocok = str_contains(mb_strtolower($m['nama']), $qLower)
                || str_contains(mb_strtolower((string) ($m['kode'] ?? '')), $qLower);

            if (! $cocok) {
                return false;
            }
        }

        return true;
    }

    protected function daftar(User $guru): Collection
    {
        $mapelDiampu = $this->penugasan->mapelDiampu($guru);
        $mapelIds = $mapelDiampu->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($mapelIds === []) {
            return collect();
        }

        $nilaiStats = $this->nilaiStats($mapelIds);
        $pengampu = $this->pengampuByMapelId($mapelIds);

        return $mapelDiampu
            ->map(function (MataPelajaran $mapel) use ($nilaiStats, $pengampu, $guru) {
                $stat = $nilaiStats->get($mapel->id);
                $siswaRelevan = $this->siswaRelevanCount($guru, (int) $mapel->id);
                $guruList = $pengampu->get((int) $mapel->id, collect());
                $diampuSaya = $guruList->contains(fn (User $u) => $u->id === $guru->id);

                return [
                    'id' => $mapel->id,
                    'kode' => $mapel->kode,
                    'nama' => $mapel->nama,
                    'aktif' => (bool) $mapel->aktif,
                    'jumlah_pengampu' => $guruList->count(),
                    'dipakai' => $guruList->isNotEmpty(),
                    'diampu_saya' => $diampuSaya,
                    'pengampu_list' => $guruList->map(fn (User $u) => [
                        'id' => $u->id,
                        'name' => $u->name,
                        'nip' => $u->nip ?? '-',
                        'email' => $u->email,
                        'is_me' => $u->id === $guru->id,
                    ])->values()->all(),
                    'total_nilai' => (int) ($stat->total_nilai ?? 0),
                    'rata_rata' => isset($stat->rata_rata) ? round((float) $stat->rata_rata, 1) : null,
                    'total_siswa' => (int) ($stat->total_siswa ?? 0),
                    'total_siswa_relevan' => $siswaRelevan,
                ];
            })
            ->values();
    }

    /**
     * @param  array<int>  $mapelIds
     * @return Collection<int, Collection<int, User>>
     */
    protected function pengampuByMapelId(array $mapelIds): Collection
    {
        $grup = collect();

        User::query()
            ->where('role', 'guru')
            ->get(['id', 'name', 'nip', 'email', 'mata_pelajaran', 'penugasan_diatur'])
            ->each(function (User $guru) use ($grup, $mapelIds): void {
                $this->penugasan->mapelDiampu($guru)
                    ->filter(fn (MataPelajaran $mapel) => in_array((int) $mapel->id, $mapelIds, true))
                    ->each(function (MataPelajaran $mapel) use ($grup, $guru): void {
                        $id = (int) $mapel->id;
                        $grup->put($id, $grup->get($id, collect())->push($guru));
                    });
            });

        return $grup->map(fn (Collection $gurus) => $gurus->unique('id')->values());
    }

    /**
     * @param  array<int>  $mapelIds
     * @return Collection<int, object>
     */
    protected function nilaiStats(array $mapelIds): Collection
    {
        return Nilai::query()
            ->whereIn('mata_pelajaran_id', $mapelIds)
            ->selectRaw('mata_pelajaran_id, count(*) as total_nilai, avg(nilai) as rata_rata, count(distinct user_id) as total_siswa')
            ->groupBy('mata_pelajaran_id')
            ->get()
            ->keyBy('mata_pelajaran_id');
    }

    protected function siswaRelevanCount(User $guru, int $mapelId): int
    {
        $rombels = $this->akademik->pasanganUntukMapel($guru, $mapelId);

        if ($rombels->isEmpty()) {
            return 0;
        }

        return User::query()
            ->where('role', 'siswa')
            ->where('status', User::STATUS_AKTIF)
            ->get(['kelas', 'jurusan'])
            ->filter(fn (User $siswa) => $this->akademik->cocokRombel($siswa, $rombels->map(fn (array $r) => [
                'kelas' => $r['kelas'],
                'jurusan' => $r['jurusan'],
            ])->values()))
            ->count();
    }

    protected function guru(Request $request): User
    {
        $guru = $request->user();

        if (! $guru instanceof User || $guru->role !== 'guru') {
            abort(403);
        }

        return $guru;
    }
}
