<?php

namespace App\Http\Controllers\Api\Billing\Tenant;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Billing\Tenant\PaymentResource;
use App\Services\Billing\Tenant\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Assinatura e pagamentos
 */
class PaymentController extends BaseController
{
    protected ?string $resourceClass = PaymentResource::class;

    protected static string $permissionGroup = 'billing';

    protected static array $permissionMethods = [
        'view' => ['index', 'show'],
    ];

    public function __construct(
        private readonly PaymentService $service
    ) {
        parent::__construct($service);
    }

    /**
     * Ver fatura
     *
     * **Permissão:** `billing.view`
     *
     * @urlParam payment string required UUID da fatura (`id` da listagem). Example: 4d5e6f7a-8b9c-4d0e-9f1a-2b3c4d5e6f7a
     *
     * @response 200 {"data": {
     *       "id": "4d5e6f7a-...",
     *       "status": {"value": "paid", "label": "Pago"},
     *       "method": "card",
     *       "amount": 4990,
     *       "amount_formatted": "R$ 49,90",
     *       "currency": "brl",
     *       "paid_at": "2026-10-01T10:00:00.000000Z",
     *       "due_date": "2026-10-01T00:00:00.000000Z",
     *       "period_start": "2026-10-01T00:00:00.000000Z",
     *       "period_end": "2026-11-01T00:00:00.000000Z",
     *       "hosted_invoice_url": "https://invoice.stripe.com/i/...",
     *       "invoice_pdf": "https://pay.stripe.com/invoice/.../pdf",
     *       "receipt_url": "https://pay.stripe.com/receipts/...",
     *       "created_at": "2026-10-01T10:00:00.000000Z"
     *     }}
     */
    public function show(int|string $id): JsonResponse
    {
        $payment = $this->service->findByUuid(request()->route('payment'));

        return (new PaymentResource($payment))->response();
    }

    /**
     * Listar faturas
     *
     * Histórico de cobranças do workspace, mais recentes primeiro. Os links levam para a fatura/recibo no Stripe.
     *
     * **Permissão:** `billing.view`
     *
     * @queryParam per_page integer Itens por página. Sem ele, vem tudo. Example: 15
     * @queryParam page integer Página. Example: 1
     * @queryParam status string Filtra pelo status. Enum: pending, processing, paid, failed, expired, void. Example: paid
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": "4d5e6f7a-...",
     *       "status": {"value": "paid", "label": "Pago"},
     *       "method": "card",
     *       "amount": 4990,
     *       "amount_formatted": "R$ 49,90",
     *       "currency": "brl",
     *       "paid_at": "2026-10-01T10:00:00.000000Z",
     *       "due_date": "2026-10-01T00:00:00.000000Z",
     *       "period_start": "2026-10-01T00:00:00.000000Z",
     *       "period_end": "2026-11-01T00:00:00.000000Z",
     *       "hosted_invoice_url": "https://invoice.stripe.com/i/...",
     *       "invoice_pdf": "https://pay.stripe.com/invoice/.../pdf",
     *       "receipt_url": "https://pay.stripe.com/receipts/...",
     *       "created_at": "2026-10-01T10:00:00.000000Z"
     *     }
     *   ],
     *   "links": {"first": "...", "last": "...", "prev": null, "next": null},
     *   "meta": {"current_page": 1, "last_page": 1, "per_page": 15, "total": 1}
     * }
     *
     * @responseField amount_formatted string Valor pronto para exibir.
     *
     * @response 403 scenario="sem permissão" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "billing.view"}
     */
    public function index(Request $request): Response
    {
        return parent::index($request);
    }
}
