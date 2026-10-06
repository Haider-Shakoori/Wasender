<?php

namespace App\Services;

use App\Contracts\TenantContext;
use App\Models\SubscriptionPlan;
use App\Services\Billing\PaymentGatewayRegistry;

final class TenantSubscriptionQuery
{
    public function __construct(private TenantContext $context, private SubscriptionUsageRegistry $usage, private PaymentGatewayRegistry $gateways) {}

    public function overview(): array
    {
        $tenant = $this->context->get();
        $subscription = $tenant->currentSubscription()->with('plan.features')->first();
        $tenant->setRelation('currentSubscription', $subscription);
        $limits = collect();
        if ($subscription) {
            $limits = $subscription->plan->features->where('value_type', 'integer')->map(fn ($f) => ['key' => $f->feature_key, 'label' => config('subscriptions.limits')[$f->feature_key] ?? $f->feature_key, 'limit' => $f->integer_value, 'unlimited' => $f->is_unlimited, 'usage' => $this->usage->usage($tenant, $f->feature_key)]);
        }

        return [
            'subscription' => $subscription,
            'limits' => $limits,
            'messageUsage' => $subscription ? $this->usage->summary($tenant, 'messages.monthly') : null,
            'payments' => $tenant->billingPayments()->latest('paid_at')->limit(10)->get(),
            'publicPlans' => SubscriptionPlan::with('features')->where('status', 'active')->where('is_public', true)->orderBy('sort_order')->get(),
            'paymentGateways' => $this->gateways->enabled(),
        ];
    }
}
