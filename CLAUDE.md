# Pandypost API

SaaS de agendamento/publicação de vídeos (YouTube, TikTok, Instagram), **multi-tenant por Workspace**. Só a API (Laravel 12, PHP 8.4, Octane/Swoole); o frontend é um SPA separado.

Stack: PostgreSQL · Sanctum · Redis + Horizon (uma fila por plataforma) · Reverb · S3 (upload via presigned URL) · Cashier/Stripe (`Billable` no Workspace) · Pest.

Tudo roda em Docker; **não há `vendor/` no host**. Sem TTY: use `docker exec pandypost-php <comando>` (ex.: `php artisan test`) em vez dos alvos `make` que usam `-it`.

## Fluxo SDD

Toda feature nova ou mudança relevante começa em `specs/NNN-nome/` (próxima: **002**). Modelo de referência: `specs/001-billing/`.

1. **`spec.md`** — o quê e por quê, sem detalhe técnico. Seções: Contexto, Escopo, Requisitos (RF-n), Contrato de API, Critérios de aceite, Fora de escopo, Questões em aberto.
2. **`plan.md`** — como: decisões (+ alternativa descartada), modelo de dados, arquivos, efeitos colaterais, testes, riscos.
3. **`tasks.md`** — checklist `- [ ] passo — teste: ...` + seção **Divergências**.
4. Implementar uma tarefa por vez, marcando `[x]`.

- Cada fase só começa após minha aprovação.
- Ambiguidade de regra de negócio vira pergunta, nunca suposição.
- Se a spec/plano estiver errado, pare e atualize o documento antes do código.
- Bugfix pequeno não precisa de spec.
- **Áreas críticas** (billing, auth, permissões/workspace, esteira de publicação, OAuth social): escreva uma spec reversa do comportamento atual antes de mexer.

**Refatoração** (`specs/NNN-refactor-nome/`): comportamento e contrato de API não mudam. Sem teste na área → teste de caracterização primeiro. Escopo fechado; problemas encontrados viram apontamento, não correção.

## Arquitetura

- **Sem Repository e sem Actions.** Services usam Eloquent direto; CRUD herda `BaseService` (config por `$with`, `$normalFilter`, `$whereHas`, `$orderBy`).
- **Controllers** em `app/Http/Controllers/Api`, finos, herdando `BaseController`. Subdomínios ganham namespace (ex.: `Billing/Admin`) — idem Requests, Resources, Services.
- **Permissões:** `$permissionGroup` + `$permissionMethods` no controller → `permission:grupo.acao`. Super admin ignora.
- **Multi-tenancy:** trait `BelongsToWorkspace` (global scope pelo workspace corrente). `withoutGlobalScopes()` só de forma consciente.
- **Rotas usam `uuid`**; `id` fica em `$hidden`.
- **Erros de domínio:** enum em `app/Enums/Exceptions/*Error.php` + exception `*Exception` com factory estática (ex.: `RoleException::nameAlreadyExists()`). Nunca `\Exception` genérica.
- **Plataformas:** Strategy + Factory (`SocialMediaFactory`, `PayloadBuilderFactory`, `OAuthProviderFactory`).
- **Efeitos colaterais:** Observers, Events (broadcast Reverb), Jobs na fila da plataforma, Notifications.
- **Billing:** regras em `specs/001-billing/plan.md`.

## Convenções

- Código em inglês; mensagens, erros e comentários em **pt-BR**.
- **Validação:** FormRequest. Ao sobrescrever método do `BaseController`: `$data = app(StoreXRequest::class)->validated();`. Nunca `$request->validate()` inline.
- **Respostas:** sempre `JsonResource`. Escrita: `response()->json(['message' => '...', 'data' => new XResource($m)], 201)`.
- **Migrations:** uma responsabilidade cada; SQL compatível com PostgreSQL **e** SQLite.
- Evitar N+1 (`with()`/`$with`). Octane: nada de estado de request em estático/singleton.
- Pint só nos arquivos alterados: `docker exec pandypost-php ./vendor/bin/pint --dirty`.
- Arquivo existente: mantenha o estilo dele. TODO/débito fora do escopo: aponte, não corrija.

## Testes

Pest em `tests/Feature/<Dominio>/`, `describe()`/`it()` em português, SQLite em memória. Helpers em `tests/Pest.php` (`createUserWithPermissions([...])`).
Toda rota nova: happy path + erro/validação + **403** + **isolamento entre workspaces**. APIs externas sempre mockadas. Rode a suíte antes de concluir.

## Git

Branch `develop`, PR para `main`. Conventional Commits em pt-BR (`feat(billing): ...`), um propósito por commit.

## Não fazer sem pedir

- Instalar pacotes ou criar abstrações novas.
- Migrations destrutivas (`migrate:fresh`, `rollback`, `db:wipe`, `make ms/rollback/wipe`).
- Push para `main` ou tags `v*` (deploy em produção).
- Alterar `.env.example`, `docker-compose.prod.yml`, `docker/` de produção, `.github/`.
- Mexer em produtos/preços do Stripe ou chamar APIs reais das redes sociais.
- Commitar sem a minha autorização.

## Agentes

`dev` implementa o `tasks.md` de spec aprovada; `qa` valida contra os critérios de aceite e só escreve em `tests/`. Fluxo: spec/plan/tasks aprovados → `dev` → `qa`.
