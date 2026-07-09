# Requisitos Funcionais — Gestão do Financeiro (Cliente)

**Documento de Especificação de Requisitos**: API REST para assinatura, pagamento e gerenciamento de faturamento do lado cliente no Pandy Pro (Laravel).

**Nota de Arquitetura**: Este documento reflete a adoção de **Laravel Cashier (Stripe)** como plataforma de pagamento. A decisão está registrada no doc 005 (Plano de Implementação). O Cashier gerencia nativamente Customer, Subscription, Payment Methods e Invoices. A gestão de inadimplência é **simplificada**: estado derivado do Stripe (via `stripe_status`) + retentativas nativas do Stripe, sem escada custom de níveis.

**Nota — limites de plano deferidos:** por decisão atual, **não há limites de plano nesta fase** (serão alinhados depois). Portanto o endpoint de uso/quota e qualquer enforcement de limite (contas sociais, posts/mês, storage) estão **fora de escopo aqui**. O controle de acesso desta fase é **apenas por inadimplência** (assinatura válida vs. bloqueada), não por consumo.

---

## 1. Visão Geral

O cliente (usuário final assinante do SaaS) interage com o Pandy Pro para:

- **Criar uma assinatura** no processo de signup, escolhendo um plano, preço (moeda + frequência) e iniciando um período de trial.
- **Visualizar a assinatura atual** (status, plano ativo, período, datas do trial, estado de faturamento).
- **Gerenciar métodos de pagamento** (listar, adicionar, remover, definir como padrão).
- **Acessar histórico de pagamentos e faturas** com links para download hospedados no Stripe.

**Escopo**: Endpoints REST protegidos (auth:sanctum + verified) scoped ao Workspace (tenant), retornando respostas em pt-BR com permissão `billing` (CheckPermission middleware).

---

## 2. Assinatura via Signup

### Fluxo de Assinatura Inicial

1. **Seleção de Plano & Preço**: O cliente na landing page seleciona:
   - Um Plano (ex: "Professional")
   - Uma Frequência (mensal/anual)
   - Uma Moeda (BRL/USD/EUR/GBP)

2. **Criação de Stripe Customer**: Ao confirmar o signup, o backend cria um Stripe Customer (via Cashier trait Billable no Workspace) com email e nome. O `stripe_id` (customer ID `cus_…`) é armazenado no Workspace.

3. **Abertura de Stripe Checkout Session**: O backend invoca:
   ```php
   $workspace->newSubscription('default', $gateway_price_id)
       ->trialDays($price->trial_period_days)
       ->withMetadata(['workspace_id' => $workspace->uuid])
       ->checkout([
           'success_url' => url('/checkout/success?session_id={CHECKOUT_SESSION_ID}'),
           'cancel_url' => url('/checkout/cancel')
       ])
   ```
   Isso cria uma **Stripe Checkout Session** (modo subscription) que:
   - Contém o `gateway_price_id` e configura trial de acordo com `trial_period_days`
   - Armazena correlação (uuid do workspace) em metadata
   - Retorna URL hospedada para o cliente acessar

4. **Redirecionamento para Checkout Hospedado**: Cliente é redirecionado para a URL do Stripe Checkout, realizando o pagamento lá (cartão, boleto, etc).

5. **Webhook de Sucesso**: Quando o cliente conclui o pagamento, o Cashier WebhookController processa o evento `invoice.payment_succeeded` com `billing_reason=subscription_create`. Isso:
   - Sincroniza a Subscription local (tabela `subscriptions`, gerenciada pelo Cashier) para status:
     - `stripe_status`: "trialing" (se dentro do período de trial) ou "active"
     - `stripe_id`: `sub_…` (subscription ID do Stripe)
     - `trial_ends_at`, `ends_at`: períodos sincronizados
   - Estado de acesso é derivado automaticamente do `stripe_status` via lógica de negócio (ver seção 7.3)

**Resultado**: Uma entidade Subscription local (modelo Laravel), gerenciada pelo Cashier, com colunas do Cashier (`stripe_id`, `stripe_status`, `stripe_price`, `trial_ends_at`, `ends_at`) sincronizadas via webhooks.

---

## 3. Ver Assinatura Atual e Uso

### 3.1 Requisitos Funcionais (Subscriptions)

**RF-C1**: Endpoint `GET /billing/subscription` retorna a assinatura ativa/trialing/past_due do workspace.
- Resposta contém: ID, status (trialing/active/past_due/canceled), plano (name, description), preço (amount, currency, frequency), datas (trial_ends_at, current_period_start/end), billing_status.
- Se múltiplas subscriptions (edge case raro), prioriza a em status mais alto (active > trialing > past_due).
- Status é lido de `stripe_status` (sincronizado pelo Cashier via webhooks).

**RF-C2**: Campo `status` é enum com valores: `trialing`, `active`, `past_due`, `canceled` (reflete `stripe_status` do Cashier).

**RF-C3**: Campo `billing_status` é enum com valores derivados: `active`, `trialing`, `past_due`, `blocked` — indicando o estado de faturamento e acesso do cliente:
- `active`: Assinatura ativa e em dia; acesso total.
- `trialing`: Em período de trial; acesso total.
- `past_due`: Pagamento pendente; Stripe está tentando novamente (durante a janela de retentativas). Acesso mantido com aviso. Veja **RN-C6**.
- `blocked`: Retentativas esgotadas ou assinatura cancelada pelo Stripe; sem acesso (exceto telas de billing para regularizar).

**RF-C4** *(DEFERIDO — limites de plano ainda serão alinhados)*: Endpoint de consumo vs. limites do plano (`GET /billing/subscription/usage`) fica **fora do escopo desta fase**. Enquanto os limites de plano não forem definidos, não há endpoint de uso nem cálculo de quota. Quando alinhado, retornará consumo vs. limite (ex.: contas sociais, posts/mês, storage) — a especificação detalhada será adicionada aqui.

**RF-C5**: Se não há assinatura ativa (workspace novo sem trial/pagamento), retorna 404 com mensagem "Nenhuma assinatura ativa para este workspace."

---

## 4. Gerenciar Métodos de Pagamento

### 4.1 Requisitos Funcionais (Payment Methods)

**Nota de Arquitetura**: Métodos de pagamento são gerenciados **diretamente via Cashier** a partir do Stripe. A tabela local `cards` é **opcional/removida** nesta arquitetura. Recomenda-se expor os helpers do Cashier sem persistência local, simplificando a sincronização.

**RF-C6**: Endpoint `GET /billing/cards` lista todos os métodos de pagamento do workspace a partir do Stripe (via `$workspace->paymentMethods()`):
- ID (id do Stripe `pm_…`), brand (visa/mastercard/amex/diners/etc), last_digits (últimos 4), exp_month, exp_year, is_default.
- Dados sensíveis (número completo) **nunca** trafegam; apenas token tokenizado (pm_…) é armazenado no Stripe.

**RF-C7**: Endpoint `POST /billing/cards` adiciona um novo método de pagamento:
- **Input** (FormRequest): `gateway_payment_method_id` (token do Stripe.js no frontend), opcionalmente `is_default: bool`.
- Backend faz attach no Stripe via `$workspace->addPaymentMethod($gateway_payment_method_id)` e, se `is_default=true`, faz `$workspace->updateDefaultPaymentMethod($gateway_payment_method_id)`.
- **Resposta**: JSON com o novo método de pagamento (ID, brand, last_digits, exp, is_default).
- Sempre há exatamente **1 método padrão** (RF-C10); se nenhum padrão anterior, o novo torna-se automático.

**RF-C8**: Endpoint `PATCH /billing/cards/{payment_method_id}` atualiza configurações do método:
- **Input**: `is_default: bool`.
- Se `is_default=true`, via `$workspace->updateDefaultPaymentMethod($payment_method_id)`, desfaz default dos demais.
- Troca de padrão aplica no **próximo ciclo de cobrança**, não retroativamente.
- **Resposta**: JSON com método atualizado.

**RF-C9**: Endpoint `DELETE /billing/cards/{payment_method_id}` remove um método:
- Backend faz detach no Stripe via `$workspace->deletePaymentMethod($payment_method_id)`.
- NÃO pode deletar o método padrão (retorna 422 com mensagem "Método de pagamento padrão não pode ser removido; defina outro como padrão antes.").
- **Resposta**: 204 No Content.

**RF-C10**: Um workspace sempre tem exatamente **1 método de pagamento padrão** (aplicado em cobranças recorrentes). Se houver múltiplos métodos, exatamente um tem flag `is_default=true`. O Cashier gerencia essa invariante.

---

## 5. Histórico de Pagamentos e Faturas

### 5.1 Opções de Implementação

Este doc apresenta **duas opções** para histórico. Recomenda-se **Opção A** (mais simples); Opção B é útil se filtros/paginação custom forem críticos.

#### Opção A: Invoices ao Vivo (Recomendada)

Expor `$workspace->invoices()` do Cashier diretamente:
- Sempre atualizado com o Stripe
- Sem tabela local necessária
- Simplifica sincronização (zero webhook custom para invoices)
- Desvantagem: paginação/filtros limitados ao que o Stripe oferece

#### Opção B: Tabela Payment Local Leve

Manter tabela **Payment** local populada por listener de webhook:
- Colunas: id, uuid, workspace_id, subscription_id, status enum, amount int, currency enum, paid_at, period_start, period_end, gateway_invoice_id, hosted_invoice_url, receipt_url, created_at, updated_at
- Listener de `invoice.created/updated/payment_succeeded/payment_failed` popula/atualiza Payment (idempotência por gateway_invoice_id)
- Vantagem: paginação rápida, filtros locais, histórico persistente
- Desvantagem: sincronização extra, mais código no webhook

**Recomendação**: Usar **Opção A** inicialmente; migrar para B se uso/filtros custom forem necessários.

### 5.2 Requisitos Funcionais (Payments — Ambas Opções)

**RF-C11**: Endpoint `GET /billing/payments` lista histórico de pagamentos/faturas do workspace com paginação (default 15 items).
- Filtros opcionais (query params): `status` (pending/paid/failed), `date_from`, `date_to`, `sort` (default: -paid_at).
- Dados retornados: Opção A = invoices do Cashier; Opção B = linhas da tabela Payment local.

**RF-C12**: Cada Payment contém:
- ID, status enum, valor, moeda, data de pagamento (paid_at), data de vencimento (due_date).
- Período faturado (period_start, period_end).
- **Links para download** (hospedados no Stripe, cliente acessa direto):
  - `hosted_invoice_url`: URL da nota fiscal interativa (Stripe Invoices)
  - `receipt_url`: URL do recibo em PDF (Stripe Payment Intents)

**RF-C13**: Links do Stripe são pré-gerados no webhook (Opção B) ou fornecidos ao vivo pelo Cashier (Opção A). Nunca são regenerados em Opção B — garantindo links estáveis.

**RF-C14**: Endpoint `GET /billing/payments/{payment_id}` retorna detalhe de um pagamento específico com mesmos campos de RF-C12.

---

## 6. Endpoints Propostos (Cliente)

| Método | Rota                           | Descrição                                              | Autenticação | Permissão     |
|--------|--------------------------------|------|--------------------------------|----|
| GET    | `/billing/subscription`        | Retorna assinatura ativa/trialing/past_due do workspace | auth:sanctum | billing:view  |
| ~~GET~~  | ~~`/billing/subscription/usage`~~ | *(DEFERIDO — limites de plano a alinhar; fora desta fase)* | — | — |
| GET    | `/billing/cards`               | Lista métodos de pagamento do workspace                 | auth:sanctum | billing:view  |
| POST   | `/billing/cards`               | Adiciona novo método de pagamento (via token Stripe.js) | auth:sanctum | billing:edit  |
| PATCH  | `/billing/cards/{payment_method_id}` | Atualiza método de pagamento (ex: is_default)  | auth:sanctum | billing:edit  |
| DELETE | `/billing/cards/{payment_method_id}` | Remove método de pagamento                      | auth:sanctum | billing:edit  |
| GET    | `/billing/payments`            | Lista histórico de pagamentos (paginado)               | auth:sanctum | billing:view  |
| GET    | `/billing/payments/{payment_id}` | Retorna detalhe de um pagamento                        | auth:sanctum | billing:view  |

**Middleware de Autorização**:
- `auth:sanctum`: Usuário autenticado via token Bearer Sanctum.
- `verified`: Email verificado.
- `permission:billing.{ability}`: Grupo de permissão `billing`, abilities `view` (leitura) e `edit` (escrita/deleção).
- **Escopo por Workspace**: Todos os endpoints filtram resultados ao `workspace_id` do access_id do usuário logado (via trait `BelongsToWorkspace`).

**Nota sobre Routing**: O projeto não usa versionamento de API (`/api/v1`). Todos os endpoints de billing usam prefixo `/billing/` dentro do bloco protegido (auth:sanctum + verified).

**Webhook** (Não é endpoint cliente): Rota pública `/stripe/webhook`, sem autenticação, usando WebhookController do Cashier com middleware `VerifyWebhookSignature`.

---

## 7. Regras de Negócio e Restrições

### 7.1 Assinatura e Períodos

**RN-C1**: **Uma assinatura ativa por workspace** — partial unique DB constraint em (workspace_id, stripe_status) para stripe_status in (active, trialing, past_due). Impede múltiplas assinaturas concorrentes e simplifica a lógica de billing.

**RN-C2**: **Período de Trial**: Definido por `Price.trial_period_days` (ex: 14, 0 = sem trial). Ao sucesso do 1º pagamento (webhook `invoice.payment_succeeded` com `billing_reason=subscription_create`):
- Se `now < trial_end_date`: Subscription `stripe_status` = "trialing", `billing_status` = "trialing"
- Se `now >= trial_end_date`: Subscription `stripe_status` = "active", `billing_status` = "active"

**RN-C3**: **Ciclo de Faturamento**: Sincronizado pelo Stripe via webhooks (Cashier gerencia). Cliente não manipula períodos — apenas consome dados lidos de `current_period_start` e `current_period_end` (colunas do Cashier).

### 7.2 Métodos de Pagamento

**RN-C4**: **Um método padrão por workspace** — usado para cobranças recorrentes. Troca de padrão (via PATCH /billing/cards/{id}) aplica no **próximo ciclo**, não retroativamente.

**RN-C5**: **Segurança de Dados de Cartão**: O backend NUNCA trafega ou armazena números completos de cartões. Dados sensíveis (number, CVC) ficam apenas no navegador (Stripe.js) ou tokenizados (`pm_…` no Stripe). Backend expõe apenas brand, last_digits, exp_month/year.

### 7.3 Inadimplência e Controle de Acesso (Modelo Simplificado)

**RN-C6**: **Três estados de acesso**, derivados do `stripe_status` e configuração nativa do Stripe:
- **Em dia** (`stripe_status` = `active` ou `trialing`) → **acesso total**. Cliente pode criar recursos e fazer uploads. *(Sem limites/quota de plano nesta fase — deferido.)*
- **Em retentativa** (`stripe_status` = `past_due`) → **acesso mantido durante a janela de retentativas do Stripe**. Configuração nativa `Cashier::keepPastDueSubscriptionsActive()` permite que `valid()` retorne true, preservando acesso. Cliente vê aviso: "Pagamento pendente; estamos tentando novamente. Atualize seu método de pagamento para evitar bloqueio."
- **Bloqueado** (retentativas esgotadas; Stripe cancela a assinatura via `customer.subscription.deleted` ou marca como `unpaid`) → **sem acesso a operações**, exceto rotas de billing (`/billing/*`) para visualizar status e atualizar pagamento. Middleware bloqueia demais requisições com erro 402 Payment Required.

**RN-C7**: A transição entre estados é **controlada nativamente pelo Stripe**, não por job nosso:
- Webhook `invoice.payment_failed` → `stripe_status` passa para "past_due" (Stripe inicia retentativas conforme cronograma do dashboard Stripe)
- Stripe re-tenta a cobrança durante a janela configurada (Smart Retries; padrão ~1-2 semanas). Cliente mantém acesso (via `keepPastDueSubscriptionsActive()`)
- Se retentativas esgotam, Stripe cancela a assinatura: evento `customer.subscription.deleted` ou `updated` com status `canceled`/`unpaid` → `stripe_status` atualiza → `valid()` retorna false → acesso bloqueado automaticamente
- Se pagamento é bem-sucedido em retentativa, webhook `invoice.payment_succeeded` → `stripe_status` volta para "active" → acesso restaurado

**RN-C8**: Um endpoint `GET /billing/subscription` **DEVE expor**:
- Campo `billing_status` (enum: `active`, `trialing`, `past_due`, `blocked`) — estado derivado simples do `stripe_status`
- Campo `billing_message` (string, opcional) — aviso textual: ex. "Pagamento pendente. Tentando novamente..." (se `past_due`) ou "Sua assinatura foi cancelada. Contate suporte para reativar." (se `blocked`)

### 7.4 Comportamento do Cliente Durante Trial

**RN-C9**: Durante trial (`stripe_status = trialing`), Cliente vê:
- Assinatura com status "Em período de teste"
- Data de término do trial (`trial_ends_at`)
- Aviso no dashboard: "Você está em período de teste gratuito até [data]. Após isso, começaremos a cobrar."
- Sem bloqueios de funcionalidade; acesso total (`billing_status` = "trialing").

**RN-C10**: Se trial expira e nenhum pagamento é completado, Subscription transiciona para `stripe_status` = "past_due" → retentativas Stripe → cancelamento (policy a definir com PM; ex: após ~1-2 semanas de retentativas sem sucesso).

### 7.5 Mudança de Plano (Escopo Futuro)

**RN-C11**: Upgrade/downgrade de plano é descrito apenas em alto nível:
- Cliente pode solicitar mudança para outro Plano.
- Backend valida (não pode fazer downgrade se sobre quota).
- Stripe recebe ordem de troca (change subscription items via `$subscription->swapAndInvoice()`).
- Cobrança é pro-rata ou sobre/sub-crédito (policy de PM).
- Implementação detalhada em documento futuro (não cobre RFC-C04).

---

## 8. Requisitos Não-Funcionais (Cliente)

### 8.1 Formato de Resposta

**RN-F1**: Todas as respostas de sucesso (2xx) usam **JsonResource** do Laravel:
```json
{
  "data": { /* recurso serializado */ },
  "message": "Cartão adicionado com sucesso."
}
```
ou, para listas:
```json
{
  "data": [ /* array de recursos */ ],
  "meta": { "total": 50, "per_page": 15, "current_page": 1 }
}
```

**RN-F2**: Mensagens de sucesso, erro e validação em **pt-BR** (ex: "Método de pagamento padrão não pode ser removido", "Email já registrado").

### 8.2 Tratamento de Erro

**RN-F3**: Exceções retornam status HTTP apropriado + JSON de erro:
```json
{
  "error": "subscription_not_found",
  "message": "Nenhuma assinatura ativa para este workspace."
}
```

**RN-F4**: Códigos HTTP:
- `200 OK`: GET bem-sucedido
- `201 Created`: Recurso criado (POST)
- `204 No Content`: Deleção bem-sucedida (DELETE)
- `401 Unauthorized`: Sem autenticação
- `402 Payment Required`: Assinatura inadimplente/bloqueada (ex: ao tentar criar recurso com billing_status = blocked)
- `403 Forbidden`: Sem permissão (grupo billing)
- `404 Not Found`: Recurso não encontrado
- `409 Conflict`: Conflito (ex: método padrão)
- `422 Unprocessable Entity`: Validação falhou (FormRequest) ou lógica de negócio violada (ex: deletar método padrão)

### 8.3 Autenticação e Autorização

**RN-F5**: Cada endpoint requer:
- `auth:sanctum`: User autenticado via token Bearer (Sanctum)
- `verified`: User.email_verified_at não é null
- `permission:billing.{view|edit}`: Usuário tem permissão grupo `billing` com ability view/edit
- Middleware `CheckPermission` implementa lógica (super_admin bypassa)

**RN-F6**: **Escopo por Workspace**:
- Todas queries executadas com global scope `whereWorkspaceId($user->access->workspace_id)`
- Cliente vê apenas dados de seu workspace
- BelongsToWorkspace trait aplicado em Subscription

**RN-F7**: **Segurança de Dados**:
- Nenhuma coluna com dados sensíveis (números de cartão, CVCs) é retornada em JSON
- `gateway_payment_method_id` (token Stripe `pm_…`) não é exposto no JSON (apenas internamente)
- URLs de fatura do Stripe (`hosted_invoice_url`, `receipt_url`) são pré-geradas pelo Stripe e expostas (cliente acessa direto, sem CORS issues)

### 8.4 Paginação

**RN-F8**: Endpoints GET com múltiplos registros (e.g., `GET /billing/payments`) suportam paginação:
- Query params: `page` (default 1), `per_page` (default 15, max 100)
- Resposta inclui `meta: { total, per_page, current_page, last_page }`

### 8.5 Validação de Input (FormRequest)

**RN-F9**: FormRequest por endpoint (ex: StoreBillingCardRequest, UpdateBillingCardRequest) com regras:
- `gateway_payment_method_id`: required|string|min:3 (token do Stripe.js)
- `is_default`: nullable|boolean
- Mensagens de validação em pt-BR

**RN-F10**: Erro de validação retorna **422** com `errors: { field: [messages] }`:
```json
{
  "message": "Validation failed",
  "errors": {
    "gateway_payment_method_id": ["O campo payment method é obrigatório."]
  }
}
```

### 8.6 Rastreamento e Auditoria

**RN-F11**: Modelos usam owen-it/laravel-auditing (Observer) para registrar alterações em tabela audits. Campos como `is_default`, `gateway_payment_method_id` são auditados.

**RN-F12**: Webhooks do Stripe (via Cashier WebhookController) são logados (Laravel logs) com event_id, tipo, status, e correlação local (ex: workspace_id do metadata).

### 8.7 Performance e Caching

**RN-F13**: Dados de assinatura (`GET /billing/subscription`) podem ser **cacheados por 5 minutos** na tag `billing.subscription.{workspace_id}`, invalidados ao webhook de mudança (invoice.payment_succeeded, invoice.payment_failed, customer.subscription.updated).

**RN-F14**: Query `with()` (eager-load relacionamentos) para evitar N+1:
- GET /billing/subscription: inclui Price, Plan
- GET /billing/payments: (Opção B) inclui Subscription
- GET /billing/cards: sem relacionamentos adicionais

---

## 9. Entidades de Banco de Dados (Resumo para Cliente)

**Subscription** (modelo do Cashier estendido, trait BelongsToWorkspace)
- Colunas do Cashier: id, uuid, workspace_id, stripe_id (cus_/sub_…), stripe_price, stripe_status enum, trial_ends_at, ends_at, created_at, updated_at
- **Não há colunas custom para inadimplência** — `billing_status` é derivado em tempo real do `stripe_status` e estado de validação do Cashier (`valid()`)
- Relação: hasOne/belongsTo Plan (via Price)
- Config no AppServiceProvider: `Cashier::keepPastDueSubscriptionsActive()` faz com que subscriptions em `past_due` sejam tratadas como válidas durante a janela de retentativas Stripe

**Payment Methods** (Gerenciados ao vivo pelo Stripe, via `$workspace->paymentMethods()`)
- Não há tabela local (Opção A, recomendada); se optar por Opção B, tabela Payment conforme abaixo

**Payment** (Opção B — Tabela Local Leve, opcional)
- Colunas: id, uuid, workspace_id, subscription_id, status enum, amount int, currency enum, paid_at timestamp, period_start date, period_end date, gateway_invoice_id, hosted_invoice_url, receipt_url, created_at, updated_at
- Relação: belongsTo Subscription
- Sincronização: listener de WebhookReceived (invoice.created, invoice.updated, invoice.payment_succeeded, invoice.payment_failed)

---

## 10. Considerações Futuras

- **Mudança de Plano (upgrade/downgrade)**: Detalhada em RFC futuro; envolve pro-rata de cobrança, revalidação de quota, alteração via Cashier `swapAndInvoice()`.
- **Cancelamento de Assinatura**: Endpoint DELETE /billing/subscription (cliente auto-cancela); marca como canceled, notifica Stripe, gera crédito se aplicável.
- **Histórico de Assinatura**: Queries para listar assinaturas passadas (para compliance/audit).
- **Reemissão de Faturas**: Endpoint para solicitar reemissão em formato diferente (atualmente gerada pelo Stripe via webhook).
- **Conformidade PSD2/SCA**: Strong Customer Authentication para boletos e cartões; implementação depende de evolução Stripe + frontend.
- **Sincronização Manual**: Endpoint admin privado (não cliente-facing) `POST /admin/webhooks/stripe/sync/{workspace_id}` para forçar sincronização em caso de falha de webhook (útil em testes/debug).

---

## Anexo: Matriz de Permissões

| Recurso      | Grupo      | Abilities     | Descrição                                   |
|--------------|-----------|---------------|---------------------------------------------|
| Subscription | `billing` | `view`        | Ler assinatura, uso, status e billing_status |
| Subscription | `billing` | `edit`        | (Futuro: mudar plano)                       |
| Payment Method | `billing` | `view`        | Listar métodos de pagamento                 |
| Payment Method | `billing` | `edit`        | Adicionar, alterar default, remover         |
| Payment      | `billing` | `view`        | Ler histórico de pagamentos/faturas         |

---

**Versão**: 3.0 (Cashier + Inadimplência Simplificada)  
**Data**: 2026-07-07  
**Status**: Rascunho para revisão
