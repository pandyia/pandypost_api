# Plan 015 — Instagram no molde da 011

## Decisões

| # | Decisão | Alternativa descartada |
|---|---------|------------------------|
| D1 | `app/Services/Instagram/InstagramApi.php` sem estado, mesmo formato do `TikTokApi`: `private api()` com timeout, `private data(Response)` que converte erro em `InstagramException`. O `access_token` continua indo na query (contrato atual com a Meta). | Header `Authorization`: comportamento não verificado na API do Instagram Login. |
| D2 | `containerStatus()` lança em falha de conexão; o **chamador** decide (service: `catch InstagramException → null`; job: deixa propagar). Preserva as duas semânticas de hoje. | Engolir dentro da Api: o job passaria a reagendar em vez de contar tentativa. |
| D3 | `InstagramService::publishContainer(post, account, token): bool` público. Faz `media_publish`, permalink (falha de permalink = só warning, como hoje) e marca `published`/`failed`. Devolve `true` se publicou. O service chama `cleanupFiles` quando `true`; o job não (preservado). | Cleanup dentro do método compartilhado: o job passaria a limpar S3, mudança de comportamento (Q3b). |
| D4 | `InstagramError` (enum, `ErrorEnumInterface`) + `InstagramException` com `fromApiError(?array $error)`, `connectionFailed()`, `containerFailed()`, `containerCreationFailed()`. Nunca body. Estende `BaseException`, então `PublishPostJob::failed()` já grava a mensagem mapeada. | Reusar `ScheduledPostException`: o erro é da rede, não do post. |
| D5 | Mantém o `bool $shouldPublish` (ver "Não vira achado" na spec). | Enum `PublishMode`: quebra jobs serializados, ganho nulo. |
| D6 | `InstagramAnalyticsProvider` passa a ler `InstagramApi::GRAPH_URL` (tarefa opcional, só a constante). | Mover o analytics para `InstagramApi`: escopo de outra spec. |
| D7 | `InstagramPayloadBuilder::extractPayload` (override que só devolve o pai + comentário) é removido; a classe fica como `GenericPayloadBuilder`. Nome e registro na factory permanecem. | Apagar o builder: a factory e o molde por rede continuam úteis para quando o Instagram ganhar campos. |

## Arquivos
| Ação | Arquivo |
|------|---------|
| novo | `app/Services/Instagram/InstagramApi.php`, `app/Enums/Exceptions/InstagramError.php`, `app/Exceptions/InstagramException.php` |
| edita | `InstagramService`, `CheckInstagramContainerJob` (corpo; **construtor e propriedades intactos**), `InstagramOAuthProvider`, `BackfillInstagramPermalinksCommand` (injeta `InstagramApi`), `SocialAccount::refreshInstagramToken`, `InstagramPayloadBuilder` |
| novo (testes) | `tests/Feature/Instagram/InstagramPublishFlowTest.php`, `.../InstagramContainerJobTest.php`, `.../InstagramWarmupTest.php`, `.../InstagramApiErrorsTest.php` |

## Ordem
Caracterização → limpeza trivial → `InstagramApi` sem trocar chamadores → trocar um chamador por vez → dedupe do publish → erros tipados → condicionais.

## Riscos
- **Job serializado:** `CheckInstagramContainerJob` tem `post`, `account`, `containerId`, `shouldPublish` públicos e fila `instagram`. Nada disso muda.
- **Teste que chama `->handle()` sem argumentos** (`InstagramPermalinkTest`, `RetryScheduledPostCoverageTest`, `PostRetrySecurityTest:191`): `handle()` segue sem parâmetros obrigatórios; `InstagramApi` entra por `app()`.
- **Mensagem de erro** muda com RF-3: nenhum teste atual fixa o JSON em `error_message` (verificado por grep em `tests/`), mas o front pode exibir o texto; avisar no release.
- **Códigos da Meta** não verificados neste ambiente (sem acesso à doc): o `fromApiError` nasce com poucos códigos e o resto cai em mensagem genérica.
- 011 não commitada (a tarefa 0 não depende; as demais, só no que tocam `SocialAccount`).
