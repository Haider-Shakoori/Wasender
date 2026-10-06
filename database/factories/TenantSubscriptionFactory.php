<?php

namespace Database\Factories;

use App\Enums\SubscriptionSource;
use App\Enums\TenantSubscriptionStatus;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

final class TenantSubscriptionFactory extends Factory
{
    protected $model = TenantSubscription::class;

    public function definition(): array
    {
        return ['tenant_id' => Tenant::factory(), 'plan_id' => SubscriptionPlan::factory(), 'status' => TenantSubscriptionStatus::Active, 'source' => SubscriptionSource::Manual, 'is_current' => true, 'starts_at' => now(), 'current_period_starts_at' => now()];
    }

    public function trialing(): static
    {
        return $this->state(['status' => TenantSubscriptionStatus::Trialing, 'source' => SubscriptionSource::Trial, 'trial_ends_at' => now()->addDays(14)]);
    }
}
