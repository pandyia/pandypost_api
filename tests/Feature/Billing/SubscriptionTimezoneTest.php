<?php

use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Regressão: datas do Stripe chegam como timestamp e o Carbon 3 as cria em UTC. Gravadas assim,
// o banco guardava a hora UTC e a leitura a interpretava no fuso do app (America/Sao_Paulo),
// deixando a assinatura "em teste" por mais 3 horas depois que o teste já tinha acabado.
describe('datas da assinatura no fuso do app', function () {
    beforeEach(function () {
        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('America/Sao_Paulo');
    });

    afterEach(function () {
        date_default_timezone_set($this->previousTimezone);
    });

    function subscriptionWithDates(array $dates): Subscription
    {
        $workspace = createUserWithPermissions([])->resolveCurrentAccess()->workspace;

        $subscription = Subscription::forceCreate([
            'workspace_id' => $workspace->id,
            'type' => 'default',
            'stripe_id' => 'sub_tz_'.uniqid(),
            'stripe_status' => 'active',
            'stripe_price' => 'price_tz',
            'quantity' => 1,
        ]);

        // Mesma forma que o Cashier e o app gravam: Carbon criado a partir de timestamp do Stripe
        foreach ($dates as $field => $timestamp) {
            $subscription->{$field} = Carbon::createFromTimestamp($timestamp);
        }
        $subscription->save();

        return $subscription->fresh();
    }

    it('teste que terminou há minutos não conta mais como teste', function () {
        $endedAt = now()->subMinutes(4)->timestamp;

        $subscription = subscriptionWithDates(['trial_ends_at' => $endedAt]);

        expect($subscription->trial_ends_at->timestamp)->toBe($endedAt)
            ->and($subscription->onTrial())->toBeFalse()
            ->and($subscription->billing_status)->toBe('active');
    });

    it('teste que ainda vai terminar continua como teste', function () {
        $endsAt = now()->addHours(2)->timestamp;

        $subscription = subscriptionWithDates(['trial_ends_at' => $endsAt]);

        expect($subscription->trial_ends_at->timestamp)->toBe($endsAt)
            ->and($subscription->onTrial())->toBeTrue()
            ->and($subscription->billing_status)->toBe('trialing');
    });

    it('fim da assinatura (ends_at) mantém o mesmo instante', function () {
        $endsAt = now()->addDays(3)->timestamp;

        $subscription = subscriptionWithDates(['ends_at' => $endsAt]);

        expect($subscription->ends_at->timestamp)->toBe($endsAt);
    });
});
