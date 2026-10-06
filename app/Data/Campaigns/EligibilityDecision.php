<?php

namespace App\Data\Campaigns;

final readonly class EligibilityDecision
{
    public function __construct(public bool $eligible, public array $reasons = []) {}
}
