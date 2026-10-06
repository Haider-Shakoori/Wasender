<?php

namespace App\Enums;

enum WhatsAppCampaignRecipientStatus: string
{
    case Prepared = 'prepared';
    case Queued = 'queued';
    case Processing = 'processing';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';
}
