<?php

namespace App\Services\Billing\Tenant;

use App\Models\Payment;
use App\Services\BaseService;

/**
 * Histórico de pagamentos/faturas do cliente, a partir da tabela Payment local
 * (populada pelo webhook). Escopado ao workspace via BelongsToWorkspace.
 */
class PaymentService extends BaseService
{
    protected array $orderBy = ['created_at' => 'desc'];
    protected array $normalFilter = ['status'];

    public function __construct(Payment $payment)
    {
        parent::__construct($payment);
    }
}
