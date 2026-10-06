<?php

namespace App\Services\Automations;

use App\Enums\AutomationWorkflowStatus;
use App\Enums\AutomationWorkflowVersionStatus;
use App\Models\AutomationWorkflow;
use Illuminate\Validation\ValidationException;

final class AutomationWorkflowLifecycleGuard
{
    public function expected(AutomationWorkflow $w, int $v): void
    {
        if ($w->version !== $v) {
            throw ValidationException::withMessages(['expected_version' => 'Workflow changed; refresh and try again.']);
        }
    }

    public function editable(AutomationWorkflow $w): void
    {
        if ($w->status === AutomationWorkflowStatus::Archived || ! $w->currentDraftVersion || $w->currentDraftVersion->status !== AutomationWorkflowVersionStatus::Draft) {
            throw ValidationException::withMessages(['workflow' => 'Only an active draft is editable.']);
        }
    }

    public function publishable(AutomationWorkflow $w): void
    {
        $this->editable($w);
    }

    public function canEnable(AutomationWorkflow $w): void
    {
        if ($w->status === AutomationWorkflowStatus::Archived || ! $w->current_published_version_id) {
            throw ValidationException::withMessages(['workflow' => 'Only a published, non-archived workflow can be enabled.']);
        }
        $published = $w->currentPublishedVersion;
        if (! $published || $published->validation_status !== 'valid' || ! $published->definition_hash) {
            throw ValidationException::withMessages(['workflow' => 'The published workflow definition is not valid.']);
        }
        if ($published->trigger_type->value !== 'manual') {
            throw ValidationException::withMessages(['workflow' => 'Only manual workflows can be enabled.']);
        }
        $registry = app(AutomationActionHandlerRegistry::class);
        foreach ($published->steps()->where('step_type', 'action')->get() as $step) {
            if (! $registry->has($step->configuration['action_type'] ?? '')) {
                throw ValidationException::withMessages(['workflow' => 'A workflow action handler is unavailable.']);
            }
        }
    }

    public function canCreateDraft(AutomationWorkflow $w): void
    {
        if ($w->status === AutomationWorkflowStatus::Archived || ! $w->current_published_version_id) {
            throw ValidationException::withMessages(['workflow' => 'A published workflow is required.']);
        }
    }

    public function canRestore(AutomationWorkflow $w): void
    {
        if ($w->status !== AutomationWorkflowStatus::Archived) {
            throw ValidationException::withMessages(['workflow' => 'Workflow is not archived.']);
        }
    }
}
