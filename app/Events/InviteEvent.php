<?php

namespace App\Events;

use App\Models\Invite;
use App\Models\User;
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

        $channels = [
            new PrivateChannel('workspaces.'.$uuid),
        ];

        // Canal pessoal do convidado (se já tem conta): a grid de recebidos atualiza mesmo com o
        // aviso no sino desligado, porque este evento não depende da preferência de notificação.
        $inviteeId = User::withoutGlobalScopes()->where('email', $this->invite->email)->value('id');

        if ($inviteeId) {
            $channels[] = new PrivateChannel('App.Models.User.'.$inviteeId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'InviteUpdated';
    }

    /** Sem e-mail: o canal do workspace é liberado a qualquer membro. */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'invite_uuid' => $this->invite->uuid,
        ];
    }
}
