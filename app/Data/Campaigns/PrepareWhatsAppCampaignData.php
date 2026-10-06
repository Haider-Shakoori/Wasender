<?php

namespace App\Data\Campaigns;

final readonly class PrepareWhatsAppCampaignData
{
    public function __construct(public string $idempotencyToken, public int $expectedVersion) {}
}
