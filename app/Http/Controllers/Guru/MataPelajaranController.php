<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\User;
use App\Services\XlsxExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MataPelajaranController extends Controller
{
    public function index(Request $request): View
    {
        $guru = $request->user();
        if (! $guru instanceof User || $guru->role !== 'guru') {
            abort(403);
        }

        $rows = $this->daftar($guru);
        $mapelSaya = $rows->firstWhere('diampu_saya', true);

        $stats = [
            [
                'key' => 'total',
                'label' => 'Total Mapel',
                'value' => $rows->count(),
                'icon' => 'lucide-book-open',
            ],
            [
                'key' => 'diampu_saya',
                'label' => 'Mapel Saya',
                'value' => $mapelSaya ? ($mapelSaya['kode'] ? $mapelSaya['kode'] : '1 Mapel') : 0,
                'subtitle' => $mapelSaya ? $mapelSaya['nama'] : 'Belum ditugaskan',
                'icon' => 'lucide-book-marked',
            ],
            [
                'key' => 'dipakai',
                'label' => 'Mapel Aktif',
                'value' => $rows->where('dipakai', true)->count(),
                'icon' => 'lucide-check-circle',
            ],
            [
                'key' => 'pengampu',
                'label' => 'Total Pengampu',
                'value' => (int) $rows->sum('jumlah_pengampu'),
                'icon' => 'lucide-users',
            ],
        ];

        $statTone = [
            'total' => ['bg-blue-100 text-primary-60', '#1E86FF'],
            'diampu_saya' => ['bg-indigo-100 text-indigo-600', '#4F46E5'],
            'dipakai' => ['bg-emerald-100 text-emerald-600', '#059669'],
            'pengampu' => ['bg-amber-100 text-amber-600', '#D97706'],
        ];

        return view('dashboard.guru.mata-pelajaran.index', [
            'stats' => $stats,
            'statTone' => $statTone,
            'mapelSaya' => $mapelSaya,
            'mapelData' => [
                'mapel' => $rows,
                'guruMapel' => $guru->mata_pelajaran,
            ],
        ]);
    }

    public function export(Request $request): Response
    {
        $guru = $request->user();
        if (! $guru instanceof User || $guru->role !== 'guru') {
            abort(403);
        }

        $rows = $this->daftar($guru)
            ->filter(fn (array $m) => $this->lolosFilter($m, $request))
            ->values();

        $headers = ['No', 'Kode', 'Mata Pelajaran', 'Status Diampu', 'Guru Pengampu', 'Catatan Nilai'];

        $baris = [];
        foreach ($rows as $i => $m) {
            $pengampuStr = ! empty($m['pengampu_list'])
                ? implode(', ', array_column($m['pengampu_list'], 'name'))
                : '-';

            $statusStr = $m['diampu_saya']
                ? 'Diampu Anda'
                : ($m['dipakai'] ? 'Aktif' : 'Belum Ada Pengampu');

            $nilaiStr = $m['total_nilai'] > 0
                ? "{$m['total_nilai']} nilai ({$m['total_siswa']} siswa)"
                : 'Belum ada nilai';

            $baris[] = [
                $i + 1,
                $m['kode'] ?? '-',
                $m['nama'],
                $statusStr,
                $pengampuStr,
                $nilaiStr,
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

        if ($status === 'diampu_saya' && ! $m['diampu_saya']) {
            return false;
        }

        if ($status === 'dipakai' && ! $m['dipakai']) {
            return false;
        }

        if ($status === 'kosong' && $m['dipakai']) {
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

    protected function daftar(?User $guru): Collection
    {
        $pengampu = $this->pengampuGrouped();
        $nilaiStats = $this->nilaiStats();
        $namaMapelGuru = $guru ? trim((string) $guru->mata_pelajaran) : '';

        return MataPelajaran::query()
            ->orderBy('nama')
            ->get()
            ->map(function (MataPelajaran $mapel) use ($pengampu, $nilaiStats, $namaMapelGuru, $guru) {
                $guruList = $pengampu->get($mapel->nama, collect());
                $stat = $nilaiStats->get($mapel->id);

                $diampuSaya = $namaMapelGuru !== '' && strcasecmp($namaMapelGuru, trim($mapel->nama)) === 0;

                return [
                    'id' => $mapel->id,
                    'kode' => $mapel->kode,
                    'nama' => $mapel->nama,
                    'jumlah_pengampu' => $guruList->count(),
                    'dipakai' => $guruList->isNotEmpty(),
                    'diampu_saya' => $diampuSaya,
                    'pengampu_list' => $guruList->map(fn (User $u) => [
                        'id' => $u->id,
                        'name' => $u->name,
                        'nip' => $u->nip ?? '-',
                        'email' => $u->email,
                        'is_me' => $guru && $u->id === $guru->id,
                    ])->values()->all(),
                    'total_nilai' => (int) ($stat->total_nilai ?? 0),
                    'rata_rata' => isset($stat->rata_rata) ? round((float) $stat->rata_rata, 1) : null,
                    'total_siswa' => (int) ($stat->total_siswa ?? 0),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<string, Collection<int, User>>
     */
    protected function pengampuGrouped(): Collection
    {
        return User::query()
            ->where('role', 'guru')
            ->whereNotNull('mata_pelajaran')
            ->where('mata_pelajaran', '!=', '')
            ->get(['id', 'name', 'nip', 'email', 'mata_pelajaran'])
            ->groupBy(fn (User $user) => trim((string) $user->mata_pelajaran));
    }

    /**
     * @return Collection<int, object>
     */
    protected function nilaiStats(): Collection
    {
        return Nilai::query()
            ->selectRaw('mata_pelajaran_id, count(*) as total_nilai, avg(nilai) as rata_rata, count(distinct user_id) as total_siswa')
            ->groupBy('mata_pelajaran_id')
            ->get()
            ->keyBy('mata_pelajaran_id');
    }
}
