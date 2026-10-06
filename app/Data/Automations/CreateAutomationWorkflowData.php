<?php

namespace App\Data\Automations;

final readonly class CreateAutomationWorkflowData
{
    public function __construct(public string $name, public ?string $description, public string $triggerType, public array $triggerConfiguration, public array $settings, public array $steps) {}

    public static function from(array $v): self
    {
        return new self($v['name'], $v['description'] ?? null, $v['trigger_type'], $v['trigger_configuration'] ?? [], $v['settings'] ?? [], array_map(AutomationWorkflowStepData::from(...), $v['steps'] ?? []));
    }
}
