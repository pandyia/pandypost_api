# Plan 016 — YouTube no molde da 011 + token por rede

## Decisões

| # | Decisão | Alternativa descartada |
|---|---------|------------------------|
| D1 | Sem wrapper do SDK. `YouTubeApi` só cobre o endpoint OAuth do Google (`refreshToken`, `revokeToken`), movido de `SocialAccount`. | `YouTubeApi::upload()` embrulhando o SDK: camada 1:1 sem ganho. |
| D2 | `PlatformTokenApiInterface` com **3 implementações** (`TikTokApi`, `InstagramApi`, `YouTubeApi`): legítima, ao contrário das interfaces de implementação única. `refreshToken(SocialAccount): array{access_token: string, expires_in: int, refresh_token?: string}` — cada Api aplica seu default de `expires_in` e valida `refresh_token` ausente (`SocialAccountException::refreshTokenMissing`). `revokeToken` do Instagram é no-op documentado (a Meta não tem endpoint). | Manter o `match` no model delegando às Apis: continua violando o checklist 6 e exige editar o model a cada rede. |
| D3 | `SocialMediaFactory::tokenApi(Platform): PlatformTokenApiInterface` (a factory já é o ponto de variação por plataforma; criar `TokenApiFactory` seria uma classe a mais). Plataforma sem Api (não existe hoje) → `getValidToken` devolve o token atual, como o `default` atual. | Factory nova. |
| D4 | `getValidToken()` fica: `if (! isTokenExpired()) return access_token;` → `tokenApi()->refreshToken($this)` → **um** `update` (`refresh_token` só quando a Api devolve). A falha continua `SocialAccountException::tokenRefreshFailed($provider, $body?)` lançada pela Api. | Persistir dentro da Api: cliente HTTP não grava no banco. |
| D5 | `YouTubeException::fromGoogle(Google\Service\Exception)` escolhe o `YouTubeError` por `reason`; `reason` é o único dado de terceiro que chega ao log. Sem `reason` → `UNKNOWN`. Erros não-Google (ex.: S3) propagam como estão (não são do YouTube). | `catch (Throwable)`: esconderia bugs nossos atrás da mensagem do YouTube. |
| D6 | Costura `app(Client::class)` (Y6) como tarefa 0, antes dos testes; os testes fazem `app()->bind(Client::class, fn () => $fake)` com `setHttpClient(new GuzzleHttp\Client(['handler' => MockHandler]))`. | Testar só por reflexão (como hoje): não cobre o upload. |

## Arquivos
| Ação | Arquivo |
|------|---------|
| novo | `app/Services/YouTube/YouTubeApi.php`, `app/Enums/Exceptions/YouTubeError.php`, `app/Exceptions/YouTubeException.php`, `app/Contracts/PlatformTokenApiInterface.php` |
| edita | `YouTubeService`, `TikTokApi` e `InstagramApi` (implementam a interface), `SocialMediaFactory`, `SocialAccount`, `GoogleOAuthProvider` (`approvedScopes`: fora; só se a 014 não tiver coberto) |
| novo (testes) | `tests/Feature/YouTube/YouTubeUploadTest.php`, `.../YouTubeClientTokenTest.php`, `.../YouTubeMetadataTest.php`, `.../YouTubeErrorMappingTest.php` |

## Ordem
Costura → caracterização → trivial (Y3, Y4) → exceções tipadas (Y1) → erros do Google (Y2, Q1) → token por rede (Y5, fecha as 3 redes).

## Riscos
- **Upload sem teste hoje.** Sem a costura (Q3) a caracterização é impossível; é o maior risco da spec.
- **Testes por reflexão** travam construtor, `getAuthenticatedClient` e `cleanupFiles` (RF-2).
- **SDK não verificável no host.** Os assertos de `Google\Service\Exception::getErrors()` e de `isAccessTokenExpired()` precisam ser conferidos dentro do container na caracterização.
- **Token de todas as redes passa por `getValidToken`** (publicar, analytics, snapshot diário, backfill): a tarefa 5 é a de maior alcance. `SocialAccountTokenTest` (14 casos) e `TikTokSecurityTest` cobrem; rodar a suíte inteira, incluindo `Analytics/` e `Security/`.
- `getValidToken` usa o `Platform` do enum: valor desconhecido na coluna `platform` precisa seguir devolvendo o token atual.
- Mensagem de `error_message` do YouTube muda (Q1): avisar no release.
