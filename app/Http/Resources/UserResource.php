<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentWorkspace = $this->currentAccess ? $this->currentAccess->workspace : null;

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'is_active' => (bool) $this->is_active,
            'remember_me' => (bool) $this->remember_me,
            'access_id' => $this->access_id,
            
            'current_workspace' => $currentWorkspace ? [
                'uuid' => $currentWorkspace->uuid,
                'name' => $currentWorkspace->name,
            ] : null,
        ];
    }
}
