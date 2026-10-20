# Plano 012 — Reenviar post que falhou

## Decisões

- **Observer do model em vez de editar cada caminho de falha.** `ScheduledPost` já centraliza o aviso de sucesso em `booted()`; a falha segue o mesmo padrão: `updating` grava `failed_at` e `updated` chama `notifyPostFailed`. Cobre `PublishPostJob::failed`, `CheckInstagramContainerJob`, `CheckTikTokPostStatusJob` e `InstagramService` sem tocar em cada um. *Descartada:* chamar `notifyPostFailed` em cada job (já era a causa de Instagram e TikTok não avisarem).
- **Falha não apaga mais a mídia** (`PublishPostJob::failed` e `CheckTikTokPostStatusJob::handleFailed`). Quem apaga é o comando diário. Isso também elimina o `deleteMany` sem checar uso compartilhado.
- **Duas colunas:** `failed_at` (início do prazo) e `media_purged_at` (retenção encerrada). *Descartada:* usar `updated_at`, que muda com qualquer update.
- **`deleteIfUnused` passa a tratar `failed` sem `media_purged_at` como "em uso".** *Descartada:* checar só `pending/processing` (YouTube publicando apagaria o arquivo do Instagram falho).
- **`retry` confere `exists()` no storage além de `media_purged_at`,** porque posts que falharam antes desta mudança tiveram a mídia apagada sem a flag.
- **Backfill:** posts já `failed` ganham `failed_at = updated_at`; `media_purged_at` fica nulo e o `exists()` decide.
- **Dedupe da notificação** inclui o timestamp da falha (`dedupe_key`), senão a 2ª falha do mesmo post nunca avisaria.
- **Escopo do reenvio:** workspace corrente (global scope), não só o autor, igual a quem recebe o aviso de falha. O job continua usando a assinatura e a conta do dono do post.

## Modelo de dados

`scheduled_posts`: `failed_at` timestamp nullable, `media_purged_at` timestamp nullable. Migration `2026_10_19_000000_add_failure_retention_to_scheduled_posts_table`.

## Arquivos

- `routes/api.php`, `ScheduledPostController::retry` (`posts.create`), `RetryScheduledPostRequest`
- `ScheduledPostService::retry`, `ScheduledPostError` + `ScheduledPostException` (`RETRY_NOT_ALLOWED` 409, `MEDIA_EXPIRED` 422)
- `ScheduledPost` (constante `MEDIA_RETENTION_DAYS`, `canRetry()`, `retryDeadline()`, observers), `ScheduledPostResource` (`can_retry`, `retry_until`)
- `PublishPostJob`, `CheckTikTokPostStatusJob` (falha não apaga), `StorageService::deleteIfUnused`
- `PurgeFailedPostsMediaCommand` (`posts:purge-failed-media`, diário às 06:00 em `routes/console.php`)
- `NotificationFactory` (texto com o prazo e `dedupe_key`), `NotificationService::notifyPostFailed`

## Efeitos colaterais

Mídia de post falho fica no bucket até 5 dias. Instagram e TikTok passam a notificar falha.

## Testes

`tests/Feature/ScheduledPosts/RetryScheduledPostTest.php` e `PurgeFailedPostsMediaTest.php`.

## Riscos

- Falha de upload no S3 antes da publicação deixa arquivo órfão por 5 dias (aceito).
- Posts `failed` antigos com `can_retry = true` podem responder 422 ao reenviar (mídia já apagada); a UI trata `media_expired`.
