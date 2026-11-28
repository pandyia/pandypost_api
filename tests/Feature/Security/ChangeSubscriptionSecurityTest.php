<?php

use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
| Spec 024, tarefa 12 / RF-10: nenhum dado de tenant vem do request além do uuid do preço.
| Stripe simulado por Tests\Support\ChangeStripeFake (ver ChangeSubscriptionTest).
*/

uses(RefreshDatabase::class);

describe('spec 024: mudança de assinatura não aceita dado de tenant do request', function () {
    it('workspace_id, workspace_uuid, subscription_id, stripe_id e afins no body são ignorados', function () {
        $current = chgPrice('Basic', 2990);
        $target = chgPrice('Pro', 4990);
        [$userA, $wsA, $subA, $fake] = chgScenario($current);

        $userB = createUserWithPermissions(['billing.manage']);
        $wsB = Workspace::withoutGlobalScopes()->find($userB->currentAccess->workspace_id);
        $subB = chgSubscribe($wsB, $current, 'active', ['stripe_id' => 'sub_vitima']);

        $this->withToken($userA->test_token)->postJson('/api/billing/subscription/change', [
            'price' => $target->uuid,
            'workspace_id' => $wsB->id,
            'workspace_uuid' => $wsB->uuid,
            'subscription_id' => $subB->id,
            'subscription' => $subB->stripe_id,
            'stripe_id' => 'sub_vitima',
            'stripe_price' => 'price_injetado',
            'user_id' => $userB->id,
            'uuid' => (string) Str::uuid(),
            'status' => 'applied',
        ])->assertOk();

        expect($subA->fresh()->stripe_price)->toBe($target->gateway_price_id)
            ->and($subB->fresh()->stripe_price)->toBe($current->gateway_price_id)
            ->and(collect($fake->callsTo('subscriptions.update'))->pluck(0)->unique()->all())->toBe([$subA->stripe_id]);
    });

    it('o agendamento nasce na assinatura do workspace corrente, com status pending, ignorando o body', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Basic', 1990);
        [$userA, , $subA, $fake] = chgScenario($current);

        $userB = createUserWithPermissions(['billing.manage']);
        $wsB = Workspace::withoutGlobalScopes()->find($userB->currentAccess->workspace_id);
        $subB = chgSubscribe($wsB, $current, 'active', ['stripe_id' => 'sub_vitima']);

        $this->withToken($userA->test_token)->postJson('/api/billing/subscription/change', [
            'price' => $target->uuid,
            'subscription_id' => $subB->id,
            'stripe_schedule_id' => 'sch_injetado',
            'effective_at' => '2020-01-01',
            'status' => 'applied',
            'workspace_uuid' => $wsB->uuid,
        ])->assertOk();

        $row = DB::table('subscription_changes')->first();
        expect(DB::table('subscription_changes')->count())->toBe(1)
            ->and($row->subscription_id)->toBe($subA->id)
            ->and($row->status)->toBe('pending')
            ->and($row->stripe_schedule_id)->not->toBe('sch_injetado')
            ->and(substr((string) $row->effective_at, 0, 4))->toBe('2027');
    });

    it('DELETE ignora identificadores no body e no query string e nunca desfaz o agendamento de outro tenant', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Basic', 1990);
        [$userA, , , $fake] = chgScenario($current);

        $userB = createUserWithPermissions(['billing.manage']);
        $wsB = Workspace::withoutGlobalScopes()->find($userB->currentAccess->workspace_id);
        $subB = chgSubscribe($wsB, $current, 'active', ['stripe_id' => 'sub_vitima']);
        $pendingB = chgPendingChange($subB, $target, 'pending', 'sch_vitima');

        $this->withToken($userA->test_token)
            ->deleteJson('/api/billing/subscription/change?subscription_id='.$subB->id.'&workspace_uuid='.$wsB->uuid, [
                'subscription_id' => $subB->id, 'stripe_schedule_id' => 'sch_vitima', 'workspace_uuid' => $wsB->uuid,
            ])
            ->assertStatus(404)->assertJsonPath('error', 'no_pending_change');

        expect(DB::table('subscription_changes')->where('id', $pendingB)->value('status'))->toBe('pending')
            ->and($fake->calls)->toBe([]);
    });

    it('a resposta não expõe ids internos nem dados do gateway além do contrato', function () {
        $current = chgPrice('Pro', 4990);
        $target = chgPrice('Basic', 1990);
        [$user] = chgScenario($current);

        $body = $this->withToken($user->test_token)->postJson('/api/billing/subscription/change', ['price' => $target->uuid])
            ->assertOk()->getContent();

        expect($body)->not->toContain('stripe_schedule_id')->not->toContain('sch_fake_')
            ->not->toContain('workspace_id')->not->toContain('subscription_id');
    });
});
