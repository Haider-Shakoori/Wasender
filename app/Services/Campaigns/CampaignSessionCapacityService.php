<?php

namespace App\Services\Campaigns;

use App\Models\WhatsAppSession;

final class CampaignSessionCapacityService
{
    public function available(WhatsAppSession $session): bool
    {
        return $session->campaignRecipientExecutions()->whereIn('status', ['claimed', 'processing'])->count() < config('whatsapp_campaign_execution.session_concurrency');
    }
}
