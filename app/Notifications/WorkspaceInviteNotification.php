<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class WorkspaceInviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $workspaceName,
        public string $invitedByName,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
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
