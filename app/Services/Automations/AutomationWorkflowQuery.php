<?php

namespace App\Services\Automations;

use App\Contracts\TenantContext;
use App\Models\AutomationWorkflow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AutomationWorkflowQuery
{
    public function __construct(private TenantContext $context) {}

    public function paginate(array $f): LengthAwarePaginator
    {
        $sort = in_array($f['sort'] ?? '', ['name', 'status', 'is_enabled', 'created_at', 'updated_at'], true) ? $f['sort'] : 'updated_at';

        return AutomationWorkflow::forTenant($this->context->id())->with(['currentDraftVersion:id,automation_workflow_id,uuid,version_number,status,trigger_type', 'currentPublishedVersion:id,automation_workflow_id,uuid,version_number,status,trigger_type'])->when($f['search'] ?? null, fn ($q, $v) => $q->where('name', 'like', '%'.addcslashes($v, '%_').'%'))->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when(array_key_exists('enabled', $f), fn ($q) => $q->where('is_enabled', filter_var($f['enabled'], FILTER_VALIDATE_BOOL)))->when($f['trigger_type'] ?? null, fn ($q, $v) => $q->whereHas('currentPublishedVersion', fn ($x) => $x->where('trigger_type', $v))->orWhereHas('currentDraftVersion', fn ($x) => $x->where('trigger_type', $v)))->when($f['created_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))->when($f['updated_from'] ?? null, fn ($q, $v) => $q->whereDate('updated_at', '>=', $v))->when(empty($f['archived']), fn ($q) => $q->where('status', '!=', 'archived'))->orderBy($sort, ($f['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc')->paginate(20)->withQueryString();
    }
}
