# Tasks 011 — Publicação no TikTok

> Uma tarefa por vez, marcando `[x]`. Fluxo: `tdd` → `dev` → `qa` → `security` → `docs`. Só começa após aprovação do plan.

## Fase 1 — Dados e OAuth
- [x] Migration `username` em `social_accounts` — teste: coluna existe (SQLite)
- [x] Migration `scopes` em `social_accounts` — teste: coluna existe
- [x] `TikTokOAuthProvider`: salvar `scopes` (campo `scope` do token) e `username` — teste: callback com `Http::fake` grava ambos
- [x] `Platform::publishScope()` + `SocialAccount::canPublish()` + `can_publish` na `SocialAccountResource` (genérico, vale p/ todas as redes) — teste: TikTok com/sem `video.publish`; rede sem scope exigido → `true`

## Fase 2 — Erros e API client
- [x] `TikTokError` + `TikTokException` (códigos RF-8) — teste: cada código mapeia para pt-BR, sem `body`
- [x] `TikTokApi` (init, status, creator-info, chunk) — teste: `Http::fake` por endpoint

## Fase 3 — Payload e creator-info
- [x] `TikTokPayloadBuilder` + registro na factory — teste: obrigatórios, brand + `SELF_ONLY` → 422
- [x] `GET /api/social-accounts/{socialAccount}/tiktok/creator-info` (`social_accounts.view`) — teste: 200 / 403 / 404 isolamento / 409
- [x] Remover `default_privacy` do `config/services.php`

## Fase 4 — Publicação
- [x] `TikTokService::upload()` enxuto (validações RF-2/privacidade; erro propaga, sem marcar `failed`; init, chunks, dispatch do job) — teste: 1 chunk, multi-chunk, sem escopo, privacidade inválida, vídeo longo, S3 intacto
- [x] `CheckTikTokPostStatusJob`: `published` com ID público, `failed` com mensagem mapeada, tempo esgotado → `failed`, limpeza só no final, notificação — teste: cada estado
- [x] `ScheduledPost::getPlatformPostUrl()` TikTok (RF-11) — teste: com/sem username e ID
- [x] Reescrever `TikTokIntegrationTest` para o novo comportamento (sem enfraquecer)

- [x] RF-13: `tokenRefreshFailed` sem `body` de terceiro — teste: `Security/`
- [x] Ajustar testes do `tdd` às Decisões da fase TDD (sem duração local, S3 só limpo em sucesso, `PublishPostJob::failed()` marca `failed`)

## Fase 5 — Produção e fechamento
- [x] Checklist de produção (plan §7) copiado para *Restrições externas* da spec
- [x] Teste garantindo que nada muda por `APP_ENV` (privacidade só vem do creator-info)
- [ ] `qa`: critérios de aceite da spec
- [ ] `security`: isolamento, token fora de resposta/log
- [ ] `docs`: Scribe do creator-info
- [x] Pint nos alterados + suíte completa verde

## Fase 6 — Correções de QA/Security
- [x] VULN-TT-02: `TikTokApi` converte `ConnectionException`/`RequestException` em `TikTokException` (`uploadFailed`/`apiUnavailable`); `PublishPostJob::failed` grava mensagem genérica para exceção fora de `BaseException` — teste: `TikTokAuditTest` (skip removido)
- [x] VULN-TT-01: `upload_url` só em https e host `tiktokapis.com`/`tiktok.com` (constante `UPLOAD_HOST_SUFFIXES`) antes do PUT — teste: `TikTokAuditTest` (skip removido)
- [x] VULN-TT-04: creator-info com falha de conexão vira 502 tratado — teste: `TikTokCreatorInfoTest`
- [x] Remover `TikTokException::videoTooLong()`; testes usam `fromApiCode('duration_check_failed')`
- [x] `SocialAccount::hasScope()` privado
- [x] `privacy_level` validado só no `TikTokPayloadBuilder`; `MAX_TITLE_LENGTH` única (constante pública do builder)
- [x] `TikTokService::ensureCanPublish()` (helper do `canPublish()` → `scopeMissing`)
- [x] `CheckTikTokPostStatusJob`: `TikTokApi` injetado no `handle`; erro permanente (`TikTokError::isPermanent()`) marca `failed` na hora — teste: `CheckTikTokPostStatusJobTest`
- [x] `TikTokOAuthProvider`: falha do user/info vira log e a conta é criada mesmo assim
- [x] Teste: creator-info 404 para conta de outra plataforma
- [x] `spec.md`: RF-4/RF-5.2/RF-6 alinhados às Decisões e ao código
- [x] VULN-TT-03 (OAUTH-01, spec 002): corrigido na spec 017 (skip removido)

## Divergências
- **Duração (Decisão TDD 2, altera RF-5.2):** sem validação local. O `->todo()` virou dois testes: o init é feito mesmo com `max_video_post_duration_sec` baixo; `duration_check_failed` do TikTok vira a mensagem de `TikTokException::videoTooLong()`. `videoTooLong()` existe (exigida por `TikTokErrorMappingTest`) e mapeia o código `duration_check_failed`.
- **Quem marca `failed` (Decisão TDD 4):** os testes do `tdd` chamavam `TikTokService::upload()` e esperavam `failed` no post. Passaram a usar o helper `tiktokUploadViaJob()` (`tests/Pest.php`), que reproduz a esteira real (service propaga, `PublishPostJob::failed()` grava). Mesmo ajuste no `TikTokEnvironmentTest`, `TikTokSecurityTest` e `TikTokIntegrationTest` (unaudited). Novos testes provam que o service sozinho não grava `failed` e que `PublishPostJob::failed()` grava a mensagem pt-BR e mantém a mídia.
- **S3 só limpo em sucesso (Decisão TDD 3):** implementado como decidido (já era o comportamento do `failed()`); nenhum teste precisou mudar.
- **`publicity_id` descartado (Decisão TDD 6):** `platform_post_id` = `publicaly_available_post_id[0]` ou `null`.
- **Defeitos de teste corrigidos (sem enfraquecer):** (a) `TikTokCreatorInfoTest` "401 sem autenticação": `withToken()` persiste nos headers e o guard do Sanctum guarda o usuário; acrescentado `flushHeaders()` + `forgetGuards()` antes da chamada anônima. (b) `TikTokSecurityTest` "usuário de outro workspace": o guard guardava o dono na 1ª chamada, então o "atacante" era autenticado como o dono (200); acrescentado `forgetGuards()`. O isolamento real foi confirmado (404). (c) `TikTokPublishTest` notificação RF-9: o `Bus::fake()` do `beforeEach` engole a notificação em fila; o teste passou a fazer `Bus::fake([CheckTikTokPostStatusJob::class])`.
- **RF-13 e testes existentes:** `SocialAccountTokenTest` tinha 2 testes do TikTok fixando o `body` na mensagem (exatamente o que a Decisão 5 remove): atualizados para exigir "Não foi possível renovar o token do TikTok." sem o body. Google e Instagram mantêm o body (fora de escopo): `tokenRefreshFailed(string $provider, ?string $responseBody = null)`.
- **`RetryScheduledPostCoverageTest` (TikTok FAILED):** exigia o `fail_reason` cru ("motivo") no `error_message`, o que contradiz RF-8. Agora exige a mensagem genérica mapeada e que o texto cru não apareça.
- **`TikTokPrivacyLevel` (enum novo):** valida `privacy_level` no request e no builder, mesmo padrão do `YouTubePrivacyStatus`. Não estava no plan §3.
- **`creatorInfo()` no `TikTokService`** (não em service novo) e controller invokable `TikTokCreatorInfoController` com `__invoke` em `$permissionMethods`.
- **`CheckTikTokPostStatusJob` não chama mais `$this->fail()` no `FAILED`:** falha de negócio já tratada não é falha de job (evita `failed_jobs` e dupla marcação). `failed()` só age se o post ainda está `processing`.
- **Fase 5:** os itens `qa`, `security` e `docs` (Scribe do creator-info) são dos agentes respectivos e seguem pendentes. O checklist de produção foi copiado para *Restrições externas* (itens ainda por fazer, são de release).
- **Migrations em dev:** `php artisan migrate` aplicou `scopes` e também migrations pendentes de outras specs (ao menos `2026_10_18`, `2026_10_19`, `2026_10_20`). Todas aditivas.

### Fase 6
- **Fixtures de host do upload:** `upload.tiktok.test` virou `open-upload.tiktokapis.com` em `tests/Pest.php` e nos testes TikTok (a validação do VULN-TT-01 rejeita `.test`; `open-upload.tiktokapis.com` é o host real do TikTok). Só fixture, nenhuma asserção mudou.
- **VULN-TT-02:** o teste esperava `ConnectionException`; com a conversão no `TikTokApi` a exceção agora é `TikTokException`. Trocado o tipo esperado; as asserções de não vazamento ficaram iguais.
- **Item 8:** `TikTokEndToEndTest` ("erro da API de status (token revogado)") esperava que o `handle` lançasse e o `failed()` marcasse ao esgotar; agora o job marca `failed` sozinho e sem lançar. O resultado final asserido (failed, mensagem "Reconecte", sem texto cru) é o mesmo. Chamadas diretas a `handle()` em `RetryScheduledPostCoverageTest` e `TikTokIntegrationTest` receberam o `TikTokApi` como 2º argumento.
- **`PublishPostJob::failed`** agora grava mensagem genérica para qualquer exceção fora de `BaseException`, também em Instagram/YouTube (ex.: "Assinatura Inativa..." deixa de aparecer no `error_message`). O log continua com a mensagem original.
- **`privacy_level` no `StoreScheduledPostRequest`** deixou de ter `Rule::enum` (fica `nullable|string`); a validação é do builder, só para posts TikTok. Valor inválido em post não-TikTok não gera mais 422.
- **Novo código de erro** `tiktok_api_unavailable` (`TikTokError::API_UNAVAILABLE`, 502) com `TikTokException::apiUnavailable()`.
