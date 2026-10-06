<?php

namespace App\Services\Automations;

use App\Contracts\TenantContext;
use App\Models\AutomationWorkflowExecution;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AutomationWorkflowExecutionQuery
{
    public function __construct(private TenantContext $tenant) {}

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $q = AutomationWorkflowExecution::forTenant($this->tenant->id())->with(['workflow:id,uuid,name', 'version:id,uuid,version_number']);
        if ($v = $filters['workflow_id'] ?? null) {
            $q->where('automation_workflow_id', $v);
        }if ($v = $filters['status'] ?? null) {
            $q->where('status', $v);
        }if ($v = $filters['trigger_type'] ?? null) {
            $q->where('trigger_type', $v);
        }if ($v = $filters['failure_code'] ?? null) {
            $q->where('failure_code', $v);
        }

        return $q->orderByDesc('created_at')->paginate(min((int) ($filters['per_page'] ?? 25), 100));
    }
}
