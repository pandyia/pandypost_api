<?php

namespace App\Enums;

enum SubscriptionChangeStatus: string
{
    case PENDING = 'pending';
    case APPLIED = 'applied';
    case CANCELED = 'canceled';
}
