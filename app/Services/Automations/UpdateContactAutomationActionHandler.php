<?php

namespace App\Services\Automations;

use App\Contracts\AutomationActionHandler;
use App\Data\Automations\AutomationActionExecutionContext;
use App\Data\Automations\AutomationActionResult;
use App\Enums\AutomationActionType;

final class UpdateContactAutomationActionHandler implements AutomationActionHandler
{
    public function __construct(private AutomationActionRuntime $runtime, private AutomationActionValueResolver $resolver, private ContactAutomationMutationService $mutations) {}

    public function supports(AutomationActionType $type): bool
    {
        return $type === AutomationActionType::UpdateContact;
    }

    public function execute(AutomationActionExecutionContext $context): AutomationActionResult
    {
        [$e,$actor,$contact] = $this->runtime->resolve($context);
        $values = [];
        foreach ($context->parameters['updates'] as $field => $mapping) {
            $value = $this->resolver->resolve($mapping, $contact, $context->context);
            if (! is_string($value) && $value !== null) {
                return new AutomationActionResult(false, failureCode: 'invalid_contact_field');
            }$limit = in_array($field, ['first_name', 'last_name'], true) ? 100 : 120;
            if (is_string($value) && mb_strlen($value) > $limit) {
                return new AutomationActionResult(false, failureCode: 'invalid_contact_field');
            }if ($field === 'timezone' && $value !== null) {
                try {
                    new \DateTimeZone($value);
                } catch (\Throwable) {
                    return new AutomationActionResult(false, failureCode: 'invalid_contact_field');
                }
            }$values[$field] = $value;
        }

        return new AutomationActionResult(true, output: ['changed_fields' => $this->mutations->update($contact, $values, $actor)]);
    }
}
