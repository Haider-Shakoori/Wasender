<?php

namespace App\Services\Automations;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Automations\StartAutomationWorkflowData;
use App\Enums\AutomationWorkflowExecutionStatus;
use App\Jobs\StartAutomationWorkflowExecution;
use App\Models\AutomationWorkflow;
use App\Models\AutomationWorkflowExecution;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StartAutomationWorkflowService
{
    public function __construct(private TenantContext $tenant, private TenantEntitlements $entitlements, private AutomationWorkflowDefinitionHasher $hasher, private AuditService $audit) {}

    public function start(AutomationWorkflow $workflow, StartAutomationWorkflowData $data, User $actor): AutomationWorkflowExecution
    {
        $this->entitlements->requireFeature('automations.access');
        $context = $data->context->toArray();
        $this->validateContext($context);
        $dispatch = false;
        $execution = DB::transaction(function () use ($workflow, $data, $actor, $context, &$dispatch) {
            $existing = AutomationWorkflowExecution::forTenant($this->tenant->id())->where('idempotency_key', $data->idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
            $w = AutomationWorkflow::forTenant($this->tenant->id())->with(['currentPublishedVersion.steps', 'tenant'])->whereKey($workflow->id)->lockForUpdate()->firstOrFail();
            $v = $w->currentPublishedVersion;
            if (! $w->is_enabled || $w->status->value === 'archived' || ! $v || $v->validation_status !== 'valid' || ! $v->definition_hash) {
                throw ValidationException::withMessages(['workflow' => 'Workflow is not executable.']);
            }
            if ($v->trigger_type->value !== 'manual') {
                throw ValidationException::withMessages(['workflow' => 'Only manual workflows can execute.']);
            }
            if (! hash_equals($v->definition_hash, $this->hasher->hash($v))) {
                throw ValidationException::withMessages(['workflow' => 'definition_hash_mismatch']);
            }
            $active = ['pending', 'running', 'waiting', 'cancelling'];
            if (AutomationWorkflowExecution::forTenant($w->tenant_id)->whereIn('status', $active)->count() >= config('automations.execution.active_per_tenant') || AutomationWorkflowExecution::where('automation_workflow_id', $w->id)->whereIn('status', $active)->count() >= config('automations.execution.active_per_workflow')) {
                throw ValidationException::withMessages(['workflow' => 'Active execution limit reached.']);
            }
            $entry = $v->steps->sortBy('position')->first()?->step_key;
            if (! $entry) {
                throw ValidationException::withMessages(['workflow' => 'Entry step is missing.']);
            } $settings = $v->settings ?? [];
            $e = AutomationWorkflowExecution::create(['tenant_id' => $w->tenant_id, 'automation_workflow_id' => $w->id, 'automation_workflow_version_id' => $v->id, 'status' => AutomationWorkflowExecutionStatus::Pending, 'trigger_type' => 'manual', 'definition_hash' => $v->definition_hash, 'idempotency_key' => $data->idempotencyKey, 'current_step_key' => $entry, 'context' => $context, 'maximum_steps' => min((int) ($settings['maximum_steps'] ?? 25), config('automations.execution.max_steps')), 'maximum_execution_minutes' => min((int) ($settings['maximum_execution_minutes'] ?? 60), config('automations.execution.max_minutes')), 'created_by' => $actor->id]);
            $this->audit->recordDomain('automation_workflow.execution_started', $actor, $w->tenant, $e, ['workflow_uuid' => $w->uuid, 'workflow_version_uuid' => $v->uuid, 'execution_uuid' => $e->uuid, 'status' => $e->status->value]);
            $dispatch = true;

            return $e;
        }, 3);
        if ($dispatch) {
            StartAutomationWorkflowExecution::dispatch($execution->id);
        }

        return $execution;
    }

    private function validateContext(array $context): void
    {
        if (array_diff(array_keys($context), ['trigger', 'contact', 'values']) !== [] || array_diff(array_keys($context['trigger'] ?? []), ['type', 'reference']) !== [] || array_diff(array_keys($context['contact'] ?? []), ['uuid']) !== [] || data_get($context, 'trigger.type') !== 'manual' || strlen(json_encode($context, JSON_THROW_ON_ERROR)) > config('automations.execution.context_max_bytes') || ! is_array($context['values'] ?? null)) {
            throw ValidationException::withMessages(['context' => 'Invalid workflow context.']);
        } foreach (($context['values'] ?? []) as $key => $v) {
            if (! is_string($key) || ! preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $key)) {
                throw ValidationException::withMessages(['context' => 'Context value key is invalid.']);
            }
            if (! is_scalar($v) && $v !== null) {
                throw ValidationException::withMessages(['context' => 'Context values must be scalar.']);
            }
        } $uuid = data_get($context, 'contact.uuid');
        if ($uuid !== null && (! is_string($uuid) || ! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $uuid))) {
            throw ValidationException::withMessages(['context' => 'Contact UUID is invalid.']);
        }
    }
}
