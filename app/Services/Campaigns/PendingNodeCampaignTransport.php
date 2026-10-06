<?php

namespace App\Services\Campaigns;

use App\Contracts\WhatsAppCampaignTransport;
use App\Data\Campaigns\CampaignTransportDispatchResult;
use App\Data\Campaigns\CampaignTransportRequest;

final class PendingNodeCampaignTransport implements WhatsAppCampaignTransport
{
    public function dispatch(CampaignTransportRequest $request): CampaignTransportDispatchResult
    {
        return new CampaignTransportDispatchResult(false, false, null, 'transport_unavailable', true);
    }
}
