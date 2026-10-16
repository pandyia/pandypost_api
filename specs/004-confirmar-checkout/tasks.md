# Tarefas — 004

- [x] Erro `checkout_session_not_found` — teste: ConfirmCheckoutTest
- [x] ConfirmCheckoutRequest + ConfirmCheckoutService — teste: sessão válida cria assinatura; idempotência
- [x] Endpoint + rota — teste: 403, 422, isolamento entre workspaces, sessão incompleta, falha Stripe
- [x] Front: chamar confirm antes do polling

## Divergências
- Próximo número livre é 004 (003 já existe: analytics).
- Testes escritos mas NÃO executados: ambiente da sessão sem PHP/Docker. Rodar `php artisan test tests/Feature/Billing` e `pint --dirty`.
