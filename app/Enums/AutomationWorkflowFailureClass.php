<?php

namespace App\Enums;

enum AutomationWorkflowFailureClass: string
{
    case Transient = 'transient';
    case Permanent = 'permanent';
    case Policy = 'policy';
    case Cancelled = 'cancelled';
    case Timeout = 'timeout';
    case Internal = 'internal';
}
