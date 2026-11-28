<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Regressão: a API atual do Stripe recusa `phases[].iterations` ("Received unknown parameter"),
// o que derrubava todo downgrade com gateway_error. A fase do novo preço usa `duration`.
describe('downgrade: fase do novo plano no Subscription Schedule', function () {
    it('usa duration de um período do novo preço e nunca iterations', function (string $frequency, string $interval) {
        $current = chgPrice('Pro', 100000, $frequency === 'yearly' ? 'yearly' : 'monthly');
        $target = chgPrice('Basic', 1000, $frequency);
        [$user, , , $fake] = chgScenario($current);

        $this->withToken($user->test_token)->postJson('/api/billing/subscription/change', ['price' => $target->uuid])
            ->assertOk();

        $phases = $fake->callsTo('subscriptionSchedules.update')[0][1]['phases'];

        expect($phases)->toHaveCount(2)
            ->and($phases[1])->not->toHaveKey('iterations')
            ->and($phases[1]['duration'])->toBe(['interval' => $interval, 'interval_count' => 1]);
    })->with([
        'novo preço mensal' => ['monthly', 'month'],
        'novo preço anual' => ['yearly', 'year'],
    ]);
});
