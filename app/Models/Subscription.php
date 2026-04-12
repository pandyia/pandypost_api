<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'plan_id',
        'starts_at',
        'ends_at',
        'status',
        'posts_limit',
        'posts_used'
    ];

    protected $casts = [
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
    ];

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Business Methods

    public function isValid(): bool
    {
        return $this->hasValidStatus() && $this->isNotExpired();
    }

    private function hasValidStatus(): bool
    {
        return in_array($this->status, ['active', 'trialing'], true);
    }

    private function isNotExpired(): bool
    {
        return !$this->ends_at || !$this->ends_at->isPast();
    }

    public function hasQuota(): bool
    {
        return $this->posts_used < $this->posts_limit;
    }
}