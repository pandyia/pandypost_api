<?php

use App\Jobs\ProcessStripeWebhookJob;
use App\Models\Notification;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
| Spec 024, tarefa 10 e RF-12 (downgrade efetivado): eventos de Subscription Schedule do Stripe.
| Estilo de StripeWebhookTest: o job é executado direto com o payload. Stripe não é chamado.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
});

function schedEvent(string $eventId, string $type, array $object = []): array
{
    return [
        'id' => $eventId,
        'type' => $type,
        'data' => ['object' => array_merge([
            'id' => 'sch_pending_1',
            'object' => 'subscription_schedule',
            'customer' => 'cus_sched_1',
            'subscription' => 'sub_chg_1',
        ], $object)],
    ];
}

/** Evento customer.subscription.updated em que o preço da assinatura mudou. */
function schedPriceChangedEvent(string $eventId, string $newGatewayPrice, string $oldGatewayPrice): array
{
    return [
        'id' => $eventId,
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => 'sub_chg_1', 'customer' => 'cus_sched_1', 'status' => 'active', 'cancel_at_period_end' => false,
                'items' => ['data' => [['id' => 'si_chg_1', 'price' => ['id' => $newGatewayPrice]]]],
            ],
            'previous_attributes' => [
                'items' => ['data' => [['id' => 'si_chg_1', 'price' => ['id' => $oldGatewayPrice]]]],
            ],
        ],
    ];
}

function schedScenario(): array
{
    $current = chgPrice('Pro', 4990);
    $target = chgPrice('Basic', 1990);
    $owner = createUserWithPermissions(['billing.manage']);
    $workspace = Workspace::withoutGlobalScopes()->find($owner->currentAccess->workspace_id);
    $workspace->forceFill(['stripe_id' => 'cus_sched_1'])->save();
    $sub = chgSubscribe($workspace, $current);
    $viewer = addUserToWorkspace($workspace);
    $viewer->currentAccess->role->permissions()->sync([permission('billing.view')->id]);
    $semBilling = addUserToWorkspace($workspace);
    $changeId = chgPendingChange($sub, $target);

    return [$owner, $viewer, $semBilling, $sub, $current, $target, $changeId];
}

function schedStatus(int $id): string
{
    return DB::table('subscription_changes')->where('id', $id)->value('status');
}

function schedNotified(): array
{
    return Notification::where('type', 'subscription_changed')->pluck('notifiable_id')->sort()->values()->all();
}

describe('subscription_schedule.completed (downgrade efetivado)', function () {
    it('marca a mudança como applied e notifica quem tem billing, sem notificar quem não tem', function () {
        [$owner, $viewer, $semBilling, , , , $changeId] = schedScenario();

        ProcessStripeWebhookJob::dispatchSync(schedEvent('evt_s1', 'subscription_schedule.completed'));

        expect(schedStatus($changeId))->toBe('applied')
            ->and(schedNotified())->toBe(collect([$owner->id, $viewer->id])->sort()->values()->all())
            ->and(Notification::where('notifiable_id', $semBilling->id)->count())->toBe(0);
    });

    it('o mesmo evento entregue duas vezes não duplica a notificação (event.id)', function () {
        [$owner, $viewer, , , , , $changeId] = schedScenario();

        ProcessStripeWebhookJob::dispatchSync(schedEvent('evt_dup', 'subscription_schedule.completed'));
        ProcessStripeWebhookJob::dispatchSync(schedEvent('evt_dup', 'subscription_schedule.completed'));

        expect(Notification::where('type', 'subscription_changed')->count())->toBe(2)
            ->and(schedStatus($changeId))->toBe('applied');
    });

    it('eventos distintos para o mesmo schedule não duplicam a notificação', function () {
        schedScenario();

        ProcessStripeWebhookJob::dispatchSync(schedEvent('evt_a', 'subscription_schedule.completed'));
        ProcessStripeWebhookJob::dispatchSync(schedEvent('evt_b', 'subscription_schedule.completed'));

        expect(Notification::where('type', 'subscription_changed')->count())->toBe(2);
    });

    it('fora de ordem: customer.subscription.updated antes de schedule.completed gera uma única notificação', function () {
        [, , , $sub, $current, $target, $changeId] = schedScenario();

        ProcessStripeWebhookJob::dispatchSync(schedPriceChangedEvent('evt_u', $target->gateway_price_id, $current->gateway_price_id));
        ProcessStripeWebhookJob::dispatchSync(schedEvent('evt_c', 'subscription_schedule.completed'));

        expect(schedStatus($changeId))->toBe('applied')
            ->and(Notification::where('type', 'subscription_changed')->count())->toBe(2);
    });

    it('fora de ordem: schedule.completed antes de customer.subscription.updated gera uma única notificação', function () {
        [, , , , $current, $target, $changeId] = schedScenario();

        ProcessStripeWebhookJob::dispatchSync(schedEvent('evt_c', 'subscription_schedule.completed'));
        ProcessStripeWebhookJob::dispatchSync(schedPriceChangedEvent('evt_u', $target->gateway_price_id, $current->gateway_price_id));

        expect(schedStatus($changeId))->toBe('applied')
            ->and(Notification::where('type', 'subscription_changed')->count())->toBe(2);
    });

    it('schedule desconhecido é ignorado (nada muda, ninguém é notificado)', function () {
        [, , , , , , $changeId] = schedScenario();

        ProcessStripeWebhookJob::dispatchSync(schedEvent('evt_x', 'subscription_schedule.completed', ['id' => 'sch_inexistente']));

        expect(schedStatus($changeId))->toBe('pending')
            ->and(Notification::where('type', 'subscription_changed')->count())->toBe(0);
    });

    it('customer de outro workspace com o id de schedule de um tenant não fecha a mudança dele (isolamento)', function () {
        [, , , , , , $changeId] = schedScenario();
        $outro = Workspace::withoutGlobalScopes()->create(['name' => 'Outro', 'is_personal_team' => false]);
        $outro->forceFill(['stripe_id' => 'cus_outro'])->save();

        ProcessStripeWebhookJob::dispatchSync(schedEvent('evt_o', 'subscription_schedule.completed', ['customer' => 'cus_outro']));

        expect(schedStatus($changeId))->toBe('pending')
            ->and(Notification::where('type', 'subscription_changed')->count())->toBe(0);
    });
});

describe('subscription_schedule.released / canceled', function () {
    it('released marca canceled e não notifica', function () {
        [, , , , , , $changeId] = schedScenario();

        ProcessStripeWebhookJob::dispatchSync(schedEvent('evt_r', 'subscription_schedule.released'));

        expect(schedStatus($changeId))->toBe('canceled')
            ->and(Notification::where('type', 'subscription_changed')->count())->toBe(0);
    });

    it('canceled marca canceled e não notifica', function () {
        [, , , , , , $changeId] = schedScenario();

        ProcessStripeWebhookJob::dispatchSync(schedEvent('evt_k', 'subscription_schedule.canceled'));

        expect(schedStatus($changeId))->toBe('canceled')
            ->and(Notification::where('type', 'subscription_changed')->count())->toBe(0);
    });

    it('released depois de completed não reabre nem cancela uma mudança já applied', function () {
        [, , , , , , $changeId] = schedScenario();

        ProcessStripeWebhookJob::dispatchSync(schedEvent('evt_c', 'subscription_schedule.completed'));
        ProcessStripeWebhookJob::dispatchSync(schedEvent('evt_r', 'subscription_schedule.released'));

        expect(schedStatus($changeId))->toBe('applied');
    });

    it('completed depois de released não aplica uma mudança cancelada', function () {
        [, , , , , , $changeId] = schedScenario();

        ProcessStripeWebhookJob::dispatchSync(schedEvent('evt_r', 'subscription_schedule.released'));
        ProcessStripeWebhookJob::dispatchSync(schedEvent('evt_c', 'subscription_schedule.completed'));

        expect(schedStatus($changeId))->toBe('canceled')
            ->and(Notification::where('type', 'subscription_changed')->count())->toBe(0);
    });
});

describe('customer.subscription.updated com troca de preço', function () {
    it('sem mudança pendente (upgrade imediato) não notifica de novo: quem avisa é o service', function () {
        [, , , $sub, $current, $target, $changeId] = schedScenario();
        DB::table('subscription_changes')->where('id', $changeId)->delete();

        ProcessStripeWebhookJob::dispatchSync(schedPriceChangedEvent('evt_up', $target->gateway_price_id, $current->gateway_price_id));

        expect(Notification::where('type', 'subscription_changed')->count())->toBe(0);
    });

    it('atualização sem troca de preço não fecha a mudança pendente', function () {
        [, , , , , , $changeId] = schedScenario();

        ProcessStripeWebhookJob::dispatchSync([
            'id' => 'evt_nop', 'type' => 'customer.subscription.updated',
            'data' => ['object' => ['id' => 'sub_chg_1', 'customer' => 'cus_sched_1', 'status' => 'active'], 'previous_attributes' => ['status' => 'trialing']],
        ]);

        expect(schedStatus($changeId))->toBe('pending');
    });
});
