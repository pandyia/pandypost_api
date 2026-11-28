<?php

use App\Enums\Exceptions\BillingError;
use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\Collection;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\CardException;
use Stripe\Invoice;

/*
|--------------------------------------------------------------------------
| Spec 024 - mudança de assinatura (fase vermelha)
|--------------------------------------------------------------------------
| PONTO DE SIMULACAO DO STRIPE (para o dev): `Tests\Support\ChangeStripeFake`, ligado ao container como
| `Stripe\StripeClient` por `chgFakeStripe()` (tests/Pest.php). Toda chamada ao Stripe deve sair de
| `Cashier::stripe()` / `$workspace->stripe()` / `app(StripeClient::class)`. O contrato de chamadas
| (swap = subscriptions.update; downgrade = subscriptionSchedules.create + update; desfazer = release/cancel;
| recusa = CardException; 3DS = pending_update + latest_invoice.hosted_invoice_url) está documentado no
| cabeçalho do fake. Nenhuma chamada real é feita.
*/

uses(RefreshDatabase::class);

const CHANGE_URL = '/api/billing/subscription/change';

function chgPending(Subscription $sub, string $status = 'pending')
{
    return DB::table('subscription_changes')->where('subscription_id', $sub->id)->where('status', $status);
}

describe('upgrade imediato', function () {
    it('mensal para um plano mais caro aplica na hora, com cobrança pro-rata (critério 1)', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user, , $sub, $fake] = chgScenario($current);

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])
            ->assertOk()
            ->assertJsonPath('data.price.id', $target->uuid)
            ->assertJsonPath('data.pending_change', null);

        expect($sub->fresh()->stripe_price)->toBe($target->gateway_price_id);

        $update = $fake->callsTo('subscriptions.update');
        expect($update)->toHaveCount(1)
            ->and($update[0][0])->toBe($sub->stripe_id)
            ->and(collect($update[0][1]['items'])->pluck('price')->filter()->all())->toContain($target->gateway_price_id)
            ->and($update[0][1]['proration_behavior'])->toBe('always_invoice')
            ->and($update[0][1]['payment_behavior'])->toBeIn(['pending_if_incomplete', 'error_if_incomplete']);
        expect($fake->called('subscriptionSchedules.create'))->toBeFalse();
    });

    it('envia chave de idempotência ao Stripe na troca imediata (plan decisão 5)', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user, , , $fake] = chgScenario($current);

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])->assertOk();

        $opts = $fake->callsTo('subscriptions.update')[0][2] ?? [];
        expect($opts['idempotency_key'] ?? null)->toBeString()->toStartWith('change:');
    });

    it('duplo clique no mesmo upgrade não cobra duas vezes (critério 9)', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user, , $sub, $fake] = chgScenario($current);

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])->assertOk();
        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])
            ->assertStatus(422)->assertJsonPath('error', 'same_price');

        expect($fake->callsTo('subscriptions.update'))->toHaveCount(1)
            ->and($sub->fresh()->stripe_price)->toBe($target->gateway_price_id);
    });

    it('mensal para anual aplica na hora mesmo com valor total maior ou menor por ciclo (RN-1a, critério 10)', function () {
        $current = chgPrice('Pro', 4990, 'monthly');
        $target = chgPrice('Pro', 49900, 'yearly');
        [$user, , $sub] = chgScenario($current);

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])
            ->assertOk()->assertJsonPath('data.pending_change', null);

        expect($sub->fresh()->stripe_price)->toBe($target->gateway_price_id)
            ->and(chgPending($sub)->count())->toBe(0);
    });

    it('upgrade com downgrade agendado descarta o agendamento e aplica o upgrade (RF-11, critério 8)', function () {
        $current = chgPrice('Pro', 4990);
        $lower = chgPrice('Basic', 1990);
        $higher = chgPrice('Premium', 9990);
        [$user, , $sub, $fake] = chgScenario($current);
        chgPendingChange($sub, $lower, 'pending', 'sch_old_1');

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $higher->uuid])
            ->assertOk()->assertJsonPath('data.price.id', $higher->uuid)->assertJsonPath('data.pending_change', null);

        $released = array_merge($fake->callsTo('subscriptionSchedules.release'), $fake->callsTo('subscriptionSchedules.cancel'));
        expect(collect($released)->pluck(0))->toContain('sch_old_1')
            ->and(chgPending($sub)->count())->toBe(0)
            ->and(chgPending($sub, 'canceled')->count())->toBe(1)
            ->and($sub->fresh()->stripe_price)->toBe($higher->gateway_price_id);
    });
});

describe('troca em trial', function () {
    it('qualquer troca encerra o trial e cobra o novo preço na hora (critério 3, RF-4)', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Basic', 1990); // mais barato: em trial ainda assim é imediata
        [$user, , $sub, $fake] = chgScenario($current, 'trialing');

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])
            ->assertOk()
            ->assertJsonPath('data.price.id', $target->uuid)
            ->assertJsonPath('data.on_trial', false)
            ->assertJsonPath('data.pending_change', null);

        $sub->refresh();
        expect($sub->stripe_price)->toBe($target->gateway_price_id)
            ->and($sub->trial_ends_at)->toBeNull()
            ->and($fake->callsTo('subscriptions.update')[0][1]['trial_end'])->toBe('now')
            ->and($fake->called('subscriptionSchedules.create'))->toBeFalse()
            ->and(chgPending($sub)->count())->toBe(0);
    });

    it('em trial a troca para anual também é imediata', function () {
        $current = chgPrice('Pro', 4990, 'monthly');
        $target = chgPrice('Pro', 49900, 'yearly');
        [$user, , $sub] = chgScenario($current, 'trialing');

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])->assertOk();

        expect($sub->fresh()->stripe_price)->toBe($target->gateway_price_id);
    });
});

describe('downgrade agendado', function () {
    it('mesma frequência com valor menor agenda para o fim do ciclo sem mudar o preço atual (critério 2)', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Basic', 1990);
        [$user, , $sub, $fake] = chgScenario($current);

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])
            ->assertOk()
            ->assertJsonPath('data.price.id', $current->uuid)
            ->assertJsonPath('data.pending_change.price.id', $target->uuid)
            ->assertJsonPath('data.pending_change.price.amount', 1990)
            ->assertJsonPath('data.pending_change.plan.name', 'Basic');

        $response = $this->withToken($user->test_token)->getJson('/api/billing/subscription')->assertOk();
        expect($response->json('data.pending_change.effective_at'))->toStartWith('2027-01-15');

        expect($sub->fresh()->stripe_price)->toBe($current->gateway_price_id)
            ->and($fake->called('subscriptions.update'))->toBeFalse();

        $create = $fake->callsTo('subscriptionSchedules.create');
        expect($create)->toHaveCount(1)->and($create[0][0]['from_subscription'])->toBe($sub->stripe_id);

        $phases = $fake->callsTo('subscriptionSchedules.update')[0][1]['phases'];
        expect($phases)->toHaveCount(2)
            ->and($phases[1]['items'][0]['price'])->toBe($target->gateway_price_id);

        $row = chgPending($sub)->first();
        expect($row)->not->toBeNull()
            ->and($row->price_id)->toBe($target->id)
            ->and($row->stripe_schedule_id)->toStartWith('sch_fake_');
    });

    it('anual para mensal é agendado para o fim do ciclo (RN-1b, critério 10)', function () {
        $current = chgPrice('Pro', 49900, 'yearly');
        $target = chgPrice('Pro', 4990, 'monthly');
        [$user, , $sub, $fake] = chgScenario($current);

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])
            ->assertOk()->assertJsonPath('data.pending_change.price.id', $target->uuid);

        expect($sub->fresh()->stripe_price)->toBe($current->gateway_price_id)
            ->and($fake->called('subscriptions.update'))->toBeFalse()
            ->and(chgPending($sub)->count())->toBe(1);
    });

    it('um novo downgrade substitui o agendado: fica só um pending, para o novo preço (RF-9, critério 8)', function () {
        $current = chgPrice('Premium', 9990);
        $mid = chgPrice('Pro', 4990);
        $low = chgPrice('Basic', 1990);
        [$user, , $sub] = chgScenario($current);

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $mid->uuid])->assertOk();
        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $low->uuid])
            ->assertOk()->assertJsonPath('data.pending_change.price.id', $low->uuid);

        expect(chgPending($sub)->count())->toBe(1)
            ->and(chgPending($sub)->first()->price_id)->toBe($low->id);
    });

    it('duplo clique no mesmo downgrade mantém um único agendamento', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Basic', 1990);
        [$user, , $sub] = chgScenario($current);

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])->assertOk();
        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])->assertOk();

        expect(chgPending($sub)->count())->toBe(1);
    });
});

describe('desfazer downgrade agendado', function () {
    it('libera o schedule no Stripe e limpa pending_change (RF-7, critério 8)', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Basic', 1990);
        [$user, , $sub, $fake] = chgScenario($current);
        chgPendingChange($sub, $target, 'pending', 'sch_undo_1');

        $this->withToken($user->test_token)->deleteJson(CHANGE_URL)
            ->assertOk()
            ->assertJsonPath('data.pending_change', null)
            ->assertJsonPath('data.price.id', $current->uuid);

        $released = array_merge($fake->callsTo('subscriptionSchedules.release'), $fake->callsTo('subscriptionSchedules.cancel'));
        expect(collect($released)->pluck(0))->toContain('sch_undo_1')
            ->and(chgPending($sub)->count())->toBe(0)
            ->and(chgPending($sub, 'canceled')->count())->toBe(1);
    });

    it('sem mudança agendada devolve 404 no_pending_change e não chama o Stripe', function () {
        $current = chgPrice('Pro', 4990);
        [$user, , , $fake] = chgScenario($current);

        $this->withToken($user->test_token)->deleteJson(CHANGE_URL)
            ->assertStatus(404)->assertJsonPath('error', 'no_pending_change');

        expect($fake->calls)->toBe([]);
    });

    it('GET /billing/subscription expõe a mudança agendada (RF-6)', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Basic', 1990);
        [$user, , $sub] = chgScenario($current);

        $this->withToken($user->test_token)->getJson('/api/billing/subscription')
            ->assertOk()->assertJsonPath('data.pending_change', null);

        chgPendingChange($sub, $target);

        $this->withToken($user->test_token)->getJson('/api/billing/subscription')
            ->assertOk()
            ->assertJsonPath('data.pending_change.price.id', $target->uuid)
            ->assertJsonPath('data.pending_change.plan.id', $target->plan->uuid);
    });

    it('mudança já aplicada ou cancelada não aparece como pending_change', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Basic', 1990);
        [$user, , $sub] = chgScenario($current);
        chgPendingChange($sub, $target, 'applied', 'sch_a');
        chgPendingChange($sub, $target, 'canceled', 'sch_b');

        $this->withToken($user->test_token)->getJson('/api/billing/subscription')
            ->assertOk()->assertJsonPath('data.pending_change', null);
    });
});

describe('falha de cobrança no upgrade', function () {
    it('cartão recusado devolve 402 payment_failed e a assinatura segue no preço antigo (critério 6)', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user, , $sub, $fake] = chgScenario($current);
        $fake->respond('subscriptions.update', CardException::factory('Mensagem crua SEGREDO-STRIPE do emissor', 402, null, null, null, 'card_declined'));

        $response = $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])
            ->assertStatus(402)->assertJsonPath('error', 'payment_failed');

        expect($response->getContent())->not->toContain('SEGREDO-STRIPE')
            ->and($sub->fresh()->stripe_price)->toBe($current->gateway_price_id)
            ->and($sub->fresh()->stripe_status)->toBe('active');
    });

    it('3DS devolve 402 payment_action_required com action_url e mantém o preço antigo (critério 12)', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user, , $sub, $fake] = chgScenario($current);
        $invoice = ['id' => 'in_3ds', 'hosted_invoice_url' => 'https://invoice.stripe.test/i/3ds', 'status' => 'open', 'payment_intent' => ['status' => 'requires_action']];
        $fake->respond('subscriptions.update', fn () => $fake->subscription($current->gateway_price_id, [
            'pending_update' => ['expires_at' => now()->addDay()->timestamp],
            'latest_invoice' => $invoice,
        ]));
        $fake->respond('invoices.retrieve', Invoice::constructFrom($invoice));
        $fake->respond('invoices.all', Collection::constructFrom(['object' => 'list', 'data' => [$invoice]]));

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])
            ->assertStatus(402)
            ->assertJsonPath('error', 'payment_action_required')
            ->assertJsonPath('action_url', 'https://invoice.stripe.test/i/3ds');

        expect($sub->fresh()->stripe_price)->toBe($current->gateway_price_id)
            ->and($sub->fresh()->stripe_status)->toBe('active');
    });

    it('falha geral do Stripe vira 502 gateway_error sem expor a mensagem', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user, , $sub, $fake] = chgScenario($current);
        $fake->respond('subscriptions.update', new ApiConnectionException('timeout interno SEGREDO-STRIPE'));

        $response = $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])
            ->assertStatus(502)->assertJsonPath('error', 'gateway_error');

        expect($response->getContent())->not->toContain('SEGREDO-STRIPE')
            ->and($sub->fresh()->stripe_price)->toBe($current->gateway_price_id);
    });

    it('falha ao criar o schedule do downgrade vira 502 e não deixa linha pending', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Basic', 1990);
        [$user, , $sub, $fake] = chgScenario($current);
        $fake->respond('subscriptionSchedules.create', new ApiConnectionException('fora'));

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])
            ->assertStatus(502)->assertJsonPath('error', 'gateway_error');

        expect(chgPending($sub)->count())->toBe(0);
    });
});

describe('validação e regras antes do Stripe', function () {
    it('exige price (422)', function () {
        [$user, , , $fake] = chgScenario(chgPrice());

        $this->withToken($user->test_token)->postJson(CHANGE_URL, [])
            ->assertStatus(422)->assertJsonValidationErrors('price');
        expect($fake->calls)->toBe([]);
    });

    it('price precisa ser uuid (422)', function () {
        [$user, , , $fake] = chgScenario(chgPrice());

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => 'nao-e-uuid'])
            ->assertStatus(422)->assertJsonValidationErrors('price');
        expect($fake->calls)->toBe([]);
    });

    it('uuid de preço inexistente devolve 404 (critério 4)', function () {
        [$user, , $sub, $fake, $current] = chgScenario(chgPrice());

        $response = $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => (string) Str::uuid()])
            ->assertStatus(404);

        // 404 do recurso (preço), não de rota inexistente
        expect(strtolower((string) $response->json('message')))->not->toContain('route');

        expect($fake->calls)->toBe([])
            ->and($sub->fresh()->stripe_price)->toBe($current->gateway_price_id);
    });

    it('mesmo preço devolve 422 same_price sem chamar o Stripe (critério 4)', function () {
        $current = chgPrice();
        [$user, , , $fake] = chgScenario($current);

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $current->uuid])
            ->assertStatus(422)->assertJsonPath('error', 'same_price');
        expect($fake->calls)->toBe([]);
    });

    it('preço de outra moeda devolve 422 price_currency_mismatch (critério 4)', function () {
        $current = chgPrice('Pro', 4990, 'monthly', 'brl');
        $usd = chgPrice('Pro', 999, 'monthly', 'usd');
        [$user, , $sub, $fake] = chgScenario($current);

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $usd->uuid])
            ->assertStatus(422)->assertJsonPath('error', 'price_currency_mismatch');

        expect($fake->calls)->toBe([])
            ->and($sub->fresh()->stripe_price)->toBe($current->gateway_price_id)
            ->and(chgPending($sub)->count())->toBe(0);
    });

    it('preço sem gateway_price_id devolve 422 price_not_synced', function () {
        $current = chgPrice();
        $unsynced = chgPrice('Premium', 9990, 'monthly', 'brl', ['gateway_price_id' => null]);
        [$user, , , $fake] = chgScenario($current);

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $unsynced->uuid])
            ->assertStatus(422)->assertJsonPath('error', 'price_not_synced');
        expect($fake->calls)->toBe([]);
    });

    it('workspace sem assinatura devolve 404 no_active_subscription', function () {
        $target = chgPrice();
        $user = createUserWithPermissions(['billing.manage']);
        $fake = chgFakeStripe();

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])
            ->assertStatus(404)->assertJsonPath('error', 'no_active_subscription');
        expect($fake->calls)->toBe([]);
    });

    it('past_due devolve 409 subscription_change_not_allowed sem tocar no Stripe (critério 5, RN-3)', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user, , $sub, $fake] = chgScenario($current, 'past_due');

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])
            ->assertStatus(409)->assertJsonPath('error', 'subscription_change_not_allowed');

        expect($fake->calls)->toBe([])
            ->and($sub->fresh()->stripe_price)->toBe($current->gateway_price_id);
    });

    it('cancelada com acesso até o fim do ciclo devolve 409 subscription_change_not_allowed (critério 5, RN-3)', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user, , $sub, $fake] = chgScenario($current, 'active', ['billing.view', 'billing.manage'], ['ends_at' => now()->addDays(10)]);

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])
            ->assertStatus(409)->assertJsonPath('error', 'subscription_change_not_allowed');

        expect($fake->calls)->toBe([])
            ->and($sub->fresh()->stripe_price)->toBe($current->gateway_price_id)
            ->and($sub->fresh()->ends_at)->not->toBeNull();
    });

    it('assinatura já encerrada (canceled) também não troca', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user, , , $fake] = chgScenario($current, 'canceled', ['billing.view', 'billing.manage'], ['ends_at' => now()->subDay()]);

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])
            ->assertStatus(409)->assertJsonPath('error', 'subscription_change_not_allowed');
        expect($fake->calls)->toBe([]);
    });
});

describe('erros de domínio (BillingError)', function () {
    it('os 6 códigos novos existem com HTTP e mensagem pt-BR fixa', function () {
        $expected = [
            'subscription_change_not_allowed' => 409,
            'same_price' => 422,
            'price_currency_mismatch' => 422,
            'payment_failed' => 402,
            'payment_action_required' => 402,
            'no_pending_change' => 404,
        ];

        foreach ($expected as $code => $http) {
            $error = BillingError::tryFrom($code);
            expect($error)->not->toBeNull("código $code ausente")
                ->and($error->httpCode())->toBe($http)
                ->and($error->message())->toBeString()->not->toBeEmpty();
        }
    });
});

describe('permissão, autenticação e isolamento', function () {
    it('sem token devolve 401 (POST e DELETE)', function () {
        $this->postJson(CHANGE_URL, ['price' => (string) Str::uuid()])->assertUnauthorized();
        $this->deleteJson(CHANGE_URL)->assertUnauthorized();
    });

    it('sem billing.manage devolve 403 e nada muda (só billing.view não basta)', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user, , $sub, $fake] = chgScenario($current, 'active', ['billing.view']);
        chgPendingChange($sub, chgPrice('Zero', 990));

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])->assertForbidden();
        $this->withToken($user->test_token)->deleteJson(CHANGE_URL)->assertForbidden();

        expect($fake->calls)->toBe([])
            ->and($sub->fresh()->stripe_price)->toBe($current->gateway_price_id)
            ->and(chgPending($sub)->count())->toBe(1);
    });

    it('super admin ignora a permissão (RN-5)', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        $admin = createSuperAdmin();
        $workspace = Workspace::withoutGlobalScopes()->find($admin->currentAccess->workspace_id);
        $sub = chgSubscribe($workspace, $current);
        chgFakeStripe($current);

        $this->withToken($admin->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])->assertOk();

        expect($sub->fresh()->stripe_price)->toBe($target->gateway_price_id);
    });

    it('assinatura de outro workspace nunca é afetada: quem não assina recebe 404 e nada muda', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [, , $subB, $fake] = chgScenario($current); // workspace B com assinatura e agendamento
        $pendingTarget = chgPrice('Zero', 990);
        chgPendingChange($subB, $pendingTarget);
        $userA = createUserWithPermissions(['billing.view', 'billing.manage']); // workspace A sem assinatura

        $this->withToken($userA->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])
            ->assertStatus(404)->assertJsonPath('error', 'no_active_subscription');
        $this->withToken($userA->test_token)->deleteJson(CHANGE_URL)
            ->assertStatus(404)->assertJsonPath('error', 'no_pending_change');

        expect($fake->calls)->toBe([])
            ->and($subB->fresh()->stripe_price)->toBe($current->gateway_price_id)
            ->and(chgPending($subB)->count())->toBe(1)
            ->and(chgPending($subB)->first()->status)->toBe('pending');
    });

    it('com assinaturas nos dois workspaces, trocar no A não toca no B', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$userA, , $subA] = chgScenario($current);

        $userB = createUserWithPermissions(['billing.view', 'billing.manage']);
        $wsB = Workspace::withoutGlobalScopes()->find($userB->currentAccess->workspace_id);
        $subB = chgSubscribe($wsB, $current, 'active', ['stripe_id' => 'sub_chg_B']);
        $pendingB = chgPendingChange($subB, chgPrice('Zero', 990), 'pending', 'sch_B');

        $this->withToken($userA->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])->assertOk();

        expect($subA->fresh()->stripe_price)->toBe($target->gateway_price_id)
            ->and($subB->fresh()->stripe_price)->toBe($current->gateway_price_id)
            ->and(DB::table('subscription_changes')->where('id', $pendingB)->value('status'))->toBe('pending');

        // desfazer no A (sem agendamento) não pode enxergar o agendamento do B
        $this->withToken($userA->test_token)->deleteJson(CHANGE_URL)->assertStatus(404)->assertJsonPath('error', 'no_pending_change');
        expect(DB::table('subscription_changes')->where('id', $pendingB)->value('status'))->toBe('pending');

        // e o GET do A não mostra o pending do B
        $this->withToken($userA->test_token)->getJson('/api/billing/subscription')->assertOk()->assertJsonPath('data.pending_change', null);
    });
});

describe('migration subscription_changes', function () {
    it('índice único parcial: no máximo um pending por assinatura, mas histórico livre', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Basic', 1990);
        [, , $sub] = chgScenario($current);

        chgPendingChange($sub, $target, 'applied', 'sch_1');
        chgPendingChange($sub, $target, 'canceled', 'sch_2');
        chgPendingChange($sub, $target, 'pending', 'sch_3');

        expect(fn () => chgPendingChange($sub, $target, 'pending', 'sch_4'))->toThrow(QueryException::class);
        expect(chgPending($sub)->count())->toBe(1);
    });

    it('stripe_schedule_id é único', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Basic', 1990);
        [, , $sub] = chgScenario($current);

        chgPendingChange($sub, $target, 'applied', 'sch_dup');

        expect(fn () => chgPendingChange($sub, $target, 'canceled', 'sch_dup'))->toThrow(QueryException::class);
    });
});

describe('notificação subscription_changed (upgrade e agendamento)', function () {
    function chgBillingMembers(Workspace $workspace): array
    {
        $viewer = addUserToWorkspace($workspace);
        $viewer->currentAccess->role->permissions()->sync([permission('billing.view')->id]);
        $semBilling = addUserToWorkspace($workspace);

        return [$viewer, $semBilling];
    }

    it('o tipo existe no enum, na família billing e na central', function () {
        $type = NotificationType::tryFrom('subscription_changed');

        expect($type)->not->toBeNull()
            ->and($type->family())->toBe('billing')
            ->and($type->isCentral())->toBeTrue();
    });

    it('upgrade aplicado notifica quem tem billing, uma vez por pessoa, e ninguém sem permissão', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user, $workspace] = chgScenario($current);
        [$viewer, $semBilling] = chgBillingMembers($workspace);

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])->assertOk();

        $ids = Notification::where('type', 'subscription_changed')->pluck('notifiable_id')->sort()->values()->all();
        expect($ids)->toBe(collect([$user->id, $viewer->id])->sort()->values()->all())
            ->and(Notification::where('notifiable_id', $semBilling->id)->count())->toBe(0);
    });

    it('downgrade agendado notifica quem tem billing', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Basic', 1990);
        [$user, $workspace] = chgScenario($current);
        [$viewer] = chgBillingMembers($workspace);

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])->assertOk();

        $ids = Notification::where('type', 'subscription_changed')->pluck('notifiable_id')->sort()->values()->all();
        expect($ids)->toBe(collect([$user->id, $viewer->id])->sort()->values()->all());
    });

    it('troca recusada (validação ou cobrança) não notifica', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user, , , $fake] = chgScenario($current);
        $fake->respond('subscriptions.update', CardException::factory('recusado', 402, null, null, null, 'card_declined'));

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])->assertStatus(402);
        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $current->uuid])->assertStatus(422);

        expect(Notification::where('type', 'subscription_changed')->count())->toBe(0);
    });

    it('billing é fixo (spec 007): preferências e avisos no app desligados não impedem a notificação', function () {
        // Ver pergunta no relatório: o critério 11 fala em "preferência desligada", mas a spec 007 não tem toggle de billing.
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$user] = chgScenario($current);
        User::whereKey($user->id)->update(['notification_preferences' => json_encode([
            'invite' => false, 'posts' => false, 'social_accounts' => false, 'post_published' => false,
            'in_app' => ['invite' => false, 'posts' => false, 'social_accounts' => false, 'post_published' => false],
        ])]);

        $this->withToken($user->test_token)->postJson(CHANGE_URL, ['price' => $target->uuid])->assertOk();

        expect(Notification::where('type', 'subscription_changed')->where('notifiable_id', $user->id)->count())->toBe(1);
    });
});
