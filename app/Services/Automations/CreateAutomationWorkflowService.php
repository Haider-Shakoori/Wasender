<?php

namespace App\Services\Automations;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Automations\CreateAutomationWorkflowData;
use App\Enums\AutomationTriggerType;
use App\Enums\AutomationWorkflowStatus;
use App\Enums\AutomationWorkflowVersionStatus;
use App\Models\AutomationWorkflow;
use App\Models\AutomationWorkflowVersion;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

final class CreateAutomationWorkflowService
{
    public function __construct(private TenantContext $context, private TenantEntitlements $entitlements, private AutomationWorkflowDefinitionValidator $validator, private AutomationWorkflowStepWriter $steps, private AuditService $audit) {}

    public function create(CreateAutomationWorkflowData $d, User $actor): AutomationWorkflow
    {
        $this->entitlements->requireFeature('automations.access');
        $this->entitlements->requireCapacity('automations.max');
        $this->validator->validate($d);

        return DB::transaction(function () use ($d, $actor) {
            $w = AutomationWorkflow::create(['tenant_id' => $this->context->id(), 'name' => $d->name, 'description' => $d->description, 'status' => AutomationWorkflowStatus::Draft, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            $v = AutomationWorkflowVersion::create(['automation_workflow_id' => $w->id, 'version_number' => 1, 'status' => AutomationWorkflowVersionStatus::Draft, 'trigger_type' => AutomationTriggerType::from($d->triggerType), 'trigger_configuration' => $d->triggerConfiguration, 'settings' => $d->settings, 'created_by' => $actor->id]);
            $this->steps->copy($v, $d->steps);
            $w->forceFill(['current_draft_version_id' => $v->id])->save();
            $this->audit->recordDomain('automation_workflow.created', $actor, $w->tenant, $w, ['workflow_uuid' => $w->uuid, 'workflow_version_uuid' => $v->uuid, 'version_number' => 1, 'trigger_type' => $d->triggerType, 'step_count' => count($d->steps)]);

            return $w->refresh()->load('currentDraftVersion.steps');
        }, 3);
    }
}
