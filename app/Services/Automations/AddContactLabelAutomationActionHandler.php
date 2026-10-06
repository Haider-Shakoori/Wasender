<?php

namespace App\Services\Automations;

use App\Contracts\AutomationActionHandler;
use App\Data\Automations\AutomationActionExecutionContext;
use App\Data\Automations\AutomationActionResult;
use App\Enums\AutomationActionType;
use App\Models\ContactLabel;

final class AddContactLabelAutomationActionHandler implements AutomationActionHandler
{
    public function __construct(private AutomationActionRuntime $runtime, private ContactAutomationMutationService $mutations) {}

    public function supports(AutomationActionType $type): bool
    {
        return $type === AutomationActionType::AddContactLabel;
    }

    public function execute(AutomationActionExecutionContext $context): AutomationActionResult
    {
        [$e,$actor,$contact] = $this->runtime->resolve($context);
        $label = ContactLabel::where('tenant_id', $e->tenant_id)->where('uuid', $context->parameters['label_uuid'])->first();
        if (! $label) {
            return new AutomationActionResult(false, failureCode: 'label_missing');
        }

        return new AutomationActionResult(true, output: ['changed' => $this->mutations->label($contact, $label, true, $actor), 'resource_uuid' => $label->uuid]);
    }
}
