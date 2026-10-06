<?php

namespace App\Services\Campaigns;

use App\Data\Campaigns\CampaignRecipientEligibilityDecision;
use App\Enums\ContactConsentStatus;
use App\Enums\ContactStatus;
use App\Enums\WhatsAppCampaignExclusionReason as Reason;
use App\Models\Contact;
use App\Models\WhatsAppCampaign;

final class CampaignRecipientEligibilityService
{
    public function evaluate(Contact $contact, WhatsAppCampaign $campaign): CampaignRecipientEligibilityDecision
    {
        $reasons = [];
        if ($contact->tenant_id !== $campaign->tenant_id) {
            $reasons[] = Reason::TenantMismatch->value;
        }
        if ($contact->trashed() || $contact->status === ContactStatus::Archived) {
            $reasons[] = Reason::ContactArchived->value;
        } elseif ($contact->status !== ContactStatus::Active) {
            $reasons[] = Reason::ContactInactive->value;
        }
        if (! preg_match('/^\+[1-9]\d{7,14}$/', (string) $contact->phone_normalized)) {
            $reasons[] = Reason::InvalidPhone->value;
        }
        if ($contact->blocked_at) {
            $reasons[] = Reason::Blocked->value;
        }
        if ($contact->suppressed_at) {
            $reasons[] = Reason::Suppressed->value;
        }
        if ($contact->opted_out_at) {
            $reasons[] = Reason::OptedOut->value;
        }
        if ($contact->consent_expires_at?->isPast()) {
            $reasons[] = Reason::ConsentExpired->value;
        } elseif ($contact->consent_status !== ContactConsentStatus::Granted) {
            $reasons[] = match ($contact->consent_status) {
                ContactConsentStatus::Pending => Reason::ConsentPending->value,
                ContactConsentStatus::Denied => Reason::ConsentDenied->value,
                ContactConsentStatus::Withdrawn => Reason::ConsentWithdrawn->value,
                ContactConsentStatus::Expired => Reason::ConsentExpired->value,
                default => Reason::ConsentUnknown->value,
            };
        }

        $priority = array_map(fn (Reason $reason) => $reason->value, [Reason::TenantMismatch, Reason::ContactArchived, Reason::InvalidPhone, Reason::Blocked, Reason::Suppressed, Reason::OptedOut, Reason::ConsentDenied, Reason::ConsentWithdrawn, Reason::ConsentExpired, Reason::ConsentUnknown, Reason::ConsentPending, Reason::ContactInactive]);
        usort($reasons, fn (string $a, string $b) => array_search($a, $priority, true) <=> array_search($b, $priority, true));

        return new CampaignRecipientEligibilityDecision($reasons === [], array_values(array_unique($reasons)), $reasons[0] ?? null);
    }
}
