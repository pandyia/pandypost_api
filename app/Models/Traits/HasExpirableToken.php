<?php

namespace App\Models\Traits;

use Illuminate\Support\Str;

trait HasExpirableToken
{
    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('token', $token)->first();
    }

    protected static function generateToken(): string
    {
        return Str::random(10);
    }

    protected static function expirationDate()
    {
        return now()->addHours(
            (int) config('auth.verification_timeout')
        );
    }
}
