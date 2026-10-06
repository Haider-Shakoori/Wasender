<?php

namespace App\Data\Automations;

final readonly class StartAutomationWorkflowData
{
    public function __construct(public string $idempotencyKey, public AutomationWorkflowExecutionContext $context) {}
}
