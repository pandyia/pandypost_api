<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class EmailVerificationNotification extends Notification implements ShouldQueue
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
        $verificationUrl = config('app.frontend_url', 'http://localhost:5173') . '/verify-email?token=' . $this->token;

        return (new MailMessage)
            ->subject('Confirmação de Email')
            ->view('emails.verification', [
                'user' => $notifiable,
                'token' => $this->token,
                'verificationUrl' => $verificationUrl,
            ]);
    }
}
