<?php

use App\Enums\PaymentStatus;
use App\Jobs\ProcessStripeWebhookJob;
use App\Models\Payment;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
});

function invoicePayload(string $eventId, string $type, array $overrides = []): array
{
    return [
        'id' => $eventId,
        'type' => $type,
        'data' => ['object' => array_merge([
            'id' => 'in_test_1',
            'customer' => 'cus_test_1',
            'subscription' => 'sub_test_1',
            'amount_paid' => 4990,
            'amount_due' => 4990,
            'currency' => 'brl',
            'hosted_invoice_url' => 'https://stripe.test/invoice',
            'invoice_pdf' => 'https://stripe.test/invoice.pdf',
            'payment_intent' => 'pi_test_1',
            'status_transitions' => ['paid_at' => 1700000000],
            'period_start' => 1700000000,
            'period_end' => 1702600000,
        ], $overrides)],
    ];
}

function workspaceWithStripeCustomer(string $customerId = 'cus_test_1'): Workspace
{
    $workspace = Workspace::withoutGlobalScopes()->create([
        'name' => 'Billing WS',
        'is_personal_team' => true,
    ]);

    $workspace->forceFill(['stripe_id' => $customerId])->save();

    return $workspace;
}

test('invoice.payment_succeeded cria um Payment pago vinculado ao workspace', function () {
    $workspace = workspaceWithStripeCustomer();

    (new ProcessStripeWebhookJob(invoicePayload('evt_1', 'invoice.payment_succeeded')))->handle();

    $payment = Payment::where('gateway_invoice_id', 'in_test_1')->first();

    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe(PaymentStatus::PAID)
        ->and($payment->amount)->toBe(4990)
        ->and($payment->workspace_id)->toBe($workspace->id)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($payment->gateway_hosted_invoice_url)->toBe('https://stripe.test/invoice');
});

test('o mesmo evento processado duas vezes não duplica o Payment (idempotência)', function () {
    workspaceWithStripeCustomer();

    $payload = invoicePayload('evt_dup', 'invoice.payment_succeeded');
    (new ProcessStripeWebhookJob($payload))->handle();
    (new ProcessStripeWebhookJob($payload))->handle();

    expect(Payment::where('gateway_invoice_id', 'in_test_1')->count())->toBe(1);
});

test('invoice.payment_failed marca o Payment como failed', function () {
    workspaceWithStripeCustomer();

    (new ProcessStripeWebhookJob(invoicePayload('evt_fail', 'invoice.payment_failed')))->handle();

    expect(Payment::where('gateway_invoice_id', 'in_test_1')->value('status'))
        ->toBe(PaymentStatus::FAILED);
});

test('evento de customer desconhecido é ignorado (nenhum Payment criado)', function () {
    // sem workspace com esse stripe_id
    (new ProcessStripeWebhookJob(invoicePayload('evt_orphan', 'invoice.payment_succeeded', [
        'customer' => 'cus_inexistente',
    ])))->handle();

    expect(Payment::count())->toBe(0);
});
