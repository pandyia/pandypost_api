<?php

namespace App\Services\Factories;

use App\Enums\NotificationType;
use App\Notifications\EmailVerificationNotification;
use App\Notifications\PasswordResetNotification;
use App\Notifications\WorkspaceInviteNotification;
use Illuminate\Notifications\Notification;

class NotificationFactory
{
    public function make(NotificationType $type, array $payload): Notification
    {
        return match ($type) {
            NotificationType::EMAIL_VERIFICATION => new EmailVerificationNotification($payload['token']),
            NotificationType::PASSWORD_RESET => new PasswordResetNotification($payload['token']),
            NotificationType::WORKSPACE_INVITE => new WorkspaceInviteNotification($payload['workspace_name'], $payload['invited_by_name']),
        };
    }
}
