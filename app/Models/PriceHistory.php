<?php

namespace App\Models;

use App\Enums\BillingFrequency;
use App\Enums\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PriceHistory extends Model
{
    protected $fillable = [
        'uuid',
        'price_id',
        'gateway_price_id',
        'amount',
        'currency',
        'frequency',
        'trial_period_days',
        'archived_at',
        'reason',
    ];

    protected $casts = [
        'amount' => 'integer',
        'trial_period_days' => 'integer',
        'currency' => Currency::class,
        'frequency' => BillingFrequency::class,
        'archived_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(fn (PriceHistory $history) => $history->uuid = $history->uuid ?: (string) Str::uuid());
    }

    // Relationships

    public function price(): BelongsTo
    {
        return $this->belongsTo(Price::class);
    }
}
