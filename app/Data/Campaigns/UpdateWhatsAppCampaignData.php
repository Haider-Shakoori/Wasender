<?php

namespace App\Data\Campaigns;

final readonly class UpdateWhatsAppCampaignData
{
    public function __construct(public CreateWhatsAppCampaignData $campaign, public int $expectedVersion) {}

    public static function from(array $v): self
    {
        return new self(CreateWhatsAppCampaignData::from($v), $v['expected_version']);
    }
}
