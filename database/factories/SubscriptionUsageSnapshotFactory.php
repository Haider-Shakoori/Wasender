<?php

namespace Database\Factories;

use App\Models\SubscriptionUsageSnapshot;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

final class SubscriptionUsageSnapshotFactory extends Factory
{
    protected $model = SubscriptionUsageSnapshot::class;

    public function definition(): array
    {
        return ['tenant_id' => Tenant::factory(), 'metric_key' => 'team_members.max', 'value' => fake()->numberBetween(0, 10), 'period_starts_at' => now()->startOfDay(), 'captured_at' => now()];
    }
}
