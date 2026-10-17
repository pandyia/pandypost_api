<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Canal `database` que também grava o workspace de origem e a chave de idempotência.
 */
class WorkspaceDatabaseChannel extends DatabaseChannel
{
    protected function buildPayload($notifiable, Notification $notification)
    {
        return array_merge(parent::buildPayload($notifiable, $notification), [
            'workspace_id' => $notification->workspaceId ?? null,
            'dedupe_key' => $notification->dedupeKey ?? null,
        ]);
    }
}
