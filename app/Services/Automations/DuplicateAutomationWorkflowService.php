<?php

namespace App\Services\Automations;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Automations\DuplicateAutomationWorkflowData;
use App\Enums\AutomationWorkflowStatus;
use App\Enums\AutomationWorkflowVersionStatus;
use App\Models\AutomationWorkflow;
use App\Models\AutomationWorkflowVersion;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

final class DuplicateAutomationWorkflowService
{
    public function __construct(private TenantContext $context, private TenantEntitlements $entitlements, private AutomationWorkflowStepWriter $steps, private AuditService $audit) {}

    public function duplicate(AutomationWorkflow $source, DuplicateAutomationWorkflowData $d, User $actor): AutomationWorkflow
    {
        $this->entitlements->requireFeature('automations.access');
        $this->entitlements->requireCapacity('automations.max');

        return DB::transaction(function () use ($source, $d, $actor) {
            $source = AutomationWorkflow::forTenant($this->context->id())->whereKey($source->id)->with(['currentDraftVersion.steps', 'currentPublishedVersion.steps'])->firstOrFail();
            $base = $source->currentDraftVersion ?: $source->currentPublishedVersion;
            $w = AutomationWorkflow::create(['tenant_id' => $source->tenant_id, 'name' => $d->name, 'description' => $source->description, 'status' => AutomationWorkflowStatus::Draft, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            $v = AutomationWorkflowVersion::create(['automation_workflow_id' => $w->id, 'version_number' => 1, 'status' => AutomationWorkflowVersionStatus::Draft, 'trigger_type' => $base?->trigger_type, 'trigger_configuration' => $base?->trigger_configuration ?? [], 'settings' => $base?->settings ?? [], 'created_by' => $actor->id]);
            $this->steps->copy($v, $base?->steps ?? []);
            $w->forceFill(['current_draft_version_id' => $v->id])->save();
            $this->audit->recordDomain('automation_workflow.duplicated', $actor, $w->tenant, $w, ['workflow_uuid' => $w->uuid, 'workflow_version_uuid' => $v->uuid, 'version_number' => 1, 'trigger_type' => $v->trigger_type?->value, 'step_count' => $v->steps()->count()]);

            return $w->refresh()->load('currentDraftVersion.steps');
        }, 3);
    }
}
