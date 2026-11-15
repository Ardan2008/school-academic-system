<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\JadwalPelajaran;
use App\Models\User;
use App\Services\GuruAkademik;
use App\Support\Kelas;
use App\Support\StrukturKelas;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Jadwal mengajar Guru, dibaca langsung dari tabel jadwal. Hanya baris yang
 * mencantumkan guru ini sebagai pengampu yang ditampilkan.
 */
class JadwalController extends Controller
{
    public function __construct(private readonly GuruAkademik $akademik) {}

    public function index(Request $request): View
    {
        $guru = $request->user();

        if (! $guru instanceof User || $guru->role !== 'guru') {
            abort(403);
        }

        $mapelIds = $this->akademik->mapelDiampu($guru)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $hari = trim((string) $request->query('hari', ''));
        $hari = in_array($hari, JadwalPelajaran::HARI, true) ? $hari : null;

        $jadwal = JadwalPelajaran::query()
            ->with('mataPelajaran')
            ->where('guru_id', $guru->id)
            ->when($hari !== null, fn ($query) => $query->where('hari', $hari))
            ->get()
            ->sortBy(fn (JadwalPelajaran $item) => array_search($item->hari, JadwalPelajaran::HARI, true).'|'.$item->jam_mulai)
            ->values();

        $perHari = collect(JadwalPelajaran::HARI)
            ->mapWithKeys(fn (string $nama) => [
                $nama => $jadwal->where('hari', $nama)->sortBy('jam_mulai')->values(),
            ]);

        $kelas = $jadwal
            ->map(fn (JadwalPelajaran $item) => [
                'kelas' => Kelas::normalize((string) $item->kelas),
                'jurusan' => trim((string) $item->jurusan),
            ])
            ->unique(fn (array $item) => $item['jurusan'].'|'.$item['kelas'])
            ->sort(fn (array $a, array $b) => strcasecmp($a['jurusan'], $b['jurusan'])
                ?: StrukturKelas::bandingkanKelas($a['kelas'], $b['kelas']))
            ->values();

        $hariIni = JadwalPelajaran::hariIni();

        return view('dashboard.guru.jadwal-pelajaran.index', [
            'jadwal' => $jadwal,
            'perHari' => $perHari,
            'kelas' => $kelas,
            'mapelIds' => $mapelIds,
            'hari' => $hari,
            'hariIni' => $hariIni,
            'stats' => [
                [
                    'key' => 'total',
                    'label' => 'Total Jadwal',
                    'value' => $jadwal->count(),
                    'icon' => 'lucide-calendar-days',
                ],
                [
                    'key' => 'kelas',
                    'label' => 'Kelas Diampu',
                    'value' => $kelas->count(),
                    'icon' => 'lucide-school',
                ],
                [
                    'key' => 'mapel',
                    'label' => 'Mata Pelajaran',
                    'value' => $jadwal->pluck('mata_pelajaran_id')->unique()->count(),
                    'icon' => 'lucide-book-open',
                ],
                [
                    'key' => 'hari_ini',
                    'label' => 'Jadwal Hari Ini',
                    'value' => $hariIni !== null ? $jadwal->where('hari', $hariIni)->count() : 0,
                    'icon' => 'lucide-clock',
                ],
            ],
        ]);
    }
}
