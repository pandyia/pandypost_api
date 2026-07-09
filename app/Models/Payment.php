<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Models\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Pagamento (1 linha por invoice/checkout), populado pelo webhook.
 * Escopado ao Workspace para as leituras do cliente (fase 3). As escritas do
 * webhook ocorrem sem usuário autenticado, então o global scope não filtra.
 */
class Payment extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'uuid',
        'workspace_id',
        'subscription_id',
        'status',
        'method',
        'amount',
        'currency',
        'gateway_checkout_session_id',
        'gateway_checkout_url',
        'gateway_checkout_expires_at',
        'gateway_invoice_id',
        'gateway_hosted_invoice_url',
        'gateway_invoice_pdf',
        'gateway_payment_intent_id',
        'receipt_url',
        'due_date',
        'paid_at',
        'period_start',
        'period_end',
    ];

    protected $casts = [
        'status' => PaymentStatus::class,
        'currency' => Currency::class,
        'amount' => 'integer',
        'gateway_checkout_expires_at' => 'datetime',
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
    ];

    protected $hidden = ['id'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(fn (Payment $payment) => $payment->uuid = $payment->uuid ?: (string) Str::uuid());
    }

    // Relationships

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
