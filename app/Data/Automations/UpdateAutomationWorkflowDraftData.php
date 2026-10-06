<?php

namespace App\Data\Automations;

final readonly class UpdateAutomationWorkflowDraftData
{
    public function __construct(public CreateAutomationWorkflowData $workflow, public int $expectedVersion) {}

    public static function from(array $v): self
    {
        return new self(CreateAutomationWorkflowData::from($v), (int) $v['expected_version']);
    }
}
