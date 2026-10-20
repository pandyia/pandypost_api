# 013 — Refatoração dos models (`app/Models`)

## Contexto

Auditoria dos 22 models, de `BelongsToWorkspace`/`HasExpirableToken` e dos 4 observers ligados a eles contra o CLAUDE.md. A base é saudável (tenant, `$hidden`, enums de erro, `$with` e Octane sem achados graves). O que sobrou é código morto, duplicação mecânica de `uuid` e três `\Exception` soltas em `SocialAccount`. Os problemas de segurança e bugs encontrados estão em **Apontamentos** e não viram tarefa.

Áreas críticas tocadas (CLAUDE.md pede spec reversa antes de mexer): multi-tenancy (`BelongsToWorkspace`), OAuth/token social (`SocialAccount`) e auth (`User::loginExpiration`). A spec reversa está nos requisitos "não muda" de RF-5, RF-8 e RF-9, e a caracterização vem primeiro (tasks 1.x).

## Escopo fechado

`app/Models/*.php`, `app/Models/Traits/*.php`, `app/Observers/*.php`. Chamadores só são editados onde a assinatura muda (RF-2, RF-9) ou para reaproveitar um scope existente (RF-2). Nada em `routes/`, `database/`, `tests/` existentes.

## Requisitos

- **RF-1 (código morto).** O comportamento não muda; `app/Models/Tenant.php` (11 linhas, nenhuma referência), `Role::hasPermission` (`Role.php:67`), `ContentPipeline::isScheduled` (`ContentPipeline.php:71`), `ScheduledPost::scopeDue` (`ScheduledPost.php:112`), as 3 linhas de `match` comentadas em `ScheduledPost::getPlatformPostUrl` (`:158-160`) e `RoleObserver::restored` + a guarda `wasChanged('deleted_at')` (`RoleObserver.php:25,34`; `Role` não tem `SoftDeletes` nem coluna `deleted_at`) deixam de existir.
- **RF-2 (reuso ignorado).** `WarmupPostsCommand.php:21` e `SocialAccountService.php:43` repetem `where('status','pending')`; passam a usar `ScheduledPost::pending()` (`ScheduledPost.php:107`), que hoje não tem nenhum chamador.
- **RF-3 (override redundante).** `Notification.php:10-13` repete exatamente os `$casts` de `DatabaseNotification`; o override sai e o comentário do `id` fica.
- **RF-4 (query redundante).** `Plan::gatewayPriceIds` (`Plan.php:77-85`) faz 3 queries onde 2 bastam (relê os mesmos preços só para pegar `gateway_price_id`). O retorno não muda.
- **RF-5 (aninhamento).** `BelongsToWorkspace::bootBelongsToWorkspace` (`BelongsToWorkspace.php:11-26`, 4 níveis) passa a usar early return e dispensa o `?? null`. O filtro de tenant, inclusive o comportamento sem `currentAccess` (fail-open, ver Apontamentos), não muda.
- **RF-6 (import).** `User.php:8` `use DB;` (alias global) vira `Illuminate\Support\Facades\DB`. Some junto com RF-9 se `DB::transaction` sair.
- **RF-7 (`$hidden`).** `Plan`, `Price` e `PriceHistory` (têm `uuid` como route key) passam a ter `$hidden = ['id']`, como os demais models. Nenhum Resource muda, porque todos já mapeiam `uuid` explicitamente.
- **RF-8 (erro de domínio).** Os 5 `throw new \Exception` de `SocialAccount` (`:144,165,174,196,217`) viram `SocialAccountException` com factory estática e casos novos em `SocialAccountError`. O texto das mensagens, inclusive o `body()` de terceiros, **não muda** nesta refatoração (ver Q2). Os chamadores capturam `Throwable` (`AnalyticsService.php:214`, `InstagramService.php:50`, `PublishPostJob::failed`), então o fluxo é o mesmo.
- **RF-9 (nome esconde intenção).** `User::loginExpiration(bool)` (`User.php:274`) grava `remember_me` dentro de uma `DB::transaction` de uma única query e devolve uma data. Passa a ser uma consulta pura (`loginExpiration()` lê `remember_me`) e `AuthService.php:65` faz o `update` explícito. As datas de expiração e o `remember_me` gravado não mudam.
- **RF-10 (duplicação real, depende de Q1).** `static::creating(... uuid ?: Str::uuid())` + `getRouteKeyName(): 'uuid'` aparecem idênticos em 11 models (`ContentPipeline, Invite, Payment, Plan, PriceHistory, Price, Role, ScheduledPost, SocialAccount, User, Workspace`). Passam a vir de uma trait `HasUuid`. `Audit` (uuid é a chave primária) fica de fora.

## Critérios de aceite

- Suíte do escopo igual ou melhor (`docker exec pandypost-php php artisan test`), nenhum teste existente alterado ou removido. Só entram testes de caracterização novos (tasks 1.x).
- Contrato de API idêntico (nenhum Resource muda).
- `docker exec pandypost-php ./vendor/bin/pint --dirty` limpo.
- Balanço esperado: cerca de 90 linhas a menos, 1 query a menos por chamada de `gatewayPriceIds`, 5 `\Exception` a menos.

## Fora de escopo

Strategy+Factory para refresh/revoke de token e URL do post (Q3); cast de enum em `SocialAccount.platform` e `ScheduledPost.status` (Q4); tipar retorno das relações e `$query` dos scopes (Q5); tudo em Apontamentos.

## Apontamentos (não viram tarefa)

Falhas de segurança:
- **A1 — mass assignment em `$fillable`.** `User.php:78-91` aceita `uuid`, `is_super_admin`, `access_id`; `Role.php:27` aceita `uuid`, `workspace_id`; `Workspace.php:20` aceita `is_personal_team`, `uuid`; `ScheduledPost.php:32-52`, `SocialAccount.php:36-48`, `ContentPipeline.php:23-33`, `Invite.php:25-32`, `Access.php:19-23` aceitam `user_id`/`workspace_id`/`uuid`. O CLAUDE.md proíbe esses campos vindos do request. A proteção hoje está só nos FormRequests (VULN-01/05 ✅) e é frágil. Tirá-los do `$fillable` exige `forceFill`/`forceCreate` nos services e na seed: é spec de segurança, não de refatoração.
- **A2 — `$request->all()` no Eloquent** (fora do escopo): `RoleController.php:86,121`, `BaseController.php:54`.
- **A3 — fail-open sem `currentAccess`.** `BelongsToWorkspace.php:14` não filtra nada se o usuário não tem `access_id` (VULN-09, já registrado na spec 002).
- **A4 — `Invite::resolveRouteBinding` (`Invite.php:54-59`)** remove o scope de tenant para qualquer UUID. Hoje `authorizeInvite` no service protege, mas a segurança depende de cada chamador lembrar disso.
- **A5 — vazamento de terceiro.** `SocialAccount.php:165,196,217` põem `$response->body()` na mensagem; ela vai para `scheduled_posts.error_message` (exposto por `ScheduledPostResource.php:31`) e para o log (`AnalyticsService.php:214`). Mesma família de VULN-RETRY-02.
- **A6 — id interno exposto.** `UserResource.php:28` devolve `access_id` (PK de `accesses`), contra "id fica em `$hidden`". `User` também serializa `access_id` em `accessWithWorkspace` (`User.php:306`).
- **A7 — token curto.** `HasExpirableToken::generateToken` (`:21`) usa `Str::random(10)` para reset de senha e verificação de e-mail. Confirmar throttling nas rotas.
- **A8 — `Access` sem `BelongsToWorkspace`** (`Access.php`) com `workspace_id` direto: a isolação depende de cada query chamar `forWorkspace` (VULN-02 corrigido só no `AccessService`). Aplicar o trait cria dependência circular com `User::currentAccess`, então pede decisão.

Bugs e débito:
- **B1 — N+1.** `ScheduledPost::totalPostsForAccount` / `failedPostsForAccount` (`:145-153`) fazem 2 `count()` por post; `ScheduledPostResource.php:18-19` as chama por item da lista. A correção é `withCount` no service/Resource.
- **B2 —** `User::hasValidSubscriptionForPublishing` (`User.php:165`) retorna sempre `true` (stub da fase 2). Mantido; decidir quando reativar.
- **B3 —** `SocialAccountService.php:46` faz chamada HTTP de revogação dentro de `DB::transaction`.
- **B4 —** `User::accessWithWorkspace` (`User.php:300`) emite token e monta array cru; o nome não diz que cria token.

## Questões em aberto

1. **Q1 — trait `HasUuid` (RF-10).** O CLAUDE.md proíbe criar abstração nova sem pedir. É a maior redução (cerca de 55 linhas líquidas, 11 models) e segue o padrão das traits que já existem. Aprova? Se não, a task 3.1 é descartada.
2. **Q2 — mensagens de `SocialAccount` (RF-8).** Manter o texto com `body()` (comportamento idêntico, vazamento A5 continua) ou trocar por mensagem fixa e mandar o `body` só para o contexto? A segunda opção muda o `error_message` gravado, então seria spec de segurança.
3. **Q3 — Strategy/Factory.** `SocialAccount::getValidToken/revokeToken` (`:112,126`) e `ScheduledPost::getPlatformPostUrl` (`:157`) fazem `match` por plataforma no model, contra o item 6 do checklist. Mover para uma Strategy por plataforma cria interfaces e classes novas (e as refresh de YouTube/TikTok/Instagram, hoje 3 métodos de ~20 linhas, reaproveitariam a mesma Strategy). Quer uma spec própria?
4. **Q4 — casts.** `SocialAccount.platform` e `ScheduledPost.status` não têm cast de enum, daí `Platform::X->value` e `ScheduledPostStatus::X->value` em 8 comparações. Adicionar cast muda o tipo lido em Resources, services, jobs e na concatenação de `getAuditRepresentation`; precisa de varredura dos consumidores. Quer em outra spec?
5. **Q5 — tipos.** Faltam tipos de retorno em `Workspace::accesses/roles` (`:108,113`), `Audit::workspace` (`:26`) e `$query` nos scopes (`Access`, `Invite`, `Plan`, `Price`, `SocialAccountMetricSnapshot`). Pelo critério "sem gosto pessoal" não entrou; entra se você quiser a consistência.
