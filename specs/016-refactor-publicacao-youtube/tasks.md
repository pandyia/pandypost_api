# Tasks 016 — YouTube no molde da 011 + token por rede

> Uma mudança por tarefa, do menor risco e maior valor ao maior risco. Ao fim de cada uma: `docker exec pandypost-php php artisan test` + `pint --dirty`. Só após aprovação e depois de 014 e 015. APIs sempre mockadas (Guzzle `MockHandler` para o SDK, `Http::fake` para o resto).

## 0. Costura e caracterização
- [x] 0.0 (Q3) `new Client` → `app(Client::class)` em `getAuthenticatedClient` (única mudança em `app/` antes dos testes) — teste: `NotificationHooksTest` (caso `social_account_disconnected`) segue verde
- [x] 0.1 Upload feliz: cliente Guzzle fake responde ao início do resumable e ao chunk final com `id`; post vira `published` com `platform_post_id`, `published_at`; S3 limpo (vídeo e thumbnail); chunks de 1 MB — teste: `tests/Feature/YouTube/YouTubeUploadTest.php`
- [x] 0.2 Token: válido não renova; vencido com `refresh_token` renova e grava `access_token`/`expires_at`; **refresh que falha segue com o token vencido** (registra o Apontamento 1); formato real guardado pela conexão (string do Socialite) — teste: `tests/Feature/YouTube/YouTubeClientTokenTest.php` (confirmar no container o que o SDK faz sem `expires_in`)
- [x] 0.3 Metadados: padrões (`public`, categoria `22`, `madeForKids=false`), tags com `trim`, `title`/`caption` — teste: `tests/Feature/YouTube/YouTubeMetadataTest.php`
- [x] 0.4 Thumbnail: enviada para vídeo normal, **não** enviada quando `is_short`, falha da thumbnail não derruba o post (`published`) — teste: `YouTubeUploadTest`
- [x] 0.5 Falha do upload: resposta de erro do Google → exceção propaga, post continua `processing` no service e `PublishPostJob::failed()` grava `failed`, S3 intacto; **sem fixar o JSON** — teste: `YouTubeUploadTest`
- [x] 0.6 `SocialAccountTokenTest` já cobre renovação/revogação das 3 redes (conferir; não duplicar)

## 1. Baixo risco
- [x] 1.1 Y3 early return em `getAuthenticatedClient` e Y4 `YouTubePrivacyStatus::PUBLIC->value` — teste: 0.2, 0.3 + `NotificationHooksTest`
- [x] 1.2 Y1: `YouTubeError`/`YouTubeException` com `refreshTokenMissing()` e `uploadFailed()` (mesmo texto) substituem os 2 `new Exception` — teste: 0.2, 0.5 + `NotificationHooksTest` (continua `toThrow(Exception::class)`)

## 2. Erros do Google (depende de Q1)
- [x] 2.1 `YouTubeException::fromGoogle()` e `catch (Google\Service\Exception)` no lugar do `catch (Exception)` que só loga; log só com o `reason` — teste: `tests/Feature/YouTube/YouTubeErrorMappingTest.php` (cada `reason` → texto pt-BR; nenhum JSON/token em `error_message`, notificação ou log) + 0.5 + `PostRetrySecurityTest`

## 3. Token por rede (fecha a série; maior alcance)
- [x] 3.1 `YouTubeApi::refreshToken/revokeToken` movidos de `SocialAccount` (mensagem com body preservada até Q5) — teste: `SocialAccountTokenTest` (YouTube)
- [x] 3.2 `PlatformTokenApiInterface`; `TikTokApi` (014, hoje `refreshToken(string)`) e `InstagramApi` (015) a implementam, ajustando a assinatura para `SocialAccount` — teste: `SocialAccountTokenTest`
- [x] 3.3 `SocialMediaFactory::tokenApi()`; `getValidToken`/`revokeToken` do model sem `match` e com um `update` só — teste: `SocialAccountTokenTest`, `TikTokSecurityTest`, `Analytics/*`, `InstagramPermalinkTest`, suíte inteira

## 4. Condicionais
- [x] 4.1 (Q5) Mensagem de renovação do Google sem body — teste: ajustar `SocialAccountTokenTest.php:84` e `RefactorModelsRegressionTest.php:28` (só o que fixa o body)
- [ ] 4.2 (NÃO executada: Q2 default = não alterar os testes de reflexão) `cleanupFiles` do YouTube usa `ScheduledPost::mediaPaths()` e os 2 testes de reflexão apontam para o efeito (S3 preservado) — teste: `RetryScheduledPostCoverageTest.php:173-205`

## Divergências

- **0.2 / bug real corrigido (fora do desenho original, autorizado pelo Gabriel).** A conexão guarda em `access_token` a string pura do Socialite; o SDK dá todo token sem `expires_in` como vencido (`isAccessTokenExpired()` = true sempre). Efeito antes: todo upload renovava o token pelo SDK, e conta sem `refresh_token` falhava com "Token expirado" mesmo com o token válido por 1 h. Agora, token sem `expires_in` usa o `expires_at` da conta (`getAuthenticatedClient`). Os 3 testes de caracterização que fixavam o comportamento antigo foram reescritos (são meus, criados nesta spec).
- **Refresh que falha deixou de ser silencioso:** `getAuthenticatedClient` agora lança `YouTubeException::tokenRefreshFailed()` (novo case `TOKEN_REFRESH_FAILED`) em vez de seguir com o token vencido.
- **3.1-3.3 nomes diferentes do plano:** `InstagramApiErrorsTest` chama `InstagramApi::refreshToken(string)` e espera `InstagramException`; trocar a assinatura exigiria editar testes existentes. A interface usa `refreshAccountToken(SocialAccount)` / `revokeAccountToken(SocialAccount)`; `TikTokApi::refreshToken/revokeToken(string)` foram renomeados (nenhum teste os chamava), `InstagramApi::refreshToken(string)` ficou e `refreshAccountToken` traduz `InstagramException` em `SocialAccountException::tokenRefreshFailed('Instagram')`. Nenhum teste existente alterado. A Api (e não o model) lança `SocialAccountException` (D4).
- **3.1:** `YouTubeApi` implementa a interface; o `Http` direto saiu do `SocialAccount`.
- **4.1 (Q5):** já feita pela 015 (SocialAccountException sem body); nada a repetir.
- **`GoogleOAuthProvider::approvedScopes`:** não existe no arquivo; nada a remover.
- **`isPermanent()` no YouTubeError:** não criado (sem consumidor; `PublishPostJob` não decide retry por tipo de exceção hoje).
- **Y2:** `catch (Exception)` da thumbnail (`streamThumbnailUpload`) mantido como estava (preserva "falha da thumbnail, inclusive S3, só vira warning").
