<?php

namespace App\Services\Campaigns;

use App\Contracts\WhatsAppCampaignTransport;
use App\Data\Campaigns\CampaignTransportDispatchResult;
use App\Data\Campaigns\CampaignTransportRequest;

final class NodeWhatsAppCampaignTransport implements WhatsAppCampaignTransport
{
    public function __construct(private NodeWhatsAppCampaignClient $client, private CampaignTransportRequestBuilder $builder) {}

    public function dispatch(CampaignTransportRequest $request): CampaignTransportDispatchResult
    {
        if (! config('whatsapp_campaign_transport.enabled')) {
            return new CampaignTransportDispatchResult(false);
        }

        return $this->client->dispatch($request, $this->builder->hash($request));
    }
}
