<?php

namespace App\Contracts;

use App\Data\Automations\AutomationActionExecutionContext;
use App\Data\Automations\AutomationActionResult;
use App\Enums\AutomationActionType;

interface AutomationActionHandler
{
    public function supports(AutomationActionType $type): bool;

    public function execute(AutomationActionExecutionContext $context): AutomationActionResult;
}
