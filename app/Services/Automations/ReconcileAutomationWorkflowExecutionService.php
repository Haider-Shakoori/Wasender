<?php

namespace App\Services\Automations;

use App\Enums\AutomationWorkflowExecutionStatus;
use App\Jobs\ContinueAutomationWorkflowExecution;
use App\Jobs\ResumeAutomationWorkflowDelay;
use App\Models\AutomationWorkflowExecution;

final class ReconcileAutomationWorkflowExecutionService
{
    public function reconcile(int $limit = 100): int
    {
        $count = 0;
        AutomationWorkflowExecution::query()->whereIn('status', ['running', 'waiting', 'cancelling'])->where(function ($q) {
            $q->where('last_heartbeat_at', '<', now()->subMinutes(config('automations.execution.stale_minutes')))->orWhere('waiting_until', '<=', now());
        })->limit(min($limit, 500))->get()->each(function ($e) use (&$count) {
            if ($e->started_at?->addMinutes($e->maximum_execution_minutes)->isPast()) {
                $e->forceFill(['status' => AutomationWorkflowExecutionStatus::TimedOut, 'timed_out_at' => now(), 'failure_code' => 'execution_timed_out'])->save();
                $count++;

                return;
            }if ($e->cancel_requested_at) {
                $e->forceFill(['status' => AutomationWorkflowExecutionStatus::Cancelled, 'cancelled_at' => now(), 'waiting_until' => null])->save();
                $count++;

                return;
            }if ($e->status === AutomationWorkflowExecutionStatus::Waiting) {
                $s = $e->stepExecutions()->where('status', 'waiting')->latest('id')->first();
                if ($s) {
                    ResumeAutomationWorkflowDelay::dispatch($e->id, $s->id);
                }
            } else {
                $e->stepExecutions()->where('status', 'processing')->where('started_at', '<', now()->subMinutes(config('automations.execution.stale_minutes')))->update(['status' => 'retry_scheduled', 'failure_class' => 'transient', 'failure_code' => 'internal_error']);
                ContinueAutomationWorkflowExecution::dispatch($e->id);
            }$e->forceFill(['last_heartbeat_at' => now()])->save();
            $count++;
        });

        return $count;
    }
}
