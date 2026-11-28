<?php

namespace App\Models;

use App\Enums\SubscriptionChangeStatus;
use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Mudança de plano agendada para o fim do ciclo (spec 024). O tenant vem da assinatura.
 */
class SubscriptionChange extends Model implements Auditable
{
    use AuditableTrait, HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'subscription_id',
        'price_id',
        'stripe_schedule_id',
        'effective_at',
        'status',
    ];

    protected $hidden = [
        'id',
        'subscription_id',
        'price_id',
        'stripe_schedule_id',
    ];

    protected $casts = [
        'effective_at' => 'datetime',
        'status' => SubscriptionChangeStatus::class,
    ];

    public function generateTags(): array
    {
        return ['subscription_change'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(Price::class)->withTrashed();
    }

    public function scopePending($query)
    {
        return $query->where('status', SubscriptionChangeStatus::PENDING->value);
    }
}
