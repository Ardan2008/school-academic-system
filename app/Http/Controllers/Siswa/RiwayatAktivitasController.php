<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RiwayatAktivitasSiswa;
use App\Services\StatistikNilai;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Riwayat seluruh aktivitas akademik siswa yang login.
 *
 * Sumber data dan batas aksesnya sama dengan halaman akademik lain: hanya
 * rombel/akun siswa ini. Filter dan paginasi dikerjakan di server agar URL
 * dapat dibagikan dan tetap bisa dibuka ulang.
 */
class RiwayatAktivitasController extends Controller
{
    private const PER_HALAMAN = 10;

    public function __construct(
        private readonly RiwayatAktivitasSiswa $riwayat,
        private readonly StatistikNilai $statistik,
    ) {}

    public function index(Request $request): View
    {
        $siswa = $request->user();

        if (! $siswa instanceof User || $siswa->role !== 'siswa') {
            abort(403);
        }

        $kategori = trim((string) $request->query('kategori', ''));

        if (! array_key_exists($kategori, RiwayatAktivitasSiswa::KATEGORI)) {
            $kategori = '';
        }

        $search = trim((string) $request->query('search', ''));

        $dari = $this->tanggalValid((string) $request->query('dari', ''));
        $sampai = $this->tanggalValid((string) $request->query('sampai', ''));

        if ($dari !== null && $sampai !== null && $dari > $sampai) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        $semua = $this->riwayat->kumpulkan($siswa);
        $terfilter = $this->riwayat->saring($semua, $kategori, $search, $dari, $sampai);

        return view('dashboard.siswa.riwayat-aktivitas.index', [
            'siswa' => $siswa,
            'aktivitas' => $this->statistik->bagiHalaman($terfilter, self::PER_HALAMAN),
            'kategori' => $kategori,
            'kategoriOptions' => RiwayatAktivitasSiswa::KATEGORI,
            'search' => $search,
            'dari' => $dari,
            'sampai' => $sampai,
            'adaFilter' => $kategori !== '' || $search !== '' || $dari !== null || $sampai !== null,
            'totalSemua' => $semua->count(),
        ]);
    }

    /**
     * Hanya tanggal ISO yang sah yang diterima; nilai lain dianggap tidak
     * memfilter supaya parameter asing tidak membuat halaman error.
     */
    protected function tanggalValid(string $tanggal): ?string
    {
        $tanggal = trim($tanggal);

        if ($tanggal === '') {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $tanggal);

        if (! $parsed || $parsed->format('Y-m-d') !== $tanggal) {
            return null;
        }

        return $tanggal;
    }
}
