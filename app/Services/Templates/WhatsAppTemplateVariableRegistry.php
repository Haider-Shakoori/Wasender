<?php

namespace App\Services\Templates;

use App\Data\Templates\TemplateVariableDefinition;
use App\Enums\TemplateVariableContext;
use InvalidArgumentException;

final class WhatsAppTemplateVariableRegistry
{
    private array $definitions;

    public function __construct()
    {
        $generic = [TemplateVariableContext::Generic, TemplateVariableContext::Contact];
        $contact = [TemplateVariableContext::Contact];
        $items = [
            new TemplateVariableDefinition('first_name', $contact, 'Alex'), new TemplateVariableDefinition('last_name', $contact, 'Morgan'),
            new TemplateVariableDefinition('full_name', $contact, 'Alex Morgan'), new TemplateVariableDefinition('display_name', $contact, 'Alex'),
            new TemplateVariableDefinition('company', $contact, 'Example Company'), new TemplateVariableDefinition('phone', $contact, '+15555550100'),
            new TemplateVariableDefinition('email', $contact, 'alex@example.com'), new TemplateVariableDefinition('preferred_language', $contact, 'English'),
            new TemplateVariableDefinition('timezone', $contact, 'UTC'), new TemplateVariableDefinition('current_date', $generic, '2026-01-15'),
            new TemplateVariableDefinition('current_time', $generic, '12:00:00'), new TemplateVariableDefinition('current_datetime', $generic, '2026-01-15T12:00:00+00:00'),
        ];
        foreach ($items as $item) {
            if (isset($this->definitions[$item->key])) {
                throw new InvalidArgumentException("Duplicate template variable: {$item->key}");
            }
            $this->definitions[$item->key] = $item;
        }
        ksort($this->definitions, SORT_STRING);
    }

    public function all(): array
    {
        return array_values($this->definitions);
    }

    public function find(string $key): ?TemplateVariableDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    public function require(string $key): TemplateVariableDefinition
    {
        return $this->find($key) ?? throw new InvalidArgumentException("Unknown template variable: {$key}");
    }

    public function forContext(TemplateVariableContext $context): array
    {
        return array_values(array_filter($this->definitions, fn ($definition) => $definition->supports($context)));
    }
}
