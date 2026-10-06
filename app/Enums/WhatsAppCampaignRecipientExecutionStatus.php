<?php

namespace App\Enums;

enum WhatsAppCampaignRecipientExecutionStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Claimed = 'claimed';
    case Processing = 'processing';
    case RetryScheduled = 'retry_scheduled';
    case TransportPending = 'transport_pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    public function terminal(): bool
    {
        return in_array($this, [self::Sent, self::Delivered, self::Read, self::Failed, self::Skipped, self::Cancelled], true);
    }
}
