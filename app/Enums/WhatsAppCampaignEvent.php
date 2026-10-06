<?php

namespace App\Enums;

enum WhatsAppCampaignEvent: string
{
    case Created = 'campaign.created';
    case Updated = 'campaign.updated';
    case Validated = 'campaign.validated';
    case Ready = 'campaign.ready';
    case NeedsAttention = 'campaign.needs_attention';
    case Scheduled = 'campaign.scheduled';
    case Unscheduled = 'campaign.unscheduled';
    case Duplicated = 'campaign.duplicated';
    case Archived = 'campaign.archived';
    case AttachmentAdded = 'campaign.attachment_added';
    case AttachmentReplaced = 'campaign.attachment_replaced';
    case AttachmentRemoved = 'campaign.attachment_removed';
    case AudienceChanged = 'campaign.audience_changed';
    case SessionsChanged = 'campaign.sessions_changed';
    case ScheduleChanged = 'campaign.schedule_changed';
    case PreparationRequested = 'campaign.preparation_requested';
    case PreparationStarted = 'campaign.preparation_started';
    case PreparationProgressed = 'campaign.preparation_progressed';
    case Prepared = 'campaign.prepared';
    case PreparationFailed = 'campaign.preparation_failed';
    case PreparationCancelled = 'campaign.preparation_cancelled';
    case PreparationInvalidated = 'campaign.preparation_invalidated';
    case NoEligibleRecipients = 'campaign.no_eligible_recipients';
    case RecipientLimitExceeded = 'campaign.recipient_limit_exceeded';
    case LaunchRequested = 'campaign.launch_requested';
    case Queued = 'campaign.queued';
    case Started = 'campaign.started';
    case PauseRequested = 'campaign.pause_requested';
    case Paused = 'campaign.paused';
    case ResumeRequested = 'campaign.resume_requested';
    case Resumed = 'campaign.resumed';
    case CancelRequested = 'campaign.cancel_requested';
    case Cancelled = 'campaign.cancelled';
    case ExecutionFailed = 'campaign.execution_failed';
    case ExecutionCompleted = 'campaign.execution_completed';
    case ExecutionCompletedWithErrors = 'campaign.execution_completed_with_errors';
    case ExecutionReconciled = 'campaign.execution_reconciled';
    case UsageReserved = 'campaign.usage_reserved';
    case UsageReleased = 'campaign.usage_released';
    case TransportPending = 'campaign.transport_pending';
}
