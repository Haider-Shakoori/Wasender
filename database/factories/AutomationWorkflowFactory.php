<?php

namespace Database\Factories;

use App\Enums\AutomationWorkflowStatus;
use App\Models\AutomationWorkflow;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

final class AutomationWorkflowFactory extends Factory
{
    protected $model = AutomationWorkflow::class;

    public function definition(): array
    {
        return ['tenant_id' => Tenant::factory(), 'name' => fake()->unique()->words(3, true), 'status' => AutomationWorkflowStatus::Draft, 'is_enabled' => false, 'created_by' => User::factory(), 'updated_by' => User::factory()];
    }

    public function archived(): self
    {
        return $this->state(['status' => AutomationWorkflowStatus::Archived, 'is_enabled' => false, 'archived_at' => now()]);
    }
}
