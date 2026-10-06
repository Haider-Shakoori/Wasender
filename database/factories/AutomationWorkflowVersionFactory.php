<?php

namespace Database\Factories;

use App\Enums\AutomationTriggerType;
use App\Enums\AutomationWorkflowVersionStatus;
use App\Models\AutomationWorkflow;
use App\Models\AutomationWorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

final class AutomationWorkflowVersionFactory extends Factory
{
    protected $model = AutomationWorkflowVersion::class;

    public function definition(): array
    {
        return ['automation_workflow_id' => AutomationWorkflow::factory(), 'version_number' => 1, 'status' => AutomationWorkflowVersionStatus::Draft, 'trigger_type' => AutomationTriggerType::Manual, 'trigger_configuration' => [], 'settings' => ['maximum_steps' => 50, 'allow_reentry' => false]];
    }
}
