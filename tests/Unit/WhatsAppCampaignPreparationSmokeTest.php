<?php

namespace Tests\Unit;

use App\Enums\ContactConsentStatus;
use App\Enums\ContactStatus;
use App\Enums\WhatsAppCampaignAudienceType;
use App\Enums\WhatsAppCampaignExclusionReason;
use App\Models\Contact;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Services\Campaigns\CampaignRecipientEligibilityService;
use Tests\TestCase;

final class WhatsAppCampaignPreparationSmokeTest extends TestCase
{
    public function test_opt_out_and_suppression_cannot_be_bypassed(): void
    {
        $campaign = new WhatsAppCampaign;
        $campaign->forceFill(['tenant_id' => 10, 'audience_type' => WhatsAppCampaignAudienceType::AllEligibleContacts]);
        $contact = new Contact;
        $contact->forceFill(['tenant_id' => 10, 'status' => ContactStatus::Active, 'consent_status' => ContactConsentStatus::Granted, 'phone_normalized' => '+15550102020', 'opted_out_at' => now(), 'suppressed_at' => now()]);

        $decision = app(CampaignRecipientEligibilityService::class)->evaluate($contact, $campaign);

        $this->assertFalse($decision->eligible);
        $this->assertContains(WhatsAppCampaignExclusionReason::OptedOut->value, $decision->reasonCodes);
        $this->assertContains(WhatsAppCampaignExclusionReason::Suppressed->value, $decision->reasonCodes);
    }

    public function test_blocked_and_invalid_contacts_are_excluded(): void
    {
        $campaign = new WhatsAppCampaign;
        $campaign->forceFill(['tenant_id' => 10]);
        $contact = new Contact;
        $contact->forceFill(['tenant_id' => 10, 'status' => ContactStatus::Active, 'consent_status' => ContactConsentStatus::Granted, 'phone_normalized' => 'invalid', 'blocked_at' => now()]);

        $decision = app(CampaignRecipientEligibilityService::class)->evaluate($contact, $campaign);

        $this->assertFalse($decision->eligible);
        $this->assertSame(WhatsAppCampaignExclusionReason::InvalidPhone->value, $decision->primaryReason);
        $this->assertContains(WhatsAppCampaignExclusionReason::Blocked->value, $decision->reasonCodes);
    }

    public function test_recipient_snapshot_identity_and_payload_fields_are_not_mass_assignable(): void
    {
        $recipient = new WhatsAppCampaignRecipient;
        $recipient->fill(['phone_normalized' => '+15550102020', 'campaign_payload_hash' => hash('sha256', 'changed'), 'campaign_version' => 99, 'recipient_status' => 'sent']);

        $this->assertNull($recipient->phone_normalized);
        $this->assertNull($recipient->campaign_payload_hash);
        $this->assertNull($recipient->campaign_version);
        $this->assertNull($recipient->recipient_status);
    }

    public function test_deduplication_key_is_tenant_bound_and_deterministic(): void
    {
        $this->assertSame(hash('sha256', '10|+15550102020'), hash('sha256', '10|+15550102020'));
        $this->assertNotSame(hash('sha256', '10|+15550102020'), hash('sha256', '11|+15550102020'));
    }
}
