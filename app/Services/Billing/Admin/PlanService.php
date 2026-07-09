<?php

namespace App\Services\Billing\Admin;

use App\Exceptions\BillingException;
use App\Models\Plan;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

class PlanService extends BaseService
{
    protected array $with = ['prices'];
    protected array $normalFilter = ['name', 'is_active', 'is_visible'];

    public function __construct(
        Plan $plan,
        private readonly StripeCatalogService $gateway,
    ) {
        parent::__construct($plan);
    }

    /**
     * Cria o Product no Stripe e persiste o plano local com o gateway_product_id.
     */
    public function createPlan(array $data): Plan
    {
        return DB::transaction(function () use ($data) {
            $productId = $this->gateway->createProduct(
                $data['name'],
                $data['description'] ?? null,
            );

            return Plan::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_visible' => $data['is_visible'] ?? true,
                'is_active' => $data['is_active'] ?? true,
                'gateway_product_id' => $productId,
            ]);
        });
    }

    /**
     * Atualiza dados do plano. A (des)ativação vem no mesmo update via is_active
     * e sincroniza com o Stripe (regras de assinatura + arquivamento).
     */
    public function updatePlan(Plan $plan, array $data): Plan
    {
        return DB::transaction(function () use ($plan, $data) {
            $isActive = $data['is_active'] ?? null;
            unset($data['is_active']);

            $syncName = array_key_exists('name', $data) && $data['name'] !== $plan->name;
            $syncDescription = array_key_exists('description', $data) && $data['description'] !== $plan->description;

            if (! empty($data)) {
                $plan->update($data);
            }

            if ($syncName || $syncDescription) {
                $this->gateway->updateProduct($plan->gateway_product_id, array_filter([
                    'name' => $plan->name,
                    'description' => $plan->description,
                ], fn ($value) => $value !== null));
            }

            if ($isActive !== null && (bool) $isActive !== (bool) $plan->is_active) {
                $isActive ? $this->activate($plan) : $this->deactivate($plan);
            }

            return $plan->refresh();
        });
    }

    /**
     * Reativa o plano localmente e no Stripe.
     */
    public function activate(Plan $plan): void
    {
        $this->gateway->updateProduct($plan->gateway_product_id, ['active' => true]);

        $plan->update(['is_active' => true]);
    }

    /**
     * Desativa o plano: bloqueia se houver assinaturas ativas, arquiva os preços
     * ativos e o produto no Stripe e marca o plano como inativo.
     */
    public function deactivate(Plan $plan): void
    {
        if ($plan->hasActiveSubscriptions()) {
            throw BillingException::planHasActiveSubscriptions();
        }

        $this->archiveOnGateway($plan);

        $plan->update(['is_active' => false]);
    }

    /**
     * Deleção conforme uso:
     * - assinaturas ativas → 409 (bloqueia);
     * - com histórico de assinaturas → soft delete;
     * - nunca teve assinaturas → hard delete.
     */
    public function deletePlan(Plan $plan): void
    {
        if ($plan->hasActiveSubscriptions()) {
            throw BillingException::planHasActiveSubscriptions();
        }

        DB::transaction(function () use ($plan) {
            $this->archiveOnGateway($plan);

            if ($plan->hasAnySubscriptions()) {
                $plan->update(['is_active' => false]);
                $plan->delete(); // soft delete
                return;
            }

            $plan->prices()->get()->each->forceDelete();
            $plan->forceDelete();
        });
    }

    /**
     * Arquiva no Stripe todos os preços ativos e o produto do plano.
     */
    private function archiveOnGateway(Plan $plan): void
    {
        $plan->prices()->where('is_active', true)->get()->each(function ($price) {
            $this->gateway->archivePrice($price->gateway_price_id);
            $price->update(['is_active' => false]);
        });

        $this->gateway->archiveProduct($plan->gateway_product_id);
    }
}
