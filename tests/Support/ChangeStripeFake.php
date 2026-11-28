<?php

namespace Tests\Support;

use Stripe\Collection;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Stripe\Subscription as StripeSubscription;

/**
 * StripeClient falso da spec 024 (mudança de assinatura).
 *
 * PONTO DE SIMULACAO (contrato para o dev): o fake é ligado no container como
 * `StripeClient` (`app()->bind(StripeClient::class, ...)`). Toda chamada ao Stripe da
 * troca de plano DEVE passar por `Cashier::stripe()` / `$workspace->stripe()` /
 * `app(StripeClient::class)` (todos resolvem o mesmo bind). Nada de `new StripeClient`,
 * de `\Stripe\Subscription::update` estático nem de HTTP direto.
 *
 * Recursos/métodos suportados (qualquer outro devolve um StripeObject vazio, e fica gravado em `$calls`):
 *   subscriptions.update | retrieve
 *   subscriptionSchedules.create | update | release | cancel | retrieve
 *   invoices.retrieve | all
 *
 * Convenções que os testes assumem:
 *  - upgrade/troca imediata = `subscriptions.update($id, $params, $opts)` com
 *    `$params['items']` contendo `['price' => <gateway_price_id novo>]`;
 *  - downgrade agendado = `subscriptionSchedules.create(['from_subscription' => sub_id])` seguido de
 *    `subscriptionSchedules.update($scheduleId, ['phases' => [fase atual, fase nova]])`, onde a última
 *    fase tem `items[0].price` = gateway_price_id novo; o fim do ciclo vem de `subscriptions.retrieve`
 *    (`current_period_end`, ou `items.data[0].current_period_end`);
 *  - desfazer/descartar agendamento = `subscriptionSchedules.release` (ou `cancel`) com o id do schedule;
 *  - cartão recusado = `subscriptions.update` lança `Stripe\Exception\CardException`;
 *  - 3DS = `subscriptions.update` devolve a assinatura com `pending_update` preenchido, itens ainda no preço
 *    antigo e `latest_invoice.hosted_invoice_url` (também servido por `invoices.retrieve`/`all`).
 */
class ChangeStripeFake extends StripeClient
{
    public const PERIOD_END = 1800014400; // 2027-01-15 12:00:00 UTC (fixo; a data não importa, só ser estável)

    /** @var list<array{0:string,1:array,2:string}> [recurso.método, argumentos, ...] */
    public array $calls = [];

    /** @var array<string, mixed> 'recurso.método' => valor | Throwable | Closure(args): mixed */
    public array $responses = [];

    public string $currentPrice = 'price_old';

    public string $subscriptionStatus = 'active';

    private int $scheduleSeq = 0;

    public function __construct() {}

    public function __get($name)
    {
        $fake = $this;

        return new class($fake, $name)
        {
            public function __construct(private ChangeStripeFake $fake, private string $resource) {}

            public function __call(string $method, array $args)
            {
                return $this->fake->handle($this->resource, $method, $args);
            }
        };
    }

    public function respond(string $key, mixed $response): self
    {
        $this->responses[$key] = $response;

        return $this;
    }

    public function handle(string $resource, string $method, array $args): mixed
    {
        $key = "$resource.$method";
        $this->calls[] = [$key, $args];

        if (array_key_exists($key, $this->responses)) {
            $r = $this->responses[$key];
            if ($r instanceof \Closure) {
                $r = $r($args);
            }
            if ($r instanceof \Throwable) {
                throw $r;
            }

            return $r;
        }

        return match ($key) {
            'subscriptions.update' => $this->subscription($this->newPriceFrom($args[1] ?? [])),
            'subscriptions.retrieve' => $this->subscription($this->currentPrice),
            'subscriptionSchedules.create' => $this->schedule('sch_fake_'.(++$this->scheduleSeq), $args[0] ?? []),
            'subscriptionSchedules.update' => $this->schedule($args[0] ?? 'sch_fake_1', $args[1] ?? []),
            'subscriptionSchedules.release', 'subscriptionSchedules.cancel', 'subscriptionSchedules.retrieve' => $this->schedule($args[0] ?? 'sch_fake_1', [], 'released'),
            default => new StripeObject,
        };
    }

    /** Chamadas de um recurso.método, na ordem. @return list<array> */
    public function callsTo(string $key): array
    {
        return array_values(array_map(fn ($c) => $c[1], array_filter($this->calls, fn ($c) => $c[0] === $key)));
    }

    public function called(string $key): bool
    {
        return $this->callsTo($key) !== [];
    }

    private function newPriceFrom(array $params): string
    {
        foreach ($params['items'] ?? [] as $item) {
            if (! empty($item['price']) && empty($item['deleted'])) {
                return $item['price'];
            }
        }

        return $this->currentPrice;
    }

    public function subscription(string $priceId, array $extra = []): StripeSubscription
    {
        return StripeSubscription::constructFrom(array_merge([
            'id' => 'sub_chg_1',
            'object' => 'subscription',
            'status' => $this->subscriptionStatus,
            'current_period_end' => self::PERIOD_END,
            'cancel_at_period_end' => false,
            'items' => Collection::constructFrom([
                'object' => 'list',
                'data' => [[
                    'id' => 'si_chg_1',
                    'object' => 'subscription_item',
                    'quantity' => 1,
                    'current_period_end' => self::PERIOD_END,
                    'price' => ['id' => $priceId, 'product' => 'prod_chg_1', 'recurring' => ['usage_type' => 'licensed', 'interval' => 'month']],
                ]],
            ]),
        ], $extra));
    }

    public function schedule(string $id, array $params = [], string $status = 'active'): StripeObject
    {
        return StripeObject::constructFrom([
            'id' => $id,
            'object' => 'subscription_schedule',
            'status' => $status,
            'subscription' => $params['from_subscription'] ?? 'sub_chg_1',
            'phases' => $params['phases'] ?? [[
                'start_date' => self::PERIOD_END - 2592000,
                'end_date' => self::PERIOD_END,
                'items' => [['price' => $this->currentPrice]],
            ]],
        ]);
    }
}
