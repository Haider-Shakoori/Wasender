<?php

namespace App\Services\Automations;

use App\Contracts\TenantContext;
use App\Data\Automations\DisableAutomationWorkflowData;
use App\Enums\AutomationWorkflowStatus;
use App\Models\AutomationWorkflow;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

final class DisableAutomationWorkflowService
{
    public function __construct(private TenantContext $context, private AutomationWorkflowLifecycleGuard $guard, private AuditService $audit) {}

    public function disable(AutomationWorkflow $workflow, DisableAutomationWorkflowData $d, User $actor): AutomationWorkflow
    {
        return DB::transaction(function () use ($workflow, $d, $actor) {
            $w = AutomationWorkflow::forTenant($this->context->id())->whereKey($workflow->id)->lockForUpdate()->firstOrFail();
            if (! $w->is_enabled) {
                return $w;
            }$this->guard->expected($w, $d->expectedVersion);
            $previous = $w->status->value;
            $w->forceFill(['is_enabled' => false, 'status' => $w->current_published_version_id ? AutomationWorkflowStatus::Disabled : AutomationWorkflowStatus::Draft, 'disabled_at' => now(), 'updated_by' => $actor->id, 'version' => $w->version + 1])->save();
            $this->audit->recordDomain('automation_workflow.disabled', $actor, $w->tenant, $w, ['workflow_uuid' => $w->uuid, 'previous_status' => $previous, 'new_status' => $w->status->value]);

            return $w;
        }, 3);
    }
}
