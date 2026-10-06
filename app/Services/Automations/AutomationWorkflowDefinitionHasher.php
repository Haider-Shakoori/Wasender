<?php

namespace App\Services\Automations;

use App\Models\AutomationWorkflowVersion;

final class AutomationWorkflowDefinitionHasher
{
    public function hash(AutomationWorkflowVersion $version): string
    {
        $version->loadMissing('steps');
        $steps = $version->steps->sortBy(fn ($s) => [$s->position, $s->step_key])->map(fn ($s) => ['step_key' => $s->step_key, 'parent_step_key' => $s->parent_step_key, 'step_type' => $s->step_type->value, 'position' => $s->position, 'configuration' => $this->canonical($s->configuration ?? [])])->values()->all();
        $value = ['workflow_schema_version' => config('automations.schema_version'), 'condition_schema_version' => config('automations.condition_schema_version'), 'trigger_type' => $version->trigger_type->value, 'trigger_configuration' => $this->canonical($version->trigger_configuration ?? []), 'settings' => $this->canonical($version->settings ?? []), 'steps' => $steps];

        return hash('sha256', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }if (array_is_list($value)) {
            return array_map($this->canonical(...), $value);
        }ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonical($item);
        }

        return $value;
    }
}
