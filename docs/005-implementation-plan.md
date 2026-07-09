# 005 — Plano de Implementação Consolidado: Financeiro (Assinaturas e Planos)

> **Nota de revisão (2026-07-07):** este documento reflete **três decisões de arquitetura**: (1) adotar o **Laravel Cashier (Stripe) v15** em vez de `stripe/stripe-php` puro com abstração de gateway própria — multi-gateway **não é prioridade** (decisão consciente) e as tabelas/código legados de assinatura são **resíduo a refazer** (rebuild limpo, sem migração de dados); (2) **dunning simplificado** — sem escada custom de `access_level` e sem job agendado: as retentativas de cobrança são do **Stripe (Smart Retries, config no dashboard)**, o acesso é **derivado do `stripe_status`** sincronizado pelo Cashier e o enforcement vira uma checagem única `valid()` em middleware; (3) **limites de plano DEFERIDOS** — por decisão do cliente ("por enquanto deixe sem limites de plano, irei alinhar isso ainda"), **nesta fase não há limites/quota de plano**: `PlanLimit`, endpoints de limites, endpoint de uso e enforcement de quota ficam fora de escopo, marcados como "DEFERIDO — a alinhar". Os docs [003](003-webhook-requirements.md) e [004](004-client-billing-requirements.md) já estão revisados sob as mesmas decisões.

**Documento-mestre** que consolida os docs [001](001-context-billing.md) (contexto/termos), [002](002-admin-billing.md) (frente Admin), [003](003-webhook-requirements.md) (frente Webhook/Inadimplência) e [004](004-client-billing-requirements.md) (frente Cliente) em um plano executável, dividido por fases.

---

## 1. Visão geral e objetivo

A feature **Financeiro** entrega o ciclo completo de cobrança recorrente do Pandy Pro como SaaS: o administrador cadastra **Planos** com **Preços** (moeda × frequência); o cliente assina no signup via **Stripe Checkout** com período de trial; o sistema cobra recorrentemente, sincroniza estado via **webhooks** e controla acesso em caso de **inadimplência**; o cliente gerencia métodos de pagamento e consulta faturas.

> **Escopo atual — sem limites de plano:** esta fase **exclui limites/quota de plano** (contas sociais, posts/mês, storage etc.), que serão **alinhados depois** com o PM/cliente. O controle de acesso é **apenas por inadimplência** (assinatura válida vs. bloqueada), nunca por consumo. Onde limites aparecem neste doc, estão marcados como **DEFERIDO — a alinhar**.

O trabalho se divide em **3 frentes** (docs 002/003/004), que também são as **fases de implementação**:

1. **Admin** (doc 002) — CRUD de **Plan/Price/PriceHistory** + criação de Products/Prices no Stripe via `Cashier::stripe()`. É a **base**: sem produtos e preços no gateway, nada assina. Inclui o setup do Cashier e o rebuild do schema legado. *(`PlanLimit` e os endpoints de limites do doc 002: DEFERIDOS.)*
2. **Webhook + Inadimplência** (doc 003) — o `WebhookController` do Cashier sincroniza o estado nativo (Subscription, customer); nosso **listener de `WebhookReceived`** aplica os efeitos custom (Payment local opcional; no 1º pagamento, setar `start_date`). A inadimplência é **mínima por design**: retentativas do Stripe (dashboard) + `Cashier::keepPastDueSubscriptionsActive()` + middleware com checagem única `valid()`.
3. **Cliente** (doc 004) — signup→checkout→trial via `newSubscription()->checkout()`, assinatura atual, métodos de pagamento via `paymentMethods()`, histórico de faturas via `invoices()`. *(Endpoint de uso/quota: DEFERIDO.)*

**Arquitetura-alvo em um parágrafo:** camadas Controller(BaseController) → FormRequest → Service(BaseService) → Model, com o ciclo de vida de billing (customer, assinatura, checkout, métodos de pagamento, faturas, webhooks, SCA) delegado ao **Laravel Cashier** — trait `Billable` no **Workspace** (billing é do tenant). O que o Cashier **não** cobre é nosso, por cima: **catálogo Admin** (Plan/Price/PriceHistory, criando Products/Prices no Stripe via `Cashier::stripe()`), tabela **Payment local opcional** e o **enforcement por middleware** — reduzido a **uma checagem** `$workspace->subscription('default')?->valid()`. Não há job de dunning nem colunas custom de acesso: o estado de inadimplência **deriva do `stripe_status`** (modelo de 3 estados, §2.7) e o cronograma de retentativas é configuração do **dashboard do Stripe**. O estado real de cobrança (períodos, trial, status) é **escrito pelos webhooks** (sync nativo do Cashier), nunca pelas chamadas de criação.

---

## 2. Decisões de arquitetura (transversais)

Valem para todas as fases:

### 2.1 Plataforma de pagamento: Laravel Cashier v15, Stripe-only
**Decisão: Laravel Cashier (Stripe) v15** para Laravel 12. **Não** haverá abstração `PaymentGateway`/`StripeGateway` própria — **multi-gateway não é prioridade** e essa é uma **decisão consciente**: aceitamos o acoplamento ao Stripe em troca de menos código em partes críticas (webhook, checkout, payment methods, faturas, SCA). Quando precisarmos do SDK cru (o Cashier não cobre tudo — ex.: catálogo Admin de Products/Prices), usamos o client configurado do próprio pacote:

```php
use Laravel\Cashier\Cashier;

Cashier::stripe()->products->create([...]);
Cashier::stripe()->prices->create([...]);
```

Nenhum service instancia `StripeClient` diretamente nem importa `Stripe\*` fora desse ponto de acesso. Em testes, fakes/mocks do Cashier substituem o antigo conceito de `FakeGateway` (ver §6).

### 2.2 Escopo de billing = Workspace, não User — via `Billable`
Billing é do tenant: quem paga é o workspace, não o indivíduo — múltiplos usuários compartilham a mesma assinatura. **Decisão: aplicar o trait `Laravel\Cashier\Billable` no model `Workspace`.** A migration do Cashier (apontada para `workspaces` via `CASHIER_MODEL`) adiciona as colunas de customer: `stripe_id` (`cus_…`), `pm_type`, `pm_last_four`, `trial_ends_at`.

**Importante — isto NÃO é um refactor com migração de dados; é substituição de legado.** As tabelas e código atuais são **resíduo a DROPAR e refazer**:

- `subscriptions` atual (ligada a `user_id`, com `posts_used`/`posts_limit` no lugar errado) → **dropada**; substituída pela tabela `subscriptions` do Cashier (+ `subscription_items`), pertencente ao Workspace.
- `plans` atual (com `stripe_plan_id` placeholder nullable, `price` decimal) → **dropada/recriada** conforme o schema do doc 002 (Plan/Price/PriceHistory; `PlanLimit` deferida).
- `SubscriptionService` atual (que só flipa status local, sem gateway) e o model `Subscription` atual → **removidos/substituídos**, não preservados.

**Sem migração de dados de produção** — rebuild limpo via migrations novas. (Confirmação com o PM de que nada em produção depende dessas tabelas: §9, Q1.)

### 2.3 Pacote Stripe: Cashier (padrão Laravel)
Nada está instalado hoje. **Decisão (trade-off já resolvido a favor do Cashier):**

- **Ganhos:** webhook (endpoint + verificação de assinatura + sync de assinaturas), checkout hospedado, gestão de payment methods, faturas (`invoices()`/`downloadInvoice()`) e SCA/PSD2 **prontos e mantidos pelo time do Laravel** — muito menos código nosso em superfície crítica de bug.
- **Custo aceito:** Stripe-only, sem abstração multi-gateway (fora de escopo, decisão consciente); adotamos as convenções/nomes do Cashier onde ele manda (`stripe_id`, `stripe_status`, `stripe_price`, `pm_type`, `pm_last_four`, `trial_ends_at`, `ends_at`).
- **Um pacote só:** o Cashier **depende de `stripe/stripe-php`** — o SDK vem junto, não instalar os dois separadamente.

A parte que o Cashier não resolve (catálogo Admin, middleware de enforcement, Payment local opcional) continua nossa e convive com os models do Cashier (subclasse mínima de Subscription, ver §3). Com o dunning simplificado (§2.7), essa parte custom **encolheu ainda mais** — não há mais escada de acesso nem job agendado.

### 2.4 Estado sincronizado por webhook
O **Stripe é a fonte de verdade do estado de cobrança**; o banco local é uma projeção alimentada pelos webhooks (doc 003 §1). Com o Cashier, a sincronização nativa (status, períodos, trial, cancelamento da Subscription; dados de customer no Workspace) é **do pacote, sem código nosso**. Chamadas de criação (checkout, attach de cartão) não escrevem estado final — escrevem intenção e aguardam o evento. Nossos efeitos custom (Payment local, `start_date` no 1º pagamento) entram pelo listener de `WebhookReceived` (doc 003 §2). O estado de acesso **não é escrito por ninguém** — é sempre **derivado** do `stripe_status` (§2.7).

### 2.5 Padrões do codebase (obrigatórios)
- Controllers finos estendendo `BaseController` com permissões declarativas (`$permissionGroup = 'billing'` + `$permissionMethods`) → middleware `permission:billing.{ability}`.
- `FormRequest` por endpoint; Services estendendo `BaseService`; saída via `JsonResource`.
- **Sem Events/Listeners no domínio** — efeitos colaterais via Observers (`#[ObservedBy]`) e Jobs (`ShouldQueue`, Horizon/Redis). **Exceção documentada:** o Cashier introduz um evento (`Laravel\Cashier\Events\WebhookReceived`) como ponto de extensão de webhook — aceitável e localizado (um único listener registrado, doc 003 §2).
- Erros: par **error-enum + Exception** — criar `BillingError` (implements `ErrorEnumInterface`) + `BillingException` com named constructors; **402 Payment Required** para inadimplência, 403 para bloqueio total, 409 para conflitos de deleção.
- Enums PHP backed em `app/Enums/*` com `label()`, espelhados como enums nativos no DB (para as colunas custom nossas; colunas do Cashier ficam como o Cashier as define).
- PK bigint (`$table->id()`) + coluna `uuid` unique como route key nos models custom; na subclasse de Subscription, `uuid` é coluna adicional nossa.
- Mensagens user-facing em **pt-BR**; render central `{error, message}`.
- Testes **Pest v4** com helpers globais (`createUserWithPermissions`, `signupUser`).
- Auditoria via **owen-it/laravel-auditing** nos models de billing.

### 2.6 Roteamento — observação de padronização
O projeto usa `routes/api.php` único, **sem versionamento** (não há `/api/v1`). Rotas do cliente ficam em `/billing/...` dentro do bloco protegido (`auth:sanctum` + `verified` + throttle); rotas admin em `/admin/plans/...` no mesmo arquivo, com permissões de admin. O **webhook é a rota do próprio Cashier** (default **`POST /stripe/webhook`**), **pública**, fora do bloco autenticado, com verificação de assinatura embutida (`VerifyWebhookSignature`) — não escrevemos esse controller (doc 003 §2).

### 2.7 Inadimplência: modelo simplificado de 3 estados (Stripe Smart Retries)
**Decisão registrada — substitui a escada de 6 níveis `access_level` e o job agendado de dunning das versões anteriores.** Modelo **"não pagou → período de retry → bloqueia"**, aproveitando o mecanismo **nativo** do Stripe. Três estados, **sem job custom e sem coluna custom** — tudo deriva do `stripe_status` sincronizado pelo Cashier (detalhes: doc 003 §7):

| Estado | `stripe_status` | Acesso |
|---|---|---|
| **Em dia** | `active` / `trialing` | Total. |
| **Em retentativa** | `past_due` | **Mantém acesso** enquanto o Stripe re-tenta a cobrança (Smart Retries, janela configurada no dashboard). |
| **Bloqueado** | `canceled` / `unpaid` (retentativas esgotadas → Stripe cancela) | Bloqueado, exceto rotas de billing para regularizar. |

O que isso implica no código (mínimo):
- **`Cashier::keepPastDueSubscriptionsActive()`** num service provider — faz `valid()`/`active()` retornarem `true` em `past_due`, preservando acesso durante a janela de retry (sem isso, o default do Cashier bloquearia já na 1ª falha).
- **Config no dashboard do Stripe** (passo de setup, **não código**): cronograma de retentativas (Smart Retries, ~1–2 semanas) + **ação final = "Cancelar assinatura"**. Opcional: e-mails automáticos do Stripe de falha de pagamento (dispensa notificação custom nossa nesta fase).
- **Middleware de enforcement com UMA checagem:** `$workspace->subscription('default')?->valid()` → segue; senão bloqueia tudo exceto rotas de billing (402/403 via `BillingException`). Variante somente-leitura disponível como mudança de 1 linha; default assumido: **bloqueio total**.
- **Nada a escrever nos webhooks:** o bloqueio acontece "sozinho" quando o Stripe cancela (`customer.subscription.deleted`/`updated` → `stripe_status` `canceled`/`unpaid` → `valid()` false). `invoice.payment_failed` vira só log/observabilidade.

---

## 3. Modelo de dados consolidado

Visão única das entidades (detalhes de campos: docs 002 §"O que deve ser feito", 003 §9, 004 §9 — não duplicados aqui). Divisão de propriedade:

- **Do Cashier:** tabela `subscriptions` + `subscription_items` (criadas pelas migrations do pacote) e colunas de customer no `workspaces` (via `Billable`). Ciclo de assinatura, webhook, faturas e métodos de pagamento são do Cashier.
- **Nosso (custom):** catálogo Admin (Plan/Price/PriceHistory — o Cashier **não** gerencia Products/Prices; usamos `Cashier::stripe()`), o middleware de enforcement e a tabela `Payment` local **opcional**. A subclasse de Subscription é **mínima** (só `uuid` + helpers derivados) — **sem colunas custom de dunning**.
- **DEFERIDO:** `PlanLimit` (limites de plano a alinhar — não entra nas migrations desta fase).

```
Workspace (tenant, Billable) ── stripe_id (cus_…), pm_type, pm_last_four, trial_ends_at
   │ 1
   │ *                                        Plan ── gateway_product_id (prod_…)   [nosso]
Subscription (Cashier + subclasse mínima)       │ 1..*
   │  stripe_id (sub_…), stripe_status,         ▼
   │  stripe_price ──────────────────────▶ Price ── gateway_price_id (price_…)  [nosso]
   │  trial_ends_at, ends_at                    │ 1..*
   │  + uuid [nosso; sem colunas de dunning]    ▼
   │ 1 ── * subscription_items (Cashier)     PriceHistory  [nosso]
   │ *
Payment [nosso, OPCIONAL] ── gateway_invoice_id / _payment_intent_id / _checkout_session_id

(PlanLimit: DEFERIDA — limites de plano a alinhar, fora desta fase)
(Card local: REMOVIDA — métodos de pagamento ao vivo via $workspace->paymentMethods())
```

| Entidade | Dono | Fase que a introduz | Principais campos gateway | Tenancy |
|---|---|---|---|---|
| `workspaces` (colunas Billable) | Cashier | 1 — Admin (migration do Cashier) | `stripe_id` (`cus_…`), `pm_type`, `pm_last_four`, `trial_ends_at` | é o tenant |
| `Plan` (rebuild do legado) | Nosso | 1 — Admin | `gateway_product_id` | Global (untenanted) |
| ~~`PlanLimit`~~ | Nosso | **DEFERIDA** — limites de plano a alinhar (§9) | — | Global |
| `Price` | Nosso | 1 — Admin | `gateway_price_id` | Global |
| `PriceHistory` | Nosso | 1 — Admin | `gateway_price_id` (arquivado) | Global |
| `subscriptions` + `subscription_items` | Cashier (schema) + subclasse mínima nossa | 1 — Admin (migrations/subclasse) | `stripe_id` (`sub_…`), `stripe_status`, `stripe_price` + **nosso** `uuid` | Workspace (Billable) |
| `Payment` (**opcional**) | Nosso | 2/3 — só se filtros/paginação custom (doc 004 §5, Opção B) | `gateway_invoice_id`, `gateway_payment_intent_id`, `gateway_checkout_session_id`, `hosted_invoice_url`, `receipt_url` | `BelongsToWorkspace` |
| ~~`Card`~~ | — | **Removida** — usar `paymentMethods()` do Cashier ao vivo (doc 003 §9, doc 004 §4) | — | — |

**Subclasse de Subscription:** `App\Models\Subscription extends Laravel\Cashier\Subscription`, registrada via `CASHIER_SUBSCRIPTION_MODEL` (ou `Cashier::useSubscriptionModel()`), com migration nossa adicionando **apenas `uuid`** (route key, padrão do projeto). **Sem `access_level`/`past_due_since`** — o estado de acesso deriva do `stripe_status`; a subclasse pode, **no máximo**, expor um accessor derivado `billing_status` para o front (`active`/`trialing`/`past_due`/`blocked`, mapeando `stripe_status` + `valid()` — doc 003 §9, doc 004 RF-C3), **sem persistir nada**.

**Decisões estruturais no nível DB:**
- **Rebuild do legado:** migrations que **dropam** `subscriptions`/`plans` atuais e aplicam o schema novo (Cashier + catálogo) — **sem backfill, sem migração de dados** (§2.2).
- **Unicidade de assinatura:** partial-unique — **1 Subscription por workspace em `stripe_status` `active`/`trialing`/`past_due`** (docs 003 §9, 004 RN-C1). O Cashier usa `type` para assinaturas nomeadas; usamos só `default`.
- **Idempotência de efeitos custom:** partial-unique (WHERE NOT NULL) em `payments.gateway_invoice_id`, `payments.gateway_payment_intent_id`, `payments.gateway_checkout_session_id` (só se a tabela `Payment` for adotada — doc 003 §3).
- **Nomenclatura:** colunas do Cashier mantêm os nomes do Cashier (`stripe_*`, `pm_*`); entidades/colunas custom nossas seguem o padrão do projeto — os `gateway_*_id` do catálogo (doc 002) e do Payment permanecem como nomes locais que armazenam ids do Stripe.
- **Amounts em centavos (int)**; `currency` enum (`brl/usd/eur/gbp`); `frequency` enum (`monthly/yearly`).
- Tabelas custom: PK bigint + `uuid` route key; enums nativos; timestamps.

---

## 4. Plano de implementação por fases

**Ordem: Admin → Webhook → Cliente.** O Admin instala o Cashier, faz o rebuild do schema e cria os Products/Prices no Stripe — sem isso não existe o que assinar. O Webhook é pré-requisito real do Cliente: o checkout só "funciona de verdade" quando o sync do Cashier + nosso listener transicionam a assinatura para `active` — implementar o Cliente antes do Webhook produziria assinaturas eternamente `incomplete`. O Cliente consome tudo. **Paralelização sugerida:** o listener/Job da Fase 2 pode começar em paralelo com o fim da Fase 1, pois depende apenas do setup do Cashier e do schema de Plan/Price, não dos endpoints admin.

---

### Fase 1 — Financeiro Admin (doc 002)

**Objetivo:** administradores gerenciam Planos e Preços com sincronização Stripe (Product/Price via `Cashier::stripe()`), respeitando imutabilidade de preço e regras de deleção. Entrega também a fundação transversal: instalação do Cashier, rebuild do schema legado, `Billable` no Workspace e subclasse de Subscription. **O catálogo Admin desta fase = Plan + Price + PriceHistory apenas** — `PlanLimit` e os endpoints de limites (`POST/PATCH/DELETE /admin/plans/{plan}/limits`, doc 002) estão **DEFERIDOS** (limites de plano a alinhar).

**Entregáveis:**
- Setup Cashier (**pré-requisito de tudo**): `composer require laravel/cashier` (traz `stripe/stripe-php` junto); publicar/ajustar migrations do Cashier (colunas de customer em `workspaces` via `CASHIER_MODEL`, tabelas `subscriptions`/`subscription_items`); `config/cashier.php` + chaves `.env` (ver §7).
- **Rebuild do legado:** migrations que dropam `subscriptions`/`plans` atuais; remoção do model `Subscription` e do `SubscriptionService` legados (e do fluxo `POST subscribe` local-only, ou marcação como depreciado até a Fase 3); **sem migração de dados** (§2.2).
- Trait `Billable` no model `Workspace`; subclasse mínima `App\Models\Subscription extends Laravel\Cashier\Subscription` registrada via `CASHIER_SUBSCRIPTION_MODEL` (migration nossa só com `uuid`; accessor derivado `billing_status` — sem colunas de dunning, §3).
- Migrations do catálogo: create `plans` (novo, doc 002: `name`, `description`, `is_visible`, `is_active`, `gateway_product_id`, soft delete/`archived_at`), `prices`, `price_histories` (constraints/enums do §3). *(`plan_limits`: **não criar** — DEFERIDA.)*
- Models: `Plan`, `Price`, `PriceHistory` + Factories.
- Enums: `Currency`, `Frequency` (backed, com `label()`).
- Services: `PlanService`, `PriceService` (extends `BaseService`; orquestram **gateway-first via `Cashier::stripe()->products` / `->prices`** + `DB::transaction`).
- Controllers + FormRequests + JsonResources para Plan e Price (grupo de permissão `billing`, abilities admin).
- Rotas em `routes/api.php`: `/admin/plans`, `/admin/plans/{plan}/prices` (+ `/versions`), conforme doc 002 §Endpoints. *(As rotas `/admin/plans/{plan}/limits` do doc 002 **não entram** nesta fase — DEFERIDAS.)*
- Erros: `BillingError` + `BillingException` (casos: `planHasActiveSubscriptions`, `priceInUse`, `lastActivePrice`, `gatewayFailure`).
- Seeders de permissões do grupo `billing`.

**Sequência de passos:**
1. Instalar Cashier, configurar `config/cashier.php` e chaves `.env`/`.env.example`; `Billable` no Workspace; `CASHIER_MODEL`/`CASHIER_SUBSCRIPTION_MODEL`.
2. Migrations de rebuild: dropar legado (`subscriptions`, `plans`) + aplicar migrations do Cashier + migration da subclasse (`uuid`).
3. Migrations do catálogo (Plan/Price/PriceHistory) + models + enums + factories.
4. `PlanService`: create (`Cashier::stripe()->products->create` → persistir), update (sync name/description via `products->update`), deactivate (arquivar prices+product; bloquear com assinaturas ativas), delete (regras hard/soft do doc 002).
5. `PriceService`: create (`Cashier::stripe()->prices->create` → persistir), **update = arquivar antigo (`active:false`) + criar novo + mover antigo para `PriceHistory` com `reason`**, delete (regras hard/soft/409 do doc 002).
6. Controllers/Requests/Resources/rotas + permissões.
7. Testes Pest (unit dos services com `StripeClient` mockado + feature dos endpoints).

**Requisitos Funcionais** (derivados do doc 002; detalhes lá — numeração mantida):
- **RF-A1** — CRUD de Plano (`POST/GET/GET:id/PATCH/DELETE /admin/plans`), com filtro ativo/inativo.
- **RF-A2** — Criar Plano cria Product no Stripe (`Cashier::stripe()->products->create`) e persiste `gateway_product_id` (gateway-first).
- **RF-A3** — Editar `name`/`description` do Plano sincroniza via `products->update`.
- **RF-A4** — *(DEFERIDO — a alinhar)* CRUD de PlanLimit (`/admin/plans/{plan}/limits`). O doc 002 especifica esses endpoints, mas **ficam para depois**: os limites concretos ainda serão alinhados com o PM. Nenhuma migration, model, rota ou validação de limite entra nesta fase.
- **RF-A5** — Criar Preço cria Price no Stripe (`Cashier::stripe()->prices->create`) e persiste `gateway_price_id`.
- **RF-A6** — **Imutabilidade de preço:** "editar" Preço = arquivar Price antigo no Stripe (`active:false`) + criar novo + mover o antigo para `PriceHistory` (com `archived_at` e `reason`).
- **RF-A7** — Listar preços e histórico de versões (`GET .../prices`, `GET .../prices/{price}/versions`).
- **RF-A8** — Desativar Plano: `is_active=false` local + arquivar Prices e Product no Stripe; **bloqueado** se houver assinaturas ativas; sem migração automática de assinaturas existentes.
- **RF-A9** — Deleção de Plano: hard delete só se nunca teve assinaturas (arquivando no Stripe antes); soft delete se teve/tem histórico; **409** com lista de assinaturas se houver ativas (matriz do doc 002).
- **RF-A10** — Deleção de Preço: hard delete só se nunca usado; soft delete se usado; **409** se for o único ativo do plano para (currency, frequency) em uso no signup.

**Requisitos Não-Funcionais:**
- Consistência: gateway-first + `DB::transaction` na persistência local; falha no Stripe → nada persiste local + `BillingException::gatewayFailure` (502/422 com mensagem pt-BR).
- Segurança: rotas admin sob `auth:sanctum` + `verified` + `permission:billing.*`; secret Stripe só via config/env.
- Auditoria: `Plan`, `Price`, `PriceHistory` auditáveis (laravel-auditing).
- Testes: services com `StripeClient` mockado; feature tests dos endpoints com `createUserWithPermissions`.
- Performance: chamadas Stripe síncronas são aceitáveis aqui (ação de admin, baixa frequência), mas com timeout configurado.

**Dependências:** nenhuma externa (é a primeira fase). Conta Stripe de teste criada. **Confirmação do PM de que nada em produção depende das tabelas legadas antes do drop** (§9, Q1).

**Critérios de aceite:**
- [ ] `composer require laravel/cashier` + migrations aplicadas: `workspaces` com colunas de customer, `subscriptions`/`subscription_items` novas, tabelas legadas removidas, suite existente adaptada verde.
- [ ] Criar plano via API resulta em Product visível no dashboard Stripe (test mode) e `gateway_product_id` persistido.
- [ ] Editar amount de um Preço gera novo `price_…` no Stripe, arquiva o antigo e cria linha em `price_histories`.
- [ ] Desativar plano com assinatura ativa retorna 409 (validável na Fase 2+; na Fase 1, teste com fixture).
- [ ] Suite Pest verde sem tocar o Stripe real (fakes do Cashier / mock do `StripeClient`).
- [ ] Permissões: usuário sem `billing.*` recebe 403.

**Riscos e mitigação:**
- *Falha parcial gateway-first* (Product criado, persistência local falha) → registros órfãos no Stripe. Mitigar: try/catch com arquivamento compensatório + log; aceitável em test mode.
- *Drop do legado remover algo em uso* (`GET /plans` e `POST subscribe` atuais referenciam os models antigos). Mitigar: confirmar com o PM que são resíduo sem consumidores reais (§9 Q1); remover/ajustar as rotas junto com o drop e rodar a suite existente.
- *Limites de plano deferidos sem data* — a intenção futura (`PlanLimit`) existe mas depende de alinhamento (§9 Q7). Mitigar: schema do catálogo não presume limites (nada a desfazer); quando alinhado, `PlanLimit` entra como migration aditiva (par `limit_type` enum + `value`), sem quebrar o que foi entregue.

---

### Fase 2 — Webhook e Gestão de Inadimplência (doc 003)

**Objetivo:** webhook operacional via **`WebhookController` do Cashier** (rota `/stripe/webhook`, verificação de assinatura e sync nativo de assinaturas **sem código nosso**) + nossa camada custom mínima: listener de `WebhookReceived`, Job de efeitos custom (Payment local opcional; `start_date` no 1º pagamento) e a inadimplência **simplificada** do §2.7 — `Cashier::keepPastDueSubscriptionsActive()`, config de retentativas no dashboard do Stripe e middleware de checagem única. O enforcement, que ficou incompleto no Django de referência, aqui é **obrigatório** — mas o escopo é pequeno de propósito.

**Entregáveis** (ainda menores que na revisão anterior — além de verificação/parsing/sync saírem para o Cashier, **não há mais job de dunning, enum de acesso nem colunas custom**):
- Registro da rota/eventos: webhook `/stripe/webhook` no dashboard Stripe (ou `php artisan cashier:webhook`), `STRIPE_WEBHOOK_SECRET` configurado (doc 003 §2).
- **Config do Cashier:** `Cashier::keepPastDueSubscriptionsActive()` no `boot()` de um service provider (`AppServiceProvider` ou provider de billing) — `past_due` continua `valid()` durante a janela de retry (doc 003 §7.1).
- **Config no dashboard do Stripe** (passo de setup documentado, **não código**): Smart Retries (~1–2 semanas) + ação final **"Cancelar assinatura"**; opcionalmente e-mails automáticos do Stripe de falha de pagamento (doc 003 §7.2).
- **Listener `StripeWebhookListener`** para `Laravel\Cashier\Events\WebhookReceived` (registrado no provider): dedup de cache por `event.id` (TTL 24h), filtro dos eventos que nos interessam, dispatch do Job (doc 003 §§2–4).
- **Job `ProcessStripeWebhookJob`** (`ShouldQueue`, fila `webhooks` no Horizon): handlers custom transacionais — `invoice.created/payment_succeeded/voided`, `checkout.session.expired` (Payment local, se Opção B); `invoice.payment_succeeded` com `billing_reason=subscription_create` (1º pagamento) → **setar `start_date` + registrar o Payment, nada mais** (*sync de limites do plano: DEFERIDO*); `invoice.payment_failed` → **só log/observabilidade** (+ opcional Payment `failed`) — conforme a tabela do doc 003 §6. Os `payment_method.*` **não** são tratados (Card local removida). **Nenhum handler escreve estado de acesso.**
- Tabela/model `Payment` **somente se** a Opção B do doc 004 §5 for adotada (com partial-uniques de idempotência); caso contrário este entregável cai.
- Enums: `PaymentStatus`/`PaymentMethod` só com a Opção B. (Status de assinatura é o `stripe_status` do Cashier — sem enum próprio; **sem enum `AccessLevel`**.)
- **Middleware `EnsureSubscriptionAccess`** registrado no grupo autenticado, com **uma checagem**: `$workspace->subscription('default')?->valid()` → segue (pode anexar flag/header quando `pastDue()` para o front avisar); senão bloqueia tudo **exceto rotas de billing/regularização** (doc 003 §7.3). *Controla acesso **apenas por inadimplência** — sem checagem de quota/consumo (limites DEFERIDOS).*
- Extensão de `BillingError`/`BillingException`: `paymentRequired` (402), `accessBlocked` (403).

**Sequência de passos:**
1. Registrar webhook no Stripe (dashboard ou `cashier:webhook`) e validar o fluxo nativo com `stripe listen` — assinatura criada em teste aparece sincronizada na tabela `subscriptions` **sem código nosso**.
2. `Cashier::keepPastDueSubscriptionsActive()` no provider + configurar Smart Retries e ação final "Cancelar assinatura" no dashboard do Stripe (documentar como pré-requisito de deploy).
3. Listener `WebhookReceived` (dedup por `event.id` + filtro de eventos) + registro no provider.
4. Job + handlers custom, na ordem: `invoice.payment_succeeded` (1º pagamento: `start_date` + Payment) → `invoice.payment_failed` (log) → `invoice.created/voided` + `checkout.session.expired` (só com Payment local).
5. Correlação: principal via `stripe_id` (o Cashier resolve sozinho); metadata `subscription_uuid` como reforço para o Payment custom (doc 003 §5).
6. Middleware de enforcement (checagem única `valid()`) + respostas 402/403 pt-BR.
7. Testes (listener com eventos fixture, idempotência do custom 2×, middleware nos 3 estados).

**Requisitos Funcionais** (consolidando doc 003; prefixo RF-W):
- **RF-W1** — Webhook via rota do Cashier (`POST /stripe/webhook`), pública, com `VerifyWebhookSignature` ativo (`STRIPE_WEBHOOK_SECRET` obrigatório); contrato HTTP do doc 003 §2. *Nenhum controller nosso.*
- **RF-W2** — Sync nativo do Cashier operante: `customer.subscription.created/updated/deleted`, `customer.updated/deleted`, `invoice.payment_action_required` mantêm `subscriptions` e os dados de customer do Workspace atualizados (doc 003 §6, coluna "Cashier nativo"). **É esse sync que muda o acesso** (`active → past_due → canceled/unpaid`), sem código nosso.
- **RF-W3** — Listener de `WebhookReceived` com dedup leve por `event.id` (cache TTL 24h) + dispatch de `ProcessStripeWebhookJob` (fila `webhooks`), handlers transacionais (doc 003 §§3–4). Idempotência definitiva dos efeitos custom por constraints de DB (se Payment local).
- **RF-W4** — Efeitos custom da tabela do doc 003 §6: `invoice.payment_succeeded` → Payment `paid` + (1º pagamento, `billing_reason=subscription_create`) setar `start_date` *(sync de limites do plano: DEFERIDO)*; `invoice.payment_failed` → **só log** (+ opcional Payment `failed`); demais eventos de invoice/checkout no Payment local (se adotado). **Nenhuma transição de acesso em handler nosso.**
- **RF-W5** — Correlação principal pelo `stripe_id` (automática, Cashier); metadata `subscription_uuid` do Checkout como reforço para o Payment custom; evento órfão → log + descarte sem erro (doc 003 §5).
- **RF-W6** — Estado de acesso **derivado** do `stripe_status` (modelo de 3 estados, §2.7 / doc 003 §7): `Cashier::keepPastDueSubscriptionsActive()` ativo num provider, garantindo `valid()` true em `past_due`. A subclasse de Subscription pode expor o accessor `billing_status` (doc 004 RF-C3), **sem persistência**. Não confundir com o `onGracePeriod()` do Cashier (cancel-at-period-end — conceito diferente; comportamento desejado: acesso até o fim do período pago).
- **RF-W7** — Retentativas de cobrança **do Stripe** (dashboard): Smart Retries configurado + ação final "Cancelar assinatura" ao esgotar — documentado como pré-requisito de deploy/checklist, **não como código**. *(Substitui o antigo job agendado `subscriptions:process-dunning` e seus thresholds — removidos.)*
- **RF-W8** — Middleware `EnsureSubscriptionAccess` com checagem única: `valid()` → segue; senão bloqueia tudo exceto rotas de billing, com 402/403 no padrão de exceptions (doc 003 §7.3). Variante somente-leitura como nota (1 linha); **sem checagem de quota** (limites DEFERIDOS).

**Requisitos Não-Funcionais** (doc 003 §8):
- Resiliência: o sync nativo do Cashier já é idempotente; handlers custom idempotentes (retry do Stripe/Horizon nunca duplica efeito); falha transiente propaga exceção (retry Horizon); falha permanente → log + descarte. O estado de acesso, por ser **derivado**, não tem estado próprio a corromper.
- Performance: resposta ao Stripe < 5s — sync nativo inline (leve) + custom em fila; nenhuma chamada externa síncrona no caminho do webhook.
- Segurança: `STRIPE_WEBHOOK_SECRET` obrigatório; **menos superfície nossa** — verificação e parsing são do pacote.
- Observabilidade: log estruturado por evento no listener (id, tipo, resultado) — em particular `invoice.payment_failed`, cujo único papel é esse registro; transições de `stripe_status` escritas pelo Cashier aparecem na trilha de auditoria da Subscription (laravel-auditing).
- Testes: listener e handlers (1º pagamento vs recorrente), idempotência do custom (mesmo evento 2×), middleware nos 3 estados — com fakes do Cashier/mock do `StripeClient`; a verificação de assinatura é testada pelo pacote (basta teste de integração da rota montada).

**Dependências:** Fase 1 (Cashier instalado, `Billable` no Workspace, subclasse de Subscription, `Plan`/`Price` com `gateway_*_id`). O listener/Job pode andar em paralelo com o fim da Fase 1.

**Critérios de aceite:**
- [ ] Evento com assinatura inválida → 400/403 pelo Cashier sem tocar em handler; evento não mapeado → 200 + log do listener.
- [ ] Assinatura de teste criada via Stripe aparece sincronizada em `subscriptions` (status, trial, períodos) **sem handler nosso**.
- [ ] Mesmo evento entregue 2× não duplica efeito custom (cache e, se Payment local, constraint DB testados separadamente).
- [ ] Com `keepPastDueSubscriptionsActive()` ativo, assinatura em `past_due` retorna `valid() === true` e **passa** no middleware (acesso mantido durante a janela de retry).
- [ ] Assinatura em `canceled`/`unpaid` (ou ausente) é **bloqueada** pelo middleware com 402/403 pt-BR em qualquer rota de produto; **rotas de billing passam** mesmo bloqueado.
- [ ] Sequência `past_due` → `invoice.payment_succeeded`/`customer.subscription.updated` (status `active`) restaura o acesso sem nenhuma escrita custom.
- [ ] `stripe listen --forward-to` no ambiente local processa um checkout de ponta a ponta.

**Riscos e mitigação:**
- *200 = "aceito", não "processado"* para o custom em fila (falha no Job não gera retry do Stripe). Mitigar: retries do Horizon, alerta em `failed_jobs`, replay manual pelo dashboard (doc 003 §4).
- *Corrida invoice-antes-do-commit-local* no checkout. Mitigar: falhar o Job quando houver suspeita de corrida → retry do Horizon (doc 003 §5).
- *Esquecer o setup do dashboard do Stripe* (Smart Retries/ação final) — sem ele, `past_due` nunca vira cancelamento e o bloqueio não acontece. Mitigar: item obrigatório no checklist de deploy (§7) e teste manual em test mode.
- *Esquecer `keepPastDueSubscriptionsActive()`* — o default do Cashier bloquearia o tenant já na 1ª falha, antes das retentativas. Mitigar: teste automatizado cobrindo `valid()` true em `past_due`.
- *Ordem de eventos não garantida pelo Stripe*. Mitigar: handlers baseados em estado-alvo, find-or-create, timestamps do próprio evento — e, para o acesso, nenhum handler nosso: o estado deriva do último sync do Cashier.

---

### Fase 3 — Financeiro Cliente (doc 004)

**Objetivo:** o cliente assina no signup (Stripe Checkout com trial, via Cashier), consulta a assinatura atual, gerencia métodos de pagamento e acessa histórico de faturas — tudo workspace-scoped, com permissões `billing.view`/`billing.edit`. *(Endpoint de uso/quota: **DEFERIDO** — sem limites de plano nesta fase.)*

**Entregáveis** (menores que na versão pré-Cashier — checkout, payment methods e faturas viram chamadas a helpers do `Billable`):
- Integração no signup: criação do Stripe Customer no Workspace (`$workspace->createAsStripeCustomer()` via Observer do Workspace ou passo do fluxo de signup).
- Checkout: endpoint `POST /billing/checkout` chamando `$workspace->newSubscription('default', $price->gateway_price_id)->trialDays($price->trial_period_days)->withMetadata([...])->checkout(['success_url' => ..., 'cancel_url' => ...])` e retornando a URL hospedada (doc 004 §2). Substitui o `POST subscribe` legado (removido na Fase 1).
- Métodos de pagamento **sem tabela local**: endpoints finos sobre `paymentMethods()`, `addPaymentMethod()`, `updateDefaultPaymentMethod()`, `deletePaymentMethod()`, `defaultPaymentMethod()` (doc 004 §4).
- Faturas/histórico: `invoices()` do Cashier ao vivo (**Opção A, recomendada**) e/ou `downloadInvoice()` (**requer `dompdf/dompdf`**); tabela `Payment` local apenas se filtros/paginação custom forem necessários (**Opção B** — decidir explicitamente; doc 004 §5).
- Assinatura atual: `$workspace->subscription('default')` + `billing_status` derivado (accessor da subclasse) + `billing_message` (aviso textual em `past_due`/`blocked` — doc 004 RN-C8). *(Endpoint `GET /billing/subscription/usage`: **DEFERIDO** — doc 004 RF-C4; sem `PlanLimit` nem contadores de consumo nesta fase.)*
- Controllers + FormRequests + JsonResources: `BillingSubscriptionController`, `BillingCardController`, `BillingPaymentController`; `StoreCheckoutRequest`, `StoreCardRequest`, `UpdateCardRequest`; `SubscriptionResource`, `CardResource`, `PaymentResource`. *(Sem `UsageResource` — deferido.)*
- Rotas no bloco protegido (**sem `/api/v1`**, cf. §2.6): `GET /billing/subscription`, `GET|POST /billing/cards`, `PATCH|DELETE /billing/cards/{payment_method_id}`, `GET /billing/payments`, `GET /billing/payments/{payment_id}`, `POST /billing/checkout`. *(`GET /billing/subscription/usage`: DEFERIDA.)*
- Cache da assinatura corrente (5 min, invalidado pelo listener de webhook — RN-F13 do doc 004).

**Sequência de passos:**
1. Signup → `createAsStripeCustomer()` no Workspace.
2. Endpoint de checkout via `newSubscription()->checkout()` (fluxo do doc 004 §2; estado final vem do webhook da Fase 2).
3. Endpoint de leitura: subscription atual com `billing_status`/`billing_message` derivados.
4. Endpoints de payment methods sobre os helpers do Cashier (sem persistência local; regra "não deletar o default" na camada de service).
5. Histórico de faturas: Opção A (`invoices()`) e, se decidida, Opção B (Payment local com filtros via `DynamicFilter`); `downloadInvoice()` com dompdf.
6. Cache + invalidação; testes E2E do fluxo signup→checkout→webhook→active.

**Requisitos Funcionais** (já numerados no doc 004 — referência direta):
- **RF-C1..C3, C5** — assinatura atual (`GET /billing/subscription` com `stripe_status` + `billing_status` derivado; 404 pt-BR sem assinatura ativa) — doc 004 §3. **RF-C4** (usage) — **DEFERIDO**, cf. doc 004.
- **RF-C6..C10** — métodos de pagamento via helpers do Cashier (listar/adicionar por token Stripe.js/definir default/remover; nunca deletar o default — 422; 1 default por workspace, invariante do Cashier) — doc 004 §4. *Sem tabela Card local.*
- **RF-C11..C14** — histórico de pagamentos/faturas com links `hosted_invoice_url`/`receipt_url` (Opção A ao vivo ou Opção B local) — doc 004 §5.
- **RF-C15** *(consolidado do §2 do doc 004)* — checkout de assinatura via `newSubscription()->trialDays()->withMetadata()->checkout()` retornando URL hospedada; estado final vem do webhook (Cashier + listener).
- Regras de negócio RN-C1..C11 do doc 004 §7 (trial, 1 assinatura ativa, troca de default no próximo ciclo, **3 estados de acesso derivados** com `billing_status`/`billing_message`, mudança de plano fora de escopo — facilitada futuramente pelo `swap()`/`swapAndInvoice()` do Cashier).

**Requisitos Não-Funcionais** (doc 004 §8 — RN-F1..F14):
- Respostas JsonResource + mensagens pt-BR; códigos HTTP da tabela RN-F4 (incl. 402 para inadimplência).
- Segurança PCI: número/CVC nunca tocam o backend (só token `pm_…` via Stripe.js); token não exposto no JSON de resposta.
- Escopo por workspace em todas as queries; helpers do Cashier já operam sobre o Billable do workspace corrente.
- Paginação padrão (per_page 15, max 100) onde houver dados locais; eager-loading contra N+1; cache de 5 min na assinatura.
- Auditoria dos models locais; FormRequests com mensagens pt-BR.

**Dependências:** Fases 1 e 2 completas (planos/preços no Stripe; sync nativo + listener + middleware operantes). O front precisa de Stripe.js + `STRIPE_KEY` (publishable) para tokenizar cartões. `dompdf/dompdf` se `downloadInvoice()` for exposto.

**Critérios de aceite:**
- [ ] Fluxo E2E em test mode: signup → checkout URL → pagamento com cartão de teste → webhook → `GET /billing/subscription` retorna `trialing` (ou `active`) com períodos preenchidos e `billing_status` derivado.
- [ ] Adicionar cartão com `is_default=true` desfaz o default anterior (via `updateDefaultPaymentMethod`); deletar o default retorna 422 pt-BR.
- [ ] `GET /billing/payments` expõe faturas com links que abrem no Stripe (e, se Opção B, pagina/filtra localmente).
- [ ] Usuário de outro workspace não enxerga métodos de pagamento/faturas alheios (teste de isolamento).
- [ ] Usuário sem `billing.view`/`billing.edit` recebe 403.

**Riscos e mitigação:**
- *Latência entre pagamento e webhook* (cliente volta do checkout antes do `payment_succeeded`). Mitigar: front trata assinatura ausente/`incomplete` como "processando" via polling; success_url carrega `session_id` para tracking.
- *Checkout abandonado* → sessão expira sem assinatura. Mitigar: handler `checkout.session.expired` (Fase 2, se Payment local) ou simplesmente nenhum estado local a limpar (Opção A).
- *Chamadas ao vivo ao Stripe* (`paymentMethods()`, `invoices()`) adicionam latência nos GETs. Mitigar: cache curto por workspace; se virar gargalo, migrar faturas para a Opção B.

---

## 5. Requisitos não-funcionais globais

- **Segurança / PCI:** dados de cartão (número, CVC) jamais trafegam ou persistem no backend — apenas tokens (`pm_…`) via **Stripe.js + Cashier**; nenhuma coluna local com dados de cartão (tabela Card removida). Webhook sempre pela rota do Cashier com `VerifyWebhookSignature` (`STRIPE_WEBHOOK_SECRET` obrigatório). Secrets exclusivamente em `.env`/`config/cashier.php` (nunca hardcoded/commitados). Rotas autenticadas: `auth:sanctum` + `verified` + `permission:billing.*`.
- **Idempotência (escopo reduzido ao custom):** o sync nativo do Cashier já é idempotente; para nossos efeitos — cache por `event.id` + (se Payment local) partial-uniques nos `gateway_*_id` + handlers find-or-create. O estado de acesso é **derivado** do `stripe_status` — não há job nem transição custom a proteger.
- **Multitenancy:** billing ancorado no Workspace (Billable); `Payment` local (se adotada) com `BelongsToWorkspace`; nenhum endpoint cliente enxerga dados de outro tenant; testes de isolamento obrigatórios.
- **Observabilidade/Auditoria:** owen-it/laravel-auditing nos models de billing (transições de `stripe_status` escritas pelo Cashier na trilha da Subscription); log estruturado de todo evento no listener (`invoice.payment_failed` existe **só** para esse registro); monitoramento de `failed_jobs` no Horizon; métrica de eventos não mapeados.
- **Performance:** custom de webhook assíncrono (fila `webhooks` no Horizon); cache de 5 min na assinatura corrente com invalidação por webhook; eager-loading padrão; middleware de enforcement com cache por request.
- **Testabilidade:** Pest v4; **fakes do Cashier / mock do `StripeClient`** (sem `FakeGateway` próprio); factories de Plan/Price/Subscription (+ Payment se adotada); suite roda verde sem rede (§6).
- **Internacionalização:** moedas `brl/usd/eur/gbp` (enum), amounts em centavos; `CASHIER_CURRENCY` como default; mensagens user-facing pt-BR.
- **Consistência:** gateway-first-then-local no catálogo Admin; escritas multi-entidade sempre em `DB::transaction`; constraints de unicidade no DB como última linha de defesa.
- **Configuração:** tudo em `config/cashier.php` (+ bloco custom mínimo para o que é nosso, ex. `config/billing.php` com URLs de success/cancel do checkout), alimentado por `.env` (ver §7); nada lido de `env()` fora de config. *(Sem thresholds de dunning — o cronograma de retentativas é config do dashboard do Stripe, não do app.)*

---

## 6. Estratégia de testes

| Camada | O que cobre | Como |
|---|---|---|
| **Unit (services)** | PlanService/PriceService (imutabilidade, regras de delete), regras de payment method (não deletar default) | Mock do `StripeClient` (`Cashier::stripe()`) via Mockery; `Cashier::fake()` quando aplicável; asserts nas chamadas ao Stripe e no estado local |
| **Feature (admin)** | Endpoints `/admin/plans...` (Plan/Price/PriceHistory; limites DEFERIDOS) | `createUserWithPermissions(['billing.*'])`; casos felizes + 403 sem permissão + 409 das matrizes de deleção |
| **Feature (cliente)** | `/billing/...` | Helpers de auth (`createUserWithPermissions`); isolamento entre workspaces; 402/422/404 pt-BR; helpers do Billable mockados/fakeados |
| **Webhook (custom)** | Listener `WebhookReceived`, handlers custom, correlação, idempotência | Disparar `WebhookReceived` com payloads fixture (sem HTTP real); mesmo evento 2× e assertar unicidade dos efeitos; 1º pagamento (`start_date`) vs recorrente; trial vs active. A verificação de assinatura é do Cashier (testada pelo pacote) — basta 1 teste de integração da rota montada/protegida |
| **Enforcement (middleware)** | Checagem única `valid()` nos 3 estados | Assinatura em `past_due` → `valid() === true` (cobre que `keepPastDueSubscriptionsActive()` está ativo) e request **passa**; `canceled`/`unpaid`/ausente → **bloqueia** com 402/403 pt-BR; rotas de billing sempre acessíveis mesmo bloqueado |
| **E2E manual/staging** | signup→checkout→webhook→active | Stripe test mode + `stripe listen --forward-to` |

**Regra de ouro:** a suite completa roda verde **sem rede** — fakes do Cashier + mock do `StripeClient` no lugar do antigo conceito de `FakeGateway`. Testes que exercitam o Stripe real ficam num grupo separado, opcional, contra test mode.

**Foco de cobertura:** o que é **nosso** (catálogo, listener/handlers custom, middleware de enforcement, derivação do `billing_status`) recebe cobertura completa; o que é do **Cashier** (verificação de assinatura, sync nativo, invariantes de payment method) é testado pelo pacote — cobrimos apenas a integração. O cronograma de retentativas é do **Stripe** (dashboard) — não há job de dunning a testar.

---

## 7. Configuração e ambiente

**`.env` (novas chaves — adicionar também ao `.env.example`):**

```dotenv
STRIPE_KEY=pk_test_...              # publishable (front / Stripe.js)
STRIPE_SECRET=sk_test_...           # secret (backend)
STRIPE_WEBHOOK_SECRET=whsec_...     # ativa o VerifyWebhookSignature do Cashier
CASHIER_CURRENCY=brl
CASHIER_MODEL=App\Models\Workspace                  # Billable
CASHIER_SUBSCRIPTION_MODEL=App\Models\Subscription  # nossa subclasse mínima (uuid + billing_status derivado)
```

*(As chaves genéricas anteriores — `PAYMENT_GATEWAY`, `PAYMENT_ENABLED`, `STRIPE_SECRET_KEY` etc. — foram **removidas** do plano; o Cashier define a nomenclatura.)*

**Config:** `config/cashier.php` (padrão do pacote) + bloco custom mínimo para o que é nosso (ex.: `config/billing.php` com URLs de success/cancel do checkout). *(Não há mais thresholds de dunning em config — removidos junto com o job; o cronograma de retentativas vive no dashboard do Stripe.)* No `boot()` de um service provider: `Cashier::keepPastDueSubscriptionsActive()` (§2.7).

**Instalação:** `composer require laravel/cashier` (traz `stripe/stripe-php` como dependência — **um pacote só**). Publicar e rodar as migrations do Cashier (ajustando a migration de customer columns para `workspaces`). `composer require dompdf/dompdf` se `downloadInvoice()` for exposto (Fase 3).

**Setup Stripe (dashboard, test mode primeiro):**
1. Registrar o endpoint de webhook apontando para `https://<host>/stripe/webhook` (rota default do Cashier) — via dashboard ou `php artisan cashier:webhook` — assinando os eventos nativos do Cashier + os consumidos pelo nosso listener (doc 003 §6): `customer.subscription.created/updated/deleted`, `customer.updated/deleted`, `invoice.payment_action_required`, `invoice.created`, `invoice.payment_succeeded`, `invoice.payment_failed`, `invoice.voided`, `checkout.session.expired`.
2. Copiar o `whsec_…` para `STRIPE_WEBHOOK_SECRET`.
3. **Config de retentativas (obrigatória — é o nosso "dunning"):** em *Settings → Billing → Subscriptions and emails*, configurar Smart Retries (ou cronograma fixo, ~1–2 semanas) e a **ação final "Cancelar assinatura"** ao esgotar as retentativas; opcionalmente habilitar os e-mails automáticos do Stripe de falha de pagamento (doc 003 §7.2). **Sem este passo o bloqueio por inadimplência não acontece.**
4. Local: `stripe listen --forward-to localhost:8000/stripe/webhook`.
5. Fila: garantir a fila `webhooks` na config do Horizon. *(Scheduler não é requisito desta feature — não há mais comando agendado de dunning.)*

---

## 8. Roadmap / ordem de entrega e marcos

As Fases 2 e 3 são **significativamente menores** que na versão pré-Cashier (o pacote absorve webhook/checkout/payment methods/faturas/SCA) — e a Fase 2 ficou **ainda menor** com o dunning simplificado (§2.7): sem job agendado, sem enum/colunas de acesso, sem thresholds; sobra config (provider + dashboard Stripe) e um middleware de checagem única. A Fase 1 também encolheu com os **limites deferidos** (sem `PlanLimit`/endpoints de limites) e a Fase 3 perdeu o endpoint de usage. O esforço concentra-se em M1 (setup + rebuild + catálogo).

| Marco | Fase | Principais tarefas | Dependências | Definição de "pronto" |
|---|---|---|---|---|
| **M1 — Setup Cashier + rebuild do legado + catálogo Admin** | 1 | `composer require laravel/cashier` + config; drop das tabelas legadas (`subscriptions`/`plans`) + migrations do Cashier + `Billable` no Workspace + subclasse mínima de Subscription; migrations Plan/Price/PriceHistory (*sem `plan_limits` — DEFERIDA*); services com gateway-first via `Cashier::stripe()`, imutabilidade de preço e matrizes de deleção; endpoints admin de Plan/Price | Conta Stripe test; OK do PM para o drop do legado (§9 Q1) | Critérios de aceite da Fase 1; planos/preços visíveis no dashboard Stripe; suite verde offline |
| **M2 — Webhook listener + inadimplência simplificada** | 2 | Registro do webhook (`cashier:webhook`); validação do sync nativo; `keepPastDueSubscriptionsActive()` no provider; config de Smart Retries + ação final no dashboard Stripe (checklist); listener `WebhookReceived` + Job custom (`start_date` no 1º pagamento, Payment local se Opção B); middleware de enforcement (checagem única `valid()`) | M1 | Critérios de aceite da Fase 2; checkout de teste sincroniza ponta a ponta via `stripe listen`; `past_due` passa e `canceled` bloqueia |
| **M3 — Cliente: checkout, cards e faturas** | 3 | Customer no signup; `newSubscription()->checkout()`; subscription atual (`billing_status` derivado); payment methods via helpers do Cashier; faturas via `invoices()` (+ `downloadInvoice()`/dompdf); cache. *Sem endpoint de usage (DEFERIDO)* | M1, M2 | Critérios de aceite da Fase 3; fluxo E2E completo em test mode |
| **M4 — Hardening/go-live** | todas | Chaves live, webhook em produção, **config de retentativas/ação final no dashboard live**, alertas de failed_jobs, revisão de auditoria/logs, decisões do §9 fechadas com PM | M1–M3 | Checklist de produção assinado; primeira assinatura real processada |

**Paralelização:** o listener/Job (M2) pode começar assim que o setup do Cashier (início do M1) estiver aplicado — não depende dos endpoints admin.

**Fora deste roadmap (deferido, a alinhar):** `PlanLimit` + endpoints admin de limites, endpoint de uso/quota do cliente, enforcement de consumo e sincronização de limites no 1º pagamento — entram num marco futuro quando os limites forem alinhados com o PM (§9 Q7).

---

## 9. Riscos globais e questões em aberto (resolver com o PM)

**Decisões já tomadas (registradas, não reabrir sem motivo):**
- ✅ **Laravel Cashier v15** adotado; sem abstração de gateway própria.
- ✅ **Multi-gateway fora de escopo** — Stripe-only é decisão consciente, não é prioridade.
- ✅ **Tabelas/código legados de assinatura são resíduo a refazer** — rebuild limpo, sem migração de dados de produção.
- ✅ **Dunning simplificado (3 estados via Stripe Smart Retries)** — sem escada `access_level`, sem job agendado, sem colunas custom; acesso derivado do `stripe_status` + `keepPastDueSubscriptionsActive()` + middleware de checagem única; retentativas e ação final ("Cancelar assinatura") configuradas no dashboard do Stripe; e-mails de falha podem ser os automáticos do Stripe. *(Assunções default registradas no doc 003: bloqueio total exceto billing, com variante read-only disponível.)*
- ✅ **Limites de plano DEFERIDOS** — decisão do cliente: "por enquanto deixe sem limites de plano, irei alinhar isso ainda". Nada de `PlanLimit`, endpoints de limites, usage ou enforcement de quota nesta fase; a intenção futura está preservada nos docs (marcada como DEFERIDO) e reaberta na Q7 abaixo.

**Questões em aberto:**

1. **Confirmação do rebuild do legado:** validar com o PM que **nada em produção depende** das tabelas `subscriptions`/`plans` atuais e do fluxo `POST subscribe` local-only antes do drop (M1). Premissa do plano: são resíduo sem dados reais; se surgir dado real, reavaliar pontualmente — mas a direção é substituição, não migração.
2. **Convivência subclasse × model do Cashier:** nossa subclasse ficou mínima (`uuid` + accessor derivado), o que reduz muito o risco — mas ainda garantir (com testes) que ela não interfere no comportamento do pacote em upgrades de versão do Cashier.
3. **Payment local (Opção A vs B, doc 004 §5):** começar com `invoices()` ao vivo (A) e só criar a tabela `Payment` se filtros/paginação custom forem exigidos? Decidir antes do M2 (afeta os handlers do listener).
4. **Mudança de plano (upgrade/downgrade):** fora de escopo do M3 (RN-C11, doc 004 §7.5) — mas **facilitada pelo Cashier** (`swap()`/`swapAndInvoice()` com pro-rata nativo); o RFC futuro fica menor. Confirmar prioridade no backlog.
5. **Boleto vs. cartão:** o Checkout habilita boleto? Boleto muda a dinâmica de retentativas (compensação em dias) — habilitar já ou só cartão no lançamento?
6. **SCA/PSD2:** o Cashier já trata `invoice.payment_action_required` e o fluxo de pagamento incompleto nativamente; definir apenas a comunicação ao cliente (e-mail/aviso no front) quando ação for exigida.
7. **Limites de plano — alinhar com o PM (item aberto principal):** o cliente vai definir depois se/quais limites existem (posts/mês, contas sociais, storage, usuários…). Quando alinhado, destravar em bloco: `PlanLimit` + endpoints admin de limites (doc 002), endpoint `GET /billing/subscription/usage` (doc 004 RF-C4), enforcement de quota e sincronização de limites no 1º pagamento (doc 003). Até lá, **nenhum código de limites** entra.
8. **Janela de retentativas do Stripe:** duração exata (Smart Retries vs cronograma fixo; ~1–2 semanas assumido) e ativação dos e-mails automáticos do Stripe — validar com o negócio antes do go-live (M4). É configuração de dashboard, não código; cobre também a política pós-trial (RN-C10 do doc 004): trial expirado sem pagamento segue o mesmo fluxo `past_due` → retentativas → cancelamento.
9. **Faturas em PDF (`downloadInvoice()`):** requer `dompdf/dompdf` — expor download próprio ou só os links hospedados do Stripe (`hosted_invoice_url`)? Decidir no M3.
10. **Cancelamento self-service** (`DELETE /billing/subscription` — trivial com `$subscription->cancel()` do Cashier) e **sincronização manual** (endpoint admin de resync): listados como futuros no doc 004 §10 — entram no M4 ou backlog?

---

**Versão:** 3.0 (Cashier + dunning simplificado + limites de plano deferidos) · **Data:** 2026-07-07 · **Status:** rascunho para revisão
**Referências:** docs 001 (termos), 002 (Admin — endpoints de limites DEFERIDOS), 003 (Webhook/Inadimplência, revisado), 004 (Cliente, revisado), contexto compartilhado (billing-context), decisões de arquitetura (cashier-decision, dunning-simplification).
