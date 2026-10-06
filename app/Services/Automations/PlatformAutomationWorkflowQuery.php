<?php

namespace App\Services\Automations;

use App\Models\AutomationWorkflow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PlatformAutomationWorkflowQuery
{
    public function paginate(array $f): LengthAwarePaginator
    {
        return AutomationWorkflow::with(['tenant:id,uuid,name', 'currentDraftVersion:id,automation_workflow_id,uuid,version_number,status,trigger_type', 'currentPublishedVersion:id,automation_workflow_id,uuid,version_number,status,trigger_type'])->when($f['tenant_id'] ?? null, fn ($q, $v) => $q->where('tenant_id', $v))->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when(array_key_exists('enabled', $f), fn ($q) => $q->where('is_enabled', filter_var($f['enabled'], FILTER_VALIDATE_BOOL)))->when($f['trigger_type'] ?? null, fn ($q, $v) => $q->whereHas('versions', fn ($x) => $x->where('trigger_type', $v)))->when($f['created_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))->when($f['updated_from'] ?? null, fn ($q, $v) => $q->whereDate('updated_at', '>=', $v))->latest('updated_at')->paginate(25)->withQueryString();
    }
}
