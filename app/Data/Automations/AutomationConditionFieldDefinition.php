<?php

namespace App\Data\Automations;

final readonly class AutomationConditionFieldDefinition
{
    public function __construct(public string $key, public string $label, public string $valueType, public array $operators, public array $triggerTypes = [], public int $maximumValues = 1) {}
}
