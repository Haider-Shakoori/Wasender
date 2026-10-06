<?php

namespace App\Services\Automations;

use App\Data\Automations\AutomationConditionFieldDefinition;

final class AutomationConditionFieldRegistry
{
    public const CONTACT_FIELDS = ['contact.status', 'contact.consent_status', 'contact.opted_out', 'contact.suppressed', 'contact.blocked', 'contact.source', 'contact.company', 'contact.preferred_language', 'contact.timezone', 'contact.created_at', 'contact.updated_at', 'trigger.type'];

    private array $items;

    public function __construct()
    {
        $string = ['equals', 'not_equals', 'contains', 'starts_with', 'ends_with', 'is_empty', 'is_not_empty'];
        $enum = ['equals', 'not_equals', 'in', 'not_in'];
        $bool = ['is_true', 'is_false'];
        $date = ['before', 'after', 'between', 'in_last_days', 'is_empty', 'is_not_empty'];
        $types = ['contact.status' => 'enum', 'contact.consent_status' => 'enum', 'contact.opted_out' => 'boolean', 'contact.suppressed' => 'boolean', 'contact.blocked' => 'boolean', 'contact.source' => 'enum', 'contact.company' => 'string', 'contact.preferred_language' => 'string', 'contact.timezone' => 'string', 'contact.created_at' => 'datetime', 'contact.updated_at' => 'datetime', 'trigger.type' => 'enum', 'trigger.changed_fields' => 'string_list'];
        foreach ($types as $key => $type) {
            $ops = match ($type) {
                'string' => $string,'enum' => $enum,'boolean' => $bool,'datetime' => $date,'string_list' => ['contains', 'contains_any', 'contains_all']
            };
            $triggers = $key === 'trigger.changed_fields' ? ['contact_updated'] : [];
            $this->items[$key] = new AutomationConditionFieldDefinition($key, str($key)->after('.')->replace('_', ' ')->headline()->toString(), $type, $ops, $triggers, $type === 'string_list' ? 25 : 1);
        }
    }

    public function all(): array
    {
        return array_values($this->items);
    }

    public function find(string $key): ?AutomationConditionFieldDefinition
    {
        return $this->items[$key] ?? null;
    }
}
