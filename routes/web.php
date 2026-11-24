<?php

use App\Http\Controllers\Admin\AkunController;
use App\Http\Controllers\Admin\BackupDatabaseController;
use App\Http\Controllers\Admin\GuruController;
use App\Http\Controllers\Admin\JadwalController as AdminJadwalController;
use App\Http\Controllers\Admin\JamBelajarController;
use App\Http\Controllers\Admin\JurusanController;
use App\Http\Controllers\Admin\KelasController;
use App\Http\Controllers\Admin\KendalaAkunController;
use App\Http\Controllers\Admin\LaporanAkademikController;
use App\Http\Controllers\Admin\LogAktivitasController;
use App\Http\Controllers\Admin\MataPelajaranController;
use App\Http\Controllers\Admin\PengajuanResetPasswordController;
use App\Http\Controllers\Admin\SiswaController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordSetupController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Dashboard\GlobalSearchController;
use App\Http\Controllers\Dashboard\NotificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Guru\InputNilaiController;
use App\Http\Controllers\Guru\JadwalController;
use App\Http\Controllers\Guru\LaporanController;
use App\Http\Controllers\Guru\RekapNilaiController;
use App\Http\Controllers\Guru\TugasMateriController;
use App\Http\Controllers\Landing\HomeController;
use App\Http\Controllers\Landing\ItHelpController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Siswa\AkademikController;
use App\Http\Controllers\Siswa\RiwayatAktivitasController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('beranda');

// Permintaan bantuan dari tombol "Hubungi Tim IT Sekolah" (section FAQ).
// Terbuka untuk pengunjung yang belum login, jadi tidak memakai middleware 'guest'.
Route::post('/hubungi-tim-it', [ItHelpController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('landing.hubungi-tim-it');

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1')->name('login.store');

// Pengajuan atur ulang password (hanya Guru & Siswa).
// Sengaja tanpa middleware 'guest': halaman login tetap bisa dibuka saat sudah
// login (untuk ganti role), sehingga form lupa password harus tetap bisa
// dikirim. Bila dibiarkan 'guest', request akan dialihkan ke dashboard dan
// frontend menerima HTML sehingga muncul pesan gagal yang menyesatkan.
Route::post('/lupa-password', [ForgotPasswordController::class, 'store'])
    ->middleware(['throttle:5,1'])
    ->name('auth.buat-password');

// Halaman atur ulang password lewat tautan bertoken.
// Sengaja tidak memakai middleware 'guest': Kalau pengajuan dibuat sebelum
// sesi lama berakhir, pengguna yang masih login tetap bisa menyelesaikan reset
// (sesi lama otomatis di-logout setelah password diganti).
Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])
    ->name('password.reset');

Route::post('/reset-password/{token}', [ResetPasswordController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('password.reset.store');

Route::middleware(['guard', 'auth', 'no-cache'])->group(function () {
    // Wajib diubah pada login pertama.
    Route::get('/ubah-password', [PasswordSetupController::class, 'showSetupForm'])->name('password.setup');
    Route::post('/ubah-password', [PasswordSetupController::class, 'store'])->name('password.setup.store');

    Route::get('/profil', function () {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        return match ($user->role) {
            'admin' => view('dashboard.admin.profile.index'),
            'guru' => view('dashboard.guru.profile.index'),
            default => view('dashboard.siswa.profile.index'),
        };
    })->name('profile');
    Route::post('/dashboard/{role}/profil/password', [ProfileController::class, 'changePassword'])
        ->where('role', 'admin|guru|siswa')
        ->name('profile.password');

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Notifikasi lonceng. Segment {role} wajib ada supaya ResolveRoleGuard
    // memilih guard sesuai dashboard yang sedang dibuka; tanpa prefix ini,
    // guard `web` (role terakhir login) menang dan notifikasi bocor ke semua role.
    Route::middleware(['guard', 'auth'])
        ->prefix('/dashboard/{role}/notifications')
        ->group(function () {
            Route::get('/', [NotificationController::class, 'index'])
                ->where('role', 'admin|guru|siswa')
                ->name('dashboard.notifications.index');
            Route::get('/count', [NotificationController::class, 'count'])
                ->where('role', 'admin|guru|siswa')
                ->name('dashboard.notifications.count');
            Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])
                ->where('role', 'admin|guru|siswa')
                ->name('dashboard.notifications.read');
            Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])
                ->where('role', 'admin|guru|siswa')
                ->name('dashboard.notifications.mark-all');
        });

    // Pencarian global lintas dashboard
    Route::middleware(['guard', 'auth'])->group(function () {
        Route::get('/dashboard/search/suggest', [GlobalSearchController::class, 'suggest'])->name('dashboard.search.suggest');
        Route::get('/dashboard/search', [GlobalSearchController::class, 'search'])->name('dashboard.search');
    });

    // Halaman khusus Admin
    Route::middleware(['guard', 'auth', 'role:admin'])->group(function () {
        Route::get('/dashboard/admin', [DashboardController::class, 'admin'])->name('dashboard.admin.index');

        Route::get('/dashboard/admin/data-guru', [GuruController::class, 'index'])->name('dashboard.admin.data-guru.index');
        Route::get('/dashboard/admin/data-guru/export', [GuruController::class, 'export'])->name('dashboard.admin.data-guru.export');
        Route::post('/dashboard/admin/data-guru', [GuruController::class, 'store'])->name('dashboard.admin.data-guru.store');
        Route::put('/dashboard/admin/data-guru/{user}', [GuruController::class, 'update'])->name('dashboard.admin.data-guru.update');
        Route::delete('/dashboard/admin/data-guru/{user}', [GuruController::class, 'destroy'])->name('dashboard.admin.data-guru.destroy');
        Route::get('/dashboard/admin/data-guru/{user}/penugasan', [GuruController::class, 'penugasan'])->name('dashboard.admin.data-guru.penugasan');
        Route::put('/dashboard/admin/data-guru/{user}/penugasan', [GuruController::class, 'simpanPenugasan'])->name('dashboard.admin.data-guru.penugasan.update');

        Route::get('/dashboard/admin/data-siswa', [SiswaController::class, 'index'])->name('dashboard.admin.data-siswa.index');
        Route::get('/dashboard/admin/data-siswa/export', [SiswaController::class, 'export'])->name('dashboard.admin.data-siswa.export');
        Route::post('/dashboard/admin/data-siswa', [SiswaController::class, 'store'])->name('dashboard.admin.data-siswa.store');
        Route::put('/dashboard/admin/data-siswa/{user}', [SiswaController::class, 'update'])->name('dashboard.admin.data-siswa.update');
        Route::delete('/dashboard/admin/data-siswa/{user}', [SiswaController::class, 'destroy'])->name('dashboard.admin.data-siswa.destroy');

        Route::get('/dashboard/admin/data-kelas', [KelasController::class, 'index'])->name('dashboard.admin.data-kelas.index');
        Route::get('/dashboard/admin/data-kelas/export', [KelasController::class, 'export'])->name('dashboard.admin.data-kelas.export');
        Route::put('/dashboard/admin/data-kelas/jam-belajar/operasional', [JamBelajarController::class, 'updateOperasional'])
            ->name('dashboard.admin.data-kelas.jam-belajar.operasional.update');
        Route::post('/dashboard/admin/data-kelas/jam-belajar/sesi', [JamBelajarController::class, 'storeSesi'])
            ->name('dashboard.admin.data-kelas.jam-belajar.sesi.store');
        Route::put('/dashboard/admin/data-kelas/jam-belajar/sesi/{sesi}', [JamBelajarController::class, 'updateSesi'])
            ->name('dashboard.admin.data-kelas.jam-belajar.sesi.update');
        Route::delete('/dashboard/admin/data-kelas/jam-belajar/sesi/{sesi}', [JamBelajarController::class, 'destroySesi'])
            ->name('dashboard.admin.data-kelas.jam-belajar.sesi.destroy');

        Route::post('/dashboard/admin/data-kelas', [KelasController::class, 'store'])->name('dashboard.admin.data-kelas.store');
        Route::put('/dashboard/admin/data-kelas/{kelas}', [KelasController::class, 'update'])->name('dashboard.admin.data-kelas.update');
        Route::delete('/dashboard/admin/data-kelas/{kelas}', [KelasController::class, 'destroy'])->name('dashboard.admin.data-kelas.destroy');

        Route::get('/dashboard/admin/jam-belajar', [JamBelajarController::class, 'index'])->name('dashboard.admin.jam-belajar.index');

        Route::get('/dashboard/admin/jadwal-mengajar', [AdminJadwalController::class, 'index'])->name('dashboard.admin.jadwal-mengajar.index');
        Route::get('/dashboard/admin/jadwal-mengajar/export', [AdminJadwalController::class, 'export'])->name('dashboard.admin.jadwal-mengajar.export');
        Route::post('/dashboard/admin/jadwal-mengajar', [AdminJadwalController::class, 'store'])->name('dashboard.admin.jadwal-mengajar.store');
        Route::post('/dashboard/admin/jadwal-mengajar/intent', [AdminJadwalController::class, 'intent'])->name('dashboard.admin.jadwal-mengajar.intent');
        Route::put('/dashboard/admin/jadwal-mengajar/{jadwal}', [AdminJadwalController::class, 'update'])->name('dashboard.admin.jadwal-mengajar.update');
        Route::delete('/dashboard/admin/jadwal-mengajar/{jadwal}', [AdminJadwalController::class, 'destroy'])->name('dashboard.admin.jadwal-mengajar.destroy');

        Route::get('/dashboard/admin/data-jurusan', [JurusanController::class, 'index'])->name('dashboard.admin.data-jurusan.index');
        Route::get('/dashboard/admin/data-jurusan/export', [JurusanController::class, 'export'])->name('dashboard.admin.data-jurusan.export');
        Route::post('/dashboard/admin/data-jurusan', [JurusanController::class, 'store'])->name('dashboard.admin.data-jurusan.store');
        Route::put('/dashboard/admin/data-jurusan/{jurusan}', [JurusanController::class, 'update'])->name('dashboard.admin.data-jurusan.update');
        Route::delete('/dashboard/admin/data-jurusan/{jurusan}', [JurusanController::class, 'destroy'])->name('dashboard.admin.data-jurusan.destroy');

        Route::get('/dashboard/admin/data-mata-pelajaran', [MataPelajaranController::class, 'index'])->name('dashboard.admin.data-mata-pelajaran.index');
        Route::get('/dashboard/admin/data-mata-pelajaran/export', [MataPelajaranController::class, 'export'])->name('dashboard.admin.data-mata-pelajaran.export');
        Route::post('/dashboard/admin/data-mata-pelajaran', [MataPelajaranController::class, 'store'])->name('dashboard.admin.data-mata-pelajaran.store');
        Route::put('/dashboard/admin/data-mata-pelajaran/{mapel}', [MataPelajaranController::class, 'update'])->name('dashboard.admin.data-mata-pelajaran.update');
        Route::delete('/dashboard/admin/data-mata-pelajaran/{mapel}', [MataPelajaranController::class, 'destroy'])->name('dashboard.admin.data-mata-pelajaran.destroy');

        Route::get('/dashboard/admin/manajemen-akun', [AkunController::class, 'index'])->name('dashboard.admin.manajemen-akun.index');
        Route::put('/dashboard/admin/manajemen-akun/{user}', [AkunController::class, 'update'])->name('dashboard.admin.manajemen-akun.update');
        Route::post('/dashboard/admin/manajemen-akun/{user}/reset-password', [AkunController::class, 'resetPassword'])->name('dashboard.admin.manajemen-akun.reset-password');
        Route::get('/dashboard/admin/manajemen-akun/export/csv', [AkunController::class, 'export'])->name('dashboard.admin.manajemen-akun.export-csv');
        Route::get('/dashboard/admin/manajemen-akun/template', [AkunController::class, 'templates'])->name('dashboard.admin.manajemen-akun.templates');
        Route::get('/dashboard/admin/manajemen-akun/slips', [AkunController::class, 'slips'])->name('dashboard.admin.manajemen-akun.slips');
        Route::get('/dashboard/admin/manajemen-akun/slips/export', [AkunController::class, 'exportSlips'])->name('dashboard.admin.manajemen-akun.slips.export');
        Route::post('/dashboard/admin/manajemen-akun/import', [AkunController::class, 'import'])->name('dashboard.admin.manajemen-akun.import');

        Route::get('/dashboard/admin/pengajuan-reset-password', [PengajuanResetPasswordController::class, 'index'])->name('dashboard.admin.pengajuan-reset-password.index');
        Route::post('/dashboard/admin/pengajuan-reset-password/{pengajuan}/setujui', [PengajuanResetPasswordController::class, 'setujui'])->name('dashboard.admin.pengajuan-reset-password.setujui');
        Route::post('/dashboard/admin/pengajuan-reset-password/{pengajuan}/tolak', [PengajuanResetPasswordController::class, 'tolak'])->name('dashboard.admin.pengajuan-reset-password.tolak');

        // Daftar & detail permintaan bantuan dari pop-up "Hubungi Tim IT Sekolah".
        // Detail bisa dibuka dari daftar maupun notifikasi sistem admin.
        Route::get('/dashboard/admin/kendala-akun', [KendalaAkunController::class, 'index'])->name('dashboard.admin.kendala-akun.index');
        Route::get('/dashboard/admin/kendala-akun/{itHelpRequest}', [KendalaAkunController::class, 'show'])->name('dashboard.admin.kendala-akun.show');
        Route::get('/dashboard/admin/kendala-akun/{itHelpRequest}/balas', [KendalaAkunController::class, 'createBalas'])->name('dashboard.admin.kendala-akun.balas.create');
        Route::post('/dashboard/admin/kendala-akun/{itHelpRequest}/balas', [KendalaAkunController::class, 'balas'])->name('dashboard.admin.kendala-akun.balas');
        Route::patch('/dashboard/admin/kendala-akun/{itHelpRequest}/selesai', [KendalaAkunController::class, 'selesai'])->name('dashboard.admin.kendala-akun.selesai');

        Route::get('/dashboard/admin/log-aktivitas', [LogAktivitasController::class, 'index'])->name('dashboard.admin.log-aktivitas.index');

        Route::get('/dashboard/admin/laporan-akademik', [LaporanAkademikController::class, 'index'])->name('dashboard.admin.laporan-akademik.index');
        Route::get('/dashboard/admin/laporan-akademik/export/kelas', [LaporanAkademikController::class, 'exportKelas'])->name('dashboard.admin.laporan-akademik.export-kelas');
        Route::get('/dashboard/admin/laporan-akademik/export/mata-pelajaran', [LaporanAkademikController::class, 'exportMataPelajaran'])->name('dashboard.admin.laporan-akademik.export-mata-pelajaran');

        Route::get('/dashboard/admin/backup-database', [BackupDatabaseController::class, 'index'])->name('dashboard.admin.backup-database.index');
        Route::post('/dashboard/admin/backup-database', [BackupDatabaseController::class, 'store'])->name('dashboard.admin.backup-database.store');
        Route::get('/dashboard/admin/backup-database/{backup}/unduh', [BackupDatabaseController::class, 'download'])->name('dashboard.admin.backup-database.download');
        Route::delete('/dashboard/admin/backup-database/{backup}', [BackupDatabaseController::class, 'destroy'])->name('dashboard.admin.backup-database.destroy');

        Route::get('/dashboard/admin/profile', function () {
            return view('dashboard.admin.profile.index');
        })->name('dashboard.admin.profile.index');
    });

    // Halaman khusus Guru
    Route::middleware(['guard', 'auth', 'role:guru'])->group(function () {
        Route::get('/dashboard/guru', [DashboardController::class, 'guru'])->name('dashboard.guru.index');

        Route::get('/dashboard/guru/data-siswa', [App\Http\Controllers\Guru\SiswaController::class, 'index'])
            ->name('dashboard.guru.data-siswa.index');
        Route::get('/dashboard/guru/data-siswa/export', [App\Http\Controllers\Guru\SiswaController::class, 'export'])
            ->name('dashboard.guru.data-siswa.export');

        Route::redirect('/dashboard/guru/mata-pelajaran', '/dashboard/guru');
        Route::redirect('/dashboard/guru/mata-pelajaran/{any}', '/dashboard/guru')->where('any', '.*');

        Route::get('/dashboard/guru/jadwal-pelajaran', [JadwalController::class, 'index'])
            ->name('dashboard.guru.jadwal-pelajaran.index');
        Route::get('/dashboard/guru/jadwal-pelajaran/export', [JadwalController::class, 'export'])
            ->name('dashboard.guru.jadwal-pelajaran.export');

        Route::get('/dashboard/guru/tugas-materi', [TugasMateriController::class, 'index'])
            ->name('dashboard.guru.tugas-materi.index');
        Route::post('/dashboard/guru/tugas-materi/tugas', [TugasMateriController::class, 'storeTugas'])
            ->name('dashboard.guru.tugas-materi.tugas.store');
        Route::put('/dashboard/guru/tugas-materi/tugas/{tugas}', [TugasMateriController::class, 'updateTugas'])
            ->name('dashboard.guru.tugas-materi.tugas.update');
        Route::delete('/dashboard/guru/tugas-materi/tugas/{tugas}', [TugasMateriController::class, 'destroyTugas'])
            ->name('dashboard.guru.tugas-materi.tugas.destroy');
        Route::get('/dashboard/guru/tugas-materi/tugas/{tugas}/preview', [TugasMateriController::class, 'previewTugas'])
            ->name('dashboard.guru.tugas-materi.tugas.preview');
        Route::get('/dashboard/guru/tugas-materi/tugas/{tugas}/unduh', [TugasMateriController::class, 'unduhTugas'])
            ->name('dashboard.guru.tugas-materi.tugas.unduh');
        Route::get('/dashboard/guru/tugas-materi/tugas/{tugas}/pengumpulan', [TugasMateriController::class, 'pengumpulan'])
            ->name('dashboard.guru.tugas-materi.pengumpulan');
        Route::put('/dashboard/guru/tugas-materi/pengumpulan/{pengumpulan}', [TugasMateriController::class, 'updatePengumpulan'])
            ->name('dashboard.guru.tugas-materi.pengumpulan.update');
        Route::post('/dashboard/guru/tugas-materi/materi', [TugasMateriController::class, 'storeMateri'])
            ->name('dashboard.guru.tugas-materi.materi.store');
        Route::put('/dashboard/guru/tugas-materi/materi/{materi}', [TugasMateriController::class, 'updateMateri'])
            ->name('dashboard.guru.tugas-materi.materi.update');
        Route::delete('/dashboard/guru/tugas-materi/materi/{materi}', [TugasMateriController::class, 'destroyMateri'])
            ->name('dashboard.guru.tugas-materi.materi.destroy');
        Route::get('/dashboard/guru/tugas-materi/materi/{materi}', [TugasMateriController::class, 'detailMateri'])
            ->name('dashboard.guru.tugas-materi.materi.show');
        Route::get('/dashboard/guru/tugas-materi/materi/{materi}/preview', [TugasMateriController::class, 'previewMateri'])
            ->name('dashboard.guru.tugas-materi.materi.preview');
        Route::get('/dashboard/guru/tugas-materi/materi/{materi}/unduh', [TugasMateriController::class, 'unduhMateri'])
            ->name('dashboard.guru.tugas-materi.materi.unduh');

        Route::get('/dashboard/guru/input-nilai', [InputNilaiController::class, 'index'])
            ->name('dashboard.guru.input-nilai.index');
        Route::post('/dashboard/guru/input-nilai', [InputNilaiController::class, 'store'])
            ->name('dashboard.guru.input-nilai.store');

        Route::get('/dashboard/guru/rekap-nilai', [RekapNilaiController::class, 'index'])
            ->name('dashboard.guru.rekap-nilai.index');
        Route::get('/dashboard/guru/rekap-nilai/export/siswa', [RekapNilaiController::class, 'exportSiswa'])
            ->name('dashboard.guru.rekap-nilai.export-siswa');
        Route::get('/dashboard/guru/rekap-nilai/export/kelas', [RekapNilaiController::class, 'exportKelas'])
            ->name('dashboard.guru.rekap-nilai.export-kelas');
        Route::get('/dashboard/guru/rekap-nilai/export/mata-pelajaran', [RekapNilaiController::class, 'exportMataPelajaran'])
            ->name('dashboard.guru.rekap-nilai.export-mata-pelajaran');

        Route::get('/dashboard/guru/laporan', [LaporanController::class, 'index'])
            ->name('dashboard.guru.laporan.index');
        Route::get('/dashboard/guru/laporan/export', [LaporanController::class, 'export'])
            ->name('dashboard.guru.laporan.export');

        Route::get('/dashboard/guru/profile', function () {
            return view('dashboard.guru.profile.index');
        })->name('dashboard.guru.profile.index');
    });

    // Halaman khusus Siswa
    Route::middleware(['guard', 'auth', 'role:siswa'])->group(function () {
        Route::get('/dashboard/siswa', [DashboardController::class, 'siswa'])->name('dashboard.siswa.index');

        Route::get('/dashboard/siswa/jadwal-pelajaran', [AkademikController::class, 'jadwal'])
            ->name('dashboard.siswa.jadwal-pelajaran.index');

        Route::get('/dashboard/siswa/tugas-materi', [AkademikController::class, 'tugasMateri'])
            ->name('dashboard.siswa.tugas-materi.index');
        Route::get('/dashboard/siswa/tugas-materi/tugas/{tugas}', [AkademikController::class, 'detailTugas'])
            ->name('dashboard.siswa.tugas-materi.tugas.show');
        Route::get('/dashboard/siswa/tugas-materi/tugas/{tugas}/preview', [AkademikController::class, 'previewTugas'])
            ->name('dashboard.siswa.tugas-materi.tugas.preview');
        Route::get('/dashboard/siswa/tugas-materi/tugas/{tugas}/unduh', [AkademikController::class, 'unduhTugas'])
            ->name('dashboard.siswa.tugas-materi.tugas.unduh');
        Route::post('/dashboard/siswa/tugas-materi/tugas/{tugas}/pengumpulan', [AkademikController::class, 'kumpulkanTugas'])
            ->name('dashboard.siswa.tugas-materi.pengumpulan.store');
        Route::get('/dashboard/siswa/tugas-materi/materi/{materi}', [AkademikController::class, 'detailMateri'])
            ->name('dashboard.siswa.tugas-materi.materi.show');
        Route::get('/dashboard/siswa/tugas-materi/materi/{materi}/preview', [AkademikController::class, 'previewMateri'])
            ->name('dashboard.siswa.tugas-materi.materi.preview');
        Route::get('/dashboard/siswa/tugas-materi/materi/{materi}/unduh', [AkademikController::class, 'unduhMateri'])
            ->name('dashboard.siswa.tugas-materi.materi.unduh');

        Route::get('/dashboard/siswa/riwayat-akademik', [AkademikController::class, 'riwayat'])
            ->name('dashboard.siswa.riwayat-akademik.index');

        Route::get('/dashboard/siswa/riwayat-aktivitas', [RiwayatAktivitasController::class, 'index'])
            ->name('dashboard.siswa.riwayat-aktivitas.index');

        Route::get('/dashboard/siswa/rapor', [AkademikController::class, 'rapor'])
            ->name('dashboard.siswa.rapor.index');
        Route::get('/dashboard/siswa/rapor/export', [AkademikController::class, 'exportRapor'])
            ->name('dashboard.siswa.rapor.export');

        Route::get('/dashboard/siswa/profile', function () {
            return view('dashboard.siswa.profile.index');
        })->name('dashboard.siswa.profile.index');
    });
});
