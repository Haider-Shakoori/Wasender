<?php

namespace Database\Factories;

use App\Enums\TenantSubscriptionStatus;
use App\Models\SubscriptionStatusHistory;
use App\Models\TenantSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

final class SubscriptionStatusHistoryFactory extends Factory
{
    protected $model = SubscriptionStatusHistory::class;

    public function definition(): array
    {
        return ['subscription_id' => TenantSubscription::factory(), 'from_status' => TenantSubscriptionStatus::Trialing, 'to_status' => TenantSubscriptionStatus::Active, 'reason' => 'Test transition', 'actor_type' => 'system', 'effective_at' => now(), 'created_at' => now()];
    }
}
