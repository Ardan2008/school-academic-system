<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    protected $table = 'login_attempts';

    protected $fillable = ['email', 'attempts', 'locked_until'];

    protected $casts = [
        'attempts' => 'integer',
        'locked_until' => 'datetime',
    ];
}
