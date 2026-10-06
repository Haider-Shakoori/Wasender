<?php

namespace Database\Factories;

use App\Enums\PlanFeatureValueType;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

final class SubscriptionPlanFeatureFactory extends Factory
{
    protected $model = SubscriptionPlanFeature::class;

    public function definition(): array
    {
        return ['plan_id' => SubscriptionPlan::factory(), 'feature_key' => 'dashboard.access', 'value_type' => PlanFeatureValueType::Boolean, 'boolean_value' => true, 'is_unlimited' => false];
    }

    public function unlimited(string $key = 'team_members.max'): static
    {
        return $this->state(['feature_key' => $key, 'value_type' => PlanFeatureValueType::Integer, 'boolean_value' => null, 'integer_value' => null, 'is_unlimited' => true]);
    }
}
