# Plano 024 — Mudança de assinatura

> Como implementar a `spec.md`. Status: **aguardando aprovação**. Cashier 16.9, stripe-php 21.

## Decisões (com alternativa descartada)

1. **Upgrade e troca em trial:** chamada **direta** à API do Stripe (`subscriptions.update`) com `proration_behavior=always_invoice`, `payment_behavior=pending_if_incomplete` e, em trial, `trial_end=now`, mais `idempotency_key` (`change:...`). Se a cobrança falhar ou exigir 3DS, o Stripe **não aplica** a troca (`pending_update`) e a assinatura segue no preço antigo (RF-8). Depois do sucesso, o service sincroniza `stripe_price`/itens locais (como o `ConfirmCheckoutService` faz), idempotente com o webhook do Cashier.
   *Descartada:* `Subscription::swapAndInvoice` do Cashier. Verificado pelo agente `tdd`: no Cashier 16.9 o `swap` falha neste projeto porque faltam as colunas `meter_id`/`meter_event_name` em `subscription_items`; além disso `errorIfPaymentFails()` usa `error_if_incomplete`, que não dá o comportamento de 3DS que a decisão 10 exige.
2. **Downgrade no fim do ciclo:** **Subscription Schedule do Stripe** com 2 fases (preço atual até `current_period_end`, depois o preço novo). O Stripe aplica sozinho na virada, sem job nosso nem risco de perder a data.
   *Descartada:* guardar a mudança só localmente e aplicar por comando agendado, que duplica a responsabilidade do Stripe e falha se o comando não rodar.
3. **Estado local da mudança agendada:** tabela `subscription_changes` (modelo `SubscriptionChange`), para o `GET /billing/subscription` mostrar `pending_change` sem chamar o Stripe. O Stripe continua sendo a fonte de verdade; o webhook fecha o ciclo da linha (`applied`/`canceled`).
   *Descartada:* ler o schedule ao vivo no Stripe a cada `GET` (lento, dependente do gateway).
4. **Quem decide upgrade × downgrade:** método único `SubscriptionChangeKind::for(Price $current, Price $target, bool $onTrial)` seguindo a RN-1 (ciclo mais longo = imediata; mais curto = fim do ciclo; mesma frequência compara `amount`; trial = sempre imediata). Enum `SubscriptionChangeKind` (`immediate`, `scheduled`), puro, testável sem Stripe.
5. **Concorrência:** a troca roda dentro de transação com `lockForUpdate()` na assinatura do workspace e envia **idempotency key** ao Stripe (`change:{subscription_id}:{price_uuid}:{minuto}`), para duplo clique não gerar duas cobranças. A decisão "estado permitido" é lida **depois** do lock.
6. **Moeda:** a mesma do preço atual da assinatura; troca de moeda é 422.

## Modelo de dados

Migration `create_subscription_changes_table` (uma responsabilidade; SQL compatível com PostgreSQL e SQLite):

| Coluna | Tipo |
|---|---|
| `id` | bigint PK |
| `uuid` | uuid único (`HasUuid`) |
| `subscription_id` | FK `subscriptions` (cascade) |
| `price_id` | FK `prices` (o preço de destino) |
| `stripe_schedule_id` | string, único |
| `effective_at` | timestamp (fim do ciclo) |
| `status` | string (`pending`, `applied`, `canceled`) |
| timestamps | |

Índice único parcial: no máximo **uma** `pending` por assinatura (`CREATE UNIQUE INDEX ... WHERE status = 'pending'`, válido nos dois bancos). `id` em `$hidden`; sem dado de tenant vindo do request (o workspace vem da assinatura).

## Arquivos

**Novos**
- `database/migrations/..._create_subscription_changes_table.php`
- `app/Models/SubscriptionChange.php` (+ factory)
- `app/Enums/SubscriptionChangeKind.php`, `app/Enums/SubscriptionChangeStatus.php`
- `app/Services/Billing/Tenant/ChangeSubscriptionService.php` (`change()`, `cancelPending()`)
- `app/Http/Requests/Billing/Tenant/ChangeSubscriptionRequest.php` (`price` uuid obrigatório)
- Notificação `subscription_changed` (via agente `notifications`, padrão da spec 007)

**Alterados**
- `routes/api.php`: `POST` e `DELETE /billing/subscription/change` com `permission:billing.manage`.
- `SubscriptionController`: ações `change` e `cancelChange` finas, `validated()`, resposta `{message, data}`.
- `BillingError` + `BillingException`: `subscription_change_not_allowed` (409), `same_price` (422), `price_currency_mismatch` (422), `payment_failed` (402), `payment_action_required` (402), `no_pending_change` (404). Mensagens fixas em pt-BR.
- `SubscriptionResource`: campo `pending_change`.
- `Subscription` model: relação `pendingChange()`.
- `HandleStripeWebhook::HANDLED` + `ProcessStripeWebhookJob`: tratam `subscription_schedule.completed`, `subscription_schedule.released` e `subscription_schedule.canceled`; em `customer.subscription.updated`, quando o preço muda, fecham a mudança pendente e disparam a notificação.
- `docs/manual-producao.md`: os três eventos novos precisam ser assinados no webhook live do Stripe (tarefa do dono).
- Docblocks Scribe dos novos endpoints (agente `docs`).

## Fluxo

1. Valida a permissão e o FormRequest; resolve o preço por `uuid` entre os **ativos** (404 se não existe ou está inativo) e o carrega ativo e sincronizado.
2. Abre transação, `lockForUpdate` na assinatura `default`. Sem assinatura → `no_active_subscription`; `stripe_status` fora de `active`/`trialing` ou com `ends_at` → `subscription_change_not_allowed`; mesmo preço → `same_price`; moeda diferente → `price_currency_mismatch`.
3. `SubscriptionChangeKind::for(...)`:
   - **immediate:** se houver `pending`, libera o schedule no Stripe e marca `canceled` (RF-11). Depois `subscriptions.update` com `pending_if_incomplete`. `IncompletePayment` → traduz para `payment_action_required` (com `action_url` = `hosted_invoice_url` da fatura aberta) ou `payment_failed`. Sucesso → notificação de upgrade aplicado.
   - **scheduled:** cria o schedule a partir da assinatura (`from_subscription`) e atualiza as fases; se já havia `pending`, substitui (RF-9). Grava `subscription_changes` (`pending`) e notifica "downgrade agendado".
4. Resposta: `SubscriptionResource` atualizado.
5. `DELETE /change`: sem `pending` → 404 `no_pending_change`; senão libera o schedule, marca `canceled`.
6. Webhook de virada do ciclo: marca `applied`, e uma notificação "downgrade efetivado" (idempotente pelo `event.id` e pelo id do schedule).

## Efeitos colaterais

- **Stripe:** cria/libera schedules e gera invoice de pro-rata. Nunca chamar API real em teste (CLAUDE.md).
- **Webhook:** 3 eventos novos a assinar no dashboard live (dono).
- **Notificação:** novo tipo no enum e na factory, no grupo billing. A família billing é **fixa** (spec 007): a API rejeita `billing:false`, então a notificação sempre chega. O critério de aceite 11 segue o comportamento existente.
- **Auditoria:** `SubscriptionChange` auditável (owen-it), como `Price`.
- **Octane:** sem estado estático. O service é resolvido por injeção.

## Testes (Pest, `tests/Feature/Billing/ChangeSubscriptionTest.php` + unitário do `ChangeKind`)

Stripe sempre simulado (`StripeClient` ligado no container, como em `ConfirmCheckoutTest`). Cobrir: cada critério de aceite 1–12; 403 sem `billing.manage`; **isolamento** (preço/assinatura de outro workspace → nada muda); idempotência do webhook (evento duplicado e fora de ordem); duplo clique com a mesma chave; 3DS e cartão recusado mantendo o preço antigo; upgrade que substitui downgrade agendado; SQLite e PostgreSQL na migration. Em `tests/Feature/Security/`: teste de que `workspace`/`subscription` nunca vêm do request. Rodar a suíte inteira antes de concluir.

- `effective_at` vem de `subscriptions.retrieve` (`current_period_end`), porque a assinatura local não guarda essa data.

## Riscos (validar no Stripe em modo teste antes de dar por pronto)

1. **`pending_if_incomplete` com mudança de intervalo** (agora chamado direto, `payment_behavior=pending_if_incomplete`) (mensal→anual): não confirmei que o Stripe aceita esse modo ao mudar o intervalo. Se não aceitar, o plano cai em criar a fatura pro-rata antes e só então aplicar. **Validar primeiro** (tarefa 1 do `tasks.md`).
2. **Âncora do ciclo** ao mudar de intervalo: o Stripe pode reiniciá-la. Conferir se o `current_period_end` novo e a pro-rata batem com o esperado.
3. **Subscription Schedule + Cashier:** o Cashier não conhece schedules; a assinatura local é sincronizada pelo webhook `customer.subscription.updated`. Conferir que a troca de preço na virada chega a `stripe_price` local.
4. **Eventos fora de ordem** (`schedule.completed` antes de `subscription.updated`): o handler deve ser idempotente e não depender da ordem.
5. **Sem limites de plano** hoje: downgrade nunca é bloqueado por quota. Revisitar quando os limites existirem.
6. **Área crítica:** `security` é obrigatório antes de concluir.
