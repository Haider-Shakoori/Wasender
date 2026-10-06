<?php

namespace App\Services\Automations;

use App\Contracts\TenantContext;
use App\Models\AutomationWorkflowStepExecution;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AutomationWorkflowStepExecutionQuery
{
    public function __construct(private TenantContext $tenant) {}

    public function paginate(int $executionId, array $filters = []): LengthAwarePaginator
    {
        $query = AutomationWorkflowStepExecution::query()->where('tenant_id', $this->tenant->id())->where('automation_workflow_execution_id', $executionId);
        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }
        if ($failure = $filters['failure_code'] ?? null) {
            $query->where('failure_code', $failure);
        }

        return $query->orderByDesc('created_at')->paginate(min((int) ($filters['per_page'] ?? 25), 100));
    }
}
