<?php

namespace App\Services\Automations;

use App\Data\Automations\CreateAutomationWorkflowData;
use App\Enums\AutomationActionType;
use App\Enums\AutomationStepType;
use App\Enums\AutomationTriggerType;
use Illuminate\Validation\ValidationException;

final class AutomationWorkflowDefinitionValidator
{
    public function validate(CreateAutomationWorkflowData $d, bool $publishing = false): void
    {
        if (! in_array($d->triggerType, AutomationTriggerType::values(), true)) {
            $this->fail('trigger_type', 'Unsupported trigger type.');
        }if (count($d->steps) > config('automations.max_steps') || ($publishing && $d->steps === [])) {
            $this->fail('steps', 'A publishable workflow needs a bounded non-empty step list.');
        }$keys = [];
        $positions = [];
        foreach ($d->steps as $s) {
            if (isset($keys[$s->key]) || isset($positions[$s->position])) {
                $this->fail('steps', 'Step keys and positions must be unique.');
            }$keys[$s->key] = true;
            $positions[$s->position] = true;
            if (! in_array($s->type, AutomationStepType::values(), true)) {
                $this->fail('steps', 'Unknown step type.');
            }if ($s->type === 'action' && ! in_array($s->configuration['action_type'] ?? '', AutomationActionType::values(), true)) {
                $this->fail('steps', 'Unknown action type.');
            }$this->bounded($s->configuration, (int) config('automations.step_config_max_bytes'));
        }foreach ($d->steps as $s) {
            if ($s->parentKey && ! isset($keys[$s->parentKey])) {
                $this->fail('steps', 'Parent step key is invalid.');
            }
        }$this->bounded($d->triggerConfiguration, (int) config('automations.config_max_bytes'));
        $this->bounded($d->settings, (int) config('automations.config_max_bytes'));
        foreach (['maximum_execution_minutes', 'maximum_steps', 'maximum_active_executions_per_contact'] as $k) {
            if (isset($d->settings[$k])) {
                [$min,$max] = config("automations.settings.$k");
                if (! is_int($d->settings[$k]) || $d->settings[$k] < $min || $d->settings[$k] > $max) {
                    $this->fail('settings', "Invalid $k.");
                }
            }
        }if (isset($d->settings['allow_reentry']) && ! is_bool($d->settings['allow_reentry'])) {
            $this->fail('settings', 'allow_reentry must be boolean.');
        }
    }

    private function bounded(array $v, int $bytes): void
    {
        if (strlen(json_encode($v, JSON_THROW_ON_ERROR)) > $bytes || $this->depth($v) > config('automations.max_nesting_depth')) {
            $this->fail('configuration', 'Configuration exceeds safety limits.');
        }$encoded = json_encode($v, JSON_THROW_ON_ERROR);
        if (preg_match('/(?:<\?php|\{!!|\{\{|\b(?:eval|class|shell|queue|node_endpoint|javascript|sql)\b)/i', $encoded)) {
            $this->fail('configuration', 'Executable or unsafe expressions are not allowed.');
        }
    }

    private function depth(array $v, int $d = 1): int
    {
        $m = $d;
        foreach ($v as $x) {
            if (is_array($x)) {
                $m = max($m, $this->depth($x, $d + 1));
            }
        }

        return $m;
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
