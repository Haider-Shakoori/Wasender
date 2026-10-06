<?php

namespace App\Services\Chatbots;

use App\Enums\WhatsAppChatbotMatchType;
use App\Models\Contact;
use App\Models\WhatsAppChatbotRule;

final class WhatsAppChatbotMatcher
{
    public function matches(WhatsAppChatbotRule $rule, string $text, ?Contact $contact): bool
    {
        $incoming = $this->normalize($text);

        return match ($rule->match_type) {
            WhatsAppChatbotMatchType::Any => $incoming !== '',
            WhatsAppChatbotMatchType::Exact => hash_equals($this->normalize((string) $rule->match_value), $incoming),
            WhatsAppChatbotMatchType::Contains => str_contains($incoming, $this->normalize((string) $rule->match_value)),
            WhatsAppChatbotMatchType::StartsWith => str_starts_with($incoming, $this->normalize((string) $rule->match_value)),
            WhatsAppChatbotMatchType::Condition => $contact !== null && $this->condition((array) $rule->condition_definition, $contact),
        };
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? '');
    }

    private function condition(array $node, Contact $contact): bool
    {
        if (($node['type'] ?? '') === 'group') {
            $results = array_map(fn (array $child) => $this->condition($child, $contact), $node['children'] ?? []);

            return ($node['logic'] ?? 'and') === 'and' ? ! in_array(false, $results, true) : in_array(true, $results, true);
        }
        $field = str($node['field'] ?? '')->after('contact.')->toString();
        $actual = match ($field) {
            'opted_out' => (bool) $contact->opted_out_at, 'suppressed' => (bool) $contact->suppressed_at, 'blocked' => (bool) $contact->blocked_at,
            'status', 'consent_status', 'source' => $contact->{$field}?->value,
            'company', 'preferred_language', 'timezone' => $contact->{$field},
            default => null,
        };
        $expected = $node['value'] ?? null;

        return match ($node['operator'] ?? '') {
            'equals' => $actual == $expected, 'not_equals' => $actual != $expected,
            'contains' => is_string($actual) && str_contains(mb_strtolower($actual), mb_strtolower((string) $expected)),
            'starts_with' => is_string($actual) && str_starts_with(mb_strtolower($actual), mb_strtolower((string) $expected)),
            'is_empty' => blank($actual), 'is_not_empty' => filled($actual), 'is_true' => $actual === true, 'is_false' => $actual === false,
            'in' => in_array($actual, (array) $expected, true), 'not_in' => ! in_array($actual, (array) $expected, true), default => false,
        };
    }
}
