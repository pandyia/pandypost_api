<?php

namespace App\Models;

use App\Enums\BillingFrequency;
use App\Enums\Currency;
use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Price extends Model implements Auditable
{
    use AuditableTrait, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'plan_id',
        'amount',
        'currency',
        'frequency',
        'trial_period_days',
        'gateway_price_id',
        'is_active',
    ];

    protected $hidden = [
        'id',
    ];

    protected $casts = [
        'amount' => 'integer',
        'trial_period_days' => 'integer',
        'currency' => Currency::class,
        'frequency' => BillingFrequency::class,
        'is_active' => 'boolean',
    ];

    public function generateTags(): array
    {
        return ['price'];
    }

    // Relationships

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
