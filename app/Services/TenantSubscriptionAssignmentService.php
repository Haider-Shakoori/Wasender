<?php

namespace App\Services;

use App\Enums\SubscriptionSource;
use App\Enums\TenantSubscriptionStatus;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionStatusHistory;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TenantSubscriptionAssignmentService
{
    public function __construct(private PlatformAuditService $audit) {}

    public function assign(Tenant $tenant, SubscriptionPlan $plan, User $actor, string $reason, int $trialDays = 0, ?\DateTimeInterface $startsAt = null, ?\DateTimeInterface $periodEndsAt = null): TenantSubscription
    {
        if ($plan->status->value !== 'active') {
            throw ValidationException::withMessages(['plan' => 'Only active plans may be assigned.']);
        }

        return DB::transaction(function () use ($tenant, $plan, $actor, $reason, $trialDays, $startsAt, $periodEndsAt) {
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            TenantSubscription::where('tenant_id', $tenant->id)->current()->update(['is_current' => false, 'ended_at' => now()]);
            $startsAt ??= now();
            $periodEndsAt ??= $plan->billing_interval->value === 'yearly' ? Carbon::parse($startsAt)->addYear() : Carbon::parse($startsAt)->addMonth();
            $status = $trialDays > 0 ? TenantSubscriptionStatus::Trialing : TenantSubscriptionStatus::Active;
            $sub = TenantSubscription::create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => $status, 'source' => SubscriptionSource::Manual, 'is_current' => true, 'starts_at' => $startsAt, 'trial_ends_at' => $trialDays ? now()->addDays($trialDays) : null, 'current_period_starts_at' => $startsAt, 'current_period_ends_at' => $periodEndsAt, 'assigned_by' => $actor->id]);
            SubscriptionStatusHistory::create(['subscription_id' => $sub->id, 'to_status' => $status, 'reason' => $reason, 'actor_type' => 'platform', 'actor_id' => $actor->id, 'effective_at' => now(), 'created_at' => now()]);
            $this->audit->record('subscription.plan.assigned', $actor, $tenant, ['plan_slug' => $plan->slug, 'reason' => $reason]);

            return $sub;
        }, 3);
    }
}
