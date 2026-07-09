# 003 — Webhook Stripe (Cashier) e Gestão de Inadimplência

> **Nota de revisão:** este documento reflete três decisões de arquitetura: (1) adotar o **Laravel Cashier (Stripe)** em vez de `stripe/stripe-php` puro com abstração de gateway; (2) **simplificar o dunning** — sem escada custom de `access_level` e sem job agendado. As retentativas de cobrança são do **Stripe (Smart Retries, config no dashboard)** e o acesso é **derivado do `stripe_status`** sincronizado pelo Cashier, com uma checagem única `valid()` no enforcement; (3) **limites de plano deferidos** — nesta fase **não há limites/quota de plano** (serão alinhados depois), então não há sincronização de limites no 1º pagamento nem enforcement de quota. O controle de acesso é **apenas por inadimplência**.

## 1. Visão geral

Este documento define os requisitos da frente **"Webhook e Gestão de Inadimplência"** do financeiro do Pandy Pro.

O módulo de webhook é o **canal oficial de sincronização de estado** entre o Stripe e o banco local. Após a criação da assinatura via Checkout (frente Cliente), o estado real — períodos de cobrança, trial, status, pagamentos, faturas — é escrito **pelos webhooks, nunca pelas chamadas de criação**. Com o Cashier, essa frente se divide em duas responsabilidades:

- **Cashier (nativo)**: recebe e verifica os eventos, e mantém sincronizada a **própria tabela `subscriptions`** (status, períodos, trial, cancelamento) e os dados de customer no Workspace (Billable).
- **Nossa (custom)**: tabela `Payment` local (registro de pagamentos/faturas) e, no 1º pagamento, apenas setar `start_date`. *(Sincronização de limites do plano fica para depois — limites deferidos.)* A **gestão de inadimplência** ficou mínima: o cronograma de retentativas é do **Stripe** (dashboard), o estado de acesso deriva do `stripe_status` do Cashier, e o enforcement é uma checagem única `valid()` em middleware (seção 7).

> **Atenção:** no projeto Django de referência, o dunning ficou incompleto (enforcement nunca foi ligado). No Pandy, a seção 7 é **requisito obrigatório** — mas o escopo é pequeno de propósito: config do Stripe + `keepPastDueSubscriptionsActive()` + um middleware.

Depende do doc 002 (Plan/Price/gateway_product_id/gateway_price_id, criados via `Cashier::stripe()`) e alimenta a frente Cliente (doc 004+). Pré-requisitos: `laravel/cashier` instalado (traz `stripe/stripe-php` junto), trait `Billable` no **Workspace**, migrations do Cashier, e config em `config/cashier.php` (`STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` via `.env`).

## 2. Endpoint e segurança

- O endpoint é o **`Laravel\Cashier\Http\Controllers\WebhookController`** do Cashier, montado automaticamente pela rota do pacote (default **`POST /stripe/webhook`**). Rota **PÚBLICA**, fora do bloco `auth:sanctum` + `verified` — **não escrevemos controller de verificação na mão**.
- **Verificação de assinatura já embutida**: com `STRIPE_WEBHOOK_SECRET` definido, o Cashier aplica o middleware `VerifyWebhookSignature` (header `Stripe-Signature`) antes de qualquer processamento. Payload/assinatura inválidos → **403/400 sem tocar em handler**. Definir o secret é **obrigatório** em todos os ambientes que recebem webhooks (usar `stripe listen` em dev).
- **Contrato de respostas HTTP** (o Stripe decide re-tentativa pelo status; semântica preservada da versão anterior):

  | Situação | HTTP | Efeito no Stripe |
  |---|---|---|
  | Assinatura/payload inválido | 400/403 (Cashier) | não re-tenta (descartado como inválido) |
  | Evento processado com sucesso | 200 | encerra entrega |
  | Evento não mapeado / ignorado | 200 | encerra entrega (sem retry inútil) |
  | Exceção durante o processamento | 500 | **Stripe re-tenta** com backoff |

  Regra prática para o nosso código: **listener que falha de forma transiente deve propagar exceção** (→ 500 → retry do Stripe) *se rodar inline*; se despachar Job (seção 4), a falha vira responsabilidade do retry do Horizon.
- **Eventos a registrar no Stripe**: configurar o webhook endpoint no dashboard (ou via `php artisan cashier:webhook`) com os eventos nativos do Cashier + os que nosso listener consome (seção 6).
- A rota do Cashier já é isenta de CSRF; pode receber throttle próprio, mais permissivo que o das rotas autenticadas.
- **Ponto de extensão custom**: nossa lógica entra via **listener do evento `Laravel\Cashier\Events\WebhookReceived`** (recomendado) — o Cashier dispara esse evento para **todo** webhook recebido, antes/além do handling nativo. Alternativa: subclasse do `WebhookController` sobrescrevendo `handleInvoicePaymentSucceeded`/`handleInvoicePaymentFailed`. **Recomendamos o listener**: não briga com o handling nativo, mantém o custom isolado e testável. Observação: o projeto não usa event bus, mas o Cashier introduz um só para webhooks — aceitável e localizado.

```php
// AppServiceProvider ou EventServiceProvider
Event::listen(WebhookReceived::class, StripeWebhookListener::class);
```

## 3. Idempotência (simplificada)

O Stripe garante entrega *at-least-once*: o mesmo evento pode chegar duas vezes (retry após timeout, replay manual, redelivery). Com o Cashier, a responsabilidade se divide:

**Estado do Cashier — já é idempotente (não é problema nosso)**
- O handling nativo sincroniza a tabela `subscriptions` a partir do payload do Stripe: reprocessar o mesmo evento reescreve o mesmo estado. Não precisamos de dedup para isso. Como o acesso é **derivado** desse estado (seção 7), o dunning também não precisa de dedup próprio.

**Efeitos custom nossos — dedup leve em 2 camadas**
- **Camada 1 — cache por `event.id`** (rápida, best-effort): no início do listener, consultar `Cache` (Redis) por chave derivada do `event.id` (ex.: `stripe:webhook:{event_id}`); se já processado → retornar sem reprocessar; senão marcar com **TTL de 24h** e seguir.
- **Camada 2 — constraints partial-unique no banco** (definitiva): índices únicos parciais (WHERE campo IS NOT NULL) em `payments`: `gateway_checkout_session_id` (`cs_…`), `gateway_invoice_id` (`in_…`), `gateway_payment_intent_id` (`pi_…`). Handlers custom usam *find-or-create* pelo id do Stripe, nunca insert cego; atualizações de status são idempotentes por natureza.

**Por que ainda as duas (para o custom)?** O cache é rápido mas volátil (flush, TTL, corrida entre pods) — evita custo de reprocessamento; as constraints de DB são a garantia real contra linhas duplicadas em `Payment`. Cache = otimização; DB = correção. A diferença para a versão anterior: **o escopo encolheu** — só protegemos nossos efeitos (a tabela `Payment`), não a sincronização de assinatura, que o Cashier já resolve.

## 4. Processamento assíncrono

O `WebhookController` do Cashier processa **inline** (verificação + sync nativo + dispatch do `WebhookReceived`) e responde na mesma requisição. O sync nativo é leve (escritas locais); o risco de latência fica nos **nossos efeitos custom**. Padrão:

1. **Listener `StripeWebhookListener`** (síncrono, dentro da request): aplica o dedup de cache (seção 3), filtra os tipos de evento que nos interessam (seção 6); tipos irrelevantes → retorna sem efeito (o Cashier já responde 200).
2. Para efeitos custom **pesados** (criar/atualizar `Payment` e, no 1º pagamento, setar `start_date`): o listener **despacha um Job** (`ProcessStripeWebhookJob`, `ShouldQueue`, fila Redis/Horizon dedicada ex. `webhooks`) com o payload do evento. O Job executa dentro de `DB::transaction()` — todas as escritas de um evento são atômicas. *(Limites de plano deferidos; quando forem definidos, a sincronização no 1º pagamento entra aqui.)*
3. Falha no Job → retries do Horizon (com backoff); esgotados → `failed_jobs` + alerta.

**Trade-off assumido:** o Stripe recomenda responder em poucos segundos. O sync nativo do Cashier roda inline (aceito: é rápido e, se falhar, o 500 aproveita o retry do Stripe). Nosso custom em fila responde rápido, mas o 200 passa a significar "aceito", não "processado" — falha posterior no Job **não** dispara retry do Stripe. Mitigação: retries idempotentes do Horizon, monitoramento de `failed_jobs` e replay manual pelo dashboard do Stripe. Efeitos custom triviais podem, como exceção documentada, rodar síncronos no listener — mas o padrão é Job.

## 5. Correlação evento → entidade local

- **Subscription**: o **Cashier correlaciona automaticamente** pelo `subscriptions.stripe_id` (`sub_…`) — é o mecanismo principal, sem código nosso. Como **reforço** para a tabela `Payment` custom, continuar gravando o uuid local via metadata no Checkout (`->withMetadata(['subscription_uuid' => …])` na frente Cliente); os handlers custom de invoice leem esse metadata quando presente e caem no `stripe_id` como fallback.
- **Customer / Workspace**: o Cashier resolve o Billable (Workspace) por `workspaces.stripe_id` (`cus_…`, coluna criada pela migration do Cashier) — usado tanto pelo sync nativo quanto pelos nossos handlers (ex.: vincular o `Payment` ao workspace correto).
- Entidade não encontrada por nenhuma chave → logar com contexto e encerrar sem erro (evento órfão; não adianta o Stripe re-tentar) — exceto quando houver suspeita de corrida (ex.: invoice chegou antes do commit local), caso em que falhar o Job para aproveitar o retry do Horizon.

## 6. Eventos tratados

Três colunas: quem trata cada evento e com que efeito. **"Cashier nativo"** = zero código nosso; **"nosso listener"** = `WebhookReceived` → Job custom.

> **Princípio central:** o acesso do tenant é **derivado do `stripe_status`** que o Cashier sincroniza — **não escrevemos nenhuma coluna custom de acesso** em nenhum handler. A transição para bloqueio acontece "sozinha": quando o Stripe cancela a assinatura (retentativas esgotadas), `customer.subscription.deleted`/`updated` levam o `stripe_status` a `canceled`/`unpaid`, `valid()` passa a `false` e o middleware (seção 7) bloqueia.

| Evento Stripe | Quem trata | Efeito |
|---|---|---|
| `customer.subscription.created` | **Cashier nativo** | Cria a linha em `subscriptions` (+ `subscription_items`): `stripe_id`, `stripe_status`, `stripe_price`, `trial_ends_at`. |
| `customer.subscription.updated` | **Cashier nativo** | Sincroniza status/preço/trial/quantidade e `ends_at` (cancel-at-period-end). Inclui as transições `active → past_due` (início da janela de retry) e `past_due → canceled`/`unpaid` (retentativas esgotadas) — **é isso que muda o acesso**, sem código nosso. |
| `customer.subscription.deleted` | **Cashier nativo** | Marca a subscription como cancelada (`stripe_status`, `ends_at`) → `valid()` vira `false` → **bloqueio automático** pelo middleware. Nenhuma escrita custom. |
| `customer.updated` / `customer.deleted` | **Cashier nativo** | Sincroniza dados de customer/método de pagamento default no Workspace (`pm_type`, `pm_last_four`); deleted limpa os campos. |
| `invoice.payment_action_required` | **Cashier nativo** | Notifica necessidade de confirmação SCA/3DS (fluxo de pagamento incompleto do Cashier). |
| `invoice.created` | **Nosso listener** | Cria (ou linka, se já existir) Payment `pending` com `gateway_invoice_id`, amount, currency, `due_date`, períodos, vinculado à Subscription. |
| `invoice.payment_succeeded` | **Nosso listener** | Se `billing_reason == 'subscription_create'` (**1º pagamento**): seta `start_date`. Sempre: Payment → `paid`, `paid_at`, `gateway_hosted_invoice_url`, `gateway_invoice_pdf`, `receipt_url`, `gateway_payment_intent_id`. Tudo em uma transação. (Status/períodos da subscription: Cashier. A recuperação de acesso após `past_due` também é automática — o `stripe_status` volta a `active` via `customer.subscription.updated`. *Sync de limites de plano: deferido.*) |
| `invoice.payment_failed` | **Nosso listener** | **Só LOG/observabilidade** (+ opcionalmente Payment → `failed`, 1 linha). **Nenhuma transição de acesso aqui**: quem re-tenta a cobrança é o **Stripe** (Smart Retries) e o estado de acesso deriva do `stripe_status` (`past_due` chega pelo `customer.subscription.updated`, nativo). E-mails de "pagamento falhou" podem ser os automáticos do próprio Stripe. |
| `invoice.voided` | **Nosso listener** | Payment da invoice → status `void`. |
| `checkout.session.expired` | **Nosso listener** | Payment do checkout (por `gateway_checkout_session_id`) → status `expired`. |
| `payment_method.attached` / `.detached` | **Opcional** (nosso listener) | Só se a tabela `Card` local for mantida como cache (ver seção 9); recomendação é usar `paymentMethods()` do Cashier ao vivo e **não** tratar esses eventos. |

Eventos fora do mapa: o Cashier responde **200** e nosso listener ignora (+ métrica/log, ver seção 8). Handlers custom devem ser classes pequenas e testáveis (Pest), recebendo o payload do evento já verificado pelo Cashier.

## 7. Gestão de inadimplência (dunning) — modelo simplificado

Modelo **"não pagou → período de retry → bloqueia"**, aproveitando o mecanismo **nativo** do Stripe (Smart Retries). **Três estados, sem job custom e sem coluna custom** — o estado de acesso é sempre derivado do `stripe_status` sincronizado pelo Cashier:

| Estado | `stripe_status` | Acesso |
|---|---|---|
| **Em dia** | `active` / `trialing` | Total. |
| **Em retentativa** | `past_due` | **Mantém acesso total** enquanto o Stripe re-tenta a cobrança (janela configurada no dashboard). |
| **Bloqueado** | `canceled` / `unpaid` (retentativas esgotadas → Stripe cancela) | Bloqueado, exceto rotas de billing para regularizar. |

O que implementar (mínimo):

### 7.1 Config do Cashier — `keepPastDueSubscriptionsActive()`

Num service provider (`AppServiceProvider` ou provider de billing):

```php
// boot()
Cashier::keepPastDueSubscriptionsActive();
```

Isso faz o Cashier tratar `past_due` como ainda válido — `valid()`/`active()` retornam `true` — preservando o acesso durante a janela de retry. **Sem essa chamada** o default do Cashier (`deactivatePastDue = true`) bloquearia o tenant já na 1ª falha de pagamento, antes de o Stripe re-tentar.

### 7.2 Config no dashboard do Stripe (setup, não código)

Passo de configuração documentado como pré-requisito de deploy, em *Settings → Billing → Subscriptions and emails*:

- **Cronograma de retentativas**: Smart Retries (recomendado) ou cronograma fixo — ex. re-tentar por ~1–2 semanas. É esse cronograma que define a duração do estado "em retentativa"; **não há job nosso**.
- **Ação final ao esgotar retentativas**: **"Cancelar assinatura"** — é isso que dispara `customer.subscription.deleted` e leva ao bloqueio.
- **Opcional**: habilitar os e-mails automáticos do Stripe de falha de pagamento/atualização de cartão — dispensa notificação custom nossa nesta fase.

### 7.3 Enforcement em request-time — checagem única

**Middleware** (ex.: `EnsureSubscriptionAccess`) registrado no grupo autenticado, integrado ao sistema de permissões existente (`CheckPermission`), com **uma** checagem:

1. Carrega a assinatura corrente do **workspace** do usuário: `$sub = $workspace->subscription('default')` (via `Access` ativo; cachear por request).
2. `$sub && $sub->valid()` → segue. (`valid()` do Cashier = active | onTrial | onGracePeriod; com `keepPastDueSubscriptionsActive()`, `past_due` também conta como válido. Pode anexar header/flag quando `pastDue()` para o front exibir aviso de "pagamento pendente".)
3. Senão → **bloqueia tudo, exceto rotas de billing/regularização** (para o cliente pagar e reativar).

> Variante somente-leitura (opcional, 1 linha de mudança): em vez de bloquear tudo, bloquear só métodos de escrita (POST/PUT/PATCH/DELETE) e consumo de quota. Default assumido: **bloqueio total**.

Respostas de bloqueio seguem o padrão de exceptions do projeto: `BillingException` (ou `SubscriptionException`) com enum implementando `ErrorEnumInterface` — **402 Payment Required** para inadimplência, **403** para bloqueio total — mensagens em pt-BR, render padrão `{error, message}`. *(Enforcement de limites/quota de plano está deferido — ver nota do topo; o middleware desta fase checa apenas a validade da assinatura, não consumo.)*

> **Não confundir:** o `onGracePeriod()` do Cashier significa "assinatura cancelada com `ends_at` no futuro" (cancel-at-period-end) — o tenant mantém acesso até o fim do período pago, o que é o comportamento desejado. Não há mais conceito próprio de "grace" de inadimplência.

## 8. Requisitos não-funcionais

- **Resiliência**: o sync nativo do Cashier já é resiliente/idempotente; nossos handlers custom também devem ser (retry do Stripe ou do Horizon nunca duplica linha em `Payment`); falhas transientes no Job propagam exceção para aproveitar retry do Horizon; falhas permanentes (evento órfão, payload malformado) → log + descarte. O estado de acesso, por ser **derivado** do `stripe_status`, não tem estado próprio a corromper.
- **Observabilidade**: logar todo evento recebido pelo listener (`event.id`, tipo, resultado: processado/ignorado/duplicado/erro) com contexto estruturado — em particular `invoice.payment_failed`, cujo único papel é este registro; mudanças em Subscription/Payment auditadas via **owen-it/laravel-auditing** (já instalado) — as transições de `stripe_status` escritas pelo Cashier aparecem na trilha de auditoria da Subscription.
- **Performance**: responder ao Stripe em **poucos segundos** (meta < 5s). Com o sync nativo inline + custom em fila (seção 4), a resposta fica dominada pelo Cashier — nenhuma chamada externa síncrona no caminho do webhook.
- **Segurança**: `STRIPE_WEBHOOK_SECRET` **obrigatório** (ativa o `VerifyWebhookSignature` do Cashier); secrets exclusivamente via env/config; endpoint não expõe detalhes internos em erros. **Menos superfície nossa**: verificação de assinatura e parsing são do pacote, não código nosso a auditar.
- **Monitoramento de eventos não tratados**: contar/logar tipos de evento fora do mapa para detectar eventos novos relevantes que o Stripe passe a enviar (métrica ou log agregável, revisão periódica).
- **Testes**: cobertura Pest para o listener e handlers custom (incluindo 1º pagamento vs. recorrente), idempotência do custom (mesmo evento 2x) e middleware de enforcement (assinatura `active`/`trialing`/`past_due` passa; `canceled`/`unpaid`/ausente bloqueia com 402/403; rotas de billing sempre acessíveis) — usando os **fakes do Cashier / mock do `StripeClient`** (`Cashier::fake()` quando aplicável) em vez de um FakeGateway próprio. Cobrir também que `keepPastDueSubscriptionsActive()` está ativo (`valid()` true em `past_due`). A verificação de assinatura em si é do Cashier (testada pelo pacote); basta um teste de integração garantindo que a rota está montada e protegida.

## 9. Modelos e campos tocados pelo webhook

**Subscription** — tabela `subscriptions` do **Cashier** (rebuild limpo: a tabela legada por `user_id` é substituída, sem migração de dados), pertencente ao **Workspace** (Billable). Model = `App\Models\Subscription extends Laravel\Cashier\Subscription` (via `CASHIER_SUBSCRIPTION_MODEL`); uuid como route key se seguirmos o padrão do projeto (coluna adicional):
- Colunas do **Cashier** (mantidas nativamente): `stripe_id` (`sub_…`), `stripe_status` (`incomplete`, `active`, `past_due`, `canceled`, `unpaid`, `trialing`, …), `stripe_price`, `quantity`, `trial_ends_at`, `ends_at`.
- **Sem colunas custom de dunning** — `access_level` e `past_due_since` foram removidos da especificação: o estado de acesso deriva do `stripe_status`. A subclasse fica **mínima**; pode, no máximo, expor helpers derivados (ex.: um accessor `billing_status` para o front, mapeando `stripe_status` + `valid()` para `active`/`trialing`/`past_due`/`blocked`), sem persistir nada.
- Regra de unicidade: **1 assinatura por workspace em status `active`/`trialing`/`past_due`** — partial-unique no nível DB (o Cashier usa `type` para múltiplas assinaturas nomeadas; usamos só `default`).

**Payment** — tabela **custom nossa** (1 linha por invoice/checkout; uuid como route key), populada exclusivamente pelo nosso listener/Job:
- `status` (enum: `pending`, `processing`, `paid`, `failed`, `expired`, `void`), `method`, `amount` (centavos), `currency`
- `gateway_checkout_session_id`, `gateway_checkout_url`, `gateway_checkout_expires_at`
- `gateway_invoice_id`, `gateway_hosted_invoice_url`, `gateway_invoice_pdf`
- `gateway_payment_intent_id`, `receipt_url`
- `due_date`, `paid_at`, `period_start`, `period_end`
- Partial-unique nos três `gateway_*_id` (seção 3).

**Workspace (Billable)** — colunas adicionadas pela migration do Cashier: `stripe_id` (`cus_…`), `pm_type`, `pm_last_four`, `trial_ends_at`. Substitui o antigo `gateway_customer_id`. *(Sincronização de limites do plano no workspace fica para depois — limites deferidos.)*

**Card local — OPCIONAL**: o Cashier expõe métodos de pagamento **ao vivo** (`paymentMethods()`, `defaultPaymentMethod()` etc.), tornando a tabela `Card` local desnecessária. **Recomendação: não criar.** Se a frente Cliente (doc 004) decidir mantê-la como cache (para listagem sem chamada ao Stripe), aí sim tratar `payment_method.attached/detached` no listener (seção 6) — com `gateway_payment_method_id` (`pm_…`), `brand`, `last_digits`, `exp_month/year`, `is_default` (partial-unique: 1 default por conta).

**Nomenclatura**: onde a coluna é do **Cashier**, usar o nome dele (`stripe_id`, `stripe_status`, `stripe_price`, `pm_type`, `pm_last_four`, `trial_ends_at`, `ends_at`) — não renomear para `gateway_*`. Entidades/colunas **custom nossas** (Payment) seguem o padrão do projeto; os `gateway_*_id` do Payment e do doc 002 permanecem como nomes locais que armazenam ids do Stripe.

**Config/env**: `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `CASHIER_CURRENCY`, `CASHIER_MODEL`, `CASHIER_SUBSCRIPTION_MODEL` — via `config/cashier.php` + `.env` (substituem as chaves genéricas anteriores em `config/services.php`).
