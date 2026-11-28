<?php

use App\Jobs\ProcessStripeWebhookJob;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Stripe\Collection;
use Stripe\Exception\InvalidRequestException;
use Stripe\Invoice;

/*
| Auditoria de segurança da spec 024 (mudança de assinatura). Complementa ChangeSubscriptionSecurityTest.
| Stripe sempre simulado (Tests\Support\ChangeStripeFake); nada de rede.
*/

uses(RefreshDatabase::class);

const AUDIT_CHANGE_URL = '/api/billing/subscription/change';

describe('spec 024 auditoria: catálogo', function () {
    it('preço de plano desativado (plan.is_active=false) não pode ser contratado por troca', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        $target->plan->update(['is_active' => false]);
        [$user, , $sub, $fake] = chgScenario($current);

        $this->withToken($user->test_token)->postJson(AUDIT_CHANGE_URL, ['price' => $target->uuid])
            ->assertNotFound();

        expect($fake->called('subscriptions.update'))->toBeFalse()
            ->and($sub->fresh()->stripe_price)->toBe($current->gateway_price_id);
    });

    it('preço de plano oculto da vitrine (is_visible=false) não pode ser contratado por troca', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Interno', 4990);
        $target->plan->update(['is_visible' => false]);
        [$user, , , $fake] = chgScenario($current);

        $this->withToken($user->test_token)->postJson(AUDIT_CHANGE_URL, ['price' => $target->uuid])
            ->assertNotFound();

        expect($fake->called('subscriptions.update'))->toBeFalse();
    })->skip('PERGUNTA-SUB-02: plano is_visible=false deve ser contratável por uuid? (decisão de negócio; hoje é, tanto na troca quanto no checkout).');
});

describe('spec 024 auditoria: webhook', function () {
    it('regressão: com STRIPE_WEBHOOK_SECRET configurado, evento sem assinatura é recusado e nada é enfileirado', function () {
        config(['cashier.webhook.secret' => 'whsec_teste']);
        Queue::fake();

        $this->postJson('/api/stripe/webhook', [
            'id' => 'evt_forjado', 'type' => 'subscription_schedule.released',
            'data' => ['object' => ['id' => 'sch_x', 'customer' => 'cus_x']],
        ])->assertForbidden();

        Queue::assertNotPushed(ProcessStripeWebhookJob::class);
    });

    it('sem STRIPE_WEBHOOK_SECRET a rota falha fechada (não aceita evento sem assinatura)', function () {
        config(['cashier.webhook.secret' => null]);
        Queue::fake();

        $response = $this->postJson('/api/stripe/webhook', [
            'id' => 'evt_forjado', 'type' => 'subscription_schedule.released',
            'data' => ['object' => ['id' => 'sch_x', 'customer' => 'cus_x']],
        ]);

        expect($response->getStatusCode())->toBeGreaterThanOrEqual(400);
        Queue::assertNotPushed(ProcessStripeWebhookJob::class);
    })->skip('VULN-SUB-03: Cashier só registra VerifyWebhookSignature se cashier.webhook.secret existir; STRIPE_WEBHOOK_SECRET nem consta no .env.example, então sem ele qualquer um forja eventos (agora também subscription_schedule.*).');
});

describe('spec 024 auditoria: estado da assinatura', function () {
    it('assinatura sem stripe_price (multi-item) não casa com preço não sincronizado e não troca', function () {
        $current = chgPrice('Pro', 4990);
        $unsynced = chgPrice('Rascunho', 100, 'monthly', 'brl', ['gateway_price_id' => null]);
        $target = chgPrice('Max', 9990);
        [$user, , $sub, $fake] = chgScenario($current, 'active', ['billing.view', 'billing.manage'], ['stripe_price' => null]);

        $this->withToken($user->test_token)->postJson(AUDIT_CHANGE_URL, ['price' => $target->uuid])
            ->assertStatus(409)->assertJsonPath('error', 'subscription_change_not_allowed');

        expect($fake->called('subscriptions.update'))->toBeFalse()
            ->and($unsynced->gateway_price_id)->toBeNull();
    });

    it('mudança pending já efetivada no Stripe (webhook perdido) não trava nem o desfazer nem uma nova troca', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Basic', 1990);
        $up = chgPrice('Max', 9990);
        [$user, , $sub, $fake] = chgScenario($current);
        chgPendingChange($sub, $target, 'pending', 'sch_velho');
        // Stripe recusa liberar schedule já concluído com 400 (não 404).
        $fake->respond('subscriptionSchedules.release', InvalidRequestException::factory('You cannot release a schedule that is completed.', 400));

        $this->withToken($user->test_token)->postJson(AUDIT_CHANGE_URL, ['price' => $up->uuid])->assertOk();

        expect(DB::table('subscription_changes')->where('stripe_schedule_id', 'sch_velho')->value('status'))->not->toBe('pending');
    })->skip('SUSPEITA-SUB-05: releaseSchedule só trata 404 como liberado; se o Stripe responder 400 para schedule concluído e o webhook se perder, o workspace fica preso em 502 (comportamento do Stripe não verificado, sem chamada real).');
});

describe('spec 024 auditoria: regressão de isolamento e vazamento', function () {
    it('desfazer libera só o schedule do workspace corrente, mesmo havendo agendamento em outro tenant', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Basic', 1990);
        [$userA, , $subA, $fake] = chgScenario($current);
        $userB = createUserWithPermissions(['billing.manage']);
        $wsB = Workspace::withoutGlobalScopes()->find($userB->currentAccess->workspace_id);
        $subB = chgSubscribe($wsB, $current, 'active', ['stripe_id' => 'sub_b']);
        chgPendingChange($subA, $target, 'pending', 'sch_a');
        chgPendingChange($subB, $target, 'pending', 'sch_b');

        $this->withToken($userA->test_token)->deleteJson(AUDIT_CHANGE_URL)->assertOk();

        expect(collect($fake->callsTo('subscriptionSchedules.release'))->pluck(0)->all())->toBe(['sch_a'])
            ->and(DB::table('subscription_changes')->where('stripe_schedule_id', 'sch_b')->value('status'))->toBe('pending');
    });

    it('GET da assinatura do workspace A não mostra o pending_change do workspace B', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Basic', 1990);
        [$userA] = chgScenario($current);
        $userB = createUserWithPermissions(['billing.manage']);
        $wsB = Workspace::withoutGlobalScopes()->find($userB->currentAccess->workspace_id);
        $subB = chgSubscribe($wsB, $current, 'active', ['stripe_id' => 'sub_b']);
        chgPendingChange($subB, $target, 'pending', 'sch_b');

        $this->withToken($userA->test_token)->getJson('/api/billing/subscription')
            ->assertOk()->assertJsonPath('data.pending_change', null);
    });

    it('o 402 de 3DS só traz action_url (sem ids de invoice/payment_intent) e a idempotency key não vaza na resposta', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user, , , $fake] = chgScenario($current);
        $invoice = ['id' => 'in_SEGREDO', 'hosted_invoice_url' => 'https://invoice.stripe.test/i/3ds', 'status' => 'open', 'payment_intent' => ['id' => 'pi_SEGREDO', 'status' => 'requires_action']];
        $fake->respond('subscriptions.update', fn () => $fake->subscription($current->gateway_price_id, [
            'pending_update' => ['expires_at' => now()->addDay()->timestamp],
            'latest_invoice' => $invoice,
        ]));
        $fake->respond('invoices.retrieve', Invoice::constructFrom($invoice));
        $fake->respond('invoices.all', Collection::constructFrom(['object' => 'list', 'data' => [$invoice]]));

        $body = $this->withToken($user->test_token)->postJson(AUDIT_CHANGE_URL, ['price' => $target->uuid])
            ->assertStatus(402)->getContent();

        expect($body)->not->toContain('in_SEGREDO')->not->toContain('pi_SEGREDO')->not->toContain('sub_chg_1')
            ->and(array_keys(json_decode($body, true)))->toEqualCanonicalizing(['error', 'message', 'action_url']);
    });

    it('billing.view sozinho não troca nem desfaz, e o 403 não chama o Stripe', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Max', 9990);
        [$user, , , $fake] = chgScenario($current, 'active', ['billing.view']);

        $this->withToken($user->test_token)->postJson(AUDIT_CHANGE_URL, ['price' => $target->uuid])->assertForbidden();
        $this->withToken($user->test_token)->deleteJson(AUDIT_CHANGE_URL)->assertForbidden();

        expect($fake->calls)->toBe([]);
    });

    it('uuid de preço mal formado (array, SQL, enorme) vira 422, nunca 500', function () {
        $current = chgPrice('Pro', 4990);
        [$user] = chgScenario($current);

        foreach ([['x'], "' OR 1=1 --", str_repeat('a', 5000), 123, null] as $bad) {
            $this->withToken($user->test_token)->postJson(AUDIT_CHANGE_URL, ['price' => $bad])->assertStatus(422);
        }
    });

    it('a chave de idempotência da troca imediata é por assinatura+preço (não vaza entre tenants)', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$userA, , $subA, $fakeA] = chgScenario($current);

        $this->withToken($userA->test_token)->postJson(AUDIT_CHANGE_URL, ['price' => $target->uuid])->assertOk();

        $key = $fakeA->callsTo('subscriptions.update')[0][2]['idempotency_key'] ?? '';
        expect($key)->toContain($subA->stripe_id)->toContain($target->uuid);
    });
});
