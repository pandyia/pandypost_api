# Tasks 014 — Base comum da publicação e do OAuth social

> Uma mudança por tarefa, do menor risco e maior valor ao maior risco. Ao fim de cada uma: `docker exec pandypost-php php artisan test` e `./vendor/bin/pint --dirty`. Só começa após aprovação da spec e commit da 011.

## 0. Caracterização (sem tocar em `app/`)
- [x] 0.1 `PublishPostJob::handle`: assinatura inativa e conta ausente/de outro usuário lançam `Exception` com o texto atual; `failed()` grava esse texto em `error_message` — teste: `tests/Feature/ScheduledPosts/PublishPostJobGuardsTest.php`
- [x] 0.2 Arquivos do post: cancelar e purge com e sem thumbnail (conferir `CancelScheduledPostTest`/`PurgeFailedPostsMediaTest`, que já têm thumbnail); sucesso do TikTok com thumbnail apaga os dois; sucesso do Instagram (`InstagramService`, container pronto) apaga vídeo e thumbnail; reenvio com thumbnail ausente = `media_expired` — teste: completar o que faltar nesses arquivos e em `tests/Feature/TikTok/CheckTikTokPostStatusJobTest.php`
- [x] 0.3 OAuth: `getRedirectUrl` sem workspace → `oauthInitializationFailed` nas 3 redes; `decrypt(state)` = uuid do workspace; `syncAccount` com `User` e com `Workspace` grava `workspace_id`/`user_id` certos (Google com `Socialite::shouldReceive`, Instagram e TikTok com `Http::fake`); Instagram: sem `code` → `authFailed`, username padrão "Instagram User", token de longa duração com fallback de 1 h; Google: falha do Socialite → `oauthTokenExchangeFailed` — teste: `tests/Feature/SocialAccounts/OAuthProvidersTest.php`
- [x] 0.4 Token TikTok: renovação, rotação do `refresh_token`, 200 sem `access_token`, revogação — já em `SocialAccountTokenTest` (conferir; não duplicar)

## 1. Baixo risco
- [x] 1.1 `ScheduledPost::mediaPaths()` + usar em `CheckTikTokPostStatusJob::handleSuccess`, `InstagramService::cleanupFiles`, `ScheduledPostService` (cancel e `mediaStillExists`) e `PurgeFailedPostsMediaCommand` (YouTube fica, ver Q2) — teste: 0.2 + `CancelScheduledPostTest`, `PurgeFailedPostsMediaTest`, `RetryScheduledPostCoverageTest`, `TikTok/*`
- [x] 1.2 Literais `'youtube'/'instagram'/'tiktok'` dos 3 providers viram `Platform::X->value` — teste: 0.3
- [x] 1.3 `ScheduledPostError` (+2 cases) e `ScheduledPostException` (+2 factories); `PublishPostJob` as usa, mesmo texto — teste: 0.1

## 2. Médio risco
- [x] 2.1 `AbstractOAuthProvider` (só se Q1 aprovada) e os 3 providers herdam, removendo os blocos copiados — teste: 0.3 + `TikTokAccountScopesTest`, `TikTokAnalyticsTest` (callback), `SocialAccountTest`
- [x] 2.2 `TikTokApi::refreshToken()/revokeToken()`; `SocialAccount` delega (mesmos campos, mesma mensagem sem body) — teste: `SocialAccountTokenTest` + `TikTokSecurityTest` (renovação não vaza body)

## 3. Condicional
- [x] 3.1 (Q3 = sim) Remover `DefaultSocialiteProvider` e o `default` da `OAuthProviderFactory` passa a lançar `SocialAccountException::platformNotSupported` — teste: novo caso em 0.3 (plataforma desconhecida → 400). Se Q3 = não: só remover o `use InvalidArgumentException`.

## Divergências

- **0.1 já tipada na partida:** o working tree já tinha `PublishPostJob` lançando `ScheduledPostException::subscriptionInactive()/accountNotFound()` (e `ScheduledPostError` com os 2 cases) e `failed()` gravando só mensagem de `BaseException` (texto genérico para o resto, fecha o Apontamento 1 da spec). Por isso a tarefa 1.3 não precisou de código: a caracterização (`PublishPostJobGuardsTest`) já trava a versão tipada (código, HTTP e texto idênticos aos de antes) e o texto genérico de `failed()`.
- **0.2/0.3 em arquivos novos:** testes de caracterização ficaram em `PostMediaFilesTest.php` (sucesso TikTok/Instagram, cancelar, purge, reenvio, com e sem thumbnail) e `OAuthProvidersTest.php`, sem editar nenhum teste existente. `CancelScheduledPostTest`/`PurgeFailedPostsMediaTest` já cobriam thumbnail e foram mantidos.
- **0.4:** `SocialAccountTokenTest` já cobria renovação, rotação do `refresh_token`, 200 sem `access_token`, mensagem sem body e revogação do TikTok; nada duplicado.
- **Q2 / YouTube:** `YouTubeService::cleanupFiles` ficou intacto (a 016 decide); os 2 testes por reflexão não foram tocados.
- **`TikTokApi::refreshToken`** lança `TikTokException::invalidResponse()` em falha (resposta sem `access_token` ou HTTP de erro); `SocialAccount` captura `TikTokException` e relança `SocialAccountException::tokenRefreshFailed('TikTok')`, mesma mensagem sem body. `ConnectionException` continua propagando como antes. O fallback `refresh_token ?? atual` foi para o `TikTokApi` (recebe o atual como argumento).
- **`TikTokOAuthProvider::getRedirectUrl`:** a checagem de `client_key` ficou antes de `stateFor`; as duas lançam a mesma `oauthInitializationFailed`, então a ordem não muda o resultado.
- **Plataforma desconhecida (Q3):** `OAuthProviderFactory` lança `platformNotSupported` (400); no callback, que captura `\Exception`, vira redirect ao front com `?error=Plataforma 'x' não é suportada.` (antes `TypeError`/500).
- **Bug de documentação (não corrigido):** a chave do YouTube na `OAuthProviderFactory` é `google` (e o redirect cadastrado no Google é `/social-accounts/google/callback`), mas o docblock do `SocialAccountController` e a spec dizem `youtube`. `GET /social-accounts/youtube/auth` agora dá 400 (antes 500). O front precisa chamar `google`.
