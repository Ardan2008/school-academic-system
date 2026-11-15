<?php

namespace App\Auth;

use Illuminate\Auth\SessionGuard as BaseSessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;

class RoleSessionGuard extends BaseSessionGuard implements StatefulGuard
{
    protected string $role;

    public function __construct(string $role, ...$args)
    {
        $this->role = $role;
        parent::__construct(...$args);
    }

    public function getName()
    {
        return 'login_'.$this->role.'_'.sha1(static::class.$this->role);
    }

    public function getRecallerName()
    {
        return 'remember_'.$this->role.'_'.sha1(static::class.$this->role);
    }

    public function login(Authenticatable $user, $remember = false): void
    {
        // Pastikan hanya user dengan role yang sama bisa login ke guard ini.
        if ($user->role !== $this->role) {
            throw new \InvalidArgumentException("User role [{$user->role}] cannot login to guard [{$this->role}]");
        }
        parent::login($user, $remember);
    }

    public function user()
    {
        $user = parent::user();
        if ($user && $user->role === $this->role) {
            return $user;
        }

        if ($user && $user->role !== $this->role) {
            $this->session->remove($this->getName());
            $this->user = null;

            return null;
        }

        return $user;
    }
}
