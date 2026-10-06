<?php

namespace App\Data\Automations;

final readonly class AutomationActionExecutionContext
{
    public function __construct(public string $executionUuid, public string $stepExecutionUuid, public string $stepKey, public int $attemptNumber, public array $context, public array $parameters) {}
}
