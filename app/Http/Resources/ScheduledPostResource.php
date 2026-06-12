<?php

namespace App\Http\Resources;

use App\Enums\ScheduledPostStatus;
use App\Services\Storage\StorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduledPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'responsible' => $this->user?->name,
            'media_url' => $this->getMediaUrl(),
            "total_posts_for_account" => $this->totalPostsForAccount(),
            "failed_posts_for_account" => $this->failedPostsForAccount(),
            'platform' => $this->platform,
            'title' => $this->title,
            'caption' => $this->caption,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'platform_post_id' => $this->platform_post_id,
            'platform_post_url' => $this->getPlatformPostUrl(),
            'error_message' => $this->when($this->status === ScheduledPostStatus::FAILED->value, $this->error_message),
            'payload' => $this->payload,
        ];
    }

    /**
     * Gera uma presigned GET URL temporária do S3 para o client visualizar o vídeo.
     */
    private function getMediaUrl(): ?string
    {
        if (!$this->media_path) {
            return null;
        }

        return app(StorageService::class)->generateDownloadUrl($this->media_path);
    }
}
