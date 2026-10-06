<?php

namespace App\Services\Campaigns;

use App\Enums\WhatsAppCampaignEvent;
use App\Enums\WhatsAppCampaignPreparationStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Models\WhatsAppCampaignPreparation;

final class FailWhatsAppCampaignPreparationService
{
    public function __construct(private WhatsAppCampaignLifecycleService $lifecycle) {}

    public function fail(WhatsAppCampaignPreparation $preparation, string $code, string $message): void
    {
        $preparation->refresh();
        if (! $preparation->status->active()) {
            return;
        }
        $preparation->forceFill(['status' => WhatsAppCampaignPreparationStatus::Failed, 'failed_at' => now(), 'failure_code' => $code, 'failure_message' => str($message)->limit(500)])->save();
        $campaign = $preparation->campaign;
        if ($campaign->status === WhatsAppCampaignStatus::Preparing) {
            $this->lifecycle->transition($campaign, WhatsAppCampaignStatus::Failed, WhatsAppCampaignEvent::PreparationFailed, null, null, ['preparation_uuid' => $preparation->uuid, 'failure_code' => $code]);
        }
    }
}
