# Plano 002 — Integração com o TikTok

> Requisitos em [`spec.md`](spec.md); andamento em [`tasks.md`](tasks.md).

## 1. Decisões

| # | Decisão | Alternativa descartada |
|---|---|---|
| 1 | **Endpoints como constantes privadas** em cada classe que os usa (`TikTokOAuthProvider`, `TikTokService`, `CheckTikTokPostStatusJob`), no mesmo padrão do `InstagramService`. O endpoint de token é `public const` no provider porque o `SocialAccount` também o usa no refresh e na revogação. | Classe `TikTokApiClient` compartilhada — abstração nova, que o CLAUDE.md pede para evitar sem combinar. |
| 2 | **Falhas de envio só lançam `ScheduledPostException::publishFailed()`**; quem marca o post como `failed` e limpa a mídia é o `PublishPostJob::failed()` ao fim das tentativas. | Manter o `$post->update(['status' => 'failed'])` dentro do service: duplica o que o job já faz e faz o status alternar `failed`/`processing` entre as tentativas. |
| 3 | **Pedaços fixos de 10 MB, total arredondado para baixo** (o último absorve o resto, < 20 MB). Vídeo < 10 MB vai inteiro. Leitura com `stream_get_contents($stream, $n)`, que lê até completar `$n` bytes. | Enviar inteiro até 64 MB (como hoje): carrega até 64 MB na memória do worker. `fread`: devolve no máximo um pacote em stream de rede (D6). |
| 4 | **MIME pela extensão** (`mp4`, `mov`, `webm`), padrão `video/mp4`. | `StorageService::mimeType()`: uma chamada HEAD a mais no S3 para algo que a extensão já diz, e o `StoragePathRule` já restringe o diretório. |
| 5 | **Mapa de códigos de erro do TikTok → mensagem pt-BR** (`TikTokService::ERROR_MESSAGES`). Código desconhecido usa a mensagem do TikTok. | Repassar só `error.message` (em inglês e pouco útil para o usuário final). |
| 6 | **Acompanhamento por prazo** (`retryUntil` = 1 h), `release()` com 5/10/20/30/60 s e `maxExceptions = 3`. O `failed()` marca o post e limpa a mídia (D4). `FAILED` do TikTok marca o post e encerra o job normalmente, sem `fail()`, para não passar duas vezes pela marcação. | Aumentar `$tries`: o número de tentativas não diz quanto tempo esperamos. |
| 7 | **Escopos `user.info.basic,video.publish`** e checagem do `scope` devolvido no token (D7). | Manter `video.upload`: escopo sem uso, pesa na auditoria. |
| 8 | **Enum `TikTokPrivacyLevel`** para as privacidades (como `YouTubePrivacyStatus`), usado no FormRequest e como padrão no service. | Lista hard-coded no FormRequest. |
| 9 | **`TikTokPayloadBuilder` itera uma lista de campos booleanos** em vez de repetir 4 blocos iguais. | — |
| 10 | **Testes migrados para Pest** em `tests/Feature/TikTok/`, um arquivo por fluxo (conexão, publicação, acompanhamento, agendamento). | Manter a classe PHPUnit. |

## 2. Modelo de dados

Sem migrations. Campos usados em `scheduled_posts`: `container_id` (= `publish_id`), `container_created_at`, `platform_post_id` (id público ou `null`), `status`, `error_message`, `published_at`, `payload` (`tiktok_*`).

## 3. Erros de domínio (novos casos)

| Enum | Caso | HTTP | Mensagem |
|---|---|---|---|
| `ScheduledPostError` | `PUBLISH_FAILED` | 502 | `Falha ao publicar no {plataforma}.` — a factory acrescenta o motivo: `Falha ao publicar no TikTok: {motivo}` |
| `SocialAccountError` | `TOKEN_REFRESH_FAILED` | 401 | `Não foi possível renovar o acesso ao {plataforma}. Reconecte a conta.` |
| `SocialAccountError` | `MISSING_PERMISSIONS` | 403 | `Autorize todas as permissões solicitadas pelo {plataforma} para conectar a conta.` |

## 4. Arquivos

| Arquivo | Mudança |
|---|---|
| `app/Enums/TikTokPrivacyLevel.php` | novo |
| `app/Enums/Exceptions/ScheduledPostError.php`, `app/Exceptions/ScheduledPostException.php` | caso + factory `publishFailed()` |
| `app/Enums/Exceptions/SocialAccountError.php`, `app/Exceptions/SocialAccountException.php` | casos + factories `tokenRefreshFailed()` e `missingPermissions()` |
| `app/Services/OAuthProviders/TikTokOAuthProvider.php` | constantes, escopos, checagem de escopo, métodos `requestToken`/`fetchProfile` |
| `app/Models/SocialAccount.php` | `refreshTikTokToken` com erro de domínio (ao lado dos outros refresh), revogação no TikTok |
| `app/Services/TikTokService.php` | reescrito em métodos pequenos (D2, D3, D5, D6) |
| `app/Jobs/CheckTikTokPostStatusJob.php` | D1, D4, prazo, limpeza de thumbnail |
| `app/Services/Payloads/Builders/TikTokPayloadBuilder.php` | loop dos booleanos |
| `app/Http/Requests/StoreScheduledPostRequest.php` | `Rule::enum(TikTokPrivacyLevel::class)` |
| `app/Models/ScheduledPost.php` | link `@/video/{id}` |
| `config/services.php` | indentação do bloco `tiktok` |
| `routes/web.php` | remove a rota `/` → `welcome` |
| `tests/Feature/TikTokIntegrationTest.php` | removido (substituído pelos arquivos abaixo) |
| `tests/Feature/TikTok/*Test.php` | novos |

## 5. Efeitos colaterais

- Publicação: `PublishPostJob` (fila `tiktok`) → `TikTokService::upload` → `CheckTikTokPostStatusJob` (fila `tiktok`). Nenhum evento/notification novo.
- `PublishPostJob` continua tentando 3× também os erros definitivos (apontamento na spec).

## 6. Testes

Arquivos em `tests/Feature/TikTok/`, `Http::fake` em tudo, `Storage::fake('s3')` quando passa pelo `StoragePathRule`, `Bus::fake` para os jobs.

| Arquivo | Critérios |
|---|---|
| `TikTokConnectionTest.php` | CA-1, CA-2, CA-3, CA-4, CA-13 (callback), CA-14, CA-16 |
| `TikTokPublishTest.php` | CA-5, CA-6, CA-7 |
| `TikTokPostStatusTest.php` | CA-8, CA-9, CA-10, CA-11 |
| `TikTokSchedulingTest.php` | CA-12, CA-13 (agendamento) |

## 7. Riscos

- **App não auditado:** até a auditoria, o TikTok só aceita publicar em **conta privada** e o post fica visível só para o autor. Para o teste manual, deixe a conta do TikTok privada e use `tiktok_privacy_level = SELF_ONLY`.
- **Link `@/video/{id}`** — confirmar no teste manual (spec, Q1).
- **Tempo do `PublishPostJob` (`timeout` 180 s):** vídeos muito grandes em rede lenta podem estourar o tempo do job durante o upload. Não muda nesta spec; observar no teste.
- **Redirect URI:** precisa ser exatamente `{APP_URL}/api/social-accounts/tiktok/callback` no portal do TikTok e em `TIKTOK_REDIRECT_URI`.
