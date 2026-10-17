<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Notifications\Messages\MailMessage;

class WorkspaceInviteNotification extends CentralNotification
{
    public function __construct(
        public string $workspaceName,
        public string $invitedByName,
        array $context = [],
        ?int $workspaceId = null,
        ?string $dedupeKey = null,
    ) {
        parent::__construct(
            NotificationType::WORKSPACE_INVITE,
            'Convite para um workspace',
            "{$invitedByName} convidou você para o workspace {$workspaceName}.",
            '/settings/invites',
            $context,
            $workspaceId,
            $dedupeKey,
        );
    }

    public function toMail($notifiable): MailMessage
    {
        $acceptUrl = config('app.frontend_url', 'http://localhost:5173');

        return (new MailMessage)
            ->subject("Convite para o workspace {$this->workspaceName}")
            ->view('emails.workspace-invite', [
                'notifiable' => $notifiable,
                'workspaceName' => $this->workspaceName,
                'invitedByName' => $this->invitedByName,
                'acceptUrl' => $acceptUrl,
            ]);
    }
}
