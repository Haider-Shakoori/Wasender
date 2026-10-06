<?php

namespace App\Services\Campaigns;

use App\Enums\WhatsAppCampaignSessionStrategy;
use App\Enums\WhatsAppSessionStatus;
use App\Models\WhatsAppCampaignExecution;
use App\Models\WhatsAppSession;

final class CampaignSessionSelector
{
    public function __construct(private CampaignSessionCapacityService $capacity) {}

    public function select(WhatsAppCampaignExecution $execution): ?WhatsAppSession
    {
        $campaign = $execution->campaign;
        $query = WhatsAppSession::forTenant($execution->tenant_id)->where('status', WhatsAppSessionStatus::Ready)->whereNull('deleted_at');
        if ($campaign->session_strategy !== WhatsAppCampaignSessionStrategy::AutomaticPool) {
            $query->whereIn('id', $campaign->sessionSelections()->where('is_enabled', true)->pluck('whatsapp_session_id'));
        }
        $sessions = $query->get()->filter(fn ($s) => $this->capacity->available($s));

        return $sessions->sortBy(fn ($s) => $s->campaignRecipientExecutions()->whereIn('status', ['claimed', 'processing'])->count())->first();
    }
}
