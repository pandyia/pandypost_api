# Tasks 015 — Instagram no molde da 011

> Uma mudança por tarefa, do menor risco e maior valor ao maior risco. Ao fim de cada uma: `docker exec pandypost-php php artisan test` + `pint --dirty`. Só após aprovação e depois da 014. `Http::fake` + `Http::preventStrayRequests()` em todos os testes novos.

## 0. Caracterização (sem tocar em `app/`)
Já coberto (não duplicar): job `FINISHED` com permalink / sem permalink / sem conexão, container `ERROR` no job, `media_publish` com erro (job e service), container pronto no service, backfill de permalinks, renovação de token.
- [x] 0.1 `InstagramService::upload` sem container: vídeo cria container `REELS` com `video_url`, imagem usa `image_url`; grava `container_id`/`container_created_at`; despacha `CheckInstagramContainerJob` (`Queue::fake`) com `shouldPublish=true`; resposta sem `id` lança `Exception` e não grava container — teste: `tests/Feature/Instagram/InstagramPublishFlowTest.php`
- [x] 0.2 Container existente: `ERROR` e resposta sem `status_code` zeram e recriam; em andamento despacha o job sem criar; falha de conexão na consulta recria; container com > 24 h é ignorado — teste: mesmo arquivo
- [x] 0.3 `prepare()`: não publica, despacha job com `shouldPublish=false`, engole erro; `upload()` propaga o erro; `posts:warmup` só pega Instagram pendente na janela de 60 min e pula post com container válido — teste: `tests/Feature/Instagram/InstagramWarmupTest.php`
- [x] 0.4 Job: em andamento reagenda com o backoff `[2,4,8,16,30]`; `FINISHED` com `shouldPublish=false` não publica; sucesso do job **não** apaga o S3 (registra o Apontamento 1 como comportamento atual) — teste: `tests/Feature/Instagram/InstagramContainerJobTest.php`
- [x] 0.5 Tudo que sai do Instagram para `error_message` hoje (resposta de erro no `media_publish`, falha na criação do container): o teste exige `failed` e mensagem não vazia, **sem fixar o JSON** (será trocado na 4.1) — teste: mesmo arquivo
- [x] 0.6 `InstagramOAuthProvider::syncAccount` (coberto pela 014 tarefa 0.3; conferir)

## 1. Baixo risco
- [x] 1.1 Remover os comentários "FASE 2" (service e job) e o override vazio de `InstagramPayloadBuilder::extractPayload` — teste: 0.* + `InstagramPermalinkTest`

## 2. Cliente HTTP (um chamador por tarefa)
- [x] 2.1 Criar `InstagramApi` (`containerStatus`, `createContainer`, `publish`, `permalink`, `profile`, `longLivedToken`, `exchangeCode`, `refreshToken`) com testes diretos via `Http::fake`, **sem trocar chamadores** — teste: `tests/Feature/Instagram/InstagramApiErrorsTest.php` (sucesso e falha de cada método)
- [x] 2.2 `InstagramService` usa `InstagramApi`; `buildApiUrl` e as constantes saem; `fetchPermalink` sai (I7) — teste: 0.1–0.3 + `InstagramPermalinkTest`
- [x] 2.3 `CheckInstagramContainerJob` usa `InstagramApi` — teste: 0.4 + `RetryScheduledPostCoverageTest`, `PostRetrySecurityTest`
- [x] 2.4 `BackfillInstagramPermalinksCommand` injeta `InstagramApi` — teste: `InstagramPermalinkTest` (grupo `instagram:backfill-permalinks`)
- [x] 2.5 `InstagramOAuthProvider` usa `profile`, `longLivedToken`, `exchangeCode` — teste: 0.6
- [x] 2.6 `SocialAccount::refreshInstagramToken` usa `InstagramApi::refreshToken` (mensagem com body preservada) — teste: `SocialAccountTokenTest`

## 3. Duplicação
- [x] 3.1 `InstagramService::publishContainer()` público devolvendo `bool`; o job passa a chamá-lo e perde `publishContainer`/`markAsPublished`; limpeza do S3 só no service — teste: 0.4 + `InstagramPermalinkTest` + `RetryScheduledPostCoverageTest`

## 4. Erros de domínio (RF-3, depende de Q1)
- [x] 4.1 `InstagramError` + `InstagramException`; `InstagramApi` converte erro de resposta e `ConnectionException`; service e job deixam de montar `json_encode`; `catch (Exception)` de `getContainerStatus` vira `catch (InstagramException)`; `new Exception` do job vira exceção tipada; logs sem resposta crua — teste: `InstagramApiErrorsTest` (nenhum token/body em `error_message`, notificação ou log) + 0.5 + `PostRetrySecurityTest`

## 5. Condicionais (Q2/Q3/Q4; cada uma só com "sim")
- [x] 5.1 (Q3a) `CheckInstagramContainerJob::failed()` marca `failed` por tempo esgotado — teste: novo, estilo `CheckTikTokPostStatusJobTest`
- [x] 5.2 (Q3b) Job limpa o S3 ao publicar — teste: atualiza 0.4 (mudança aprovada de comportamento)
- [x] 5.3 (Q3c) Remover `$this->fail()` de `handleError` — teste: `RetryScheduledPostCoverageTest` (já tolera ambos)
- [x] 5.4 (Q2) Mensagem de renovação do Instagram sem body — teste: ajustar `SocialAccountTokenTest.php:113` e `RefactorModelsRegressionTest.php:28`
- [x] 5.5 (opcional) `InstagramAnalyticsProvider` lê `InstagramApi::GRAPH_URL` — teste: `InstagramAnalyticsTest`

## Divergências

- **Ordem:** `InstagramError`/`InstagramException` (4.1) foram criados junto com `InstagramApi` (2.1), porque o cliente já converte erro em exceção tipada; `InstagramApiErrorsTest` cobre sucesso e falha de cada método desde o início.
- **3.1 / D3:** `publishContainer()` ficou `void` (não `bool`) e **faz a limpeza do S3 dentro dele**, porque com a decisão Q3b (5.2) service e job passaram a limpar; o `bool` só servia para o service decidir sozinho. Falha de conexão no `media_publish` propaga (não marca `failed`), como antes; resposta de erro da Meta marca `failed`.
- **`publish` com resposta HTTP de falha sem objeto `error`** (ex.: 500 em HTML): antes era tratada como sucesso com `platform_post_id` nulo; agora vira `failed` com a mensagem genérica. Resposta 200 sem `id` continua publicada com id nulo.
- **Job:** `containerStatus` agora lança em erro da Meta (antes virava `null` e reagendava). Erro permanente (token/permissão: 10, 102, 190, 200-299) marca `failed` na hora; os demais propagam para o retry da fila (15 tentativas) e, esgotadas, `failed()` marca `failed`. `handle()` continua sem parâmetros (`InstagramApi`/`InstagramService` via `app()`), porque testes existentes o chamam direto.
- **Mapeamento Q1:** só os códigos confirmados no repositório (`InstagramAnalyticsProvider::REAUTH_ERROR_CODES` e seu teste: 10, 102, 190 e faixa 200-299) têm mensagem própria ("reconecte a conta"). Limite de chamadas (4/17/32/613), mídia inválida (100) e demais códigos **não foram confirmados em documentação local** e caem na mensagem genérica; nenhum código foi inventado.
- **OAuth:** falha de conexão/erro ao buscar o perfil ou o token de longa duração cai nos mesmos fallbacks que já existiam para resposta de erro (`Instagram User`; token curto com 1 h). Antes, uma falha de conexão nessas duas chamadas lançava `ConnectionException` crua.
- **Q2:** além do Instagram, a renovação do Google também perdeu o body (a decisão cobre os dois); `SocialAccountException::tokenRefreshFailed()` perdeu o parâmetro `$responseBody`. Testes ajustados: `SocialAccountTokenTest` (Google e Instagram), `RefactorModelsRegressionTest` (Instagram), cada um com a asserção exata da nova mensagem mais `not->toContain(body)`.
- **5.5:** `InstagramAnalyticsProvider::GRAPH_URL` agora aponta para `InstagramApi::GRAPH_URL` (só a constante).
- **Sem mudança em `PublishPostJob`:** o `PublishPostJob::failed()` já grava só mensagem de `BaseException`; como `InstagramException` é uma, a mensagem mapeada chega ao usuário sem mudar o `PublishPostJob`.
