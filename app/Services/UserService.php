<?php

namespace App\Services;

use App\Exceptions\UserException;
use App\Models\Access;
use App\Models\Role;
use App\Models\User;

class UserService extends BaseService
{
    protected array $normalFilter = ['name', 'email'];

    public function __construct(User $user)
    {
        parent::__construct($user);
    }

    public function removeFromWorkspace(string $userUuid): void
    {
        $authUser = auth()->user();
        $currentWorkspaceId = $authUser->resolveCurrentAccess()->workspace_id;

        $targetUser = $this->findByUuid($userUuid);

        if ($targetUser->id === $authUser->id) {
            throw UserException::cannotRemoveYourself();
        }

        $workspaceMemberCount = Access::where('workspace_id', $currentWorkspaceId)->count();

        if ($workspaceMemberCount <= 1) {
            throw UserException::workspaceMustHaveAtLeastOneUser();
        }

        $access = Access::where('user_id', $targetUser->id)
            ->where('workspace_id', $currentWorkspaceId)
            ->firstOrFail();

        // Se o usuário removido estava ativo nesse workspace, deslogar e redirecionar
        if ($targetUser->access_id === $access->id) {
            $targetUser->tokens()->delete();
            $personalAccess = $targetUser->accesses()
                ->whereHas('workspace', fn($q) => $q->withoutGlobalScope('member')->where('is_personal_team', true))
                ->first();

            $targetUser->update(['access_id' => $personalAccess?->id]);
        }

        $access->delete();
    }

    public function changeRole(string $userUuid, string $roleUuid): void
    {
        $authUser = auth()->user();
        $currentWorkspaceId = $authUser->resolveCurrentAccess()->workspace_id;

        $targetUser = $this->findByUuid($userUuid);

        $role = Role::where('uuid', $roleUuid)
            ->where('workspace_id', $currentWorkspaceId)
            ->first();

        if (!$role) {
            throw UserException::profileNotFound();
        }

        $access = Access::where('user_id', $targetUser->id)
            ->where('workspace_id', $currentWorkspaceId)
            ->firstOrFail();

        $access->update(['role_id' => $role->id]);
    }
}

