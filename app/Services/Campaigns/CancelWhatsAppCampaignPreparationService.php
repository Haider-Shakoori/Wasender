<?php

namespace App\Services\Campaigns;

use App\Enums\WhatsAppCampaignEvent;
use App\Enums\WhatsAppCampaignPreparationStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

final class CancelWhatsAppCampaignPreparationService
{
    public function __construct(private WhatsAppCampaignLifecycleService $lifecycle, private AuditService $audit) {}

    public function cancel(WhatsAppCampaign $campaign, ?User $actor): void
    {
        DB::transaction(function () use ($campaign, $actor): void {
            $campaign = WhatsAppCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $preparation = $campaign->preparations()->whereIn('status', ['pending', 'running', 'finalizing'])->lockForUpdate()->latest('id')->first();
            if (! $preparation) {
                return;
            }
            $preparation->forceFill(['status' => WhatsAppCampaignPreparationStatus::Cancelled, 'cancelled_at' => now(), 'failure_code' => 'preparation_cancelled'])->save();
            $this->lifecycle->transition($campaign, WhatsAppCampaignStatus::Ready, WhatsAppCampaignEvent::PreparationCancelled, $actor, null, ['preparation_uuid' => $preparation->uuid]);
            $this->audit->recordDomain('whatsapp_campaign.preparation_cancelled', $actor, $campaign->tenant, $campaign, ['campaign_uuid' => $campaign->uuid, 'preparation_uuid' => $preparation->uuid]);
        });
    }
}
