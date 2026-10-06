<?php

namespace App\Enums;

enum WhatsAppCampaignDispatchAttemptStatus: string
{
    case Created = 'created';
    case Queued = 'queued';
    case Processing = 'processing';
    case TransportPending = 'transport_pending';
    case Accepted = 'accepted';
    case Succeeded = 'succeeded';
    case Unknown = 'unknown';
    case FailedTransient = 'failed_transient';
    case FailedPermanent = 'failed_permanent';
    case Cancelled = 'cancelled';
}
