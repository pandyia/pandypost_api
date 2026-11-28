# Tasks 024 — Mudança de assinatura

> Uma tarefa por vez, marcando `[x]`. Pré-requisito: `plan.md` aprovado.

- [ ] 0. **(NÃO EXECUTAR sem autorização do dono: exige criar objetos reais no Stripe de teste.)** Validar no Stripe **modo teste** os riscos 1 a 3 do plano (script descartável no scratchpad, sem tocar produção) e registrar o resultado em **Divergências** — teste: nota escrita com o que o Stripe devolveu em cada caso
- [x] 1. `BillingError` + `BillingException`: os 6 erros novos, mensagens pt-BR — teste: cada factory devolve código e HTTP esperados
- [x] 2. Enums `SubscriptionChangeKind` e `SubscriptionChangeStatus` e a regra `ChangeKind::for` (RN-1) — teste: unitário, todas as combinações (ciclo mais longo/curto, mesma frequência maior/menor/igual, trial)
- [x] 3. Migration `subscription_changes` + model + factory + relação `pendingChange()` — teste: índice único parcial impede 2 `pending`; roda em SQLite
- [x] 4. `ChangeSubscriptionRequest` — teste: `price` obrigatório e uuid; permissão
- [x] 5. `ChangeSubscriptionService::change` — upgrade imediato com `skipTrial` e `errorIfPaymentFails` — teste: critérios 1, 3, 6, 10 e 12 com Stripe simulado
- [x] 6. `ChangeSubscriptionService::change` — downgrade agendado via Subscription Schedule — teste: critérios 2 e 8; substitui o `pending` anterior
- [x] 7. Validações do estado (RF-2, RN-3) e lock/idempotência — teste: critérios 4 e 5; duplo clique não cobra duas vezes
- [x] 8. `ChangeSubscriptionService::cancelPending` — teste: critério 8 (desfazer) e `no_pending_change`
- [x] 9. Rotas, `SubscriptionController::change`/`cancelChange` e `SubscriptionResource::pending_change` — teste: contrato, 403 sem `billing.manage`, **isolamento entre workspaces**
- [x] 10. Webhook: eventos de schedule em `HandleStripeWebhook::HANDLED` e `ProcessStripeWebhookJob` (fecha a mudança `applied`/`canceled`) — teste: critério 9 (duplicado e fora de ordem)
- [x] 11. Notificação `subscription_changed` (agente `notifications`) para os 3 momentos — teste: critério 11, inclusive com a preferência desligada
- [x] 12. Teste de segurança em `tests/Feature/Security/` (nenhum dado de tenant vem do request) e rodada do agente `security`
- [x] 13. Documentar os endpoints no Scribe (agente `docs`) e atualizar `docs/manual-producao.md` com os eventos de webhook a assinar
- [x] 14. Pint (`--dirty`) e suíte inteira passando

## Divergências

- **`phases[].iterations` não existe na API atual do Stripe (2026-06-24):** o primeiro downgrade real deu `Received unknown parameter: phases[iterations]` (502 `gateway_error`). A fase do novo preço passou a usar `duration` (um período do novo preço, `interval`/`interval_count`), com `end_behavior: release`. Era o risco 3 do plano, só detectável no Stripe real. Teste de regressão em `ChangeSubscriptionPhaseTest`. Ainda vale validar a virada do ciclo com um Test Clock.

- **Tarefa 12:** auditoria do agente `security` feita; VULN-SUB-01 e VULN-SUB-04 corrigidos (service) e VULN-SUB-03 (webhook sem secret, pré-existente), SUSPEITA-SUB-05 e PERGUNTA-SUB-02 ficaram abertos em `specs/002-security-hardening/spec.md`.
- **Tarefa 13:** docblocks Scribe feitos pelo agente `docs`; o `scribe:generate` avisa FAIL em `PUT /me/avatar` e `PUT /workspaces/{workspace}/avatar` (fora desta feature, não investigado). `docs/manual-producao.md` ainda precisa listar os 3 eventos `subscription_schedule.*` do webhook live.
- **Tarefa 0 segue pendente** (validação no Stripe de teste, com autorização do dono).

- **Tarefa 0 não executada:** CLAUDE.md proíbe mexer em produtos/preços do Stripe sem pedir. Os riscos 1 a 3 do plano seguem em aberto e devem ser validados pelo dono em modo teste antes de ir a produção.
- **Cashier `swap` descartado** (achado do agente `tdd`): faltam `meter_id`/`meter_event_name` em `subscription_items` e `errorIfPaymentFails()` usa `error_if_incomplete`. O plano passou a chamar `subscriptions.update` direto no Stripe, com `pending_if_incomplete`, e a sincronizar a assinatura local.
- **Critério 11:** a família billing é fixa (spec 007), então não existe "preferência desligada"; ajustado na spec.
- **Nome da regra:** `SubscriptionChangeKind::for`.
- **Preço inativo:** responde 404, como inexistente.
- **Implementação (tarefas 1 a 11):** `pending_if_incomplete` com `trial_end=now` e `always_invoice` foi implementado conforme o plano, mas não validado no Stripe real (tarefa 0 segue pendente).
- **Distinção 3DS x cartão recusado:** `pending_update` preenchido vira `payment_action_required` (com `action_url` da fatura hospedada) e só vira `payment_failed` se o payment intent estiver em `requires_payment_method`; `CardException` também vira `payment_failed`. Não usa `expand` de `payment_intent` (evita erro em versões novas da API); o intent é buscado só se vier como id.
- **Fase 2 do schedule:** `iterations: 1` e `end_behavior: release`, com `proration_behavior: none`. Risco: `subscription_schedule.completed` só chega ao fim da 2a fase; por isso o webhook também fecha a mudança em `customer.subscription.updated` (troca de preço) e notifica por aí, com dedupe pelo id do schedule.
- **Upgrade que falha depois de liberar o schedule:** o downgrade agendado já foi liberado no Stripe (obrigatório para trocar o preço) e fica `canceled` localmente mesmo se a cobrança falhar; o cliente precisa reagendar. Não há reagendamento automático.
- **Mesmo downgrade já agendado:** pedir de novo é idempotente (200, sem chamada ao Stripe nem nova notificação).
- **`action_url`:** `BillingException::render()` foi sobrescrito para incluir `action_url` na raiz da resposta.
- **Preço atual fora do catálogo local:** a troca devolve `subscription_change_not_allowed` (não dá para classificar).
- **Tarefas 12 a 14:** 12 (testes de segurança) já existem e passam; rodada do agente `security`, docs Scribe e `manual-producao.md` ficam para os agentes/dono.
