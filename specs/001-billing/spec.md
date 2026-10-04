# Spec 001 — Billing (Financeiro: Assinaturas e Planos)

> **Origem:** consolidação dos antigos `docs/001` a `docs/004` (conteúdo preservado; só a formatação foi ajustada). O antigo `docs/005` virou o [`plan.md`](plan.md) e o andamento está em [`tasks.md`](tasks.md).
>
> **Como ler as referências cruzadas:** "doc 001", "doc 002", "doc 003" e "doc 004" (e seus `§`) apontam para as seções **[001]**, **[002]**, **[003]** e **[004]** deste arquivo. "doc 005" é o `plan.md`.
>
> **Decisões que se sobrepõem ao texto original** (detalhes no `plan.md` §2 e §9): Laravel Cashier (Stripe-only) em vez de abstração de gateway; dunning simplificado (estado de acesso derivado do `stripe_status`, retentativas do Stripe); **limites de plano DEFERIDOS** — tudo sobre `PlanLimit`, limites, uso ou quota está fora desta fase.

**Status:** implementada, com pendências (ver `tasks.md`) · **Última revisão do conteúdo:** 2026-07-07

---

## [001] Contexto e termos

O pandy pro é um SAAS, ou seja, um serviço de assinatura e para tanto precisamos estruturar um fluxo de cobranças que permita:

- Que o usuário possa assinar o sistema usando um conjunto de métodos de pagamento
- Que o administrador do sistema possa criar e editar os preços e os limites da assinatura
- Que o usuário possa editar seus métodos de pagamento e visualizar o histórico de faturas
- Que o sistema faça automaticamente a cobrança e controle o acesso ao sistema em caso de inadimplência
- Que o usuário possa realizar a assinatura através da landing page, no processo de signup

Para isso, precisamos inicialmente definir os termos que vamos lidar no fluxo:

- **Plano:** Entidade que representa um conjunto de limites para o uso da aplicação. Exemplo: O plano A tem o limite de 5 contas, já o plano B tem o limite de upload de até 10 vídeos mensais.
- **Preço:** Entidade que representa um preço, ou seja, um valor em uma determinada moeda. Exemplo: 50 doláres, 13 reais
- **Assinatura:** Entidade que representa o vínculo da conta com o plano, a partir da assinatura teremos como calcular limites para um determinado cliente.
- **Pagamento:** Entidade que representa um ato individual de pagamento, em um sistema de pagamento recorrente por assinatura cada assinatura terá muitos pagamentos. Todo pagamento tem um status, é feito através de um método e tem uma fatura que representa o que foi gasto para gerar a necessidade daquele pagamento
- **Cliente:** Entidade que realiza os pagamentos com o objetivo de criar/manter a assinatura

O administrador cadastrará Planos, cada plano terá um conjunto de preços a depender da frequência (mensal, anual) e da moeda (dolár, real, euro).

Um usuário se torna cliente ao fazer signup no sistema, ele coloca seus dados e cria uma assinatura, ao criar a assinatura ele terá um período de teste e após isso será efetuado pagamentos recorrentes de acorso com a frequência escolhida.

Um cliente pode alterar seu método de pagamento e o sistema descontará no próximo ciclo no novo método de pagamento.

Um cliente também pode ver os pagamentos realizados, baixar a fatura relacionada àquele pagamento.

Usaremos o stripe como gateway de pagamentos.

Dito isso, as tarefas do backend se divirão em três frentes:

1. Gestão do financeiro - Administrador — seção [002]
2. Webhook e Gestão de Inadimplência — seção [003]
3. Gestão do financeiro - Cliente — seção [004]

---

## [002] Gestão do Financeiro — Administrador

> **Nota de revisão:** `PlanLimit` e os endpoints `/admin/plans/:id/limits` estão **DEFERIDOS** (ver `plan.md` §9 Q7). O resto desta seção vale.

### Visão Geral

Implementar o módulo de Gestão do Financeiro do Administrador no Pandy Pro, responsável por permitir que administradores do sistema cadastrem, editem e gerenciem os Planos e seus respectivos Preços (por frequência e moeda), bem como os limites de uso associados a cada plano. Este módulo é a base do fluxo de cobrança recorrente do SaaS e fornece os dados que serão consumidos pelos demais módulos (Cliente e Webhook/Inadimplência).

### O que deve ser feito?

#### Criar as entidades no banco de dados

**Plano (Plan)** com os atributos:
- name (text)
- description (text)
- is_visible (boolean)
- is_active (boolean)
- gateway_product_id
- created_at
- updated_at

**Limites de Plano (PlanLimit)** com os atributos: *(DEFERIDO)*
- plan_id

**Preço (Price)** com os atributos:
- plan_id
- amount (integer) - armazenar em centavos
- currency (enum)
- frequency (enum)
- trial_period_days (integer)
- gateway_price_id
- created_at
- updated_at

**Histórico de Preço (PriceHistory)** com os atributos:
- price_id
- gateway_price_id
- amount (integer) - armazenar em centavos
- currency (enum)
- frequency (enum)
- trial_period_days (integer)
- archived_at
- reason (text)

Os limites dos planos ainda serão definidos posteriormente.

#### Integrar com o Stripe

**Criação**
- Ao criar um Plano no Pandy → criar Product correspondente no Stripe e armazenar stripe_product_id
- Ao criar um Preço → criar Price correspondente no Stripe e armazenar stripe_price_id

**Edição de Plano**
- Campos editáveis no Plano (name, description) são sincronizados via stripe.products.update() — operação simples.
- Edição de limites (PlanLimit) é local, não reflete no Stripe.

**Edição de Preço (ponto crítico)**

Preços no Stripe são imutáveis. Não existe stripe.prices.update() para amount, currency ou recurring.interval. Para "editar" um Preço, o fluxo correto é:
1. Arquivar o Price antigo no Stripe: `stripe.prices.update(oldId, { active: false })`
2. Criar um novo Price no Stripe com os novos valores
3. Persistir o novo stripe_price_id no banco local, sem apagar o antigo (mover o antigo para o history)

**Desativação de Plano**
- Marcar Plan.is_active = false localmente
- Arquivar todos os Prices ativos do plano no Stripe (active: false)
- Arquivar o Product no Stripe (active: false)
- Não permitir desativar se houver assinaturas ativas no plano
- Não migrar automaticamente assinaturas existentes — elas continuam no preço antigo até decisão explícita do admin ou renovação manual do cliente

**Deleção de Plano**

| Cenário | Comportamento |
|---|---|
| Plano nunca teve assinaturas | Permite hard delete. Antes de deletar: arquivar Product e todos os Prices no Stripe; remover registros locais. |
| Plano teve ou tem assinaturas (ativas ou históricas) | Bloquear hard delete. Apenas soft delete: is_active = false, archived_at = now(). Stripe: Product.active = false. |
| Plano tem assinaturas ativas | Bloquear até todas as assinaturas serem canceladas ou migradas. Retornar 409 Conflict com lista de assinaturas afetadas. |

**Deleção de Preço**

| Cenário | Comportamento |
|---|---|
| Preço nunca foi usado em assinatura/pagamento | Permite hard delete. Arquivar no Stripe e remover registro local + entradas de PriceHistory. |
| Preço foi usado em pelo menos um pagamento ou assinatura (mesmo cancelada) | Bloquear hard delete. Apenas soft delete: is_active = false, archived_at = now(). Stripe: Price.active = false. |
| Preço é o único ativo do plano para uma combinação (currency, frequency) em uso no signup | Bloquear até ser substituído por outro preço ativo. Retornar 409 Conflict. |

### Endpoints

**Planos**
- `POST /admin/plans` — criar plano
- `GET /admin/plans` — listar planos (com filtros: ativo/inativo)
- `GET /admin/plans/:id` — detalhar plano
- `PATCH /admin/plans/:id` — atualizar dados do plano / ativar e desativar
- `DELETE /admin/plans/:id` — remover plano

**Limites do Plano** *(DEFERIDO)*
- `POST /admin/plans/:id/limits` — adicionar limite
- `PATCH /admin/plans/:id/limits/:limitId` — editar limite
- `DELETE /admin/plans/:id/limits/:limitId` — remover limite

**Preços**
- `GET /admin/plans/:id/prices` — listar preços
- `GET /admin/plans/:id/prices/:priceId/versions` — listar histórico de preços
- `POST /admin/plans/:id/prices` — adicionar preço ao plano
- `PATCH /admin/plans/:id/prices/:priceId` — atualizar preço (arquiva e recria no Stripe)
- `DELETE /admin/plans/:id/prices/:priceId` — desativar preço

---

## [003] Webhook Stripe (Cashier) e Gestão de Inadimplência

> **Nota de revisão:** este documento reflete três decisões de arquitetura: (1) adotar o **Laravel Cashier (Stripe)** em vez de `stripe/stripe-php` puro com abstração de gateway; (2) **simplificar o dunning** — sem escada custom de `access_level` e sem job agendado. As retentativas de cobrança são do **Stripe (Smart Retries, config no dashboard)** e o acesso é **derivado do `stripe_status`** sincronizado pelo Cashier, com uma checagem única `valid()` no enforcement; (3) **limites de plano deferidos** — nesta fase **não há limites/quota de plano** (serão alinhados depois), então não há sincronização de limites no 1º pagamento nem enforcement de quota. O controle de acesso é **apenas por inadimplência**.

### 1. Visão geral

Este documento define os requisitos da frente **"Webhook e Gestão de Inadimplência"** do financeiro do Pandy Pro.

O módulo de webhook é o **canal oficial de sincronização de estado** entre o Stripe e o banco local. Após a criação da assinatura via Checkout (frente Cliente), o estado real — períodos de cobrança, trial, status, pagamentos, faturas — é escrito **pelos webhooks, nunca pelas chamadas de criação**. Com o Cashier, essa frente se divide em duas responsabilidades:

- **Cashier (nativo)**: recebe e verifica os eventos, e mantém sincronizada a **própria tabela `subscriptions`** (status, períodos, trial, cancelamento) e os dados de customer no Workspace (Billable).
- **Nossa (custom)**: tabela `Payment` local (registro de pagamentos/faturas) e, no 1º pagamento, apenas setar `start_date`. *(Sincronização de limites do plano fica para depois — limites deferidos.)* A **gestão de inadimplência** ficou mínima: o cronograma de retentativas é do **Stripe** (dashboard), o estado de acesso deriva do `stripe_status` do Cashier, e o enforcement é uma checagem única `valid()` em middleware (seção 7).

> **Atenção:** no projeto Django de referência, o dunning ficou incompleto (enforcement nunca foi ligado). No Pandy, a seção 7 é **requisito obrigatório** — mas o escopo é pequeno de propósito: config do Stripe + `keepPastDueSubscriptionsActive()` + um middleware.

Depende do doc 002 (Plan/Price/gateway_product_id/gateway_price_id, criados via `Cashier::stripe()`) e alimenta a frente Cliente (doc 004+). Pré-requisitos: `laravel/cashier` instalado (traz `stripe/stripe-php` junto), trait `Billable` no **Workspace**, migrations do Cashier, e config em `config/cashier.php` (`STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` via `.env`).

### 2. Endpoint e segurança

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

### 3. Idempotência (simplificada)

O Stripe garante entrega *at-least-once*: o mesmo evento pode chegar duas vezes (retry após timeout, replay manual, redelivery). Com o Cashier, a responsabilidade se divide:

**Estado do Cashier — já é idempotente (não é problema nosso)**
- O handling nativo sincroniza a tabela `subscriptions` a partir do payload do Stripe: reprocessar o mesmo evento reescreve o mesmo estado. Não precisamos de dedup para isso. Como o acesso é **derivado** desse estado (seção 7), o dunning também não precisa de dedup próprio.

**Efeitos custom nossos — dedup leve em 2 camadas**
- **Camada 1 — cache por `event.id`** (rápida, best-effort): no início do listener, consultar `Cache` (Redis) por chave derivada do `event.id` (ex.: `stripe:webhook:{event_id}`); se já processado → retornar sem reprocessar; senão marcar com **TTL de 24h** e seguir.
- **Camada 2 — constraints partial-unique no banco** (definitiva): índices únicos parciais (WHERE campo IS NOT NULL) em `payments`: `gateway_checkout_session_id` (`cs_…`), `gateway_invoice_id` (`in_…`), `gateway_payment_intent_id` (`pi_…`). Handlers custom usam *find-or-create* pelo id do Stripe, nunca insert cego; atualizações de status são idempotentes por natureza.

**Por que ainda as duas (para o custom)?** O cache é rápido mas volátil (flush, TTL, corrida entre pods) — evita custo de reprocessamento; as constraints de DB são a garantia real contra linhas duplicadas em `Payment`. Cache = otimização; DB = correção. A diferença para a versão anterior: **o escopo encolheu** — só protegemos nossos efeitos (a tabela `Payment`), não a sincronização de assinatura, que o Cashier já resolve.

### 4. Processamento assíncrono

O `WebhookController` do Cashier processa **inline** (verificação + sync nativo + dispatch do `WebhookReceived`) e responde na mesma requisição. O sync nativo é leve (escritas locais); o risco de latência fica nos **nossos efeitos custom**. Padrão:

1. **Listener `StripeWebhookListener`** (síncrono, dentro da request): aplica o dedup de cache (seção 3), filtra os tipos de evento que nos interessam (seção 6); tipos irrelevantes → retorna sem efeito (o Cashier já responde 200).
2. Para efeitos custom **pesados** (criar/atualizar `Payment` e, no 1º pagamento, setar `start_date`): o listener **despacha um Job** (`ProcessStripeWebhookJob`, `ShouldQueue`, fila Redis/Horizon dedicada ex. `webhooks`) com o payload do evento. O Job executa dentro de `DB::transaction()` — todas as escritas de um evento são atômicas. *(Limites de plano deferidos; quando forem definidos, a sincronização no 1º pagamento entra aqui.)*
3. Falha no Job → retries do Horizon (com backoff); esgotados → `failed_jobs` + alerta.

**Trade-off assumido:** o Stripe recomenda responder em poucos segundos. O sync nativo do Cashier roda inline (aceito: é rápido e, se falhar, o 500 aproveita o retry do Stripe). Nosso custom em fila responde rápido, mas o 200 passa a significar "aceito", não "processado" — falha posterior no Job **não** dispara retry do Stripe. Mitigação: retries idempotentes do Horizon, monitoramento de `failed_jobs` e replay manual pelo dashboard do Stripe. Efeitos custom triviais podem, como exceção documentada, rodar síncronos no listener — mas o padrão é Job.

### 5. Correlação evento → entidade local

- **Subscription**: o **Cashier correlaciona automaticamente** pelo `subscriptions.stripe_id` (`sub_…`) — é o mecanismo principal, sem código nosso. Como **reforço** para a tabela `Payment` custom, continuar gravando o uuid local via metadata no Checkout (`->withMetadata(['subscription_uuid' => …])` na frente Cliente); os handlers custom de invoice leem esse metadata quando presente e caem no `stripe_id` como fallback.
- **Customer / Workspace**: o Cashier resolve o Billable (Workspace) por `workspaces.stripe_id` (`cus_…`, coluna criada pela migration do Cashier) — usado tanto pelo sync nativo quanto pelos nossos handlers (ex.: vincular o `Payment` ao workspace correto).
- Entidade não encontrada por nenhuma chave → logar com contexto e encerrar sem erro (evento órfão; não adianta o Stripe re-tentar) — exceto quando houver suspeita de corrida (ex.: invoice chegou antes do commit local), caso em que falhar o Job para aproveitar o retry do Horizon.

### 6. Eventos tratados

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

### 7. Gestão de inadimplência (dunning) — modelo simplificado

Modelo **"não pagou → período de retry → bloqueia"**, aproveitando o mecanismo **nativo** do Stripe (Smart Retries). **Três estados, sem job custom e sem coluna custom** — o estado de acesso é sempre derivado do `stripe_status` sincronizado pelo Cashier:

| Estado | `stripe_status` | Acesso |
|---|---|---|
| **Em dia** | `active` / `trialing` | Total. |
| **Em retentativa** | `past_due` | **Mantém acesso total** enquanto o Stripe re-tenta a cobrança (janela configurada no dashboard). |
| **Bloqueado** | `canceled` / `unpaid` (retentativas esgotadas → Stripe cancela) | Bloqueado, exceto rotas de billing para regularizar. |

O que implementar (mínimo):

#### 7.1 Config do Cashier — `keepPastDueSubscriptionsActive()`

Num service provider (`AppServiceProvider` ou provider de billing):

```php
// boot()
Cashier::keepPastDueSubscriptionsActive();
```

Isso faz o Cashier tratar `past_due` como ainda válido — `valid()`/`active()` retornam `true` — preservando o acesso durante a janela de retry. **Sem essa chamada** o default do Cashier (`deactivatePastDue = true`) bloquearia o tenant já na 1ª falha de pagamento, antes de o Stripe re-tentar.

#### 7.2 Config no dashboard do Stripe (setup, não código)

Passo de configuração documentado como pré-requisito de deploy, em *Settings → Billing → Subscriptions and emails*:

- **Cronograma de retentativas**: Smart Retries (recomendado) ou cronograma fixo — ex. re-tentar por ~1–2 semanas. É esse cronograma que define a duração do estado "em retentativa"; **não há job nosso**.
- **Ação final ao esgotar retentativas**: **"Cancelar assinatura"** — é isso que dispara `customer.subscription.deleted` e leva ao bloqueio.
- **Opcional**: habilitar os e-mails automáticos do Stripe de falha de pagamento/atualização de cartão — dispensa notificação custom nossa nesta fase.

#### 7.3 Enforcement em request-time — checagem única

**Middleware** (ex.: `EnsureSubscriptionAccess`) registrado no grupo autenticado, integrado ao sistema de permissões existente (`CheckPermission`), com **uma** checagem:

1. Carrega a assinatura corrente do **workspace** do usuário: `$sub = $workspace->subscription('default')` (via `Access` ativo; cachear por request).
2. `$sub && $sub->valid()` → segue. (`valid()` do Cashier = active | onTrial | onGracePeriod; com `keepPastDueSubscriptionsActive()`, `past_due` também conta como válido. Pode anexar header/flag quando `pastDue()` para o front exibir aviso de "pagamento pendente".)
3. Senão → **bloqueia tudo, exceto rotas de billing/regularização** (para o cliente pagar e reativar).

> Variante somente-leitura (opcional, 1 linha de mudança): em vez de bloquear tudo, bloquear só métodos de escrita (POST/PUT/PATCH/DELETE) e consumo de quota. Default assumido: **bloqueio total**.

Respostas de bloqueio seguem o padrão de exceptions do projeto: `BillingException` (ou `SubscriptionException`) com enum implementando `ErrorEnumInterface` — **402 Payment Required** para inadimplência, **403** para bloqueio total — mensagens em pt-BR, render padrão `{error, message}`. *(Enforcement de limites/quota de plano está deferido — ver nota do topo; o middleware desta fase checa apenas a validade da assinatura, não consumo.)*

> **Não confundir:** o `onGracePeriod()` do Cashier significa "assinatura cancelada com `ends_at` no futuro" (cancel-at-period-end) — o tenant mantém acesso até o fim do período pago, o que é o comportamento desejado. Não há mais conceito próprio de "grace" de inadimplência.

### 8. Requisitos não-funcionais

- **Resiliência**: o sync nativo do Cashier já é resiliente/idempotente; nossos handlers custom também devem ser (retry do Stripe ou do Horizon nunca duplica linha em `Payment`); falhas transientes no Job propagam exceção para aproveitar retry do Horizon; falhas permanentes (evento órfão, payload malformado) → log + descarte. O estado de acesso, por ser **derivado** do `stripe_status`, não tem estado próprio a corromper.
- **Observabilidade**: logar todo evento recebido pelo listener (`event.id`, tipo, resultado: processado/ignorado/duplicado/erro) com contexto estruturado — em particular `invoice.payment_failed`, cujo único papel é este registro; mudanças em Subscription/Payment auditadas via **owen-it/laravel-auditing** (já instalado) — as transições de `stripe_status` escritas pelo Cashier aparecem na trilha de auditoria da Subscription.
- **Performance**: responder ao Stripe em **poucos segundos** (meta < 5s). Com o sync nativo inline + custom em fila (seção 4), a resposta fica dominada pelo Cashier — nenhuma chamada externa síncrona no caminho do webhook.
- **Segurança**: `STRIPE_WEBHOOK_SECRET` **obrigatório** (ativa o `VerifyWebhookSignature` do Cashier); secrets exclusivamente via env/config; endpoint não expõe detalhes internos em erros. **Menos superfície nossa**: verificação de assinatura e parsing são do pacote, não código nosso a auditar.
- **Monitoramento de eventos não tratados**: contar/logar tipos de evento fora do mapa para detectar eventos novos relevantes que o Stripe passe a enviar (métrica ou log agregável, revisão periódica).
- **Testes**: cobertura Pest para o listener e handlers custom (incluindo 1º pagamento vs. recorrente), idempotência do custom (mesmo evento 2x) e middleware de enforcement (assinatura `active`/`trialing`/`past_due` passa; `canceled`/`unpaid`/ausente bloqueia com 402/403; rotas de billing sempre acessíveis) — usando os **fakes do Cashier / mock do `StripeClient`** (`Cashier::fake()` quando aplicável) em vez de um FakeGateway próprio. Cobrir também que `keepPastDueSubscriptionsActive()` está ativo (`valid()` true em `past_due`). A verificação de assinatura em si é do Cashier (testada pelo pacote); basta um teste de integração garantindo que a rota está montada e protegida.

### 9. Modelos e campos tocados pelo webhook

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

---

## [004] Gestão do Financeiro — Cliente

**Documento de Especificação de Requisitos**: API REST para assinatura, pagamento e gerenciamento de faturamento do lado cliente no Pandy Pro (Laravel).

**Nota de Arquitetura**: Este documento reflete a adoção de **Laravel Cashier (Stripe)** como plataforma de pagamento. A decisão está registrada no doc 005 (Plano de Implementação). O Cashier gerencia nativamente Customer, Subscription, Payment Methods e Invoices. A gestão de inadimplência é **simplificada**: estado derivado do Stripe (via `stripe_status`) + retentativas nativas do Stripe, sem escada custom de níveis.

**Nota — limites de plano deferidos:** por decisão atual, **não há limites de plano nesta fase** (serão alinhados depois). Portanto o endpoint de uso/quota e qualquer enforcement de limite (contas sociais, posts/mês, storage) estão **fora de escopo aqui**. O controle de acesso desta fase é **apenas por inadimplência** (assinatura válida vs. bloqueada), não por consumo.

---

### 1. Visão Geral

O cliente (usuário final assinante do SaaS) interage com o Pandy Pro para:

- **Criar uma assinatura** no processo de signup, escolhendo um plano, preço (moeda + frequência) e iniciando um período de trial.
- **Visualizar a assinatura atual** (status, plano ativo, período, datas do trial, estado de faturamento).
- **Gerenciar métodos de pagamento** (listar, adicionar, remover, definir como padrão).
- **Acessar histórico de pagamentos e faturas** com links para download hospedados no Stripe.

**Escopo**: Endpoints REST protegidos (auth:sanctum + verified) scoped ao Workspace (tenant), retornando respostas em pt-BR com permissão `billing` (CheckPermission middleware).

---

### 2. Assinatura via Signup

#### Fluxo de Assinatura Inicial

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

### 3. Ver Assinatura Atual e Uso

#### 3.1 Requisitos Funcionais (Subscriptions)

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

### 4. Gerenciar Métodos de Pagamento

#### 4.1 Requisitos Funcionais (Payment Methods)

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

### 5. Histórico de Pagamentos e Faturas

#### 5.1 Opções de Implementação

Este doc apresenta **duas opções** para histórico. Recomenda-se **Opção A** (mais simples); Opção B é útil se filtros/paginação custom forem críticos.

##### Opção A: Invoices ao Vivo (Recomendada)

Expor `$workspace->invoices()` do Cashier diretamente:
- Sempre atualizado com o Stripe
- Sem tabela local necessária
- Simplifica sincronização (zero webhook custom para invoices)
- Desvantagem: paginação/filtros limitados ao que o Stripe oferece

##### Opção B: Tabela Payment Local Leve

Manter tabela **Payment** local populada por listener de webhook:
- Colunas: id, uuid, workspace_id, subscription_id, status enum, amount int, currency enum, paid_at, period_start, period_end, gateway_invoice_id, hosted_invoice_url, receipt_url, created_at, updated_at
- Listener de `invoice.created/updated/payment_succeeded/payment_failed` popula/atualiza Payment (idempotência por gateway_invoice_id)
- Vantagem: paginação rápida, filtros locais, histórico persistente
- Desvantagem: sincronização extra, mais código no webhook

**Recomendação**: Usar **Opção A** inicialmente; migrar para B se uso/filtros custom forem necessários.

#### 5.2 Requisitos Funcionais (Payments — Ambas Opções)

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

### 6. Endpoints Propostos (Cliente)

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

### 7. Regras de Negócio e Restrições

#### 7.1 Assinatura e Períodos

**RN-C1**: **Uma assinatura ativa por workspace** — partial unique DB constraint em (workspace_id, stripe_status) para stripe_status in (active, trialing, past_due). Impede múltiplas assinaturas concorrentes e simplifica a lógica de billing.

**RN-C2**: **Período de Trial**: Definido por `Price.trial_period_days` (ex: 14, 0 = sem trial). Ao sucesso do 1º pagamento (webhook `invoice.payment_succeeded` com `billing_reason=subscription_create`):
- Se `now < trial_end_date`: Subscription `stripe_status` = "trialing", `billing_status` = "trialing"
- Se `now >= trial_end_date`: Subscription `stripe_status` = "active", `billing_status` = "active"

**RN-C3**: **Ciclo de Faturamento**: Sincronizado pelo Stripe via webhooks (Cashier gerencia). Cliente não manipula períodos — apenas consome dados lidos de `current_period_start` e `current_period_end` (colunas do Cashier).

#### 7.2 Métodos de Pagamento

**RN-C4**: **Um método padrão por workspace** — usado para cobranças recorrentes. Troca de padrão (via PATCH /billing/cards/{id}) aplica no **próximo ciclo**, não retroativamente.

**RN-C5**: **Segurança de Dados de Cartão**: O backend NUNCA trafega ou armazena números completos de cartões. Dados sensíveis (number, CVC) ficam apenas no navegador (Stripe.js) ou tokenizados (`pm_…` no Stripe). Backend expõe apenas brand, last_digits, exp_month/year.

#### 7.3 Inadimplência e Controle de Acesso (Modelo Simplificado)

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

#### 7.4 Comportamento do Cliente Durante Trial

**RN-C9**: Durante trial (`stripe_status = trialing`), Cliente vê:
- Assinatura com status "Em período de teste"
- Data de término do trial (`trial_ends_at`)
- Aviso no dashboard: "Você está em período de teste gratuito até [data]. Após isso, começaremos a cobrar."
- Sem bloqueios de funcionalidade; acesso total (`billing_status` = "trialing").

**RN-C10**: Se trial expira e nenhum pagamento é completado, Subscription transiciona para `stripe_status` = "past_due" → retentativas Stripe → cancelamento (policy a definir com PM; ex: após ~1-2 semanas de retentativas sem sucesso).

#### 7.5 Mudança de Plano (Escopo Futuro)

**RN-C11**: Upgrade/downgrade de plano é descrito apenas em alto nível:
- Cliente pode solicitar mudança para outro Plano.
- Backend valida (não pode fazer downgrade se sobre quota).
- Stripe recebe ordem de troca (change subscription items via `$subscription->swapAndInvoice()`).
- Cobrança é pro-rata ou sobre/sub-crédito (policy de PM).
- Implementação detalhada em documento futuro (não cobre RFC-C04).

---

### 8. Requisitos Não-Funcionais (Cliente)

#### 8.1 Formato de Resposta

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

#### 8.2 Tratamento de Erro

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

#### 8.3 Autenticação e Autorização

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

#### 8.4 Paginação

**RN-F8**: Endpoints GET com múltiplos registros (e.g., `GET /billing/payments`) suportam paginação:
- Query params: `page` (default 1), `per_page` (default 15, max 100)
- Resposta inclui `meta: { total, per_page, current_page, last_page }`

#### 8.5 Validação de Input (FormRequest)

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

#### 8.6 Rastreamento e Auditoria

**RN-F11**: Modelos usam owen-it/laravel-auditing (Observer) para registrar alterações em tabela audits. Campos como `is_default`, `gateway_payment_method_id` são auditados.

**RN-F12**: Webhooks do Stripe (via Cashier WebhookController) são logados (Laravel logs) com event_id, tipo, status, e correlação local (ex: workspace_id do metadata).

#### 8.7 Performance e Caching

**RN-F13**: Dados de assinatura (`GET /billing/subscription`) podem ser **cacheados por 5 minutos** na tag `billing.subscription.{workspace_id}`, invalidados ao webhook de mudança (invoice.payment_succeeded, invoice.payment_failed, customer.subscription.updated).

**RN-F14**: Query `with()` (eager-load relacionamentos) para evitar N+1:
- GET /billing/subscription: inclui Price, Plan
- GET /billing/payments: (Opção B) inclui Subscription
- GET /billing/cards: sem relacionamentos adicionais

---

### 9. Entidades de Banco de Dados (Resumo para Cliente)

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

### 10. Considerações Futuras

- **Mudança de Plano (upgrade/downgrade)**: Detalhada em RFC futuro; envolve pro-rata de cobrança, revalidação de quota, alteração via Cashier `swapAndInvoice()`.
- **Cancelamento de Assinatura**: Endpoint DELETE /billing/subscription (cliente auto-cancela); marca como canceled, notifica Stripe, gera crédito se aplicável.
- **Histórico de Assinatura**: Queries para listar assinaturas passadas (para compliance/audit).
- **Reemissão de Faturas**: Endpoint para solicitar reemissão em formato diferente (atualmente gerada pelo Stripe via webhook).
- **Conformidade PSD2/SCA**: Strong Customer Authentication para boletos e cartões; implementação depende de evolução Stripe + frontend.
- **Sincronização Manual**: Endpoint admin privado (não cliente-facing) `POST /admin/webhooks/stripe/sync/{workspace_id}` para forçar sincronização em caso de falha de webhook (útil em testes/debug).

---

### Anexo: Matriz de Permissões

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
