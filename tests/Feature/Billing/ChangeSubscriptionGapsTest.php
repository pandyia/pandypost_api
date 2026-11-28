<?php

use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Invoice;
use Stripe\StripeObject;

/*
|--------------------------------------------------------------------------
| Spec 024 - lacunas de QA (complemento de ChangeSubscriptionTest)
|--------------------------------------------------------------------------
| Stripe sempre simulado por Tests\Support\ChangeStripeFake; nada de rede.
*/

uses(RefreshDatabase::class);

const GAP_URL = '/api/billing/subscription/change';

function gapPending($sub, string $status = 'pending')
{
    return DB::table('subscription_changes')->where('subscription_id', $sub->id)->where('status', $status);
}

/** Faz o fake devolver pending_update (cobrança que o Stripe não aplicou) com a fatura/intent informados. */
function gapPendingUpdate($fake, $current, array $invoice): void
{
    $fake->respond('subscriptions.update', fn () => $fake->subscription($current->gateway_price_id, [
        'pending_update' => ['expires_at' => now()->addDay()->timestamp],
        'latest_invoice' => $invoice,
    ]));
}

describe('cobrança pendente: payment_failed x payment_action_required (RF-8, critérios 6 e 12)', function () {
    it('pending_update com payment intent embutido em requires_payment_method vira 402 payment_failed (sem action_url)', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user, , $sub, $fake] = chgScenario($current);
        gapPendingUpdate($fake, $current, [
            'id' => 'in_declined', 'hosted_invoice_url' => 'https://invoice.stripe.test/i/declined', 'status' => 'open',
            'payment_intent' => ['id' => 'pi_declined', 'status' => 'requires_payment_method'],
        ]);

        $response = $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $target->uuid])
            ->assertStatus(402)->assertJsonPath('error', 'payment_failed');

        expect($response->json())->not->toHaveKey('action_url')
            ->and($response->getContent())->not->toContain('pi_declined')->not->toContain('in_declined')
            ->and($sub->fresh()->stripe_price)->toBe($current->gateway_price_id)
            ->and($sub->fresh()->stripe_status)->toBe('active')
            ->and(Notification::where('type', 'subscription_changed')->count())->toBe(0);
    });

    it('latest_invoice e payment_intent só como ids: o service busca fatura e intent e devolve payment_failed', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user, , $sub, $fake] = chgScenario($current);
        $fake->respond('subscriptions.update', fn () => $fake->subscription($current->gateway_price_id, [
            'pending_update' => ['expires_at' => now()->addDay()->timestamp],
            'latest_invoice' => 'in_by_id',
        ]));
        $fake->respond('invoices.retrieve', Invoice::constructFrom(['id' => 'in_by_id', 'hosted_invoice_url' => 'https://invoice.stripe.test/i/x', 'payment_intent' => 'pi_by_id']));
        $fake->respond('paymentIntents.retrieve', StripeObject::constructFrom(['id' => 'pi_by_id', 'status' => 'requires_payment_method']));

        $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $target->uuid])
            ->assertStatus(402)->assertJsonPath('error', 'payment_failed');

        expect($fake->callsTo('invoices.retrieve'))->toHaveCount(1)
            ->and($fake->callsTo('paymentIntents.retrieve'))->toHaveCount(1)
            ->and($sub->fresh()->stripe_price)->toBe($current->gateway_price_id);
    });

    it('latest_invoice e payment_intent só como ids em requires_action: 402 payment_action_required com action_url', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user, , $sub, $fake] = chgScenario($current);
        $fake->respond('subscriptions.update', fn () => $fake->subscription($current->gateway_price_id, [
            'pending_update' => ['expires_at' => now()->addDay()->timestamp],
            'latest_invoice' => 'in_by_id',
        ]));
        $fake->respond('invoices.retrieve', Invoice::constructFrom(['id' => 'in_by_id', 'hosted_invoice_url' => 'https://invoice.stripe.test/i/3ds', 'payment_intent' => 'pi_by_id']));
        $fake->respond('paymentIntents.retrieve', StripeObject::constructFrom(['id' => 'pi_by_id', 'status' => 'requires_action']));

        $response = $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $target->uuid])
            ->assertStatus(402)->assertJsonPath('error', 'payment_action_required')
            ->assertJsonPath('action_url', 'https://invoice.stripe.test/i/3ds');

        expect($response->getContent())->not->toContain('pi_by_id')->not->toContain('in_by_id')
            ->and($sub->fresh()->stripe_price)->toBe($current->gateway_price_id);
    });

    it('3DS sem hosted_invoice_url ainda devolve payment_action_required (sem action_url, sem quebrar)', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user, , $sub, $fake] = chgScenario($current);
        gapPendingUpdate($fake, $current, ['id' => 'in_x', 'status' => 'open', 'payment_intent' => ['status' => 'requires_action']]);

        $response = $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $target->uuid])
            ->assertStatus(402)->assertJsonPath('error', 'payment_action_required');

        expect($response->json())->not->toHaveKey('action_url')
            ->and($sub->fresh()->stripe_price)->toBe($current->gateway_price_id);
    });

    it('falha ao buscar a fatura na distinção 3DS/recusa não vaza o erro do Stripe', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user, , , $fake] = chgScenario($current);
        $fake->respond('subscriptions.update', fn () => $fake->subscription($current->gateway_price_id, [
            'pending_update' => ['expires_at' => now()->addDay()->timestamp],
            'latest_invoice' => 'in_boom',
        ]));
        $fake->respond('invoices.retrieve', new ApiConnectionException('SEGREDO-STRIPE falha'));

        $response = $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $target->uuid])
            ->assertStatus(402);

        expect($response->getContent())->not->toContain('SEGREDO-STRIPE')->not->toContain('in_boom');
    });

    it('em trial, cartão recusado mantém o trial e o preço antigo (nada é gravado)', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user, , $sub, $fake] = chgScenario($current, 'trialing');
        $fake->respond('subscriptions.update', CardException::factory('recusado SEGREDO-STRIPE', 402, null, null, null, 'card_declined'));

        $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $target->uuid])
            ->assertStatus(402)->assertJsonPath('error', 'payment_failed');

        $fresh = $sub->fresh();
        expect($fresh->stripe_status)->toBe('trialing')
            ->and($fresh->trial_ends_at)->not->toBeNull()
            ->and($fresh->stripe_price)->toBe($current->gateway_price_id);
    });

    it('em trial a troca manda trial_end=now e fecha o trial localmente', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user, , $sub, $fake] = chgScenario($current, 'trialing');
        $fake->subscriptionStatus = 'active'; // o Stripe devolve ativa após encerrar o trial

        $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $target->uuid])->assertOk();

        expect($fake->callsTo('subscriptions.update')[0][1]['trial_end'])->toBe('now')
            ->and($sub->fresh()->trial_ends_at)->toBeNull()
            ->and($sub->fresh()->stripe_status)->toBe('active');
    });

    it('upgrade que falha na cobrança depois de liberar o agendamento: cobrança recusada, schedule fica canceled (divergência documentada)', function () {
        $current = chgPrice('Pro', 4990);
        $lower = chgPrice('Basic', 1990);
        $higher = chgPrice('Premium', 9990);
        [$user, , $sub, $fake] = chgScenario($current);
        chgPendingChange($sub, $lower, 'pending', 'sch_to_drop');
        $fake->respond('subscriptions.update', CardException::factory('x', 402, null, null, null, 'card_declined'));

        $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $higher->uuid])
            ->assertStatus(402)->assertJsonPath('error', 'payment_failed');

        expect(gapPending($sub)->count())->toBe(0)
            ->and(gapPending($sub, 'canceled')->count())->toBe(1)
            ->and($sub->fresh()->stripe_price)->toBe($current->gateway_price_id);
    });
});

describe('preço atual fora do catálogo local', function () {
    it('upgrade devolve 409 subscription_change_not_allowed sem tocar no Stripe', function () {
        $target = chgPrice('Pro', 4990);
        $ghost = chgPrice('Fantasma', 100);
        [$user, , $sub, $fake] = chgScenario($ghost);
        $sub->forceFill(['stripe_price' => 'price_que_nao_existe_localmente'])->save();

        $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $target->uuid])
            ->assertStatus(409)->assertJsonPath('error', 'subscription_change_not_allowed');

        expect($fake->calls)->toBe([])
            ->and($sub->fresh()->stripe_price)->toBe('price_que_nao_existe_localmente')
            ->and(gapPending($sub)->count())->toBe(0);
    });

    it('downgrade também devolve 409 e não cria agendamento', function () {
        $target = chgPrice('Basic', 1);
        $ghost = chgPrice('Fantasma', 100);
        [$user, , $sub, $fake] = chgScenario($ghost);
        $sub->forceFill(['stripe_price' => 'price_que_nao_existe_localmente'])->save();

        $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $target->uuid])
            ->assertStatus(409)->assertJsonPath('error', 'subscription_change_not_allowed');

        expect($fake->calls)->toBe([])->and(gapPending($sub)->count())->toBe(0);
    });

    it('assinatura com stripe_price nulo (vários itens) também não troca, sem erro 500', function () {
        $target = chgPrice('Pro', 4990);
        [$user, , $sub, $fake] = chgScenario(chgPrice('Basic', 2990));
        $sub->forceFill(['stripe_price' => null])->save();

        $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $target->uuid])
            ->assertStatus(409)->assertJsonPath('error', 'subscription_change_not_allowed');

        expect($fake->calls)->toBe([]);
    });

    it('preço atual inativo ou apagado (soft delete) no catálogo ainda permite trocar', function () {
        $current = chgPrice('Legado', 2990, 'monthly', 'brl', ['is_active' => false]);
        $target = chgPrice('Pro', 4990);
        [$user, , $sub] = chgScenario($current);

        $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $target->uuid])->assertOk();

        expect($sub->fresh()->stripe_price)->toBe($target->gateway_price_id);
    });
});

describe('mesmo downgrade já agendado (no-op)', function () {
    it('pedir de novo devolve 200, sem chamada ao Stripe, sem nova notificação e sem novo agendamento', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Basic', 1990);
        [$user, , $sub, $fake] = chgScenario($current);
        chgPendingChange($sub, $target, 'pending', 'sch_existing');

        $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $target->uuid])
            ->assertOk()
            ->assertJsonPath('data.pending_change.price.id', $target->uuid)
            ->assertJsonPath('data.price.id', $current->uuid);

        expect($fake->calls)->toBe([])
            ->and(gapPending($sub)->count())->toBe(1)
            ->and(gapPending($sub)->value('stripe_schedule_id'))->toBe('sch_existing')
            ->and(gapPending($sub, 'canceled')->count())->toBe(0)
            ->and(Notification::where('type', 'subscription_changed')->count())->toBe(0);
    });

    it('com agendamento para X, pedir downgrade para Y libera o schedule de X e agenda Y', function () {
        $current = chgPrice('Premium', 9990);
        $x = chgPrice('Pro', 4990);
        $y = chgPrice('Basic', 1990);
        [$user, , $sub, $fake] = chgScenario($current);
        chgPendingChange($sub, $x, 'pending', 'sch_x');

        $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $y->uuid])
            ->assertOk()->assertJsonPath('data.pending_change.price.id', $y->uuid);

        expect(collect($fake->callsTo('subscriptionSchedules.release'))->pluck(0))->toContain('sch_x')
            ->and(gapPending($sub)->count())->toBe(1)
            ->and(gapPending($sub)->first()->price_id)->toBe($y->id);
    });

    it('falha ao liberar o schedule antigo (500 do Stripe) vira 502 e mantém o agendamento antigo pending', function () {
        $current = chgPrice('Premium', 9990);
        $x = chgPrice('Pro', 4990);
        $y = chgPrice('Basic', 1990);
        [$user, , $sub, $fake] = chgScenario($current);
        chgPendingChange($sub, $x, 'pending', 'sch_x');
        $fake->respond('subscriptionSchedules.release', new ApiConnectionException('SEGREDO-STRIPE'));

        $response = $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $y->uuid])
            ->assertStatus(502)->assertJsonPath('error', 'gateway_error');

        expect($response->getContent())->not->toContain('SEGREDO-STRIPE')
            ->and(gapPending($sub)->count())->toBe(1)
            ->and(gapPending($sub)->first()->price_id)->toBe($x->id);
    });
});

describe('desfazer (DELETE) em casos de borda', function () {
    it('schedule já inexistente no Stripe (404) conta como liberado: 200 e linha canceled', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Basic', 1990);
        [$user, , $sub, $fake] = chgScenario($current);
        chgPendingChange($sub, $target, 'pending', 'sch_gone');
        $fake->respond('subscriptionSchedules.release', InvalidRequestException::factory('No such schedule', 404));

        $this->withToken($user->test_token)->deleteJson(GAP_URL)
            ->assertOk()->assertJsonPath('data.pending_change', null);

        expect(gapPending($sub)->count())->toBe(0)->and(gapPending($sub, 'canceled')->count())->toBe(1);
    });

    it('falha do Stripe ao liberar devolve 502 sem vazar mensagem e mantém o pending', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Basic', 1990);
        [$user, , $sub, $fake] = chgScenario($current);
        chgPendingChange($sub, $target, 'pending', 'sch_keep');
        $fake->respond('subscriptionSchedules.release', new ApiConnectionException('SEGREDO-STRIPE sch_keep'));

        $response = $this->withToken($user->test_token)->deleteJson(GAP_URL)
            ->assertStatus(502)->assertJsonPath('error', 'gateway_error');

        expect($response->getContent())->not->toContain('SEGREDO-STRIPE')->not->toContain('sch_keep')
            ->and(gapPending($sub)->count())->toBe(1);
    });

    it('desfazer duas vezes: a segunda devolve 404 no_pending_change', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Basic', 1990);
        [$user, , $sub] = chgScenario($current);
        chgPendingChange($sub, $target);

        $this->withToken($user->test_token)->deleteJson(GAP_URL)->assertOk();
        $this->withToken($user->test_token)->deleteJson(GAP_URL)->assertStatus(404)->assertJsonPath('error', 'no_pending_change');
    });

    it('workspace sem assinatura: DELETE devolve 404 no_pending_change', function () {
        $user = createUserWithPermissions(['billing.manage']);
        $fake = chgFakeStripe();

        $this->withToken($user->test_token)->deleteJson(GAP_URL)
            ->assertStatus(404)->assertJsonPath('error', 'no_pending_change');
        expect($fake->calls)->toBe([]);
    });
});

describe('ordem das validações e preço inativo', function () {
    it('preço inativo devolve 404 (como inexistente) sem tocar no Stripe', function () {
        $current = chgPrice('Basic', 2990);
        $inactive = chgPrice('Antigo', 4990, 'monthly', 'brl', ['is_active' => false]);
        [$user, , $sub, $fake] = chgScenario($current);

        $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $inactive->uuid])->assertStatus(404);

        expect($fake->calls)->toBe([])->and($sub->fresh()->stripe_price)->toBe($current->gateway_price_id);
    });

    it('preço apagado (soft delete) devolve 404', function () {
        $current = chgPrice('Basic', 2990);
        $deleted = chgPrice('Apagado', 4990);
        [$user, , , $fake] = chgScenario($current);
        $deleted->delete();

        $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $deleted->uuid])->assertStatus(404);
        expect($fake->calls)->toBe([]);
    });

    it('preço inativo tem precedência sobre assinatura inexistente e past_due (404 antes de 409)', function () {
        $inactive = chgPrice('Antigo', 4990, 'monthly', 'brl', ['is_active' => false]);
        [$user, , , $fake] = chgScenario(chgPrice('Basic', 2990), 'past_due');

        $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $inactive->uuid])->assertStatus(404);
        expect($fake->calls)->toBe([]);
    });

    it('past_due tem precedência sobre price_not_synced, same_price e moeda diferente (409 primeiro)', function () {
        $current = chgPrice('Basic', 2990);
        $unsynced = chgPrice('Premium', 9990, 'monthly', 'brl', ['gateway_price_id' => null]);
        $usd = chgPrice('Pro', 999, 'monthly', 'usd');
        [$user, , , $fake] = chgScenario($current, 'past_due');

        foreach ([$unsynced, $usd, $current] as $price) {
            $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $price->uuid])
                ->assertStatus(409)->assertJsonPath('error', 'subscription_change_not_allowed');
        }
        expect($fake->calls)->toBe([]);
    });

    it('price_not_synced tem precedência sobre moeda diferente', function () {
        $current = chgPrice('Basic', 2990, 'monthly', 'brl');
        $unsyncedUsd = chgPrice('Pro', 999, 'monthly', 'usd', ['gateway_price_id' => null]);
        [$user, , , $fake] = chgScenario($current);

        $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $unsyncedUsd->uuid])
            ->assertStatus(422)->assertJsonPath('error', 'price_not_synced');
        expect($fake->calls)->toBe([]);
    });

    it('moeda diferente em trial também é recusada sem tocar no Stripe', function () {
        $current = chgPrice('Basic', 2990, 'monthly', 'brl');
        $usd = chgPrice('Pro', 999, 'monthly', 'usd');
        [$user, , $sub, $fake] = chgScenario($current, 'trialing');

        $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $usd->uuid])
            ->assertStatus(422)->assertJsonPath('error', 'price_currency_mismatch');
        expect($fake->calls)->toBe([])->and($sub->fresh()->stripe_status)->toBe('trialing');
    });

    it('mesmo preço em trial também é 422 same_price', function () {
        $current = chgPrice('Basic', 2990);
        [$user, , , $fake] = chgScenario($current, 'trialing');

        $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $current->uuid])
            ->assertStatus(422)->assertJsonPath('error', 'same_price');
        expect($fake->calls)->toBe([]);
    });

    it('incomplete, unpaid e incomplete_expired também não trocam (só active/trialing)', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);

        foreach (['incomplete', 'unpaid', 'incomplete_expired', 'paused'] as $status) {
            [$user, , , $fake] = chgScenario($current, $status, ['billing.manage'], ['stripe_id' => 'sub_'.$status]);

            $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $target->uuid])
                ->assertStatus(409)->assertJsonPath('error', 'subscription_change_not_allowed');
            expect($fake->calls)->toBe([]);
        }
    });

    it('trialing com cancelamento agendado (ends_at) não troca', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user, , , $fake] = chgScenario($current, 'trialing', ['billing.manage'], ['ends_at' => now()->addDays(3)]);

        $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $target->uuid])
            ->assertStatus(409)->assertJsonPath('error', 'subscription_change_not_allowed');
        expect($fake->calls)->toBe([]);
    });

    it('price como array, inteiro ou vazio é 422 (não 500)', function () {
        [$user, , , $fake] = chgScenario(chgPrice());

        foreach ([['price' => ['x']], ['price' => 123], ['price' => '']] as $body) {
            $this->withToken($user->test_token)->postJson(GAP_URL, $body)->assertStatus(422)->assertJsonValidationErrors('price');
        }
        expect($fake->calls)->toBe([]);
    });

    it('a validação 422 roda depois da permissão: sem billing.manage e body inválido devolve 403', function () {
        [$user] = chgScenario(chgPrice(), 'active', ['billing.view']);

        $this->withToken($user->test_token)->postJson(GAP_URL, [])->assertForbidden();
    });
});

describe('usuário só com billing.view', function () {
    it('lê GET /billing/subscription com pending_change, mas não troca nem desfaz', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Basic', 1990);
        [$user, , $sub, $fake] = chgScenario($current, 'active', ['billing.view']);
        chgPendingChange($sub, $target);

        $this->withToken($user->test_token)->getJson('/api/billing/subscription')
            ->assertOk()->assertJsonPath('data.pending_change.price.id', $target->uuid);
        $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $target->uuid])->assertForbidden();
        $this->withToken($user->test_token)->deleteJson(GAP_URL)->assertForbidden();

        expect($fake->calls)->toBe([])->and(gapPending($sub)->count())->toBe(1);
    });

    it('usuário sem nenhuma permissão de billing recebe 403 em POST e DELETE', function () {
        [$user, , $sub, $fake] = chgScenario(chgPrice('Pro', 4990), 'active', []);

        $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => (string) Str::uuid()])->assertForbidden();
        $this->withToken($user->test_token)->deleteJson(GAP_URL)->assertForbidden();
        expect($fake->calls)->toBe([]);
    });
});

describe('nenhuma resposta vaza ids ou mensagens do Stripe', function () {
    it('respostas de sucesso e de erro não contêm ids do Stripe (sub_, si_, sch_, in_, pi_, cus_)', function () {
        $current = chgPrice('Pro', 4990);
        $higher = chgPrice('Premium', 9990);
        $lower = chgPrice('Basic', 1990);
        [$user, , $sub, $fake] = chgScenario($current);

        $bodies = [];
        $bodies[] = $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $lower->uuid])->assertOk()->getContent();
        $bodies[] = $this->withToken($user->test_token)->getJson('/api/billing/subscription')->assertOk()->getContent();
        $bodies[] = $this->withToken($user->test_token)->deleteJson(GAP_URL)->assertOk()->getContent();
        $bodies[] = $this->withToken($user->test_token)->deleteJson(GAP_URL)->assertStatus(404)->getContent();
        $bodies[] = $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $current->uuid])->assertStatus(422)->getContent();

        $fake->respond('subscriptions.update', CardException::factory('SEGREDO-STRIPE', 402, null, null, null, 'card_declined'));
        $bodies[] = $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $higher->uuid])->assertStatus(402)->getContent();

        $fake->respond('subscriptions.update', new ApiConnectionException('SEGREDO-STRIPE'));
        $bodies[] = $this->withToken($user->test_token)->postJson(GAP_URL, ['price' => $higher->uuid])->assertStatus(502)->getContent();

        foreach ($bodies as $body) {
            expect($body)->not->toMatch('/\b(sub|si|sch|in|pi|cus)_[A-Za-z0-9]/')
                ->and($body)->not->toContain('SEGREDO-STRIPE')
                ->and($body)->not->toContain($sub->stripe_id)
                ->and($body)->not->toContain($current->gateway_price_id);
        }
    });

    it('GET /billing/subscription com pending_change não expõe stripe_schedule_id', function () {
        $current = chgPrice('Pro', 4990);
        [$user, , $sub] = chgScenario($current);
        chgPendingChange($sub, chgPrice('Basic', 1990), 'pending', 'sch_secret_123');

        $body = $this->withToken($user->test_token)->getJson('/api/billing/subscription')->assertOk()->getContent();

        expect($body)->not->toContain('sch_secret_123')->not->toContain('stripe_schedule_id');
    });
});
