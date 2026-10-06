<?php

namespace App\Data\Automations;

final readonly class AutomationActionResult
{
    public function __construct(public bool $successful, public bool $retryable = false, public ?string $failureCode = null, public ?string $safeMessage = null, public array $output = [], public bool $stopWorkflow = false) {}
}
