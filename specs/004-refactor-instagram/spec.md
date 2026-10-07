# Spec 004 — Refatoração do Instagram (publicação, warmup e conexão)

**Status:** aprovada (2026-10-06) · **Tipo:** refatoração + correções aprovadas · **Última revisão:** 2026-10-06

> **Refatoração + correções:** o objetivo é deixar o código do Instagram enxuto e confortável de ler, no padrão do TikTok (spec 002). O contrato da API não muda; os comportamentos que mudam são só as correções aprovadas na seção **Decisões**. Depende da base comum da spec 005. Área crítica (OAuth social + esteira de publicação): a seção **[A]** é a spec reversa do comportamento atual, e **não há nenhum teste do Instagram hoje** — os testes de caracterização vêm antes de qualquer mudança.

---

## Contexto

O Instagram funciona em produção: conexão via Instagram Login (API com login do Instagram, `graph.instagram.com`), publicação de Reels e imagens por **container** e um **warmup** que cria o container até 1 h antes do horário, para o post sair na hora certa. O código tem pontos que dificultam a leitura:

- A lógica de "consultar status do container" e "publicar container" está **duplicada** entre `InstagramService` e `CheckInstagramContainerJob`, e as duas cópias já divergem (ver Apontamentos I1).
- Versão e URL da Graph API repetidas em 3 classes.
- `InstagramService` tem imports sem uso (`PublishPostJob`, `Storage`) e `\Exception` genérica.
- `InstagramPayloadBuilder` é só um comentário (não acrescenta nada à classe-base).

---

## [A] Comportamento atual (spec reversa)

### A.1 Conexão (OAuth)

| Etapa | Onde | O que acontece |
|---|---|---|
| Iniciar | `GET /api/social-accounts/instagram/auth` → `InstagramOAuthProvider::getRedirectUrl` | Exige workspace corrente (senão 500 `oauth_initialization_failed`). URL `https://www.instagram.com/oauth/authorize` com `client_id` (Meta app id), `redirect_uri`, `response_type=code`, escopos `instagram_business_basic`, `instagram_business_content_publish`, `instagram_business_manage_messages` e `state = encrypt(workspace.uuid)`. |
| Callback | `GET /api/social-accounts/instagram/callback` | Mesmo fluxo das outras plataformas (`?success=1` / `?error=`). |
| Trocar código | `syncAccount` | Sem `code` → `authFailed('Instagram')`. `POST api.instagram.com/oauth/access_token` (token curto). Sem `access_token` ou `user_id` → `authFailed('Instagram')`. |
| Perfil | `syncAccount` | `GET graph.instagram.com/v25.0/me?fields=id,username` → `nickname` (padrão `Instagram User`). Avatar não é buscado (`null`). |
| Token longo | `exchangeForLongLivedToken` | `GET graph.instagram.com/access_token` (`ig_exchange_token`) → token de ~60 dias. **Se falhar, guarda o token curto com validade de 1 h, sem avisar.** |
| Persistir | `syncAccount` | `updateOrCreate` por (`workspace_id`, `platform=instagram`, `platform_id = user_id`), `refresh_token = null`. |

### A.2 Token

- `getValidToken()` renova quando falta ≤ 5 min: `GET graph.instagram.com/refresh_access_token` (`ig_refresh_token`), validade padrão 5.184.000 s (60 dias). Falha → `\Exception` genérica.
- Desconectar: o Instagram não tem endpoint de revogação; só apaga o registro (e cancela posts `pending`).

### A.3 Agendamento

Sem campos próprios do Instagram: usa `caption` e a mídia (`videos/` ou `images/`). O `InstagramPayloadBuilder` não acrescenta nada além do `thumbnail_path` da classe-base.

### A.4 Warmup (`posts:warmup`, a cada minuto)

1. Busca posts `instagram` `pending` com `scheduled_at` nos próximos 60 min, que **não** tenham container válido.
2. Para cada um, `InstagramService::prepare()` cria o container **sem publicar** e despacha `CheckInstagramContainerJob` com `shouldPublish = false`. Erros só geram log.
3. Container válido = tem `container_id` e `container_created_at`, o post não está `failed` e foi criado há menos de 24 h.

### A.5 Publicação (`PublishPostJob` → `InstagramService::upload`, fila `instagram`)

1. Post → `processing`. Obtém token válido.
2. **Se já existe container válido**, consulta o status (`GET /{container}?fields=status_code`):
   - `FINISHED` → publica na hora (A.6, caminho do service).
   - `ERROR` ou sem status → limpa o container e cria outro (passo 3).
   - Outro (ex.: `IN_PROGRESS`) → despacha o job de acompanhamento e termina.
3. **Cria o container**: gera URL pré-assinada de leitura do S3 (30 min); envia `caption` e, se a extensão for `mp4/mov/avi/mkv`, `media_type=REELS` + `video_url`, senão `image_url`. Sem `id` na resposta → `\Exception` "Erro ao criar container: {json}".
4. Grava `container_id` / `container_created_at` e despacha `CheckInstagramContainerJob` (`shouldPublish = true`).
5. Exceção → log; no `upload` é repassada para o `PublishPostJob` (3 tentativas, depois `failed` + apaga arquivos); no `prepare` (warmup) é engolida.

### A.6 Publicar o container (existe em **dois** lugares)

| | `InstagramService::publishContainer` (container já pronto no upload) | `CheckInstagramContainerJob::publishContainer` (container ficou pronto no acompanhamento) |
|---|---|---|
| Chamada | `POST /{ig-user-id}/media_publish` com `creation_id` | igual |
| Erro | `failed`, `error_message = "Erro na publicação: {json}"` (sem limite de tamanho) | `failed`, mesma mensagem cortada em 255 |
| Sucesso | `published`, `platform_post_id`, `published_at` **e apaga mídia + thumbnail** (`deleteIfUnused`) | `published`, `platform_post_id`, `published_at` — **não apaga os arquivos** |

### A.7 Acompanhamento (`CheckInstagramContainerJob`, fila `instagram`)

- Até 15 tentativas, `release()` com 2, 4, 8, 16, 30, 30… s (≈ 5–6 min).
- `FINISHED` → com `shouldPublish`, publica (A.6, coluna da direita); sem, só registra que o warmup terminou.
- `ERROR` → limpa o container, marca `failed` ("Erro no processamento do container pelo Instagram") e falha o job.
- Outro status → tenta de novo. Tentativas esgotadas → o job falha **sem `failed()`**: o post fica em `processing`.

Link do post: `https://www.instagram.com/p/{platform_post_id}`.

---

## Escopo

**Dentro:**

1. **Testes de caracterização** de tudo em [A] antes de mexer no código, incluindo as diferenças da tabela A.6 (o teste registra o comportamento atual, mesmo quando ele é um problema).
2. Uma fonte só para URL/versão da Graph API (constantes).
3. `InstagramService` e `CheckInstagramContainerJob`: métodos curtos e nomeados; "status do container" e "publicar" passam a existir **em um lugar só** (o service), remoção dos imports sem uso, erros de domínio no lugar de `\Exception`.
4. `InstagramOAuthProvider`: constantes, `Platform::INSTAGRAM`, métodos curtos.
5. `SocialAccount::refreshInstagramToken`: erro de domínio (`SocialAccountException::tokenRefreshFailed`).
6. `WarmupPostsCommand`: enxugar (resolver o service uma vez, `Platform::INSTAGRAM` na query).
7. `InstagramPayloadBuilder`: remover o corpo vazio/comentado (a classe fica, pois a factory a usa).
8. **Correções aprovadas:** I1 a I7.

---

## Requisitos

- **RF-1** Conectar, renovar token, desconectar, agendar, warmup, publicar e acompanhar continuam com o comportamento de [A].
- **RF-2** As chamadas à Graph API continuam iguais (endpoints, versão `v25.0`, parâmetros), exceto as correções abaixo.
- **RF-3** Publicar um container, em qualquer caminho, apaga a mídia do S3 se ela não estiver em uso (I1).
- **RF-4** O acompanhamento dura até 30 min; ao esgotar (ou com erros repetidos), um post em publicação fica `failed` e a mídia sai do S3. No warmup, esgotar não altera o post (I2).
- **RF-5** Container com `ERROR` durante o **warmup** só é descartado (o post continua `pending` e o container é recriado na publicação); durante a **publicação**, o post fica `failed` (I2).
- **RF-6** Após publicar, o sistema busca o `permalink` da mídia e o `platform_post_url` passa a ser esse link; sem permalink, `null` (I3).
- **RF-7** Um comando diário renova os tokens do Instagram que vencem nos próximos 7 dias (I4).
- **RF-8** Se a troca pelo token longo falhar na conexão, a conexão é recusada com erro (`token_exchange_failed`), em vez de salvar um token de 1 h (I5).
- **RF-9** Os escopos pedidos são `instagram_business_basic` e `instagram_business_content_publish` (I6).
- **RF-10** Falha ao publicar grava `Falha ao publicar no Instagram: {motivo}` em vez do JSON cru (I7).
- **RNF-1** Contrato da API inalterado (rotas, status, mensagens de redirect, campos do post).
- **RNF-2** Código no padrão do CLAUDE.md (enxuto, legível, constantes nomeadas, sem código morto ou duplicado).
- **RNF-3** Testes mockam a Graph API (`Http::fake`), nenhuma chamada real.

---

## Contrato de API

Sem mudanças. Rotas e comandos cobertos pelos testes de caracterização:

| Método | Rota / comando | Permissão |
|---|---|---|
| GET | `/api/social-accounts/instagram/auth` | `social_accounts.connect` |
| GET | `/api/social-accounts/instagram/callback` | pública (`state`) |
| DELETE | `/api/social-accounts/{uuid}` | `social_accounts.disconnect` |
| POST | `/api/scheduled-posts` | `posts.create` |
| — | `php artisan posts:warmup` (agendado a cada minuto) | — |

---

## Critérios de aceite

- **CA-1** Existem testes de caracterização (em `tests/Feature/Instagram/`) para cada item de [A], escritos e **verdes antes** da refatoração.
- **CA-2** Os mesmos testes continuam verdes **depois**; só mudam as asserções das correções I1–I7, cada mudança registrada nas Divergências do `tasks.md`.
- **CA-3** Reels (`.mp4`, `.mov`) vão com `media_type=REELS` + `video_url`; imagens com `image_url`; ambos com a `caption`.
- **CA-4** Warmup: só posts `pending` do Instagram na janela de 60 min e sem container válido; cria o container sem publicar; erro de um post não interrompe os outros.
- **CA-5** Container existente: `FINISHED` publica na hora, `ERROR`/sem status recria, `IN_PROGRESS` só acompanha.
- **CA-6** Token longo falhando na conexão → redirect com `?error=` e nenhuma conta salva (I5).
- **CA-7** Isolamento: callback com `state` do workspace A nunca cria conta no workspace B.
- **CA-8** Suíte completa verde; Pint nos arquivos alterados.
- **CA-9** Publicação pelo job de acompanhamento apaga a mídia; com outro post pendente usando o mesmo arquivo, mantém (I1).
- **CA-10** `platform_post_url` = `permalink` devolvido pela Graph API (I3).
- **CA-11** `instagram:refresh-tokens` renova só contas do Instagram com token vencendo em até 7 dias e ainda válido; falha em uma conta não interrompe as outras (I4).

---

## Fora de escopo

- Buscar avatar, capa (`cover_url`) de Reels, carrossel, stories ou colaboradores.
- Esteira compartilhada (`PublishPostJob`): spec 005.
- Ler mensagens (DMs): não é usado; o escopo sai (I6).

## Problemas encontrados (todos corrigidos nesta spec)

| # | Problema | Efeito |
|---|---|---|
| I1 | Quando a publicação acontece no job de acompanhamento (o caso mais comum), a mídia **não é apagada** do S3 (A.6). | Vídeos e imagens se acumulam no storage. |
| I2 | Tentativas esgotadas no acompanhamento deixam o post em `processing` para sempre (mesmo problema que o TikTok tinha). | Post nunca termina e a mídia fica no S3. (No warmup não há efeito: o post segue `pending` e o status do container é consultado de novo na hora de publicar.) |
| I3 | O link usa o **id da mídia** (`/p/{id}`), mas o Instagram usa o **shortcode** nessa URL. O link certo vem do campo `permalink` da mídia. | `platform_post_url` do Instagram abre uma página inexistente. |
| I4 | O token longo do Instagram vale 60 dias e só é renovado quando falta ≤ 5 min **e** a conta é usada. Token vencido não pode mais ser renovado. | Conta sem postar por ~60 dias perde o acesso e precisa reconectar. |
| I5 | Se a troca pelo token longo falha, a conta é salva com o token curto (1 h) sem avisar ninguém. | A conta "conectada" para de funcionar em 1 h. |
| I6 | O escopo `instagram_business_manage_messages` é pedido, mas nada no sistema lê mensagens. | Escopo a mais pesa na revisão da Meta e assusta o usuário na tela de consentimento. |
| I7 | `"Erro na publicação: {json}"` grava a resposta crua da Graph API no `error_message`. | Mensagem técnica em inglês exibida ao usuário. |

---

## Decisões (2026-10-06)

- **D1 — Unificar "publicar container":** sim. Um só caminho no service, e ele apaga a mídia (corrige I1).
- **D2 — Correções:** I1 a I7 nesta spec.
- **D3 — Renovação de token (I4):** comando `instagram:refresh-tokens`, diário às 03:00, para tokens que vencem em até 7 dias.
- **D4 — Escopo de mensagens (I6):** removido; o sistema só publica. Contas já conectadas não precisam reconectar.

## Questões em aberto

Nenhuma.
