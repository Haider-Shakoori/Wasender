<?php

namespace App\Services\Automations;

use App\Data\Automations\AutomationActionDefinition;
use App\Enums\AutomationActionType;

final class AutomationActionRegistry
{
    private array $definitions = [];

    public function __construct()
    {
        foreach (AutomationActionType::cases() as $type) {
            $supported = in_array($type, [AutomationActionType::SendWhatsAppTemplate, AutomationActionType::AddContactLabel, AutomationActionType::RemoveContactLabel, AutomationActionType::AddContactToGroup, AutomationActionType::RemoveContactFromGroup, AutomationActionType::UpdateContact, AutomationActionType::StopWorkflow], true);
            $fields = match ($type) {
                AutomationActionType::SendWhatsAppTemplate => ['template_uuid', 'template_version_uuid', 'template_version_number', 'template_content_hash', 'session_strategy', 'session_uuid', 'variable_mappings'],AutomationActionType::AddContactLabel,AutomationActionType::RemoveContactLabel => ['label_uuid'],AutomationActionType::AddContactToGroup,AutomationActionType::RemoveContactFromGroup => ['group_uuid'],AutomationActionType::UpdateContact => ['updates'],AutomationActionType::StopWorkflow => ['reason'],default => []
            };
            $this->definitions[$type->value] = new AutomationActionDefinition($type, str($type->value)->replace('_', ' ')->headline()->toString(), 'Trusted workflow action.', $supported, $type !== AutomationActionType::StopWorkflow, $type === AutomationActionType::SendWhatsAppTemplate, $fields);
        }
    }

    public function all(): array
    {
        return array_values($this->definitions);
    }

    public function find(string|AutomationActionType $type): ?AutomationActionDefinition
    {
        return $this->definitions[$type instanceof AutomationActionType ? $type->value : $type] ?? null;
    }
}
