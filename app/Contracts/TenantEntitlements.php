<?php

namespace App\Contracts;

use App\Data\Subscriptions\EntitlementLimit;
use App\Models\SubscriptionPlan;
use App\Models\TenantSubscription;

interface TenantEntitlements
{
    public function hasFeature(string $featureKey): bool;

    public function limit(string $limitKey): EntitlementLimit;

    public function usage(string $limitKey): int;

    public function remaining(string $limitKey): ?int;

    public function canConsume(string $limitKey, int $amount = 1): bool;

    public function requireFeature(string $featureKey): void;

    public function requireCapacity(string $limitKey, int $amount = 1): void;

    public function subscription(): TenantSubscription;

    public function plan(): SubscriptionPlan;
}
