<?php

namespace App\Events;

use App\Models\Invite;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InviteEvent implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $action,
        public Invite $invite
    ) {}

    public function broadcastOn(): array
    {
        $uuid = $this->invite->workspaceSender()->withoutGlobalScopes()->value('uuid');

        return [
            new PrivateChannel('workspaces.'.$uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'InviteUpdated';
    }

    /** Sem e-mail: o canal é liberado a qualquer membro do workspace. */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'invite_uuid' => $this->invite->uuid,
        ];
    }
}
