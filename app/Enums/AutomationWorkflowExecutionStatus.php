<?php

namespace App\Enums;

enum AutomationWorkflowExecutionStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Waiting = 'waiting';
    case Cancelling = 'cancelling';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Failed = 'failed';
    case TimedOut = 'timed_out';
}
