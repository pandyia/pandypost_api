<?php

namespace App\Http\Resources;

use App\Models\Invite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceivedInviteResource extends JsonResource
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
            'invitedBy' => $this->when($this->relationLoaded('invitedBy') && $this->invitedBy, fn() => [
                'uuid' => $this->invitedBy->uuid,
                'name' => $this->invitedBy->name,
                'email' => $this->invitedBy->email,
            ]),
            'workspace' => $this->when($this->relationLoaded('workspaceSender') && $this->workspaceSender, fn() => [
                'uuid' => $this->workspaceSender->uuid,
                'name' => $this->workspaceSender->name,
            ]),
            'role' => $this->when($this->relationLoaded('role') && $this->role, fn() => [
                'uuid' => $this->role->uuid,
                'name' => $this->role->name,
            ]),
            'status' => strtolower($this->status),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'expires_at' => $this->expires_at?->format('Y-m-d H:i:s'),
        ];
    }
}
