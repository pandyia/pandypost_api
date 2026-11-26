# Pandypost API

SaaS de agendamento/publicação de vídeos (YouTube, TikTok, Instagram), **multi-tenant por Workspace**. Só a API (Laravel 12, PHP 8.4, Octane/Swoole); o frontend é um SPA separado.

Stack: PostgreSQL · Sanctum · Redis + Horizon (uma fila por plataforma) · Reverb · S3 (upload via presigned URL) · Cashier/Stripe (`Billable` no Workspace) · Pest.

Tudo roda em Docker; **não há `vendor/` no host**. Sem TTY: use `docker exec pandypost-php <comando>` (ex.: `php artisan test`) em vez dos alvos `make` que usam `-it`.

## Fluxo SDD

Toda feature nova ou mudança relevante começa em `specs/NNN-nome/` (próxima: **023**). Modelo de referência: `specs/001-billing/`.

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

## Redes sociais (YouTube, Instagram, TikTok e futuras)

**É um padrão.** Toda rede segue o mesmo molde (`Platform` + `<Rede>Api` + `<Rede>Error/Exception` + `<Rede>Service` + job de conferência + `<Rede>PayloadBuilder` + `<Rede>OAuthProvider` + analytics, registrados nas factories). Guia completo, checklist para rede nova (Facebook, X, Kwai...) e armadilhas já pagas: **`docs/padrao-redes-sociais.md`**. Use o agente `social-network`.

- **A conta social é do workspace:** quem tem acesso e permissão agenda em qualquer conta conectada; `social_accounts.user_id` é só "quem conectou por último", nunca autorização.
- Validação por rede no **agendamento** (422 tudo-ou-nada); `error_message` sempre texto fixo em pt-BR do enum de erro da rede, nunca body/token/URL de terceiro.
- Só o `<Rede>Api` faz `Http::`; erro permanente (`isPermanent()`) não repete; nunca publicar em duplicidade; mídia no S3 só apagada em sucesso; job com `->afterCommit()`; `state` OAuth de uso único (`OAuthStateService`).
- Specs de referência: 011 (TikTok), 014 (base comum), 015 (Instagram), 016 (YouTube), 017 (conta do workspace), 018 (endurecimento). Produto concorrente do mLabs: o diferencial é confiabilidade, validação antecipada e trabalho em equipe.

## Segurança

Achados abertos e decisões pendentes: `specs/002-security-hardening/spec.md`.

- **Nunca** `$request->all()`/`input()` direto no Eloquent: `store`/`update` usam `validated()` e campos explícitos. `is_super_admin`, `access_id`, `workspace_id`, `user_id`, `uuid` e `is_personal_team` nunca vêm do request.
- Model novo com dado de tenant usa `BelongsToWorkspace` (ou filtro explícito pelo workspace corrente). Toda ação de controller (inclusive `update`/`destroy`/`restore` herdados e ações custom) entra em `$permissionMethods`.
- Rotas `/admin/**` são do dono do SaaS, não do dono do workspace.
- Respostas nunca expõem `$e->getMessage()` de exceção de terceiros nem tokens de plataforma.
- OAuth: `state` com nonce, expiração e vínculo ao usuário; stubs de plataforma só em `local`/`testing`.

## Convenções

- Código em inglês; mensagens, erros e comentários em **pt-BR**.
- **Validação:** FormRequest. Controller que herda o CRUD do `BaseController` declara o FormRequest por ação em `protected static array $formRequests = ['store' => StoreXRequest::class, 'update' => UpdateXRequest::class];` e a base valida (sem declaração ela lança `LogicException`, nunca usa request cru). Em override de método da base, use `$data = $this->validatedInput('store');`. Ação que não sobrescreve a base recebe o FormRequest na assinatura. Quando a validação consulta serviço externo (S3 etc.), a permissão vai no `authorize()` do FormRequest para rodar antes das regras. Nunca `$request->validate()` inline nem `app(XRequest::class)` no controller.
- **Respostas:** sempre `JsonResource`. Escrita: `response()->json(['message' => '...', 'data' => new XResource($m)], 201)`.
- **Migrations:** uma responsabilidade cada; SQL compatível com PostgreSQL **e** SQLite.
- Evitar N+1 (`with()`/`$with`). Octane: nada de estado de request em estático/singleton.
- Pint só nos arquivos alterados: `docker exec pandypost-php ./vendor/bin/pint --dirty`.
- Arquivo existente: mantenha o estilo dele. TODO/débito fora do escopo: aponte, não corrija.

## Testes

Pest em `tests/Feature/<Dominio>/`, `describe()`/`it()` em português, SQLite em memória. Helpers em `tests/Pest.php` (`createUserWithPermissions([...])`).
Toda rota nova: happy path + erro/validação + **403** + **isolamento entre workspaces**. APIs externas sempre mockadas. Rode a suíte antes de concluir.

- Isolamento = recurso de outro workspace retorna 404 **e** nada muda no banco.
- Testes de segurança em `tests/Feature/Security/`. Vulnerabilidade confirmada e ainda não corrigida: teste do comportamento seguro com `->skip('VULN-NN: causa')` e ID na spec de segurança; ao corrigir, remova o `skip`. Proteção frágil/incidental ganha teste de regressão ativo.
- Nunca enfraqueça ou apague teste para fazer passar.

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

| Agente | Papel | Escreve em |
|--------|-------|------------|
| `tdd` | Fase vermelha: testes da spec antes do código, falhando pelo motivo certo | `tests/` |
| `dev` | Implementa o `tasks.md` de spec aprovada | código |
| `refactor` | Refatoração de `specs/NNN-refactor-*` sem mudar comportamento; caracterização antes | código |
| `qa` | Valida contra os critérios de aceite | `tests/` |
| `security` | Audita e prova falhas com teste; não corrige | `tests/` |
| `docs` | Documenta os endpoints no Scribe (`/docs`) para o frontend; não muda comportamento | docblocks dos controllers |
| `social-network` | Especialista em redes sociais: conduz rede nova/mudança de rede no padrão de `docs/padrao-redes-sociais.md` | spec + código + `tests/` (via fluxo SDD) |
| `notifications` | Monta uma notificação nova no padrão da central (enum, factory, gancho, testes) e entrega o contrato ao front | código + `tests/` |

Fluxo de feature: spec/plan/tasks aprovados → `tdd` → `dev` → `qa` → `security` (obrigatório em área crítica) → `docs`. Refatoração: spec aprovada → `refactor` → `qa`. Agentes que só escrevem em `tests/` não corrigem bugs: reportam.
