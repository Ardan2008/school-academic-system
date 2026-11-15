<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public function record(
        string $type,
        string $description,
        ?User $actor = null,
        ?string $ip = null
    ): ?ActivityLog {
        $actor ??= Auth::user();

        if (! $actor instanceof User) {
            return null;
        }

        return ActivityLog::create([
            'user_id' => $actor->getKey(),
            'user_name' => $actor->name,
            'role' => $actor->role,
            'activity_type' => $type,
            'description' => $description,
            'ip_address' => $ip ?? $this->ipAddress(),
        ]);
    }

    protected function ipAddress(): ?string
    {
        return app()->bound('request') ? request()->ip() : null;
    }
}
