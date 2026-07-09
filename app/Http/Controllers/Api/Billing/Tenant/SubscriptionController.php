<?php

namespace App\Http\Controllers\Api\Billing\Tenant;

use App\Exceptions\BillingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\Tenant\StoreCheckoutRequest;
use App\Http\Resources\Billing\Tenant\SubscriptionResource;
use App\Models\Price;
use App\Models\Workspace;
use App\Services\Billing\Tenant\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout
    ) {
    }

    /**
     * Assinatura atual do workspace.
     */
    public function current(Request $request): JsonResponse
    {
        $subscription = $this->workspace($request)->subscription('default');

        if (! $subscription) {
            throw BillingException::noActiveSubscription();
        }

        return (new SubscriptionResource($subscription))->response();
    }

    /**
     * Inicia o Stripe Checkout e retorna a URL hospedada.
     */
    public function checkout(Request $request): JsonResponse
    {
        $data = app(StoreCheckoutRequest::class)->validated();
        $price = Price::where('uuid', $data['price'])->firstOrFail();

        $url = $this->checkout->start($this->workspace($request), $price);

        return response()->json(['checkout_url' => $url]);
    }

    private function workspace(Request $request): Workspace
    {
        return $request->user()->resolveCurrentAccess()->workspace;
    }
}
