<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token tidak valid atau pengguna tidak ditemukan',
                ], 401);
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token tidak valid atau telah kedaluwarsa',
            ], 401);
        }

        return $next($request);
    }
}
