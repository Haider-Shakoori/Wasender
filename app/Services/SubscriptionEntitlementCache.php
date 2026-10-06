<?php

namespace App\Services;

use App\Models\SubscriptionPlan;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;

final class SubscriptionEntitlementCache
{
    public function __construct(private Repository $cache) {}

    public function forPlan(SubscriptionPlan $plan): Collection
    {
        return collect($this->cache->rememberForever($this->key($plan), fn () => $plan->features()->get()->keyBy('feature_key')->map(fn ($f) => ['type' => $f->value_type->value, 'boolean' => $f->boolean_value, 'integer' => $f->integer_value, 'string' => $f->string_value, 'unlimited' => $f->is_unlimited])->all()));
    }

    public function forget(SubscriptionPlan $plan): void
    {
        $this->cache->forget($this->key($plan));
    }

    private function key(SubscriptionPlan $plan): string
    {
        return "subscriptions:plan:{$plan->uuid}:{$plan->updated_at?->timestamp}:entitlements";
    }
}
