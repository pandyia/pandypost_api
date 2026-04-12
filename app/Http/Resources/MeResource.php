<?php

namespace App\Http\Resources;

use App\Models\Access;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $access = $this->resolveCurrentAccess();

        $permissions = $this->resolvePermissions($access);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'email_verified_at' => $this->email_verified_at?->format('d/m/Y H:i:s'),
            'is_super_admin' => $this->is_super_admin,
            'current_workspace' => $this->when($access, fn() => [
                'uuid' => $access->workspace?->uuid,
                'name' => $access->workspace?->name,
            ]),
            'role' => $this->when($access, fn() => $access->role?->name),
            'permissions' => $permissions,
        ];
    }

    private function resolvePermissions(?Access $access): array
    {
        if ($this->isSuperAdmin()) {
            return Permission::all()
                ->pluck('name')
                ->all();
        }

        if (!$access || !$access->role) {
            return [];
        }

        return $access->role->permissions
            ->pluck('name')
            ->all();
    }
}
