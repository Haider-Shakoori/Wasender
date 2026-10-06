<?php

namespace App\Services\Automations;

use App\Contracts\AutomationActionHandler;
use App\Data\Automations\AutomationActionExecutionContext;
use App\Data\Automations\AutomationActionResult;
use App\Enums\AutomationActionType;
use App\Models\ContactGroup;

final class RemoveContactFromGroupAutomationActionHandler implements AutomationActionHandler
{
    public function __construct(private AutomationActionRuntime $runtime, private ContactAutomationMutationService $mutations) {}

    public function supports(AutomationActionType $type): bool
    {
        return $type === AutomationActionType::RemoveContactFromGroup;
    }

    public function execute(AutomationActionExecutionContext $context): AutomationActionResult
    {
        [$e,$actor,$contact] = $this->runtime->resolve($context);
        $group = ContactGroup::where('tenant_id', $e->tenant_id)->where('uuid', $context->parameters['group_uuid'])->where('is_active', true)->first();
        if (! $group) {
            return new AutomationActionResult(false, failureCode: 'group_missing');
        }

        return new AutomationActionResult(true, output: ['changed' => $this->mutations->group($contact, $group, false, $actor), 'resource_uuid' => $group->uuid]);
    }
}
