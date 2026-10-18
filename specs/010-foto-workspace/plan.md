# Plano

- Coluna `workspaces.avatar_path` (única migration, estrutural). `Workspace`: `avatar_path` em `$fillable`/`$hidden`, accessor `avatar` em `$appends` (URL assinada via `StorageService::generateDownloadUrl`, TTL `presigned_avatar_ttl`). O `$appends` cobre as respostas que devolvem o model cru (lista, detalhe, login); os Resources montados à mão ganham o campo.
- `StorageService::generateWorkspaceAvatarUploadUrl` reusa `presignedPut`; `WorkspaceAvatarPathRule` no molde de `AvatarPathRule`.
- `WorkspaceService::setAvatar/removeAvatar` espelham `ProfileService`; disparam `WorkspaceEvent` (`ShouldBroadcastNow` + `ShouldDispatchAfterCommit`).
- `WorkspaceAvatarController` fora do `BaseController` (o `permission:` do middleware olha o workspace corrente); autoriza com `User::hasPermissionInWorkspace`.
- Alternativas descartadas: multipart pela API (spec 006); corrigir `hasPermission()` globalmente (a proteção incidental já cobre o corrente e mexer nela afeta todas as rotas).
- Risco: URL assinada expira (~6 dias); a UI cai na inicial e recarrega na próxima navegação.
