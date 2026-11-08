# Spec 020 — Posts presos em `processing`

**Status:** aprovada e implementada (2026-11-06) · **Área crítica:** esteira de publicação

## Contexto

Todo post precisa terminar em um estado final: `published` quando a rede aceitou, `failed` quando não. Hoje isso vale nos caminhos normais (`PublishPostJob::failed()`, `CheckTikTokPostStatusJob`, `CheckInstagramContainerJob`), mas **não há rede de segurança** se o job morrer sem passar por eles: worker derrubado, timeout, reinício do Horizon no meio do envio, job perdido na fila.

Caso real (06/11/2026, TikTok): os posts 27 e 28 ficaram em `processing` por horas, sem erro e sem opção de reenvio, porque o Horizon seguia com código antigo e o job nunca chegou ao `failed()`. Só voltaram a ser utilizáveis porque o estado foi corrigido à mão no banco. Enquanto está em `processing`, o usuário não consegue cancelar (409) nem reenviar (a spec 012 só reenvia `failed`).

## Escopo

Um comando agendado que resolve os posts parados em `processing` além de um limite de tempo: **primeiro consulta a rede**; só então decide `published`, `failed` ou aguardar. Objetivo: o estado do post sempre reflete a rede, e o reenvio nunca duplica a publicação.

## Requisitos

- **RF-1** Post em `processing` há mais tempo que o limite da plataforma é conferido na rede (TikTok: `publish_id`; Instagram: contêiner; YouTube: ID do vídeo, ver RF-9). A contagem parte da última atualização do post.
- **RF-2** Limites (aprovados): **TikTok 30 min, YouTube 30 min, Instagram 60 min**.
- **RF-3** Resultado da conferência: a rede confirma publicação → `published` (com `platform_post_id`/`published_at`); a rede informa falha → `failed`; a rede ainda processa → nada muda; **sem identificador gravado** (não dá para conferir) → `failed` com texto que manda **conferir na conta da rede antes de reenviar**.
- **RF-4** Só mexe em `processing`. `pending`, `published`, `failed` e `cancelled` nunca são alterados, e a troca é condicional (`WHERE status = 'processing'`).
- **RF-5** `failed` do comando tem `failed_at` e `error_message` fixos em pt-BR do enum de erro da rede, nunca texto de terceiro.
- **RF-6** Mídia: só é apagada em sucesso e via `deleteIfUnused`. Em `failed` o arquivo fica (retenção de 5 dias, spec 012). Falha de um post **nunca** apaga a mídia de outro post que a usa (cada rede mantém o seu post independente), e o reenvio reaproveita o arquivo já no S3, sem novo upload.
- **RF-7** Aviso ao usuário pelo `NotificationService::notifyPostFailed`/fluxo já existente, uma única vez; rodar o comando duas vezes é idempotente.
- **RF-8** (era questão em aberto) Promoção tardia: se a rede confirmar a publicação, o post vira `published` mesmo que o comando o tenha fechado antes, **desde que ainda não tenha sido reenviado** e dentro de 6 h do fechamento (depois disso só o `/retry` confere). O `/retry` responde 409 se a rede já publicou ou ainda está processando. Assim `failed` nunca esconde um vídeo que existe, e o reenvio manual só é oferecido quando a rede não tem o vídeo.
- **RF-9** YouTube: o ID do vídeo é gravado no post (`platform_post_id`) **assim que o upload termina**, antes de qualquer etapa seguinte. Se existir ID, o `PublishPostJob` e o reenvio não enviam de novo; só conferem.
- **RF-10** Antiduplicidade geral: o reenvio (`/retry`) e a republicação de job atrasado não publicam se o post já tem identificador da rede; nesse caso conferem e atualizam o estado.

## Contrato de API

Nenhum endpoint novo. O post fechado pelo comando aparece como `failed` e `can_retry = true` na listagem existente, e o reenvio usa `POST /scheduled-posts/{uuid}/retry`.

## Critérios de aceite

1. Post TikTok em `processing` com 31 min é conferido na rede: sem `publish_id` vira `failed` (mensagem pt-BR); com a rede ainda processando espera até 60 min (2× o limite) e então vira `failed`; com 29 min, não é nem consultado.
2. Post Instagram em `processing` com 45 min não muda; com 61 min vira `failed`.
3. Posts em outros status não são tocados.
4. O post fechado tem `failed_at` preenchido e dispara uma única notificação, mesmo com duas execuções do comando.
5. Depois de fechado como `failed` (rede sem o vídeo), o post pode ser reenviado por `/retry` e volta a `pending`.
8. Post cujo vídeo existe na rede nunca fica `failed` nem é publicado de novo; três posts (3 redes) com uma falha não afetam os outros dois nem a mídia deles.
6. Post que passa a `published` entre a leitura e a gravação não é sobrescrito.
7. Nenhuma mensagem expõe token, body ou URL de terceiro.

## Fora de escopo

- Corrigir a causa de cada travamento (código antigo no Horizon, timeouts de cada rede).
- Mudar o fluxo de reenvio (spec 012) ou a retenção de mídia.
- Reiniciar o Horizon automaticamente após deploy (apontamento de infraestrutura).
- Cancelar post em `processing` (hoje 409, de propósito).

## Questões em aberto

1. Frequência do comando (sugestão: a cada 5 min).
2. Mensagens exatas em pt-BR (sugestão para falha: "A publicação demorou mais que o esperado e foi interrompida. Você pode reenviar o post."; sem como conferir: "Não foi possível confirmar a publicação. Confira na sua conta da rede antes de reenviar.").
3. Log (só código e id do post) quando o comando mudar um post? Sugestão: sim.
4. Critério 6 e RF-8: confirmar que a promoção tardia vale só para post ainda `failed` e nunca para um que já voltou a `pending` pelo reenvio.
