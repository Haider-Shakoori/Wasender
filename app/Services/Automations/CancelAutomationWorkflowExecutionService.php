<?php

namespace App\Services\Automations;

use App\Contracts\TenantContext;
use App\Enums\AutomationWorkflowExecutionStatus;
use App\Enums\AutomationWorkflowStepExecutionStatus;
use App\Models\AutomationWorkflowExecution;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

final class CancelAutomationWorkflowExecutionService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit) {}

    public function cancel(AutomationWorkflowExecution $execution, User $actor): AutomationWorkflowExecution
    {
        return DB::transaction(function () use ($execution, $actor) {
            $e = AutomationWorkflowExecution::forTenant($this->tenant->id())->with('tenant')->lockForUpdate()->findOrFail($execution->id);
            if (in_array($e->status, [AutomationWorkflowExecutionStatus::Completed, AutomationWorkflowExecutionStatus::Failed, AutomationWorkflowExecutionStatus::TimedOut, AutomationWorkflowExecutionStatus::Cancelled], true)) {
                return $e;
            }$e->stepExecutions()->whereIn('status', ['pending', 'waiting', 'retry_scheduled'])->update(['status' => AutomationWorkflowStepExecutionStatus::Cancelled->value, 'cancelled_at' => now()]);
            $e->forceFill(['status' => AutomationWorkflowExecutionStatus::Cancelled, 'cancel_requested_at' => $e->cancel_requested_at ?? now(), 'cancelled_at' => now(), 'waiting_until' => null])->save();
            $this->audit->recordDomain('automation_workflow.execution_cancelled', $actor, $e->tenant, $e, ['execution_uuid' => $e->uuid, 'status' => $e->status->value]);

            return $e;
        }, 3);
    }
}
