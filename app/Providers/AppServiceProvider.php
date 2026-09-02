<?php

namespace App\Providers;

use App\Models\Subscription;
use App\Models\Workspace;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // O Cashier registra sozinho um POST /stripe/webhook com o nome
        // `cashier.webhook`, e routes/api.php declara o mesmo nome em
        // /api/stripe/webhook. Sem cache de rotas o Laravel tolera o nome
        // duplicado, mas `artisan route:cache` aborta com LogicException — ou
        // seja, produção estava rodando sem cache de rotas. Como a rota do app
        // é a única divulgada no dashboard do Stripe (e a única que passa pelo
        // pipeline de middleware da API), desligamos o registro do pacote.
        if (class_exists(\Laravel\Cashier\Cashier::class)) {
            \Laravel\Cashier\Cashier::ignoreRoutes();
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super admin bypass all permissions
        Gate::before(function ($user, $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }
        });

        $this->configureCashier();
    }

    /**
     * Configuração do Laravel Cashier (billing).
     *
     * Guardado por class_exists para que a aplicação continue subindo mesmo
     * antes de `composer require laravel/cashier` (ex.: durante o setup da fase).
     */
    private function configureCashier(): void
    {
        if (! class_exists(\Laravel\Cashier\Cashier::class)) {
            return;
        }

        // O tenant (Workspace) é o Billable; a Subscription usa nossa subclasse.
        \Laravel\Cashier\Cashier::useCustomerModel(Workspace::class);
        \Laravel\Cashier\Cashier::useSubscriptionModel(Subscription::class);

        // Dunning simplificado: durante `past_due` (janela de retentativas do
        // Stripe) a assinatura permanece válida — o bloqueio só ocorre quando o
        // Stripe cancela após esgotar as retentativas.
        \Laravel\Cashier\Cashier::keepPastDueSubscriptionsActive();

        // Efeitos custom do webhook (tabela Payment) via listener do Cashier.
        Event::listen(
            \Laravel\Cashier\Events\WebhookReceived::class,
            \App\Listeners\HandleStripeWebhook::class,
        );
    }
}
