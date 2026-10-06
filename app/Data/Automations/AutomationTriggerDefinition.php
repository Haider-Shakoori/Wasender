<?php

namespace App\Data\Automations;

use App\Enums\AutomationTriggerType;

final readonly class AutomationTriggerDefinition
{
    public function __construct(public AutomationTriggerType $type, public string $label, public string $description, public bool $supported, public array $configurationFields, public array $availableConditionFields, public bool $requiresContactContext) {}
}
