<?php

namespace App\Enums;

enum WhatsAppCampaignPreparationStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Finalizing = 'finalizing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Stale = 'stale';

    public function active(): bool
    {
        return in_array($this, [self::Pending, self::Running, self::Finalizing], true);
    }
}
