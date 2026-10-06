<?php

namespace App\Services\Automations;

use App\Enums\AutomationStepType;

final class AutomationWorkflowStepHandlerRegistry
{
    public function available(AutomationStepType $type): bool
    {
        return $type !== AutomationStepType::Action;
    }
}
