# Plano 005 — Esteira de publicação

## 1. Decisões

| # | Decisão | Alternativa descartada |
|---|---|---|
| 1 | `markAsPublished`/`markAsFailed` no **model** `ScheduledPost`, junto dos outros métodos de negócio (`hasValidContainer`, `getPlatformPostUrl`). | Trait ou classe nova: abstração a mais para dois métodos. |
| 2 | `deletePostMedia(ScheduledPost)` no **`StorageService`**, reaproveitando `deleteIfUnused`. | Cada plataforma montar a lista de caminhos (como hoje): 4 cópias da mesma lógica. |
| 3 | Remover o `timeout` do job; vale o do supervisor da fila. | Timeout por plataforma no job: duplica o que já está no `config/horizon.php`. |
| 4 | `public array $backoff = [30, 120]`. | Classificar erros definitivos: fora de escopo (spec). |

## 2. Modelo de dados

Sem migrations.

## 3. Arquivos

| Arquivo | Mudança |
|---|---|
| `app/Models/ScheduledPost.php` | `markAsPublished`, `markAsFailed`, `mediaPaths` |
| `app/Services/Storage/StorageService.php` | `deletePostMedia` |
| `app/Jobs/PublishPostJob.php` | E1, E3–E6 |
| `app/Services/ScheduledPostService.php` | E2 (`cancel`) |
| `app/Services/TikTokService.php`, `app/Jobs/CheckTikTokPostStatusJob.php` | usar a base comum |
| `tests/Feature/ScheduledPosts/PublishPipelineTest.php` | novo |

## 4. Efeitos colaterais

Nenhum evento novo. Posts que falham depois de 3 tentativas demoram ~2,5 min a mais para aparecer como `failed`.

## 5. Testes

`tests/Feature/ScheduledPosts/PublishPipelineTest.php`: CA-1 a CA-5 e CA-7, com `Storage::fake('s3')` e `Bus::fake`. CA-6: suíte do TikTok.

## 6. Riscos

- Arquivos de posts que falharam antes desta correção podem já ter sido apagados; nada a recuperar.
