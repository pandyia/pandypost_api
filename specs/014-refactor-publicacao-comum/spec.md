# 014 — Refatoração: base comum da publicação e do OAuth social

> Primeira de três specs que levam Instagram e YouTube ao molde da spec 011 (TikTok). Esta cobre o que é **comum às 3 redes** e não depende de nenhuma delas: [015 Instagram](../015-refactor-publicacao-instagram/spec.md) e [016 YouTube](../016-refactor-publicacao-youtube/spec.md) vêm depois, nessa ordem. Áreas críticas (esteira de publicação, OAuth social): o comportamento atual está descrito em §Contexto e fixado por testes de caracterização antes de qualquer mudança.

## Contexto
A spec 011 deixou o molde no TikTok: cliente HTTP em `app/Services/<Rede>/`, enum de erro + exception por rede, `Platform::publishScope()`/`canPublish()`, payload builder, job de estado final e S3 só limpo em sucesso. Sobraram redundâncias que atravessam as 3 redes. Comportamento atual:
- `PublishPostJob` (fila por rede, `tries=3`) valida assinatura e conta, delega à `SocialMediaFactory` e, em falha final, grava `failed` + `error_message` (primeiros 255 caracteres da mensagem da exceção).
- Os 3 `OAuthProviders` montam o `state` (`encrypt(workspace uuid)`) e resolvem workspace/usuário dono da conta cada um com seu bloco copiado.
- `SocialAccount::getValidToken()/revokeToken()` fazem `match` por plataforma e chamam direto as URLs de OAuth de cada rede.
- Cada rede monta a lista de arquivos do post (vídeo + thumbnail) para limpar o S3 por conta própria.

## Escopo fechado
`app/Jobs/PublishPostJob.php`, `app/Services/OAuthProviders/*`, `app/Services/Factories/OAuthProviderFactory.php`, `app/Models/ScheduledPost.php` (um método), `app/Services/ScheduledPostService.php` (2 trechos), `app/Console/Commands/PurgeFailedPostsMediaCommand.php`, `app/Jobs/CheckTikTokPostStatusJob.php` (1 trecho), `app/Services/InstagramService.php` (1 trecho, `cleanupFiles`), `app/Services/TikTok/TikTokApi.php`, `app/Models/SocialAccount.php` (só o ramo TikTok), `app/Exceptions/ScheduledPostException.php` + `ScheduledPostError`.

## Achados

| # | Onde | Regra violada | Correção (uma frase) | Ganho |
|---|------|---------------|----------------------|-------|
| A1 | `InstagramService.php:227-237`, `YouTubeService.php:172-181`, `CheckTikTokPostStatusJob.php:94`, `ScheduledPostService.php:76-81` e `:130`, `PurgeFailedPostsMediaCommand.php:27-30` | Duplicação real (6 lugares, mesmo motivo para mudar: "quais arquivos o post ocupa") | `ScheduledPost::mediaPaths(): array` (vídeo + thumbnail, sem vazios) e os 5 lugares usam | ~25 linhas a menos; regra da thumbnail num ponto |
| A2 | `PublishPostJob.php:33` e `:39` | `\Exception` genérica (CLAUDE.md "Erros de domínio") | `ScheduledPostException::subscriptionInactive()` e `::accountNotFound()` com **o mesmo texto** de hoje | erro tipado, sem mudar `error_message` |
| A3 | `GoogleOAuthProvider.php:18-24,39-45`, `InstagramOAuthProvider.php:16-22,40-46`, `TikTokOAuthProvider.php:29-33,46-52` | Duplicação real (3×): checagem do workspace + `state` e resolução workspace/usuário dono | classe base `AbstractOAuthProvider` com `stateFor(?User)` e `ownerIds(User\|Workspace)` | ~35 linhas a menos; o `state` (achado da spec 002) passa a mudar em **um** lugar |
| A4 | `GoogleOAuthProvider.php:14`, `InstagramOAuthProvider.php:73`, `TikTokOAuthProvider.php:71` | Nome mente: literais `'youtube'/'instagram'/'tiktok'` quando existe `Platform` | `Platform::X->value` | um vocabulário só |
| A5 | `SocialAccount.php:136-141` e `:177-203` | `match` por plataforma fora da factory + URL do TikTok espalhada (provider, model, analytics) | `TikTokApi::refreshToken()/revokeToken()`; o model só persiste e delega | model mais curto; endpoints TikTok em um cliente (molde 011) |
| A6 | `OAuthProviderFactory.php:6,20` + `DefaultSocialiteProvider.php` (51 linhas, 100% comentadas) | Código morto; `use InvalidArgumentException` sem uso | remover o arquivo e o import (ver **Q3**: o `default` hoje quebra) | -52 linhas |

Itens que **não** viram achado (aplicado o critério de over-engineering): extrair `retryWithBackoff()/backoff()` dos dois jobs de status (só 2 cópias — vira trait se surgir o 3º), interface para `ScheduledPost::mediaPaths`, trocar `getPlatformPostUrl()` por Strategy (um `match` curto resolve).

## Requisitos
- RF-1: o conjunto de arquivos apagado do S3 em cada caminho (cancelar, limpar falhos, sucesso no Instagram/TikTok) não muda; a estrutura passa a ser `ScheduledPost::mediaPaths()`. (YouTube só em 016, por causa de testes que chamam `cleanupFiles` por reflexão; ver **Q2**.)
- RF-2: `PublishPostJob` continua lançando nos mesmos dois casos, com o mesmo texto; a estrutura passa a ser `ScheduledPostException`.
- RF-3: `getRedirectUrl`/`syncAccount` das 3 redes não mudam (URL, `state`, campos gravados, exceções); a estrutura passa a ser `AbstractOAuthProvider`.
- RF-4: renovação e revogação de token do TikTok não mudam (mesmos endpoints, mesma mensagem **sem** body, mesmos campos gravados); a estrutura passa a ser `TikTokApi`.
- RF-5: remoção de código morto do `DefaultSocialiteProvider` (condicionada a Q3).

## Critérios de aceite
- Suíte do escopo igual ou melhor; **nenhum teste existente alterado** (testes novos de caracterização são adicionados).
- Contrato de API inalterado (`can_publish`, rotas, respostas).
- `docker exec pandypost-php php artisan test` verde e `pint --dirty` nos arquivos alterados.

## Fora de escopo
Cliente/erro/job do Instagram (015) e do YouTube (016); `state` com nonce/expiração (spec 002); troca do `match` de `getValidToken` por Strategy (016, última tarefa, quando as 3 redes já tiverem cliente); `PublishPostJob::failed()` gravar mensagem de terceiro (Apontamento 1).

## Pré-requisito
A spec 011 está **não commitada** no working tree (`TikTokApi`, `CheckTikTokPostStatusJob`, `TikTokOAuthProvider`, `SocialAccount` etc. aparecem como `M`/`??`). Os passos que tocam esses arquivos só começam depois do commit da 011.

## Apontamentos (não corrigir aqui)
1. **VULN-RETRY-02 segue aberta** (`PublishPostJob.php:54`): `failed()` grava `substr($exception->getMessage(), 0, 255)` para qualquer exceção; `ConnectionException` do Laravel carrega a URL com `access_token` (o Instagram manda o token na query). Há teste `skip` em `PostRetrySecurityTest.php:114-127`. Quando 015/016 tiparem todos os erros, a correção é pequena: `failed()` grava a mensagem só se for `BaseException`, senão texto genérico (muda o `error_message`; decisão de segurança, spec própria).
2. `SocialAccountController.php:93,107` capturam `\Exception` e o callback devolve `getMessage()` na URL do front; hoje só sai `SocialAccountException`, mas qualquer outra exceção vazaria.
3. `GoogleOAuthProvider.php:49` `catch (\Exception)` engole a causa do Socialite sem log.
4. `YouTubeAnalyticsProvider.php:348` lança `RuntimeException` com `$response->body()` (vaza resposta de terceiro para log/AnalyticsService). Fora do escopo de publicação.
5. `OAuthProviderFactory.php:20`: a plataforma desconhecida cai em `new DefaultSocialiteProvider($platform)`, que não implementa `OAuthProviderInterface`: `TypeError` (HTTP 500) em vez de `SocialAccountException::platformNotSupported` (já existe e não é usada).

## Questões em aberto
- **Q1 (CLAUDE.md "criar abstrações novas")** — Posso criar `AbstractOAuthProvider` (A3)? Recomendo sim: 3 implementações e 3 cópias do mesmo bloco. Alternativa: trait com os 2 métodos (mesmo ganho, sem herança).
- **Q2** — Os testes `RetryScheduledPostCoverageTest.php:181-198` chamam `YouTubeService::cleanupFiles($post, $payload)` por reflexão. Para o YouTube usar `mediaPaths()` o método precisa sumir e esses 2 testes mudariam. Default desta spec: **não tocar** no YouTube (5 de 6 locais); a decisão fica na 016.
- **Q3** — A6 muda comportamento para plataforma desconhecida (500 → 400 `platformNotSupported`)? Se não quiser, a tarefa fica de fora e o `DefaultSocialiteProvider` permanece (só o import morto sai).

## Decisões (Gabriel delegou — 2026-11-05)
- Q1: **sim**, classe base `AbstractOAuthProvider` (3 cópias do mesmo bloco).
- Q2: **sim**, os testes por reflexão podem ser adaptados ao mínimo necessário, sem perder asserção.
- Q3: **sim**, plataforma desconhecida passa de 500 para 400 (`platformNotSupported`).
- O `state` com nonce NÃO entra aqui: é a spec 017, que roda depois desta.
