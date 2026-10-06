<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionPlanStatus;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

final class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return ['name' => fake()->unique()->words(2, true), 'slug' => fake()->unique()->slug(), 'description' => fake()->sentence(), 'status' => SubscriptionPlanStatus::Active, 'is_public' => true, 'is_featured' => false, 'sort_order' => 0, 'billing_interval' => BillingInterval::Monthly, 'price_amount' => null, 'price_currency' => 'USD', 'trial_days' => 0, 'grace_days' => 3, 'is_system' => false];
    }

    public function archived(): static
    {
        return $this->state(['status' => SubscriptionPlanStatus::Archived, 'is_public' => false]);
    }
}
