<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Plan extends Model implements Auditable
{
    use AuditableTrait, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'is_visible',
        'is_active',
        'gateway_product_id',
        // Legado (fase Cliente irá refatorar) — mantidos por compatibilidade:
        'monthly_posts_limit',
        'social_accounts_limit',
        'price',
        'stripe_plan_id',
    ];

    protected $hidden = [
        'id',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function generateTags(): array
    {
        return ['plan'];
    }

    // Relationships

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    // Business Methods

    /**
     * IDs de preço no gateway associados a este plano — dos preços atuais
     * (inclusive arquivados) e do histórico. Usado para correlacionar as
     * assinaturas do Cashier (que guardam `stripe_price`) a este plano, já que
     * não há FK direta plano→assinatura no schema do Cashier.
     */
    public function gatewayPriceIds(): array
    {
        $current = $this->prices()->withTrashed()->pluck('gateway_price_id');
        $historical = PriceHistory::whereIn('price_id', $this->prices()->withTrashed()->select('id'))
            ->pluck('gateway_price_id');

        return $current->merge($historical)->filter()->unique()->values()->all();
    }

    public function hasActiveSubscriptions(): bool
    {
        $ids = $this->gatewayPriceIds();

        return $ids !== [] && Subscription::whereIn('stripe_price', $ids)
            ->whereIn('stripe_status', ['active', 'trialing', 'past_due'])
            ->exists();
    }

    public function hasAnySubscriptions(): bool
    {
        $ids = $this->gatewayPriceIds();

        return $ids !== [] && Subscription::whereIn('stripe_price', $ids)->exists();
    }
}
