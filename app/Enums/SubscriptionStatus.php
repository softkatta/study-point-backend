<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Renewed = 'renewed';
    case Pending = 'pending';
    case Expired = 'expired';
    case ExpiringSoon = 'expiring_soon';
    case Cancelled = 'cancelled';
    case Paused = 'paused';
}
