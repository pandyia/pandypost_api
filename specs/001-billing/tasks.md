# Tasks 001 — Billing

> Checklist derivado das fases do [`plan.md`](plan.md) §4. **Status reconstruído a partir do código em 2026-10-04:** um `[x]` significa que o código correspondente existe no repositório. Isso não garante que os testes passam nem que foi validado em test mode do Stripe.
>
> Legenda: `[x]` feito · `[ ]` pendente · `[-]` deferido/descartado por decisão · ⚠️ implementado diferente do plano (ver "Divergências").

## Fase 1 — Admin (catálogo)

- [x] Instalar `laravel/cashier` e configurar `config/cashier.php`
- [x] `Billable` no `Workspace` + migration de colunas de customer (`add_cashier_columns_to_workspaces_table`)
- [x] Drop do legado (`drop_legacy_subscriptions_table`) + tabelas do Cashier (`subscriptions`, `subscription_items`)
- [x] Subclasse `App\Models\Subscription` registrada via `Cashier::useSubscriptionModel()` com accessor `billing_status`
- [ ] Coluna `uuid` na subclasse de Subscription (plan §3) — não encontrada na migration de `subscriptions`
- [x] Migrations + models: `Plan` (com colunas de billing), `Price`, `PriceHistory`
- [x] Enums `Currency` e `BillingFrequency`
- [x] `PlanService` (criar, editar com sync, ativar/desativar, deletar hard/soft) ⚠️ via `StripeCatalogService`
- [x] `PriceService` (criar, editar = arquivar + recriar + `PriceHistory`, deletar com as regras de 409)
- [x] Controllers, FormRequests e Resources em `Billing/Admin` + rotas `/admin/plans` e `/admin/plans/{plan}/prices` (+ `/versions`)
- [x] `BillingError` + `BillingException`
- [x] Permissões do grupo `billing` no seeder ⚠️ (`billing.view` / `billing.manage`)
- [x] `PlanSeeder`
- [x] Testes: `AdminPlanTest`, `AdminPriceTest`
- [-] `PlanLimit` e endpoints `/admin/plans/{plan}/limits` — DEFERIDO (plan §9 Q7)

## Fase 2 — Webhook e inadimplência

- [x] Rota do webhook `POST /api/stripe/webhook` (WebhookController do Cashier; rotas automáticas do Cashier desabilitadas)
- [x] `Cashier::keepPastDueSubscriptionsActive()` no `AppServiceProvider`
- [x] Listener de `WebhookReceived` ⚠️ (`HandleStripeWebhook`) filtrando `invoice.created`, `invoice.payment_succeeded`, `invoice.payment_failed`, `invoice.voided`, `checkout.session.expired`
- [x] `ProcessStripeWebhookJob` com dedup por `event.id` em cache (TTL 24h) e escrita em `DB::transaction`
- [x] Tabela/model `Payment` com unique nos 3 `gateway_*_id` (Opção B adotada — plan §9 Q3 resolvida)
- [x] Enum `PaymentStatus`
- [ ] Fila dedicada `webhooks` no Horizon — o job hoje vai para a fila `default`
- [x] Middleware `EnsureSubscriptionAccess` (alias `subscribed`) criado
- [ ] **Aplicar `subscribed` nas rotas de produto** (liberando as rotas `/billing/*`) — ainda não aplicado, por decisão da fase
- [ ] Config do dashboard do Stripe: Smart Retries + ação final "Cancelar assinatura" (setup, não código — confirmar em test e live)
- [x] Testes: `StripeWebhookTest`, `SubscriptionAccessTest`

## Fase 3 — Cliente

- [x] Checkout via `newSubscription()->checkout()` ⚠️ (`POST /billing/subscription/checkout`)
- [x] `GET /billing/subscription` com `billing_status`
- [ ] Campo `billing_message` em `GET /billing/subscription` (spec [004] RN-C8)
- [x] Métodos de pagamento sem tabela local: `GET /billing/setup-intent`, `GET|POST /billing/cards`, `PATCH|DELETE /billing/cards/{paymentMethod}` (erro ao remover o cartão padrão)
- [x] Histórico: `GET /billing/payments` e `GET /billing/payments/{payment}` (tabela `Payment` local)
- [x] Moeda do workspace resolvida pelo Price da assinatura (`Workspace::preferredCurrency()`)
- [ ] Cache de 5 min da assinatura corrente com invalidação via webhook (spec [004] RN-F13)
- [-] `downloadInvoice()` com dompdf — não adotado; usar os links hospedados do Stripe (plan §9 Q9)
- [-] `GET /billing/subscription/usage` — DEFERIDO
- [x] Testes: `ClientBillingTest`, `WorkspaceCurrencyTest`
- [ ] Teste E2E manual em test mode: signup → checkout → webhook → `trialing`/`active`

## Fase 4 — Hardening / go-live

- [ ] Chaves live + webhook de produção registrado
- [ ] Retentativas e ação final configuradas no dashboard **live**
- [ ] Alertas de `failed_jobs`
- [ ] Questões em aberto do plan §9 fechadas com o PM (boleto, SCA, cancelamento self-service, resync manual)

## Divergências entre o plano e o código

Registradas para decidir depois: atualizar a spec ou o código. **Não corrigir de passagem.**

1. **Cliente do Stripe:** o plano (§2.1) diz para usar sempre `Cashier::stripe()`. O `StripeCatalogService` instancia `new \Stripe\StripeClient(config('services.stripe.secret'))`.
2. **Permissões:** a spec [004] usa `billing.view` / `billing.edit`. O código usa `billing.view` / `billing.manage`.
3. **⚠️ Rotas admin:** `/admin/plans` (catálogo **global**) são protegidas pelas mesmas permissões `billing.*` que as rotas do tenant. Verificar se um membro de workspace com `billing.manage` consegue criar ou apagar planos globais. Se conseguir, as rotas admin precisam exigir super admin.
4. **Rota de checkout:** o plano diz `POST /billing/checkout`. O código usa `POST /billing/subscription/checkout`.
5. **Nomes:** o plano diz `StripeWebhookListener`. O código usa `HandleStripeWebhook`.
6. **Customer no signup:** o plano diz `createAsStripeCustomer()` no signup. O código não chama isso no signup (o customer é criado no checkout).
7. **Restos de quota:** `SubscriptionService::consumeQuota()` e `SubscriptionError::QUOTA_EXCEEDED` existem, mas os limites de plano estão deferidos. Verificar se é código morto.
