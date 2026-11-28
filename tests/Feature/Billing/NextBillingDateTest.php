<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Stripe\Exception\ApiConnectionException;
use Tests\Support\ChangeStripeFake;

uses(RefreshDatabase::class);

describe('GET /billing/subscription: next_billing_at', function () {
    beforeEach(function () {
        config(['services.stripe.enabled' => true]);
        Cache::flush();
    });

    it('em assinatura ativa traz o fim do período atual do Stripe', function () {
        $price = chgPrice('Pro', 4990);
        [$user] = chgScenario($price);

        $response = $this->withToken($user->test_token)->getJson('/api/billing/subscription')->assertOk();

        expect(Carbon\Carbon::parse($response->json('data.next_billing_at'))->timestamp)->toBe(ChangeStripeFake::PERIOD_END);
    });

    it('em teste grátis traz o fim do teste, sem chamar o Stripe', function () {
        $price = chgPrice('Pro', 4990);
        [$user, , $sub, $fake] = chgScenario($price, 'trialing');

        $response = $this->withToken($user->test_token)->getJson('/api/billing/subscription')->assertOk();

        expect(Carbon\Carbon::parse($response->json('data.next_billing_at'))->timestamp)->toBe($sub->fresh()->trial_ends_at->timestamp)
            ->and($fake->called('subscriptions.retrieve'))->toBeFalse();
    });

    it('assinatura que vai encerrar não tem próxima cobrança', function () {
        $price = chgPrice('Pro', 4990);
        [$user] = chgScenario($price, 'active', ['billing.view', 'billing.manage'], ['ends_at' => now()->addDays(5)]);

        $this->withToken($user->test_token)->getJson('/api/billing/subscription')
            ->assertOk()
            ->assertJsonPath('data.next_billing_at', null);
    });

    it('se o Stripe falhar, a tela continua funcionando com a data nula e sem vazar o erro', function () {
        $price = chgPrice('Pro', 4990);
        [$user, , , $fake] = chgScenario($price);
        $fake->respond('subscriptions.retrieve', new ApiConnectionException('boom: sk_test_secreto'));

        $response = $this->withToken($user->test_token)->getJson('/api/billing/subscription')->assertOk();

        expect($response->json('data.next_billing_at'))->toBeNull()
            ->and($response->getContent())->not->toContain('sk_test_secreto');
    });

    it('com o gateway desligado não consulta o Stripe', function () {
        config(['services.stripe.enabled' => false]);
        $price = chgPrice('Pro', 4990);
        [$user, , , $fake] = chgScenario($price);

        $this->withToken($user->test_token)->getJson('/api/billing/subscription')
            ->assertOk()
            ->assertJsonPath('data.next_billing_at', null);

        expect($fake->called('subscriptions.retrieve'))->toBeFalse();
    });

    it('consulta o Stripe uma vez só dentro da janela de cache', function () {
        $price = chgPrice('Pro', 4990);
        [$user, , , $fake] = chgScenario($price);

        $this->withToken($user->test_token)->getJson('/api/billing/subscription')->assertOk();
        $this->withToken($user->test_token)->getJson('/api/billing/subscription')->assertOk();

        expect($fake->callsTo('subscriptions.retrieve'))->toHaveCount(1);
    });
});
