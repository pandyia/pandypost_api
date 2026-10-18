<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Notifications\Messages\MailMessage;

class WorkspaceInviteAcceptedNotification extends CentralNotification
{
    public function __construct(
        public string $workspaceName,
        public string $acceptedByName,
        array $context = [],
        ?int $workspaceId = null,
        ?string $dedupeKey = null,
    ) {
        parent::__construct(
            NotificationType::WORKSPACE_INVITE_ACCEPTED,
            'Convite aceito',
            "{$acceptedByName} aceitou seu convite para o workspace {$workspaceName}.",
            '/settings/invites',
            $context,
            $workspaceId,
            $dedupeKey,
        );
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->acceptedByName} aceitou seu convite")
            ->view('emails.workspace-invite-accepted', [
                'notifiable' => $notifiable,
                'workspaceName' => $this->workspaceName,
                'acceptedByName' => $this->acceptedByName,
                'url' => rtrim(config('app.frontend_url', 'http://localhost:5173'), '/').$this->actionUrl,
            ]);
    }
}
