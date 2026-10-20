# Spec 012 — Reenviar post que falhou

**Status:** implementada (API + frontend, spec 017 do UI); aprovação dada em conversa, sem aprovar plan/tasks separadamente · **Área crítica:** esteira de publicação (spec reversa abaixo)

## Contexto

Hoje, quando um post falha (`status = failed`), o usuário só vê o `error_message` e precisa criar tudo de novo (novo upload, título, legenda, opções). Caso real: um mesmo vídeo foi agendado para YouTube e Instagram; o YouTube publicou e o Instagram falhou por causa de um problema de ambiente. O vídeo do post falho continuava no bucket, mas não havia como reenviá-lo.

O objetivo é um botão **Reenviar** nos posts com falha, que tenta publicar de novo usando os mesmos dados do post, e uma regra clara de retenção da mídia: ela fica guardada enquanto o post estiver `failed` e é limpa depois de 5 dias sem reenvio.

## Comportamento atual (spec reversa)

- Rotas de post: `GET scheduled-posts`, `POST scheduled-posts/upload-url`, `POST scheduled-posts`, `DELETE scheduled-posts/{scheduledPost}`. Não existe reenvio.
- Status possíveis: `pending`, `processing`, `published`, `failed` (`ScheduledPostStatus`).
- `PublishPostJob::handle` exige assinatura válida do dono do post e que a conta social ainda pertença a ele.
- Permissões: grupo `posts` com `view`, `create` e `delete`.

Caminhos de falha hoje:

| Caminho | Apaga a mídia? | Notifica? |
|---|---|---|
| `PublishPostJob::failed()` (YouTube; Instagram ao criar o container; TikTok no início do envio) | sim, `deleteMany`, sem conferir se outro post usa o arquivo | sim, `notifyPostFailed` |
| `CheckInstagramContainerJob::markAsFailed` (container em `ERROR`) | não | **não** |
| `CheckTikTokPostStatusJob::handleFailed` | sim, `deleteIfUnused` | **não** |

- `NotificationService::notifyPostFailed` já existe: tipo `POST_FAILED`, família `posts`, aparece na central (in-app) e vai por e-mail conforme a preferência do usuário (spec 009). Destinatários: membros do workspace com permissão de posts.
- `StorageService::deleteIfUnused` só considera "em uso" os posts `pending` e `processing`. Um post `failed` não protege o arquivo: se o YouTube publica depois de o Instagram ter falhado com o mesmo vídeo, o arquivo é apagado e o reenvio do Instagram deixa de ser possível. Foi o que quase aconteceu no caso real.
- O arquivo do post do Instagram sobrou no bucket por acaso: o YouTube não o apagou porque o post do Instagram ainda estava `processing`, e o `PublishPostJob` do Instagram já tinha terminado com sucesso, então o `failed()` nunca rodou.

## Escopo

1. Ação para reenfileirar, de imediato, um post `failed` do workspace corrente.
2. Retenção da mídia de posts `failed` por 5 dias, com job diário de limpeza.
3. Aviso de falha por app e e-mail em todos os caminhos de falha.

## Requisitos

- **RF-1** Só posts com `status = failed` podem ser reenviados. Qualquer outro status retorna erro de domínio (409).
- **RF-2** O post volta para `pending`, `error_message` e `failed_at` são limpos e o `PublishPostJob` é despachado na fila da plataforma. Título, legenda, payload e conta não mudam. Sem `scheduled_at` o reenvio é imediato; com `scheduled_at` (opcional, mínimo 5 minutos no futuro, mesma regra do agendamento) o post é reagendado e o job sai com atraso.
- **RF-3** Só é possível reenviar se a mídia (e a thumbnail, quando houver) ainda existir no storage. Se já foi limpa pelo job, erro de domínio (422) com aviso para o usuário adicionar o post novamente com novo upload.
- **RF-4** Permissão `posts.create`. Super admin ignora.
- **RF-5** Isolamento por workspace: post de outro workspace retorna 404 e nada muda.
- **RF-6** A listagem informa `can_retry`: verdadeiro quando `status = failed` e a mídia ainda não foi limpa (não consulta o S3 item a item), e `retry_until`: até quando o arquivo fica guardado (`failed_at` + 5 dias; `null` se não dá para reenviar). `media_url` vem `null` depois da limpeza.
- **RF-7** O reenvio reaproveita as verificações do `PublishPostJob` (assinatura válida, conta vinculada). Não há regra nova de billing.
- **RF-8** Posts do Instagram reenviados criam um container novo (o antigo foi zerado na falha).
- **RF-9** Nenhuma falha, em nenhuma plataforma, apaga a mídia. A mídia só sai quando o post é publicado com sucesso, é excluído/cancelado, ou pelo job de limpeza (RF-11).
- **RF-10** Mídia usada por um post `failed` ainda dentro do prazo conta como "em uso": a publicação de outro post com o mesmo arquivo não o apaga.
- **RF-11** Um job diário apaga a mídia (e a thumbnail) dos posts `failed` há mais de 5 dias sem reenvio, respeitando outros posts que usem o mesmo arquivo. O post continua na listagem com `can_retry = false`.
- **RF-12** O prazo de 5 dias conta a partir da falha mais recente: um reenvio que falha de novo reinicia a contagem.
- **RF-13** Toda falha, em qualquer plataforma e caminho, dispara `notifyPostFailed` (in-app e e-mail conforme preferência). Hoje o Instagram (container em erro) e o TikTok (após o envio) não avisam. A mensagem diz que o usuário tem 5 dias para reenviar. Cada falha avisa uma vez; um reenvio que falha de novo avisa de novo.
- **RF-14** Sem limite de reenvios por post.

## Contrato de API

`POST /api/scheduled-posts/{scheduledPost}/retry` (uuid)

Body opcional: `scheduled_at` (data/hora, pelo menos 5 minutos no futuro). Sem ele, reenvia agora.

- 200: `{"message": "Post reenviado para a fila.", "data": ScheduledPostResource}`
- 403: sem `posts.create`
- 404: post inexistente ou de outro workspace
- 409: post não está `failed`
- 422: mídia já foi limpa (`media_expired`) ou `scheduled_at` inválido; no primeiro caso o usuário precisa agendar de novo com novo upload

`GET scheduled-posts` e `scheduled_posts` de `GET social-accounts/schedules` (mesmo Resource): cada item ganha `can_retry` (boolean) e `retry_until` (ISO 8601 ou `null`).

## Critérios de aceite

1. Post `failed` com mídia no storage: `POST .../retry` retorna 200, o post vira `pending`, `error_message` fica nulo e um `PublishPostJob` entra na fila da plataforma.
2. Post `pending`, `processing` ou `published`: 409 e nada muda.
3. Post `failed` cuja mídia já foi limpa: 422 e nada muda.
4. Usuário sem `posts.create`: 403.
5. Post de outro workspace: 404 e nada muda no banco.
6. Listagem traz `can_retry = true` só para `failed` com mídia não limpa.
7. Falha de publicação não apaga a mídia em nenhuma plataforma.
8. O job de limpeza apaga a mídia de posts `failed` com mais de 5 dias e mantém a de posts com 4 dias.
9. O job de limpeza não apaga arquivo usado por outro post `pending` ou `processing`.
10. Falha no Instagram (container em erro) e no TikTok (após o envio) notifica os membros com permissão.
11. YouTube publicando não apaga o arquivo de um post `failed` do Instagram dentro do prazo.
12. O storage é sempre mockado nos testes.
13. Reenvio com `scheduled_at` reagenda; com `scheduled_at` a menos de 5 minutos retorna 422 e nada muda.
14. Reenvio que falha de novo notifica de novo.

## Fora de escopo

- Reenvio em lote.
- Mostrar o motivo real do erro do Instagram (apontamento: o job só consulta `status_code`).
- Frontend: tratado na spec 017 do `pandypost_ui`.

## Questões em aberto

Nenhuma. Decididas em conversa: sem limite de reenvios; a notificação informa o prazo de 5 dias; a limpeza apaga a mídia e mantém o registro do post; `scheduled_at` opcional no reenvio.
