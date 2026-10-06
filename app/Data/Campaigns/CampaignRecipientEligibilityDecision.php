<?php

namespace App\Data\Campaigns;

final readonly class CampaignRecipientEligibilityDecision
{
    public function __construct(public bool $eligible, public array $reasonCodes = [], public ?string $primaryReason = null) {}
}
