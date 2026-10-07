# Plano 003 — Refatoração do YouTube

## 1. Decisões

| # | Decisão | Alternativa descartada |
|---|---|---|
| 1 | **Ponto de injeção do `Google\Client`:** os services passam a criar o client com `app(Client::class)`. Em produção é o mesmo `new Client()`; nos testes, o container entrega um client com Guzzle mockado. É o primeiro passo, antes dos testes de caracterização. | Mockar o SDK do Google com Mockery: testes frágeis, acoplados a dezenas de classes do SDK. |
| 2 | **Token pelo `getValidToken()`** no upload e no analytics, com um único `makeClient()` por service. | Manter o refresh próprio do upload (renova sempre). |
| 3 | **Pedaços de 8 MB** lidos com `stream_get_contents` (exatos, múltiplos de 256 KB). | Manter 1 MB: 8× mais requisições sem ganho; a memória (8 MB) não pesa no worker. |
| 4 | **Analytics no mesmo service**, dividido em métodos curtos: `report()` único para as consultas, `isAuthorizationError()` único, `match` nos períodos, constantes para fallbacks, TTLs e limiares. | Quebrar em várias classes (calculadora de score, gerador de alertas): abstração nova sem necessidade. |
| 5 | **`winnerScore` sem CTR**, com pesos nomeados (30/15/40/15). | Manter o CTR de anotações (sempre 0). |
| 6 | **`best-times`:** `Cache::get` + `Cache::put` só no sucesso; erro devolve o fallback sem cache. | `Cache::remember` com try/catch dentro (guarda o fallback por 7 dias). |
| 7 | **Título obrigatório para YouTube** via `after()` no `StoreScheduledPostRequest`, consultando as contas escolhidas. | Validar no service: o erro viria como 400 de domínio, não como 422 de validação. |
| 8 | **Factory OAuth:** `default` lança `SocialAccountException::platformNotSupported`. | — |

## 2. Modelo de dados

Sem migrations.

## 3. Arquivos

| Arquivo | Mudança |
|---|---|
| `app/Services/YouTubeService.php` | reescrito (D2, D3, base comum da 005) |
| `app/Services/YouTubeAnalyticsService.php` | reescrito (D4–D6) |
| `app/Http/Controllers/Api/AnalyticsController.php` | enxuto, `error` só com debug (Y8) |
| `app/Services/OAuthProviders/GoogleOAuthProvider.php` | constantes, `Platform::YOUTUBE` |
| `app/Services/OAuthProviders/DefaultSocialiteProvider.php` | removido |
| `app/Services/Factories/OAuthProviderFactory.php` | `default` → 400 |
| `app/Models/SocialAccount.php` | `refreshYouTubeToken` com erro de domínio |
| `app/Http/Requests/StoreScheduledPostRequest.php` | título para YouTube, mensagem `.enum` |
| `tests/Feature/YouTube/*` | novos |
| `tests/Pest.php` | helpers do Google fake |

## 4. Efeitos colaterais

Nenhum evento novo. O front deve parar de exibir `ctr` dos top vídeos.

## 5. Testes

`tests/Feature/YouTube/`: `YouTubeConnectionTest`, `YouTubePublishTest`, `YouTubeAnalyticsTest`, `YouTubeSchedulingTest`. Google fake: `fakeGoogleApi(fn (Request) => Response)` em `tests/Pest.php` liga um `Google\Client` com `MockHandler` do Guzzle e registra as requisições.

## 6. Riscos

- O SDK do Google faz chamadas em ordem própria (início do upload resumable, pedaços, thumbnail). O fake responde por URL/método, não por ordem.
- Pedaços de 8 MB: o worker precisa de ~16 MB livres por upload (pedaço + cópia do Guzzle). O limite do supervisor é 256 MB.
