<?php

namespace App\Data\Automations;

final readonly class AutomationWorkflowValidationResult
{
    public function __construct(public bool $valid, public array $errors, public array $warnings, public ?string $definitionHash) {}
}
