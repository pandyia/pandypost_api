<?php

namespace App\Models;

use App\Models\Traits\HasExpirableToken;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationEmailToken extends Model
{
    use HasFactory;
    use HasExpirableToken;

    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function generate(User $user): string
    {
        $token = static::generateToken();

        static::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => static::expirationDate(),
        ]);

        return $token;
    }
}
