<?php

namespace App\Services\Automations;

use App\Contracts\AutomationActionHandler;
use App\Data\Automations\AutomationActionExecutionContext;
use App\Data\Automations\AutomationActionResult;
use App\Enums\AutomationActionType;

final class StopWorkflowAutomationActionHandler implements AutomationActionHandler
{
    public function supports(AutomationActionType $type): bool
    {
        return $type === AutomationActionType::StopWorkflow;
    }

    public function execute(AutomationActionExecutionContext $context): AutomationActionResult
    {
        return new AutomationActionResult(true, output: ['reason' => $context->parameters['reason']], stopWorkflow: true);
    }
}
