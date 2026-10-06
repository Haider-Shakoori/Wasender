<?php

namespace App\Services\Automations;

use App\Contracts\TenantContext;
use App\Data\Automations\RestoreAutomationWorkflowData;
use App\Enums\AutomationWorkflowStatus;
use App\Models\AutomationWorkflow;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

final class RestoreAutomationWorkflowService
{
    public function __construct(private TenantContext $context, private AutomationWorkflowLifecycleGuard $guard, private AuditService $audit) {}

    public function restore(AutomationWorkflow $workflow, RestoreAutomationWorkflowData $d, User $actor): AutomationWorkflow
    {
        return DB::transaction(function () use ($workflow, $d, $actor) {
            $w = AutomationWorkflow::forTenant($this->context->id())->whereKey($workflow->id)->lockForUpdate()->firstOrFail();
            $this->guard->expected($w, $d->expectedVersion);
            $this->guard->canRestore($w);
            $status = $w->current_published_version_id ? AutomationWorkflowStatus::Disabled : AutomationWorkflowStatus::Draft;
            $w->forceFill(['status' => $status, 'is_enabled' => false, 'archived_at' => null, 'disabled_at' => now(), 'updated_by' => $actor->id, 'version' => $w->version + 1])->save();
            $this->audit->recordDomain('automation_workflow.restored', $actor, $w->tenant, $w, ['workflow_uuid' => $w->uuid, 'previous_status' => 'archived', 'new_status' => $status->value]);

            return $w;
        }, 3);
    }
}
