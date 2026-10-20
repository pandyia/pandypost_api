# 013 — Tasks

Uma mudança por tarefa, do menor risco e maior valor ao maior risco. Rodar `docker exec pandypost-php php artisan test` e `pint --dirty` ao fim de cada uma.

## 1. Caracterização (primeiro, sem tocar em `app/`)

- [x] 1.1 Token social: `getValidToken` renova YouTube, Instagram e TikTok (grava `access_token`/`expires_at`), `\Exception` com a mensagem atual sem `refresh_token` (YouTube/TikTok) e com resposta de falha (as 3), e `revokeToken` (YouTube/TikTok/Instagram) — teste: `tests/Feature/SocialAccounts/SocialAccountTokenTest.php` com `Http::fake`
- [x] 1.2 `BelongsToWorkspace`: filtro direto (`Role`), indireto (`User`, `ScheduledPost`) e sem `currentAccess` (sem filtro, comportamento atual) — teste: `tests/Feature/Workspaces/BelongsToWorkspaceScopeTest.php`
- [x] 1.3 `Plan::gatewayPriceIds`: preço atual, arquivado (soft delete) e histórico, sem duplicar nem trazer nulos — teste: `tests/Feature/Billing/PlanGatewayPriceIdsTest.php`
- [x] 1.4 `uuid` automático e `getRouteKeyName() === 'uuid'` nos 11 models do RF-10, e uuid explícito respeitado; `toArray()` de `Plan/Price/PriceHistory` hoje expõe `id` (registra o estado antes do RF-7) — teste: `tests/Feature/Models/UuidAndHiddenTest.php`
- [x] 1.5 `SocialAccount::daysSinceLastActivity/isStale` (sem post, post futuro, post antigo, relação carregada) — teste: `tests/Feature/SocialAccounts/SocialAccountActivityTest.php`
- [x] 1.6 Login: `remember_me` true/false grava a flag e devolve a expiração certa (`auth.token_expiration_days` / `sanctum.expiration`); confirmar o que `AuthenticationTest` já cobre antes de duplicar — teste: `tests/Feature/Auth/`

## 2. Baixo risco

- [x] 2.1 Remover `app/Models/Tenant.php` (RF-1) — teste: suíte inteira
- [x] 2.2 Remover `Role::hasPermission`, `ContentPipeline::isScheduled`, `ScheduledPost::scopeDue` e o `match` comentado de `getPlatformPostUrl` (RF-1) — teste: `RoleCrudTest`, `ContentPipelineTest`, `InstagramPermalinkTest`
- [x] 2.3 `RoleObserver`: remover `restored` e a guarda `deleted_at` (RF-1) — teste: `RoleCrudTest`, `tests/Feature/Security/`
- [x] 2.4 Usar `ScheduledPost::pending()` em `WarmupPostsCommand` e `SocialAccountService` (RF-2) — teste: `SocialAccountTest`, `CancelScheduledPostTest`
- [x] 2.5 Remover o override de `$casts` em `Notification` (RF-3) — teste: `NotificationApiTest`, `NotificationHooksTest`
- [x] 2.6 `Plan::gatewayPriceIds` com uma query a menos (RF-4) — teste: 1.3 + `AdminPlanTest`, `AdminPriceTest`
- [x] 2.7 `User`: import de `DB` pela facade (RF-6; some junto com 3.2 se a transação sair) — teste: `AuthenticationTest`

## 3. Médio risco

- [x] 3.1 `$hidden = ['id']` em `Plan`, `Price`, `PriceHistory` (RF-7); atualizar o estado registrado em 1.4 só no que é esperado (id some do `toArray`), sem alterar testes existentes — teste: 1.4 + `tests/Feature/Billing/*`
- [x] 3.2 `User::loginExpiration()` pura e `update(remember_me)` em `AuthService` (RF-9) — teste: 1.6 + `AuthenticationTest`, `TokenSecurityTest`
- [x] 3.3 `BelongsToWorkspace` com early return (RF-5) — teste: 1.2 + `WorkspacePermissionScopeTest`, `AuthorizationVulnerabilitiesTest`, `NotificationPermissionTest`
- [x] 3.4 `SocialAccount`: 5 `\Exception` viram `SocialAccountException` (casos novos em `SocialAccountError`), mesma mensagem (RF-8, Q2) — teste: 1.1 + `TikTokIntegrationTest`, `*AnalyticsTest`

## 4. Maior alcance (só com Q1 aprovada)

- [x] 4.1 Criar `Models/Traits/HasUuid.php` e migrar um model piloto (`Plan`) — teste: 1.4 + `AdminPlanTest`
- [x] 4.2 Migrar `Price`, `PriceHistory`, `Payment` — teste: 1.4 + `tests/Feature/Billing/*`
- [x] 4.3 Migrar `Role`, `ContentPipeline`, `Invite` — teste: 1.4 + `RoleCrudTest`, `ContentPipelineTest`, `InviteTest`
- [x] 4.4 Migrar `ScheduledPost`, `SocialAccount`, `Workspace`, `User` — teste: 1.4 + suíte inteira, incluindo `tests/Feature/Security/`

## Divergências

- 3.3: mantido o `?? null` em `(new static)->workspaceRelation ?? null` (RF-5 pedia dispensá-lo). Models diretos não declaram a propriedade; sem o `??` a leitura cairia no `__get` do Eloquent e quebraria se `preventAccessingMissingAttributes` for ativado. O early return foi aplicado normalmente.
- 2.6: `gatewayPriceIds` passou de 3 para 2 queries usando subquery (`select('id')`) no `whereIn` do histórico.
- 2.5: o comentário do `id` ficou como único conteúdo da classe `Notification`.
- Pint reformatou trechos vizinhos nos arquivos tocados (ordem de traits, espaçamento) em `User`, `AuthService`, `SocialAccountService`, `WarmupPostsCommand`, `RoleObserver`, `Role`, `ContentPipeline`, `Plan`, `Price`, `SocialAccountException`.
- Suíte: 3 falhas pré-existentes em `TikTokIntegrationTest` (redirect url, upload initializes, unaudited client error), idênticas antes e depois da refatoração; fora de escopo.
- Caracterização 1.2/1.3/1.5/1.6 passaram de primeira; em 1.4 o teste de `$hidden` foi invertido na task 3.1 (teste novo, não existente).
