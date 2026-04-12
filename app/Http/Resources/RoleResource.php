<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'user_count' => $this->whenLoaded('users', fn() => $this->users->count()),
            'users' => $this->whenLoaded('users', fn() => $this->users->map(fn($user) => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
            ])),
        ];
    }
}
