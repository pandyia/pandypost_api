# Plan 014 — Base comum da publicação e do OAuth social

## Decisões

| # | Decisão | Alternativa descartada |
|---|---------|------------------------|
| D1 | `ScheduledPost::mediaPaths(): array` no model (`array_values(array_filter([media_path, payload['thumbnail_path']]))`). O model já tem `canRetry()`/`retryDeadline()` ligados à mídia. | Método em `StorageService` recebendo o post: o service de storage passaria a conhecer o formato do `payload`. |
| D2 | Duas factories estáticas novas em `ScheduledPostException` + dois cases em `ScheduledPostError` (`SUBSCRIPTION_INACTIVE` 403, `ACCOUNT_NOT_FOUND` 404), texto idêntico ao atual. | Reaproveitar `noAccountLinked`: o texto é outro e `error_message` mudaria. |
| D3 | `AbstractOAuthProvider` (ver Q1) com `protected stateFor(?User): string` (lança `oauthInitializationFailed`, devolve `encrypt($workspace->uuid)`) e `protected ownerIds(User\|Workspace): array`. O TikTok mantém sua checagem extra de `client_key` antes de chamar `stateFor`. | Mais um método (`exchangeCode`): cada rede troca código de um jeito, não há duplicação real. |
| D4 | `TikTokApi::refreshToken(string $refreshToken): array` e `revokeToken(string $token): void`, devolvendo `['access_token','refresh_token','expires_in']` já com o default de 86400. `SocialAccount::refreshTikTokToken()` mantém `refreshTokenMissing()` e a `SocialAccountException::tokenRefreshFailed('TikTok')` (sem body) e só persiste. | Mover a persistência para o `TikTokApi`: o cliente HTTP não deve gravar no banco. |
| D5 | Não mexer em `getValidToken()` além do ramo TikTok. O `match` só some na 016 (Strategy com 3 implementações). | Strategy já aqui, com 1 implementação: abstração prematura. |
| D6 | `PublishPostJob::failed()` **não muda** (Apontamento 1). | Corrigir junto: muda `error_message`, é decisão de segurança. |

## Arquivos
| Ação | Arquivo |
|------|---------|
| edita | `ScheduledPost` (+`mediaPaths`), `ScheduledPostService` (cancel, `mediaStillExists`), `PurgeFailedPostsMediaCommand`, `CheckTikTokPostStatusJob` (`handleSuccess`), `InstagramService::cleanupFiles` |
| edita | `PublishPostJob`, `ScheduledPostException`, `ScheduledPostError` |
| novo | `app/Services/OAuthProviders/AbstractOAuthProvider.php` (Q1) |
| edita | `GoogleOAuthProvider`, `InstagramOAuthProvider`, `TikTokOAuthProvider` |
| edita | `TikTokApi` (+2 métodos), `SocialAccount` (ramo TikTok) |
| remove (Q3) | `DefaultSocialiteProvider`, `use InvalidArgumentException` da factory |
| novo (testes) | `tests/Feature/ScheduledPosts/PublishPostJobGuardsTest.php`, `tests/Feature/SocialAccounts/OAuthProvidersTest.php` |

## Ordem
Caracterização → `mediaPaths` → exceções tipadas → literais para `Platform` → base OAuth → `TikTokApi` token → código morto.

## Riscos
- `PublishPostJob`, `CheckTikTokPostStatusJob` e `CheckInstagramContainerJob` são serializados na fila: **não renomear propriedades públicas nem mudar o construtor**. Só o corpo dos métodos muda.
- 011 não commitada: conflito com o trabalho em andamento. Começar após o commit.
- `state` do OAuth: o teste de caracterização trava o formato (`decrypt(state) === workspace uuid`) para a base não mudá-lo por engano.
- `mediaPaths()` é chamado em `ScheduledPostService::mediaStillExists` (reenvio) e no purge: um `array_filter` a mais ou a menos mudaria a decisão de "mídia expirada". Os testes de reenvio/purge (`RetryScheduledPostCoverageTest`, `PurgeFailedPostsMediaTest`) cobrem vídeo com e sem thumbnail.
