<?php

namespace App\Services\Automations;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Automations\EnableAutomationWorkflowData;
use App\Enums\AutomationWorkflowStatus;
use App\Models\AutomationWorkflow;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

final class EnableAutomationWorkflowService
{
    public function __construct(private TenantContext $context, private TenantEntitlements $entitlements, private AutomationWorkflowLifecycleGuard $guard, private AuditService $audit) {}

    public function enable(AutomationWorkflow $workflow, EnableAutomationWorkflowData $d, User $actor): AutomationWorkflow
    {
        $this->entitlements->requireFeature('automations.access');

        return DB::transaction(function () use ($workflow, $d, $actor) {
            $w = AutomationWorkflow::forTenant($this->context->id())->whereKey($workflow->id)->lockForUpdate()->firstOrFail();
            if ($w->is_enabled) {
                return $w;
            }$this->guard->expected($w, $d->expectedVersion);
            $this->guard->canEnable($w);
            $previous = $w->status->value;
            $w->forceFill(['is_enabled' => true, 'status' => AutomationWorkflowStatus::Published, 'enabled_at' => now(), 'disabled_at' => null, 'updated_by' => $actor->id, 'version' => $w->version + 1])->save();
            $this->audit->recordDomain('automation_workflow.enabled', $actor, $w->tenant, $w, ['workflow_uuid' => $w->uuid, 'previous_status' => $previous, 'new_status' => $w->status->value]);

            return $w;
        }, 3);
    }
}
