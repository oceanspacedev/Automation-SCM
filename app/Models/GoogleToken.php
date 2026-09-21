<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoogleToken extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
    ];

    /**
     * Check whether access token has expired (with 60-second grace window).
     */
    public function isExpired(): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->subSeconds(60)->isPast();
    }
}
