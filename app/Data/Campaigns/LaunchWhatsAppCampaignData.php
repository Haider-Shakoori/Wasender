<?php

namespace App\Data\Campaigns;

final readonly class LaunchWhatsAppCampaignData
{
    public function __construct(public string $idempotencyToken, public int $expectedVersion, public string $launchType = 'manual') {}
}
