<?php

declare(strict_types=1);

namespace App\Enums;

enum MembershipStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Suspended = 'suspended';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invited',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Removed => 'Removed',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Invited => 'warning',
            self::Suspended => 'danger',
            self::Removed => 'neutral',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
