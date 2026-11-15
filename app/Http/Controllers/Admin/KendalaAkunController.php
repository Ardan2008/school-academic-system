<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ItHelpReplyMail;
use App\Mail\ItHelpResolvedMail;
use App\Models\ActivityLog;
use App\Models\ItHelpReply;
use App\Models\ItHelpRequest;
use App\Services\ActivityLogger;
use App\Support\LoginIdentifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/**
 * Detail permintaan bantuan dari pop-up "Hubungi Tim IT Sekolah" di landing
 * page. Halaman ini dibuka lewat notifikasi admin sehingga data pengirim
 * ditampilkan lengkap.
 *
 * Hanya menangani it_help_requests (route model binding); pengajuan reset
 * password punya halaman sendiri agar kedua jenis pengajuan tidak tertukar.
 */
class KendalaAkunController extends Controller
{
    /** Pesan email ke pengaju saat pengajuan ditandai Selesai. */
    public const PESAN_SELESAI = 'Kendala yang Anda laporkan sudah diperbaiki dan sudah bisa difungsikan kembali dengan normal. Silakan coba kembali, dan terima kasih atas kesabaran Anda.';

    /**
     * Daftar seluruh pengajuan IT Help Request dengan pencarian,
     * filter status, dan pagination — konsisten dengan halaman
     * Pengajuan Reset Password.
     */
    public function index(Request $request): View
    {
        $statuses = [
            ItHelpRequest::STATUS_BARU,
            ItHelpRequest::STATUS_DIPROSES,
            ItHelpRequest::STATUS_SUDAH_DIBALAS,
            ItHelpRequest::STATUS_SELESAI,
        ];

        $status = (string) $request->query('status', '');
        if (! in_array($status, $statuses, true)) {
            $status = '';
        }

        $search = trim((string) $request->query('search', ''));
        $role = (string) $request->query('role', '');
        $allowedRoles = ['guru', 'siswa'];
        if (! in_array($role, $allowedRoles, true)) {
            $role = '';
        }

        $query = ItHelpRequest::query()->with('user')->orderByDesc('created_at');

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($role !== '') {
            $query->where('role', $role);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('identifier', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('pesan', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $requests = $query->paginate(15)->withQueryString();

        // Hitung jumlah setiap status dalam satu query agregat.
        $counts = array_fill_keys($statuses, 0);
        ItHelpRequest::query()
            ->selectRaw('status, COUNT(*) as total')
            ->whereIn('status', $statuses)
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status')
            ->each(function ($total, $key) use (&$counts) {
                $counts[$key] = $total;
            });

        return view('dashboard.admin.kendala-akun.index', [
            'requests' => $requests,
            'status' => $status,
            'counts' => $counts,
            'search' => $search,
            'role' => $role,
        ]);
    }

    private function tandaiSedangDiproses(ItHelpRequest $itHelpRequest): void
    {
        if ($itHelpRequest->status === ItHelpRequest::STATUS_BARU) {
            $itHelpRequest->update(['status' => ItHelpRequest::STATUS_DIPROSES]);
        }
    }

    public function show(ItHelpRequest $itHelpRequest): View
    {
        $this->tandaiSedangDiproses($itHelpRequest);

        $role = (string) $itHelpRequest->role;

        return view('dashboard.admin.kendala-akun.show', [
            'permintaan' => $itHelpRequest->load(['user', 'replies.user']),
            'identifierLabel' => LoginIdentifier::label($role),
        ]);
    }

    /**
     * Halaman khusus untuk menulis balasan atas permintaan bantuan IT.
     */
    public function createBalas(ItHelpRequest $itHelpRequest): View
    {
        $this->tandaiSedangDiproses($itHelpRequest);

        $role = (string) $itHelpRequest->role;

        return view('dashboard.admin.kendala-akun.balas', [
            'permintaan' => $itHelpRequest->load(['user', 'replies.user']),
            'identifierLabel' => LoginIdentifier::label($role),
        ]);
    }

    /**
     * Simpan balasan Admin sebagai riwayat tindak lanjut, lalu kirim
     * email ke alamat pengaju. Status pengajuan diperbarui menjadi Sudah Dibalas
     * setelah email benar-benar terkirim.
     */
    public function balas(Request $request, ItHelpRequest $itHelpRequest): JsonResponse
    {
        $validated = $request->validate(
            [
                'pesan' => ['required', 'string', 'min:10', 'max:2000'],
            ],
            [
                'pesan.required' => 'Tuliskan tanggapan atau solusi untuk pengguna.',
                'pesan.min' => 'Tanggapan minimal 10 karakter.',
                'pesan.max' => 'Tanggapan maksimal 2000 karakter.',
            ]
        );

        // Riwayat tindak lanjut disimpan lebih dulu supaya balasan
        // Admin tidak hilang bila pengiriman email ternyata gagal.
        $balasan = ItHelpReply::create([
            'it_help_request_id' => $itHelpRequest->getKey(),
            'user_id' => $request->user()?->getKey(),
            'pesan' => $validated['pesan'],
            'status_pengiriman' => 'terkirim',
        ]);

        try {
            Mail::to($itHelpRequest->email)->send(new ItHelpReplyMail($balasan));
        } catch (Throwable $e) {
            $balasan->update(['status_pengiriman' => 'gagal']);

            Log::warning('Balasan permintaan bantuan gagal dikirim ke pengguna.', [
                'it_help_request_id' => $itHelpRequest->getKey(),
                'it_help_reply_id' => $balasan->getKey(),
                'recipient' => $itHelpRequest->email,
                'error' => $e->getMessage(),
            ]);

            // Status sengaja tidak diubah: email belum sampai ke pengguna.
            return response()->json([
                'status' => 'error',
                'message' => 'Balasan tersimpan sebagai riwayat, tetapi email gagal dikirim ke pengguna. Silakan coba kirim ulang atau hubungi pengguna melalui email lain.',
            ], 500);
        }

        if ($itHelpRequest->status !== ItHelpRequest::STATUS_SELESAI) {
            $itHelpRequest->update(['status' => ItHelpRequest::STATUS_SUDAH_DIBALAS]);
        }

        app(ActivityLogger::class)->record(
            ActivityLog::TIPE_KENDALA_AKUN,
            'Membalas kendala akun dari '.($itHelpRequest->user?->name ?? $itHelpRequest->email).'.',
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Balasan berhasil dikirim ke '.$itHelpRequest->email.'. Status pengajuan diperbarui menjadi Sudah Dibalas.',
            'redirect' => route('dashboard.admin.kendala-akun.show', $itHelpRequest),
        ]);
    }

    /**
     * Tandai pengajuan bantuan Selesai dan kirim email pemberitahuan
     * ke alamat pengaju: kendala yang dialaminya sudah diperbaiki dan
     * sudah bisa difungsikan kembali dengan normal.
     *
     * Status hanya berubah menjadi Selesai setelah email benar-benar
     * terkirim, sama seperti alur Balas, supaya pengguna tidak
     * dibiarkan tanpa kabar bila pengiriman email gagal.
     */
    public function selesai(Request $request, ItHelpRequest $itHelpRequest): JsonResponse
    {
        if ($itHelpRequest->status === ItHelpRequest::STATUS_SELESAI) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pengajuan ini sudah ditandai Selesai sebelumnya.',
            ], 422);
        }

        // Pemberitahuan Selesai dicatat dulu sebagai riwayat tindak
        // lanjut supaya tetap tercatat bila pengiriman email gagal.
        $notifikasi = ItHelpReply::create([
            'it_help_request_id' => $itHelpRequest->getKey(),
            'user_id' => $request->user()?->getKey(),
            'pesan' => self::PESAN_SELESAI,
            'status_pengiriman' => 'terkirim',
        ]);

        try {
            Mail::to($itHelpRequest->email)->send(new ItHelpResolvedMail($notifikasi));
        } catch (Throwable $e) {
            $notifikasi->update(['status_pengiriman' => 'gagal']);

            Log::warning('Pemberitahuan kendala selesai gagal dikirim ke pengguna.', [
                'it_help_request_id' => $itHelpRequest->getKey(),
                'it_help_reply_id' => $notifikasi->getKey(),
                'recipient' => $itHelpRequest->email,
                'error' => $e->getMessage(),
            ]);

            // Status sengaja tidak diubah: pengguna belum diberitahu.
            return response()->json([
                'status' => 'error',
                'message' => 'Pengajuan belum ditandai Selesai karena email pemberitahuan gagal dikirim ke pengguna. Silakan coba lagi atau hubungi pengguna melalui email lain.',
            ], 500);
        }

        $itHelpRequest->update(['status' => ItHelpRequest::STATUS_SELESAI]);

        app(ActivityLogger::class)->record(
            ActivityLog::TIPE_KENDALA_AKUN,
            'Menandai kendala akun dari '.($itHelpRequest->user?->name ?? $itHelpRequest->email).' selesai.',
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan ditandai Selesai dan pemberitahuan berhasil dikirim ke '.$itHelpRequest->email.'.',
        ]);
    }
}
