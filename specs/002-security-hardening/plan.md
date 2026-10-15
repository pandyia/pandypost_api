# Plan 002 — Endurecimento de segurança

> Escopo desta fase (decisões do usuário em 2026-10-14): **ADM-01, VULN-01, VULN-02, VULN-03, VULN-05**.
> Fora desta fase: VULN-04 (pipeline — D2), BUG-01 (rotas não usadas pelo `pandypost_ui` — D3), OAUTH-01/02, VULN-06, AUTH-01/02, TEN-01, DOS-01 (aguardam as questões da spec).
>
> Restrição: **nenhuma rota é removida** e o contrato usado pelo `pandypost_ui` não muda (`GET /scheduled-posts`, `POST/PUT /workspaces` com `{ name }`).

## 1. Decisões

| # | Decisão | Alternativa descartada |
|---|---------|------------------------|
| P1 | **ADM-01:** middleware `EnsureSuperAdmin` (alias `super_admin`) aplicado ao grupo `prefix('admin')`. As permissões `billing.*` continuam nos controllers admin (inofensivas: super admin ignora). | Criar uma permissão `admin.*` que ninguém recebe: frágil, porque `Role::adminPermissions()` dá **todas** as permissões ao workspace pessoal — no dia em que alguém semeasse a permissão, todo dono a ganharia. |
| P2 | **VULN-03:** `ScheduledPost` passa a usar `BelongsToWorkspace` com `$workspaceRelation = 'socialAccount'` (filtro via `whereHas`, mecanismo que o trait já suporta — mesmo padrão do `User`). | Coluna `workspace_id` em `scheduled_posts` + backfill: migration + mudança no fluxo de criação, maior que o necessário agora. Pode virar evolução (índice direto). |
| P3 | **VULN-02:** `Access` **não** ganha global scope: o próprio scope lê `currentAccess` (query de `Access` → recursão) e quebraria `switchWorkspace`, o scope `member` do `Workspace` e o fallback de `resolveCurrentAccess`, que precisam ver acessos de outros workspaces. Segue o padrão do `Invite`: scope local `Access::forWorkspace()` + método de listagem no service (`workspaceAccesses()`, como `InviteService::receivedInvites()`) + `findById` escopado (usado por `show`/`destroy`). No `store`, o controller junta o `workspace_id` corrente ao `validated()` (padrão do `ContentPipelineController::store`) e o `StoreAccessRequest` exige `role_id` do workspace corrente. | Remover `apiResource('accesses')` (não usado pelo front): descartado pela D3. Sobrescrever `paginate`/`store` no service: redundante com o `BaseService`. |
| P4 | **VULN-01:** `UserController::store` sobrescrito com `StoreUserRequest` (`name`, `email`, `password`) e criação com campos explícitos. Mesma resposta de hoje (`201`, `Registro inserido com sucesso.`). | Tirar `is_super_admin` do `$fillable`: quebraria o `UserSeeder`. |
| P5 | **VULN-05:** `WorkspaceService::store` repassa só `name` ao `createAllAccess` (o observer de verificação de email continua passando os dados do workspace pessoal direto ao `createAllAccess`). `WorkspaceController::update` usa `UpdateWorkspaceRequest` (`name`). `StoreWorkspaceRequest` valida `name`. | Validar só no service: foge da convenção de FormRequest. |
| P6 | Campos proibidos são **ignorados** (não 422) — mantém o contrato e é o mesmo comportamento já adotado no `POST /pipeline`. (Q6) | 422: quebraria clientes que mandam campos a mais. |

## 2. Modelo de dados

Sem migrations.

## 3. Arquivos

- Novo: `app/Http/Middleware/EnsureSuperAdmin.php`; alias em `bootstrap/app.php`; `routes/api.php` (grupo `admin`).
- `app/Models/ScheduledPost.php` (trait + `$workspaceRelation`).
- `app/Models/Access.php` (scope `forWorkspace`); `app/Services/AccessService.php` (`workspaceAccesses`, `findById`); novo `app/Http/Requests/StoreAccessRequest.php`; `app/Http/Controllers/Api/AccessController.php` (`index`, `store`).
- `app/Http/Controllers/Api/UserController.php` (`store`); novo `app/Http/Requests/StoreUserRequest.php`.
- `app/Services/WorkspaceService.php` (`store`); `app/Http/Controllers/Api/WorkspaceController.php` (`store`, `update`); novos `StoreWorkspaceRequest`, `UpdateWorkspaceRequest`.

## 4. Efeitos colaterais

- `GET /scheduled-posts`: passa a mostrar só posts cujas contas sociais são do workspace corrente. Posts cuja conta foi desconectada (`social_account_id = null`) deixam de aparecer na listagem. O cancelamento (`DELETE /scheduled-posts/{uuid}`) passa a exigir que o post seja do workspace corrente.
- Jobs, commands e webhooks rodam sem usuário autenticado → o scope não filtra (comportamento atual preservado).
- `StorageService::deleteIfUnused` em request HTTP passa a enxergar só posts do workspace corrente; os paths já são prefixados por `workspaces/{uuid}/`, então o resultado não muda.

## 5. Testes

- Remover o `skip` de `VULN-01`, `VULN-02`, `VULN-03`, `VULN-05`, `ADM-01`; adicionar casos de `show`/`destroy` de accesses entre workspaces.
- `AdminPlanTest`/`AdminPriceTest`: autenticar como super admin (D1); o teste "sem billing.manage não cria plano" passa a "usuário comum não cria plano".
- Novos: listagem de posts do próprio workspace continua funcionando; `PUT /workspaces` com `{ name }` continua funcionando; 422 sem `name`.

## 6. Riscos

- **ScheduledPost via `whereHas`**: custo de subquery na listagem (aceitável no volume atual; P2 alternativa resolve se necessário).
- Usuário sem `currentAccess` continua sem filtro (TEN-01, fora desta fase).
