<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SwitchWorkspaceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'current_workspace' => [
                'uuid' => $this->workspace->uuid,
                'name' => $this->workspace->name,
            ],
            'role' => $this->role->name,
            'permissions' => $this->role->permissions->pluck('name')->all(),
        ];
    }
}
