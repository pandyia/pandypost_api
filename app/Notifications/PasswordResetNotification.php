<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class PasswordResetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $resetUrl = config('app.frontend_url', 'http://localhost:5173') . '/forgot-password?token=' . $this->token;

        return (new MailMessage)
            ->subject('Redefinição de Senha')
            ->view('emails.password-reset', [
                'user' => $notifiable,
                'token' => $this->token,
                'resetUrl' => $resetUrl,
            ]);
    }
}
