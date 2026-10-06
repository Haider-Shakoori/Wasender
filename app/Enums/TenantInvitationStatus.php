<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantInvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function canBeAccepted(): bool
    {
        return $this === self::Pending;
    }
}
