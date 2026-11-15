<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user() ?? Auth::user();

        if (! $user && Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
        }
        foreach (['admin', 'guru', 'siswa'] as $g) {
            try {
                if (! $user && Auth::guard($g)->check()) {
                    $user = Auth::guard($g)->user();
                }
            } catch (\Throwable $e) {
            }
        }

        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
