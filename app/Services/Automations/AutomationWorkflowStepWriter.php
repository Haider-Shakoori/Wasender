<?php

namespace App\Services\Automations;

use App\Data\Automations\AutomationWorkflowStepData;
use App\Models\AutomationWorkflowStep;
use App\Models\AutomationWorkflowVersion;

final class AutomationWorkflowStepWriter
{
    public function replace(AutomationWorkflowVersion $v, array $steps): void
    {
        $v->steps()->delete();
        $this->copy($v, $steps);
    }

    public function copy(AutomationWorkflowVersion $v, iterable $steps): void
    {
        foreach ($steps as $s) {
            $dto = $s instanceof AutomationWorkflowStepData;
            AutomationWorkflowStep::create(['automation_workflow_version_id' => $v->id, 'step_key' => $dto ? $s->key : (is_object($s) ? $s->step_key : $s['step_key']), 'parent_step_key' => $dto ? $s->parentKey : (is_object($s) ? $s->parent_step_key : ($s['parent_step_key'] ?? null)), 'step_type' => $dto ? $s->type : (is_object($s) ? $s->step_type : $s['step_type']), 'position' => is_object($s) ? $s->position : $s['position'], 'name' => is_object($s) ? $s->name : ($s['name'] ?? null), 'configuration' => is_object($s) ? $s->configuration : ($s['configuration'] ?? [])]);
        }
    }
}
