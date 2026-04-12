<?php

namespace App\Services;

use App\Exceptions\RoleException;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class RoleService extends BaseService
{
    protected array $with = ['permissions', 'users'];
    protected array $normalFilter = ['name'];

    public function __construct(Role $role)
    {
        parent::__construct($role);
    }

    public function createRole(array $data): Role
    {
        $workspaceId = auth()->user()->currentAccess->workspace_id;

        $exists = Role::isRoleNameAlreadyExists($data['name'], $workspaceId);

        if ($exists) {
            throw RoleException::nameAlreadyExists();
        }

        return DB::transaction(function () use ($data, $workspaceId) {
            $role = Role::create([
                'name' => $data['name'],
                'workspace_id' => $workspaceId,
            ]);
            $permissionIds = Permission::whereIn('name', $data['permissions'] ?? [])->pluck('id');
            $role->permissions()->sync($permissionIds);
            return $role->load(['permissions', 'users']);
        });
    }

    public function updateRole(array $data, string $uuid): Role
    {
        return DB::transaction(function () use ($data, $uuid) {
            $role = $this->findByUuid($uuid);

            if (isset($data['name']) && $data['name'] !== $role->name) {
                $workspaceId = auth()->user()->currentAccess->workspace_id;

                if (Role::isRoleNameAlreadyExists($data['name'], $workspaceId)) {
                    throw RoleException::nameAlreadyExists();
                }
            }

            $role->update(['name' => $data['name'] ?? $role->name]);

            if (isset($data['permissions'])) {
                $permissionIds = Permission::whereIn('name', $data['permissions'])->pluck('id');
                $role->permissions()->sync($permissionIds);
            }

            return $role->load(['permissions', 'users']);
        });
    }
}
