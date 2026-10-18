<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Notifications\Messages\MailMessage;

class WorkspaceInviteDeclinedNotification extends CentralNotification
{
    public function __construct(
        public string $workspaceName,
        public string $declinedByName,
        array $context = [],
        ?int $workspaceId = null,
        ?string $dedupeKey = null,
    ) {
        parent::__construct(
            NotificationType::WORKSPACE_INVITE_DECLINED,
            'Convite recusado',
            "{$declinedByName} recusou seu convite para o workspace {$workspaceName}.",
            '/settings/invites',
            $context,
            $workspaceId,
            $dedupeKey,
        );
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->declinedByName} recusou seu convite")
            ->view('emails.workspace-invite-declined', [
                'notifiable' => $notifiable,
                'workspaceName' => $this->workspaceName,
                'declinedByName' => $this->declinedByName,
                'url' => rtrim(config('app.frontend_url', 'http://localhost:5173'), '/').$this->actionUrl,
            ]);
    }
}
