<?php

namespace App\Enums;

enum AutomationWorkflowStepExecutionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Failed = 'failed';
    case RetryScheduled = 'retry_scheduled';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';
}
