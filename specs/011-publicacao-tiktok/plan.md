# Plan 011 — Publicação no TikTok (Direct Post)

> Decisões de negócio em [`spec.md`](spec.md) §Decisões. Este plano é o "como". **Aprovado pelo Gabriel em 2026-11-05**, com duas diretrizes: (1) o que for estrutural nasce **genérico** para Instagram/YouTube reaproveitarem; (2) **pronto para produção** desde o início (sandbox é só ambiente de dev).

## 1. Decisões técnicas

| # | Decisão | Alternativa descartada |
|---|---------|------------------------|
| D1 | `TikTokService::upload()` só **inicia**: valida → `init` → envia chunks → grava `publish_id` → dispara `CheckTikTokPostStatusJob`. Quem marca `published`/`failed` é o job (RF-7). | Polling com `sleep` dentro do `PublishPostJob` (como está hoje): ocupa o worker até 60 s e duplica a lógica do job. |
| D2 | O job de status já existe e é reaproveitado. Ganha: `publicaly_available_post_id`, mapeamento de erros, fail por tempo esgotado (`failed()` quando `tries` acaba), limpeza de S3 só no estado final e notificação de falha. | Reescrever o job. Desnecessário. |
| D3 | Cliente HTTP por plataforma em `app/Services/<Plataforma>/` (aqui `app/Services/TikTok/TikTokApi.php`): concentra URLs, headers e a conversão de resposta de erro em exceção. Instagram e YouTube ganham o seu na spec própria (§9). **Aprovado pelo Gabriel.** | `Http::` espalhado em service + job + provider (hoje são 3 lugares com URLs duplicadas). |
| D4 | Erros: `TikTokError` (enum, `ErrorEnumInterface`) + `TikTokException` com factories (`scopeMissing()`, `privacyNotAllowed()`, `videoTooLong()`, `fromApiCode($code)` ...). Código do TikTok → mensagem pt-BR (RF-8). Nunca `body` cru. | `\Exception` com `json_encode($init)` (hoje vaza resposta de terceiros). |
| D5 | Payload TikTok validado por `TikTokPayloadBuilder` (via `PayloadBuilderFactory`, mesmo padrão dos outros): `privacy_level` obrigatório, `disable_*`, `brand_content_toggle`, `brand_organic_toggle`; brand content + `SELF_ONLY` → 422. Chaves sem prefixo `tiktok_` (nomes da spec). | Manter `tiktok_privacy_level` e default `SELF_ONLY`: a spec proíbe default, o usuário escolhe. |
| D6 | **Genérico:** `scopes` em `social_accounts` vale para qualquer rede; `Platform::publishScope()` (enum) devolve o scope exigido (`video.publish` no TikTok; YouTube/Instagram preenchem na spec deles, `null` = sem exigência). `SocialAccount::canPublish()` usa isso e `SocialAccountResource` expõe `can_publish` para todas. | `hasScope('video.publish')` fixo no TikTok. |
| D7 | Config `services.tiktok.default_privacy` **sai** (RF-4 sem default). | Manter como fallback: contradiz a spec. |
| D8 | **Produção sem mudança de código:** a privacidade permitida vem do `creator-info` em tempo real, então quando o TikTok auditar o app as opções públicas aparecem sozinhas; nada em código ou config diferencia sandbox de produção. | Flag `TIKTOK_AUDITED`/`APP_ENV` decidindo privacidade: frágil e fácil de esquecer. |

## 2. Modelo de dados

- `social_accounts.username` string nullable — migration `2026_11_05_000000_add_username_to_social_accounts_table` (**já criada e rodada em dev**).
- `social_accounts.scopes` text nullable — migration nova, uma responsabilidade. Compatível com PostgreSQL e SQLite.
- Contas antigas: `scopes` null ⇒ `can_publish=false` ⇒ precisam reconectar (RF-1/RF-2).

## 3. Arquivos

| Ação | Arquivo |
|------|---------|
| novo | `app/Services/TikTok/TikTokApi.php` |
| novo | `app/Enums/Exceptions/TikTokError.php`, `app/Exceptions/TikTokException.php` |
| novo | `app/Services/Payloads/Builders/TikTokPayloadBuilder.php` (+ registro na factory) |
| novo | `app/Http/Controllers/Api/TikTokCreatorInfoController.php`, `TikTokCreatorInfoResource` |
| novo | migration `scopes` |
| edita | `TikTokService` (enxuto: ~60 linhas), `CheckTikTokPostStatusJob`, `TikTokOAuthProvider` (scopes + `scopes`/`username`), `SocialAccount` (`scopes` fillable, `hasScope`), `SocialAccountResource` (`can_publish`), `ScheduledPost::getPlatformPostUrl`, `routes/api.php`, `config/services.php`, seeder se criar permissão (não cria: usa `social_accounts.view`) |
| remove | `TikTokAuthController` (já apagado), stub antigo |
| docs | docblocks Scribe do endpoint novo (agente `docs`) |

## 4. Efeitos colaterais
- Observer do `ScheduledPost` já notifica falha (RF-9): sem tipo novo.
- Fila `tiktok` (Horizon) recebe o job de status; sem mudança de config.
- Octane: nada de estado estático; `TikTokApi` sem estado de request.
- Contas conectadas hoje precisam reconectar (comunicar no release).

## 5. Testes (Pest, `Http::fake`, nunca API real)
`tests/Feature/TikTok/`: sucesso ponta a ponta (1 chunk e multi-chunk), `FAILED` com motivo, tempo esgotado, conta sem escopo, token expirado com renovação, `privacy_level` inválido/ausente, brand + `SELF_ONLY`, vídeo acima da duração, S3 limpo só no estado final, creator-info (200/403/404 isolamento entre workspaces/409), nenhum token ou `body` cru nas respostas, `can_publish` na Resource, URL do post. Os 3 testes atuais de `tests/Feature/TikTokIntegrationTest.php` são reescritos na fase `tdd` (hoje assumem o comportamento antigo); nenhum é apagado para "passar".
Segurança (`tests/Feature/Security/`): isolamento do creator-info; token nunca em resposta/log.

## 6. Riscos
- Sandbox/não auditado: só `SELF_ONLY`; sem ID público ⇒ `platform_post_id` null e URL null. Esperado.
- `platform_post_id` hoje pode receber `publish_id` (job usa como fallback): passa a ficar null sem ID público, para a URL nunca apontar para vídeo inexistente. Conferir se algo lê o campo para TikTok.
- Reconexão em massa de contas antigas.

## 7. Prontidão para produção
Tudo abaixo é checklist de release (não é código) e entra em **Restrições externas** da spec:
- Painel TikTok for Developers: Content Posting API com **Direct Post** ligado, scopes `user.info.basic`, `user.info.profile`, `user.info.stats`, `video.list`, `video.upload`, `video.publish`; redirect URI de **produção** `{APP_URL}/api/social-accounts/tiktok/callback`; termos e privacidade públicos.
- Submeter o app para **auditoria** com o fluxo gravado cumprindo as UX Guidelines (privacidade escolhida pelo usuário, interações, divulgação comercial, nome da conta de destino). Sem auditoria, publica só `SELF_ONLY`.
- Variáveis de produção: `TIKTOK_CLIENT_KEY`, `TIKTOK_CLIENT_SECRET`, `TIKTOK_REDIRECT_URI` (secrets no gerenciador de produção, **não** em `.env.example`). Não toco em `docker-compose.prod.yml`, `docker/` nem `.github/` sem pedido.
- Horizon: worker da fila `tiktok` ativo em produção; `tries`/`backoff` do job de status cobrem ~5 min de processamento.
- Rate limit do TikTok (`spam_risk_too_many_posts`) tratado como erro mapeado, sem retry cego.
- Mensagem de release: contas conectadas antes precisam reconectar.
- Logs nunca com token/`body` cru (testado em `Security/`).

## 8. Apontamentos (fora do escopo, não corrigir aqui)
- `PublishPostJob` lança `\Exception` genérica (assinatura inativa, conta não encontrada).
- `state` do OAuth sem nonce/expiração (spec 002).
- `TikTokOAuthProvider`/`SocialAccount` usam `\Exception`/`oauthTokenExchangeFailed` misturados.
- `.env.example` alterado no WIP (`DB_EMULATE_PREPARES`, ngrok): regra "não alterar sem pedir".

## 9. Próximas specs (mesmo padrão, fora do escopo desta)
Esta spec deixa o molde; as outras redes entram em specs de refatoração/feature próprias (`CLAUDE.md`: escopo fechado):
- **014 — Instagram:** cliente `app/Services/Instagram/`, `InstagramError`, scope em `Platform::publishScope()`, job de status/container no mesmo formato.
- **015 — YouTube:** idem, com cota diária da API como erro mapeado.
- Antes de cada uma, o agente `architect` audita o módulo e propõe o que unificar (ex.: base comum para "iniciar upload → job confere estado final → limpa S3").
Só extraio base comum **depois** de duas redes seguirem o padrão (evita abstração prematura).
