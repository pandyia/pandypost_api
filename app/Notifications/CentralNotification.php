<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use App\Notifications\Channels\WorkspaceDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificação da central: database + broadcast conforme a preferência "no app"; e-mail conforme a preferência de e-mail.
 * `data` tem chaves fixas e nunca carrega tokens nem mensagens cruas de terceiros.
 */
class CentralNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public NotificationType $type,
        public string $title,
        public string $message,
        public string $actionUrl,
        public array $context = [],
        public ?int $workspaceId = null,
        public ?string $dedupeKey = null,
    ) {}

    public function via($notifiable): array
    {
        $channels = [];

        if (! ($notifiable instanceof User) || $notifiable->wantsInAppFor($this->type)) {
            $channels = [WorkspaceDatabaseChannel::class, 'broadcast'];
        }

        if ($notifiable instanceof User && $notifiable->wantsEmailFor($this->type)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function databaseType($notifiable): string
    {
        return $this->type->value;
    }

    public function broadcastType(): string
    {
        return $this->type->value;
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => $this->type->value,
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $this->actionUrl,
            'context' => $this->context,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->view('emails.notification', [
                'notifiable' => $notifiable,
                'title' => $this->title,
                'body' => $this->message,
                'url' => rtrim(config('app.frontend_url', 'http://localhost:5173'), '/').$this->actionUrl,
            ]);
    }
}
