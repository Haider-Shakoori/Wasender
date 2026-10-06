<?php

namespace App\Services\Campaigns;

use App\Enums\WhatsAppCampaignEvent;
use App\Enums\WhatsAppCampaignPreparationStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

final class InvalidateWhatsAppCampaignPreparationService
{
    public function __construct(private WhatsAppCampaignLifecycleService $lifecycle, private AuditService $audit) {}

    public function invalidate(WhatsAppCampaign $campaign, ?User $actor, int $expectedVersion): void
    {
        DB::transaction(function () use ($campaign, $actor, $expectedVersion): void {
            $campaign = WhatsAppCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            if ($campaign->status !== WhatsAppCampaignStatus::Prepared) {
                return;
            }
            $campaign->activePreparation?->forceFill(['status' => WhatsAppCampaignPreparationStatus::Stale])->save();
            $campaign->recipients()->delete();
            $campaign->exclusions()->delete();
            $campaign->forceFill(['active_preparation_id' => null, 'prepared_at' => null, 'snapshot_recipient_count' => 0, 'eligible_recipient_count' => 0, 'excluded_recipient_count' => 0, 'progress_percentage' => 0, 'last_progress_at' => null])->save();
            $this->lifecycle->transition($campaign, WhatsAppCampaignStatus::Ready, WhatsAppCampaignEvent::PreparationInvalidated, $actor, $expectedVersion);
            $this->audit->recordDomain('whatsapp_campaign.preparation_invalidated', $actor, $campaign->tenant, $campaign, ['campaign_uuid' => $campaign->uuid, 'campaign_version' => $campaign->version]);
        });
    }
}
