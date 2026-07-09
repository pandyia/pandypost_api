<?php

namespace App\Models;

use App\Enums\BillingFrequency;
use App\Enums\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Price extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

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

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(fn (Price $price) => $price->uuid = $price->uuid ?: (string) Str::uuid());
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
