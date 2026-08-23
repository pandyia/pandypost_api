<?php

namespace App\Models;

use App\Observers\WorkspaceObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

#[ObservedBy(WorkspaceObserver::class)]
class Workspace extends Model implements Auditable
{
    use AuditableTrait, Billable;

    protected $fillable = ['name', 'is_personal_team', 'uuid'];

    public function getAuditRepresentation(): string
    {
        return $this->name;
    }

    // tags para auditoria
    public function generateTags(): array
    {
        return ['workspace'];
    }

    protected $hidden = [
        'id',
    ];

    /**
     * Memoiza a moeda resolvida: ManagesInvoices chama preferredCurrency() uma
     * vez por item da fatura, e cada chamada faria uma query no Price.
     */
    protected ?string $resolvedCurrency = null;

    /**
     * Moeda usada pelos fluxos do Cashier que NÃO passam por um Price:
     * charge(), refund(), invoiceFor(), tab() e applyBalance().
     *
     * O Checkout de assinatura não usa isto — lá a moeda vem do Price
     * cadastrado no Stripe (ver CheckoutService). Como o Stripe trava a moeda
     * do Customer na primeira cobrança, a fonte de verdade é o Price da
     * assinatura existente, inclusive de uma já cancelada. Sem assinatura
     * nenhuma, cai no default de config/cashier.php.
     */
    public function preferredCurrency(): string
    {
        if ($this->resolvedCurrency !== null) {
            return $this->resolvedCurrency;
        }

        $gatewayPriceId = $this->subscriptions->first()?->stripe_price;

        // withTrashed: um Price arquivado (editar preço faz soft delete e cria
        // outro) continua sendo o vigente para quem já assinou naquele id.
        $price = $gatewayPriceId
            ? Price::withTrashed()->where('gateway_price_id', $gatewayPriceId)->first()
            : null;

        return $this->resolvedCurrency = $price?->currency->value
            ?? config('cashier.currency');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted()
    {
        // sempre que for buscar workspaces, buscar apenas os que o usuário tem acesso  
        static::addGlobalScope('member', function ($builder) {
            if (auth()->hasUser()) {
                $builder->whereHas('accesses', fn($q) => $q->where('user_id', auth()->id()));
            }
        });
        // ao criar um workspace, gerar um uuid
        static::creating(fn($workspace) => $workspace->uuid = $workspace->uuid ?: (string) Str::uuid());
    }

    // Relationships

    public function accesses()
    {
        return $this->hasMany(Access::class);
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    // Scopes

    public function scopeAllAccess($query)
    {
        return $query->withoutGlobalScope('member');
    }
}
