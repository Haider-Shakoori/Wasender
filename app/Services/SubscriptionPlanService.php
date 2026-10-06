<?php

namespace App\Services;

use App\Enums\PlanFeatureValueType;
use App\Enums\SubscriptionPlanStatus;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SubscriptionPlanService
{
    public function __construct(private SubscriptionEntitlementCache $cache, private PlatformAuditService $audit) {}

    public function save(array $data, User $actor, ?SubscriptionPlan $plan = null): SubscriptionPlan
    {
        return DB::transaction(function () use ($data, $actor, $plan) {
            $plan ??= new SubscriptionPlan;
            $slug = Str::slug($data['slug'] ?? $data['name']);
            if (SubscriptionPlan::where('slug', $slug)->when($plan->exists, fn ($q) => $q->whereKeyNot($plan->id))->exists()) {
                throw ValidationException::withMessages(['slug' => 'The plan slug is already used.']);
            }$plan->fill(['name' => trim($data['name']), 'slug' => $slug, 'description' => $data['description'] ?? null, 'status' => $data['status'], 'is_public' => (bool) ($data['is_public'] ?? false), 'is_featured' => (bool) ($data['is_featured'] ?? false), 'sort_order' => (int) ($data['sort_order'] ?? 0), 'billing_interval' => $data['billing_interval'], 'price_amount' => $data['price_amount'] ?? null, 'price_currency' => strtoupper($data['price_currency'] ?? 'USD'), 'trial_days' => (int) ($data['trial_days'] ?? 0), 'grace_days' => (int) ($data['grace_days'] ?? 0), 'is_system' => $plan->is_system ?? false])->save();
            $rows = [];
            foreach (config('subscriptions.features') as $key => $label) {
                $rows[$key] = ['value_type' => PlanFeatureValueType::Boolean, 'boolean_value' => in_array($key, $data['features'] ?? [], true), 'integer_value' => null, 'is_unlimited' => false];
            }foreach (config('subscriptions.limits') as $key => $label) {
                $unlimited = (bool) ($data['limit_unlimited'][$key] ?? false);
                $value = $data['limits'][$key] ?? null;
                if (! $unlimited && $value !== null && (! is_numeric($value) || (int) $value < 0)) {
                    throw ValidationException::withMessages(["limits.{$key}" => 'Limits must be zero or greater.']);
                }$rows[$key] = ['value_type' => PlanFeatureValueType::Integer, 'boolean_value' => null, 'integer_value' => $unlimited ? null : (int) ($value ?? 0), 'is_unlimited' => $unlimited];
            }foreach ($rows as $key => $row) {
                $plan->features()->updateOrCreate(['feature_key' => $key], $row);
            }$plan->touch();
            $this->cache->forget($plan);
            $this->audit->record($plan->wasRecentlyCreated ? 'subscription.plan.created' : 'subscription.plan.updated', $actor, $plan, ['slug' => $plan->slug]);

            return $plan->refresh();
        }, 3);
    }

    public function archive(SubscriptionPlan $plan, User $actor): void
    {
        if ($plan->is_system) {
            throw ValidationException::withMessages(['plan' => 'System plans cannot be archived.']);
        }$plan->update(['status' => SubscriptionPlanStatus::Archived, 'is_public' => false]);
        $this->cache->forget($plan);
        $this->audit->record('subscription.plan.archived', $actor, $plan);
    }
}
