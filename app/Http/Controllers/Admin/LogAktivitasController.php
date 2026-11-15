<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class LogAktivitasController extends Controller
{
    public function index(Request $request): View
    {
        $roles = ['admin', 'guru', 'siswa'];

        $role = (string) $request->query('role', '');
        if (! in_array($role, $roles, true)) {
            $role = '';
        }

        $tipe = (string) $request->query('tipe', '');
        if (! array_key_exists($tipe, ActivityLog::labels())) {
            $tipe = '';
        }

        $search = trim((string) $request->query('search', ''));

        $dari = $this->tanggalValid((string) $request->query('dari', ''));
        $sampai = $this->tanggalValid((string) $request->query('sampai', ''));

        $tanggalError = null;
        $filterDari = $dari;
        $filterSampai = $sampai;

        if ($dari !== null && $sampai !== null && $dari > $sampai) {
            $tanggalError = 'Tanggal akhir tidak boleh lebih awal daripada tanggal mulai.';
            $filterDari = null;
            $filterSampai = null;
        }

        $query = ActivityLog::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($role !== '') {
            $query->where('role', $role);
        }

        if ($tipe !== '') {
            $query->where('activity_type', $tipe);
        }

        if ($filterDari !== null) {
            $query->where('created_at', '>=', Carbon::parse($filterDari, Waktu::ZONA)->startOfDay()->utc());
        }

        if ($filterSampai !== null) {
            $query->where('created_at', '<=', Carbon::parse($filterSampai, Waktu::ZONA)->endOfDay()->utc());
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        $logs = $query->paginate(10)->withQueryString();

        $tanggalResetQuery = array_filter([
            'search' => $search,
            'role' => $role,
            'tipe' => $tipe,
        ], fn (string $nilai) => $nilai !== '');

        return view('dashboard.admin.log-aktivitas.index', [
            'logs' => $logs,
            'role' => $role,
            'tipe' => $tipe,
            'search' => $search,
            'dari' => $dari,
            'sampai' => $sampai,
            'tanggalError' => $tanggalError,
            'tanggalResetUrl' => route('dashboard.admin.log-aktivitas.index', $tanggalResetQuery),
            'tipeOptions' => ActivityLog::labels(),
        ]);
    }

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
