# Pandypost API

**Pandy Pro / Pandy Post** é um SaaS de agendamento e publicação de vídeos em redes sociais (**YouTube, TikTok e Instagram**). O usuário conecta suas contas sociais, faz upload da mídia direto no S3 e agenda publicações para várias plataformas; uma esteira de filas publica no horário certo. O sistema é **multi-tenant por Workspace**: cada workspace tem membros (via convites), perfis (roles) com permissões granulares, assinatura própria (cobrança recorrente via Stripe), um pipeline de conteúdo estilo Kanban, analytics do YouTube e log de auditoria.

Este repositório é só a **API** (Laravel 12, sem views Blade). O frontend é um SPA separado (`FRONTEND_URL`, dev em `localhost:5173`). Domínio de produção: `pandy.pro`.

## Stack

- **PHP 8.4 / Laravel 12**, servido por **Octane (Swoole)** atrás de Nginx
- **Banco:** PostgreSQL 17 (dev via Docker; produção no Supabase — exige `DB_SSLMODE=require`, ver `config/database.php`)
- **Auth:** Laravel Sanctum (Bearer token / personal access tokens). Socialite é usado só para **conectar contas sociais** (OAuth Google/Meta/TikTok), não para login
- **Filas:** Redis + **Horizon**. Uma fila por plataforma (`default`, `instagram`, `youtube`, `tiktok` — ver `config/horizon.php`)
- **Cache:** Redis separado (`redis-cache`, LRU, sem persistência)
- **WebSocket:** Laravel **Reverb** (canais em `routes/channels.php`, ex.: `workspaces.{uuid}`)
- **Storage:** S3 via Flysystem — dev usa `play.min.io` (bucket `pandypost-dev`), produção usa **Backblaze B2**. Upload é feito pelo client com **presigned PUT URL** (`StorageService`)
- **Billing:** Laravel **Cashier (Stripe)** — `Billable` fica no **Workspace**, não no User
- **E-mail:** Resend (`MAIL_MAILER=resend`)
- **Auditoria:** `owen-it/laravel-auditing` (models implementam `Auditable`)
- **APIs externas:** `google/apiclient` (YouTube Data/Analytics), Graph API da Meta (Instagram), TikTok
- **Infra:** Docker Compose (dev: `docker-compose.yml`; prod: `docker-compose.prod.yml`). Deploy via GitHub Actions ao criar tag `v*` (imagens no GHCR → VPS)

## Ambiente de desenvolvimento

Tudo roda em Docker. **Não há `vendor/` no host** — comandos PHP/Artisan/Composer rodam dentro do container `pandypost-php` (via `make` ou `docker exec -it pandypost-php ...`).

> O `README.md` ainda fala de SQLite/Mailtrap — está desatualizado. A fonte de verdade é o `docker-compose.yml` + `.env.example`.

## Como trabalhamos aqui: Spec-Driven Development (SDD)

Este projeto segue SDD no nível **spec-first / spec-anchored**: toda feature nova ou mudança relevante começa com uma spec escrita antes do código. A estrutura segue a convenção do **GitHub Spec Kit** (só a convenção de pastas, sem a ferramenta).

### Estrutura

```
CLAUDE.md                      ← regras permanentes do projeto (este arquivo)
specs/
  001-billing/                 ← uma pasta por feature, numerada com 3 dígitos
    spec.md                    ← O QUÊ e POR QUÊ
    plan.md                    ← COMO
    tasks.md                   ← checklist de execução
  NNN-refactor-nome/           ← refatorações seguem a mesma estrutura
docs/                          ← documentação geral que não é spec (ex.: coleção Postman)
```

- Specs existentes: **`001-billing`** (o Financeiro: Cashier, webhook, inadimplência, cliente). A **próxima spec é a `002`**.
- O `specs/001-billing/` é a referência de nível de detalhe, em especial o `plan.md` (decisões de arquitetura registradas e questões em aberto).
- Arquivos opcionais dentro da pasta da feature, quando ajudarem: `data-model.md`, `contracts/` (exemplos de payload), `research.md` (comparação de alternativas).

### Fluxo obrigatório para novas features

Cada fase só começa depois que a anterior foi revisada e aprovada por mim.

1. **`spec.md` primeiro.** Antes de qualquer código, crie a pasta `specs/NNN-nome-da-feature/` com o `spec.md`. Nada de detalhe técnico aqui, só comportamento esperado. Não pule essa etapa mesmo que o pedido pareça simples. Ambiguidades de regra de negócio viram perguntas para mim, não suposições.
2. **`plan.md`** depois da spec aprovada: como implementar seguindo a arquitetura deste arquivo (arquivos tocados, migrations, filas, eventos, riscos).
3. **`tasks.md`** depois do plano aprovado: passos pequenos e verificáveis, em ordem de execução, cada um com seu teste.
4. **Implemente uma tarefa por vez**, marcando `[x]` no `tasks.md` ao concluir. Se perceber que a spec ou o plano estão errados ou incompletos, **pare e atualize o documento primeiro**, não improvise silenciosamente.
5. **Specs ficam no repositório** como histórico de decisão, inclusive depois de prontas. Decisões que mudarem depois entram como "Nota de revisão" no topo do arquivo afetado. Diferenças entre o que está escrito e o código vão para a seção "Divergências" do `tasks.md`.

Para features pequenas, os três arquivos podem ser curtos. O que importa é a separação entre o quê, o como e os passos.

### Template — `spec.md`

```markdown
# Spec NNN — [nome da feature]

**Status:** rascunho | aprovada | implementada

## Contexto
Por que isso existe, qual problema resolve, para quem.

## Escopo
O que está dentro e o que está explicitamente fora.

## Histórias / requisitos funcionais
- RF-1: Como [papel], quero [ação] para [benefício].
- Regras de negócio objetivas, casos de borda, validações. Deixar claro o escopo por workspace.

## Contrato de API (se aplicável)
- Rota, método, permissão necessária (`grupo.acao`), payload de entrada,
  resposta de sucesso e de erro (códigos de erro de domínio + HTTP)

## Requisitos não-funcionais (se aplicável)
Segurança, performance, auditoria, idempotência.

## Critérios de aceite
- [ ] Lista verificável de "pronto quando...".

## Fora de escopo / não fazer
O que explicitamente NÃO deve ser feito (evita over-engineering do agente).

## Questões em aberto
Pontos a decidir antes de implementar.
```

### Template — `plan.md`

```markdown
# Plano NNN — [nome da feature]

## Abordagem
Resumo técnico em um parágrafo.

## Decisões de arquitetura
Decisão + alternativa descartada + motivo.

## Modelo de dados
Migrations, models, relacionamentos, se usa BelongsToWorkspace / Auditable / uuid.

## Arquivos a criar / alterar
Controllers, FormRequests, Resources, Services, Enums de erro, Jobs, rotas, seeders de permissão.

## Efeitos colaterais
Filas/Jobs, broadcast (Reverb), notificações, APIs externas, Stripe.

## Estratégia de testes
Quais testes de feature/unit, o que mockar.

## Riscos e mitigação
```

### Template — `tasks.md`

```markdown
# Tasks NNN — [nome da feature]

- [ ] 1. [passo pequeno e verificável] — teste: [qual teste cobre]
- [ ] 2. ...

## Divergências
Diferenças entre spec/plano e o que foi implementado (decidir: atualizar o documento ou o código).
```

## Refatorações

Refatoração segue SDD também, mas com uma regra extra inegociável: **comportamento observável não muda**. Não é o momento de "aproveitar e ajustar" regra de negócio — se encontrar algo errado durante a refatoração, aponte, não corrija junto (isso vira outra tarefa/spec).

Pasta `specs/NNN-refactor-nome/`, com os mesmos três arquivos. No `spec.md`: **Motivação**, **Escopo (arquivos/módulos)**, **Comportamento que deve ser preservado** e **Apontamentos fora de escopo**. No `plan.md`: **Testes de caracterização** e **Plano incremental**. Passos obrigatórios:

1. **Sem teste cobrindo a área? Primeiro passo é escrever teste de caracterização** (captura o comportamento atual, nem que seja feio) — só depois mexe no código.
2. **Escopo fechado.** Liste os arquivos/módulos dentro do escopo na spec. Qualquer coisa fora disso que pareça merecer refactor também vira um apontamento no final, não uma mudança na mesma tarefa.
3. **Rodar a suíte de testes antes e depois** e confirmar que o resultado é idêntico (mesmos testes passando, nenhum novo caso de comportamento).
4. Se a refatoração for grande (ex: trocar padrão arquitetural inteiro), quebrar em specs menores e incrementais em vez de uma spec gigante — cada uma com seu próprio "antes/depois passa nos testes".
5. **Refatoração que muda contrato de API** não é refatoração pura — se o payload/resposta mudar, é mudança de contrato e precisa de spec de feature normal, não de refactor.

## Aplicando SDD retroativamente (projeto já em andamento)

Como o sistema já existe, não vamos reescrever tudo como spec de uma vez. Regra prática:
- **Features novas e mudanças grandes** → sempre nascem como spec (`specs/`).
- **Bugfixes pequenos e ajustes pontuais** → não precisam de spec formal, mas ainda seguem as regras gerais abaixo.
- **Áreas críticas do sistema existente** → vale escrever uma spec "reversa" (documentando o comportamento atual) antes de qualquer mudança nelas, mesmo pequena. No Pandypost são:
  - **Billing / Stripe** (Cashier, webhook, `HandleStripeWebhook`, moeda do workspace) — já documentado em `specs/001-billing/`
  - **Autenticação** (signup, login, verificação de e-mail, reset de senha, expiração de token)
  - **Permissões e isolamento por workspace** (`CheckPermission`, `BelongsToWorkspace`, super admin)
  - **Esteira de publicação** (`ScheduledPostService`, `PublishPostJob`, `CheckInstagramContainerJob`, payload builders, upload S3)
  - **OAuth de contas sociais** (tokens das plataformas, callback público validado pelo `state`)

## Arquitetura

- **Sem Repository Pattern.** Services usam Eloquent direto.
- **Service layer** para toda regra de negócio (`app/Services`). CRUD genérico herda de `BaseService` (`paginate` com `DynamicFilter`, `findById`, `findByUuid`, `store`, `update`, `destroy`, `restore`). Configuração por propriedades: `$with`, `$normalFilter`, `$whereHas`, `$orderBy`.
- **Sem Actions.** Não introduzir Actions sem spec.
- **Controllers** em `app/Http/Controllers/Api`, herdando de `BaseController`, que já implementa `index/store/show/update/destroy/restore` delegando ao service. Controllers sobrescrevem só o que precisam.
- **Permissões** declaradas no controller: `$permissionGroup` + `$permissionMethods` geram middleware `permission:{grupo}.{acao}` (ex.: `roles.view`, `billing.manage`). Rotas fora de controller usam `->middleware('permission:...')` direto. Super admin (`is_super_admin`) ignora a checagem.
- **Multi-tenancy:** Workspace é o tenant. Models com a trait `BelongsToWorkspace` recebem global scope pelo `workspace_id` do `currentAccess` do usuário (ou via `$workspaceRelation` para filtro indireto). O workspace corrente vem de `auth()->user()->resolveCurrentAccess()`. Em testes/consultas administrativas use `withoutGlobalScopes()` conscientemente.
- **Identificadores públicos:** recursos expostos na API usam **`uuid`** nas rotas; o `id` numérico fica em `$hidden`.
- **Erros de domínio:** cada domínio tem um enum em `app/Enums/Exceptions/*Error.php` (implementa `ErrorEnumInterface` com `message()` e `httpCode()`) e uma exception em `app/Exceptions/*Exception.php` (estende `BaseException`, com factories estáticas, ex.: `RoleException::nameAlreadyExists()`). A resposta é `{ "error": "<codigo>", "message": "<texto pt-BR>" }`. Novos erros seguem esse padrão — não lançar `\Exception` genérica em código novo.
- **Integrações por plataforma** (Strategy + Factory): `SocialMediaFactory` → `*Service` (`SocialMediaServiceInterface`); `PayloadBuilderFactory` → `*PayloadBuilder` (`PlatformPayloadBuilderInterface`); `OAuthProviderFactory` → `*OAuthProvider` (`OAuthProviderInterface`). Nova plataforma = novo case em `App\Enums\Platform` + implementações dessas interfaces + fila no Horizon.
- **Efeitos colaterais:** Observers (`#[ObservedBy]`) para reações a mudanças de model; Events com broadcast via Reverb (ex.: `RoleEvent`); Listener `HandleStripeWebhook` para eventos do Cashier; Notifications para e-mail.
- **Jobs:** `PublishPostJob` é despachado na fila da plataforma (`onQueue($post->platform->value)`). Timeouts dos jobs precisam ficar abaixo do `retry_after` da conexão de fila.
- **Agendamentos** em `routes/console.php` (`posts:warmup`, `invites:clean-expired`, `audits:prune`).
- **Billing:** decisões em `specs/001-billing/plan.md` (andamento e divergências em `tasks.md`) — estado da assinatura vem do Stripe via webhook (nunca da chamada de criação), sem limites de plano nesta fase, middleware `subscribed` (`EnsureSubscriptionAccess`) ainda não aplicado nas rotas.

## Convenções de código

- **Formatação:** Laravel Pint (preset padrão `laravel`, não há `pint.json`). O código existente **não está 100% formatado** pelo Pint — rode só nos arquivos alterados (`--dirty`) para não gerar diff de formatação em massa.
- **Idioma:** código (classes, métodos, variáveis) em inglês; **mensagens de resposta, erros e comentários em português (pt-BR)**.
- **Controllers:** finos. Sem regra de negócio no controller — delegar para o Service.
- **Validação:** via `FormRequest` dedicado. Como os métodos de `BaseController` têm assinatura `store(Request $request)` / `update(Request $request, $id)`, o padrão do projeto ao sobrescrevê-los é resolver o FormRequest dentro do método: `$data = app(StorePlanRequest::class)->validated();`. Em métodos novos (que não sobrescrevem o BaseController), pode tipar o FormRequest direto no parâmetro. Nunca `$request->validate()` inline em código novo. (Alguns controllers antigos — Role, User, Workspace — ainda passam `$request->all()` sem FormRequest; não "corrigir de passagem".)
- **Respostas de API:** sempre via `JsonResource`, nunca Model/Collection cru. Padrão do projeto:
  - leitura: `(new XResource($model))->response()` ou `XResource::collection($paginator)->response()`
  - escrita: `response()->json(['message' => '...', 'data' => new XResource($model)], 201)`
- **Nomenclatura:**
  - Controllers: `NomeController` (singular do recurso). Subdomínios ganham namespace (`Billing/Admin`, `Billing/Tenant`, `Billing/Public`) — mesmo padrão para Requests, Resources e Services.
  - Form Requests: `StoreNomeRequest`, `UpdateNomeRequest` (ou verbo da ação: `MoveStageRequest`, `GenerateUploadUrlRequest`)
  - Resources: `NomeResource`, `NomeCollection`
  - Services: `NomeService`; Enums em `app/Enums` com `label()` quando exibidos
- **Migrations:** uma responsabilidade por migration, nomes descritivos (`add_status_to_orders_table`, não `update_orders`). O SQL precisa funcionar em **PostgreSQL e SQLite** (os testes rodam em SQLite em memória).
- **Eloquent:** evitar N+1 — usar `with()`/`load()` explícito (ou `$with` no service); evitar lógica pesada em accessors/mutators.
- **Octane:** a app roda em processo persistente (Swoole). Não guardar estado de request em propriedades estáticas/singletons.

## Testes

- **Framework:** Pest 4 (`pestphp/pest-plugin-laravel`). Testes de feature em `tests/Feature/<Dominio>/`, agrupados com `describe()` / `it()` em português.
- Banco de teste: **SQLite em memória** (`phpunit.xml`), fila `sync`, mail `array`, broadcast `null`. Cada arquivo usa `uses(RefreshDatabase::class)` e normalmente `withoutMiddleware(ThrottleRequests::class)`.
- Helpers globais em `tests/Pest.php` (ex.: `signupUser()`, `createUserWithPermissions([...])` — o usuário retornado tem `test_token`).
- Toda rota de API nova precisa de teste de feature: happy path + pelo menos 1 caso de erro/validação + **1 caso de permissão negada (403)** + **isolamento entre workspaces** quando o recurso é escopado.
- Chamadas a APIs externas (Stripe, Meta, Google, TikTok, S3) devem ser mockadas/fakeadas — nunca bater em serviço real no teste.
- Rodar a suíte antes de dar uma tarefa como concluída (`make test`).

## Fluxo de git

- Branch de trabalho: `develop`; PRs para `main`. Deploy em produção acontece ao criar tag `v*`.
- Commits no padrão **Conventional Commits em português**: `feat(billing): ...`, `fix(cors): ...`, `chore(docker): ...`. Pequenos, um propósito por commit.

## Regras gerais para o agente

- **Nunca** assuma regra de negócio não documentada — pergunte ou marque como suposição explícita no código/commit.
- **Nunca** crie abstrações, camadas ou dependências novas (packages) sem justificar e sem estarem na spec.
- Ao editar um arquivo existente, **mantenha o estilo já usado nele**, mesmo que diferente do padrão acima (não refatore de graça).
- Se encontrar um TODO, código morto ou débito técnico fora do escopo da tarefa atual, **não corrija de passagem** — apenas aponte no final da resposta.

## O que NÃO fazer sem pedir

- Não instalar pacotes novos (composer) sem confirmar comigo.
- Não alterar `.env.example`, `docker-compose.prod.yml`, `docker/` de produção ou `.github/workflows/` sem avisar explicitamente.
- Não rodar migrations destrutivas (`migrate:fresh`, `migrate:rollback`, `db:wipe` — inclusive `make ms`, `make rollback`, `make wipe`) automaticamente.
- Não criar tags `v*` nem fazer push para `main` (dispara deploy em produção).
- Não alterar produtos/preços no Stripe nem chamar APIs reais das redes sociais.

## Comandos úteis

```bash
make buildup                 # sobe a stack Docker e instala dependências
make up / make down          # sobe / derruba os containers
make e                       # shell dentro do container PHP
make test                    # roda a suíte (php artisan test no container)
make migrate                 # roda migrations
make horizon-status          # status do Horizon
make logs-octane             # logs da API (também logs-horizon, logs-reverb)
make restart-octane          # reinicia o Octane após mudanças que não recarregam sozinhas
make test-s3                 # testa escrita no bucket S3 de dev

docker exec -it pandypost-php ./vendor/bin/pint --dirty   # formata arquivos alterados
docker exec -it pandypost-php composer test               # alternativa ao make test
```

> **Para o agente:** o agente que tem permissão para usar Docker **pode usar o `make` diretamente** (`make up`, `make migrate`, `make horizon-status`, `make logs-octane`, `make restart-octane`, `make test-s3`...), respeitando as regras de "O que NÃO fazer sem pedir" (alvos destrutivos/produção continuam exigindo confirmação).
>
> Exceção: não há TTY, então os alvos do Makefile que usam `docker exec -it` (variável `exec`: `make test`, `make e`...) falham. Nesses casos use o equivalente `docker exec pandypost-php <comando>` sem `-it` (ex.: `docker exec pandypost-php php artisan test`).

## Agentes (subagents do Claude Code)

Definidos em `.claude/agents/`, ligados ao fluxo de SDD:

- **`dev`**: implementa o `tasks.md` de uma spec aprovada, uma tarefa por vez.
- **`qa`**: confere a implementação contra os critérios de aceite do `spec.md`, escreve os testes que faltam, roda a suíte e entrega um relatório. **Só escreve em `tests/`** (e cria factories novas); nunca altera código da aplicação. É uma regra de instrução, não imposta por ferramenta: o relatório termina com `git status --short` para conferência.

**Proteções do projeto (`.claude/settings.json`):** regras `ask` que exigem confirmação do usuário (inclusive em modo auto, e para qualquer agente) antes de: push/tag, `git reset --hard`/`git clean`, migrations destrutivas e `db:wipe`, alvos destrutivos ou de produção do Makefile, `composer require/remove/update`, apagar volumes do Docker e editar `.env.example`, arquivos de produção do Docker e `.github/`.

Fluxo típico: spec/plan/tasks aprovados → `dev` → `qa` → bugs do relatório voltam para o `dev`.

Documentação da API (Postman): `docs/pandy-api.postman_collection.json` / https://documenter.getpostman.com/view/19909270/2sBXc8oi7R
