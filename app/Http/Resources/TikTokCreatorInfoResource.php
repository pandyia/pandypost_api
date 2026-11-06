<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Recorte do creator_info/query do TikTok: só o que o front precisa para montar o post. */
class TikTokCreatorInfoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'creator_nickname' => $this->resource['creator_nickname'] ?? null,
            'privacy_level_options' => $this->resource['privacy_level_options'] ?? [],
            'comment_disabled' => (bool) ($this->resource['comment_disabled'] ?? false),
            'duet_disabled' => (bool) ($this->resource['duet_disabled'] ?? false),
            'stitch_disabled' => (bool) ($this->resource['stitch_disabled'] ?? false),
            'max_video_post_duration_sec' => $this->resource['max_video_post_duration_sec'] ?? null,
        ];
    }
}
