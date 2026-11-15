<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotifikasiAkademikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notifikasi untuk ketiga role (Admin, Guru, Siswa).
 *
 * Sumbernya hanya tabel notifications dari aktivitas nyata di database, dan
 * status baca disimpan pada kolom read_at baris itu sendiri. Notifikasi yang
 * sudah dibaca tidak pernah dihapus; hanya badge jumlahnya yang berkurang.
 * Semua hitungan dikerjakan backend dari database agar selalu akurat.
 */
class NotificationController extends Controller
{
    /** Filter daftar notifikasi: Semua, Belum Dibaca, Sudah Dibaca. */
    private const FILTERS = ['all', 'unread', 'read'];

    public function __construct(
        private readonly NotifikasiAkademikService $notifikasiAkademik,
    ) {}

    /**
     * Tolak bila user yang login tidak sesuai role di URL, agar notifikasi
     * satu role tidak bisa dibaca role lain lewat manipulasi URL.
     */
    private function userUntukRole($request, string $role)
    {
        $user = $request->user();

        if (! $user instanceof User || $user->role !== $role) {
            abort(403, 'Anda tidak memiliki akses ke notifikasi role ini.');
        }

        return $user;
    }

    /**
     * Batasi query ke user_id + role baris (atau role kosong), sehingga
     * status baca satu pengguna tidak menyentuh milik pengguna lain.
     */
    private function baseQuery($user)
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->where(function ($q) use ($user) {
                if ($user->role) {
                    $q->where('role', $user->role)->orWhereNull('role');
                }
            });
    }

    private function sinkronkanBilaAkademik($user): void
    {
        // Guru & Siswa: sinkronkan notifikasi dari aktivitas nyata terbaru
        // (tugas, nilai, jadwal, pengumuman) sebelum membaca data.
        if ($user->role === 'guru' || $user->role === 'siswa') {
            $this->notifikasiAkademik->sinkronkanPengingat($user);
        }
    }

    /**
     * Daftar notifikasi milik user, dengan filter all|unread|read. Response
     * menyertakan jumlah per kategori agar tab dan badge sesuai database.
     */
    public function index(Request $request, string $role): JsonResponse
    {
        $user = $this->userUntukRole($request, $role);
        $this->sinkronkanBilaAkademik($user);

        $filter = strtolower(trim((string) $request->query('filter', 'all')));
        if (! in_array($filter, self::FILTERS, true)) {
            $filter = 'all';
        }

        $unreadCount = $this->baseQuery($user)->whereNull('read_at')->count();
        $readCount = $this->baseQuery($user)->whereNotNull('read_at')->count();

        $listQuery = match ($filter) {
            'unread' => $this->baseQuery($user)->whereNull('read_at'),
            'read' => $this->baseQuery($user)->whereNotNull('read_at'),
            default => $this->baseQuery($user),
        };

        $notifications = $listQuery
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($n) {
                return [
                    'id' => $n->id,
                    'type' => $n->type,
                    'title' => $n->title,
                    'message' => $n->message,
                    'icon' => $n->icon ?? 'bell',
                    'url' => $n->url,
                    'read_at' => $n->read_at,
                    'is_read' => $n->read_at !== null,
                    'time' => $n->created_at ? $n->created_at->diffForHumans() : null,
                    'created_at' => $n->created_at,
                ];
            });

        return response()->json([
            'filter' => $filter,
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'counts' => [
                'all' => $unreadCount + $readCount,
                'unread' => $unreadCount,
                'read' => $readCount,
            ],
        ]);
    }

    /**
     * Tandai satu notifikasi sebagai dibaca. Barisnya tetap tersimpan dan
     * tetap tampil di filter Semua/Sudah Dibaca; jumlah belum dibaca dihitung
     * ulang dari database.
     */
    public function markAsRead(Request $request, string $role, $id): JsonResponse
    {
        $user = $this->userUntukRole($request, $role);

        $notification = $this->baseQuery($user)->find($id);
        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'unread_count' => $this->baseQuery($user)->whereNull('read_at')->count(),
        ]);
    }

    /**
     * Tandai semua notifikasi user ini sebagai dibaca. Query dibatasi user_id
     * + role sehingga notifikasi pengguna lain tidak ikut berubah.
     */
    public function markAllAsRead(Request $request, string $role): JsonResponse
    {
        $user = $this->userUntukRole($request, $role);

        $this->baseQuery($user)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'unread_count' => 0,
        ]);
    }

    /** Badge jumlah belum dibaca, dihitung dari database setelah sinkronisasi. */
    public function count(Request $request, string $role): JsonResponse
    {
        $user = $this->userUntukRole($request, $role);
        $this->sinkronkanBilaAkademik($user);

        return response()->json([
            'unread_count' => $this->baseQuery($user)->whereNull('read_at')->count(),
        ]);
    }
}
