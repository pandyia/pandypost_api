# Tasks

- [x] Migration + model (`avatar_path`, accessor `avatar`) — verificação: `WorkspaceAvatarTest`
- [x] Storage, rule, requests, service, event, controller, rotas — verificação: `WorkspaceAvatarTest`
- [x] `avatar` nos resources — verificação: teste de serialização
- [x] AUTH-03: `update/destroy` por workspace da URL — verificação: `WorkspacePermissionScopeTest` (falhava antes: 200/204)
- [x] Fluxo real no MinIO (upload-url → PUT → confirmar → imagem 200 → remover → 404)
- [x] Suíte completa: 389 passam; só os 3 de TikTok falham (já falhavam)

## Divergências
- O relato inicial ("`hasPermission` vale em qualquer workspace") estava errado: o escopo de `Role` já limita ao corrente. O que existia era a falha de `PUT/DELETE /workspaces/{uuid}` (AUTH-03).
