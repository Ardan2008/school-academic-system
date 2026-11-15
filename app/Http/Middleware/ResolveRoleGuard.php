<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveRoleGuard
{
    /** Guard default berdasarkan awalan URL dashboard. */
    private const GUARD_DASHBOARD = [
        'dashboard/admin' => 'admin',
        'dashboard/guru' => 'guru',
        'dashboard/siswa' => 'siswa',
    ];

    /** Urutan guard yang dicek saat mencari user yang sedang login. */
    private const GUARD_DICEK = ['web', 'portal', 'admin', 'guru', 'siswa'];

    public function handle(Request $request, Closure $next): Response
    {
        $guard = $this->guardDashboard($request->path());

        // Cari guard yang benar-benar memiliki user terautentikasi. Bila tidak
        // ada, jangan paksa ganti default guard: biarkan guard default (web)
        // yang menangani request tamu agar alur redirect ke halaman login
        // tetap konsisten.
        if ($guard !== null && $this->guardPunyaUser($guard)) {
            $targetGuard = $guard;
        } else {
            $targetGuard = $this->guardTerlogin();
        }

        if ($targetGuard !== null) {
            Auth::shouldUse($targetGuard);
        }

        return $next($request);
    }

    private function guardDashboard(string $path): ?string
    {
        foreach (self::GUARD_DASHBOARD as $prefix => $guard) {
            if (str_starts_with($path, $prefix)) {
                return $guard;
            }
        }

        return null;
    }

    private function guardPunyaUser(string $guard): bool
    {
        try {
            return Auth::guard($guard)->check();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function guardTerlogin(): ?string
    {
        foreach (self::GUARD_DICEK as $guard) {
            if ($this->guardPunyaUser($guard)) {
                return $guard;
            }
        }

        return null;
    }
}
