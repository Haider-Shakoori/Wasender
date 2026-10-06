<?php

namespace App\Data\Automations;

final readonly class AutomationStepProcessingResult
{
    public function __construct(public string $status, public ?string $nextStepKey = null, public array $output = []) {}
}
