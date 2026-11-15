<?php

namespace App\Auth;

use Illuminate\Auth\SessionGuard as BaseSessionGuard;
use Illuminate\Contracts\Auth\StatefulGuard;

class PortalSessionGuard extends BaseSessionGuard implements StatefulGuard
{
    public function __construct(...$args)
    {
        parent::__construct(...$args);
    }

    public function check(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        // Portal guard menerima user aktif apa pun sebagai fallback untuk
        // route/view/test.
        return true;
    }

    /**
     * Resolver user kustom yang fallback ke guard lain.
     */
    public function user()
    {
        if ($this->loggedOut) {
            return null;
        }

        if (! is_null($this->user)) {
            return $this->user;
        }

        $this->user = parent::user();

        // Fallback: bila sesi guard ini kosong, coba ambil user dari guard
        // role lain.
        if ($this->user === null && app()->bound('auth')) {
            $auth = app('auth');
            foreach (['web', 'admin', 'guru', 'siswa'] as $g) {
                try {
                    $guard = $auth->guard($g);
                    if ($guard === $this) {
                        continue;
                    }
                    if (method_exists($guard, 'check') && $guard->check()) {
                        $u = $guard->user();
                        if ($u) {
                            $this->user = $u;

                            return $u;
                        }
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        return $this->user;
    }

    public function logout(): void
    {
        try {
            parent::logout();
        } catch (\Throwable $e) {
        }

        $this->user = null;
        $this->loggedOut = true;
    }
}
