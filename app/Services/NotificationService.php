<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\Factories\NotificationFactory;

class NotificationService
{
    public function __construct(
        protected NotificationFactory $factory
    ) {}

    public function send(User $user, NotificationType $type, array $payload = []): void
    {
        $notification = $this->factory->make($type, $payload);
        $user->notify($notification);
    }
}
