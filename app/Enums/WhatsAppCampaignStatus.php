<?php

namespace App\Enums;

enum WhatsAppCampaignStatus: string
{
    case Draft = 'draft';
    case Validating = 'validating';
    case NeedsAttention = 'needs_attention';
    case Ready = 'ready';
    case Scheduled = 'scheduled';
    case Preparing = 'preparing';
    case Prepared = 'prepared';
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
    case Archived = 'archived';

    public function editable(): bool
    {
        return in_array($this, [self::Draft, self::NeedsAttention, self::Ready], true);
    }

    public function terminal(): bool
    {
        return in_array($this, [self::Cancelled, self::Completed, self::CompletedWithErrors, self::Failed, self::Archived], true);
    }
}
