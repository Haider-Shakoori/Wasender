<?php

namespace App\Enums;

enum WhatsAppCampaignPreparationFailureCode: string
{
    case CampaignNotReady = 'campaign_not_ready';
    case CampaignVersionMismatch = 'campaign_version_mismatch';
    case CampaignPayloadMismatch = 'campaign_payload_mismatch';
    case AudienceInvalid = 'audience_invalid';
    case AudienceSourceMissing = 'audience_source_missing';
    case RecipientLimitExceeded = 'recipient_limit_exceeded';
    case NoEligibleRecipients = 'no_eligible_recipients';
    case SnapshotInsertFailed = 'snapshot_insert_failed';
    case PreparationConflict = 'preparation_conflict';
    case PreparationCancelled = 'preparation_cancelled';
    case InternalError = 'internal_error';
}
