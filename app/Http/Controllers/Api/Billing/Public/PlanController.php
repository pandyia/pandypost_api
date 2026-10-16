<?php

namespace App\Http\Controllers\Api\Billing\Public;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Billing\Public\PlanCollection;
use App\Services\Billing\Public\PlanService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Planos (público)
 *
 * Vitrine de planos para a landing page e a tela de assinatura.
 */
class PlanController extends BaseController
{
    // Endpoint público: a landing page consome os planos ativos sem autenticação.
    // Sem $permissionGroup, o BaseController não registra o middleware de permissão.

    public function __construct(
        private PlanService $service
    ) {
        parent::__construct($service);
    }

    /**
     * Listar planos
     *
     * Planos ativos e visíveis, com os preços ativos (do menor para o maior). Não precisa de login.
     * Para assinar, envie o `id` do **preço** em `POST /billing/subscription/checkout`.
     *
     * Limite: 45 requisições por minuto.
     *
     * @unauthenticated
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": "1a2b3c4d-...",
     *       "name": "Pro",
     *       "description": "Para criadores que postam toda semana",
     *       "prices": [
     *         {"id": "9f8e7d6c-...", "amount": 4990, "currency": "brl", "frequency": "monthly", "trial_period_days": 7},
     *         {"id": "8e7d6c5b-...", "amount": 49900, "currency": "brl", "frequency": "yearly", "trial_period_days": 7}
     *       ]
     *     }
     *   ],
     *   "meta": {"total": 1}
     * }
     *
     * @responseField amount integer Valor em centavos.
     * @responseField frequency string `monthly` ou `yearly`.
     * @responseField currency string `brl`, `usd`, `eur` ou `gbp`.
     */
    public function index(Request $request): Response
    {
        $plans = $this->service->getActivePlans();

        return (new PlanCollection($plans))->response();
    }
}
