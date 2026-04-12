<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SocialAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'platform' => $this->platform,
            'platform_id' => $this->platform_id,
            'nickname' => $this->nickname,
            'avatar' => $this->avatar,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'connected_at' => $this->created_at?->toIso8601String(),
            'scheduled_posts' => ScheduledPostResource::collection($this->whenLoaded('scheduledPosts')),
        ];
    }
}
