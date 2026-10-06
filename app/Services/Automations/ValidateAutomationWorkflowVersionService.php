<?php

namespace App\Services\Automations;

use App\Data\Automations\AutomationWorkflowValidationResult;
use App\Models\AutomationWorkflowVersion;

final class ValidateAutomationWorkflowVersionService
{
    public function __construct(private AutomationTriggerConfigurationValidator $triggers, private AutomationWorkflowStepConfigurationValidator $steps, private AutomationWorkflowGraphValidator $graph, private AutomationWorkflowDefinitionHasher $hasher) {}

    public function validate(AutomationWorkflowVersion $version, bool $publishing = false, bool $persist = true): AutomationWorkflowValidationResult
    {
        $version->loadMissing(['workflow', 'steps']);
        $errors = $this->triggers->validate($version->trigger_type->value, $version->trigger_configuration ?? [], $publishing);
        foreach ($version->steps as $step) {
            $errors = array_merge($errors, $this->steps->validate($step, $version->trigger_type->value, $publishing));
        }$errors = array_merge($errors, $this->graph->validate($version->steps));
        $warnings = [];
        if ($version->steps->contains(fn ($s) => $s->step_type->value === 'action')) {
            $warnings[] = ['code' => 'action_execution_deferred', 'field' => 'steps', 'step_key' => null, 'message' => 'Action schemas and execution are deferred to Parts 3 and 4.'];
        }$hash = $errors === [] ? $this->hasher->hash($version) : null;
        $result = new AutomationWorkflowValidationResult($errors === [], $errors, $warnings, $hash);
        if ($persist && $version->status->value === 'draft') {
            $version->forceFill(['definition_hash' => $hash, 'validation_status' => $result->valid ? 'valid' : 'invalid', 'validation_summary' => ['errors' => array_column($errors, 'code'), 'warnings' => array_column($warnings, 'code')], 'schema_version' => config('automations.schema_version'), 'validated_at' => now()])->save();
        }

        return $result;
    }
}
