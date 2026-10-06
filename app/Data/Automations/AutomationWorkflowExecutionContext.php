<?php

namespace App\Data\Automations;

final readonly class AutomationWorkflowExecutionContext
{
    public function __construct(public array $trigger, public array $contact, public array $values) {}

    public function toArray(): array
    {
        return ['trigger' => $this->trigger, 'contact' => $this->contact, 'values' => $this->values];
    }
}
