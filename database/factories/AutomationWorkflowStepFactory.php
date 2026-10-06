<?php

namespace Database\Factories;

use App\Enums\AutomationStepType;
use App\Models\AutomationWorkflowStep;
use App\Models\AutomationWorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

final class AutomationWorkflowStepFactory extends Factory
{
    protected $model = AutomationWorkflowStep::class;

    public function definition(): array
    {
        return ['automation_workflow_version_id' => AutomationWorkflowVersion::factory(), 'step_key' => 'step_'.fake()->unique()->numberBetween(1, 9999), 'step_type' => AutomationStepType::Stop, 'position' => 1, 'configuration' => []];
    }
}
