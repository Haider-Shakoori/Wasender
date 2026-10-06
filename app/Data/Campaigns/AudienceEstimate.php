<?php

namespace App\Data\Campaigns;

use Carbon\CarbonImmutable;

final readonly class AudienceEstimate
{
    public function __construct(public int $candidateCount, public ?int $estimatedEligibleCount, public bool $authoritative, public array $warnings, public CarbonImmutable $calculatedAt) {}
}
