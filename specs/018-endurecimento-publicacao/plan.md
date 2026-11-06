# Plano 018

## Decisões
- **Permanente = método `isPermanent()` na exceção de cada rede** (já existe em Instagram/TikTok; `YouTubeError`/`YouTubeException` ganham o mesmo). O job decide em um `match` pelo tipo; `ScheduledPostException` tem lista fixa de códigos. Alternativa descartada: interface `PermanentFailure` (abstração nova, 3 implementações).
- **Falha permanente sem retry**: `catch` no `handle()`, `$this->fail($e)` quando há job (chama `failed()` e registra um único `failed_jobs`); sem job (chamada direta) chama `failed()`. Alternativa descartada: `$tries` dinâmico/`retryUntil` (não pára no ato).
- **Guarda de status** por consulta direta (`ScheduledPost::withoutGlobalScopes()->whereKey()->value('status')`), não `refresh()`: `refresh()` recarregaria as relações já carregadas e o worker não tem workspace corrente.
- **`publishContainer(post, account, token, containerId)`**: parâmetro obrigatório; o caminho `handleExistingContainer` passa `$post->container_id`.
- **Instagram PUBLISHED**: não há como obter o `media id` a partir do container com segurança (ids diferentes), então o post fecha `published` com `platform_post_id`/`platform_post_url` nulos (o modelo já aceita; `getPlatformPostUrl` devolve null). Não se faz busca "por proximidade" em `/media`.
- **Destinatários**: `recipientsWithPermission()` (já existe) + autor da conexão se ainda tem `Access`; união por `id`; envio por pessoa com `sendSafely`. Dedupe por `type + resource` já existente.
- **Fuso**: `Carbon::parse($v)->setTimezone(config('app.timezone'))` em `ScheduledPostService` (agendar e reenviar). Entrada sem offset segue interpretada no fuso da aplicação.
- **Validações por rede** no payload builder (padrão do TikTok), que roda dentro da transação do `schedule()`: `ValidationException` => 422 com rollback.

## Arquivos
`app/Jobs/PublishPostJob.php`, `app/Jobs/CheckInstagramContainerJob.php`, `app/Services/InstagramService.php`, `app/Services/YouTubeService.php`, `app/Services/NotificationService.php`, `app/Services/ScheduledPostService.php`, `app/Enums/Exceptions/YouTubeError.php`, `app/Enums/Exceptions/TikTokError.php`, `app/Exceptions/YouTubeException.php`, `app/Services/Payloads/Builders/{Abstract,YouTube,TikTok,Instagram}*.php`. Testes novos em `tests/Feature/ScheduledPosts/`, `tests/Feature/Instagram/`, `tests/Feature/YouTube/`, `tests/Feature/Notifications/`.

## Efeitos colaterais
Posts cancelados deixam de ser publicados (antes o job rodava e falhava por falta de mídia, sobrescrevendo `cancelled`). Mensagens 422 novas no agendamento. Nenhuma migration.

## Riscos
- `TikTokError::PRIVACY_NOT_ALLOWED` vira permanente: o `CheckTikTokPostStatusJob` não o gera, sem impacto.
- Validação de vídeo/JPEG olha a extensão do path (a presigned URL fixa a extensão pelo `content_type` aceito: mp4, mov, jpg, png).
