<?php

namespace App\Enums;

enum WhatsAppCampaignExecutionStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Running = 'running';
    case Pausing = 'pausing';
    case Paused = 'paused';
    case Resuming = 'resuming';
    case Cancelling = 'cancelling';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';
    case Stale = 'stale';

    public function active(): bool
    {
        return in_array($this, [self::Pending, self::Queued, self::Running, self::Pausing, self::Paused, self::Resuming, self::Cancelling], true);
    }
}
