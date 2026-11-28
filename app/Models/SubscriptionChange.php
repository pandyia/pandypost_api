<?php

namespace App\Models;

use App\Enums\SubscriptionChangeStatus;
use App\Models\Traits\HasUuid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /**
     * O Stripe manda timestamps e o Carbon 3 os cria em UTC; gravados assim, o banco guardaria a hora
     * UTC e a leitura a interpretaria no fuso do app (a data adiantaria horas, às vezes um dia).
     * Converte para o fuso do app antes de gravar (mesmo cuidado de `Subscription`).
     */
    protected function effectiveAt(): Attribute
    {
        return Attribute::set(fn ($value) => $value instanceof CarbonInterface
            ? $value->copy()->setTimezone(date_default_timezone_get())
            : $value);
    }

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
