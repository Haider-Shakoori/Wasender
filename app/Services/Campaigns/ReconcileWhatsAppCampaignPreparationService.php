<?php

namespace App\Services\Campaigns;

use App\Enums\WhatsAppCampaignPreparationStatus;
use App\Models\WhatsAppCampaignPreparation;

final class ReconcileWhatsAppCampaignPreparationService
{
    public function reconcile(WhatsAppCampaignPreparation $preparation): string
    {
        $preparation->refresh();
        if ($preparation->status === WhatsAppCampaignPreparationStatus::Completed && $preparation->campaign->active_preparation_id !== $preparation->id) {
            return 'completed_unbound';
        }
        if ($preparation->status->active() && $preparation->updated_at->lt(now()->subMinutes(config('whatsapp_campaigns.preparation_stale_minutes')))) {
            $preparation->forceFill(['status' => WhatsAppCampaignPreparationStatus::Failed, 'failed_at' => now(), 'failure_code' => 'preparation_conflict', 'failure_message' => 'Preparation stopped making progress and requires retry.'])->save();

            return 'marked_failed';
        }
        if ($preparation->campaign_version !== $preparation->campaign->version || $preparation->campaign_payload_hash !== $preparation->campaign->payload_hash) {
            if ($preparation->status === WhatsAppCampaignPreparationStatus::Completed) {
                $preparation->forceFill(['status' => WhatsAppCampaignPreparationStatus::Stale])->save();
            }

            return 'stale';
        }

        return 'healthy';
    }
}
