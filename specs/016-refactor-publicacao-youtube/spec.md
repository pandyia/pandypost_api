# 016 — Refatoração: publicação no YouTube no molde da spec 011 (e fechamento do token por rede)

> Depende da [014](../014-refactor-publicacao-comum/spec.md) e da [015](../015-refactor-publicacao-instagram/spec.md) (a tarefa final precisa das 3 redes com cliente próprio). Área crítica: caracterização antes de mexer.

## Contexto (comportamento atual)
- `PublishPostJob` (fila `youtube`) chama `YouTubeService::upload()`: marca `processing`, monta um `Google\Client` com o token da conta (renova pelo SDK se vencido; sem `refresh_token` notifica a desconexão e lança), envia o vídeo por resumable upload em chunks de 1 MB lendo o S3 em stream, sobe a thumbnail (exceto Shorts; falha da thumbnail só vira warning), marca `published` com o ID do vídeo e limpa o S3. Qualquer exceção é logada e relançada; quem grava `failed` é `PublishPostJob::failed()` com `getMessage()` da exceção.
- Não há job de conferência: o `videos.insert` devolve o ID ao fim do upload, que é o estado final que o produto usa.
- Renovação e revogação de token do Google vivem em `SocialAccount` (`Http` direto em `oauth2.googleapis.com`), **além** da renovação feita pelo SDK dentro de `YouTubeService::getAuthenticatedClient`.
- A mensagem do `Google\Service\Exception` é o **JSON cru** da resposta do Google e vai para `error_message`.

## Escopo fechado
`app/Services/YouTubeService.php`, `app/Services/OAuthProviders/GoogleOAuthProvider.php` (só o que a 014 não cobre), `app/Services/Payloads/Builders/YouTubePayloadBuilder.php` (leitura), `app/Models/SocialAccount.php` (token por rede), `app/Services/Factories/SocialMediaFactory.php`; novos `app/Services/YouTube/YouTubeApi.php`, `app/Enums/Exceptions/YouTubeError.php`, `app/Exceptions/YouTubeException.php`, `app/Contracts/PlatformTokenApiInterface.php`.

## Achados

| # | Onde | Regra violada | Correção | Ganho |
|---|------|---------------|----------|-------|
| Y1 | `YouTubeService.php:78` (`new Exception('Token expirado e sem refresh_token...')`), `:146` (`new Exception('Falha ao fazer upload...')`) | `\Exception` genérica | `YouTubeException::refreshTokenMissing()` / `::uploadFailed()` (mesmos textos) | erro tipado; os testes continuam passando porque `BaseException extends Exception` |
| Y2 | `YouTubeService.php:59-62` | `catch (Exception) { log; throw }` só para logar; a mensagem do Google (JSON) chega ao `error_message` | `catch (\Google\Service\Exception $e)` → `throw YouTubeException::fromGoogle($e)` (usa `getErrors()[0]['reason']`: `quotaExceeded`, `uploadLimitExceeded`, `forbidden`/`insufficientPermissions`, `authError`/`invalid_grant`, `youtubeSignupRequired`, demais genérico); log só com o `reason` | cota diária vira erro mapeado (como o limite de posts do TikTok); sem body de terceiro |
| Y3 | `YouTubeService.php:66-90` (`getAuthenticatedClient`, 24 linhas, 3 níveis) | Aninhamento que early return resolve | `if (! $client->isAccessTokenExpired()) return $client;` no topo | -1 nível, leitura linear |
| Y4 | `YouTubeService.php:115` | Reuso ignorado: `'public'` literal com `YouTubePrivacyStatus::PUBLIC` existindo | `YouTubePrivacyStatus::PUBLIC->value` | um vocabulário só |
| Y5 | `SocialAccount.php:133-135,147-172` | `match` por plataforma fora da factory (checklist 6) + 3 blocos `update([...expires_at => now()->addSeconds(...)])` quase idênticos (`:160`, `:190`, `:213`) | `PlatformTokenApiInterface` (`refreshToken(SocialAccount): array`, `revokeToken(SocialAccount): void`) implementada por `TikTokApi` (014), `InstagramApi` (015) e `YouTubeApi`; `SocialMediaFactory::tokenApi(Platform)`; `getValidToken()` fica com um `update` só | ~45 linhas a menos no model; adicionar rede = 1 classe, sem editar o model |
| Y6 | `YouTubeService.php:68` | Sem costura para teste: `new Client` impede caracterizar o upload | `app(Google\Client::class)` (mesmo comportamento; permite `bind` de um cliente com `MockHandler` do Guzzle nos testes) | upload do YouTube passa a ser testável |

**Não vira achado / descartado**: criar `YouTubeApi` que embrulha `videos->insert`/`thumbnails->set` (o SDK do Google já é o cliente; seria uma camada 1:1); job de conferência de estado final (o upload já devolve o ID, um job adicionaria atraso e mudaria quando o post vira `published`); unificar `getAuthenticatedClient` com `SocialAccount::getValidToken` (formatos de expiração diferentes: JSON do SDK vs `expires_at`; ver Apontamento 1); extrair `streamVideoUpload`/`streamThumbnailUpload` (duas cópias, motivos de mudança diferentes).
O `YouTubeApi` portanto existe **só** para o endpoint de token do Google (`refreshToken`/`revokeToken`) e implementa a interface de Y5.

## Requisitos
- RF-1: o que o upload faz e grava não muda; erros passam a ser `YouTubeException` com mensagem pt-BR (**muda o texto de `error_message`**, depende de Q1).
- RF-2: a assinatura do construtor de `YouTubeService` (`StorageService`) e os métodos privados `getAuthenticatedClient(SocialAccount)` e `cleanupFiles(ScheduledPost, array)` **permanecem** (testes de `NotificationHooksTest.php:240-241` e `RetryScheduledPostCoverageTest.php:181-198` os usam por reflexão), salvo decisão de Q2.
- RF-3: renovação/revogação do token dos 3 provedores iguais a hoje (endpoints, campos, defaults de `expires_in`: YouTube obrigatório, TikTok 86400, Instagram 5184000); a estrutura passa a ser Strategy + Factory.
- RF-4: a notificação `social_account_disconnected` continua saindo uma vez quando falta o `refresh_token`.

## Critérios de aceite
- Suíte do escopo igual ou melhor; nenhum teste existente alterado (exceções só com aprovação: Q1/Q2).
- Contrato de API inalterado.
- `grep -n "match (\$this->platform)" app/Models/SocialAccount.php` = vazio.

## Fora de escopo
`Platform::publishScope()` do YouTube e gravar `scopes` (mudaria `can_publish` de contas já conectadas; Q4); job de conferência; unificar as duas renovações de token; `YouTubeAnalyticsProvider`.

## Apontamentos (não corrigir aqui)
1. **Renovação duplicada e silenciosa.** `getAuthenticatedClient` (`:80-86`) ignora a falha do refresh (`fetchAccessTokenWithRefreshToken` devolve array com `error`; só atualiza se houver `access_token`) e segue com o token vencido; o erro só aparece depois, no upload. Já `SocialAccount::refreshYouTubeToken` lança. Além disso o formato guardado em `access_token` pela conexão é a string do Socialite, enquanto o SDK calcula expiração por `created + expires_in` do JSON: **verificar no container** (`vendor/` não existe no host) o que `isAccessTokenExpired()` faz com token sem `expires_in`; a caracterização 0.2 mostra o comportamento real.
2. `PublishPostJob` tenta 3× erros permanentes (cota, escopo).
3. Cota diária do YouTube (10 000 unidades; um `videos.insert` custa ~1 600): sem tratamento prévio, o 7º upload do dia falha; hoje a mensagem é o JSON do Google.
4. `YouTubeService.php:85` `streamVideoUpload` não restaura `setDefer(false)` se o upload lançar; inofensivo hoje porque o `Client` é descartado a cada post.
5. `YouTubeAnalyticsProvider.php:348` inclui `$response->body()` em `RuntimeException`.

## Questões em aberto
- **Q1** — Texto pt-BR por motivo do Google (proposta: `quotaExceeded` "cota diária do YouTube esgotada, tente amanhã"; `uploadLimitExceeded` "limite de uploads do canal"; `forbidden`/`authError`/`invalid_grant` "reconecte a conta do YouTube"; `youtubeSignupRequired` "a conta Google não tem canal do YouTube"; demais genérico). Aprova, e que `error_message` deixe de trazer o JSON?
- **Q2** — Posso alterar os 2 testes de reflexão de `cleanupFiles` (e o de `getAuthenticatedClient`, se quiser renomear para algo que diga que ele também renova e notifica)? Default: **não**; o nome fica e o `cleanupFiles` fica como está (14 linhas, sem duplicar com os outros locais além de `mediaPaths`).
- **Q3** — Aprova a costura `app(Client::class)` (Y6) como tarefa 0 *antes* da caracterização? É a única mudança em `app/` antes dos testes e não altera comportamento. Sem ela o upload do YouTube não pode ser caracterizado, e as tarefas 2–3 ficam sem rede de segurança (recomendo não executá-las nesse caso).
- **Q4** — Ativar `publishScope` do YouTube (`youtube.upload`) gravando `scopes` na conexão? Feature, muda `can_publish`.
- **Q5** — Remover o corpo (body) da mensagem de renovação do Google (`SocialAccountTokenTest.php:84`)? Igual à Q2 da 015.

## Decisões (Gabriel delegou — 2026-11-05)
- Q1: **sim**, texto pt-BR mapeado no lugar do JSON cru.
- Q3: **sim**, costura `app(Client::class)` como tarefa 0.
- Q4: **não** ativar `publishScope` do YouTube agora (idem Instagram).
- Q5: **sim**, retirar o body da mensagem de renovação do token do Google (junto com a 015-Q2).
