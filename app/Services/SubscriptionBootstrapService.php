<?php

namespace App\Services;

use App\Enums\SubscriptionSource;
use App\Enums\TenantSubscriptionStatus;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionStatusHistory;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SubscriptionBootstrapService
{
    public function assignDefault(Tenant $tenant, ?User $actor = null, ?string $planSlug = null, ?int $trialDays = null, SubscriptionSource $source = SubscriptionSource::Trial): TenantSubscription
    {
        return DB::transaction(function () use ($tenant, $actor, $planSlug, $trialDays, $source) {
            $existing = TenantSubscription::where('tenant_id', $tenant->id)->current()->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }$slug = $planSlug ?: config('subscriptions.default_plan_slug');
            $plan = SubscriptionPlan::where('slug', $slug)->where('status', 'active')->first() ?? throw new RuntimeException("Configured subscription plan [{$slug}] is unavailable.");
            $days = $trialDays ?? ($plan->trial_days ?: config('subscriptions.default_trial_days'));
            $status = $days > 0 ? TenantSubscriptionStatus::Trialing : TenantSubscriptionStatus::Active;
            $subscription = TenantSubscription::create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => $status, 'source' => $source, 'is_current' => true, 'starts_at' => now(), 'trial_ends_at' => $days > 0 ? now()->addDays($days) : null, 'current_period_starts_at' => now()]);
            SubscriptionStatusHistory::create(['subscription_id' => $subscription->id, 'from_status' => null, 'to_status' => $status, 'reason' => 'Initial subscription assignment', 'actor_type' => $actor ? 'platform' : 'system', 'actor_id' => $actor?->id, 'effective_at' => now(), 'created_at' => now()]);

            return $subscription;
        }, 3);
    }
}
