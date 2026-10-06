<?php

namespace App\Data\Automations;

final readonly class AutomationWorkflowStepData
{
    public function __construct(public string $key, public ?string $parentKey, public string $type, public int $position, public ?string $name, public array $configuration) {}

    public static function from(array $v): self
    {
        return new self($v['step_key'], $v['parent_step_key'] ?? null, $v['step_type'], (int) $v['position'], $v['name'] ?? null, $v['configuration'] ?? []);
    }
}
