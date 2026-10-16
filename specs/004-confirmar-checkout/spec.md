# 004 — Confirmar checkout (sincronização imediata da assinatura)

## Contexto
Hoje a assinatura só passa a existir no banco quando o webhook do Stripe chega em `/api/stripe/webhook`.
Em ambiente sem webhook alcançável (dev local sem `stripe listen`, atraso/falha de entrega), o usuário paga, volta para
`/billing/success` e `GET /billing/subscription` retorna 404 até o timeout da tela, mesmo com o pagamento concluído.
O Stripe devolve `?session_id=` na URL de sucesso; a API pode confirmar a sessão direto no gateway.

## Escopo
- Novo endpoint que, dado o `session_id` do checkout, consulta a sessão no Stripe e cria/atualiza a assinatura local.
- Front (`/billing/success`) chama o endpoint antes de iniciar o polling.
- O webhook continua sendo a fonte de verdade; o confirm apenas antecipa o mesmo efeito.

## Requisitos
- RF-1: `POST /api/billing/subscription/checkout/confirm` com `session_id` (permissão `billing.manage`).
- RF-2: a sessão só é aceita se o `customer` dela for o `stripe_id` do workspace corrente; caso contrário 404 e nada muda.
- RF-3: sessão não concluída (`status != complete`) ou sem assinatura → 404 `no_active_subscription`, nada muda.
- RF-4: idempotente: chamar várias vezes, ou depois do webhook, não duplica a assinatura.
- RF-5: sincroniza os mesmos campos que o Cashier sincroniza no webhook `customer.subscription.created`
  (status, preço, quantidade, trial, itens).
- RF-6: falha do Stripe → 502 `gateway_error`, sem expor a mensagem do Stripe.

## Contrato de API
`POST /api/billing/subscription/checkout/confirm` `{ "session_id": "cs_test_..." }`
- 200 `{ "data": <SubscriptionResource> }`
- 404 `checkout_session_not_found` (sessão de outro workspace/customer) · 404 `no_active_subscription` (ainda não concluída)
- 422 validação · 403 sem permissão · 502 `gateway_error`

## Critérios de aceite
- Sessão válida do workspace cria a assinatura e retorna 200; segunda chamada retorna 200 sem duplicar.
- Sessão de outro workspace → 404 e nenhuma assinatura criada.
- Sem permissão → 403. `session_id` ausente/formato inválido → 422.

## Fora de escopo
Alterar webhook, cobrança, planos/preços; mexer em Stripe real (testes mockados).

## Questões em aberto
Nenhuma (decisão do usuário: "fazer o que for preciso para funcionar").
