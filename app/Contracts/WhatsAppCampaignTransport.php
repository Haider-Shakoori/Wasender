<?php

namespace App\Contracts;

use App\Data\Campaigns\CampaignTransportDispatchResult;
use App\Data\Campaigns\CampaignTransportRequest;

interface WhatsAppCampaignTransport
{
    public function dispatch(CampaignTransportRequest $request): CampaignTransportDispatchResult;
}
