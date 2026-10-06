<?php

namespace App\Enums;

enum TenantSubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case Grace = 'grace';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function permitsAccess(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::Grace], true);
    }
}
