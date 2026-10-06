<?php

namespace App\Data\Automations;

use App\Enums\AutomationActionType;

final readonly class AutomationActionDefinition
{
    public function __construct(public AutomationActionType $type, public string $label, public string $description, public bool $supported, public bool $requiresContact, public bool $mayRetry, public array $configurationFields) {}
}
