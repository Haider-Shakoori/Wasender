<?php

namespace App\Services\Automations;

use App\Contracts\TenantContext;
use App\Data\Automations\ArchiveAutomationWorkflowData;
use App\Enums\AutomationWorkflowStatus;
use App\Models\AutomationWorkflow;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

final class ArchiveAutomationWorkflowService
{
    public function __construct(private TenantContext $context, private AutomationWorkflowLifecycleGuard $guard, private AuditService $audit) {}

    public function archive(AutomationWorkflow $workflow, ArchiveAutomationWorkflowData $d, User $actor): AutomationWorkflow
    {
        return DB::transaction(function () use ($workflow, $d, $actor) {
            $w = AutomationWorkflow::forTenant($this->context->id())->whereKey($workflow->id)->lockForUpdate()->firstOrFail();
            if ($w->status === AutomationWorkflowStatus::Archived) {
                return $w;
            }$this->guard->expected($w, $d->expectedVersion);
            $previous = $w->status->value;
            $w->forceFill(['status' => AutomationWorkflowStatus::Archived, 'is_enabled' => false, 'disabled_at' => $w->is_enabled ? now() : $w->disabled_at, 'archived_at' => now(), 'updated_by' => $actor->id, 'version' => $w->version + 1])->save();
            $this->audit->recordDomain('automation_workflow.archived', $actor, $w->tenant, $w, ['workflow_uuid' => $w->uuid, 'previous_status' => $previous, 'new_status' => 'archived']);

            return $w;
        }, 3);
    }
}
