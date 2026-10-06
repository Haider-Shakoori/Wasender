<?php

namespace App\Enums;

enum WhatsAppMessageStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Sending = 'sending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Read, self::Failed, self::Cancelled, self::Expired], true);
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::Queued, self::Processing], true);
    }

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
