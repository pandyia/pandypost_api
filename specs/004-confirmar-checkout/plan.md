# Plano — 004 Confirmar checkout

## Decisões
- `ConfirmCheckoutService` (Services/Billing/Tenant) usa `Cashier::stripe()->checkout->sessions->retrieve($id, ['expand' => ['subscription']])`.
  Descartado: replicar via `WebhookController` do Cashier (métodos protegidos, exigiria subclasse/assinatura sintética).
- Sincronização por `subscriptions()->updateOrCreate(['stripe_id'])`, espelhando o handler `customer.subscription.created` do Cashier (idempotente).
- Autorização por posse: `session.customer === workspace.stripe_id`.
- Novo erro `CHECKOUT_SESSION_NOT_FOUND` (404) em `BillingError`/`BillingException`.

## Arquivos
- `app/Services/Billing/Tenant/ConfirmCheckoutService.php` (novo)
- `app/Http/Requests/Billing/Tenant/ConfirmCheckoutRequest.php` (novo)
- `SubscriptionController::confirm`, rota em `routes/api.php`
- `BillingError`, `BillingException`
- `tests/Feature/Billing/ConfirmCheckoutTest.php`
- Front: `billingService.confirmCheckout`, `BillingSuccessView.vue`

## Efeitos colaterais
Nenhum Event/Job novo; o webhook posterior encontra a assinatura já criada e não duplica.

## Riscos
Divergência futura com o handler do Cashier (mitigado: webhook segue como fonte de verdade e atualiza o estado).
