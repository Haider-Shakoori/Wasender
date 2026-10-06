<?php

namespace App\Data\Automations;

final readonly class DisableAutomationWorkflowData
{
    public function __construct(public int $expectedVersion) {}

    public static function from(array $v): self
    {
        return new self((int) $v['expected_version']);
    }
}
