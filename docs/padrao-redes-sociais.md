# Padrão de redes sociais do Pandypost

> Guia vivo. Origem: specs 011 (TikTok), 014 (base comum), 015 (Instagram), 016 (YouTube), 017 (conta do workspace + `state` OAuth), 018 (endurecimento) e 019 (fechamento). **Toda rede nova segue este molde** (Facebook, X/Twitter, Kwai, LinkedIn, Pinterest, Threads...). Não invente estrutura nova: reaproveite, e se algo não couber, pare e proponha a mudança em spec.
>
> Produto: concorrente direto do mLabs. O diferencial é **confiabilidade de publicação** (validar antes de agendar, nunca postar duplicado, erro claro em pt-BR, retry só quando faz sentido), **conta do workspace** (equipe agenda sem depender de quem conectou) e **analytics**. Toda decisão numa rede nova deve preservar isso.

## 1. Regras de negócio que valem para qualquer rede

1. **A conta social pertence ao workspace.** Quem tem acesso ao workspace e a permissão (`posts.create`, etc.) agenda em qualquer conta conectada nele, independente de quem conectou ou reconectou. `SocialAccount.user_id` é só "quem conectou por último" (auditoria/notificação), **nunca autorização**. O isolamento vem do global scope `BelongsToWorkspace`, que **falha fechado**: usuário autenticado sem `currentAccess` não enxerga registro de tenant nenhum (sem usuário autenticado, como em jobs e comandos, o escopo não se aplica). **Cancelar e reenviar posts valem para qualquer membro com `posts.delete`/`posts.create` no workspace do post**, não só o autor; outro workspace dá 404. `ensureValidSocialAccount` ainda filtra por `workspace_id` explícito. Conta sem o escopo de publicação (`canPublish()`, hoje o TikTok) dá 422 no agendamento. Mídia compartilhada entre posts (mesmo arquivo em várias redes) só sai do S3 via `deleteIfUnused`, nunca `deleteMany` (cancelar e desconectar conta incluídos).
2. **Validar no agendamento, não na publicação.** Limites e formatos da rede (tipo de mídia, tamanho de legenda, campos obrigatórios) viram 422 em `StoreScheduledPostRequest` + `*PayloadBuilder`, tudo ou nada. Falhar horas depois, na fila, é o pior caso.
3. **`error_message` é texto fixo em pt-BR**, vindo do enum de erro da rede. **Nunca** `getMessage()` de terceiro, JSON/`body` cru, token ou URL assinada em resposta, log, notificação ou `error_message`. Logs levam só HTTP status e código.
4. **Erro permanente não repete.** Token revogado, permissão negada, cota, privacidade inválida: `isPermanent()` → o `PublishPostJob` falha uma vez (`$this->fail()`), sem gastar cota da rede. Transitório (rede fora do ar, 5xx) usa retry da fila.
5. **Nunca publicar em duplicidade.** Timeouts do job acima do tempo real de upload e abaixo do `retry_after` do Redis (config/queue.php); antes de republicar, conferir se a rede já publicou (ver Instagram). `failed()` jamais sobrescreve `published` ou `cancelled`.
6. **Mídia no S3:** só é apagada em sucesso (`ScheduledPost::mediaPaths()` + `StorageService::deleteIfUnused`). Em falha fica para reenvio (spec 012). Falha ao apagar vira warning, nunca derruba/repete o post.
7. **OAuth:** `state` emitido por `OAuthStateService` (payload cifrado `workspace_uuid`, `user_id`, `platform`, `nonce`, `exp`; nonce de uso único via `Cache::pull`, TTL 10 min; `consume($state, $platform)` recusa state de outra rede). O callback só mostra no `?error=` a mensagem de `BaseException` (qualquer outra exceção vira texto genérico e o log leva só a classe), exige `social_accounts.connect` do iniciador no workspace e tem `throttle`. Cache precisa ser compartilhado (Redis).
8. **Segredos fora do repo:** chaves em `.env` (e secrets de produção), documentadas em `.env.example` (só com pedido do dono).

## 2. Anatomia de uma rede (o que existe para TikTok/Instagram/YouTube)

| Peça | Onde | Papel |
|------|------|-------|
| Enum `Platform` | `app/Enums/Platform.php` | `case`, `label()`, `publishScope()` (scope OAuth exigido para publicar; `null` = não exige). **O valor do case é o que vai em `social_accounts.platform` e na fila.** |
| Cliente HTTP | `app/Services/<Rede>/<Rede>Api.php` | **Único** lugar com URLs, versão, headers e `Http::`. Converte falha de conexão/HTTP em exceção de domínio (sem URL/token na mensagem). Implementa `PlatformTokenApiInterface` (`refreshAccountToken`, `revokeAccountToken`). Se a rede tem SDK próprio (YouTube), o `Api` cobre só o que o SDK não cobre. |
| Erros | `app/Enums/Exceptions/<Rede>Error.php` (`ErrorEnumInterface`) + `app/Exceptions/<Rede>Exception.php` | Mapeia código da rede → mensagem pt-BR + HTTP code; factories estáticas; `fromApiCode()`; `isPermanent()`. Código desconhecido → mensagem genérica segura (nunca inventar código da rede sem doc). |
| Service de publicação | `app/Services/<Rede>Service.php` (`SocialMediaServiceInterface::upload`) | Orquestra: valida conta/escopo → obtém token (`getValidToken`) → upload → marca estado. O erro **propaga** como `<Rede>Exception`; quem grava `failed` é o `PublishPostJob::failed()`. |
| Job de conferência (se a rede é assíncrona) | `app/Jobs/Check<Rede>...Job.php` | Redes que processam depois do upload (TikTok `publish_id`, Instagram container) têm job que consulta o estado final, marca `published`/`failed`, limpa o S3 e trata `failed()` por tempo esgotado. Rede síncrona (YouTube) não precisa. |
| Payload builder | `app/Services/Payloads/Builders/<Rede>PayloadBuilder.php` (herda `AbstractPlatformPayloadBuilder`) | Campos específicos da rede, planos no request, movidos para `payload`; valida enums/limites; regras de mídia aceita. |
| OAuth provider | `app/Services/OAuthProviders/<Rede>OAuthProvider.php` (herda `AbstractOAuthProvider`) | `getRedirectUrl` (usa `stateFor($user, '<chave da rede>')`), `syncAccount(User $iniciador, ?string $code, ?Workspace $ws)` grava `scopes`, `username`/`nickname`, `user_id` do iniciador. |
| Analytics | `app/Services/Analytics/<Rede>AnalyticsProvider.php` | `AnalyticsProviderInterface`; snapshots por conta. |
| Factories | `SocialMediaFactory` (`make`, `tokenApi`), `PayloadBuilderFactory`, `OAuthProviderFactory` (chave string, ex.: `google` para YouTube), `AnalyticsProviderFactory` | Um `match` por rede. `match` sem `default` nos de `Platform` para o compilador/teste lembrar de registrar. |
| Config | `config/services.php` (`client_id/key`, `client_secret`, `redirect`) + `.env.example` | Redirect: `{APP_URL}/api/social-accounts/<provider>/callback`. |
| Fila | `config/horizon.php`: um supervisor por rede (`queue` = valor do enum) | `PublishPostJob` usa `onQueue($platform->value)`. Timeout por rede e abaixo do `retry_after`. |
| Permissões | grupo `social_accounts` e `posts` já existentes | Nenhuma permissão nova por rede. |

Dados: `social_accounts` já tem `platform`, `platform_id`, `access_token`, `refresh_token`, `expires_at`, `nickname`, `username`, `avatar`, `scopes`. Coluna nova só com migration aditiva (PostgreSQL **e** SQLite).

## 3. Checklist: adicionar uma rede nova (ex.: Facebook)

Fluxo SDD obrigatório (`CLAUDE.md`): spec → plan → tasks (aprovados) → `tdd` → `dev` → `qa` → `security` → `docs`. Use o agente `social-network` para conduzir e o `architect` se houver refatoração.

0. **Spec `NNN-publicacao-<rede>`** com: API oficial usada e limites (mídia, legenda, cota, rate limit), escopos OAuth, modelo de publicação (síncrono vs. assíncrono com id de processamento), exigências de auditoria/revisão do app, o que o usuário vê em cada erro. **Pergunte ao dono** regras de negócio ambíguas; nunca suponha. Confirme códigos de erro na documentação oficial (não invente).
1. Enum `Platform`: novo `case`, `label()`, `publishScope()`.
2. `config/services.php` + variáveis (peça antes de tocar `.env.example`).
3. `<Rede>Error` + `<Rede>Exception` (com `isPermanent()`), `<Rede>Api` (+ `PlatformTokenApiInterface`).
4. `<Rede>OAuthProvider` (herda `AbstractOAuthProvider`; `stateFor()`; grava `scopes`) e registro em `OAuthProviderFactory`. Redirect URI no painel da rede (dev: ngrok; prod: domínio real).
5. `<Rede>PayloadBuilder` + registro; regras de mídia/limites; teste 422 tudo-ou-nada.
6. `<Rede>Service::upload()` + `SocialMediaFactory::make/tokenApi`. Job de conferência se for assíncrona.
7. Fila/Horizon: supervisor da rede, `timeout` e `retry_after`.
8. `<Rede>AnalyticsProvider` + registro (pode ser spec separada).
9. `publishScope()` correto e `can_publish` na `SocialAccountResource`.
10. Testes Pest (`Http::fake`, nunca API real): caracterização do fluxo feliz, cada erro mapeado, erro permanente sem retry, token expirado com refresh, sem vazamento de token/body/URL (`tests/Feature/Security/`), isolamento entre workspaces, membro que não conectou agenda na conta (403 sem permissão), cancelamento, reenvio, S3 só em sucesso, `scheduled_at` com fuso.
11. Scribe (agente `docs`), `specs/NNN/tasks.md` com Divergências, Pint, suíte completa verde.
12. Front (`pandypost_ui`): `PLATFORMS`, ícone/label, tela de conexão (`/social-accounts/<provider>/auth`), regras de validação espelhadas, tratamento do `error_message` como texto fixo.

## 4. Armadilhas já pagas (não repetir)

- Job despachado dentro de transação: usar `->afterCommit()` (senão o worker pega antes do commit ou de um post com rollback).
- `scheduled_at` ISO com offset deve ser convertido para `America/Sao_Paulo`.
- Token sem `expires_in` o SDK trata como vencido: vale o `expires_at` da conta.
- Job atrasado publicava post **cancelado**: o job só age em `pending`/`processing`.
- Job de warmup publicando container recriado: publicar o id do **job**, não o do post.
- Timeout de job menor que o upload real faz o retry duplicar o vídeo.
- Mensagem de exceção de HTTP client carrega URL com token/assinatura: capturar e relançar exceção de domínio.
- PNG no Instagram é recusado; TikTok/YouTube aceitam só vídeo no escopo atual.
- Perfil/`username` da rede falhando no OAuth não pode derrubar a conexão (log + fallback).
- Cache do `state` precisa ser Redis em produção.
- Permalink/URL devolvida pela rede e gravada para o front (ex.: `platform_post_url`) é validada: string `https` do domínio da rede, senão fica `null`. Upload por URL assinada de terceiro: `Http::withoutRedirecting()`. Log de exceção de terceiros: só classe e código de domínio, nunca `getMessage()`.

## 5. Ideias de diferencial vs. mLabs (backlog, não implementado)

Sugestões para specs futuras, em ordem de valor/risco: pré-validação visual no front espelhando as regras do backend; fila de reenvio automático inteligente (só transitório); calendário com melhor horário por rede (já há `best-times`); aprovação de posts por papel antes de publicar; comentários/inbox unificado; relatórios de analytics comparando redes; reaproveitar um vídeo adaptando a legenda/limites por rede; alerta proativo de token prestes a vencer (`is_stale`). Cada uma exige spec própria.

## 6. Pontos de extensão já preparados para novas redes

- `Platform::publishScope()` aceita `null` (rede sem escopo de publicação).
- `PlatformTokenApiInterface` + `SocialMediaFactory::tokenApi()`: renovação/revogação de token sem tocar em `SocialAccount`.
- `AbstractOAuthProvider` (`stateFor`, `ownerIds`) e `OAuthStateService`: OAuth seguro pronto.
- `AbstractPlatformPayloadBuilder`: base de validação por rede.
- `ErrorEnumInterface` + `BaseException`: erro de domínio padronizado.
- `PublishPostJob`: retry/permanência/fila por plataforma sem mudança de código.
- `ScheduledPost::mediaPaths()` / `StorageService::deleteIfUnused`: ciclo de vida da mídia.
