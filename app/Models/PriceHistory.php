<?php

namespace App\Models;

use App\Enums\BillingFrequency;
use App\Enums\Currency;
use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    use HasUuid;

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

    protected $hidden = [
        'id',
    ];

    protected $casts = [
        'amount' => 'integer',
        'trial_period_days' => 'integer',
        'currency' => Currency::class,
        'frequency' => BillingFrequency::class,
        'archived_at' => 'datetime',
    ];

    // Relationships

    public function price(): BelongsTo
    {
        return $this->belongsTo(Price::class);
    }
}
