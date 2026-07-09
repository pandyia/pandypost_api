<?php

namespace App\Http\Controllers\Api\Billing\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\Tenant\StorePaymentMethodRequest;
use App\Http\Resources\Billing\Tenant\PaymentMethodResource;
use App\Models\Workspace;
use App\Services\Billing\Tenant\PaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentMethodController extends Controller
{
    public function __construct(
        private readonly PaymentMethodService $service
    ) {
    }

    /**
     * SetupIntent para o Stripe.js coletar o cartão no front.
     */
    public function setupIntent(Request $request): JsonResponse
    {
        return response()->json([
            'client_secret' => $this->service->setupIntentSecret($this->workspace($request)),
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return PaymentMethodResource::collection($this->service->list($this->workspace($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = app(StorePaymentMethodRequest::class)->validated();

        $this->service->add(
            $this->workspace($request),
            $data['payment_method'],
            (bool) ($data['is_default'] ?? false),
        );

        return response()->json(['message' => 'Cartão adicionado com sucesso.'], 201);
    }

    public function update(Request $request, string $paymentMethod): JsonResponse
    {
        $this->service->setDefault($this->workspace($request), $paymentMethod);

        return response()->json(['message' => 'Cartão padrão atualizado com sucesso.']);
    }

    public function destroy(Request $request, string $paymentMethod): JsonResponse
    {
        $this->service->remove($this->workspace($request), $paymentMethod);

        return response()->json(['message' => 'Cartão removido com sucesso.']);
    }

    private function workspace(Request $request): Workspace
    {
        return $request->user()->resolveCurrentAccess()->workspace;
    }
}
