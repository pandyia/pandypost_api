<?php

namespace App\Enums;

enum NotificationType: string
{
    case EMAIL_VERIFICATION = 'email_verification';
    case PASSWORD_RESET = 'password_reset';
    case WORKSPACE_INVITE = 'workspace_invite';
}
