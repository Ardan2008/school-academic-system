<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BackupGagalException;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\BackupDatabase;
use App\Services\ActivityLogger;
use App\Services\DatabaseBackupService;
use App\Support\Waktu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Backup Database.
 *
 * Admin membuat file .sql dari database yang sedang dipakai, lalu bisa
 * mengunduh atau menghapusnya. Pemulihan (restore) sengaja belum tersedia:
 * menjalankan isi file .sql itu menghapus data yang ada, jadi harus lewat
 * langkah manual dari phpMyAdmin atau terminal.
 */
class BackupDatabaseController extends Controller
{
    public function index(): View
    {
        $backup = BackupDatabase::query()
            ->with('pembuat')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $driver = DB::connection()->getDriverName();

        $kapasitas = $this->kapasitasTersedia();

        // Nilai kartu sudah diformat di sini, karena isinya campuran angka
        // dan teks. Kartu di halaman cukup menampilkannya apa adanya.
        $stats = [
            [
                'key' => 'total',
                'label' => 'Total Backup',
                'value' => $backup->count(),
                'icon' => 'lucide-database-backup',
            ],
            [
                'key' => 'ukuran',
                'label' => 'Total Ukuran',
                'value' => $this->ukuranTerbaca((int) $backup->sum('ukuran_bytes')),
                'icon' => 'lucide-hard-drive',
            ],
            [
                'key' => 'terakhir',
                'label' => 'Backup Terakhir',
                // Format angka dipakai juga oleh tabel di halaman ini, supaya
                // kartu dan daftar tidak pernah menampilkan tanggal berbeda.
                'value' => $this->tanggalTerbaca($backup->first()?->created_at),
                'icon' => 'lucide-clock',
            ],
            [
                'key' => 'tersedia',
                'label' => 'Kapasitas Tersedia',
                'value' => $kapasitas === null ? 'Tidak diketahui' : $this->ukuranTerbaca($kapasitas),
                'icon' => 'lucide-gauge',
            ],
        ];

        $statTone = [
            'total' => ['bg-blue-100 text-primary-60', '#1E86FF'],
            'ukuran' => ['bg-indigo-100 text-indigo-600', '#4F46E5'],
            'terakhir' => ['bg-emerald-100 text-emerald-600', '#059669'],
            'tersedia' => ['bg-amber-100 text-amber-600', '#D97706'],
        ];

        // Riwayat yang filenya hilang tetap dikirim ke halaman dan ditandai
        // "File Hilang". Barisnya tidak dihapus diam-diam supaya admin tetap
        // tahu file itu pernah ada, dan bisa menghapusnya lewat tombol Hapus.
        $backupData = [
            'backup' => $backup->map(fn (BackupDatabase $b) => $this->baris($b))->all(),
            'driver' => $driver,
            'database' => (string) DB::connection()->getDatabaseName(),
            'maks' => (int) config('backup.keep'),
        ];

        return view('dashboard.admin.backup-database.index', [
            'stats' => $stats,
            'statTone' => $statTone,
            'backupData' => $backupData,
        ]);
    }

    /**
     * Buat satu file backup baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'catatan' => ['nullable', 'string', 'max:191'],
            ],
            [
                'catatan.max' => 'Catatan maksimal 191 karakter.',
            ]
        );

        $service = app(DatabaseBackupService::class);

        try {
            $hasil = $service->jalankan([
                'catatan' => $validated['catatan'] ?? null,
                'dibuat_oleh' => $request->user()?->id,
            ]);
        } catch (BackupGagalException $e) {
            // Pesan pada exception ini memang disiapkan untuk dibaca admin,
            // jadi boleh ditampilkan apa adanya.
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->status());
        } catch (Throwable $e) {
            // Detail teknis dari driver tidak pernah ditampilkan apa adanya
            // karena bisa membocorkan struktur database. Semuanya ditulis ke
            // log supaya tetap bisa ditelusuri, sementara admin diberi tahu
            // dengan kalimat umum.
            report($e);

            return response()->json([
                'status' => 'error',
                'message' => 'Backup gagal dibuat. Silakan coba lagi atau periksa catatan pada log aplikasi.',
            ], 500);
        }

        /** @var BackupDatabase $baru */
        $baru = $hasil['backup'];

        $pesan = 'Backup database berhasil dibuat.';

        if ($hasil['dihapus'] !== []) {
            // Rotasi diam-diam menghapus file lama, jadi jumlahnya disebut
            // supaya admin tidak bingung kenapa daftarnya memendek.
            $pesan .= ' '.$this->jumlahKata(count($hasil['dihapus'])).' backup lama dihapus otomatis.';
        }

        app(ActivityLogger::class)->record(
            ActivityLog::TIPE_BACKUP_DATABASE,
            "Membuat backup database {$baru->namaSederhana()}.",
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => $pesan,
            'data' => ['backup' => $this->baris($baru->fresh())],
        ], 201);
    }

    /**
     * Unduh file .sql milik satu backup.
     */
    public function download(BackupDatabase $backup): StreamedResponse|JsonResponse
    {
        $disk = Storage::disk((string) config('backup.disk', 'local'));

        if (! $disk->exists($backup->nama_file)) {
            // Baris riwayat tetap dibiarkan supaya admin tahu file ini hilang
            // dan bisa menghapusnya lewat tombol hapus.
            return response()->json([
                'status' => 'error',
                'message' => 'File backup ini sudah tidak ada di penyimpanan. Hapus daftarnya dengan tombol Hapus.',
            ], 404);
        }

        app(ActivityLogger::class)->record(
            ActivityLog::TIPE_BACKUP_DATABASE,
            "Mengunduh backup database {$backup->namaSederhana()}."
        );

        return $disk->download($backup->nama_file, $backup->namaSederhana());
    }

    /**
     * Hapus file backup beserta baris riwayatnya.
     */
    public function destroy(BackupDatabase $backup): JsonResponse
    {
        $nama = $backup->namaSederhana();

        app(DatabaseBackupService::class)->hapus($backup);

        app(ActivityLogger::class)->record(ActivityLog::TIPE_BACKUP_DATABASE, "Menghapus backup database {$nama}.");

        return response()->json([
            'status' => 'success',
            'message' => "Backup {$nama} berhasil dihapus.",
            'data' => ['id' => $backup->id],
        ]);
    }

    /**
     * Satu baris riwayat untuk ditampilkan di halaman.
     *
     * @return array{id: int, nama: string, tanggal: string, ukuran: int, ukuran_teks: string, jumlah_tabel: int, durasi: int, durasi_teks: string, catatan: string|null, pembuat: string|null, driver: string, database: string, ada_file: bool}
     */
    protected function baris(BackupDatabase $backup): array
    {
        $disk = Storage::disk((string) config('backup.disk', 'local'));

        return [
            'id' => $backup->id,
            'nama' => $backup->namaSederhana(),
            // Tanpa penanda zona waktu, browser akan mengira ini waktu lokal
            // dan selisih "x jam lalu" ikut meleset sebanyak selisih
            // antara UTC dan zona waktu admin.
            'tanggal' => $backup->created_at?->toIso8601String(),
            'ukuran' => (int) $backup->ukuran_bytes,
            'ukuran_teks' => $this->ukuranTerbaca((int) $backup->ukuran_bytes),
            'jumlah_tabel' => (int) $backup->jumlah_tabel,
            'durasi' => (int) $backup->durasi_detik,
            'durasi_teks' => $this->durasiTerbaca((int) $backup->durasi_detik),
            'catatan' => $backup->catatan,
            'pembuat' => $backup->pembuat?->name,
            'driver' => $backup->driver,
            'database' => $backup->database,
            // Disimpan supaya tombol unduh bisa dimatikan dari awal kalau
            // filenya hilang, tanpa harus menunggu klik pertama.
            'ada_file' => $disk->exists($backup->nama_file),
        ];
    }

    protected function ukuranTerbaca(int $byte): string
    {
        if ($byte < 1024) {
            return $byte.' B';
        }

        $satuan = ['KB', 'MB', 'GB', 'TB'];
        $ukuran = $byte / 1024;
        $i = 0;

        while ($ukuran >= 1024 && $i < count($satuan) - 1) {
            $ukuran /= 1024;
            $i++;
        }

        return number_format($ukuran, $ukuran >= 100 ? 0 : 1, ',', '.').' '.$satuan[$i];
    }

    protected function durasiTerbaca(int $detik): string
    {
        if ($detik < 60) {
            return $detik.' detik';
        }

        return intdiv($detik, 60).' menit '.($detik % 60).' detik';
    }

    /**
     * Tanggal dengan format angka Indonesia, atau null kalau belum ada.
     *
     * Dikonversi ke WIB lebih dulu, karena aplikasi menyimpan waktu dalam
     * UTC. Kalau tidak, kartu statistik akan menampilkan jam yang 7 jam lebih
     * awal dari yang ditulis di tabel.
     */
    protected function tanggalTerbaca(?Carbon $waktu): ?string
    {
        return Waktu::zona($waktu)?->format('d/m/Y H:i');
    }

    protected function jumlahKata(int $jumlah): string
    {
        return $jumlah === 1 ? '1' : (string) $jumlah;
    }

    /**
     * Ruang kosong di disk penyimpanan, atau null kalau tidak bisa dibaca.
     *
     * Ruang kosong sering tidak dilaporkan di sebagian sistem berkas, jadi
     * null ditampilkan sebagai "tidak diketahui" daripada angka yang salah.
     */
    protected function kapasitasTersedia(): ?int
    {
        $folder = Storage::disk((string) config('backup.disk', 'local'))->path(config('backup.folder'));

        if (! is_dir($folder)) {
            return null;
        }

        $bytes = @disk_free_space($folder);

        return $bytes === false ? null : (int) $bytes;
    }
}
