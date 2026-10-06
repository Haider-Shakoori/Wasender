<?php

namespace App\Enums;

enum WhatsAppCampaignExclusionReason: string
{
    case TenantMismatch = 'tenant_mismatch';
    case ContactMissing = 'contact_missing';
    case ContactArchived = 'contact_archived';
    case ContactInactive = 'contact_inactive';
    case InvalidPhone = 'invalid_phone';
    case DuplicatePhone = 'duplicate_phone';
    case ConsentUnknown = 'consent_unknown';
    case ConsentPending = 'consent_pending';
    case ConsentDenied = 'consent_denied';
    case ConsentWithdrawn = 'consent_withdrawn';
    case ConsentExpired = 'consent_expired';
    case OptedOut = 'opted_out';
    case Suppressed = 'suppressed';
    case Blocked = 'blocked';
    case AudienceSourceMissing = 'audience_source_missing';
    case SubscriptionLimit = 'subscription_limit';
    case StaleCampaignVersion = 'stale_campaign_version';
    case StalePayload = 'stale_payload';
    case InternalValidationFailure = 'internal_validation_failure';
    case TemplateRenderFailed = 'template_render_failed';
}
