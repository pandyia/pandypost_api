<?php

namespace App\Services;

use App\Exceptions\WorkspaceException;
use App\Models\Access;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WorkspaceService extends BaseService
{
    // protected array $with = ['accesses'];

    public function __construct(Workspace $workspace)
    {
        parent::__construct($workspace);
    }

    public function store(array $data): Model
    {
        $user = auth()->user();

        $alreadyExists = $user->accesses()
            ->whereHas('workspace', fn($q) => $q->where('name', $data['name']))
            ->exists();

        if ($alreadyExists) {
            throw WorkspaceException::nameAlreadyExists();
        }

        return $this->createAllAccess($user, $data);
    }

    public function createAllAccess(User $user, array $data): Workspace
    {
        return DB::transaction(function () use ($user, $data) {
            $workspace = Workspace::create([
                'name' => $data['name'],
                'is_personal_team' => $data['is_personal_team'] ?? false,
            ]);

            $role = Role::create([
                'name' => $data['role_name'] ?? 'Administrador',
                'workspace_id' => $workspace->id,
            ]);

            $role->permissions()->syncWithoutDetaching($data['permissions'] ?? Role::adminPermissions());

            Access::create([
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'role_id' => $role->id,
            ]);

            return $workspace;
        });
    }

    public function destroy(int|string $id): void
    {
        $entity = $this->findById($id);

        if ($entity->is_personal_team) {
            throw WorkspaceException::personalTeamCannotDeleted();
        }

        $entity->delete();
    }

    public function destroyByUuid(string $uuid): void
    {
        $entity = $this->findByUuid($uuid);

        if ($entity->is_personal_team) {
            throw WorkspaceException::personalTeamCannotDeleted();
        }

        $entity->delete();
    }

    public function getAllSystemWorkspaces()
    {
        return $this->model->allAccess()->get();
    }

    public function switchWorkspace(User $user, string $workspaceUuid): Access
    {
        $workspace = Workspace::where('uuid', $workspaceUuid)->firstOrFail();

        $access = $user->accesses()
            ->where('workspace_id', $workspace->id)
            ->first();

        if (!$access) {
            throw WorkspaceException::userNotLinked();
        }

        $user->update(['access_id' => $access->id]);
        $user->unsetRelation('currentAccess');

        return $access->load('role.permissions', 'workspace');
    }
}
