<?php

namespace App\Jobs;

use App\Contracts\TenantContext;
use App\Enums\AutomationWorkflowExecutionStatus;
use App\Models\AutomationWorkflowExecution;
use App\Services\Automations\AutomationWorkflowDefinitionHasher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class StartAutomationWorkflowExecution implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $executionId)
    {
        $this->onQueue(config('automations.execution.queues.control'));
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("automation-start:{$this->executionId}"))->expireAfter(120)];
    }

    public function handle(TenantContext $context, ?AutomationWorkflowDefinitionHasher $hasher = null): void
    {
        $e = AutomationWorkflowExecution::with(['tenant', 'workflow', 'version'])->findOrFail($this->executionId);
        $context->set($e->tenant);
        if ($e->status !== AutomationWorkflowExecutionStatus::Pending) {
            return;
        }$hasher ??= app(AutomationWorkflowDefinitionHasher::class);
        if (! $e->workflow->is_enabled || $e->version->definition_hash !== $e->definition_hash || ! hash_equals($e->definition_hash, $hasher->hash($e->version))) {
            $e->forceFill(['status' => AutomationWorkflowExecutionStatus::Failed, 'failed_at' => now(), 'failure_code' => 'workflow_version_stale'])->save();

            return;
        }$e->forceFill(['status' => AutomationWorkflowExecutionStatus::Running, 'started_at' => now(), 'last_heartbeat_at' => now()])->save();
        ContinueAutomationWorkflowExecution::dispatch($e->id);
    }
}
