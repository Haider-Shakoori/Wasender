<?php

namespace App\Jobs;

use App\Enums\AutomationWorkflowExecutionStatus as ExecutionStatus;
use App\Enums\AutomationWorkflowStepExecutionStatus as StepStatus;
use App\Models\AutomationWorkflowExecution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;

final class ResumeAutomationWorkflowDelay implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $executionId, public int $stepExecutionId)
    {
        $this->onQueue(config('automations.execution.queues.delays'));
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("automation-delay:{$this->stepExecutionId}"))->expireAfter(120)];
    }

    public function handle(): void
    {
        $continue = false;
        DB::transaction(function () use (&$continue) {
            $e = AutomationWorkflowExecution::lockForUpdate()->findOrFail($this->executionId);
            $s = $e->stepExecutions()->whereKey($this->stepExecutionId)->firstOrFail();
            if ($e->cancel_requested_at) {
                $s->forceFill(['status' => StepStatus::Cancelled, 'cancelled_at' => now()])->save();
                $e->forceFill(['status' => ExecutionStatus::Cancelled, 'cancelled_at' => now(), 'waiting_until' => null])->save();

                return;
            }if ($e->status !== ExecutionStatus::Waiting || $s->status !== StepStatus::Waiting || $s->waiting_until->isFuture()) {
                return;
            }$s->forceFill(['status' => StepStatus::Completed, 'completed_at' => now()])->save();
            $e->forceFill(['status' => ExecutionStatus::Running, 'waiting_until' => null, 'current_step_key' => $s->next_step_key, 'processed_steps' => $e->processed_steps + 1, 'last_heartbeat_at' => now()])->save();
            if (! $s->next_step_key) {
                $e->forceFill(['status' => ExecutionStatus::Completed, 'completed_at' => now()])->save();
            } else {
                $continue = true;
            }
        });
        if ($continue) {
            ContinueAutomationWorkflowExecution::dispatch($this->executionId);
        }
    }
}
