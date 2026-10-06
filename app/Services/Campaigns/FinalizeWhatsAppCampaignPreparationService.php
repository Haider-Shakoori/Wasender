<?php

namespace App\Services\Campaigns;

use App\Contracts\TenantEntitlements;
use App\Enums\WhatsAppCampaignEvent;
use App\Enums\WhatsAppCampaignPreparationStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignPreparation;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

final class FinalizeWhatsAppCampaignPreparationService
{
    public function __construct(private TenantEntitlements $entitlements, private WhatsAppCampaignLifecycleService $lifecycle, private AuditService $audit) {}

    public function finalize(WhatsAppCampaignPreparation $preparation): void
    {
        DB::transaction(function () use ($preparation): void {
            $preparation = WhatsAppCampaignPreparation::whereKey($preparation->id)->lockForUpdate()->firstOrFail();
            if ($preparation->status === WhatsAppCampaignPreparationStatus::Completed) {
                return;
            }
            $campaign = WhatsAppCampaign::whereKey($preparation->whatsapp_campaign_id)->lockForUpdate()->firstOrFail();
            if ($campaign->version !== $preparation->campaign_version || $campaign->payload_hash !== $preparation->campaign_payload_hash) {
                $preparation->forceFill(['status' => WhatsAppCampaignPreparationStatus::Stale, 'failed_at' => now(), 'failure_code' => 'campaign_payload_mismatch', 'failure_message' => 'Campaign changed during preparation.'])->save();
                $this->lifecycle->transition($campaign, WhatsAppCampaignStatus::NeedsAttention, WhatsAppCampaignEvent::PreparationFailed, null);

                return;
            }
            $eligible = $preparation->recipients()->count();
            $excluded = $preparation->exclusions()->count();
            $limit = $this->entitlements->limit('campaigns.recipients_per_campaign_max');
            if (! $limit->unlimited && $eligible > ($limit->value ?? 0)) {
                $preparation->forceFill(['status' => WhatsAppCampaignPreparationStatus::Failed, 'failed_at' => now(), 'failure_code' => 'recipient_limit_exceeded', 'failure_message' => 'The prepared audience exceeds the subscription recipient limit.'])->save();
                $this->lifecycle->transition($campaign, WhatsAppCampaignStatus::NeedsAttention, WhatsAppCampaignEvent::RecipientLimitExceeded, null);

                return;
            }
            if ($eligible === 0) {
                $preparation->forceFill(['status' => WhatsAppCampaignPreparationStatus::Failed, 'failed_at' => now(), 'failure_code' => 'no_eligible_recipients', 'failure_message' => 'No eligible recipients remained after consent and safety checks.', 'excluded_count' => $excluded, 'progress_percentage' => 100])->save();
                $campaign->forceFill(['excluded_recipient_count' => $excluded, 'progress_percentage' => 100, 'failure_code' => 'no_eligible_recipients'])->save();
                $this->lifecycle->transition($campaign, WhatsAppCampaignStatus::NeedsAttention, WhatsAppCampaignEvent::NoEligibleRecipients, null);

                return;
            }
            $preparation->forceFill(['status' => WhatsAppCampaignPreparationStatus::Completed, 'eligible_count' => $eligible, 'excluded_count' => $excluded, 'processed_candidates' => $eligible + $excluded, 'progress_percentage' => 100, 'completed_at' => now()])->save();
            $campaign->forceFill(['active_preparation_id' => $preparation->id, 'prepared_at' => now(), 'snapshot_recipient_count' => $eligible, 'eligible_recipient_count' => $eligible, 'excluded_recipient_count' => $excluded, 'progress_percentage' => 100, 'last_progress_at' => now(), 'failure_code' => null, 'failure_message' => null])->save();
            $this->lifecycle->transition($campaign, WhatsAppCampaignStatus::Prepared, WhatsAppCampaignEvent::Prepared, null, null, ['preparation_uuid' => $preparation->uuid, 'eligible_count' => $eligible, 'excluded_count' => $excluded]);
            $this->audit->recordDomain('whatsapp_campaign.prepared', null, $campaign->tenant, $campaign, ['campaign_uuid' => $campaign->uuid, 'preparation_uuid' => $preparation->uuid, 'campaign_version' => $campaign->version, 'eligible_count' => $eligible, 'excluded_count' => $excluded]);
        });
    }
}
