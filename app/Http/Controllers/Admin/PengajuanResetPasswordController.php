<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PasswordResetException;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PasswordResetRequest;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\PasswordResetService;
use Illuminate\Http\Request;

/**
 * Halaman admin untuk memproses pengajuan atur ulang password Guru/Siswa.
 *
 * Menyetujui berarti menerbitkan token 30 menit lalu mengirim email;
 * menolak wajib menyertakan alasan. Password tidak pernah diubah admin.
 */
class PengajuanResetPasswordController extends Controller
{
    public function index(Request $request)
    {
        $statuses = [
            PasswordResetRequest::STATUS_PENDING,
            PasswordResetRequest::STATUS_APPROVED,
            PasswordResetRequest::STATUS_REJECTED,
        ];

        $status = (string) $request->query('status', PasswordResetRequest::STATUS_PENDING);

        if (! in_array($status, $statuses, true)) {
            $status = PasswordResetRequest::STATUS_PENDING;
        }

        $search = trim((string) $request->query('search', ''));
        $role = (string) $request->query('role', '');
        $allowedRoles = ['guru', 'siswa'];
        if (! in_array($role, $allowedRoles, true)) {
            $role = '';
        }

        $query = PasswordResetRequest::query()
            ->with(['user', 'reviewer'])
            ->where('status', $status);

        if ($role !== '') {
            $query->where('role', $role);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('identifier', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $requests = $query
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        // Hitung jumlah semua status dalam satu query.
        $counts = array_fill_keys($statuses, 0);

        PasswordResetRequest::query()
            ->selectRaw('status, COUNT(*) as total')
            ->whereIn('status', $statuses)
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status')
            ->each(function ($total, $key) use (&$counts) {
                $counts[$key] = $total;
            });

        // Status tautan (aktif / kedaluwarsa / sudah dipakai) untuk tab Disetujui.
        $tokenStates = $status === PasswordResetRequest::STATUS_APPROVED
            ? $this->tokenStates($requests)
            : [];

        return view('dashboard.admin.pengajuan-reset-password.index', [
            'requests' => $requests,
            'status' => $status,
            'counts' => $counts,
            'tokenStates' => $tokenStates,
            'search' => $search,
            'role' => $role,
        ]);
    }

    public function setujui(Request $request, PasswordResetRequest $pengajuan, PasswordResetService $passwordReset)
    {
        try {
            $passwordReset->approve($pengajuan, $this->admin($request));
        } catch (PasswordResetException $e) {
            return $this->fail($request, $e->getMessage());
        }

        app(ActivityLogger::class)->record(
            ActivityLog::TIPE_PENGAJUAN_RESET_PASSWORD,
            'Menyetujui pengajuan reset password untuk '.($pengajuan->user?->name ?? 'akun terkait').'.',
            $this->admin($request)
        );

        return $this->ok($request, 'Pengajuan disetujui. Tautan atur ulang password telah dikirim ke email akun.');
    }

    public function tolak(Request $request, PasswordResetRequest $pengajuan, PasswordResetService $passwordReset)
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'rejection_reason.required' => 'Alasan penolakan wajib diisi.',
            'rejection_reason.min' => 'Alasan penolakan minimal 5 karakter.',
            'rejection_reason.max' => 'Alasan penolakan maksimal 255 karakter.',
        ]);

        try {
            $passwordReset->reject($pengajuan, $this->admin($request), $data['rejection_reason']);
        } catch (PasswordResetException $e) {
            return $this->fail($request, $e->getMessage());
        }

        app(ActivityLogger::class)->record(
            ActivityLog::TIPE_PENGAJUAN_RESET_PASSWORD,
            'Menolak pengajuan reset password untuk '.($pengajuan->user?->name ?? 'akun terkait').'.',
            $this->admin($request)
        );

        return $this->ok($request, 'Pengajuan ditolak. Alasan penolakan telah dicatat.');
    }

    protected function admin(Request $request): User
    {
        $user = $request->user();

        abort_if(! $user instanceof User, 403);

        return $user;
    }

    /**
     * Status tautan untuk setiap user pada halaman pengajuan (hanya dipakai
     * di tab Disetujui). Mengambil token terbaru milik user, lalu menilai
     * apakah masih bisa dipakai, sudah kedaluwarsa, atau sudah terpakai.
     *
     * @return array<int, string> key = user_id, value = active|expired|used
     */
    protected function tokenStates($requests): array
    {
        $userIds = $requests->pluck('user_id')->filter()->all();

        if ($userIds === []) {
            return [];
        }

        return PasswordResetToken::query()
            ->whereIn('user_id', $userIds)
            ->orderByDesc('id')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($tokens) => $tokens->first())
            ->map(fn (PasswordResetToken $token): string => $this->tokenState($token))
            ->all();
    }

    protected function tokenState(PasswordResetToken $token): string
    {
        if ($token->isUsed()) {
            return 'used';
        }

        return $token->isExpired() ? 'expired' : 'active';
    }

    protected function ok(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
            ]);
        }

        return redirect()
            ->route('dashboard.admin.pengajuan-reset-password.index', $this->redirectQuery($request))
            ->with('success', $message);
    }

    protected function fail(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'message' => $message,
            ], 422);
        }

        return redirect()
            ->route('dashboard.admin.pengajuan-reset-password.index', $this->redirectQuery($request))
            ->withErrors(['status' => $message]);
    }

    /**
     * Pertahankan filter status, role, search, dan halaman saat ini ketika redirect setelah
     * setujui/tolak, supaya admin tidak dilempar balik ke tab/halaman default.
     */
    protected function redirectQuery(Request $request): array
    {
        $statuses = [
            PasswordResetRequest::STATUS_PENDING,
            PasswordResetRequest::STATUS_APPROVED,
            PasswordResetRequest::STATUS_REJECTED,
        ];

        $query = [];

        $status = (string) $request->query('status', '');

        if (in_array($status, $statuses, true)) {
            $query['status'] = $status;
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query['search'] = $search;
        }

        $role = (string) $request->query('role', '');
        if (in_array($role, ['guru', 'siswa'], true)) {
            $query['role'] = $role;
        }

        if ($request->query('page') !== null) {
            $query['page'] = (int) $request->query('page');
        }

        return $query;
    }
}
