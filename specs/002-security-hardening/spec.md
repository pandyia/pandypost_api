# Spec 002 — Endurecimento de segurança (auditoria 2026-10-14)

> **Origem:** auditoria de segurança feita sobre o código de `develop` (commit `f9b6cc7`). Esta spec é, ao mesmo tempo, a **spec reversa** do comportamento atual nas áreas críticas (permissões/workspace, auth, OAuth, billing) e a proposta de correção.
>
> **Evidência:** cada vulnerabilidade tem um teste em `tests/Feature/Security/AuthorizationVulnerabilitiesTest.php` (ou no arquivo do domínio) que descreve o comportamento **seguro** e está marcado com `->skip('VULN-NN: ...')`. Os testes foram executados sem o `skip` e **falharam pelo motivo descrito** antes de serem marcados. Ao corrigir, remova o `skip`.

**Status:** aprovada para execução (fase 1 — ver `plan.md`) · **Data:** 2026-10-14 · **Revisão:** 2026-10-14 (decisões D1–D2)

## Decisões

- **D1 (Q1, ADM-01):** o catálogo de planos/preços (`/api/admin/**`) é **exclusivo do super admin** (criadores/funcionários do Pandypost). Nenhum usuário de workspace, nem com `billing.manage`, tem acesso. `billing.view`/`billing.manage` continuam valendo só para a assinatura/cartões/pagamentos **do próprio workspace** (`/api/billing/**`).
- **D2 (Q5, VULN-04):** pipeline **fora do escopo por ora**. O achado fica registrado; os testes seguem com `skip`.
- **D3 (Q8, BUG-01):** as rotas afetadas (`GET/PATCH /users/{uuid}`, `GET /workspaces/{uuid}`, `/accesses`) não são usadas pelo `pandypost_ui` → **não mexer** nelas nesta fase (nem consertar nem remover). Nenhuma rota é removida.
- **D4 (Q6):** campos proibidos no request são ignorados (sem 422).

---

## Contexto

O Pandypost é multi-tenant por Workspace. O isolamento depende de três mecanismos: o global scope `BelongsToWorkspace`, o scope `member` do `Workspace` e o middleware `permission:grupo.acao`. A auditoria encontrou rotas e models que ficam fora desses mecanismos, além de pontos de mass assignment via `BaseController` (`$request->all()`).

## Achados

✅ = corrigido na fase 1 (2026-10-14). Severidade: **Crítica** (tomada de conta/tenant ou vazamento entre tenants) · **Alta** · **Média** · **Baixa**.

| ID | Sev. | Achado | Onde | Teste |
|----|------|--------|------|-------|
| VULN-01 ✅ | Crítica | `POST /api/users` cria usuário com `is_super_admin=true` (e `access_id` arbitrário). `BaseController::store` repassa `$request->all()` e `User::$fillable` inclui `is_super_admin`/`access_id`. Quem tem `users.create` — todo dono de workspace — vira super admin, que ignora todas as permissões. | `BaseController::store`, `User::$fillable` | `VULN-01` |
| VULN-02 ✅ | Crítica | `Access` não usa `BelongsToWorkspace`. `GET /api/accesses` lista acessos (com user, role e workspace) de **todos** os tenants; `POST /api/accesses` aceita `workspace_id`/`role_id` livres → o atacante se coloca como admin em qualquer workspace. | `Access`, `AccessService`, `AccessController` | `VULN-02` (2 testes) |
| VULN-03 ✅ | Crítica | `ScheduledPost` não tem escopo de workspace. `GET /api/scheduled-posts` retorna os posts (título, legenda, mídia, payload) de todos os tenants. | `ScheduledPost`, `BaseController::index` | `VULN-03` |
| ADM-01 ✅ | Crítica | As rotas `/api/admin/plans/**` (catálogo global de planos/preços, com sync no Stripe) exigem só `billing.view`/`billing.manage` — as mesmas permissões que todo dono recebe no workspace pessoal. A spec 001 fala em "administrador **do sistema**"; confirmado pela D1. | `routes/api.php` (prefixo `admin`), `Billing/Admin/*Controller` | `ADM-01` (+ regressão: super admin consegue gerenciar) |
| VULN-04 | Alta (adiado — D2) | `PATCH`, `DELETE` e `PATCH .../restore` de `/api/pipeline/{uuid}` não têm permissão: qualquer membro do workspace edita, remove e restaura cards. | `ContentPipelineController::$permissionMethods` | `VULN-04` (2 testes) |
| VULN-05 ✅ | Média | `POST /api/workspaces` aceita `is_personal_team`, `role_name` e `permissions` do corpo. Um workspace "pessoal" falso não pode ser apagado e confunde o fallback de `resolveCurrentAccess()`. `PATCH /api/workspaces/{uuid}` também repassa `$request->all()` (inclui `uuid` e `is_personal_team`). | `WorkspaceService::store/createAllAccess`, `WorkspaceController::update` | `VULN-05` |
| OAUTH-01 | Média | O `state` do OAuth é só `encrypt(workspace_uuid)`: sem nonce, sem expiração e sem vínculo com o usuário. Um `state` vazado ou gerado por um atacante pode ser reutilizado indefinidamente (OAuth CSRF: a vítima autoriza o próprio canal e ele é ligado ao workspace do atacante). No callback, a conta fica com o `user_id` do **primeiro** membro do workspace, não de quem conectou. | `*OAuthProvider::getRedirectUrl`, `SocialAccountController::callback`, `TikTokOAuthProvider::syncAccount` | **Corrigido na spec 017** (`OAuthStateService`: nonce de uso único, TTL 10 min, vínculo ao iniciador; conta grava o `user_id` de quem conectou). `OAuthStateTest` |
| OAUTH-02 | Média | `TikTokOAuthProvider` é um stub ativo em qualquer ambiente: `GET /social-accounts/tiktok/auth` cria uma conta TikTok falsa (`mock_tiktok_code`) sem gate por `app.env`. | `TikTokOAuthProvider` | — |
| VULN-06 | Média | `/api/analytics/{socialAccount}/*` não exige permissão e devolve `$e->getMessage()` cru no 500 (pode vazar detalhes da API do Google). | `AnalyticsController` | — (Q3) |
| VULN-09 | Baixa | Sem `currentAccess` (`access_id` nulo) o global scope de `Role` falha aberto e `User::hasPermission()` (usado por `CheckPermission`) vale para qualquer workspace do usuário. Achado na auditoria da spec 009 (`notifications.*`); vale para todas as rotas `permission:*`. | `BelongsToWorkspace`, `User::hasPermission` | `NotificationPermissionSecurityTest` (skip VULN-09); **parcial na spec 019**: o escopo de tenant agora falha fechado sem `currentAccess` (VULN-TEN-02); `User::hasPermission()` ainda vale para qualquer workspace do usuário e o skip permanece. |
| VULN-RETRY-01 ✅ | Média | `POST /scheduled-posts/{uuid}/retry` lê o status e só depois faz `update` (sem lock nem UPDATE condicional): dois cliques simultâneos despacham dois `PublishPostJob` e publicam duplicado na rede social. Achado na auditoria da spec 012. | `ScheduledPostService::retry` | `PostRetrySecurityTest` (ativo; corrigido com `lockForUpdate` e nova checagem do status dentro da transação) |
| VULN-RETRY-02 ✅ | Média | `error_message` recebe `$exception->getMessage()` / `json_encode(resposta)` crus de terceiros (Google/Meta/TikTok, possivelmente com URL contendo `access_token`) e o `ScheduledPostResource` o expõe a quem tem `posts.view`. Spec 012 amplia a exposição (posts falhos ficam 5 dias). | `PublishPostJob::failed`, `InstagramService`, `CheckInstagramContainerJob`, `CheckTikTokPostStatusJob`, `ScheduledPostResource` | `PostRetrySecurityTest` (skip VULN-RETRY-02); **corrigido na spec 019** (skip removido, teste ativo). |
| VULN-TT-01 | Baixa | `upload_url` devolvida pelo TikTok não é validada (esquema/host); `uploadChunk` faz PUT do vídeo inteiro nela (SSRF/exfiltração se a resposta for adulterada). Hardening. | `TikTokApi::initUpload/uploadChunk` | `TikTokAuditTest` (skip VULN-TT-01) |
| VULN-TT-02 | Média | `PublishPostJob::failed` grava `getMessage()` cru de exceção que não é de domínio; `ConnectionException` no PUT do chunk traz a URL assinada do TikTok (`x-signature`) para `error_message`/log/`ScheduledPostResource`. Extensão do VULN-RETRY-02. | `PublishPostJob::failed`, `TikTokApi`, `TikTokService::sendChunks` | `TikTokAuditTest` (skip VULN-TT-02) |
| VULN-TT-03 | Média | Ocorrência do OAUTH-01 no TikTok: `state` reutilizável, sem nonce/expiração/usuário; reconectar pode trocar o dono (`user_id`) e quebrar agendamentos. Corrige junto do OAUTH-01. | `TikTokOAuthProvider`, `SocialAccountController::callback` | `TikTokAuditTest` | **Corrigido na spec 017** (skip removido) |
| VULN-TT-04 | Baixa | `creator-info` não captura `ConnectionException`: 500 em vez de erro de domínio tratado. | `TikTokCreatorInfoController`, `TikTokService::creatorInfo` | — |
| VULN-RETRY-03 ✅ | Baixa | `permalink` da Graph API é gravado em `platform_post_url` e devolvido sem validar esquema/host (`javascript:` etc.). Hardening. | `InstagramService::fetchPermalink`, `CheckInstagramContainerJob::fetchPermalink` | `PostRetrySecurityTest` (skip VULN-RETRY-03); **corrigido na spec 019** (skip removido, teste ativo). |
| VULN-SCHED-01 ✅ | Alta | `GET /api/social-accounts/schedules` não está em `SocialAccountController::$permissionMethods`: a rota fica sem `CheckPermission` e qualquer membro (mesmo sem `social_accounts.view`/`posts.view`) lista contas e posts do workspace (título, legenda, `media_url` assinada, payload, `error_message`). Auditoria final das specs 011/014-018. | `SocialAccountController::schedules`, `routes/api.php` | `FinalAuditSocialPublishTest` (skip VULN-SCHED-01); **corrigido na spec 019** (skip removido, teste ativo). |
| VULN-TEN-02 ✅ | Crítica (pré-condição: `access_id` nulo) | Sem `currentAccess` o `BelongsToWorkspace` falha aberto e o relation fica em cache negativo mesmo após `resolveCurrentAccess()`: `POST /scheduled-posts` com o uuid de conta social de **outro tenant** cria o post nela (mídia do próprio workspace do atacante). Extensão do TEN-01/VULN-09 para a esteira de publicação. | `BelongsToWorkspace`, `User::resolveCurrentAccess`, `ScheduledPostService::ensureValidSocialAccount` | `FinalAuditSocialPublishTest` (skip VULN-TEN-02); **corrigido na spec 019** (skip removido, teste ativo). |
| VULN-POST-01 ✅ | Média | `ScheduledPostService::cancel` usa `deleteMany()` e apaga a mídia compartilhada (agendamento em várias contas reaproveita o `media_path`): os posts irmãos pendentes falham. Também reprovado em `QaConjuntoPublicacaoTest`. | `ScheduledPostService::cancel` | `FinalAuditSocialPublishTest` (skip VULN-POST-01); **corrigido na spec 019** (skip removido, teste ativo). |
| VULN-OAUTH-04 ✅ | Média | O callback público faz `redirect(?error=urlencode($e->getMessage()))` para qualquer `\Exception`: `QueryException` traz o SQL com `access_token`/`refresh_token` (bindings) e `ConnectionException` a URL; vão para a URL do front (histórico, Referer, logs de proxy). | `SocialAccountController::callback` | `FinalAuditSocialPublishTest` (skip VULN-OAUTH-04); **corrigido na spec 019** (skip removido, teste ativo). |
| VULN-OAUTH-05 ✅ | Baixa | O `state` não leva a plataforma: state emitido para `google` vale em `/instagram/callback`. | `OAuthStateService`, `SocialAccountController::callback` | `FinalAuditSocialPublishTest` (skip VULN-OAUTH-05); **corrigido na spec 019** (skip removido, teste ativo). |
| VULN-OAUTH-06 ✅ | Baixa | O callback só confere se o iniciador ainda tem `Access` no workspace, não se ainda tem `social_accounts.connect` (papel pode ter mudado nos 10 min do state). | `SocialAccountController::callback` | `FinalAuditSocialPublishTest` (skip VULN-OAUTH-06); **corrigido na spec 019** (skip removido, teste ativo). |
| VULN-OAUTH-07 ✅ | Baixa | Callback público sem `throttle` (só grupo `api`). | `routes/api.php` | `FinalAuditSocialPublishTest` (skip VULN-OAUTH-07); **corrigido na spec 019** (skip removido, teste ativo). |
| VULN-RETRY-03b | Baixa | `InstagramApi::permalink` tem retorno `string`: permalink array/objeto vira `TypeError` depois da publicação (só `ScheduledPostException` é capturada); o post fica em `processing` até o job repetir. | `InstagramApi::permalink`, `InstagramService::permalinkOrNull` | `FinalAuditSocialPublishTest` (skip VULN-RETRY-03b) |
| VULN-RETRY-04 ✅ | Baixa | `PublishPostJob::failed` (e `InstagramService::processContainer`) logam `getMessage()` cru de exceções que não são de domínio (pode trazer URL/token). `error_message` já está saneado (VULN-RETRY-02 corrigido nessa via). | `PublishPostJob::failed` | `FinalAuditSocialPublishTest` (skip VULN-RETRY-04); **corrigido na spec 019** (skip removido, teste ativo). |
| VULN-SA-01 | Baixa/Média | `access_token`/`refresh_token` ficam em texto puro no banco (sem cast `encrypted`). | `SocialAccount::$casts` | `FinalAuditSocialPublishTest` (skip VULN-SA-01); **Deferido (D3, spec 019)**: criptografar quebraria as contas já conectadas sem migração de dados planejada; teste segue em skip. |
| VULN-UP-01 | Média | A presigned PUT assina só o header `host` (`X-Amz-SignedHeaders=host`): content-type e tamanho não são impostos; `max_size` é informativo; `directory` não casa com `content_type`. | `StorageService::presignedPut`, `GenerateUploadUrlRequest` | `FinalAuditSocialPublishTest` (skip VULN-UP-01); **Deferido (D3, spec 019)**: mudaria o contrato de upload com o front (presigned POST/Content-Length assinado); teste segue em skip. |
| BUG-01 | Média | Dois defeitos nos métodos genéricos do `BaseController`, presentes desde o commit inicial (`87b9649`). Ver detalhe abaixo. | `BaseController::show/update`, `BaseService::findById/update` | `BUG-01` (3 testes) + `ContentPipelineTest` |
| AUTH-01 | Baixa | Login diferencia "usuário não encontrado" (404) de "senha inválida" (401) → enumeração de emails. Limite de 55 tentativas/min por IP. (O `forgot-password` já não revela — coberto em `TokenSecurityTest`.) | `AuthService::login`, `routes/api.php` | — (Q4; `AuthenticationTest` afirma o 404 atual) |
| AUTH-02 | Baixa | Tokens de verificação e de reset são `Str::random(10)` e ficam **em texto puro** no banco; vazamento do banco permite redefinir senhas pendentes. | `HasExpirableToken`, `ResetPassword` | — |
| TEN-01 | Baixa | `BelongsToWorkspace` não filtra nada quando o usuário autenticado não tem `currentAccess` (falha aberta). Hoje mitigado porque quase todo usuário verificado tem workspace pessoal, mas um usuário sem nenhum access enxergaria todos os registros. Idem `hasPermission()`: só é restrito ao workspace corrente porque o `whereHas('role.permissions')` herda o scope de `Role` (proteção incidental — coberta pelo teste de regressão). | `BelongsToWorkspace`, `User::hasPermission` | regressão ativa |
| DOS-01 | Baixa | `per_page` padrão `-1` devolve todos os registros sem limite; `per_page` não numérico gera 500 (`TypeError`). | `BaseService::paginate`, `DynamicFilter` | — |

### Detalhe do BUG-01

**(a) `show` busca uuid na coluna `id`.** As rotas usam `uuid` (`/users/{uuid}`), mas o `BaseController::show` chama `BaseService::findById($id)` → `findOrFail($id)`, que procura na coluna `id` (inteiro).
- No SQLite dos testes: nenhum registro → **404**.
- No **PostgreSQL** (dev/produção): `invalid input syntax for type bigint` → **500** (confirmado no banco de dev).
- Rotas afetadas (sem override de `show`): `GET /api/users/{uuid}`, `GET /api/workspaces/{uuid}`, `GET /api/pipeline/{uuid}`. Não afetadas: `accesses/{id}` e `permissions/{id}` (usam id numérico), `roles`, `admin/plans`, `billing/payments` (têm `show` próprio por uuid).

**(b) `update` chama o service com os argumentos trocados.** O `BaseController::update` faz `$this->service->update($request->all(), $id)`, mas a assinatura é `BaseService::update(object $entity, array $data)` → `TypeError` → **500** sempre.
- Rotas afetadas (sem override de `update`): `PATCH /api/users/{uuid}` e `PATCH /api/accesses/{id}`. As demais têm `update` próprio.
- Efeito colateral positivo: como a rota sempre quebra, hoje ela **não** permite mass assignment de `is_super_admin` via update. Ao consertar, RF-1 precisa vir junto, senão o conserto abre a mesma falha do VULN-01 no update.

**Provável origem:** o `BaseService` passou a trabalhar com entidade/uuid (`update(object, array)`, `updateByUuid`, `findByUuid`), mas o `BaseController` continuou com o contrato antigo (id + array). Os controllers que sobrescrevem `show`/`update` (Role, Workspace, Pipeline, Billing) contornam o problema; os que herdam (User, Access) quebram.

**Decisão necessária (Q8):** as rotas `GET/PATCH /api/users/{uuid}` e `GET /api/workspaces/{uuid}` são usadas pelo frontend? Se não, uma alternativa é remover a rota (`->except([...])`) em vez de consertar.

## Escopo

Corrigir VULN-01, VULN-02, VULN-03, VULN-05, ADM-01 e BUG-01 (conforme Q8) e, conforme as respostas das questões, OAUTH-01/02, VULN-06 e AUTH-01. VULN-04 adiado (D2). AUTH-02, TEN-01 e DOS-01 entram se aprovados; senão viram débito registrado.

## Requisitos

- **RF-1** Nenhuma rota de escrita repassa `$request->all()` ao Eloquent; todo `store`/`update` usa FormRequest (`validated()`) e campos explícitos. `is_super_admin`, `access_id`, `workspace_id`, `uuid` e `is_personal_team` nunca vêm do request.
- **RF-2** Todo model com dado de tenant é escopado: `Access` e `ScheduledPost` passam a ser filtrados pelo workspace corrente (direto ou via relação).
- **RF-3** Criação/edição de `Access` só é possível dentro do workspace corrente e com um `Role` desse workspace (ou a rota genérica de accesses é removida — ver Q2).
- **RF-4** *(adiado — D2)* Toda ação do `ContentPipelineController` exige permissão.
- **RF-5** `show`/`update` herdados do `BaseController` resolvem por `uuid` e com a assinatura correta do `BaseService`.
- **RF-6** As rotas `/api/admin/**` exigem super admin (D1). Usuário comum recebe 403, mesmo com `billing.manage`.
- **RF-7** (se aprovado) O `state` do OAuth contém workspace, usuário, nonce e expiração; o callback rejeita `state` expirado ou reutilizado e grava o `user_id` de quem iniciou a conexão.
- **RF-8** (se aprovado) O stub do TikTok só responde em `local`/`testing`.

## Contrato de API

Sem mudança de rotas. Mudanças de comportamento:

- Campos proibidos (RF-1) passam a ser ignorados (ou 422 — ver Q6) em vez de gravados.
- `GET /api/accesses` e `GET /api/scheduled-posts` passam a retornar só dados do workspace corrente.
- `GET /api/users/{uuid}`, `GET /api/workspaces/{uuid}` e `GET /api/pipeline/{uuid}` passam a retornar 200 (ou a rota é removida — Q8); `PATCH /api/users/{uuid}` e `PATCH /api/accesses/{id}` deixam de dar 500.
- `/api/admin/**` retorna 403 para quem não é super admin (D1).

## Critérios de aceite

1. Todos os testes marcados com `->skip('VULN-..')` / `->skip('BUG-01..')` passam sem o `skip`.
2. O teste de regressão "permissão vale só no workspace corrente" continua passando.
3. A suíte inteira continua verde; nenhum teste existente foi enfraquecido. Exceção decidida: `AdminPlanTest` e `AdminPriceTest` passam a autenticar como super admin (D1), e `AuthenticationTest` muda junto com a Q4, se aprovada.
4. Cada rota alterada tem teste de 403 e de isolamento entre workspaces.

## Fora de escopo

- Reescrever o modelo de permissões ou o `BaseController`/`BaseService` além do necessário para RF-1 e RF-5.
- Rate limiting por usuário/conta (além do ajuste de login da Q4).
- Hardening de infraestrutura (Docker, nginx, headers HTTP).

## Questões em aberto

1. ~~**ADM-01:** `/api/admin/plans` exclusivo do super admin?~~ → **Sim (D1).**
2. **VULN-02:** a rota genérica `apiResource('accesses')` tem uso no frontend? Se não, removê-la é a correção mais segura (o vínculo já é gerenciado por convites + `users/{user}/role`).
3. **VULN-06:** qual permissão deve proteger `/api/analytics/*`? (`social_accounts.view`?)
4. **AUTH-01:** podemos unificar a resposta do login para 401 "credenciais inválidas" (quebra o teste atual que espera 404)? E reduzir o limite de login para algo como 5–10/min?
5. ~~**VULN-04:** permissões do pipeline~~ → **Adiado (D2).**
6. **RF-1:** campo proibido no request deve ser **ignorado** silenciosamente ou retornar **422**?
7. ~~**OAUTH-01:** quando a conta social é conectada, ela pertence ao usuário que conectou ou ao workspace todo? (Hoje o agendamento só aceita contas com `user_id` do próprio usuário.)~~ → **A conta pertence ao workspace (Gabriel, 2026-11-05):** quem tem acesso ao workspace e a permissão pode agendar em qualquer conta social dele, independente de quem conectou ou reconectou. Executado na spec 017.
8. **BUG-01:** `GET/PATCH /api/users/{uuid}` e `GET /api/workspaces/{uuid}` são usados pelo frontend? Consertar ou remover as rotas?

## Adendo — AUTH-03 ✅ (2026-10-18)
`PUT/DELETE /api/workspaces/{uuid}` checavam a permissão no workspace corrente e agiam no workspace da URL: o admin do A, só membro do B, renomeava e excluía o B. Corrigido em `WorkspaceController` (`hasPermissionInWorkspace`); testes em `tests/Feature/Workspaces/WorkspacePermissionScopeTest.php`. Detalhes na spec 010.


## Adendo — spec 019 (fechamento da publicação, 2026-11-06)
Corrigidos: VULN-POST-01, VULN-TEN-02, VULN-SCHED-01, VULN-OAUTH-04/05/06/07, VULN-RETRY-03/03b/04 (e o skip obsoleto de VULN-RETRY-02). **Deferidos (D3)**: VULN-SA-01 (tokens em texto puro) e VULN-UP-01 (presigned PUT sem limite de tamanho). Pendência futura (D4): histórico de posts some ao desconectar a conta. Fora de escopo (D5): `consumeQuota`/assinatura no-op.
