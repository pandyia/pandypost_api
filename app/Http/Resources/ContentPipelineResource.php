<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentPipelineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                => $this->uuid,
            'title'               => $this->title,
            'description'         => $this->description,
            'stage'               => $this->stage->value,
            'platform'            => $this->platform,
            'due_date'            => $this->due_date?->toDateString(),
            'created_by'          => $this->user?->name,
            'scheduled_post_uuid' => $this->scheduledPost?->uuid,
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),
        ];
    }
}
