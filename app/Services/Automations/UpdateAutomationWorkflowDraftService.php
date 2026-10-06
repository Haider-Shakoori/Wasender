<?php

namespace App\Services\Automations;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Automations\UpdateAutomationWorkflowDraftData;
use App\Enums\AutomationTriggerType;
use App\Models\AutomationWorkflow;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

final class UpdateAutomationWorkflowDraftService
{
    public function __construct(private TenantContext $context, private TenantEntitlements $entitlements, private AutomationWorkflowLifecycleGuard $guard, private AutomationWorkflowDefinitionValidator $validator, private AutomationWorkflowStepWriter $steps, private AuditService $audit) {}

    public function update(AutomationWorkflow $workflow, UpdateAutomationWorkflowDraftData $d, User $actor): AutomationWorkflow
    {
        $this->entitlements->requireFeature('automations.access');
        $this->validator->validate($d->workflow);

        return DB::transaction(function () use ($workflow, $d, $actor) {
            $w = AutomationWorkflow::forTenant($this->context->id())->whereKey($workflow->id)->lockForUpdate()->firstOrFail();
            $w->load('currentDraftVersion');
            $this->guard->expected($w, $d->expectedVersion);
            $this->guard->editable($w);
            $v = $w->currentDraftVersion;
            $v->forceFill(['trigger_type' => AutomationTriggerType::from($d->workflow->triggerType), 'trigger_configuration' => $d->workflow->triggerConfiguration, 'settings' => $d->workflow->settings])->save();
            $this->steps->replace($v, $d->workflow->steps);
            $v->forceFill(['definition_hash' => null, 'validation_status' => null, 'validation_summary' => null, 'validated_at' => null])->save();
            $w->forceFill(['name' => $d->workflow->name, 'description' => $d->workflow->description, 'updated_by' => $actor->id, 'version' => $w->version + 1])->save();
            $this->audit->recordDomain('automation_workflow.updated', $actor, $w->tenant, $w, ['workflow_uuid' => $w->uuid, 'workflow_version_uuid' => $v->uuid, 'version_number' => $v->version_number, 'trigger_type' => $v->trigger_type->value, 'step_count' => count($d->workflow->steps)]);

            return $w->refresh()->load('currentDraftVersion.steps');
        }, 3);
    }
}
