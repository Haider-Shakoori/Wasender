<?php

namespace App\Services;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Subscriptions\EntitlementLimit;
use App\Exceptions\TenantFeatureUnavailableException;
use App\Exceptions\TenantLimitExceededException;
use App\Exceptions\TenantSubscriptionInactiveException;
use App\Exceptions\TenantSubscriptionMissingException;
use App\Models\SubscriptionPlan;
use App\Models\TenantSubscription;

final class TenantEntitlementService implements TenantEntitlements
{
    public function __construct(private TenantContext $context, private SubscriptionEntitlementCache $cache, private SubscriptionUsageRegistry $usageRegistry) {}

    public function subscription(): TenantSubscription
    {
        $tenant = $this->context->get();
        if (! $tenant->is_active) {
            throw new TenantSubscriptionInactiveException;
        }$sub = TenantSubscription::with('plan')->where('tenant_id', $tenant->id)->current()->first();
        if (! $sub) {
            throw new TenantSubscriptionMissingException;
        }if (! $sub->status->permitsAccess()) {
            throw new TenantSubscriptionInactiveException;
        }
        $accessEndsAt = $sub->status->value === 'trialing' ? $sub->trial_ends_at : ($sub->status->value === 'grace' ? $sub->grace_ends_at : $sub->current_period_ends_at);
        if ($accessEndsAt?->isPast()) {
            throw new TenantSubscriptionInactiveException;
        }

        return $sub;
    }

    public function plan(): SubscriptionPlan
    {
        return $this->subscription()->plan;
    }

    public function hasFeature(string $key): bool
    {
        if (! array_key_exists($key, config('subscriptions.features'))) {
            return false;
        }$row = $this->cache->forPlan($this->plan())->get($key);

        return $row && $row['type'] === 'boolean' && $row['boolean'] === true;
    }

    public function limit(string $key): EntitlementLimit
    {
        if (! array_key_exists($key, config('subscriptions.limits'))) {
            return new EntitlementLimit(0);
        }$row = $this->cache->forPlan($this->plan())->get($key);

        return $row && $row['type'] === 'integer' ? new EntitlementLimit($row['integer'], $row['unlimited']) : new EntitlementLimit(0);
    }

    public function usage(string $key): int
    {
        return $this->usageRegistry->usage($this->context->get(), $key);
    }

    public function remaining(string $key): ?int
    {
        return $this->limit($key)->remaining($this->usage($key));
    }

    public function canConsume(string $key, int $amount = 1): bool
    {
        return $amount > 0 && $this->limit($key)->permits($this->usage($key), $amount);
    }

    public function requireFeature(string $key): void
    {
        if (! $this->hasFeature($key)) {
            throw new TenantFeatureUnavailableException($key);
        }
    }

    public function requireCapacity(string $key, int $amount = 1): void
    {
        if (! $this->canConsume($key, $amount)) {
            throw new TenantLimitExceededException($key);
        }
    }
}
