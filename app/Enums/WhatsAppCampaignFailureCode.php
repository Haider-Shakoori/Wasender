<?php

namespace App\Enums;

enum WhatsAppCampaignFailureCode: string
{
    case ValidationFailed = 'validation_failed';
    case NoEligibleSession = 'no_eligible_session';
    case StalePayload = 'stale_payload';
    case InternalError = 'internal_error';
}
