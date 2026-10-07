# Spec 005 — Esteira de publicação (correções e base comum)

**Status:** aprovada (2026-10-06) · **Tipo:** correção + consolidação · **Última revisão:** 2026-10-06

> Área crítica (esteira de publicação). A seção **[A]** é a spec reversa do comportamento atual. Esta spec vem antes das specs 003 (YouTube) e 004 (Instagram), porque as duas usam a base comum criada aqui.

---

## Contexto

Toda publicação passa pelo `PublishPostJob` (uma fila por plataforma), que chama o service da plataforma. Ao terminar (sucesso, falha ou cancelamento), o post é marcado e a mídia sai do S3. Hoje cada plataforma repete "marcar como publicado", "marcar como falho" e "limpar arquivos" do seu jeito, e a esteira tem bugs que afetam as três plataformas.

---

## [A] Comportamento atual (spec reversa)

### A.1 `PublishPostJob`

- Fila = plataforma do post; `tries = 3`, **sem intervalo** entre as tentativas; `timeout = 180 s`.
- `handle`: se `user->hasValidSubscriptionForPublishing()` for falso → `\Exception` "Assinatura Inativa ou Expirada…" (hoje sempre verdadeiro: limites deferidos na spec 001). Conta inexistente ou de outro usuário → `\Exception` "Conta vinculada ao agendamento não encontrada.". Senão, `SocialMediaFactory` → `upload()`.
- `failed`: marca `failed` com `substr($mensagem, 0, 255)` e apaga mídia + thumbnail com **`deleteMany`**.

### A.2 Cancelamento (`DELETE /api/scheduled-posts/{uuid}`)

Só posts `pending` do próprio usuário (senão 409 `cancel_not_allowed`). Marca `cancelled` e apaga mídia + thumbnail com **`deleteMany`**.

### A.3 Mídia compartilhada

Agendar para N contas cria N posts com o **mesmo** `media_path`. `StorageService::deleteIfUnused` só apaga um arquivo se nenhum outro post `pending`/`processing` o usa; `deleteMany` apaga sem checar.

---

## Problemas corrigidos

| # | Problema | Efeito |
|---|---|---|
| E1 | `PublishPostJob::failed()` apaga a mídia com `deleteMany`. | Falha em uma conta apaga o vídeo que as outras contas ainda vão publicar. |
| E2 | Cancelar um post apaga a mídia com `deleteMany`. | Cancelar uma conta de um agendamento múltiplo quebra a publicação das outras. |
| E3 | `substr(..., 255)` corta por bytes. | Mensagem pt-BR com acento na posição 255 vira UTF-8 inválido e o PostgreSQL recusa o update: o post fica preso em `processing`. |
| E4 | `timeout = 180 s` no job sobrepõe o do supervisor (300 s em YouTube e TikTok). | Uploads grandes são interrompidos no meio e recomeçam do zero. |
| E5 | Novas tentativas imediatas. | Erros temporários (rede, limite de requisições) gastam as 3 tentativas em segundos. |
| E6 | `\Exception` genérica no job. | Fora do padrão de erros de domínio. |
| E7 | A checagem de "arquivo em uso" só olha o `media_path` dos outros posts (achado na implementação). | A thumbnail compartilhada no agendamento múltiplo é apagada enquanto outro post ainda vai usá-la. |

---

## Escopo

1. **Base comum no model e no storage**, usada pelas 3 plataformas:
   - `ScheduledPost::markAsPublished(?string $platformPostId)` e `ScheduledPost::markAsFailed(string $message)` (mensagem cortada em 255 **caracteres**).
   - `StorageService::deletePostMedia(ScheduledPost $post)`: apaga mídia + thumbnail só se nenhum outro post ativo usa.
2. Corrigir E1–E7.
3. TikTok passa a usar a base comum (sem mudar comportamento).

---

## Requisitos

- **RF-1** Falha definitiva, cancelamento ou publicação de um post apagam a mídia **só** quando nenhum outro post `pending`/`processing` a usa.
- **RF-2** A mensagem de erro gravada no post tem no máximo 255 caracteres e sempre é UTF-8 válido.
- **RF-3** O tempo máximo de um upload é o do supervisor da fila da plataforma (`config/horizon.php`).
- **RF-4** Entre as tentativas do `PublishPostJob` há espera de 30 s e depois 2 min.
- **RF-5** Erros do job são de domínio: assinatura inativa → `SubscriptionException::subscriptionInactive`; conta ausente → `ScheduledPostException::noAccountLinked`.

---

## Contrato de API

Sem mudanças de rota ou formato. `DELETE /api/scheduled-posts/{uuid}` continua igual; só deixa de apagar a mídia de outros posts.

---

## Critérios de aceite

- **CA-1** Agendar para 2 contas e cancelar uma → o arquivo continua no S3; cancelar a última → o arquivo sai.
- **CA-2** `PublishPostJob::failed` com outro post pendente usando a mesma mídia → mídia mantida; post marcado `failed`.
- **CA-3** Mensagem com 300 caracteres acentuados → `error_message` com 255 caracteres e UTF-8 válido.
- **CA-4** `PublishPostJob` sem `timeout` próprio e com `backoff` `[30, 120]`.
- **CA-5** Conta removida antes da publicação → `ScheduledPostException` (`no_account_linked`).
- **CA-6** Testes do TikTok continuam verdes sem alterar asserções.
- **CA-7** Isolamento: usuário de outro workspace não cancela o post (404).

---

## Fora de escopo

- Não tentar de novo erros definitivos (ex.: app não auditado no TikTok). Exigiria classificar os erros de cada plataforma; as 3 tentativas com intervalo resolvem o caso comum.
- Regra "a conta precisa ser do mesmo usuário que agendou" (A.1): mantida como está.

## Questões em aberto

Nenhuma.
