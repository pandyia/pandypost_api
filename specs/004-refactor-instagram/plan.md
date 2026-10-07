# Plano 004 — Refatoração do Instagram

## 1. Decisões

| # | Decisão | Alternativa descartada |
|---|---|---|
| 1 | **O service é dono da Graph API** (`containerStatus`, `publish`). O job de acompanhamento só decide *quando* chamar. | Manter cópias no job: é a causa de I1. |
| 2 | **Falhas sobem como `ScheduledPostException`** e quem marca o post como `failed` é o `failed()` do job que estiver rodando (`PublishPostJob` ou `CheckInstagramContainerJob`), como no TikTok. | Marcar `failed` dentro do service: duplica o que os jobs já fazem. |
| 3 | **Acompanhamento por prazo** (`retryUntil` 30 min, consultas a 5/10/20/30/60 s, `maxExceptions` 3), como no TikTok. | Aumentar `$tries`. |
| 4 | **Permalink no `payload`** (`payload.permalink`), lido pelo `getPlatformPostUrl`. | Coluna nova: migration para um dado que só o Instagram usa. |
| 5 | **`SocialAccount::refreshToken()` público**, usado pelo `getValidToken()` e pelo comando `instagram:refresh-tokens`. | Comando com lógica de refresh própria (duplicaria `refreshInstagramToken`). |
| 6 | **Constante `GRAPH_API_URL` pública no service**, usada pelo provider OAuth e pelo model. | Config em `services.php`: a versão da API é detalhe do código, não do ambiente. |

## 2. Modelo de dados

Sem migrations. Novo campo em `scheduled_posts.payload`: `permalink`.

## 3. Arquivos

| Arquivo | Mudança |
|---|---|
| `app/Services/InstagramService.php` | reescrito |
| `app/Jobs/CheckInstagramContainerJob.php` | reescrito (usa o service) |
| `app/Services/OAuthProviders/InstagramOAuthProvider.php` | constantes, escopos (I6), falha do token longo (I5) |
| `app/Models/SocialAccount.php` | `refreshToken()` público, `refreshInstagramToken` com erro de domínio |
| `app/Models/ScheduledPost.php` | link pelo `permalink` (I3) |
| `app/Console/Commands/WarmupPostsCommand.php` | enxuto |
| `app/Console/Commands/RefreshInstagramTokensCommand.php` | novo (I4) |
| `routes/console.php` | agenda o comando |
| `app/Services/Payloads/Builders/InstagramPayloadBuilder.php` | corpo vazio |
| `tests/Feature/Instagram/*` | novos |

## 4. Efeitos colaterais

Comando agendado novo (diário, 03:00). Uma chamada a mais à Graph API por publicação (permalink).

## 5. Testes

`tests/Feature/Instagram/`: `InstagramConnectionTest`, `InstagramPublishTest`, `InstagramContainerJobTest`, `InstagramWarmupTest`, `InstagramTokenRefreshTest`. `Http::fake` para a Graph API, `Storage::fake('s3')`.

## 6. Riscos

- Posts do Instagram publicados antes desta correção ficam sem link (`null`) em vez do link quebrado.
- Refresh do Instagram exige token com pelo menos 24 h; tokens recém-criados que caírem na janela (não deveria acontecer com 60 dias) só geram log.
