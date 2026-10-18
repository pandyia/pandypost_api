<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\SetWorkspaceAvatarRequest;
use App\Http\Requests\Workspace\WorkspaceAvatarUploadUrlRequest;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

/**
 * @group Workspaces
 *
 * Foto do workspace, no mesmo fluxo da foto do perfil (URL pré-assinada):
 *
 * 1. `POST /workspaces/{uuid}/avatar/upload-url` com o `content_type` → recebe `url`, `headers` e `path`.
 * 2. O front faz `PUT` direto na `url` com o arquivo (sem token).
 * 3. `PUT /workspaces/{uuid}/avatar` com o `path` → a API confere tipo e tamanho (até 5 MB), troca a foto e apaga a antiga.
 *
 * **Permissão:** `workspaces.update` **no workspace da URL** (ser admin de outro workspace não vale).
 * O campo `avatar` do workspace é uma URL assinada que muda a cada resposta (válida por ~6 dias).
 */
class WorkspaceAvatarController extends Controller
{
    public function __construct(
        private readonly WorkspaceService $service,
    ) {}

    /**
     * Pedir URL de upload da foto
     *
     * @urlParam workspace string required UUID do workspace. Example: 7f3c9a1e-2b4d-4c8e-9a10-5d6e7f8a9b0c
     *
     * @bodyParam content_type string required `image/jpeg`, `image/png` ou `image/webp`. Example: image/jpeg
     *
     * @response 200 {"url": "https://storage.exemplo.com/pandypost/workspaces/7f3c.../avatar/a1b2....jpg?X-Amz-Signature=...", "headers": {}, "path": "workspaces/7f3c.../avatar/a1b2....jpg", "content_type": "image/jpeg", "max_size": 5242880, "expires_in": 86400}
     * @response 403 scenario="sem permissão neste workspace" {"message": "Você não tem permissão para acessar este recurso.", "required_permission": "workspaces.update"}
     */
    public function uploadUrl(Workspace $workspace): JsonResponse
    {
        $this->authorizeUpdate($workspace);

        $data = app(WorkspaceAvatarUploadUrlRequest::class)->validated();

        return response()->json($this->service->generateAvatarUploadUrl($workspace, $data['content_type']));
    }

    /**
     * Confirmar a foto enviada
     *
     * @urlParam workspace string required UUID do workspace. Example: 7f3c9a1e-2b4d-4c8e-9a10-5d6e7f8a9b0c
     *
     * @bodyParam path string required `path` recebido em `upload-url`. Example: workspaces/7f3c.../avatar/a1b2....jpg
     *
     * @response 200 {"data": {"uuid": "7f3c9a1e-2b4d-4c8e-9a10-5d6e7f8a9b0c", "name": "Agência Aurora", "avatar": "https://storage.exemplo.com/...assinada..."}}
     * @response 422 {"error": "workspace_avatar_too_large", "message": "A foto é maior que o limite de 5 MB. Escolha uma imagem menor."}
     */
    public function set(Workspace $workspace): JsonResponse
    {
        $this->authorizeUpdate($workspace);

        $data = app(SetWorkspaceAvatarRequest::class)->validated();

        return $this->respond($this->service->setAvatar($workspace, $data['path']));
    }

    /**
     * Remover a foto
     *
     * Idempotente: sem foto, responde 200 do mesmo jeito.
     *
     * @urlParam workspace string required UUID do workspace. Example: 7f3c9a1e-2b4d-4c8e-9a10-5d6e7f8a9b0c
     *
     * @response 200 {"data": {"uuid": "7f3c9a1e-2b4d-4c8e-9a10-5d6e7f8a9b0c", "name": "Agência Aurora", "avatar": null}}
     */
    public function remove(Workspace $workspace): JsonResponse
    {
        $this->authorizeUpdate($workspace);

        return $this->respond($this->service->removeAvatar($workspace));
    }

    private function respond(Workspace $workspace): JsonResponse
    {
        return response()->json(['data' => [
            'uuid' => $workspace->uuid,
            'name' => $workspace->name,
            'avatar' => $workspace->avatar,
        ]]);
    }

    /** A permissão tem que ser do workspace da URL: `hasPermission` vale para qualquer workspace do usuário. */
    private function authorizeUpdate(Workspace $workspace): void
    {
        $user = request()->user();

        if ($user->isSuperAdmin() || $user->hasPermissionInWorkspace('workspaces.update', $workspace->id)) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'message' => 'Você não tem permissão para acessar este recurso.',
            'required_permission' => 'workspaces.update',
        ], 403));
    }
}
