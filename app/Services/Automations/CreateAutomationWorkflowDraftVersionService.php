<?php

namespace App\Services\Automations;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Automations\CreateAutomationWorkflowDraftVersionData;
use App\Enums\AutomationWorkflowVersionStatus;
use App\Models\AutomationWorkflow;
use App\Models\AutomationWorkflowVersion;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateAutomationWorkflowDraftVersionService
{
    public function __construct(private TenantContext $context, private TenantEntitlements $entitlements, private AutomationWorkflowLifecycleGuard $guard, private AutomationWorkflowStepWriter $steps, private AuditService $audit) {}

    public function create(AutomationWorkflow $workflow, CreateAutomationWorkflowDraftVersionData $d, User $actor): AutomationWorkflowVersion
    {
        $this->entitlements->requireFeature('automations.access');

        return DB::transaction(function () use ($workflow, $d, $actor) {
            $w = AutomationWorkflow::forTenant($this->context->id())->whereKey($workflow->id)->lockForUpdate()->firstOrFail();
            if ($w->current_draft_version_id) {
                $draft = $w->currentDraftVersion;
                if (! $draft || $draft->automation_workflow_id !== $w->id || $draft->status !== AutomationWorkflowVersionStatus::Draft) {
                    throw ValidationException::withMessages(['version' => 'Active draft pointer is invalid.']);
                }

                return $draft;
            }$this->guard->expected($w, $d->expectedVersion);
            $this->guard->canCreateDraft($w);
            $published = $w->currentPublishedVersion?->load('steps');
            if (! $published || $published->automation_workflow_id !== $w->id) {
                throw ValidationException::withMessages(['version' => 'Published version pointer is invalid.']);
            }$v = AutomationWorkflowVersion::create(['automation_workflow_id' => $w->id, 'version_number' => (int) $w->versions()->max('version_number') + 1, 'status' => AutomationWorkflowVersionStatus::Draft, 'trigger_type' => $published->trigger_type, 'trigger_configuration' => $published->trigger_configuration, 'settings' => $published->settings, 'created_by' => $actor->id]);
            $this->steps->copy($v, $published->steps);
            $w->forceFill(['current_draft_version_id' => $v->id, 'updated_by' => $actor->id, 'version' => $w->version + 1])->save();
            $this->audit->recordDomain('automation_workflow.draft_created', $actor, $w->tenant, $w, ['workflow_uuid' => $w->uuid, 'workflow_version_uuid' => $v->uuid, 'version_number' => $v->version_number, 'trigger_type' => $v->trigger_type->value, 'step_count' => $published->steps->count()]);

            return $v->refresh()->load('steps');
        }, 3);
    }
}
