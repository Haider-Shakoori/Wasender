<?php

namespace App\Services\Campaigns;

use App\Contracts\TenantContext;
use App\Models\WhatsAppCampaign;

final class WhatsAppCampaignExecutionQuery
{
    public function __construct(private TenantContext $context) {}

    public function latest(WhatsAppCampaign $campaign)
    {
        return $campaign->executions()->where('tenant_id', $this->context->id())->with(['reservation', 'preparation'])->latest('id')->first();
    }
}
