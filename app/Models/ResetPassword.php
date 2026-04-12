<?php

namespace App\Models;

use App\Models\Traits\HasExpirableToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResetPassword extends Model
{
    use HasExpirableToken;

    protected $table = 'password_reset_tokens';
    protected $primaryKey = 'email';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'email',
        'token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }

    public static function generate(User $user): string
    {
        static::where('email', $user->email)->delete();

        $token = static::generateToken();

        static::create([
            'email' => $user->email,
            'token' => $token,
            'expires_at' => static::expirationDate(),
        ]);

        return $token;
    }
}
