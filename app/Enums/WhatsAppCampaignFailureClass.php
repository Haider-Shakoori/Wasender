<?php

namespace App\Enums;

enum WhatsAppCampaignFailureClass: string
{
    case Transient = 'transient';
    case Permanent = 'permanent';
    case Policy = 'policy';
    case Subscription = 'subscription';
    case Session = 'session';
    case Transport = 'transport';
    case Attachment = 'attachment';
    case Recipient = 'recipient';
    case Unknown = 'unknown';
    case Internal = 'internal';
    case Cancelled = 'cancelled';
}
