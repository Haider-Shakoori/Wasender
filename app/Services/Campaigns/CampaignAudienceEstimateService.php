<?php

namespace App\Services\Campaigns;

use App\Data\Campaigns\AudienceEstimate;
use App\Models\WhatsAppCampaign;
use App\Services\Campaigns\Audience\CampaignAudienceResolverRegistry;
use Illuminate\Support\Facades\Cache;

final class CampaignAudienceEstimateService
{
    public function __construct(private CampaignAudienceResolverRegistry $resolvers, private CampaignAudienceDefinitionHasher $hasher) {}

    public function estimate(WhatsAppCampaign $campaign): AudienceEstimate
    {
        $key = "wa-campaign-estimate:{$campaign->tenant_id}:".$this->hasher->hash($campaign);

        return Cache::remember($key, config('whatsapp_campaigns.estimate_cache_seconds'), fn () => $this->resolvers->for($campaign->audience_type)->estimate($campaign->tenant, $campaign));
    }
}
