# 010 — Foto do workspace

## Contexto
O workspace aparece só pela inicial do nome. Agências com vários clientes querem reconhecê-lo por uma imagem. A foto do usuário (spec 006) já define o padrão: URL pré-assinada, confirmação com checagem real de arquivo, URL de leitura assinada.

## Escopo
Enviar, trocar e remover a foto do workspace; expor `avatar` onde o workspace aparece; avisar os membros em tempo real. Inclui o conserto de autorização descoberto no caminho (ver "Spec reversa").

## Requisitos
- RF-1: `POST /api/workspaces/{uuid}/avatar/upload-url` (`content_type` jpeg/png/webp, `throttle:20,1`) devolve `url`, `headers`, `path` (`workspaces/{uuid}/avatar/{uuid}.{ext}`), `content_type`, `max_size`, `expires_in`.
- RF-2: `PUT /api/workspaces/{uuid}/avatar {path}` confere o arquivo de verdade (mime e até 5 MB), troca a foto, apaga a antiga e responde `{data: {uuid, name, avatar}}`. Arquivo inválido ou grande é apagado do S3: `workspace_invalid_avatar_file` / `workspace_avatar_too_large` (422).
- RF-3: `DELETE /api/workspaces/{uuid}/avatar` apaga o arquivo e zera a foto; idempotente.
- RF-4: `avatar` (URL assinada, ~6 dias, muda a cada resposta; `null` sem foto) em `/me`, troca de workspace, `UserResource`, lista/detalhe de workspaces, login/cadastro e `workspace` dos convites recebidos. O path nunca sai (`avatar_path` em `$hidden`).
- RF-5: ao definir/remover, `WorkspaceUpdated` em `private workspaces.{uuid}` com `{workspace_uuid, avatar}`.
- RF-6: workspace pessoal também pode ter foto.
- RF-7: os três endpoints exigem `workspaces.update` **no workspace da URL**; não-membro recebe 404.
- RF-8: `PUT` e `DELETE /api/workspaces/{uuid}` passam a exigir `workspaces.update` / `workspaces.delete` **no workspace da URL**.

## Spec reversa (permissões/workspace — área crítica)
- O middleware `permission:` chama `User::hasPermission()`, que olha os acessos do usuário com `role.permissions`. O `Role` tem o escopo global do workspace **corrente**, então a checagem vale só no corrente (proteção incidental, coberta por teste em `AuthorizationVulnerabilitiesTest`). Isto **não** é "qualquer workspace".
- **Falha confirmada (AUTH-03):** `PUT/DELETE /api/workspaces/{uuid}` confere a permissão no workspace corrente mas age no workspace da URL (basta ser membro, via escopo `member`). Um admin do A, só membro do B, renomeava (200) e excluía (204) o B. Reproduzido em `WorkspacePermissionScopeTest` antes do conserto.
- Conserto: `WorkspaceController::update/destroy` conferem `hasPermissionInWorkspace` no workspace da URL (superadmin libera). `User::hasPermissionInWorkspace` ignora o escopo de `Role`.
- Fora de escopo: a proteção incidental de `hasPermission()` segue como está.

## Critérios de aceite
`WorkspaceAvatarTest` e `WorkspacePermissionScopeTest` (Pest) cobrem RF-1 a RF-8, incluindo admin do A em workspace B (403) e quem tem a permissão só no B. Fluxo real verificado contra o MinIO.

## Fora de escopo
Apagar a foto do S3 ao excluir o workspace (o `WorkspaceObserver` não limpa arquivos); `GET /workspaces/{uuid}` (BUG-01 já registrado).

## Questões em aberto
Nenhuma.
