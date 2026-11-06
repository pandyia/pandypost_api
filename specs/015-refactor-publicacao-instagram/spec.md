# 015 — Refatoração: publicação no Instagram no molde da spec 011

> Depende da [014](../014-refactor-publicacao-comum/spec.md) (`ScheduledPost::mediaPaths`, base OAuth) e da 011 commitada. Área crítica (esteira de publicação + OAuth social): caracterização antes de mexer.

## Contexto (comportamento atual)
- `PublishPostJob` (fila `instagram`) chama `InstagramService::upload()`: marca `processing`, obtém token (`getValidToken`), e: se o post já tem container válido (24 h) consulta o status — `FINISHED` publica, `ERROR`/desconhecido zera o container e cria outro, em andamento despacha `CheckInstagramContainerJob`; sem container, cria um (`/media`, `REELS` + `video_url` para vídeo, `image_url` para imagem), grava `container_id`/`container_created_at` e despacha o job.
- `CheckInstagramContainerJob` (fila `instagram`, `tries=15`, backoff 2–30 s) consulta `status_code`; `FINISHED` publica (`media_publish`) e grava `published` + `platform_post_id` + permalink; `ERROR` zera o container e marca `failed`; outro valor reagenda.
- `posts:warmup` chama `InstagramService::prepare()` (cria/confere o container sem publicar) para posts dos próximos 60 min.
- Erro da Graph API vira texto com o **JSON cru** da resposta em `error_message`.
- Renovação do token (`graph.instagram.com/refresh_access_token`) vive em `SocialAccount`; a troca de código, o perfil e o token de longa duração vivem no `InstagramOAuthProvider`.

## Escopo fechado
`app/Services/InstagramService.php`, `app/Jobs/CheckInstagramContainerJob.php`, `app/Services/OAuthProviders/InstagramOAuthProvider.php`, `app/Console/Commands/BackfillInstagramPermalinksCommand.php` (só a dependência), `app/Models/SocialAccount.php` (só `refreshInstagramToken`), `app/Services/Payloads/Builders/InstagramPayloadBuilder.php`; novos `app/Services/Instagram/InstagramApi.php`, `app/Enums/Exceptions/InstagramError.php`, `app/Exceptions/InstagramException.php`.

## Achados

| # | Onde | Regra violada | Correção | Ganho |
|---|------|---------------|----------|-------|
| I1 | `v25.0`/`graph.instagram.com` em `InstagramService.php:18-20,213`, `CheckInstagramContainerJob.php:22-24,67-75,91-100`, `InstagramOAuthProvider.php:61,100`, `SocialAccount.php:207` (+ `InstagramAnalyticsProvider.php:29`) | Duplicação real (5 arquivos) + `Http::` espalhado (molde 011 D3) | `InstagramApi`: `containerStatus`, `createContainer`, `publish`, `permalink`, `profile`, `longLivedToken`, `refreshToken`; URL e versão num só lugar | subir de versão da Graph API = 1 constante; ~40 linhas de `Http::` a menos |
| I2 | `InstagramService.php:124-163` vs `CheckInstagramContainerJob.php:91-133` | Duplicação real: publicar container + permalink + marcar `published` (já divergiram: só o service limpa o S3) | `InstagramService::publishContainer()` público, devolve `bool`; o job chama; o service faz a limpeza depois | ~35 linhas a menos; um lugar para mudar |
| I3 | `InstagramService.php:132-136,205-207`, `CheckInstagramContainerJob.php:102-103` | Erro de domínio sem enum/exception (`throw new Exception("Erro ao criar container: {json}")`); mensagem monta `json_encode($response)` (vaza resposta de terceiro para `error_message`, exposto por `ScheduledPostResource`); log com a resposta inteira | `InstagramError` + `InstagramException::fromApiError(?array)` mapeando `error.code`; `ConnectionException` vira `InstagramException::connectionFailed()` (a mensagem original traz a URL **com `access_token`**) | sem body/token em mensagem; `error_message` em pt-BR |
| I4 | `CheckInstagramContainerJob.php:154` | `\Exception` genérica | `InstagramException::containerFailed()` (ou removida junto com `fail()`, ver Q3) | erro tipado |
| I5 | `InstagramService.php:117` | `catch (Exception)` onde cabe tipo específico (e engole qualquer coisa como "container inválido") | `catch (InstagramException)` | falha inesperada deixa de virar "recria o container" |
| I6 | `InstagramService.php:159`, `CheckInstagramContainerJob.php:190` | Código morto (comentário "FASE 2... removida") | remover | -2 linhas |
| I7 | `InstagramService.php:171-183` | Reuso: `fetchPermalink` é público no service só para o job e o comando de backfill | vira `InstagramApi::permalink()`; os 3 chamadores usam o cliente | service sem método público estranho |

**Não vira achado** (descartado): trocar o `bool $shouldPublish` por enum/dois métodos (a flag atravessa `processContainer`, `handleExistingContainer` e **o construtor do job serializado**; renomear quebra jobs na fila e o ganho não cabe em uma frase); enum para `FINISHED/ERROR/IN_PROGRESS` (3 comparações com `match`/`if` claros); extrair `retryWithBackoff()/backoff()` (só 2 cópias com o job do TikTok); `catch (Exception)` de `processContainer` (:66, melhor-esforço no warmup, comentado, não muda).

## Requisitos
- RF-1: chamadas à Graph API, o fluxo de container e os campos gravados não mudam; a estrutura passa a ser `InstagramApi`.
- RF-2: `publishContainer` existe em um só lugar; o service continua limpando o S3 após publicar e o job continua **sem** limpar (a divergência atual fica preservada; ver Apontamento 1).
- RF-3: erros da Graph API viram `InstagramException` com mensagem pt-BR; nenhum body, URL com token ou `$e->getMessage()` de terceiro em `error_message`, notificação ou log. **Muda o texto de `error_message`** — depende de Q1.
- RF-4: `fetchStatus` que falha por conexão continua tratado como "container desconhecido" no service (recria) e propaga no job (reagenda via retry), como hoje.
- RF-5: renovação de token Instagram usa `InstagramApi::refreshToken()`; a mensagem de falha **continua** com o body até Q2 ser respondida.

## Critérios de aceite
- Suíte do escopo igual ou melhor; nenhum teste existente alterado. Exceções (apenas com aprovação, ver Q2): `SocialAccountTokenTest.php:113` e `RefactorModelsRegressionTest.php:28`, que fixam o body na mensagem.
- `Http::fake` continua sendo a única fronteira; nenhuma chamada real.
- `grep -rn "graph.instagram.com" app` fora de `InstagramApi` (e do provider de analytics) = vazio.

## Fora de escopo
`Platform::publishScope()` para Instagram e gravar `scopes` na conexão (mudaria `can_publish` de todas as contas já conectadas, que têm `scopes` nulo; ver Q4); `InstagramAnalyticsProvider` (só pode ler a constante de versão); `state` do OAuth; `PublishPostJob::failed()`.

## Apontamentos (bugs e riscos, não corrigir aqui)
1. **S3 nunca limpo no caminho principal.** O fluxo normal (cria container → job publica) termina em `CheckInstagramContainerJob::markAsPublished` (`:111-133`), que **não** chama `deleteIfUnused`; só o caminho "container já pronto" (`InstagramService.php:161-162`) limpa. Vídeos de posts publicados ficam no S3 para sempre. (011 RF-6 pede o contrário.) Correção de uma linha, mas muda comportamento: Q3.
2. **Post preso em `processing`.** O job não tem `failed()`; esgotadas as 15 tentativas com o container sempre em andamento, o post nunca sai de `processing` (o TikTok recebeu `failed()` por isso na 011 RF-7).
3. `CheckInstagramContainerJob.php:154` chama `$this->fail()` depois de marcar `failed`: gera `failed_jobs` duplicado do erro já tratado (o TikTok removeu isso na 011).
4. `Log::error(..., ['response' => $response])` (`CheckInstagramContainerJob.php:102`) grava a resposta inteira da Meta no log.
5. `PublishPostJob` tenta 3× erros permanentes (token inválido, formato) — igual ao TikTok.

## Questões em aberto
- **Q1** — Mapeamento de erros da Graph API: texto pt-BR por código. Proposta inicial (códigos **a confirmar na doc da Meta antes de implementar**): 190 → "acesso expirado ou revogado, reconecte"; 4/17/32/613 → "limite de chamadas, tente mais tarde"; 100/formato de mídia → "mídia não aceita pelo Instagram"; demais → genérico. Aprova o conjunto e que `error_message` deixe de trazer o JSON?
- **Q2** — Retirar o body da mensagem de renovação de token (Google e Instagram), como a 011 fez no TikTok? Exige alterar 3 testes que hoje fixam o body (`SocialAccountTokenTest.php:84,113`; `RefactorModelsRegressionTest.php:28`). Sem aprovação, nada disso é tocado.
- **Q3** — Alinhar o job ao molde (muda comportamento; cada item é independente): (a) `failed()` por tempo esgotado → `failed` (Apontamento 2); (b) limpar S3 ao publicar no job (Apontamento 1); (c) remover `$this->fail()` (Apontamento 3). Recomendo (a), (b) e (c).
- **Q4** — Ativar `Platform::publishScope()` do Instagram (`instagram_business_content_publish`) e gravar `scopes`? É feature (muda `can_publish`; contas atuais precisariam reconectar ou de backfill), não refatoração.

## Decisões (Gabriel delegou — 2026-11-05)
- Q1: **sim**, `error_message` com texto pt-BR mapeado no lugar do JSON cru (confirmar códigos da Meta na doc antes de implementar; avisar no release).
- Q2: **sim**, retirar o body da mensagem de renovação de token (Google e Instagram), ajustando os 3 testes que fixam o body sem perder asserção.
- Q3: **sim** às três: `failed()` por tempo esgotado, limpar S3 ao publicar, remover `$this->fail()` duplicado.
- Q4: **não** ativar `publishScope` do Instagram agora (é feature e mudaria `can_publish` das contas já conectadas); spec própria depois.
