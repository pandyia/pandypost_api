# Spec 024 — Mudança de assinatura (trocar de plano ou frequência)

> Área crítica (billing). Status: **spec aprovada** (decisões 1–5 respondidas pelo dono). `plan.md` e `tasks.md` aguardam aprovação.
> Base: spec 001-billing (RN-C1, RN-C11 "escopo futuro") e spec 004-confirmar-checkout.

## Contexto

Hoje um workspace assina por **Stripe Checkout** (`POST /billing/subscription/checkout`). Quem já tem assinatura recebe `409 already_subscribed` e **não há como trocar** de plano ou de frequência (mensal/anual): a única saída seria cancelar, o que também não existe na API. O pedido é permitir que quem já assina mude para outro preço.

### Comportamento atual (spec reversa, lido no código)

- `CheckoutService::start`: resolve o preço por `uuid` (404 se não existe) → `409 already_subscribed` se `subscribed('default')` → `422 price_not_synced` se o preço não tem `gateway_price_id` → cria a Checkout Session (`trialDays` se o preço tem trial; metadata `workspace_uuid` e `price_uuid`).
- `GET /billing/subscription` devolve `billing_status`, `stripe_status`, `on_trial`, `trial_ends_at`, `ends_at`, `plan` e `price` (resolvidos por `stripe_price`). 404 `no_active_subscription` se não há assinatura.
- Uma assinatura `default` por workspace (RN-C1). Permissões: `billing.view` (ler) e `billing.manage` (agir).
- Webhook (`ProcessStripeWebhookJob`): `customer.subscription.updated` só dispara notificação quando o cancelamento é agendado; qualquer outra atualização é ignorada pelo app (o Cashier sincroniza a assinatura local). Há idempotência por `event.id`.
- **Não existe:** troca de plano, cancelar, retomar, nem noção de "mudança agendada".
- Preço: `amount` (centavos), `currency`, `frequency` (`monthly`/`yearly`), `trial_period_days`, `gateway_price_id`, `is_active`.

## Escopo

Trocar a assinatura do workspace para **outro preço** (outro plano e/ou outra frequência), com a regra de vigência abaixo. Entram: a rota de troca, a validação, a cobrança da diferença, a mudança agendada de downgrade e o reflexo em `GET /billing/subscription`.

## Decisões de negócio já tomadas

1. **Upgrade vale na hora**, com cobrança pro-rata da diferença.
2. **Downgrade vale só no fim do ciclo atual**, sem estorno.
3. **Quem está em trial e troca de plano:** o trial **encerra** e o novo plano é cobrado na hora.
4. **Escopo:** mudar de plano **e** de frequência. Cancelar e retomar ficam fora.
5. **Estados permitidos (decisão do time técnico, ver RN-3):** só `active` e `trialing`. `past_due`, cancelada (mesmo com acesso até o fim) e sem assinatura **não** trocam.
6. **Mensal→anual vale na hora; anual→mensal no fim do ciclo** (regra por duração do ciclo, RN-1).
7. **Em trial, toda troca encerra o trial** e cobra o novo preço na hora.
8. **Desfazer downgrade agendado entra** nesta spec (RF-7).
9. **Notificação nova** na central: tipo `subscription_changed` (RF-12).
10. **Cobrança que exige autenticação (3DS):** a API devolve o link do Stripe para o cliente autorizar (RF-8).

## Requisitos

- **RF-1.** Endpoint para trocar a assinatura do workspace corrente para outro preço, informado por `uuid`. Permissão `billing.manage`.
- **RF-2.** Antes de qualquer chamada ao Stripe, validar: o workspace tem assinatura `active` ou `trialing`; o preço existe e está ativo (inexistente ou inativo → 404) e sincronizado com o gateway; é **diferente** do preço atual; está na **mesma moeda** da assinatura atual.
- **RF-3.** Classificar a troca como **upgrade** ou **downgrade** (regra em RN-1).
- **RF-4.** **Upgrade** (e qualquer troca feita em `trialing`): aplicar na hora, cobrar a diferença pro-rata (em `trialing`, o valor cheio do novo preço, pois nada foi pago) e encerrar o trial. A resposta já traz a assinatura no novo preço.
- **RF-5.** **Downgrade** (em `active`): **não** muda o preço agora. Registra uma **mudança agendada** para o fim do ciclo atual. No fim do ciclo, a assinatura passa para o novo preço e é cobrada por ele.
- **RF-6.** `GET /billing/subscription` passa a informar a mudança agendada (`pending_change`: preço e data), ou `null`.
- **RF-7.** O cliente pode **desfazer** um downgrade agendado antes do fim do ciclo.
- **RF-8.** Se a cobrança do upgrade falhar, a assinatura **continua no preço antigo** e a API responde com erro de domínio em pt-BR, sem expor mensagem do Stripe. Cartão recusado → `payment_failed`. Cobrança que exige autenticação (3DS) → `payment_action_required` com o link do Stripe (`action_url`) para o cliente autorizar; a troca só se completa depois da autorização.
- **RF-9.** Pedir um downgrade quando já existe outro agendado **substitui** o agendamento anterior.
- **RF-10.** Todas as ações ficam na trilha de auditoria e o `workspace` é sempre o corrente. Nenhum dado de tenant vem do request além do `uuid` do preço.
- **RF-11.** Pedir um **upgrade** (ou qualquer troca em trial) com um downgrade já agendado **descarta o agendamento** e aplica o upgrade.
- **RF-12.** Notificação `subscription_changed` na central (grupo billing, respeitando preferências, spec 007) para: upgrade aplicado, downgrade agendado e downgrade efetivado no fim do ciclo.

## Regras de negócio

- **RN-1 (vigência da troca).** Decide-se pela **duração do ciclo** e, se igual, pelo valor: (a) frequência nova **mais longa** (mensal→anual) = vigência **imediata**; (b) frequência nova **mais curta** (anual→mensal) = **fim do ciclo**; (c) **mesma frequência**: valor maior = imediata; valor menor ou igual = fim do ciclo. Em trial vale sempre a vigência imediata (decisão 7).
- **RN-2 (uma assinatura).** A RN-C1 continua valendo: a troca altera a assinatura existente, nunca cria uma segunda.
- **RN-3 (estados).** `past_due`: bloqueado, pois o cliente precisa regularizar o pagamento antes. Cancelada com fim agendado: bloqueado, porque trocar de plano retomaria a assinatura de forma implícita, e retomar é outra funcionalidade. Os dois devolvem erro de domínio claro, sem tocar no Stripe.
- **RN-4 (limites de plano).** Os limites ainda não existem (RF-C4 da spec 001 está deferida). Portanto **não há validação de "downgrade sobre a quota"**; ela entra quando os limites forem definidos.
- **RN-5 (quem pode).** `billing.manage`. Super admin ignora, como no resto.

## Contrato de API (proposto)

```
POST   /billing/subscription/change      permission: billing.manage
  body: { "price": "<uuid do preço>" }
  200:  { "message": "...", "data": SubscriptionResource }   // upgrade imediato ou downgrade agendado
DELETE /billing/subscription/change      permission: billing.manage
  200:  { "message": "...", "data": SubscriptionResource }   // desfaz o agendamento
```

`SubscriptionResource` ganha `pending_change: { "price": {id, amount, currency, frequency}, "plan": {id, name}, "effective_at": "<data>" } | null`.

Erros de domínio novos em `BillingError`/`BillingException` (mensagens em pt-BR):

| Código | HTTP | Quando |
|---|---|---|
| `subscription_change_not_allowed` | 409 | estado `past_due` ou cancelada |
| `same_price` | 422 | o preço informado já é o atual |
| `price_currency_mismatch` | 422 | moeda diferente da assinatura atual |
| `price_not_synced` (existente) | 422 | preço sem `gateway_price_id` |
| `no_active_subscription` (existente) | 404 | workspace sem assinatura |
| `payment_failed` | 402 | cobrança do upgrade recusada |
| `payment_action_required` | 402 | cobrança exige autenticação (3DS); resposta traz `action_url` |
| `no_pending_change` | 404 | desfazer sem mudança agendada |
| `gateway_error` (existente) | 502 | falha geral no Stripe |

## Critérios de aceite

1. Workspace `active` no plano A faz upgrade para B: a assinatura passa ao preço B na hora e o Stripe gera a cobrança proporcional.
2. Workspace `active` faz downgrade: o preço atual não muda, `pending_change` aparece com a data do fim do ciclo, e na renovação passa ao novo preço.
3. Workspace `trialing` troca de plano: o trial termina e o novo preço é cobrado imediatamente.
4. Trocar para o mesmo preço → 422 `same_price`; para moeda diferente → 422 `price_currency_mismatch`; preço inexistente → 404.
5. `past_due` ou cancelada → 409 `subscription_change_not_allowed`, sem chamada ao Stripe.
6. Cartão recusado no upgrade → erro `payment_failed` e assinatura intacta.
7. Usuário sem `billing.manage` → 403. Workspace de outro tenant nunca é afetado (isolamento).
8. Novo downgrade substitui o agendado; desfazer limpa `pending_change`; upgrade com downgrade agendado descarta o agendamento.
10. Mensal→anual aplica na hora; anual→mensal é agendado; em trial toda troca aplica na hora.
11. As três situações (upgrade, downgrade agendado, downgrade efetivado) geram a notificação `subscription_changed`. A família billing é fixa (spec 007), então não há toggle: mesmo com as preferências desligadas, ela chega.
12. Cobrança com 3DS devolve 402 `payment_action_required` com `action_url`, e a assinatura segue no preço antigo.
9. Webhooks duplicados ou fora de ordem não repetem a cobrança nem a troca.

## Adendo — data da próxima cobrança (2026-11-27)

`GET /billing/subscription` ganha `next_billing_at` (ISO ou `null`): fim do teste grátis se estiver em teste; senão o fim do período atual no Stripe (cache de 5 min, descartado quando o plano muda). `null` com a assinatura encerrando, gateway desligado ou falha do Stripe, que nunca derruba a rota nem vaza erro. Serve para o front mostrar quando o cliente será cobrado.

## Fora de escopo

Cancelar e retomar assinatura; limites e quotas de plano; trocar de moeda; mudança de cartão (já existe); créditos ou cupons; a tela no front (spec própria em `pandypost_ui`, que consome este contrato); cobrança de valores além da pro-rata nativa do Stripe.

## Questões em aberto

Nenhuma de negócio. Riscos técnicos a validar no Stripe (modo teste) estão no `plan.md`.
