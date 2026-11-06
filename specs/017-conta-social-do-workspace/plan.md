# Plan 017

## Decisões
| # | Decisão | Alternativa descartada |
|---|---------|------------------------|
| D1 | `ensureValidSocialAccount` passa a `SocialAccount::where('uuid', ...)` (o global scope `BelongsToWorkspace` já isola por workspace). | Filtro explícito por `workspace_id`: redundante com o scope. |
| D2 | `PublishPostJob`: `account->workspace_id === post->workspace_id` (usar o campo do post; confirmar no model). Erro: `ScheduledPostException::accountNotFound()`. Em job não há workspace "corrente": comparar ids explicitamente, sem depender do scope. | Remover a checagem: perderia a defesa em profundidade. |
| D3 | `state` via serviço pequeno `OAuthStateService` (`issue(User, Workspace): string`, `consume(string): array`) usando `Cache` (nonce uso único, TTL 10 min) + `Crypt`. Todos os providers e o controller o usam. | `state` assinado sem cache: não garante uso único. **Abstração nova: exigida para não repetir 3x; aprovada pela decisão de padronizar as redes.** |
| D4 | `syncAccount` recebe o `User` iniciador (contexto do `state`), grava `user_id` dele; o ramo `Workspace` (primeiro membro) some. | Manter os dois ramos: é o bug. |
| D5 | Sem migration: `user_id` continua, com novo significado documentado no model. | Remover coluna: destrutivo. |

## Arquivos
`ScheduledPostService`, `PublishPostJob`, `SocialAccountController::callback`, `OAuthProviderInterface` + `Google/Instagram/TikTok OAuthProvider`, `OAuthStateService` (novo), `SocialAccount` (docblock), `SocialAccountService` se tocar o fluxo, docblocks Scribe do callback.

## Efeitos colaterais
Contas existentes: nada a migrar. `state` antigo (em voo) deixa de valer: usuário refaz a conexão. Auditoria registra o `user_id` do iniciador.

## Testes
`tests/Feature/SocialAccounts/` (workspace compartilhado, RF-1/2/3/6), `tests/Feature/Security/` (RF-4/5/7; remover skips VULN-TT-03 e OAUTH-01), isolamento entre workspaces, `Http::fake`/`Socialite` mockados.

## Riscos
- Mais gente passa a poder agendar em contas existentes: é a decisão pretendida; permissões `posts.*` continuam mandando.
- `PublishPostJob` e `SocialAccount` são compartilhados com Instagram/YouTube: rodar a suíte completa.

## Ordem
Executa **depois** da spec 014 (que extrai a base comum dos `OAuthProviders`); o `state` seguro entra sobre essa base, uma vez só.
