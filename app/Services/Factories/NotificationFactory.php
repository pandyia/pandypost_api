<?php

namespace App\Services\Factories;

use App\Enums\NotificationType;
use App\Notifications\CentralNotification;
use App\Notifications\EmailVerificationNotification;
use App\Notifications\PasswordResetNotification;
use App\Notifications\WorkspaceInviteAcceptedNotification;
use App\Notifications\WorkspaceInviteDeclinedNotification;
use App\Notifications\WorkspaceInviteNotification;
use Illuminate\Notifications\Notification;

class NotificationFactory
{
    public function make(NotificationType $type, array $payload): Notification
    {
        $workspaceId = $payload['workspace_id'] ?? null;
        $workspaceContext = ['workspace_uuid' => $payload['workspace_uuid'] ?? null];
        $dedupeKey = isset($payload['resource']) ? "{$type->value}:{$payload['resource']}" : null;

        return match ($type) {
            NotificationType::EMAIL_VERIFICATION => new EmailVerificationNotification($payload['token']),
            NotificationType::PASSWORD_RESET => new PasswordResetNotification($payload['token']),
            NotificationType::WORKSPACE_INVITE => new WorkspaceInviteNotification(
                $payload['workspace_name'],
                $payload['invited_by_name'],
                $workspaceContext + ['invite_uuid' => $payload['resource'] ?? null],
                $workspaceId,
                $dedupeKey,
            ),
            NotificationType::WORKSPACE_INVITE_ACCEPTED => new WorkspaceInviteAcceptedNotification(
                $payload['workspace_name'],
                $payload['accepted_by_name'],
                $workspaceContext + [
                    'invite_uuid' => $payload['resource'] ?? null,
                    'accepted_by_uuid' => $payload['accepted_by_uuid'],
                ],
                $workspaceId,
                $dedupeKey,
            ),
            NotificationType::WORKSPACE_INVITE_DECLINED => new WorkspaceInviteDeclinedNotification(
                $payload['workspace_name'],
                $payload['declined_by_name'],
                $workspaceContext + [
                    'invite_uuid' => $payload['resource'] ?? null,
                    'declined_by_uuid' => $payload['declined_by_uuid'],
                ],
                $workspaceId,
                $dedupeKey,
            ),
            NotificationType::POST_FAILED => new CentralNotification(
                $type,
                'Falha ao publicar',
                "Não foi possível publicar \"{$payload['post_title']}\" no {$payload['platform_label']}.",
                '/pipeline',
                [
                    'post_uuid' => $payload['resource'],
                    'platform' => $payload['platform'],
                    'author_name' => $payload['author_name'],
                ] + $workspaceContext,
                $workspaceId,
                $dedupeKey,
            ),
            NotificationType::POST_PUBLISHED => new CentralNotification(
                $type,
                'Post publicado',
                "\"{$payload['post_title']}\" foi publicado no {$payload['platform_label']}.",
                '/pipeline',
                [
                    'post_uuid' => $payload['resource'],
                    'platform' => $payload['platform'],
                ] + $workspaceContext,
                $workspaceId,
                $dedupeKey,
            ),
            NotificationType::PAYMENT_FAILED => new CentralNotification(
                $type,
                'Pagamento recusado',
                "Não conseguimos processar o pagamento da assinatura do workspace {$payload['workspace_name']}. Atualize a forma de pagamento.",
                '/billing',
                $workspaceContext,
                $workspaceId,
                $dedupeKey,
            ),
            NotificationType::SUBSCRIPTION_CANCELED => new CentralNotification(
                $type,
                'Assinatura cancelada',
                "A assinatura do workspace {$payload['workspace_name']} foi cancelada.",
                '/billing',
                $workspaceContext,
                $workspaceId,
                $dedupeKey,
            ),
            NotificationType::SOCIAL_ACCOUNT_DISCONNECTED => new CentralNotification(
                $type,
                'Conta social desconectada',
                "A conta {$payload['nickname']} ({$payload['platform_label']}) precisa ser reconectada.",
                '/settings/workspace',
                [
                    'social_account_uuid' => $payload['account_uuid'],
                    'platform' => $payload['platform'],
                ] + $workspaceContext,
                $workspaceId,
                $dedupeKey,
            ),
        };
    }
}
