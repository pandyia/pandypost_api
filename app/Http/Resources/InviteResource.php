<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InviteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $recipient = $this->recipient;

        return [
            'uuid' => $this->uuid,
            'recipient' => $this->when($recipient, fn() => [
                'uuid' => $recipient->uuid,
                'name' => $recipient->name,
                'email' => $recipient->email,
            ], [
                'email' => $this->email,
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
