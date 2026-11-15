<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\MataPelajaran;
use App\Models\Notification;
use App\Models\User;
use App\Services\InfoSiswa;
use App\Services\NotifikasiAkademikService;
use App\Services\StatistikNilai;
use App\Support\Periode;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly StatistikNilai $statistik,
        private readonly InfoSiswa $infoSiswa,
        private readonly NotifikasiAkademikService $notifikasiAkademik,
    ) {}

    public function index()
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $this->viewForRole($user->role);
    }

    public function admin()
    {
        $totalSiswa = User::where('role', 'siswa')->count();
        $siswaAktif = User::where('role', 'siswa')->where('is_active', true)->count();
        $totalGuru = User::where('role', 'guru')->count();
        $guruAktif = User::where('role', 'guru')->where('is_active', true)->count();
        $totalMapel = MataPelajaran::count();

        $stats = [
            [
                'label' => 'Total Siswa',
                'value' => $totalSiswa,
                'desc' => 'Seluruh peserta didik',
                'icon' => 'lucide-graduation-cap',
                'bg' => 'bg-primary-10',
                'text' => 'text-primary-70',
                'accent' => '#1E86FF',
            ],
            [
                'label' => 'Siswa Aktif',
                'value' => $siswaAktif,
                'desc' => $totalSiswa > 0
                    ? round($siswaAktif / max($totalSiswa, 1) * 100).'% dari total siswa'
                    : 'Belum ada siswa',
                'icon' => 'lucide-user-check',
                'bg' => 'bg-emerald-50',
                'text' => 'text-emerald-600',
                'accent' => '#059669',
            ],
            [
                'label' => 'Total Guru',
                'value' => $totalGuru,
                'desc' => $guruAktif.' guru aktif',
                'icon' => 'lucide-users',
                'bg' => 'bg-amber-50',
                'text' => 'text-amber-600',
                'accent' => '#D97706',
            ],
            [
                'label' => 'Mata Pelajaran',
                'value' => $totalMapel,
                'desc' => 'Mapel terdaftar',
                'icon' => 'lucide-book-open',
                'bg' => 'bg-rose-50',
                'text' => 'text-rose-600',
                'accent' => '#E11D48',
            ],
        ];

        $recentActivities = ActivityLog::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'judul' => $log->label(),
                'detail' => $log->description,
                'pengguna' => $log->user_name ?? 'Pengguna terhapus',
                'role' => $log->role,
                'role_label' => $log->roleLabel(),
                'waktu' => $log->created_at,
            ]);

        // Grafik & rekap nilai dihitung dari tabel nilai, bukan angka contoh.
        $nilaiKelas = $this->statistik->rataPerKelasPerPeriode();
        $kelasTertinggi = $this->statistik->kelasTertinggi();

        return view('dashboard.admin.index', compact('stats', 'recentActivities', 'nilaiKelas', 'kelasTertinggi'));
    }

    public function guru(): View
    {
        $guru = Auth::user();

        if (! $guru instanceof User || $guru->role !== 'guru') {
            abort(403);
        }

        $this->notifikasiAkademik->sinkronkanPengingat($guru);

        $periode = Periode::periodeSekarang();
        $tahunAjaran = $periode['tahun_ajaran'];
        $semester = $periode['semester'];

        // Lewat StatistikNilai supaya angkanya sama dengan Input Nilai, Rekap, dan Laporan;
        // semua dibatasi ke mapel yang diampu akun guru ini, tanpa bocor data guru lain.
        $mapelIds = $this->statistik->mapelIdsDiampu($guru);
        $kelasDiampu = $this->statistik->kelasDiampu($guru);
        $siswa = $this->statistik->siswaDiKelas($kelasDiampu);

        return view('dashboard.guru.index', [
            'periode' => $periode,
            'totalKelas' => $kelasDiampu->count(),
            'totalSiswa' => $siswa->count(),
            'siswaAktif' => $siswa->where('status', User::STATUS_AKTIF)->count(),
            'siswa' => $siswa,
            // Berhalaman (5 baris) supaya dashboard tidak memanjang ke bawah.
            'siswaPaginasi' => $this->statistik->bagiHalaman($siswa, 5, 'siswa_page'),
            'kelasDiampu' => $kelasDiampu,
            'mapelDiampu' => $this->statistik->mapelDiampu($guru),

            'ringkasan' => $this->statistik->ringkasan($tahunAjaran, $semester, null, null, $mapelIds),

            'perkembangan' => $this->statistik->perkembanganPeriode(null, null, $mapelIds),

            'kelasTertinggi' => $this->statistik->bagiHalaman($this->statistik->kelasTertinggi($tahunAjaran, $semester, $mapelIds), 5, 'kelas_tertinggi_page'),
            'perKelas' => $this->statistik->rekapPerKelasPaginated($tahunAjaran, $semester, null, null, $mapelIds, 5, 'kelas_page'),

            // Siswa belum tuntas, diurutkan dari rata-rata terendah; berhalaman
            // (5 baris) agar footer & paginasi tetap muncul walau isinya kurang dari lima.
            'perluPerhatian' => $this->statistik->bagiHalaman(
                $this->statistik
                    ->rekapPerSiswaSemua($tahunAjaran, $semester, null, null, $mapelIds)
                    ->filter(fn (array $row) => $row['belum_tuntas'] > 0)
                    ->sortBy('rata_rata')
                    ->values(),
                5,
                'perlu_perhatian_page'
            ),
        ]);
    }

    public function siswa(): View
    {
        $siswa = Auth::user();

        if (! $siswa instanceof User || $siswa->role !== 'siswa') {
            abort(403);
        }

        $periode = Periode::periodeSekarang();

        $this->notifikasiAkademik->sinkronkanPengingat($siswa);

        $tugasBelum = $this->infoSiswa->tugasBelumDikumpulkan($siswa);
        $aktivitas = $this->infoSiswa->aktivitasTerbaru($siswa);

        // Panel "Notifikasi Terbaru" membaca tabel notifications dengan
        // batasan yang sama dengan lonceng header (user_id + role user),
        // jadi tidak ada sumber tampilan lain yang bisa berbeda isi.
        $notifikasi = Notification::query()
            ->where('user_id', $siswa->id)
            ->where(function ($q) use ($siswa) {
                $q->where('role', $siswa->role)->orWhereNull('role');
            })
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($n) => [
                'judul' => $n->title,
                'detail' => $n->message,
                'waktu' => $n->created_at,
                'icon' => $n->icon ?? 'bell',
            ]);

        return view('dashboard.siswa.index', [
            'periode' => $periode,
            'ringkasan' => $this->statistik->ringkasanSiswa($siswa, $periode['tahun_ajaran'], $periode['semester']),
            'perkembangan' => $this->statistik->perkembanganSiswa($siswa),
            'jadwalHariIni' => $this->infoSiswa->jadwalHariIni($siswa),
            'tugasBelumDikumpulkan' => $tugasBelum,
            'tugasPaginasi' => $this->statistik->bagiHalaman($tugasBelum, 5, 'tugas_page'),
            'aktivitasTerbaru' => $aktivitas,
            'aktivitasPaginasi' => $this->statistik->bagiHalaman($aktivitas, 5, 'aktivitas_page'),
            'notifikasi' => $notifikasi,
        ]);
    }

    protected function viewForRole(string $role)
    {
        return match ($role) {
            'admin' => $this->admin(),
            'guru' => $this->guru(),
            default => $this->siswa(),
        };
    }
}
