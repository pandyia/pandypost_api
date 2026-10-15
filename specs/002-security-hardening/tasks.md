# Tasks 002 — Endurecimento de segurança

> Legenda: `[x]` feito · `[ ]` pendente · `[-]` fora desta fase.

## Fase 1 — Críticas

- [x] T1 ADM-01: middleware `EnsureSuperAdmin` + alias `super_admin` no grupo `/admin` — teste: `ADM-01` sem skip; `AdminPlanTest`/`AdminPriceTest` como super admin
- [x] T2 VULN-03: `ScheduledPost` com `BelongsToWorkspace` via `socialAccount` — teste: `VULN-03` sem skip; listagem do próprio workspace; `CancelScheduledPostTest`
- [x] T3 VULN-02: `AccessService` escopado + `StoreAccessRequest` — teste: `VULN-02` sem skip; show/destroy de outro workspace → 404
- [x] T4 VULN-01: `UserController::store` + `StoreUserRequest` — teste: `VULN-01` sem skip; 422 sem campos
- [x] T5 VULN-05: `WorkspaceService::store` só com `name` + `Store/UpdateWorkspaceRequest` — teste: `VULN-05` sem skip; `WorkspaceCrudTest` verde
- [x] T6 Pint + suíte completa

## Fora desta fase

- [-] VULN-04 pipeline (D2)
- [-] BUG-01 show/update genéricos — rotas não usadas pelo `pandypost_ui` (D3)
- [-] OAUTH-01/02, VULN-06, AUTH-01/02, TEN-01, DOS-01 — aguardam questões da spec

## Divergências

- **Pint (T6):** o `pint --dirty` reformatou os arquivos tocados por inteiro (ordem de imports em `routes/api.php`, `concat_space` em `bootstrap/app.php`, indentação em `ScheduledPost`). Só estilo, sem efeito de comportamento.
- **T2:** além da listagem, `DELETE /scheduled-posts/{uuid}` passa a exigir que o post seja do workspace corrente (antes bastava ser do mesmo usuário). Previsto no `plan.md` §4.
- **T3:** `PATCH /accesses/{id}` continua com 500 (BUG-01, fora desta fase pela D3); como sempre falha, não é vetor de escrita.
