<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\Exception\ApiConnectionException;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

const CONFIRM_URL = '/api/billing/subscription/checkout/confirm';

function stripeSession(array $overrides = []): object
{
    $subscription = new class
    {
        public function toArray(): array
        {
            return [
                'id' => 'sub_confirm_1',
                'status' => 'active',
                'metadata' => [],
                'items' => ['data' => [[
                    'id' => 'si_1',
                    'quantity' => 1,
                    'price' => ['id' => 'price_test_1', 'product' => 'prod_1'],
                ]]],
            ];
        }
    };

    return (object) array_merge([
        'customer' => 'cus_confirm_1',
        'status' => 'complete',
        'subscription' => $subscription,
    ], $overrides);
}

function fakeStripeSessions(object $session): void
{
    $sessions = new class($session)
    {
        public function __construct(private object $session) {}

        public function retrieve(string $id, array $params = []): object
        {
            if ($this->session instanceof Throwable) {
                throw $this->session;
            }

            return $this->session;
        }
    };

    $client = new class($sessions) extends StripeClient
    {
        public object $checkout;

        public function __construct(object $sessions)
        {
            $this->checkout = (object) ['sessions' => $sessions];
        }
    };

    app()->bind(StripeClient::class, fn () => $client);
}

function confirmUser(array $permissions = ['billing.view', 'billing.manage']): array
{
    $user = createUserWithPermissions($permissions);
    $workspace = $user->resolveCurrentAccess()->workspace;
    $workspace->forceFill(['stripe_id' => 'cus_confirm_1'])->save();

    return [$user, $workspace];
}

describe('confirmar checkout', function () {
    it('cria a assinatura a partir da sessão do Stripe', function () {
        [$user, $workspace] = confirmUser();
        fakeStripeSessions(stripeSession());

        $this->withToken($user->test_token)
            ->postJson(CONFIRM_URL, ['session_id' => 'cs_test_123'])
            ->assertOk()
            ->assertJsonPath('data.stripe_status', 'active');

        expect($workspace->subscriptions()->where('stripe_id', 'sub_confirm_1')->count())->toBe(1);
    });

    it('é idempotente', function () {
        [$user, $workspace] = confirmUser();
        fakeStripeSessions(stripeSession());

        $this->withToken($user->test_token)->postJson(CONFIRM_URL, ['session_id' => 'cs_test_123'])->assertOk();
        $this->withToken($user->test_token)->postJson(CONFIRM_URL, ['session_id' => 'cs_test_123'])->assertOk();

        expect($workspace->subscriptions()->count())->toBe(1);
    });

    it('retorna 404 e não cria nada para sessão de outro workspace', function () {
        [$user, $workspace] = confirmUser();
        fakeStripeSessions(stripeSession(['customer' => 'cus_de_outro']));

        $this->withToken($user->test_token)
            ->postJson(CONFIRM_URL, ['session_id' => 'cs_test_123'])
            ->assertStatus(404)
            ->assertJsonPath('error', 'checkout_session_not_found');

        expect($workspace->subscriptions()->count())->toBe(0);
    });

    it('retorna 404 quando o checkout ainda não foi concluído', function () {
        [$user, $workspace] = confirmUser();
        fakeStripeSessions(stripeSession(['status' => 'open', 'subscription' => null]));

        $this->withToken($user->test_token)
            ->postJson(CONFIRM_URL, ['session_id' => 'cs_test_123'])
            ->assertStatus(404)
            ->assertJsonPath('error', 'no_active_subscription');

        expect($workspace->subscriptions()->count())->toBe(0);
    });

    it('retorna 502 sem expor a mensagem do Stripe quando o gateway falha', function () {
        [$user] = confirmUser();
        fakeStripeSessions(new ApiConnectionException('segredo interno do stripe'));

        $this->withToken($user->test_token)
            ->postJson(CONFIRM_URL, ['session_id' => 'cs_test_123'])
            ->assertStatus(502)
            ->assertJsonPath('error', 'gateway_error')
            ->assertDontSee('segredo interno');
    });

    it('valida o session_id', function () {
        [$user] = confirmUser();

        $this->withToken($user->test_token)->postJson(CONFIRM_URL, [])
            ->assertStatus(422)->assertJsonValidationErrors('session_id');
        $this->withToken($user->test_token)->postJson(CONFIRM_URL, ['session_id' => 'sub_x'])
            ->assertStatus(422)->assertJsonValidationErrors('session_id');
    });

    it('retorna 403 sem a permissão billing.manage', function () {
        [$user] = confirmUser(['billing.view']);

        $this->withToken($user->test_token)
            ->postJson(CONFIRM_URL, ['session_id' => 'cs_test_123'])
            ->assertStatus(403);
    });
});
