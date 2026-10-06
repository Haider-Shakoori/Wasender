<?php

namespace App\Data\Campaigns;

final readonly class CampaignControlData
{
    public function __construct(public string $idempotencyToken, public int $expectedVersion, public ?string $reason = null) {}
}
