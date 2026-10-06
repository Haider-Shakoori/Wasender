<?php

namespace App\Services\Automations;

use App\Models\AutomationWorkflowExecution;
use App\Models\Contact;
use RuntimeException;

final class AutomationConditionEvaluator
{
    public function evaluate(array $node, AutomationWorkflowExecution $execution): bool
    {
        if (($node['type'] ?? '') === 'group') {
            $values = array_map(fn (array $child) => $this->evaluate($child, $execution), $node['children'] ?? []);

            return ($node['logic'] ?? 'and') === 'and' ? ! in_array(false, $values, true) : in_array(true, $values, true);
        }
        $actual = $this->value((string) $node['field'], $execution);
        $expected = $node['value'] ?? null;

        return match ($node['operator']) {
            'equals' => $actual == $expected,'not_equals' => $actual != $expected,'contains' => is_array($actual) ? in_array($expected, $actual, true) : (is_string($actual) && str_contains($actual, (string) $expected)),'starts_with' => is_string($actual) && str_starts_with($actual, (string) $expected),'ends_with' => is_string($actual) && str_ends_with($actual, (string) $expected),'in' => in_array($actual, (array) $expected, true),'not_in' => ! in_array($actual, (array) $expected, true),'is_empty' => $actual === null || $actual === '' || $actual === [],'is_not_empty' => $actual !== null && $actual !== '' && $actual !== [],'is_true' => $actual === true,'is_false' => $actual === false,'before' => $actual !== null && strtotime((string) $actual) < strtotime((string) $expected),'after' => $actual !== null && strtotime((string) $actual) > strtotime((string) $expected),'between' => $actual !== null && strtotime((string) $actual) >= strtotime((string) $expected[0]) && strtotime((string) $actual) <= strtotime((string) $expected[1]),'in_last_days' => $actual !== null && strtotime((string) $actual) >= now()->subDays((int) $expected)->getTimestamp(),'contains_any' => count(array_intersect((array) $actual, (array) $expected)) > 0,'contains_all' => array_diff((array) $expected, (array) $actual) === [],default => throw new RuntimeException('condition_evaluation_failed')
        };
    }

    private function value(string $field, AutomationWorkflowExecution $execution): mixed
    {
        $context = $execution->context ?? [];
        if ($field === 'trigger.type') {
            return data_get($context, 'trigger.type');
        } if ($field === 'trigger.changed_fields') {
            return data_get($context, 'trigger.changed_fields', []);
        } if (! str_starts_with($field, 'contact.')) {
            throw new RuntimeException('condition_evaluation_failed');
        } $uuid = data_get($context, 'contact.uuid');
        if (! $uuid) {
            return null;
        }
        $contact = Contact::query()->where('tenant_id', $execution->tenant_id)->where('uuid', $uuid)->first();
        if (! $contact) {
            throw new RuntimeException('contact_missing');
        } $key = substr($field, 8);
        $allowed = ['status', 'consent_status', 'source', 'company', 'preferred_language', 'timezone', 'created_at', 'updated_at'];
        if (in_array($key, $allowed, true)) {
            $value = $contact->{$key};
            if ($value instanceof \BackedEnum) {
                return $value->value;
            } if ($value instanceof \DateTimeInterface) {
                return $value->format(DATE_ATOM);
            }

            return $value;
        }

        return match ($key) {
            'opted_out' => (bool) $contact->opted_out_at,'suppressed' => (bool) $contact->suppressed_at,'blocked' => (bool) $contact->blocked_at,default => throw new RuntimeException('condition_evaluation_failed')
        };
    }
}
