<?php

namespace App\Events;

use App\Models\Workspace;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dados do workspace mudaram (hoje: a foto). Só membros assinam o canal `workspaces.{uuid}`.
 */
class WorkspaceEvent implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Workspace $workspace) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('workspaces.'.$this->workspace->uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'WorkspaceUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'workspace_uuid' => $this->workspace->uuid,
            'avatar' => $this->workspace->avatar,
        ];
    }
}
