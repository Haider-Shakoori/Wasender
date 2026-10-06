<?php

namespace App\Data\Campaigns;

final readonly class CampaignAudienceCandidate
{
    public function __construct(public string $contactUuid, public int|string $contactKey, public string $sourceType, public ?string $sourceReference) {}
}
