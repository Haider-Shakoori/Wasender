<?php

namespace App\Services\Automations;

final class AutomationConditionDefinitionValidator
{
    public function __construct(private AutomationConditionFieldRegistry $fields) {}

    public function validate(mixed $condition, string $triggerType, ?string $stepKey = null): array
    {
        $errors = [];
        $rules = 0;
        $walk = function (mixed $node, int $depth) use (&$walk, &$errors, &$rules, $triggerType, $stepKey): void {
            if (! is_array($node) || $depth > config('automations.condition_max_depth')) {
                $errors[] = $this->error('invalid_condition', 'condition', $stepKey, 'Condition nesting is invalid.');

                return;
            }if (($node['type'] ?? 'group') === 'group') {
                if (array_diff(array_keys($node), ['type', 'version', 'logic', 'children']) !== [] || ($node['logic'] ?? null) !== 'and' && ($node['logic'] ?? null) !== 'or' || ! is_array($node['children'] ?? null)) {
                    $errors[] = $this->error('invalid_condition', 'condition', $stepKey, 'Condition group is malformed.');

                    return;
                }if ($depth === 1 && ($node['version'] ?? null) !== config('automations.condition_schema_version')) {
                    $errors[] = $this->error('invalid_condition', 'condition.version', $stepKey, 'Condition schema version is unsupported.');
                }foreach ($node['children'] as $child) {
                    $walk($child, $depth + 1);
                }

                return;
            }if (($node['type'] ?? null) !== 'rule' || array_diff(array_keys($node), ['type', 'field', 'operator', 'value']) !== []) {
                $errors[] = $this->error('invalid_condition', 'condition', $stepKey, 'Condition rule is malformed.');

                return;
            }$rules++;
            $field = $this->fields->find((string) ($node['field'] ?? ''));
            if (! $field || ($field->triggerTypes !== [] && ! in_array($triggerType, $field->triggerTypes, true)) || ! in_array($node['operator'] ?? '', $field->operators, true)) {
                $errors[] = $this->error('invalid_condition', 'condition.field', $stepKey, 'Condition field or operator is not allowed.');

                return;
            }$value = $node['value'] ?? null;
            if (in_array($node['operator'], ['is_empty', 'is_not_empty', 'is_true', 'is_false'], true) && $value !== null) {
                $errors[] = $this->error('invalid_condition', 'condition.value', $stepKey, 'This operator requires a null value.');
            }if (in_array($node['operator'], ['in', 'not_in', 'between', 'contains_any', 'contains_all'], true) && (! is_array($value) || count($value) > config('automations.condition_max_values'))) {
                $errors[] = $this->error('invalid_condition', 'condition.value', $stepKey, 'Condition value list is invalid.');
            }if (is_string($value) && mb_strlen($value) > 500) {
                $errors[] = $this->error('invalid_condition', 'condition.value', $stepKey, 'Condition value is too long.');
            }
        };
        $walk($condition, 1);
        if ($rules > config('automations.condition_max_rules')) {
            $errors[] = $this->error('invalid_condition', 'condition', $stepKey, 'Condition has too many rules.');
        }if (is_array($condition) && strlen(json_encode($condition, JSON_THROW_ON_ERROR)) > config('automations.step_config_max_bytes')) {
            $errors[] = $this->error('definition_too_large', 'condition', $stepKey, 'Condition is too large.');
        }

        return $errors;
    }

    private function error(string $code, string $field, ?string $stepKey, string $message): array
    {
        return ['code' => $code, 'field' => $field, 'step_key' => $stepKey, 'message' => $message];
    }
}
