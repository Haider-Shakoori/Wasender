<?php

namespace App\Services\Automations;

use App\Enums\AutomationActionType;
use App\Models\AutomationWorkflowStep;

final class AutomationWorkflowStepConfigurationValidator
{
    public function __construct(private AutomationConditionDefinitionValidator $conditions, private AutomationActionConfigurationValidator $actions) {}

    public function validate(AutomationWorkflowStep $step, string $triggerType, bool $publishing = false): array
    {
        $c = $step->configuration ?? [];
        $errors = [];
        $allowed = match ($step->step_type->value) {
            'action' => ['action_type', 'parameters', 'next_step_key'],'condition' => ['condition', 'next_step_key'],'branch' => ['condition', 'true_step_key', 'false_step_key'],'delay' => ['delay_type', 'seconds', 'next_step_key'],'stop' => ['reason'],default => []
        };
        if ($step->step_type->value === 'stop' && array_intersect(array_keys($c), ['next_step_key', 'true_step_key', 'false_step_key']) !== []) {
            return [$this->error('stop_step_has_outgoing_edge', 'configuration', $step, 'Stop steps cannot have outgoing edges.')];
        }
        if (array_diff(array_keys($c), $allowed) !== []) {
            return [$this->error('unsupported_action_configuration', 'configuration', $step, 'Unknown step configuration key.')];
        }if ($step->step_type->value === 'action' && (! in_array($c['action_type'] ?? '', AutomationActionType::values(), true) || ! is_array($c['parameters'] ?? null))) {
            $errors[] = $this->error('unsupported_action_configuration', 'configuration', $step, 'Action placeholder is malformed.');
        } elseif ($step->step_type->value === 'action') {
            $errors = array_merge($errors, $this->actions->validate($step, $publishing));
        }if ($step->step_type->value === 'delay' && (($c['delay_type'] ?? null) !== 'duration' || ! is_int($c['seconds'] ?? null) || $c['seconds'] < config('automations.delay_min_seconds') || $c['seconds'] > config('automations.delay_max_seconds'))) {
            $errors[] = $this->error('invalid_delay', 'configuration', $step, 'Delay must be a fixed bounded duration.');
        }if (in_array($step->step_type->value, ['condition', 'branch'], true)) {
            $errors = array_merge($errors, $this->conditions->validate($c['condition'] ?? null, $triggerType, $step->step_key));
        }if ($step->step_type->value === 'branch' && (! is_string($c['true_step_key'] ?? null) || ! is_string($c['false_step_key'] ?? null) || $c['true_step_key'] === $c['false_step_key'])) {
            $errors[] = $this->error('branch_target_missing', 'configuration', $step, 'Binary branch targets are required and must differ.');
        }

        return $errors;
    }

    private function error(string $code, string $field, AutomationWorkflowStep $step, string $message): array
    {
        return ['code' => $code, 'field' => $field, 'step_key' => $step->step_key, 'message' => $message];
    }
}
