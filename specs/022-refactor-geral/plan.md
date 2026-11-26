# Plan 022 — Refatoração geral em 3 fases

## Decisões

| # | Decisão | Alternativa descartada |
|---|---------|------------------------|
| D1 | **Mecânica do BaseController:** o filho declara `protected static array $formRequests = ['store' => StoreXRequest::class, 'update' => UpdateXRequest::class];` e a base ganha um helper `protected function validatedInput(string $action): array` que resolve `app(static::$formRequests[$action])->validated()` (sem entrada declarada → `LogicException` com mensagem clara em dev, nunca `$request->all()`). `store()`/`update()` da base usam o helper; overrides com resposta própria chamam `$this->validatedInput('store')` em vez de `app(X::class)->validated()`. | (i) Só propriedades `$storeRequest`/`$updateRequest`: não dá nome ao caso de ações extras e espalha propriedades; (ii) resolver o FormRequest por convenção de nome (`Store{Model}Request`): mágico, quebra nos subdomínios `Billing/Admin`; (iii) type-hint do FormRequest no override: viola LSP (narrowing) — é exatamente o motivo do padrão `app()` existir. |
| D2 | Ações que **não** são override (sem LSP) usam FormRequest direto na assinatura — padrão Laravel, zero código. Vale para todos os controllers que estendem `Controller` e para ações custom dos filhos do `BaseController` (`changeRole`, `move`, `uploadUrl`, `retry` — os 2 últimos já fazem isso). | Usar o helper D1 também nelas: uniformidade à custa de abrir mão da injeção nativa. |
| D3 | `users.update` e `accesses.update` (Q1, default a): mantêm override explícito de 3 linhas com `$request->all()` + comentário apontando A1/A2 — o desvio fica **visível e localizado** em vez de escondido na base. | Criar FormRequests mínimos: muda comportamento (vira Q1-b, segurança). |
| D4 | RF-8: `WorkspaceError::PERMISSION_DENIED_IN_WORKSPACE` + `WorkspaceException::permissionDeniedInWorkspace(string $permission)` com `render()` sobrescrito devolvendo `{"message", "required_permission"}` 403, idêntico ao middleware `CheckPermission` (Q3). A checagem (`Workspace::where('uuid')` + `hasPermissionInWorkspace`) vira `WorkspaceService::authorizePermissionInWorkspace(User, string $uuid, string $permission): Workspace`. | Render padrão do `BaseException`: adicionaria `error` e removeria `required_permission` do corpo — contrato muda. |
| D5 | RF-7: `SocialAccountService::connectFromCallback(string $platform, ?string $code, array $state): void` — resolve workspace/iniciador, revalida vínculo e permissão (lançando `SocialAccountException` com códigos próprios para "state inválido" e "sem permissão", mapeados no controller para as mesmas mensagens de hoje) e chama `syncAccount`. O controller mantém: consumo do state primeiro, os 3 `catch` e os redirects — resposta HTTP é papel dele. | Mover o try/catch e o redirect para o service: service devolvendo RedirectResponse inverte as camadas. |
| D6 | RF-12: exceção nova `AnalyticsApiException extends BaseException` (caso novo em `AnalyticsError`, p.ex. `UNEXPECTED_API_RESPONSE`), **de propósito fora da hierarquia de `AnalyticsException`**: `AnalyticsService::callProvider` relança `AnalyticsException` ao cliente e trata o resto como inesperado (log com `getMessage()` + 502 `providerUnavailable`) — assim log e contrato ficam byte a byte iguais aos do `RuntimeException` atual. Mensagem idêntica à de hoje (Q4). | Caso novo lançado como `AnalyticsException`: passaria pelo `catch (AnalyticsException)` e chegaria ao cliente com código/mensagem novos — contrato mudaria. |
| D7 | RF-13: `AbstractAnalyticsProvider` com `client(string $token)` concreto (withToken/acceptJson/timeout 20 — Instagram sobrescreve para query param), `get()` concreto e `ensureSuccessful()` template: `isSuccessful(Response): bool` (default `successful()`; TikTok sobrescreve para conferir `error.code`), `isReauthError(Response): bool` (abstrato — cada rede mantém sua detecção) e `platform(): Platform` (abstrato). Constantes e métricas específicas ficam em cada provider. | Mover `dashboard()`/`recentContents()` para a base com template method completo: as redes divergem demais (TikTok sem série diária, YouTube com 2 APIs) — generalização que ninguém pediu. |
| D8 | RF-14: trait `app/Jobs/Concerns/ChecksPlatformPostStatus` (nome final a gosto do dev) com `$tries`, `backoff()`, `retryWithBackoff()` e `failed()`; abstratos: `markFailed(string $message): void` (corpo continua por job — o do TikTok confere estado final, o do Instagram não) e `timeoutException(): BaseException` (fallback do `failed()`: `InstagramException::publishTimeout()` / `TikTokException::publishTimeout()`). A mensagem de log do `retryWithBackoff` fica genérica por propriedade/método para não alterar os textos atuais. | Classe base abstrata de job: trait basta e não força hierarquia; unificar os corpos de `markFailed`: mudaria comportamento (checagem de estado final só existe no TikTok). |

## Modelo de dados

Nenhuma migration. Nenhum campo novo.

## Arquivos

| Ação | Arquivo |
|------|---------|
| edita (F1) | `BaseController`, `WorkspaceController`, `UserController`, `RoleController`, `AccessController`, `ScheduledPostController`, `ContentPipelineController`, `NotificationController`, `ProfileController`, `WorkspaceAvatarController`, `Admin/LandingSettingsController`, `NotificationPreferenceController`, `Billing/Admin/PlanController`, `Billing/Admin/PriceController`, `Billing/Tenant/SubscriptionController`, `Billing/Tenant/PaymentMethodController`, `AuthController`, `AuthService` |
| novo (F1) | `app/Http/Requests/Auth/ConfirmEmailRequest.php`, `app/Http/Requests/StoreRoleRequest.php`, `app/Http/Requests/UpdateRoleRequest.php` (Q2) |
| edita (F2) | `SocialAccountController`, `SocialAccountService`, `WorkspaceController`, `WorkspaceService`, `ContentPipelineController`, `ContentPipelineService`, `AccessController`, `AccessService`, `Billing/Admin/Plan*`, `Billing/Admin/Price*` + services de billing, `Billing/Tenant/SubscriptionController` + service de checkout |
| novo (F2) | caso novo em `WorkspaceError` + factory em `WorkspaceException` (D4) |
| edita (F3) | `TikTokAnalyticsProvider`, `InstagramAnalyticsProvider`, `YouTubeAnalyticsProvider`, `CheckInstagramContainerJob`, `CheckTikTokPostStatusJob`, `YouTubeService`, `InstagramService`, `TikTokService`, `PublishPostJob`, `Storage/StorageService` |
| novo (F3) | `app/Services/Analytics/AbstractAnalyticsProvider.php`, `app/Jobs/Concerns/ChecksPlatformPostStatus.php`, `app/Exceptions/AnalyticsApiException.php` + caso em `AnalyticsError` |
| novo (testes) | `tests/Feature/Users/AccessUpdateCharacterizationTest.php` (Q1), `tests/Feature/SocialAccounts/OAuthCallbackUnexpectedErrorTest.php`, `tests/Feature/Analytics/AnalyticsProviderErrorTest.php` (502 TikTok/Instagram) |

## Efeitos colaterais

Nenhum novo. Observers, events, jobs e notifications existentes não mudam de gatilho; a trait D8 não altera fila (`onQueue` fica no construtor de cada job) nem `afterCommit`.

## Testes

- Cobertura existente que trava o comportamento: `WorkspaceCrudTest` (403/404 do `authorizeInWorkspace`), `RoleCrudTest` (20 casos), `UserTest`, `OAuthStateTest` + `OAuthProvidersTest` + `FechamentoPublicacaoTest:329` (callback), `Pipeline/*`, `Billing/*`, `Analytics/*` (YouTube já cobre o 502 genérico em `YouTubeAnalyticsTest:193`), `CheckTikTokPostStatusJobTest`, `InstagramContainerJobTest`, `TikTokEndToEndTest`, `InstagramWarmupTest`, `ScheduledPosts/*`, `Security/*`.
- Caracterização nova (antes de tocar a área): `PATCH /accesses/{id}` (comportamento atual de sucesso, Q1), ramo `Throwable` do callback (mensagem genérica + log só com a classe), 502 genérico dos providers TikTok e Instagram.
- Rodar: `docker exec pandypost-php php artisan test` (suíte inteira após cada tarefa; sem TTY, nunca alvos `make -it`).

## Ordem

Caracterização (0.x) → Fase 1 do menor para o maior alcance (Auth → assinaturas → base → overrides → Role) → Fase 2 (billing → pipeline/access → workspace → callback) → Fase 3 (caracterização 3.0 → literais → exceção dos providers → classe base analytics → trait dos jobs).

## Riscos

- **RF-1 muda a espinha de todos os CRUDs.** Mitigação: nenhuma rota viva usa o `store` cru da base; as 2 do `update` cru ficam explícitas (D3); suíte inteira após a tarefa.
- **Callback OAuth é área crítica** com mensagens casadas com o front. Mitigação: textos ficam literais iguais (testes já asseram `?error=` urlencoded); caracterização do único ramo sem teste antes.
- **`ensureSuccessful` por rede tem semântica própria** (TikTok 200-com-erro; Instagram códigos 200-299; YouTube cota). Mitigação: a detecção fica em métodos por provider (D7), a base só ordena os passos; `Analytics/*` cobre reauth das 3 redes.
- **`failed()`/`markFailed` dos jobs decidem estado final de post.** Mitigação: corpos de `markFailed` intactos (D8); `CheckTikTokPostStatusJobTest` e `InstagramContainerJobTest` cobrem timeout, estado final e warmup.
- **Enum literal → `->value`**: risco de trocar valor sem perceber. Mitigação: tarefa única mecânica, grep de conferência antes/depois (`grep -rn "'processing'\|'published'\|'failed'" app/Services app/Jobs`), suíte inteira.
- **FormRequests novos de Role (Q2)** podem recusar payload que hoje passa (500). Mitigação: regras mínimas, divergência registrada, `RoleCrudTest` intacto.
