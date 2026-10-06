<?php

namespace App\Services\Automations;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Automations\PublishAutomationWorkflowData;
use App\Enums\AutomationWorkflowStatus;
use App\Enums\AutomationWorkflowVersionStatus;
use App\Models\AutomationWorkflow;
use App\Models\AutomationWorkflowVersion;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PublishAutomationWorkflowService
{
    public function __construct(private TenantContext $context, private TenantEntitlements $entitlements, private AutomationWorkflowLifecycleGuard $guard, private ValidateAutomationWorkflowVersionService $validator, private FreezeAutomationActionResourcesService $freezer, private AuditService $audit) {}

    public function publish(AutomationWorkflow $workflow, PublishAutomationWorkflowData $d, User $actor): AutomationWorkflowVersion
    {
        $this->entitlements->requireFeature('automations.access');

        return DB::transaction(function () use ($workflow, $d, $actor) {
            $w = AutomationWorkflow::forTenant($this->context->id())->whereKey($workflow->id)->lockForUpdate()->firstOrFail();
            if (! $w->current_draft_version_id && $w->current_published_version_id) {
                return $w->currentPublishedVersion;
            }$w->load('currentDraftVersion.steps');
            $this->guard->expected($w, $d->expectedVersion);
            $this->guard->publishable($w);
            $v = $w->currentDraftVersion;
            if (! $v || $v->automation_workflow_id !== $w->id) {
                throw ValidationException::withMessages(['version' => 'Active draft pointer is invalid.']);
            }
            $this->freezer->freeze($v);
            $v->load('steps');
            $validation = $this->validator->validate($v, true);
            if (! $validation->valid) {
                throw ValidationException::withMessages(['workflow' => array_values(array_unique(array_column($validation->errors, 'code')))]);
            }
            if ($w->current_published_version_id) {
                AutomationWorkflowVersion::whereKey($w->current_published_version_id)->where('automation_workflow_id', $w->id)->where('status', 'published')->update(['status' => 'superseded', 'superseded_at' => now(), 'updated_at' => now()]);
            }$v->forceFill(['status' => AutomationWorkflowVersionStatus::Published, 'definition_hash' => $validation->definitionHash, 'validation_status' => 'valid', 'schema_version' => config('automations.schema_version'), 'published_by' => $actor->id, 'published_at' => now()])->save();
            $previous = $w->status->value;
            $w->forceFill(['current_draft_version_id' => null, 'current_published_version_id' => $v->id, 'status' => $w->is_enabled ? AutomationWorkflowStatus::Published : AutomationWorkflowStatus::Disabled, 'published_at' => now(), 'updated_by' => $actor->id, 'version' => $w->version + 1])->save();
            $this->audit->recordDomain('automation_workflow.published', $actor, $w->tenant, $w, ['workflow_uuid' => $w->uuid, 'workflow_version_uuid' => $v->uuid, 'version_number' => $v->version_number, 'trigger_type' => $v->trigger_type->value, 'previous_status' => $previous, 'new_status' => $w->status->value, 'step_count' => $v->steps->count()]);

            return $v->refresh();
        }, 3);
    }
}
