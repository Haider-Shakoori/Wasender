<?php

namespace App\Data\Automations;

use Carbon\CarbonImmutable;

final readonly class AutomationRetryDecision
{
    public function __construct(public bool $retry, public ?CarbonImmutable $retryAt, public string $code) {}
}
