# Tasks 018

- [x] 1 `PublishPostJob`: erro permanente falha uma vez sem retry; guarda de status; `failed()` não sobrescreve published/cancelled; timeout por rede (+ `isPermanent` no YouTube, privacidade TikTok) — teste: `PublishPostJobPermanentTest`
- [x] 2 `publishContainer` com o `container_id` do job; job de verificação com guarda de status; warmup não derruba o post — teste: `InstagramContainerJobTest` (novo arquivo `InstagramHardeningTest`)
- [x] 3 YouTube: `invalid_grant` no refresh notifica a desconexão — teste: `YouTubeRefreshNotificationTest`
- [x] 4 `social_account_disconnected` para quem conectou + membros com permissão, sem duplicar — teste: `SocialAccountDisconnectedRecipientsTest`
- [x] 5 Instagram `PUBLISHED`/`EXPIRED` e conferência após erro do `media_publish` — teste: `InstagramHardeningTest`
- [x] 6 Revisão ponta a ponta: fuso do `scheduled_at`, validações por rede, cleanup do YouTube sem retry — teste: `SchedulePostValidationTest`, `YouTubeCleanupTest`
- [x] 7 Pint nos alterados + suíte completa verde

## Divergências
- **D1 (execução direta do job)**: `PublishPostJob::handle` só chama `$this->fail()` quando há job na fila. Sem job (chamada direta, como em `PublishPostJobGuardsTest`/`WorkspaceSharedAccountTest`) a exceção é relançada, preservando o comportamento que esses testes travam. Em produção sempre há job.
- **D2 (Instagram PUBLISHED)**: o id do container não é o id da mídia e não há consulta segura de um para o outro. Post com container `PUBLISHED` fecha `published` com `platform_post_id`/`platform_post_url` nulos (sem link no front). Não foi implementada busca em `/media` por proximidade de horário.
- **D3 (warmup)**: erro, `ERROR`/`EXPIRED` ou token inválido num job de warmup deixam de marcar o post como `failed` (antes o post agendado para daqui a horas falhava e o job de publicação o ignoraria com a nova guarda de status).
- **D4 (destinatários)**: quem conectou só é avisado se ainda tem `Access` no workspace da conta (mesmo padrão de `notifyWorkspaceInviteAccepted`). Antes era avisado mesmo tendo saído.
- **D5 (fuso)**: entrada sem offset continua no fuso da aplicação (`America/Sao_Paulo`).
- **D6 (Pint)**: o `pint --dirty` também reformatou trechos antigos de `AbstractPlatformPayloadBuilder` e `YouTubeService` (aspas, espaços de operador).
- **Apontamentos (fora de escopo)**: ver relatório final do agente.
