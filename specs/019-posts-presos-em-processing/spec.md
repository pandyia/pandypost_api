# Spec 019 — Posts presos em `processing`

**Status:** rascunho, **aguardando aprovação** (nada implementado) · **Área crítica:** esteira de publicação

## Contexto

Todo post precisa terminar em um estado final: `published` quando a rede aceitou, `failed` quando não. Hoje isso vale nos caminhos normais (`PublishPostJob::failed()`, `CheckTikTokPostStatusJob`, `CheckInstagramContainerJob`), mas **não há rede de segurança** se o job morrer sem passar por eles: worker derrubado, timeout, reinício do Horizon no meio do envio, job perdido na fila.

Caso real (06/11/2026, TikTok): os posts 27 e 28 ficaram em `processing` por horas, sem erro e sem opção de reenvio, porque o Horizon seguia com código antigo e o job nunca chegou ao `failed()`. Só voltaram a ser utilizáveis porque o estado foi corrigido à mão no banco. Enquanto está em `processing`, o usuário não consegue cancelar (409) nem reenviar (a spec 012 só reenvia `failed`).

## Escopo

Um comando agendado que fecha como `failed` os posts parados em `processing` além de um limite de tempo, para que o usuário possa reenviá-los pelo fluxo da spec 012.

## Requisitos

- **RF-1** Post em `processing` há mais tempo que o limite da plataforma vira `failed`, com `failed_at` preenchido e `error_message` fixo em pt-BR (texto do enum de erro, nunca texto de terceiro).
- **RF-2** Limites por plataforma (aprovados): **TikTok 30 min, YouTube 30 min, Instagram 60 min** (o contêiner de vídeo do Instagram demora mais). A contagem parte da última atualização do post em `processing`.
- **RF-3** Só mexe em posts `processing`. `pending`, `published`, `failed` e `cancelled` nunca são alterados.
- **RF-4** Nunca sobrescreve um estado final: a troca é condicional (`WHERE status = 'processing'`), então um job que termine no mesmo instante não é desfeito.
- **RF-5** Mídia: o post `failed` mantém o arquivo, como nos demais caminhos, e segue a retenção de 5 dias (spec 012). Assim o reenvio continua possível.
- **RF-6** O usuário é avisado pelo caminho já existente (`NotificationService::notifyPostFailed`), sem tipo novo de notificação.
- **RF-7** Roda a cada poucos minutos (frequência a definir no plan) e é idempotente: rodar duas vezes seguidas não gera segunda notificação.
- **RF-8** Um post que o comando fechou e que depois o job conclui com sucesso (job apenas atrasado) não deve ficar `failed` para sempre. Comportamento a definir (ver Questões em aberto).

## Contrato de API

Nenhum endpoint novo. O post fechado pelo comando aparece como `failed` e `can_retry = true` na listagem existente, e o reenvio usa `POST /scheduled-posts/{uuid}/retry`.

## Critérios de aceite

1. Post TikTok em `processing` com 31 min sem atualização vira `failed` com mensagem pt-BR; com 29 min, não muda.
2. Post Instagram em `processing` com 45 min não muda; com 61 min vira `failed`.
3. Posts em outros status não são tocados.
4. O post fechado tem `failed_at` preenchido e dispara uma única notificação, mesmo com duas execuções do comando.
5. Depois de fechado, o post pode ser reenviado por `/retry` e volta a `pending`.
6. Post que passa a `published` entre a leitura e a gravação não é sobrescrito.
7. Nenhuma mensagem expõe token, body ou URL de terceiro.

## Fora de escopo

- Corrigir a causa de cada travamento (código antigo no Horizon, timeouts de cada rede).
- Mudar o fluxo de reenvio (spec 012) ou a retenção de mídia.
- Reiniciar o Horizon automaticamente após deploy (apontamento de infraestrutura).
- Cancelar post em `processing` (hoje 409, de propósito).

## Questões em aberto

1. **RF-8:** se o job concluir depois de o comando fechar o post, o post deve ser promovido a `published`, ou o estado `failed` prevalece e o usuário reenvia (com risco de publicar duas vezes)? Hoje `PublishPostJob::failed()` ignora estado final, mas não há regra para o caso inverso. Regra de negócio: precisa de decisão.
2. A frequência do comando (a cada 5 min?).
3. Mensagem exata em pt-BR (sugestão: "A publicação demorou mais que o esperado e foi interrompida. Você pode reenviar o post.").
4. Deve existir também um aviso nos logs (só código e id do post) quando o comando fechar um post?
