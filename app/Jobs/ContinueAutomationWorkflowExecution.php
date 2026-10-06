<?php

namespace App\Jobs;

use App\Contracts\TenantContext;
use App\Models\AutomationWorkflowExecution;
use App\Services\Automations\ProcessAutomationWorkflowStepService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class ContinueAutomationWorkflowExecution implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 30;

    public function __construct(public int $executionId)
    {
        $this->onQueue(config('automations.execution.queues.steps'));
    }

    public function uniqueId(): string
    {
        return (string) $this->executionId;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("automation-continue:{$this->executionId}"))->expireAfter(120)];
    }

    public function handle(ProcessAutomationWorkflowStepService $processor, TenantContext $context): void
    {
        $execution = AutomationWorkflowExecution::with('tenant')->findOrFail($this->executionId);
        $context->set($execution->tenant);
        $processor->process($this->executionId);
    }
}
