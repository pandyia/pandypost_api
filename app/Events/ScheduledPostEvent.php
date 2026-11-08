<?php

namespace App\Events;

use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\Workspace;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ScheduledPostEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public string $uuid;

    public string $status;

    public ?string $workspaceUuid;

    public function __construct(ScheduledPost $post)
    {
        $this->uuid = $post->uuid;
        $this->status = $post->status;

        // O post pode ser fechado por comando (sem workspace corrente): resolve o workspace pela conta social.
        $workspaceId = SocialAccount::withoutGlobalScopes()->whereKey($post->social_account_id)->value('workspace_id');
        $this->workspaceUuid = $workspaceId ? Workspace::allAccess()->whereKey($workspaceId)->value('uuid') : null;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('workspaces.'.$this->workspaceUuid)];
    }

    public function broadcastAs(): string
    {
        return 'ScheduledPostUpdated';
    }

    public function broadcastWith(): array
    {
        return ['uuid' => $this->uuid, 'status' => $this->status];
    }
}
