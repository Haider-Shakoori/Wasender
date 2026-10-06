<?php

namespace App\Services\Automations;

use App\Contracts\TenantEntitlements;
use App\Data\Automations\AutomationActionExecutionContext;
use App\Models\AutomationWorkflowExecution;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class AutomationActionRuntime
{
    public function __construct(private TenantEntitlements $entitlements) {}

    public function resolve(AutomationActionExecutionContext $context, bool $contactRequired = true): array
    {
        $this->entitlements->requireFeature('automations.access');
        $execution = AutomationWorkflowExecution::with(['tenant', 'workflow', 'version'])->where('uuid', $context->executionUuid)->firstOrFail();
        if ($execution->cancel_requested_at || $execution->status->value !== 'running') {
            throw ValidationException::withMessages(['action' => 'execution_cancelled']);
        }$actor = User::find($execution->created_by);
        if (! $actor) {
            throw ValidationException::withMessages(['action' => 'permission_denied']);
        }$contact = null;
        if ($contactRequired) {
            $uuid = data_get($context->context, 'contact.uuid');
            $contact = Contact::where('tenant_id', $execution->tenant_id)->where('uuid', $uuid)->whereNull('deleted_at')->first();
            if (! $contact) {
                throw ValidationException::withMessages(['action' => 'contact_missing']);
            }
        }

        return [$execution, $actor, $contact];
    }
}
