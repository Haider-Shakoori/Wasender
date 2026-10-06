<?php

namespace App\Data\Campaigns;

use Carbon\CarbonImmutable;

final readonly class CampaignRetryDecision
{
    public function __construct(public bool $retry, public ?CarbonImmutable $nextAttemptAt = null, public ?string $reasonCode = null) {}
}
