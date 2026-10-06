<?php

namespace App\Services\Automations;

use App\Data\Automations\AutomationTriggerDefinition;
use App\Enums\AutomationTriggerType;

final class AutomationTriggerRegistry
{
    private array $items;

    public function __construct()
    {
        foreach (AutomationTriggerType::cases() as $type) {
            $supported = in_array($type, [AutomationTriggerType::Manual, AutomationTriggerType::ContactCreated, AutomationTriggerType::ContactUpdated, AutomationTriggerType::ContactAddedToGroup, AutomationTriggerType::ContactLabelAssigned, AutomationTriggerType::ConsentGranted, AutomationTriggerType::ConsentWithdrawn, AutomationTriggerType::ScheduledDatetime], true);
            $fields = match ($type) {
                AutomationTriggerType::ContactUpdated => ['tracked_fields'],AutomationTriggerType::ContactAddedToGroup => ['group_uuid'],AutomationTriggerType::ContactLabelAssigned => ['label_uuid'],AutomationTriggerType::ScheduledDatetime => ['timezone', 'run_at'],default => []
            };
            $contact = $type !== AutomationTriggerType::Manual && $type !== AutomationTriggerType::ScheduledDatetime;
            $this->items[$type->value] = new AutomationTriggerDefinition($type, str($type->value)->replace('_', ' ')->headline()->toString(), 'Reserved automation trigger.', $supported, $fields, $contact ? AutomationConditionFieldRegistry::CONTACT_FIELDS : ['trigger.type'], $contact);
        }
    }

    public function all(): array
    {
        return array_values($this->items);
    }

    public function find(string $type): ?AutomationTriggerDefinition
    {
        return $this->items[$type] ?? null;
    }

    public function require(string $type): AutomationTriggerDefinition
    {
        return $this->find($type) ?? throw new \InvalidArgumentException('Unknown trigger type.');
    }
}
